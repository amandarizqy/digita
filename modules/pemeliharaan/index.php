<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Koneksi Database (Aman di Server Intranet PLN maupun Localhost)
$root_path = $_SERVER['DOCUMENT_ROOT'];
if (!isset($conn)) {
    if (file_exists($root_path . '/config/database.php')) {
        require_once $root_path . '/config/database.php';
    } else {
        require_once __DIR__ . '/../../config/database.php';
    }
}

// 1. Evaluasi Hak Akses Role berdasarkan Tabel Otoritas DIGITA
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

// 2. Ambil parameter sub-halaman
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

// Daftar tab yang tampil sesuai hak akses (gaya sama dengan modul Master)
$tab_pemeliharaan = [];
if ($akses_order) {
    $tab_pemeliharaan[] = ['key' => 'order_pemeliharaan',  'label' => 'Order Pemeliharaan',            'icon' => 'bi-tools'];
}
if ($akses_mutasi) {
    $tab_pemeliharaan[] = ['key' => 'mutasi_pemeliharaan', 'label' => 'Mutasi Pemeliharaan',           'icon' => 'bi-arrow-left-right'];
}
if ($akses_mon_order) {
    $tab_pemeliharaan[] = ['key' => 'monitoring_order',    'label' => 'Monitoring Order Pemeliharaan', 'icon' => 'bi-display'];
}
if ($akses_mon_mutasi) {
    $tab_pemeliharaan[] = ['key' => 'monitoring_mutasi',   'label' => 'Monitoring Mutasi Pemeliharaan', 'icon' => 'bi-journal-check'];
}

$page_title = "Modul Pemeliharaan S41 - DIGITA";
?>

<div class="container-fluid px-2 py-2">
    <!-- JUDUL HALAMAN -->
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
            <h4 class="fw-bold text-dark mb-1">Modul Pemeliharaan S41</h4>
            <p class="text-muted small mb-0">Kelola eksekusi order, mutasi pemeliharaan, serta monitoring laporan pemeliharaan perangkat.</p>
        </div>
    </div>

    <!-- SUB MENU PEMELIHARAAN (menyamping rata kiri, gaya sama dengan tab Master) -->
    <div class="card border-0 shadow-sm rounded-4 p-2 mb-3 bg-white">
        <ul class="nav nav-pills d-flex flex-row align-items-center gap-2 mb-0 border-0">
            <?php foreach ($tab_pemeliharaan as $tab): ?>
                <?php $aktif = ($sub === $tab['key']); ?>
                <li class="nav-item">
                    <a class="nav-link text-nowrap rounded-3 fw-semibold <?= $aktif ? 'active bg-primary text-white' : 'text-secondary' ?>"
                       href="index.php?page=pemeliharaan&sub=<?= htmlspecialchars($tab['key']) ?>">
                        <i class="bi <?= htmlspecialchars($tab['icon']) ?> me-2"></i><?= htmlspecialchars($tab['label']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- ROUTER SUB-HALAMAN -->
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