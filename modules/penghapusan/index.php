<?php
// modules/penghapusan/index.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/penghapusan_ui.php';
require_once __DIR__ . '/akses.php';

$menu_grup = [];
foreach (pg_menu_penghapusan() as $kunci => $g) {
    $menu_grup[$kunci] = array_keys($g['items']);
}
$menu_urutan  = array_merge(...array_values($menu_grup));
$menu_pertama = pga_pertama_hapus($menu_urutan);

$action = $_GET['action'] ?? $menu_pertama;

if ($action === '' || !pga_boleh_hapus($action)) {
    $tujuan = '?page=penghapusan&action=' . $menu_pertama;
    echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script><script>
        Swal.fire({icon: "error", title: "Akses ditolak", text: "Anda tidak memiliki wewenang.", confirmButtonText: "OK"})
        .then(() => { location.href = "' . $tujuan . '"; });
    </script>';
    return;
}

switch ($action) {
    case 'form_pk':     include __DIR__ . '/form_pk.php'; break;
    case 'form_order':  include __DIR__ . '/form_order.php'; break;
    case 'form_ba':     include __DIR__ . '/form_ba.php'; break;
    case 'monitor_pk':  
    default:
        include __DIR__ . '/monitor_pk.php'; 
}