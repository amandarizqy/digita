<?php
session_start();
require_once '../../config/database.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['NamaAkun'])) {
    header("Location: ../auth/login.php");
    exit;
}

// ---------------------------------------------------------
// AREA KERJA BACKEND: 
// Teman timmu bisa melakukan query SELECT/INSERT/UPDATE di sini
// ---------------------------------------------------------


// Atur judul halaman
$page_title = "Master Data - Digita S41";

// Tangkap output view ke dalam variabel $content
ob_start();
// Sesuaikan path ini dengan modul yang sedang dikerjakan
require_once '../../templates/master/index.php'; 
$content = ob_get_clean();

// Render ke dalam layout utama
require_once '../../templates/layouts/base.php';
?>