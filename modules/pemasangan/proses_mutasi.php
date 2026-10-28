<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (($_SESSION['KodeHak'] ?? '') !== 'SF.UP') die("Akses Ditolak. Hanya SF.UP yang dapat melakukan Mutasi Pasang.");

$action    = $_GET['action'] ?? '';
$unit_up   = $_SESSION['UnitUp'] ?? '';
$unit_ap   = $_SESSION['UnitAp'] ?? '';
$unit_upi  = $_SESSION['UnitUpi'] ?? '';
$nama_akun = $_SESSION['NamaAkun'] ?? '';

try {
    // 1. FUNGSI FINALISASI SPK OLEH SF.UP (GET Action)
    if ($action === 'finalisasi_spk') {
        $no_form_order = trim($_GET['no_form'] ?? '');
        
        $stmt = $conn->prepare("UPDATE frm_order SET StatusData = 'SELESAI' WHERE NoFormOrder = ?");
        $stmt->execute([$no_form_order]);

        header("Location: ../../index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=daftar&status=sukses_final");
        exit;
    }

    // 2. PROSES PENYIMPANAN FORMULIR MUTASI PASANG (POST Method)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $no_form_order = trim($_POST['no_form_order']);
        $no_ref        = trim($_POST['no_ref']);
        $id_pel        = trim($_POST['id_pel']);
        $sim_id        = trim($_POST['sim_id']);
        $imei_modem    = trim($_POST['imei_modem']);
        $tgl_pasang    = trim($_POST['tgl_pasang']);
        $posisi_pasang = trim($_POST['posisi_pasang']);
        $kondisi_relay = trim($_POST['kondisi_relay']);
        $longitude     = trim($_POST['longitude'] ?? '');
        $latitude      = trim($_POST['latitude'] ?? '');

        // Generate No Form Pasang
        $no_form_pasang = $unit_up . date('YmdHis') . '-PAS';

        $foto_barang = null;
        if (isset($_FILES['foto_barang']) && $_FILES['foto_barang']['error'] === UPLOAD_ERR_OK) {
            $foto_barang = file_get_contents($_FILES['foto_barang']['tmp_name']);
        }

        $foto_pasang = null;
        if (isset($_FILES['foto_pasang']) && $_FILES['foto_pasang']['error'] === UPLOAD_ERR_OK) {
            $foto_pasang = file_get_contents($_FILES['foto_pasang']['tmp_name']);
        }

        $conn->beginTransaction();

        // Cek apakah data pasang untuk alat ini sudah ada sebelumnya
        $stmt_check = $conn->prepare("SELECT NoFormPasang FROM frm_pasang WHERE NoFormOrder = ? AND NoRef = ?");
        $stmt_check->execute([$no_form_order, $no_ref]);
        $existing = $stmt_check->fetch();

        if ($existing) {
            // Update data pasang
            if ($foto_barang !== null && $foto_pasang !== null) {
                $stmt_up = $conn->prepare("UPDATE frm_pasang SET TglPasang=?, KdPetugas='IPM.001', NamaAkun=?, SimId=?, ImeiModem=?, IdPel=?, Longitude=?, Latitude=?, PosisiPasang=?, FotoBarang=?, FotoPasang=?, StatusData='AKTIF' WHERE NoFormOrder=? AND NoRef=?");
                $stmt_up->execute([$tgl_pasang, $nama_akun, $sim_id, $imei_modem, $id_pel, $longitude, $latitude, $posisi_pasang, $foto_barang, $foto_pasang, $no_form_order, $no_ref]);
            } elseif ($foto_barang !== null) {
                $stmt_up = $conn->prepare("UPDATE frm_pasang SET TglPasang=?, KdPetugas='IPM.001', NamaAkun=?, SimId=?, ImeiModem=?, IdPel=?, Longitude=?, Latitude=?, PosisiPasang=?, FotoBarang=?, StatusData='AKTIF' WHERE NoFormOrder=? AND NoRef=?");
                $stmt_up->execute([$tgl_pasang, $nama_akun, $sim_id, $imei_modem, $id_pel, $longitude, $latitude, $posisi_pasang, $foto_barang, $no_form_order, $no_ref]);
            } elseif ($foto_pasang !== null) {
                $stmt_up = $conn->prepare("UPDATE frm_pasang SET TglPasang=?, KdPetugas='IPM.001', NamaAkun=?, SimId=?, ImeiModem=?, IdPel=?, Longitude=?, Latitude=?, PosisiPasang=?, FotoPasang=?, StatusData='AKTIF' WHERE NoFormOrder=? AND NoRef=?");
                $stmt_up->execute([$tgl_pasang, $nama_akun, $sim_id, $imei_modem, $id_pel, $longitude, $latitude, $posisi_pasang, $foto_pasang, $no_form_order, $no_ref]);
            } else {
                $stmt_up = $conn->prepare("UPDATE frm_pasang SET TglPasang=?, KdPetugas='IPM.001', NamaAkun=?, SimId=?, ImeiModem=?, IdPel=?, Longitude=?, Latitude=?, PosisiPasang=?, StatusData='AKTIF' WHERE NoFormOrder=? AND NoRef=?");
                $stmt_up->execute([$tgl_pasang, $nama_akun, $sim_id, $imei_modem, $id_pel, $longitude, $latitude, $posisi_pasang, $no_form_order, $no_ref]);
            }
        } else {
            // Insert data baru pasang
            $stmt_ins = $conn->prepare("INSERT INTO frm_pasang (NoFormPasang, NoFormOrder, TglPasang, KdPetugas, NamaAkun, NoRef, SimId, ImeiModem, IdPel, Longitude, Latitude, PosisiPasang, FotoBarang, FotoPasang, UnitUp, UnitAp, UnitUpi, StatusData) VALUES (?, ?, ?, 'IPM.001', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'AKTIF')");
            $stmt_ins->execute([$no_form_pasang, $no_form_order, $tgl_pasang, $nama_akun, $no_ref, $sim_id, $imei_modem, $id_pel, $longitude, $latitude, $posisi_pasang, $foto_barang, $foto_pasang, $unit_up, $unit_ap, $unit_upi]);
        }

        // Update master_barang untuk mengikat IdPel dan menghidupkan/mematikan Relay
        $status_relay = ($kondisi_relay === 'BERFUNGSI') ? 'ON' : 'OFF';
        $stmt_barang = $conn->prepare("UPDATE master_barang SET IdPel = ?, StatusRelay = ? WHERE NoRef = ?");
        $stmt_barang->execute([$id_pel, $status_relay, $no_ref]);

        $conn->commit();

        header("Location: ../../index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=detail&no_form=" . urlencode($no_form_order) . "&status=sukses_mutasi");
        exit;
    }
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    die("<script>alert('Gagal menyimpan Mutasi Pasang: " . addslashes($e->getMessage()) . "'); window.history.back();</script>");
}