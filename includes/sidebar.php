<?php 
// Ambil URL saat ini untuk mendeteksi menu mana yang sedang aktif
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
        <li class="nav-item mb-1">
            <a href="/modules/master/index.php" class="nav-link text-white <?= (strpos($current_path, 'master') !== false) ? 'active bg-primary' : '' ?>">
                <i class="bi bi-hdd-stack me-2"></i> Master Data
            </a>
        </li>
        <li class="nav-item mb-1">
            <a href="/modules/perencanaan/index.php" class="nav-link text-white <?= (strpos($current_path, 'perencanaan') !== false) ? 'active bg-primary' : '' ?>">
                <i class="bi bi-bar-chart-steps me-2"></i> Perencanaan
            </a>
        </li>
        <li class="nav-item mb-1">
            <a href="/modules/pengadaan/index.php" class="nav-link text-white <?= (strpos($current_path, 'pengadaan') !== false) ? 'active bg-primary' : '' ?>">
                <i class="bi bi-box-seam me-2"></i> Pengadaan & Stok
            </a>
        </li>
        <li class="nav-item mb-1">
            <a href="/modules/pemasangan/index.php" class="nav-link text-white <?= (strpos($current_path, 'pemasangan') !== false) ? 'active bg-primary' : '' ?>">
                <i class="bi bi-tools me-2"></i> Pemasangan
            </a>
        </li>
        <li class="nav-item mb-1">
            <a href="/modules/penggunaan/index.php" class="nav-link text-white <?= (strpos($current_path, 'penggunaan') !== false) ? 'active bg-primary' : '' ?>">
                <i class="bi bi-activity me-2"></i> Penggunaan
            </a>
        </li>
        <li class="nav-item mb-1">
            <a href="/modules/pemeliharaan/index.php" class="nav-link text-white <?= (strpos($current_path, 'pemeliharaan') !== false) ? 'active bg-primary' : '' ?>">
                <i class="bi bi-wrench me-2"></i> Pemeliharaan
            </a>
        </li>
        <li class="nav-item mb-1">
            <a href="/modules/penghapusan/index.php" class="nav-link text-white <?= (strpos($current_path, 'penghapusan') !== false) ? 'active bg-primary' : '' ?>">
                <i class="bi bi-trash me-2"></i> Penghapusan
            </a>
        </li>
        <li class="nav-item mb-1">
            <a href="/modules/laporan/index.php" class="nav-link text-white <?= (strpos($current_path, 'laporan') !== false) ? 'active bg-primary' : '' ?>">
                <i class="bi bi-file-earmark-text me-2"></i> Laporan
            </a>
        </li>
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