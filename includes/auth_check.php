<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. BUAT PENDETEKSI PATH DINAMIS
// dirname() akan mengambil nama folder tempat index.php dipanggil
// rtrim() digunakan untuk membersihkan kelebihan garis miring di akhir URL
$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

// 2. PERBAIKI REDIRECT LOGIN
if (!isset($_SESSION['NamaAkun']) || !isset($_SESSION['KodeHak'])) {
    // Sisipkan $base_path di depan URL
    header("Location: " . $base_path . "/modules/auth/login.php");
    exit;
}

$page_request = $_GET['page'] ?? 'dashboard'; 

if ($page_request === 'dashboard' || empty($page_request)) {
    return;
}

require_once __DIR__ . '/../config/database.php';

$kode_hak = $_SESSION['KodeHak'];
$is_authorized = false;

// ==== TAMBAHAN: PEMBATASAN ROLE UNTUK MODUL MASTER DATA ====
// Modul master hanya boleh dibuka oleh role di bawah ini, apa pun isi tabel pengaturan_hak_akses.
// Role lain (misal SF.AP) langsung ditolak walaupun datanya masih tercatat di database.
$role_boleh_master = ['SA.KP', 'SF.UI', 'AM.UI'];

if ($page_request === 'master' && !in_array($kode_hak, $role_boleh_master, true)) {
    echo "<script>alert('Akses Ditolak: Anda tidak memiliki wewenang untuk membuka modul master.'); window.location.href='" . $base_path . "/index.php';</script>";
    exit;
}
// ==== AKHIR TAMBAHAN ====

try {
    $query = "SELECT m.UrlRoute FROM pengaturan_menu m 
              JOIN pengaturan_hak_akses h ON m.IdMenu = h.IdMenu 
              WHERE h.KodeHak = :kode_hak AND m.StatusData = 'AKTIF'";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':kode_hak', $kode_hak);
    $stmt->execute();
    $allowed_menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allowed_menus as $menu) {
        $path_parts = explode('/', trim($menu['UrlRoute'], '/'));
        $module_name = $path_parts[1] ?? 'dashboard';
        
        if (strpos($menu['UrlRoute'], 'modules') === false) {
            $module_name = 'dashboard';
        }
        
        if ($module_name === $page_request) {
            $is_authorized = true;
            break;
        }
    }
} catch (PDOException $e) {
    die("Kesalahan sistem pengecekan akses: " . $e->getMessage());
}

// 3. PERBAIKI REDIRECT JAVASCRIPT JIKA AKSES DITOLAK
if (!$is_authorized) {
    // Sisipkan $base_path pada window.location.href
    echo "<script>alert('Akses Ditolak: Anda tidak memiliki wewenang untuk membuka modul " . htmlspecialchars($page_request) . ".'); window.location.href='" . $base_path . "/index.php';</script>";
    exit;
}
?>