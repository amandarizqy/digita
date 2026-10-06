<?php 
require_once __DIR__ . '/../config/database.php';

$kode_hak = $_SESSION['KodeHak'] ?? '';
$menus = [];

// Ambil page aktif dari URL saat ini (default: dashboard)
$current_page = $_GET['page'] ?? 'dashboard';

// Ambil jalur URI saat ini untuk deteksi halaman di luar query parameter ?page=
$request_uri = $_SERVER['REQUEST_URI'] ?? '';

if (!empty($kode_hak)) {
    try {
        $query_menu = "SELECT m.NamaMenu, m.UrlRoute, m.Icon 
                       FROM pengaturan_menu m 
                       JOIN pengaturan_hak_akses h ON m.IdMenu = h.IdMenu 
                       WHERE h.KodeHak = :kode_hak AND m.StatusData = 'AKTIF' 
                       ORDER BY m.Urutan ASC";
        $stmt_menu = $conn->prepare($query_menu);
        $stmt_menu->bindParam(':kode_hak', $kode_hak);
        $stmt_menu->execute();
        $menus = $stmt_menu->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Abaikan error di UI
    }
}
?>
<div class="sidebar d-flex flex-column flex-shrink-0 p-3 bg-dark text-white min-vh-100" style="width: 250px;">
    <a href="/index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none ps-2">
        <i class="bi bi-lightning-charge-fill text-warning fs-4 me-2"></i>
        <span class="fs-4 fw-bold">DIGITA</span>
    </a>
    <hr class="text-secondary">
    <div class="small text-uppercase text-muted fw-bold ps-2 mb-2" style="letter-spacing: 0.5px; font-size: 0.75rem;">Menu Utama</div>
    
    <ul class="nav nav-pills flex-column mb-auto">
        <?php foreach ($menus as $menu): ?>
            <?php 
                // 1. Ekstrak nama modul dari UrlRoute database (misal: "/modules/master/index.php" -> "master")
                $path_parts = explode('/', trim($menu['UrlRoute'], '/'));
                $module_name = $path_parts[1] ?? 'dashboard'; 
                
                // Jika URL tidak mengandung kata 'modules' (misal cuma '/index.php'), anggap sebagai dashboard
                if (strpos($menu['UrlRoute'], 'modules') === false) {
                    $module_name = 'dashboard';
                }

                // 2. LOGIKA PENTING: Tentukan apakah menu ini sedang aktif
                $is_active = false;
                if ($current_page === $module_name) {
                    $is_active = true;
                } elseif ($module_name === 'master' && strpos($request_uri, '/modules/master/') !== false) {
                    // Menyala biru jika URL sedang mengakses controller di modul master
                    $is_active = true;
                }

                $active_class = $is_active ? 'active bg-primary' : '';

                // 3. LOGIKA PENTING: Tentukan tautan URL
                // Jika menu adalah Master Data, langsung arahkan ke unit_Controller.php
                if ($module_name === 'master') {
                    $target_url = "/modules/master/unit_Controller.php";
                } else {
                    $target_url = "/index.php?page=" . htmlspecialchars($module_name);
                }
            ?>
            <li class="nav-item mb-1">
                <a href="<?= $target_url; ?>" class="nav-link text-white <?= $active_class ?>">
                    <i class="bi <?= htmlspecialchars($menu['Icon']) ?> me-2"></i> <?= htmlspecialchars($menu['NamaMenu']) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    
    <hr class="text-secondary">
    <div class="dropdown">
        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle p-2 rounded hover-bg" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-person-circle fs-5 me-2"></i>
            <strong><?= htmlspecialchars($_SESSION['NamaPengguna'] ?? 'Administrator'); ?></strong>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark text-small shadow">
            <li><a class="dropdown-item" href="#">Profil</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="/modules/auth/logout.php">Keluar (Logout)</a></li>
        </ul>
    </div>
</div>