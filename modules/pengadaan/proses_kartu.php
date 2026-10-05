<?php
session_start();
require_once '../../config/database.php';

$kode_hak = $_SESSION['KodeHak'] ?? '';
$allowed_roles = ['TL.AP', 'TL.UP', 'SA.KP']; 
if (!isset($_SESSION['NamaAkun']) || !in_array($kode_hak, $allowed_roles)) {
    die("<script>alert('Akses Ditolak.'); window.history.back();</script>");
}

$action = $_GET['action'] ?? '';
$unit_upi = $_SESSION['UnitUpi'] ?? '';
$unit_ap  = $_SESSION['UnitAp'] ?? '';
$unit_up  = $_SESSION['UnitUp'] ?? '';

try {
    $conn->beginTransaction();

    // -----------------------------------------
    // PROSES AKTIVASI PERDANA
    // -----------------------------------------
    if ($action === 'aktivasi' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $metode = $_POST['metode'] ?? 'manual';
        
        $query_insert = "INSERT INTO master_nomor (SimId, KodeProduk, KodeProvider, NomorAkun, JenisProduk, TglAktifasi, KodeUp, KodeAp, KodeUpi, StatusData) 
                         VALUES (:simid, :produk, :provider, :akun, :jenis, :tgl, :up, :ap, :upi, 'AKTIF')
                         ON DUPLICATE KEY UPDATE TglAktifasi = :tgl, StatusData = 'AKTIF'";
        $stmt = $conn->prepare($query_insert);

        if ($metode === 'excel' && isset($_FILES['file_excel']['tmp_name'])) {
            $file = $_FILES['file_excel']['tmp_name'];
            if (($handle = fopen($file, "r")) !== FALSE) {
                fgetcsv($handle, 1000, ";"); // Skip header
                while (($row = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    $simid    = trim($row[0] ?? '');
                    $akun     = trim($row[1] ?? '');
                    $provider = (int)($row[2] ?? 0);
                    $produk   = (int)($row[3] ?? 0);
                    $jenis    = strtoupper(trim($row[4] ?? 'PRABAYAR'));
                    $tgl      = trim($row[5] ?? date('Y-m-d'));

                    if (empty($simid)) continue;

                    $stmt->execute([
                        ':simid' => $simid, ':produk' => $produk, ':provider' => $provider, 
                        ':akun' => $akun, ':jenis' => $jenis, ':tgl' => $tgl,
                        ':up' => $unit_up, ':ap' => $unit_ap, ':upi' => $unit_upi
                    ]);
                }
                fclose($handle);
            }
        } elseif ($metode === 'manual') {
            $stmt->execute([
                ':simid'    => trim($_POST['sim_id']),
                ':produk'   => $_POST['kode_produk'],
                ':provider' => $_POST['kode_provider'],
                ':akun'     => trim($_POST['nomor_akun']),
                ':jenis'    => $_POST['jenis_produk'],
                ':tgl'      => $_POST['tgl_aktifasi'],
                ':up' => $unit_up, ':ap' => $unit_ap, ':upi' => $unit_upi
            ]);
        }
        
        $conn->commit();
        header("Location: ../../index.php?page=pengadaan&menu=kartu&sub=aktivasi&view=daftar&status=sukses");
        exit;
    }

    // -----------------------------------------
    // PROSES ISI PULSA
    // -----------------------------------------
    if ($action === 'pulsa' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $metode = $_POST['metode'] ?? 'manual';
        
        $query_update = "UPDATE master_nomor SET JumlahKredit = :nominal, TglPulsaTerakhir = :tgl WHERE SimId = :simid";
        $stmt = $conn->prepare($query_update);

        if ($metode === 'excel' && isset($_FILES['file_excel']['tmp_name'])) {
            $file = $_FILES['file_excel']['tmp_name'];
            if (($handle = fopen($file, "r")) !== FALSE) {
                fgetcsv($handle, 1000, ";"); // Skip header
                while (($row = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    $simid   = trim($row[0] ?? '');
                    $nominal = (int) preg_replace('/[^0-9]/', '', $row[1] ?? '0');
                    $tgl     = trim($row[2] ?? date('Y-m-d'));

                    if (empty($simid)) continue;

                    $stmt->execute([':nominal' => $nominal, ':tgl' => $tgl, ':simid' => $simid]);
                }
                fclose($handle);
            }
        } elseif ($metode === 'manual') {
            $stmt->execute([
                ':nominal' => (int) preg_replace('/[^0-9]/', '', $_POST['nominal']),
                ':tgl'     => $_POST['tgl_isi'],
                ':simid'   => trim($_POST['sim_id'])
            ]);
        }
        
        $conn->commit();
        header("Location: ../../index.php?page=pengadaan&menu=kartu&sub=pulsa&view=daftar&status=sukses");
        exit;
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    die("<script>alert('Gagal memproses data: " . addslashes($e->getMessage()) . "'); window.history.back();</script>");
}
?>