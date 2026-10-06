<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-box me-2"></i>Modul Pengadaan & Stok</h1>
</div>

<?php 
// Normalisasi menu untuk UI
$is_barang = in_array($menu, ['barang', 'pembelian', 'pengiriman', 'penerimaan']); 
$is_monitoring = ($menu === 'monitoring');
$sub = in_array($menu, ['pembelian', 'pengiriman', 'penerimaan']) ? $menu : $sub;
if ($is_monitoring && !isset($_GET['sub'])) {
    $sub = 'aset';
}
?>

<!-- MENU LEVEL 2 (Tab Atas) -->
<?php 
// Tentukan tujuan default menu Barang berdasarkan role
$barang_default_link = in_array($kode_hak, ['TL.AP', 'TL.UP']) 
    ? 'index.php?page=pengadaan&menu=penerimaan&view=daftar' 
    : 'index.php?page=pengadaan&menu=pembelian&view=daftar';
?>
<ul class="nav nav-tabs mb-4 border-bottom-0">
    <li class="nav-item">
        <a class="nav-link <?= ($is_barang) ? 'active bg-primary text-white border-primary shadow-sm' : 'bg-light border text-secondary' ?> me-1" href="<?= $barang_default_link ?>">
            <i class="bi bi-box me-1"></i> Barang
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= ($menu == 'kartu') ? 'active bg-primary text-white border-primary shadow-sm' : 'bg-light border text-secondary' ?> me-1" href="index.php?page=pengadaan&menu=kartu">
            <i class="bi bi-sim me-1"></i> Kartu
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= ($is_monitoring) ? 'active bg-primary text-white border-primary shadow-sm' : 'bg-light border text-secondary' ?>" href="index.php?page=pengadaan&menu=monitoring&sub=aset">
            <i class="bi bi-display me-1"></i> Monitoring
        </a>
    </li>
</ul>

