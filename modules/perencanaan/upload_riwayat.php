<?php
// modules/perencanaan/upload_riwayat.php

$pesan_sukses = "";
$pesan_error = "";

// ---------------------------------------------------------
// 1. AMBIL UNIT PENGGUNA DARI TABLE master_pengguna BERDASARKAN SESSION LOGIN
// ---------------------------------------------------------
$namaAkun = $_SESSION['NamaAkun'] ?? '';
$unitUp   = '';
$unitAp   = '';
$unitUpi  = '';

if (!empty($namaAkun)) {
    try {
        $stmt_user = $conn->prepare("SELECT UnitUp, UnitAp, UnitUpi FROM master_pengguna WHERE NamaAkun = ?");
        $stmt_user->execute([$namaAkun]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($user_data) {
            $unitUp  = $user_data['UnitUp'] ?? '';
            $unitAp  = $user_data['UnitAp'] ?? '';
            $unitUpi = $user_data['UnitUpi'] ?? '';
        } else {
            $pesan_error = "Data unit pengguna tidak ditemukan di master_pengguna.";
        }
    } catch (PDOException $e) {
        $pesan_error = "Gagal mengambil data unit pengguna: " . $e->getMessage();
    }
} else {
    $pesan_error = "Sesi login tidak valid. Silakan login kembali.";
}

// ---------------------------------------------------------
// 2. PROSES SIMPAN DATA (Versi PDO)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_data'])) {
    $idPel    = $_POST['IdPel'];
    $thBlRek  = $_POST['ThBlRek'];
    $tglBayar = empty($_POST['TglBayar']) ? NULL : $_POST['TglBayar'];
    $rpBK     = empty($_POST['RpBK']) ? NULL : $_POST['RpBK'];
    $rpTag    = empty($_POST['RpTag']) ? NULL : $_POST['RpTag'];
    
    // UnitUp, UnitAp, dan UnitUpi otomatis memakai hasil query master_pengguna di atas

    try {
        $query = "INSERT INTO pelunasan_ap2t (IdPel, ThBlRek, TglBayar, RpBK, RpTag, UnitUp, UnitAp, UnitUpi) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $simpan = $stmt->execute([$idPel, $thBlRek, $tglBayar, $rpBK, $rpTag, $unitUp, $unitAp, $unitUpi]);

        if ($simpan) {
            $pesan_sukses = "Data riwayat pelunasan berhasil ditambahkan!";
        }
    } catch (PDOException $e) {
        $pesan_error = "Gagal menyimpan data: " . $e->getMessage();
    }
}

// ---------------------------------------------------------
// 3. PROSES AMBIL DATA (Versi PDO)
// ---------------------------------------------------------
try {
    $query_tampil = "SELECT * FROM pelunasan_ap2t ORDER BY WaktuData DESC LIMIT 50";
    $stmt = $conn->query($query_tampil);
    
    $data_riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $data_riwayat = [];
    $pesan_error = "Gagal mengambil data: " . $e->getMessage();
}

// ---------------------------------------------------------
// 4. PANGGIL TAMPILAN FRONTEND
// ---------------------------------------------------------
include '../../templates/perencanaan/upload_riwayat.php';
?>