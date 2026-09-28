<?php
// Pastikan auth_check.php dipanggil pertama kali
require_once 'includes/auth_check.php';

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
     $_GET['page'] = 'perencanaan'; }
     
$page = $_GET['page'] ?? 'dashboard';
if (!array_key_exists($page, $pages)) {
    $page = 'dashboard';
}

$page_title  = $pages[$page] . ' - Digita S41';

// Cek apakah file ada di folder modules (backend) atau templates (frontend langsung)
$module_file = __DIR__ . "/modules/$page/index.php"; 
$template_file = __DIR__ . "/templates/$page/index.php";

ob_start();
if (file_exists($module_file)) {
    // Muat dari modul (yang nanti akan memanggil template-nya sendiri)
    require $module_file;
} elseif (file_exists($template_file)) {
    // Fallback: Jika backend modul belum dibuat, langsung muat template-nya
    require $template_file;
} else {
    echo '<div class="alert alert-warning m-4">Halaman <b>' . htmlspecialchars($pages[$page]) . '</b> belum dibuat/tidak ditemukan.</div>';
}
$content = ob_get_clean();

// Render sidebar + navbar + konten utama
require_once 'templates/layouts/base.php';
?>