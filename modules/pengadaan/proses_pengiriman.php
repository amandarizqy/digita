<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['NamaAkun'])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $metode = $_POST['metode'] ?? 'manual';
    $tgl_kirim = $_POST['tgl_kirim_massal'] ?? $_POST['tgl_kirim'] ?? date('Y-m-d');
    $tujuan_up = $_POST['tujuan_up_massal'] ?? $_POST['tujuan_up'] ?? '';
    $nama_akun = $_SESSION['NamaAkun'];

    if (empty($tujuan_up)) die("Unit tujuan belum dipilih.");

    try {
        $conn->beginTransaction();

        // Ambil data AP dan UPI dari UnitUp tujuan yang dipilih
        $stmt_tujuan = $conn->prepare("SELECT UnitAp, UnitUpi FROM master_up WHERE UnitUp = :up");
        $stmt_tujuan->execute([':up' => $tujuan_up]);
        $tujuan = $stmt_tujuan->fetch(PDO::FETCH_ASSOC);

        if (!$tujuan) throw new Exception("Unit tujuan tidak ditemukan.");

        $kode_ap = $tujuan['UnitAp'];
        $kode_upi = $tujuan['UnitUpi'];

        // 1. Insert Header Pengiriman (Trigger MySQL akan otomatis membuat NoFormulir berdasarkan KodeUp tujuan)
        $query_form = "INSERT INTO formulir_pengiriman (NoFormulir, TglFormulir, NamaAkun, KodeUp, KodeAp, KodeUpi, StatusData) 
                       VALUES ('', :tgl, :akun, :up, :ap, :upi, 'AKTIF')";
        $stmt_form = $conn->prepare($query_form);
        $stmt_form->execute([
            ':tgl'  => $tgl_kirim,
            ':akun' => $nama_akun,
            ':up'   => $tujuan_up,
            ':ap'   => $kode_ap,
            ':upi'  => $kode_upi
        ]);

        // 2. Ambil NoFormulir yang baru digenerate
        $stmt_get_id = $conn->prepare("SELECT NoFormulir FROM formulir_pengiriman WHERE NamaAkun = :akun ORDER BY WaktuData DESC LIMIT 1");
        $stmt_get_id->execute([':akun' => $nama_akun]);
        $no_formulir = $stmt_get_id->fetchColumn();

        if (!$no_formulir) throw new Exception("Gagal menggenerate Nomor Formulir.");

        // Siapkan statement detail & update perpindahan unit alat
        $query_detil = "INSERT INTO formulir_pengiriman_detil (NoFormulir, NoRef, NomorRef, StikerQC, CacatFisik) 
                        VALUES (:no_form, :noref, :nomorref, :qc, :cacat)";
        $stmt_detil = $conn->prepare($query_detil);

        $query_update_barang = "UPDATE master_barang SET UnitUp = :up, UnitAp = :ap, UnitUpi = :upi WHERE NoRef = :noref";
        $stmt_update_barang = $conn->prepare($query_update_barang);

        // --- PROSES CSV ---
        if ($metode === 'excel' && isset($_FILES['file_excel']['tmp_name'])) {
            $file = $_FILES['file_excel']['tmp_name'];
            if (($handle = fopen($file, "r")) !== FALSE) {
                fgetcsv($handle, 1000, ";"); // Abaikan baris header
                
                while (($row = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    $no_ref = trim($row[0] ?? '');
                    // Index 1 (Harga) diabaikan karena tidak dibutuhkan di tabel pengiriman detail
                    $qc     = strtoupper(trim($row[2] ?? 'TIDAK'));
                    $cacat  = strtoupper(trim($row[3] ?? 'YA'));

                    if (empty($no_ref)) continue;

                    $stmt_detil->execute([
                        ':no_form' => $no_formulir, ':noref' => $no_ref, ':nomorref' => $no_ref,
                        ':qc' => $qc, ':cacat' => $cacat
                    ]);

                    // Pindahkan status kepemilikan alat ke Unit Tujuan
                    $stmt_update_barang->execute([
                        ':up' => $tujuan_up, ':ap' => $kode_ap, ':upi' => $kode_upi, ':noref' => $no_ref
                    ]);
                }
                fclose($handle);
            }
        } 
        // --- PROSES MANUAL ---
        elseif ($metode === 'manual') {
            $no_ref = trim($_POST['no_ref'] ?? '');
            $qc     = strtoupper(trim($_POST['stiker_qc'] ?? 'TIDAK'));
            $cacat  = strtoupper(trim($_POST['cacat_fisik'] ?? 'YA'));

            if (!empty($no_ref)) {
                $stmt_detil->execute([
                    ':no_form' => $no_formulir, ':noref' => $no_ref, ':nomorref' => $no_ref,
                    ':qc' => $qc, ':cacat' => $cacat
                ]);

                $stmt_update_barang->execute([
                    ':up' => $tujuan_up, ':ap' => $kode_ap, ':upi' => $kode_upi, ':noref' => $no_ref
                ]);
            }
        }

        $conn->commit();
        header("Location: pengiriman.php?status=sukses");
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        die("Gagal memproses pengiriman: " . $e->getMessage());
    }
}
?>