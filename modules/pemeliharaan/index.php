<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$root_path = $_SERVER['DOCUMENT_ROOT'];
if (file_exists($root_path . '/config/database.php')) {
    require_once $root_path . '/config/database.php';
} else {
    require_once __DIR__ . '/../../config/database.php';
}

// 1. Evaluasi Hak Akses Role berdasarkan Tabel Otoritas
$user_role = $_SESSION['KodeHak'] ?? $_SESSION['Role'] ?? 'GUEST';
$is_sa     = ($user_role === 'SA.KP');

// Order Pemeliharaan -> Hanya TL.UP & SA.KP
$akses_order    = ($is_sa || $user_role === 'TL.UP');

// Mutasi Pemeliharaan -> Hanya SF.UP & SA.KP
$akses_mutasi   = ($is_sa || $user_role === 'SF.UP');

// Monitoring Order & Mutasi -> Semua Role Terdaftar
$role_monitoring = ['SA.KP', 'MB.UI', 'AM.UI', 'AM.AP', 'ML.UP', 'TL.AP', 'TL.UP', 'SF.UI', 'SF.AP', 'SF.UP', 'SF.VD'];
$akses_mon_order  = in_array($user_role, $role_monitoring, true);
$akses_mon_mutasi = in_array($user_role, $role_monitoring, true);

// 2. Ambil parameter sub
$sub = $_GET['sub'] ?? '';

// Validasi & Proteksi URL jika Role tidak berhak
if ($sub === 'order_pemeliharaan' && !$akses_order) {
    $sub = '';
}
if ($sub === 'mutasi_pemeliharaan' && !$akses_mutasi) {
    $sub = '';
}
if ($sub === 'monitoring_order' && !$akses_mon_order) {
    $sub = '';
}
if ($sub === 'monitoring_mutasi' && !$akses_mon_mutasi) {
    $sub = '';
}

// Default otomatis pilih tab pertama yang diizinkan untuk role tersebut
if (empty($sub)) {
    if ($akses_order) {
        $sub = 'order_pemeliharaan';
    } elseif ($akses_mutasi) {
        $sub = 'mutasi_pemeliharaan';
    } elseif ($akses_mon_order) {
        $sub = 'monitoring_order';
    } elseif ($akses_mon_mutasi) {
        $sub = 'monitoring_mutasi';
    }
}

$page_title = "Modul Pemeliharaan S41 - DIGITA";
?>

<div class="container-fluid px-2 py-2">
    <!-- Header Title -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">Modul Pemeliharaan S41</h3>
            <p class="text-muted small mb-0">Kelola eksekusi order, mutasi pemeliharaan, serta monitoring laporan pemeliharaan perangkat.</p>
        </div>
    </div>

    <!-- Navigasi Sub-Modul Pemeliharaan (Sesuai Tampilan Asli Kamu) -->
    <div class="card border-0 shadow-sm rounded-4 p-2 mb-3 bg-white">
        <ul class="nav nav-pills nav-fill gap-2">
            
            <?php if ($akses_order): ?>
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold <?= $sub === 'order_pemeliharaan' ? 'active bg-primary' : 'text-secondary' ?>" href="/index.php?page=pemeliharaan&sub=order_pemeliharaan">
                        <i class="bi bi-tools me-1"></i> Order Pemeliharaan
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($akses_mutasi): ?>
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold <?= $sub === 'mutasi_pemeliharaan' ? 'active bg-primary' : 'text-secondary' ?>" href="/index.php?page=pemeliharaan&sub=mutasi_pemeliharaan">
                        <i class="bi bi-arrow-left-right me-1"></i> Mutasi Pemeliharaan
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($akses_mon_order): ?>
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold <?= $sub === 'monitoring_order' ? 'active bg-primary' : 'text-secondary' ?>" href="/index.php?page=pemeliharaan&sub=monitoring_order">
                        <i class="bi bi-display me-1"></i> Monitoring Order Pemeliharaan
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($akses_mon_mutasi): ?>
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold <?= $sub === 'monitoring_mutasi' ? 'active bg-primary' : 'text-secondary' ?>" href="/index.php?page=pemeliharaan&sub=monitoring_mutasi">
                        <i class="bi bi-journal-check me-1"></i> Monitoring Mutasi Pemeliharaan
                    </a>
                </li>
            <?php endif; ?>

        </ul>
    </div>

    <!-- Router Sub-Halaman (Sesuai Pemanggilan File Asli Kamu) -->
    <?php
    switch ($sub) {
        case 'mutasi_pemeliharaan':
            if ($akses_mutasi && file_exists(__DIR__ . '/eksekusi_mutasi.php')) {
                include __DIR__ . '/eksekusi_mutasi.php';
            }
            break;

        case 'monitoring_order':
            if ($akses_mon_order && file_exists(__DIR__ . '/monitoring_order.php')) {
                include __DIR__ . '/monitoring_order.php';
            }
            break;

        case 'monitoring_mutasi':
            if ($akses_mon_mutasi && file_exists(__DIR__ . '/monitoring_mutasi.php')) {
                include __DIR__ . '/monitoring_mutasi.php';
            }
            break;

        case 'order_pemeliharaan':
        default:
            if ($akses_order && file_exists(__DIR__ . '/frm_pemeliharaan.php')) {
                include __DIR__ . '/frm_pemeliharaan.php';
            }
            break;
    }
    ?>
</div>