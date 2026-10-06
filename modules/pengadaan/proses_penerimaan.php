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
$unit_ap   = $_SESSION['UnitAp'] ?? '56610';
$unit_up   = $_SESSION['UnitUp'] ?? '56610';

try {
    if ($action === 'terima_barang' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $no_pengiriman = trim($_POST['no_pengiriman'] ?? ($_POST['no_form'] ?? ''));
        
        if (empty($no_pengiriman)) {
            throw new Exception("Nomor pengiriman kosong.");
        }

        $item_refs = $_POST['item_ref'] ?? [];
        if (empty($item_refs)) {
            throw new Exception("Tidak ada item barang yang dipilih.");
        }

        $tgl_terima = date('Y-m-d'); 
        
        // Buat nomor penerimaan yang selaras dan bersih
        $kode_unit = !empty($unit_up) ? $unit_up : (!empty($unit_ap) ? $unit_ap : '56610');
        $no_penerimaan = trim($kode_unit . date('Ymd') . '-F.C');

        $conn->beginTransaction();

        // PENTING: Nonaktifkan foreign key checks sementara untuk menghindari error relasi CHAR(20)
        $conn->exec("SET foreign_key_checks = 0");

        // 1. Bersihkan data lama jika ada
        $conn->prepare("DELETE FROM formulir_penerimaan_detil WHERE NoFormulir = ?")->execute([$no_penerimaan]);
        $conn->prepare("DELETE FROM formulir_penerimaan WHERE NoFormulir = ?")->execute([$no_penerimaan]);

        // 2. Insert Header Formulir Penerimaan
        $stmt_form = $conn->prepare("INSERT INTO formulir_penerimaan (NoFormulir, TglFormulir, NamaAkun, KodeUp, KodeAp, KodeUpi, StatusData) VALUES (?, ?, ?, ?, ?, ?, 'AKTIF')");
        $stmt_form->execute([
            $no_penerimaan,
            $tgl_terima,
            $nama_akun,
            empty($unit_up) ? NULL : $unit_up,
            empty($unit_ap) ? NULL : $unit_ap,
            empty($unit_upi) ? NULL : $unit_upi
        ]);

        // 3. Insert Detil Penerimaan & Update Master Barang
        $qc_states = $_POST['qc'] ?? [];
        $cacat_states = $_POST['cacat'] ?? [];

        $stmt_detil = $conn->prepare("INSERT INTO formulir_penerimaan_detil (NoFormulir, NoRef, NomorRef, StikerQC, CacatFisik) VALUES (?, ?, ?, ?, ?)");
        $stmt_update_barang = $conn->prepare("UPDATE master_barang SET UnitAp = ?, UnitUp = ? WHERE NoRef = ?");

        foreach ($item_refs as $no_ref) {
            $no_ref = trim($no_ref);
            if (empty($no_ref)) continue;

            $qc_final = $qc_states[$no_ref] ?? 'ADA';
            $cacat_final = $cacat_states[$no_ref] ?? 'TIDAK';

            $stmt_detil->execute([
                $no_penerimaan,
                $no_ref,
                $no_ref,
                $qc_final,
                $cacat_final
            ]);

            $stmt_update_barang->execute([
                empty($unit_ap) ? NULL : $unit_ap,
                empty($unit_up) ? NULL : $unit_up,
                $no_ref
            ]);
        }

        // 4. Update status Pengiriman menjadi DITERIMA
        $stmt_kirim = $conn->prepare("UPDATE formulir_pengiriman SET StatusPengiriman = 'DITERIMA', TglTerima = ?, AkunPenerima = ? WHERE NoFormulir = ?");
        $stmt_kirim->execute([
            $tgl_terima,
            $nama_akun,
            $no_pengiriman
        ]);

        // Aktifkan kembali foreign key checks
        $conn->exec("SET foreign_key_checks = 1");

        $conn->commit();
        header("Location: ../../index.php?page=pengadaan&menu=penerimaan&view=daftar&status=sukses");
        exit;
    }

} catch (Exception $e) {
    if (isset($conn)) {
        // Pastikan foreign key diaktifkan kembali jika terjadi rollback
        try { $conn->exec("SET foreign_key_checks = 1"); } catch (Exception $ex) {}
        if ($conn->inTransaction()) { $conn->rollBack(); }
    }
    die("<script>alert('Gagal SQL: " . addslashes($e->getMessage()) . " (Line: " . $e->getLine() . ")'); window.history.back();</script>");
}
?>