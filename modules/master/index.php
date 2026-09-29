<?php
/**
 * Router / Pintu Masuk Modul Master Data
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Gunakan __DIR__ agar alamat file config selalu pas dari mana pun dipanggil
require_once __DIR__ . '/../../config/database.php';

// Verifikasi Session Login Pengguna
if (!isset($_SESSION['NamaAkun'])) {
    header("Location: /modules/auth/login.php");
    exit;
}

// Pemetaan sub-modul master ke file controller masing-masing
$peta_sub = [
    'unit'     => 'unit_Controller.php',
    'pengguna' => 'pengguna_Controller.php',
    'provider' => 'provider_Controller.php',
    'modem'    => 'modem_Controller.php',
    'pesan'    => 'pesan_Controller.php',
];

$sub = $_GET['sub'] ?? 'provider';

if (isset($peta_sub[$sub])) {
    header("Location: /modules/master/" . $peta_sub[$sub]);
    exit;
}

// Fallback default ke provider
header("Location: /modules/master/provider_Controller.php");
exit;