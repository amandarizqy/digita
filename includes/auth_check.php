<?php
require_once __DIR__ . '/../config/app.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['NamaAkun'])) {
    header("Location: " . BASE_URL . "/modules/auth/login.php");
    exit;
}