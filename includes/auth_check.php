<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika belum login, lempar kembali ke halaman login
if (!isset($_SESSION['user_id'])) {
    header("Location: /s41_monitoring/modules/auth/login.php");
    exit;
}