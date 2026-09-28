<?php
session_start();
require_once '../../config/database.php';

$kode_hak = $_SESSION['KodeHak'] ?? '';
$allowed_roles = ['TL.AP', 'TL.UP', 'SA.KP']; 
if (!isset($_SESSION['NamaAkun']) || !in_array($kode_hak, $allowed_roles)) {
    die("Akses Ditolak: Anda tidak memiliki otoritas untuk memproses penerimaan.");
}

$action = $_GET['action'] ?? '';
$nama_akun = $_SESSION['NamaAkun'];
$unit_upi  = $_SESSION['UnitUpi'] ?? '';
$unit_ap   = $_SESSION['UnitAp'] ?? '';
$unit_up   = $_SESSION['UnitUp'] ?? '';

try {
    if ($action === 'terima_barang' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $no_pengiriman = $_POST['no_pengiriman'];
        $tgl_terima = date('Y-m-d'); // Tanggal sistem (misal: 2026-09-25)
        
        // 1. Generate NoFormulir Penerimaan Baru (Format: KODEUP/AP-YYYYMMDD-F.C)
        $kode_unit = !empty($unit_up) ? $unit_up : $unit_ap;
        $no_penerimaan = $kode_unit . date('Ymd') . '-F.C' . rand(10,99); // max 20 char sesuai DB

        $conn->beginTransaction();

        // 2. Insert Header Formulir Penerimaan (Auto-generated)
        $query_form_terima = "INSERT INTO formulir_penerimaan (NoFormulir, TglFormulir, NamaAkun, KodeUp, KodeAp, KodeUpi, StatusData) 
                              VALUES (:no_form, :tgl, :akun, :up, :ap, :upi, 'AKTIF')";
        $stmt_form_terima = $conn->prepare($query_form_terima);
        $stmt_form_terima->execute([
            ':no_form' => $no_penerimaan,
            ':tgl'     => $tgl_terima,
            ':akun'    => $nama_akun,
            ':up'      => empty($unit_up) ? NULL : $unit_up,
            ':ap'      => empty($unit_ap) ? NULL : $unit_ap,
            ':upi'     => $unit_upi
        ]);

        // 3. Pindahkan Barang ke formulir_penerimaan_detil & Update master_barang
        $item_refs = $_POST['item_ref'] ?? [];
        $qc_states = $_POST['qc'] ?? [];
        $cacat_states = $_POST['cacat'] ?? [];

        $query_detil_terima = "INSERT INTO formulir_penerimaan_detil (NoFormulir, NoRef, NomorRef, StikerQC, CacatFisik) 
                               VALUES (:no_form, :noref, :noref_copy, :qc, :cacat)";
        $stmt_detil_terima = $conn->prepare($query_detil_terima);

        $query_update_barang = "UPDATE master_barang SET UnitAp = :ap, UnitUp = :up WHERE NoRef = :noref";
        $stmt_update_barang = $conn->prepare($query_update_barang);

        foreach ($item_refs as $no_ref) {
            $qc_final = $qc_states[$no_ref] ?? 'ADA';
            $cacat_final = $cacat_states[$no_ref] ?? 'TIDAK';

            // Insert detail penerimaan
            $stmt_detil_terima->execute([
                ':no_form' => $no_penerimaan,
                ':noref'   => $no_ref,
                ':noref_copy' => $no_ref,
                ':qc'      => $qc_final,
                ':cacat'   => $cacat_final
            ]);

            // Update kepemilikan aset di master_barang ke Unit Penerima
            $stmt_update_barang->execute([
                ':ap' => empty($unit_ap) ? NULL : $unit_ap,
                ':up' => empty($unit_up) ? NULL : $unit_up,
                ':noref' => $no_ref
            ]);
        }

        // 4. Tutup status Formulir Pengiriman (Selesai/Diterima)
        $query_update_kirim = "UPDATE formulir_pengiriman 
                               SET StatusPengiriman = 'DITERIMA', TglTerima = :tgl, AkunPenerima = :akun 
                               WHERE NoFormulir = :no_pengiriman";
        $stmt_update_kirim = $conn->prepare($query_update_kirim);
        $stmt_update_kirim->execute([
            ':tgl' => $tgl_terima,
            ':akun' => $nama_akun,
            ':no_pengiriman' => $no_pengiriman
        ]);

        $conn->commit();
        header("Location: penerimaan.php?view=daftar&status=sukses");
        exit;
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    die("<script>alert('Gagal: " . addslashes($e->getMessage()) . "'); window.history.back();</script>");
}
?>