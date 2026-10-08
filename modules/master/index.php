<?php
/**
 * Router Inner Modul Master Data
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['NamaAkun'])) {
    header("Location: /modules/auth/login.php");
    exit;
}

$role = $_SESSION['KodeHak'] ?? $_SESSION['Role'] ?? 'GUEST';
$sub  = $_GET['sub'] ?? '';

// Jika AM.UI mencoba mengakses selain pesan, kunci ke pesan
if ($role === 'AM.UI' && $sub !== 'pesan') {
    $sub = 'pesan';
}

// Peta rute sub-modul
$peta_sub = [
    'unit'     => 'unit_Controller.php',
    'ui'       => 'unit_Controller.php',
    'up3'      => 'unit_Controller.php',
    'ulp'      => 'unit_Controller.php',
    'pengguna' => 'pengguna_Controller.php',
    'provider' => 'provider_Controller.php',
    'nomor_server' => 'modem_Controller.php',
    'modem'    => 'modem_Controller.php',
    'pesan'    => 'pesan_Controller.php',
];

if (empty($sub)) {
    $sub = ($role === 'AM.UI') ? 'pesan' : 'unit';
}

if (isset($peta_sub[$sub])) {
    require_once __DIR__ . '/' . $peta_sub[$sub];
    exit;
}

// Fallback
if ($role === 'AM.UI') {
    require_once __DIR__ . '/pesan_Controller.php';
} else {
    require_once __DIR__ . '/unit_Controller.php';
}
exit;