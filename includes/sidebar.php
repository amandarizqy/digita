<?php 
// Panggil koneksi database
require_once __DIR__ . '/../config/database.php';

$current_path = $_SERVER['REQUEST_URI']; 
?>
<!-- Tambahkan bg-dark dan min-vh-100 agar sidebar berwarna gelap dan tingginya penuh -->
<div class="sidebar d-flex flex-column flex-shrink-0 p-3 bg-dark text-white min-vh-100" style="width: 250px;">
    <a href="/index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none ps-2">
        <i class="bi bi-lightning-charge-fill text-warning fs-4 me-2"></i>
        <span class="fs-4 fw-bold">DIGITA</span>
    </a>
    <hr class="text-secondary">
    <div class="small text-uppercase text-muted fw-bold ps-2 mb-2" style="font-size: 0.75rem;">Menu Utama</div>
    
    <ul class="nav nav-pills flex-column mb-auto">
        <!-- Render menu secara dinamis menggunakan loop -->
        <?php foreach ($menus as $menu): ?>
            <?php 
                // Ekstrak nama folder modul dari URL (misal: 'master' dari '/modules/master/index.php')
                $path_parts = explode('/', trim($menu['UrlRoute'], '/'));
                $module_name = $path_parts[1] ?? '';
                
                // Deteksi menu aktif
                $is_active = (strpos($current_path, $module_name) !== false) ? 'active bg-primary' : '';
            ?>
            <li class="nav-item mb-1">
                <a href="<?= htmlspecialchars($menu['UrlRoute']) ?>" class="nav-link text-white <?= $is_active ?>">
                    <i class="bi <?= htmlspecialchars($menu['Icon']) ?> me-2"></i> <?= htmlspecialchars($menu['NamaMenu']) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <hr class="text-secondary">
    <div class="dropdown">
        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle p-2 rounded hover-bg" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-person-circle fs-5 me-2"></i>
            <strong><?= $_SESSION['NamaPengguna'] ?? 'Administrator'; ?></strong>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark text-small shadow">
            <li><a class="dropdown-item" href="#">Profil</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="/modules/auth/logout.php">Keluar (Logout)</a></li>
        </ul>
    </div>
</div>