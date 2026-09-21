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
$page_title = "Perencanaan - Digita S41";
$active_menu = 'perencanaan';      // ← ini yang membuat menu Perencanaan menyala

// Tangkap output view ke dalam variabel $content
ob_start();
require_once '../../templates/perencanaan/index.php'; 
$content = ob_get_clean();

// Render ke dalam layout utama
require_once '../../templates/layouts/base.php';
?>

<h3>Modul Perencanaan</h3>
  <div class="card shadow-sm mt-3">
    <div class="card-header fw-bold text-primary">Daftar Perencanaan</div>
    <div class="card-body">Isi tabel / form perencanaan di sini.</div>
  </div>