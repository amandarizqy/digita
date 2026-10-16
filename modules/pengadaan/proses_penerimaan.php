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

        // --- PERBAIKAN: Ambil Tujuan Sebenarnya dari Formulir Pengiriman ---
        $stmt_cek_kirim = $conn->prepare("SELECT KodeAp_Tujuan, KodeUp_Tujuan, KodeUpi_Tujuan FROM formulir_pengiriman WHERE NoFormulir = ?");
        $stmt_cek_kirim->execute([$no_pengiriman]);
        $data_tujuan = $stmt_cek_kirim->fetch(PDO::FETCH_ASSOC);
        
        if (!$data_tujuan) {
            throw new Exception("Data pengiriman tidak ditemukan di database.");
        }
        
        // Kita gunakan variabel target ini sebagai acuan mutlak lokasi barang
        $target_ap = !empty($data_tujuan['KodeAp_Tujuan']) ? $data_tujuan['KodeAp_Tujuan'] : NULL;
        $target_up = !empty($data_tujuan['KodeUp_Tujuan']) ? $data_tujuan['KodeUp_Tujuan'] : NULL;
        $target_upi = !empty($data_tujuan['KodeUpi_Tujuan']) ? $data_tujuan['KodeUpi_Tujuan'] : $unit_upi;
        // -------------------------------------------------------------------

        $tgl_terima = date('Y-m-d'); 
        $tgl_format = date('Ymd'); 
        
        // 1. Tentukan Unit Kode Prefix (UP / AP / UPI)
        $kode_unit = !empty($target_up) ? $target_up : (!empty($target_ap) ? $target_ap : $target_upi);
        
        // 2. Hitung jumlah form penerimaan hari ini untuk generate urutan (001, 002, dst)
        $stmt_count = $conn->prepare("SELECT COUNT(*) FROM formulir_penerimaan WHERE TglFormulir = ?");
        $stmt_count->execute([$tgl_terima]);
        $jumlah_hari_ini = (int) $stmt_count->fetchColumn();
        $urutan_str = str_pad($jumlah_hari_ini + 1, 3, '0', STR_PAD_LEFT);
        
        // 3. Format Final: KODEUNIT + URUTAN + YYYYMMDD + -F.C (Contoh: 5661000120261016-F.C)
        $no_penerimaan = trim($kode_unit . $urutan_str . $tgl_format . '-F.C');

        $conn->beginTransaction();

        // PENTING: Nonaktifkan foreign key checks sementara untuk menghindari error relasi CHAR(20)
        $conn->exec("SET foreign_key_checks = 0");

        // 1. Bersihkan data lama jika ada
        $conn->prepare("DELETE FROM formulir_penerimaan_detil WHERE NoFormulir = ?")->execute([$no_penerimaan]);
        $conn->prepare("DELETE FROM formulir_penerimaan WHERE NoFormulir = ?")->execute([$no_penerimaan]);

        // 2. Insert Header Formulir Penerimaan menggunakan variabel TARGET
        $stmt_form = $conn->prepare("INSERT INTO formulir_penerimaan (NoFormulir, TglFormulir, NamaAkun, KodeUp, KodeAp, KodeUpi, StatusData) VALUES (?, ?, ?, ?, ?, ?, 'AKTIF')");
        $stmt_form->execute([
            $no_penerimaan,
            $tgl_terima,
            $nama_akun,
            $target_up,
            $target_ap,
            $target_upi
        ]);

        // 3. Insert Detil Penerimaan & Update Master Barang
        $qc_states = $_POST['qc'] ?? [];
        $cacat_states = $_POST['cacat'] ?? [];

        $stmt_detil = $conn->prepare("INSERT INTO formulir_penerimaan_detil (NoFormulir, NoRef, NomorRef, StikerQC, CacatFisik) VALUES (?, ?, ?, ?, ?)");
        
        // UPDATE BARANG JUGA MENGGUNAKAN VARIABEL TARGET
        $stmt_update_barang = $conn->prepare("UPDATE master_barang SET UnitAp = ?, UnitUp = ?, UnitUpi = ? WHERE NoRef = ?");

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

            // Eksekusi pemindahan stok dengan akurat
            $stmt_update_barang->execute([
                $target_ap,
                $target_up,
                $target_upi,
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
        try { $conn->exec("SET foreign_key_checks = 1"); } catch (Exception $ex) {}
        if ($conn->inTransaction()) { $conn->rollBack(); }
    }
    die("<script>alert('Gagal SQL: " . addslashes($e->getMessage()) . " (Line: " . $e->getLine() . ")'); window.history.back();</script>");
}
?>