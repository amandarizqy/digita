<?php
// modules/pengaturan/index.php
// Router modul Pengaturan: index.php?page=pengaturan&sub=...
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$kode_hak = $_SESSION['KodeHak'] ?? '';
$menu     = require __DIR__ . '/../../includes/menu_pengaturan.php';
$sub      = $_GET['sub'] ?? 'update_password';

// Cari sub-menu yang diminta di daftar menu
$target = null;
foreach ($menu as $items) {
    foreach ($items as $item) {
        if ($item['sub'] === $sub) {
            $target = $item;
            break 2;
        }
    }
}

if ($target === null) {
    echo '<div class="alert alert-warning m-4">Halaman pengaturan tidak dikenali.</div>';
} elseif (!in_array($kode_hak, $target['roles'], true)) {
    // Cek role di sisi server (menyembunyikan menu di sidebar saja tidak cukup)
    echo '<div class="alert alert-danger m-4"><strong>Akses ditolak.</strong> '
       . 'Peran Anda tidak memiliki wewenang untuk membuka menu ini.</div>';
} else {
    $file = __DIR__ . '/' . $target['file'];
    if (is_file($file)) {
        require $file;
    } else {
        echo '<div class="alert alert-info m-4">Halaman <b>'
           . htmlspecialchars($target['label']) . '</b> belum dibuat.</div>';
    }
}