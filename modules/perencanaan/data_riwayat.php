<?php
// modules/perencanaan/data_riwayat.php

$pesan_sukses = "";
$pesan_error = "";

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
include '../../templates/perencanaan/data_riwayat.php';
?>