<?php
// modules/perencanaan/index.php
// Dipanggil dari root index.php (?page=perencanaan). Root yang membungkus dengan layout,
// jadi file ini HANYA menampilkan isi halaman (JANGAN panggil base.php di sini).
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/perencanaan_ui.php';
require_once __DIR__ . '/akses.php';
chdir(__DIR__);   // supaya include relatif di file-file perencanaan yang belum dirombak tetap benar

// ---- Hak akses submenu (matriks di akses.php) ----
$menu_grup = [];                                   // ['input' => [action,...], 'proses' => [...], 'monitoring' => [...]]
foreach (pr_menu() as $kunci => $g) $menu_grup[$kunci] = array_keys($g['items']);
$menu_urutan = array_merge(...array_values($menu_grup));
$menu_ditolak = array_values(array_filter($menu_urutan, fn($a) => !pa_boleh($a)));
$menu_pertama = pa_pertama($menu_urutan);          // submenu pertama yang boleh dibuka

$action = $_GET['action'] ?? $menu_pertama;        // tanpa ?action= -> submenu pertama yang boleh

if ($action === '' || !pa_boleh($action)) {
    $tujuan = $menu_pertama !== '' ? pr_url($menu_pertama) : '/index.php';
    echo '<div class="alert alert-danger border-0 shadow-sm rounded-3 m-3" id="pr-akses-ditolak"><i class="bi bi-shield-lock-fill me-2"></i>'
       . '<strong>Akses ditolak.</strong> Anda tidak memiliki wewenang untuk membuka menu ini.</div>'
       . '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script><script>'
       . 'var tujuan = ' . json_encode($tujuan) . ';'
       . 'if (window.Swal) { Swal.fire({icon: "error", title: "Akses ditolak", text: "Anda tidak memiliki wewenang untuk membuka menu ini.", confirmButtonText: "OK", confirmButtonColor: "#0d6efd", allowOutsideClick: false}).then(function () { location.href = tujuan; }); }'
       . 'else { alert("Akses ditolak"); location.href = tujuan; }'
       . '</script>';
    return;
}

switch ($action) {
    case 'upload_riwayat':     include __DIR__ . '/upload_riwayat.php'; break;
    case 'input_kepentingan':  include __DIR__ . '/input_kepentingan.php'; break;
    case 'input_survey':       include __DIR__ . '/input_survey.php'; break;
    case 'proses_risiko':      include __DIR__ . '/proses_risiko.php'; break;
    case 'proses_prioritas':   include __DIR__ . '/proses_prioritas.php'; break;
    case 'data_riwayat':       include __DIR__ . '/data_riwayat.php'; break;
    case 'hasil_kepentingan':  include __DIR__ . '/hasil_kepentingan.php'; break;
    case 'hasil_survey':       include __DIR__ . '/hasil_survey.php'; break;
    case 'hasil_risiko':       include __DIR__ . '/hasil_risiko.php'; break;
    case 'hasil_prioritas':    include __DIR__ . '/hasil_prioritas.php'; break;
    default:
        include __DIR__ . '/' . ($menu_pertama ?: 'upload_riwayat') . '.php';
}

// Cegat klik menu yang tidak diizinkan (SweetAlert2 "Akses ditolak")
if ($menu_ditolak) echo pa_skrip_cegat($menu_grup, $menu_ditolak);
