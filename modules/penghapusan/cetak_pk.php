<?php
// Cetak Formulir Perintah Kerja (PK) Penghapusan, dipanggil dari tabel FPK (Order Pembongkaran) dan Eksekusi > Mutasi Pembongkaran.
// Bisa dicetak sebelum eksekusi (kolom lapangan dikosongkan agar diisi tangan oleh petugas)
// maupun sesudah eksekusi (kolom lapangan terisi dari Berita Acara).
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/penghapusan_helper.php';
require_once __DIR__ . '/../../functions/penghapusan_cetak.php';

if (!isset($_SESSION['NamaAkun']) || !pgh_akses_monitoring()) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$no_fpk = trim((string) ($_GET['no_fpk'] ?? ''));
if ($no_fpk === '') {
    http_response_code(400);
    exit('Nomor FPK tidak ditemukan.');
}

[$sc, $sp] = pgh_scope('p');
try {
    $d = pgc_ambil_fpk($conn, $no_fpk, $sc, $sp);
} catch (PDOException $e) {
    error_log('penghapusan cetak_pk: ' . $e->getMessage());
    http_response_code(500);
    exit('Gagal memuat Perintah Kerja.');
}

if (!$d) {
    http_response_code(404);
    exit('FPK tidak ditemukan atau di luar unit Anda.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Perintah Kerja - <?= pgh_e($d['NoFpk']) ?></title>
    <style><?= pgc_css() ?></style>
</head>
<body onload="window.print()">
<div class="kertas">
    <div class="no-print">
        <button onclick="window.print()" style="padding:8px 15px;background:#0d6efd;color:#fff;border:none;cursor:pointer;font-weight:bold;">Cetak / Simpan PDF</button>
    </div>
    <?= pgc_halaman_pk($conn, $d) ?>
</div>
</body>
</html>