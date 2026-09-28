<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['NamaAkun']) || !isset($_SESSION['KodeHak'])) {
    header("Location: /modules/auth/login.php");
    exit;
}

$page_request = $_GET['page'] ?? 'dashboard'; 

// Dashboard dan root selalu diizinkan bagi yang sudah login
if ($page_request === 'dashboard' || empty($page_request)) {
    return;
}

require_once __DIR__ . '/../config/database.php';

$kode_hak = $_SESSION['KodeHak'];
$is_authorized = false;

try {
    $query = "SELECT m.UrlRoute FROM pengaturan_menu m 
              JOIN pengaturan_hak_akses h ON m.IdMenu = h.IdMenu 
              WHERE h.KodeHak = :kode_hak AND m.StatusData = 'AKTIF'";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':kode_hak', $kode_hak);
    $stmt->execute();
    $allowed_menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allowed_menus as $menu) {
        // Ekstrak nama modul dari database
        $path_parts = explode('/', trim($menu['UrlRoute'], '/'));
        $module_name = $path_parts[1] ?? 'dashboard';
        
        if (strpos($menu['UrlRoute'], 'modules') === false) {
            $module_name = 'dashboard';
        }
        
        // Cocokkan nama modul dengan URL yang sedang diakses pengguna
        if ($module_name === $page_request) {
            $is_authorized = true;
            break;
        }
    }
} catch (PDOException $e) {
    die("Kesalahan sistem pengecekan akses: " . $e->getMessage());
}

if (!$is_authorized) {
    echo "<script>alert('Akses Ditolak: Anda tidak memiliki wewenang untuk membuka modul " . htmlspecialchars($page_request) . ".'); window.location.href='/index.php';</script>";
    exit;
}
?>