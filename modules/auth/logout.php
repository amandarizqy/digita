<?php
// modules/auth/logout.php  -  Proses logout user, lepas tiket sesi halaman Pemutusan & Penyalaan (AMI/AMR/SDU), hapus sesi.
session_start();

// Muat koneksi database ($conn). Sesuaikan dengan cara index.php memuatnya.
require_once __DIR__ . '/../../config/database.php';          // <- contoh, ganti dengan file config Anda yang membuat $conn
require_once __DIR__ . '/../../includes/pg_lepas_sesi.php';

// 1) Lepas tiket DULU, selagi SessionID halaman masih ada di $_SESSION
if (isset($conn) && $conn instanceof PDO) {
    pg_lepas_sesi_tiket($conn);
}

// 2) Baru hapus semua variabel sesi dan sesinya
$_SESSION = [];
session_destroy();

// Arahkan kembali ke index.php di root (otomatis melempar ke login)
header("Location: ../../index.php");
exit;