<?php
// modules/penggunaan/index.php
// Dipanggil dari root index.php (?page=penggunaan). Root yang membungkus dengan layout,
// jadi file ini HANYA menampilkan isi halaman (JANGAN panggil base.php di sini).
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/penggunaan_ui.php';
require_once __DIR__ . '/akses.php';
chdir(__DIR__);   // supaya include relatif tetap benar

// ---- Hak akses submenu (matriks di akses.php) ----
$menu_grup = [];                                   // ['input' => [action,...], 'monitoring' => [...]]
foreach (pg_menu() as $kunci => $g) $menu_grup[$kunci] = array_keys($g['items']);
$menu_urutan  = array_merge(...array_values($menu_grup));
$menu_ditolak = array_values(array_filter($menu_urutan, fn($a) => !pga_boleh($a)));
$menu_pertama = pga_pertama($menu_urutan);         // submenu pertama yang boleh dibuka

$action = $_GET['action'] ?? $menu_pertama;        // tanpa ?action= -> submenu pertama yang boleh

if ($action === '' || !pga_boleh($action)) {
    $tujuan = $menu_pertama !== '' ? pg_url($menu_pertama) : '/index.php';
    echo '<div class="alert alert-danger border-0 shadow-sm rounded-3 m-3" id="pg-akses-ditolak"><i class="bi bi-shield-lock-fill me-2"></i>'
       . '<strong>Akses ditolak.</strong> Anda tidak memiliki wewenang untuk membuka menu ini.</div>'
       . '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script><script>'
       . 'var tujuan = ' . json_encode($tujuan) . ';'
       . 'if (window.Swal) { Swal.fire({icon: "error", title: "Akses ditolak", text: "Anda tidak memiliki wewenang untuk membuka menu ini.", confirmButtonText: "OK", confirmButtonColor: "#0d6efd", allowOutsideClick: false}).then(function () { location.href = tujuan; }); }'
       . 'else { alert("Akses ditolak"); location.href = tujuan; }'
       . '</script>';
    return;
}

switch ($action) {
    // INPUT
    case 'input_dil':          include __DIR__ . '/input_dil.php'; break;
    case 'input_saldo':        include __DIR__ . '/input_saldo.php'; break;
    case 'input_pelunasan':    include __DIR__ . '/input_pelunasan.php'; break;
    case 'input_perintah':     include __DIR__ . '/input_perintah.php'; break;
    // MONITORING
    case 'monitor_dil':        include __DIR__ . '/monitor_dil.php'; break;
    case 'monitor_saldo':      include __DIR__ . '/monitor_saldo.php'; break;
    case 'monitor_pelunasan':  include __DIR__ . '/monitor_pelunasan.php'; break;
    case 'monitor_perintah':   include __DIR__ . '/monitor_perintah.php'; break;
    default:
        include __DIR__ . '/' . ($menu_pertama ?: 'monitor_dil') . '.php';
}

// Cegat klik menu yang tidak diizinkan (SweetAlert2 "Akses ditolak")
if ($menu_ditolak) echo pga_skrip_cegat($menu_grup, $menu_ditolak);