<!-- MENU LEVEL 3 (Sub Entitas) & AKSI DAFTAR/BARU -->
<div class="d-flex align-items-center mb-4 bg-white p-3 rounded shadow-sm border flex-wrap">
    <span class="text-muted fw-bold me-3 text-uppercase small" style="letter-spacing: 1px;">Sub Entitas:</span>
    <div class="btn-group me-4 shadow-sm mb-2 mb-md-0" role="group">
        <?php if ($is_barang): ?>
            <a href="index.php?page=pengadaan&menu=pembelian" class="btn btn-sm <?= ($sub == 'pembelian') ? 'btn-primary fw-bold' : 'btn-outline-primary' ?>">
                <i class="bi bi-cart-plus me-1"></i> Pembelian (UI)
            </a>
            <a href="index.php?page=pengadaan&menu=pengiriman" class="btn btn-sm <?= ($sub == 'pengiriman') ? 'btn-primary fw-bold' : 'btn-outline-primary' ?>">
                <i class="bi bi-truck me-1"></i> Pengiriman
            </a>
            <a href="index.php?page=pengadaan&menu=penerimaan" class="btn btn-sm <?= ($sub == 'penerimaan') ? 'btn-primary fw-bold' : 'btn-outline-primary' ?>">
                <i class="bi bi-box-arrow-in-down me-1"></i> Penerimaan
            </a>
        <?php elseif ($menu === 'kartu'): ?>
            <a href="index.php?page=pengadaan&menu=kartu&sub=aktivasi" class="btn btn-sm <?= ($sub == 'aktivasi') ? 'btn-primary fw-bold' : 'btn-outline-primary' ?>">
                <i class="bi bi-sim me-1"></i> Aktivasi Perdana
            </a>
            <a href="index.php?page=pengadaan&menu=kartu&sub=pulsa" class="btn btn-sm <?= ($sub == 'pulsa') ? 'btn-primary fw-bold' : 'btn-outline-primary' ?>">
                <i class="bi bi-wallet2 me-1"></i> Isi Pulsa
            </a>
        <?php elseif ($is_monitoring): ?>
            <a href="index.php?page=pengadaan&menu=monitoring&sub=aset" class="btn btn-sm <?= ($sub == 'aset') ? 'btn-primary fw-bold' : 'btn-outline-primary' ?>">
                <i class="bi bi-box-seam me-1"></i> Aset
            </a>
            <a href="index.php?page=pengadaan&menu=monitoring&sub=pembelian" class="btn btn-sm <?= ($sub == 'pembelian') ? 'btn-primary fw-bold' : 'btn-outline-primary' ?>">
                <i class="bi bi-cart-check me-1"></i> Pembelian Perdana
            </a>
            <a href="index.php?page=pengadaan&menu=monitoring&sub=pengiriman" class="btn btn-sm <?= ($sub == 'pengiriman') ? 'btn-primary fw-bold' : 'btn-outline-primary' ?>">
                <i class="bi bi-truck me-1"></i> Pengiriman
            </a>
            <a href="index.php?page=pengadaan&menu=monitoring&sub=penerimaan" class="btn btn-sm <?= ($sub == 'penerimaan') ? 'btn-primary fw-bold' : 'btn-outline-primary' ?>">
                <i class="bi bi-box-arrow-in-down me-1"></i> Penerimaan
            </a>
            <a href="index.php?page=pengadaan&menu=monitoring&sub=pulsa" class="btn btn-sm <?= ($sub == 'pulsa') ? 'btn-primary fw-bold' : 'btn-outline-primary' ?>">
                <i class="bi bi-wallet2 me-1"></i> Riwayat Pulsa
            </a>
        <?php endif; ?>
    </div>
    
    <?php if (!$is_monitoring): ?>
    <div class="btn-group rounded-pill border" role="group">
        <a href="index.php?page=pengadaan&menu=<?= $menu ?>&sub=<?= $sub ?>&view=daftar" class="btn btn-sm rounded-pill <?= ($view == 'daftar') ? 'btn-primary fw-bold' : 'btn-light text-muted' ?> px-4">
            Daftar Data
        </a>
        <?php if ($sub !== 'penerimaan'): ?>
        <a href="index.php?page=pengadaan&menu=<?= $menu ?>&sub=<?= $sub ?>&view=baru" class="btn btn-sm rounded-pill <?= ($view == 'baru') ? 'btn-light fw-bold text-dark border' : 'btn-light text-muted' ?> px-4">
            <i class="bi bi-plus-lg"></i> Form Baru
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="mt-2">
    <?php
    if ($is_barang) {
        if ($sub === 'pembelian') {
            if ($view === 'daftar') require_once __DIR__ . '/pembelian_daftar.php';
            elseif ($view === 'baru') require_once __DIR__ . '/pembelian_baru.php';
            elseif ($view === 'detail') require_once __DIR__ . '/pembelian.php'; 
        } 
        elseif ($sub === 'pengiriman') {
            if ($view === 'daftar') require_once __DIR__ . '/pengiriman_daftar.php';
            elseif ($view === 'baru') require_once __DIR__ . '/pengiriman_baru.php';
            elseif ($view === 'detail') require_once __DIR__ . '/pengiriman.php'; 
        } 
        elseif ($sub === 'penerimaan') {
            if ($view === 'daftar') require_once __DIR__ . '/penerimaan_daftar.php';
            elseif ($view === 'detail') require_once __DIR__ . '/penerimaan.php'; 
        }
    } 
    elseif ($menu === 'kartu') {
        if ($sub === 'aktivasi') {
            if ($view === 'daftar') require_once __DIR__ . '/kartu_aktivasi_daftar.php';
            elseif ($view === 'baru') require_once __DIR__ . '/kartu_aktivasi_baru.php';
        }
        elseif ($sub === 'pulsa') {
            if ($view === 'daftar') require_once __DIR__ . '/kartu_pulsa_daftar.php';
            elseif ($view === 'baru') require_once __DIR__ . '/kartu_pulsa_baru.php';
        }
    }
    elseif ($is_monitoring) {
        // Tampilan tabel untuk kelima sub-entitas monitoring
        require_once __DIR__ . '/monitoring.php'; 
    }
    ?>
</div>