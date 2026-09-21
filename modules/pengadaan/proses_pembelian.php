<?php
session_start();
require_once '../../config/database.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['NamaAkun'])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $metode = $_POST['metode'] ?? 'manual';
    $tgl_beli = $_POST['tgl_beli_massal'] ?? $_POST['tgl_beli'] ?? date('Y-m-d');
    
    // Ambil data sesi
    $nama_akun = $_SESSION['NamaAkun'];
    $unit_upi  = $_SESSION['UnitUpi'] ?? '56'; // UID Banten
    $unit_ap   = $_SESSION['UnitAp'] ?? '56610'; // UP3 Cikokol
    $unit_up   = $_SESSION['UnitUp'] ?? '56610'; // ULP Cikokol

    try {
        $conn->beginTransaction();

        // 1. Insert Header ke formulir_pembelian 
        // NoFormulir dikirim string kosong ('') karena akan diisi otomatis oleh Trigger MySQL
        $query_form = "INSERT INTO formulir_pembelian (NoFormulir, TglBeli, NamaAkun, KodeUp, KodeAp, KodeUpi, StatusData) 
                       VALUES ('', :tgl, :akun, :up, :ap, :upi, 'AKTIF')";
        $stmt_form = $conn->prepare($query_form);
        $stmt_form->execute([
            ':tgl'  => $tgl_beli,
            ':akun' => $nama_akun,
            ':up'   => $unit_up,
            ':ap'   => $unit_ap,
            ':upi'  => $unit_upi
        ]);

        // 2. Ambil NoFormulir yang baru saja dibuat oleh Trigger
        $stmt_get_id = $conn->prepare("SELECT NoFormulir FROM formulir_pembelian WHERE NamaAkun = :akun ORDER BY WaktuData DESC LIMIT 1");
        $stmt_get_id->execute([':akun' => $nama_akun]);
        $no_formulir = $stmt_get_id->fetchColumn();

        if (!$no_formulir) {
            throw new Exception("Gagal mendapatkan Nomor Formulir dari sistem.");
        }

        // Siapkan Statement untuk Master Barang & Detail Pembelian
        // Disesuaikan dengan kolom: NoRef, UnitUp, UnitAp, UnitUpi, StatusData
        $query_barang = "INSERT INTO master_barang (NoRef, UnitUp, UnitAp, UnitUpi, StatusData) 
                         VALUES (:noref, :up, :ap, :upi, 'AKTIF')
                         ON DUPLICATE KEY UPDATE StatusData = 'AKTIF'"; 
                         // Antisipasi jika NoRef sudah ada agar tidak error
        $stmt_barang = $conn->prepare($query_barang);

        // Disesuaikan dengan kolom: NoFormulir, NoRef, StikerQC, CacatFisik, HargaBeli
        $query_detil = "INSERT INTO formulir_pembelian_detil (NoFormulir, NoRef, StikerQC, CacatFisik, HargaBeli) 
                        VALUES (:no_form, :noref, :qc, :cacat, :harga)";
        $stmt_detil = $conn->prepare($query_detil);

        // --- BLOK PROSES FILE CSV ---
        if ($metode === 'excel' && isset($_FILES['file_excel']['tmp_name'])) {
            $file = $_FILES['file_excel']['tmp_name'];
            
            if (($handle = fopen($file, "r")) !== FALSE) {
                fgetcsv($handle, 1000, ";"); // Lewati baris header
                
                while (($row = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    $no_ref = trim($row[0] ?? '');
                    $harga  = (int) preg_replace('/[^0-9]/', '', $row[1] ?? '0');
                    $qc     = strtoupper(trim($row[2] ?? 'TIDAK'));
                    $cacat  = strtoupper(trim($row[3] ?? 'YA'));

                    if (empty($no_ref)) continue;

                    // Insert ke Master Barang
                    $stmt_barang->execute([
                        ':noref' => $no_ref,
                        ':up'    => $unit_up, 
                        ':ap'    => $unit_ap, 
                        ':upi'   => $unit_upi
                    ]);

                    // Insert ke Detail
                    $stmt_detil->execute([
                        ':no_form' => $no_formulir, 
                        ':noref'   => $no_ref, 
                        ':qc'      => $qc, 
                        ':cacat'   => $cacat, 
                        ':harga'   => $harga
                    ]);
                }
                fclose($handle);
            }

        // --- BLOK PROSES MANUAL ---
        } elseif ($metode === 'manual') {
            $no_ref = trim($_POST['no_ref'] ?? '');
            $harga  = (int) preg_replace('/[^0-9]/', '', $_POST['harga'] ?? '0');
            $qc     = strtoupper(trim($_POST['stiker_qc'] ?? 'TIDAK'));
            $cacat  = strtoupper(trim($_POST['cacat_fisik'] ?? 'YA'));

            if (!empty($no_ref)) {
                $stmt_barang->execute([
                    ':noref' => $no_ref,
                    ':up'    => $unit_up, 
                    ':ap'    => $unit_ap, 
                    ':upi'   => $unit_upi
                ]);

                $stmt_detil->execute([
                    ':no_form' => $no_formulir, 
                    ':noref'   => $no_ref, 
                    ':qc'      => $qc, 
                    ':cacat'   => $cacat, 
                    ':harga'   => $harga
                ]);
            }
        }

        $conn->commit();
        
        // Redirect kembali ke form dengan parameter sukses
        header("Location: pembelian.php?status=sukses");
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        die("Gagal memproses data: " . $e->getMessage());
    }
}
?>