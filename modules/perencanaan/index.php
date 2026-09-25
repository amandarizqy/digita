<?php
session_start();
require_once '../../config/database.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['NamaAkun'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Atur judul halaman & menu aktif
$page_title = "Perencanaan - Digita S41";
$active_menu = 'perencanaan';      // ← ini yang membuat menu Perencanaan menyala

// Ambil parameter action dari URL, jika kosong arahkan ke dashboard
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Mulai menangkap output view ke dalam variabel $content
ob_start();

// ---------------------------------------------------------
// AREA KERJA BACKEND & ROUTING: 
switch ($action) {
    // Kolom Input
    case 'upload_riwayat':
        // Memanggil halaman input dari folder yang sama (modules/perencanaan)
        include 'upload_riwayat.php';
        break;
    case 'input_kepentingan':
        include 'input_kepentingan.php';
        break;
    case 'input_survey':
        include 'input_survey.php';
        break;
    // Kolom Proses    
    case 'proses_risiko':
        include 'proses_risiko.php';
        break;
    case 'proses_prioritas':
        include 'proses_prioritas.php'; 
        break;
    // Kolom Monitoring
    case 'data_riwayat':
        include 'data_riwayat.php';
        break;
    case 'hasil_kepentingan':
        include 'hasil_kepentingan.php'; break;
    case 'hasil_survey':
        include 'hasil_survey.php'; break;
    case 'hasil_risiko':
        include 'hasil_risiko.php'; break;
    case 'hasil_prioritas':
        include 'hasil_prioritas.php'; break;

    default:
        // PENTING: Gunakan ../../ untuk mundur ke root folder digita, 
        // lalu masuk ke folder templates
        include '../../templates/perencanaan/index.php';
        break;
}
// ---------------------------------------------------------

// Selesai menangkap output
$content = ob_get_clean();

// Render ke dalam layout utama
require_once '../../templates/layouts/base.php';
?>