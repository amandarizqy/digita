<?php
session_start();

if (!isset($_SESSION['NamaAkun'])) {
    header("Location: modules/auth/login.php");
    exit;
}

// Daftar halaman yang diizinkan: kunci => judul
$pages = [
    'dashboard'    => 'Dashboard',
    'master'       => 'Master Data',
    'perencanaan'  => 'Perencanaan',
    'pengadaan'    => 'Pengadaan & Stok',
    'pemasangan'   => 'Pemasangan',
    'penggunaan'   => 'Penggunaan',
    'pemeliharaan' => 'Pemeliharaan',
    'penghapusan'  => 'Penghapusan',
    'laporan'      => 'Laporan',
];

$page = $_GET['page'] ?? 'dashboard';
if (!array_key_exists($page, $pages)) {
    $page = 'dashboard';            // whitelist: nama file tidak diambil mentah dari URL
}

$active_menu = $page;               // dipakai sidebar untuk menandai menu aktif
$page_title  = $pages[$page] . ' - Digita S41';

// 1. Tangkap konten halaman yang dipilih
$file = __DIR__ . "/templates/$page/index.php";
ob_start();
if (file_exists($file)) {
    require $file;
} else {
    echo '<div class="alert alert-warning">Halaman <b>' . $pages[$page] . '</b> belum dibuat.</div>';
}
$content = ob_get_clean();

// 2. Render sidebar + navbar + $content
require_once 'templates/layouts/base.php';