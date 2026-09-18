<?php
session_start();

// Jika sesi NamaAkun belum ada, arahkan ke halaman login
if (!isset($_SESSION['NamaAkun'])) {
    header("Location: modules/auth/login.php");
    exit;
}

$page_title = "Dashboard - Digita S41";

// 1. Tangkap konten spesifik (Dashboard) ke dalam variabel $content DULUAN
ob_start();
require_once 'templates/dashboard/index.php'; 
$content = ob_get_clean();

// 2. BARU panggil base.php untuk merender sidebar, navbar, dan mencetak $content di tengah
require_once 'templates/layouts/base.php';
?>