<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-4 py-2 shadow-sm">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h6 text-secondary">
            <i class="bi bi-shield-lock-fill text-primary"></i> Manajemen Mutu & Siklus Aset S41
        </span>
        <div class="d-flex align-items-center">
            <span class="badge bg-info text-dark me-3 px-3 py-2">
                Role: <?= htmlspecialchars($_SESSION['KodeHak'] ?? 'USER'); ?>
            </span>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="fw-semibold"><?= htmlspecialchars($_SESSION['NamaPengguna'] ?? 'User Aktif'); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end text-small shadow" aria-labelledby="dropdownUser1">
                    <!-- ABSOLUTE PATH UNTUK LOGOUT -->
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/auth/logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>