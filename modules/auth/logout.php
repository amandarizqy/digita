<?php
session_start();

// Hapus semua variabel sesi
$_SESSION = [];

// Hancurkan sesi sepenuhnya
session_destroy();

// Arahkan kembali ke file index.php di root (yang akan otomatis melempar ke login)
header("Location: ../../index.php");
exit;
?>