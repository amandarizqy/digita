<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Koneksi selalu dari folder proyek ini (bukan dari DOCUMENT_ROOT, supaya tidak salah database)
if (!isset($conn)) {
    require_once __DIR__ . '/../../config/database.php';
}
require_once __DIR__ . '/../../functions/pemeliharaan_helper.php';

// 1. Hak akses per sub-halaman
$boleh = [
    'order_pemeliharaan'  => ph_akses_order(),        // TL.UP & SA.KP
    'mutasi_pemeliharaan' => ph_akses_mutasi(),       // SF.UP & SA.KP
    'monitoring_order'    => ph_akses_monitoring(),   // semua role terdaftar
    'monitoring_mutasi'   => ph_akses_monitoring(),
];

// 2. Struktur menu: menu utama -> sub-halaman
$struktur = [
    'eksekusi' => [
        'label' => 'Eksekusi', 'icon' => 'bi-tools',
        'sub'   => [
            'order_pemeliharaan'  => ['Order Pemeliharaan',  'bi-file-earmark-text', 'frm_pemeliharaan.php'],
            'mutasi_pemeliharaan' => ['Mutasi Pemeliharaan', 'bi-arrow-left-right',  'eksekusi_mutasi.php'],
        ],
    ],
    'monitoring' => [
        'label' => 'Monitoring', 'icon' => 'bi-display',
        'sub'   => [
            'monitoring_order'  => ['Monitoring Order Pemeliharaan',  'bi-display',       'monitoring_order.php'],
            'monitoring_mutasi' => ['Monitoring Mutasi Pemeliharaan', 'bi-journal-check', 'monitoring_mutasi.php'],
        ],
    ],
];

// Hanya tampilkan yang boleh diakses
foreach ($struktur as $kunci_menu => $m) {
    foreach ($m['sub'] as $kunci_sub => $info) {
        if (empty($boleh[$kunci_sub])) {
            unset($struktur[$kunci_menu]['sub'][$kunci_sub]);
        }
    }
    if (!$struktur[$kunci_menu]['sub']) {
        unset($struktur[$kunci_menu]);
    }
}

// 3. Tentukan menu & sub yang aktif (nilai tidak sah / tidak berhak -> halaman pertama yang diizinkan)
$sub  = (string) ($_GET['sub'] ?? '');
$menu = (string) ($_GET['menu'] ?? '');
if ($sub !== '') {
    $menu = ph_menu_dari_sub($sub);
}
if (!isset($struktur[$menu])) {
    $menu = (string) (array_key_first($struktur) ?? '');
}
if ($menu !== '' && !isset($struktur[$menu]['sub'][$sub])) {
    $sub = (string) array_key_first($struktur[$menu]['sub']);
}
?>

<style>
    .sub-entity-label { font-size: 0.75rem; font-weight: 700; color: #495057; letter-spacing: 0.5px; }
    .btn-sub-pill { border-radius: 50rem; font-size: 0.825rem; padding: 0.35rem 1.1rem; font-weight: 500; background-color: #fff; border: 1px solid #ced4da; color: #495057; text-decoration: none; display: inline-flex; align-items: center; transition: all .2s ease-in-out; }
    .btn-sub-pill:hover { background-color: #f8f9fa; border-color: #0d6efd; color: #0d6efd; }
    .btn-sub-pill.active { background-color: #e7f1ff; border-color: #0d6efd; color: #0d6efd; font-weight: 600; }
</style>

<div class="container-fluid px-3 py-2">
    <div class="mb-3">
        <h4 class="fw-bold text-dark mb-1">Modul Pemeliharaan S41</h4>
        <p class="text-muted small mb-0">Kelola eksekusi order, mutasi pemeliharaan, serta monitoring laporan pemeliharaan perangkat.</p>
    </div>

    <?php if (!$struktur): ?>
        <div class="alert alert-warning">Peran Anda tidak memiliki akses ke sub-menu Pemeliharaan.</div>
    <?php else: ?>
        <!-- 1. MENU UTAMA -->
        <div class="card border-0 shadow-sm rounded-4 p-2 mb-3 bg-white">
            <ul class="nav nav-pills d-flex flex-row align-items-center gap-2 mb-0 border-0">
                <?php foreach ($struktur as $kunci_menu => $m): ?>
                    <?php $awal = array_key_first($m['sub']); ?>
                    <li class="nav-item">
                        <a class="nav-link text-nowrap rounded-3 px-4 py-2 fw-semibold <?= $menu === $kunci_menu ? 'active bg-primary text-white' : 'text-secondary' ?>"
                           href="index.php?page=pemeliharaan&menu=<?= ph_e($kunci_menu) ?>&sub=<?= ph_e($awal) ?>">
                            <i class="bi <?= ph_e($m['icon']) ?> me-2"></i><?= ph_e($m['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- 2. SUB ENTITAS -->
        <div class="d-flex align-items-center flex-wrap gap-2 mb-3 px-1">
            <span class="sub-entity-label text-uppercase me-1">SUB ENTITAS:</span>
            <?php foreach ($struktur[$menu]['sub'] as $kunci_sub => $info): ?>
                <a href="index.php?page=pemeliharaan&menu=<?= ph_e($menu) ?>&sub=<?= ph_e($kunci_sub) ?>"
                   class="btn-sub-pill <?= $sub === $kunci_sub ? 'active' : '' ?>">
                    <?php if ($sub === $kunci_sub): ?><i class="bi bi-check2 text-primary me-1"></i><?php endif; ?>
                    <i class="bi <?= ph_e($info[1]) ?> me-1"></i> <?= ph_e($info[0]) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- 3. ISI SUB-HALAMAN (nama file berasal dari daftar tetap di atas, bukan dari input) -->
        <?php include __DIR__ . '/' . $struktur[$menu]['sub'][$sub][2]; ?>
    <?php endif; ?>
</div>