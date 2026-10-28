<?php
// Mengirim satu foto (BLOB) milik satu form pemeliharaan, dengan cek login & batas unit.
// Dipakai <img src="..."> supaya halaman daftar tidak perlu memuat semua foto sekaligus.
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/pemeliharaan_helper.php';

if (!isset($_SESSION['NamaAkun'])) {
    http_response_code(401);
    exit;
}
if (!ph_akses_monitoring()) {
    http_response_code(403);
    exit;
}

$no    = (string) ($_GET['no'] ?? '');
$kolom = ['pasang' => 'FotoPasang', 'barang' => 'FotoBarang'][$_GET['jenis'] ?? ''] ?? null;
if ($no === '' || $kolom === null) {
    http_response_code(400);
    exit;
}

[$sc, $sp] = ph_scope('p');
$st = $conn->prepare("SELECT p.$kolom AS foto FROM frm_pemeliharaan p WHERE p.NoFormPemeliharaan = ?" . $sc . " LIMIT 1");
$st->execute(array_merge([$no], $sp));
$foto = $st->fetchColumn();

if ($foto === false || $foto === null || $foto === '') {
    http_response_code(404);
    exit;
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($foto);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    $mime = 'image/jpeg';
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($foto));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
echo $foto;