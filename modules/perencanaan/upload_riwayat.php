<?php
// modules/perencanaan/upload_riwayat.php

$pesan_sukses = "";
$pesan_error = "";

// 1. PROSES SIMPAN DATA (Versi PDO)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_data'])) {
    $idPel    = $_POST['IdPel'];
    $thBlRek  = $_POST['ThBlRek'];
    $tglBayar = empty($_POST['TglBayar']) ? NULL : $_POST['TglBayar'];
    $rpBK     = empty($_POST['RpBK']) ? NULL : $_POST['RpBK'];
    $rpTag    = empty($_POST['RpTag']) ? NULL : $_POST['RpTag'];
    $unitUp   = $_POST['UnitUp'];
    $unitAp   = $_POST['UnitAp'];
    $unitUpi  = $_POST['UnitUpi'];

    try {
        $query = "INSERT INTO pelunasan_ap2t (IdPel, ThBlRek, TglBayar, RpBK, RpTag, UnitUp, UnitAp, UnitUpi) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        // Di PDO, parameter langsung dimasukkan ke dalam array di method execute()
        $simpan = $stmt->execute([$idPel, $thBlRek, $tglBayar, $rpBK, $rpTag, $unitUp, $unitAp, $unitUpi]);

        if ($simpan) {
            $pesan_sukses = "Data riwayat pelunasan berhasil ditambahkan!";
        }
    } catch (PDOException $e) {
        $pesan_error = "Gagal menyimpan data: " . $e->getMessage();
    }
}

// 2. PROSES AMBIL DATA (Versi PDO)
try {
    $query_tampil = "SELECT * FROM pelunasan_ap2t ORDER BY WaktuData DESC LIMIT 50";
    $stmt = $conn->query($query_tampil);
    
    // fetchAll(PDO::FETCH_ASSOC) langsung mengambil semua baris data menjadi array
    $data_riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $data_riwayat = [];
    $pesan_error = "Gagal mengambil data: " . $e->getMessage();
}

// 3. PANGGIL TAMPILAN FRONTEND
include '../../templates/perencanaan/upload_riwayat.php';
?>