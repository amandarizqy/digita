<?php
// modules/pengaturan/tata_kelola.php -> LOGIKA (controller) halaman Buku Tata Kelola S41
// Dipanggil oleh modules/pengaturan/index.php (?page=pengaturan&sub=tata_kelola)

// ====== UBAH NAMA FILE DI SINI ======
$nama_gambar = 'tata_kelola_s41.jpeg';          // letakkan di assets/img/
$nama_pdf    = 'buku_tata_kelola_s41.pdf';     // letakkan di assets/pdf/
// ====================================

$base = defined('BASE_URL') ? BASE_URL : '';
$root = realpath(__DIR__ . '/../../');

$path_gambar = $root . '/assets/img/' . $nama_gambar;
$path_pdf    = $root . '/assets/pdf/' . $nama_pdf;

// Jika nama persis di atas tidak ketemu, cari file lain yang namanya diawali "tata_kelola"
// (mis. tata_kelola.jpg, tata_kelola_s41.webp) supaya salah nama/ekstensi tidak jadi masalah.
if (!is_file($path_gambar)) {
    foreach (glob($root . '/assets/img/tata_kelola*.{png,jpg,jpeg,webp,gif,PNG,JPG,JPEG}', GLOB_BRACE) ?: [] as $f) {
        $nama_gambar = basename($f);
        $path_gambar = $f;
        break;
    }
}
if (!is_file($path_pdf)) {
    foreach (glob($root . '/assets/pdf/*{tata_kelola,s41,S41}*.{pdf,PDF}', GLOB_BRACE) ?: [] as $f) {
        $nama_pdf = basename($f);
        $path_pdf = $f;
        break;
    }
}

$gambar_ada = is_file($path_gambar);
$pdf_ada    = is_file($path_pdf);

// ?v=waktu-ubah-file supaya browser memuat ulang jika file diganti
$url_gambar = $base . '/assets/img/' . rawurlencode($nama_gambar) . ($gambar_ada ? '?v=' . filemtime($path_gambar) : '');
$url_pdf    = $base . '/assets/pdf/' . rawurlencode($nama_pdf)    . ($pdf_ada    ? '?v=' . filemtime($path_pdf)    : '');

$judul = 'Buku Tata Kelola S41';

require __DIR__ . '/../../templates/pengaturan/tata_kelola.php';