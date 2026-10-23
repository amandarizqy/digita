<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$root_path = $_SERVER['DOCUMENT_ROOT'];
if (!isset($conn)) {
    if (file_exists($root_path . '/config/database.php')) {
        require_once $root_path . '/config/database.php';
    } else {
        require_once __DIR__ . '/../../config/database.php';
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_role = $_SESSION['KodeHak'] ?? $_SESSION['Role'] ?? 'GUEST';
$is_sa     = ($user_role === 'SA.KP');

$akses_order    = ($is_sa || $user_role === 'TL.UP');
$akses_mutasi   = ($is_sa || $user_role === 'SF.UP');

$role_monitoring = ['SA.KP', 'MB.UI', 'AM.UI', 'AM.AP', 'ML.UP', 'TL.AP', 'TL.UP', 'SF.UI', 'SF.AP', 'SF.UP', 'SF.VD'];
$akses_mon_order  = in_array($user_role, $role_monitoring, true);
$akses_mon_mutasi = in_array($user_role, $role_monitoring, true);

// Parameter Navigasi Utama: eksekusi / monitoring
$menu = $_GET['menu'] ?? 'eksekusi';

// Parameter Sub Entitas: order_pemeliharaan / mutasi_pemeliharaan / monitoring_order / monitoring_mutasi
$sub = $_GET['sub'] ?? '';

if (empty($sub)) {
    if ($menu === 'eksekusi') {
        $sub = 'order_pemeliharaan';
    } else {
        $sub = 'monitoring_order';
    }
}

$page_title = "Modul Pemeliharaan S41 - DIGITA";
?>

<style>
    .sub-entity-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: #495057;
        letter-spacing: 0.5px;
    }
    .btn-sub-pill {
        border-radius: 50rem;
        font-size: 0.825rem;
        padding: 0.35rem 1.1rem;
        font-weight: 500;
        background-color: #ffffff;
        border: 1px solid #ced4da;
        color: #495057;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        transition: all 0.2s ease-in-out;
    }
    .btn-sub-pill:hover {
        background-color: #f8f9fa;
        border-color: #0d6efd;
        color: #0d6efd;
    }
    .btn-sub-pill.active {
        background-color: #e7f1ff;
        border-color: #0d6efd;
        color: #0d6efd;
        font-weight: 600;
    }
</style>

<div class="container-fluid px-3 py-2">
    <!-- Header Title -->
    <div class="mb-3">
        <h4 class="fw-bold text-dark mb-1">Modul Pemeliharaan S41</h4>
        <p class="text-muted small mb-0">Kelola eksekusi order, mutasi pemeliharaan, serta monitoring laporan pemeliharaan perangkat.</p>
    </div>

    <!-- 1. MAIN TAB (HANYA 2: EKSEKUSI & MONITORING) -->
    <div class="card border-0 shadow-sm rounded-4 p-2 mb-3 bg-white">
        <ul class="nav nav-pills d-flex flex-row align-items-center gap-2 mb-0 border-0">
            <li class="nav-item">
                <a class="nav-link text-nowrap rounded-3 px-4 py-2 fw-semibold <?= ($menu === 'eksekusi') ? 'active bg-primary text-white' : 'text-secondary' ?>"
                   href="index.php?page=pemeliharaan&menu=eksekusi&sub=order_pemeliharaan">
                    <i class="bi bi-tools me-2"></i>Eksekusi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-nowrap rounded-3 px-4 py-2 fw-semibold <?= ($menu === 'monitoring') ? 'active bg-primary text-white' : 'text-secondary' ?>"
                   href="index.php?page=pemeliharaan&menu=monitoring&sub=monitoring_order">
                    <i class="bi bi-display me-2"></i>Monitoring
                </a>
            </li>
        </ul>
    </div>

    <!-- 2. SUB ENTITAS (PILL BUTTONS) -->
    <div class="d-flex align-items-center gap-2 mb-3 px-1">
        <span class="sub-entity-label text-uppercase me-1">SUB ENTITAS:</span>

        <?php if ($menu === 'eksekusi'): ?>
            <!-- Sub Entitas untuk Menu Eksekusi -->
            <?php if ($akses_order): ?>
                <a href="index.php?page=pemeliharaan&menu=eksekusi&sub=order_pemeliharaan" 
                   class="btn-sub-pill <?= ($sub === 'order_pemeliharaan') ? 'active' : '' ?>">
                    <?php if ($sub === 'order_pemeliharaan'): ?>
                        <i class="bi bi-check2 text-primary me-1"></i>
                    <?php endif; ?>
                    <i class="bi bi-file-earmark-text me-1"></i> Order Pemeliharaan
                </a>
            <?php endif; ?>

            <?php if ($akses_mutasi): ?>
                <a href="index.php?page=pemeliharaan&menu=eksekusi&sub=mutasi_pemeliharaan" 
                   class="btn-sub-pill <?= ($sub === 'mutasi_pemeliharaan') ? 'active' : '' ?>">
                    <?php if ($sub === 'mutasi_pemeliharaan'): ?>
                        <i class="bi bi-check2 text-primary me-1"></i>
                    <?php endif; ?>
                    <i class="bi bi-arrow-left-right me-1"></i> Mutasi Pemeliharaan
                </a>
            <?php endif; ?>

        <?php else: ?>
            <!-- Sub Entitas untuk Menu Monitoring -->
            <?php if ($akses_mon_order): ?>
                <a href="index.php?page=pemeliharaan&menu=monitoring&sub=monitoring_order" 
                   class="btn-sub-pill <?= ($sub === 'monitoring_order') ? 'active' : '' ?>">
                    <?php if ($sub === 'monitoring_order'): ?>
                        <i class="bi bi-check2 text-primary me-1"></i>
                    <?php endif; ?>
                    <i class="bi bi-display me-1"></i> Monitoring Order Pemeliharaan
                </a>
            <?php endif; ?>

            <?php if ($akses_mon_mutasi): ?>
                <a href="index.php?page=pemeliharaan&menu=monitoring&sub=monitoring_mutasi" 
                   class="btn-sub-pill <?= ($sub === 'monitoring_mutasi') ? 'active' : '' ?>">
                    <?php if ($sub === 'monitoring_mutasi'): ?>
                        <i class="bi bi-check2 text-primary me-1"></i>
                    <?php endif; ?>
                    <i class="bi bi-journal-check me-1"></i> Monitoring Mutasi Pemeliharaan
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- 3. PEMANGGILAN SUB MODULES -->
    <?php
    if ($menu === 'eksekusi') {
        if ($sub === 'mutasi_pemeliharaan' && $akses_mutasi && file_exists(__DIR__ . '/eksekusi_mutasi.php')) {
            include __DIR__ . '/eksekusi_mutasi.php';
        } elseif ($akses_order && file_exists(__DIR__ . '/frm_pemeliharaan.php')) {
            include __DIR__ . '/frm_pemeliharaan.php';
        }
    } else {
        if ($sub === 'monitoring_mutasi' && $akses_mon_mutasi && file_exists(__DIR__ . '/monitoring_mutasi.php')) {
            include __DIR__ . '/monitoring_mutasi.php';
        } elseif ($akses_mon_order && file_exists(__DIR__ . '/monitoring_order.php')) {
            include __DIR__ . '/monitoring_order.php';
        }
    }
    ?>
</div>