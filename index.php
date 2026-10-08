<?php
// Deteksi protokol HTTP atau HTTPS
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
// Deteksi domain/IP
$domain = $_SERVER['HTTP_HOST'];
// Deteksi path folder
$path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

// Buat konstanta BASE_URL yang bisa dipakai di seluruh file HTML/PHP
define('BASE_URL', $protocol . $domain . $path);

// Pastikan auth_check.php dipanggil pertama kali
require_once 'includes/auth_check.php';

// Deteksi protokol & domain untuk BASE_URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain   = $_SERVER['HTTP_HOST'];
$path     = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

if (!defined('BASE_URL')) {
    define('BASE_URL', $protocol . $domain . $path);
}

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

if (($_GET['module'] ?? '') === 'perencanaan') {
    $_GET['page'] = 'perencanaan'; 
}

$page = $_GET['page'] ?? 'dashboard';

// LOGIKA PENTING: Petakan sub-page Master Data jika dipanggil secara langsung via ?page=
$sub_master_list = ['unit', 'pengguna', 'provider', 'nomor_server', 'modem', 'pesan'];
if (in_array($page, $sub_master_list)) {
    $_GET['sub'] = ($page === 'nomor_server') ? 'modem' : $page;
    $page = 'master';
}

if (!array_key_exists($page, $pages)) {
    $page = 'dashboard';
}

$page_title = $pages[$page] . ' - Digita S41';

// Cek apakah file ada di folder modules atau templates
$module_file   = __DIR__ . "/modules/$page/index.php"; 
$template_file = __DIR__ . "/templates/$page/index.php";

ob_start();
if (file_exists($module_file)) {
    require $module_file;
} elseif (file_exists($template_file)) {
    require $template_file;
} else {
    echo '<div class="alert alert-warning m-4">Halaman <b>' . htmlspecialchars($pages[$page]) . '</b> belum dibuat/tidak ditemukan.</div>';
}
$content = ob_get_clean();

// Render layout utama
require_once 'templates/layouts/base.php';
?>