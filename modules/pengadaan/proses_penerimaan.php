<?php
session_start();
require_once '../../config/database.php';
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$kode_hak = $_SESSION['KodeHak'] ?? '';
$allowed_roles = ['TL.AP', 'TL.UP', 'SA.KP']; 
if (!isset($_SESSION['NamaAkun']) || !in_array($kode_hak, $allowed_roles)) {
    die("Akses Ditolak: Anda tidak memiliki otoritas.");
}

$action = $_GET['action'] ?? '';
$nama_akun = $_SESSION['NamaAkun'] ?? 'System';
$unit_upi  = $_SESSION['UnitUpi'] ?? '56';
$unit_ap   = $_SESSION['UnitAp'] ?? '';
$unit_up   = $_SESSION['UnitUp'] ?? '';

try {
    if ($action === 'terima_barang' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $no_form_kirim = trim($_POST['no_pengiriman'] ?? ($_POST['no_form'] ?? ''));
        
        if (empty($no_form_kirim)) {
            throw new Exception("Nomor pengiriman (NoFormKirim) kosong.");
        }

        $item_refs = $_POST['item_ref'] ?? [];
        if (empty($item_refs)) {
            throw new Exception("Tidak ada item barang yang dipilih.");
        }

        $tgl_terima = date('Y-m-d'); 
        $tgl_format = date('Ymd'); 

        // 1. Ambil informasi tujuan dari formulir pengiriman
        $stmt_cek = $conn->prepare("SELECT KodeApTujuan, KodeUpTujuan, KodeUpiTujuan FROM formulir_pengiriman WHERE NoFormKirim = ?");
        $stmt_cek->execute([$no_form_kirim]);
        $tujuan = $stmt_cek->fetch(PDO::FETCH_ASSOC);

        $target_ap = !empty($tujuan['KodeApTujuan']) ? $tujuan['KodeApTujuan'] : null;
        $target_up = !empty($tujuan['KodeUpTujuan']) ? $tujuan['KodeUpTujuan'] : null;
        $target_upi = !empty($tujuan['KodeUpiTujuan']) ? $tujuan['KodeUpiTujuan'] : $unit_upi;

        $kode_unit = !empty($target_up) ? $target_up : (!empty($target_ap) ? $target_ap : $target_upi);

        // 2. Generate Nomor Formulir Penerimaan otomatis (Format: KODEUNIT + URUTAN + YYYYMMDD + -F.C)
        $stmt_count = $conn->prepare("SELECT COUNT(*) FROM formulir_penerimaan WHERE TglFormulir = ?");
        $stmt_count->execute([$tgl_terima]);
        $urutan = (int) $stmt_count->fetchColumn() + 1;
        $no_penerimaan = $kode_unit . str_pad($urutan, 3, '0', STR_PAD_LEFT) . $tgl_format . '-F.C';

        $conn->beginTransaction();

        // 3. Insert Header Penerimaan
        $stmt_form = $conn->prepare("INSERT INTO formulir_penerimaan (NoFormulir, TglFormulir, NoFormKirim, NamaAkun, UnitUp, UnitAp, UnitUpi, StatusData) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, 'AKTIF')");
        $stmt_form->execute([
            $no_penerimaan,
            $tgl_terima,
            $no_form_kirim,
            $nama_akun,
            $target_up,
            $target_ap,
            $target_upi
        ]);

        // 4. Insert Detail Penerimaan & Update Lokasi Master Barang
        $qc_states = $_POST['qc'] ?? [];
        $cacat_states = $_POST['cacat'] ?? [];

        $stmt_detil = $conn->prepare("INSERT INTO formulir_penerimaan_detil (NoFormulir, NoRef, StikerQC, CacatFisik) VALUES (?, ?, ?, ?)");
        
        // Update lokasi master barang ke unit penerima dengan mempertahankan AP induk jika tujuan berupa UP
        $stmt_update_barang = $conn->prepare("
            UPDATE master_barang b 
            SET 
                b.UnitAp = COALESCE(:target_ap, (SELECT u.UnitAp FROM master_up u WHERE u.UnitUp = :target_up), b.UnitAp), 
                b.UnitUp = :target_up, 
                b.UnitUpi = :target_upi 
            WHERE b.NoRef = :noref
        ");

        foreach ($item_refs as $no_ref) {
            $no_ref = trim($no_ref);
            if (empty($no_ref)) continue;

            $qc_final = $qc_states[$no_ref] ?? 'ADA';
            $cacat_final = $cacat_states[$no_ref] ?? 'TIDAK';

            $stmt_detil->execute([
                $no_penerimaan,
                $no_ref,
                $qc_final,
                $cacat_final
            ]);

            // Eksekusi update dengan mendeteksi AP induk dari master_up jika target_ap kosong
            $stmt_update_barang->execute([
                ':target_ap'  => $target_ap,
                ':target_up'  => $target_up,
                ':target_upi' => $target_upi,
                ':noref'      => $no_ref
            ]);
        }

        $conn->commit();
        header("Location: ../../index.php?page=pengadaan&menu=penerimaan&view=daftar&status=sukses");
        exit;
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) { $conn->rollBack(); }
    die("<script>alert('Gagal SQL: " . addslashes($e->getMessage()) . "'); window.history.back();</script>");
}
?>