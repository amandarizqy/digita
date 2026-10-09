<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold text-dark mb-1">Modul Pengadaan & Stok</h4>
        <p class="text-muted small mb-0">Manajemen pengadaan, distribusi, dan penerimaan aset perangkat S41.</p>
    </div>
</div>

<?php 
// Normalisasi menu untuk UI
$is_barang = in_array($menu, ['barang', 'pembelian', 'pengiriman', 'penerimaan']); 
$is_monitoring = ($menu === 'monitoring');
$sub = in_array($menu, ['pembelian', 'pengiriman', 'penerimaan']) ? $menu : $sub;
if ($is_monitoring && !isset($_GET['sub'])) {
    $sub = 'aset';
}

// Cek hak akses untuk penanda gembok visual
$can_pembelian_pengiriman = in_array($kode_hak, ['MB.UI', 'AM.UI', 'SA.KP']);
$can_penerimaan = in_array($kode_hak, ['TL.AP', 'TL.UP', 'SA.KP']);
$can_kartu = in_array($kode_hak, ['TL.AP', 'TL.UP', 'SA.KP']);

// Daftar aksi/menu yang ditolak berdasarkan role
$ditolak_list = [];
if (!$can_pembelian_pengiriman) {
    $ditolak_list[] = 'pembelian';
    $ditolak_list[] = 'pengiriman';
}
if (!$can_penerimaan) {
    $ditolak_list[] = 'penerimaan';
}
if (!$can_kartu) {
    $ditolak_list[] = 'kartu';
}
?>

<!-- LEVEL 2: NAVIGASI TAB MENU UTAMA -->
<?php 
$barang_default_link = in_array($kode_hak, ['TL.AP', 'TL.UP']) 
    ? 'index.php?page=pengadaan&menu=penerimaan&view=daftar' 
    : 'index.php?page=pengadaan&menu=pembelian&view=daftar';

$kartu_link = $can_kartu ? 'index.php?page=pengadaan&menu=kartu' : 'javascript:void(0);';
$kartu_class = $can_kartu ? '' : 'sub-menu-link';
$kartu_attr = $can_kartu ? '' : 'data-menu="kartu"';
?>
<ul class="nav nav-pills bg-white p-2 rounded-3 shadow-sm mb-3 border border-light-subtle" role="tablist">
    <li class="nav-item">
        <a class="nav-link py-2 px-3 fw-medium <?= ($is_barang) ? 'active text-white' : 'text-secondary' ?>" <?= ($is_barang) ? 'style="background-color: #0d6efd;"' : '' ?> href="<?= $barang_default_link ?>">
            <i class="bi bi-box me-2"></i> Barang
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link py-2 px-3 fw-medium <?= ($menu == 'kartu') ? 'active text-white' : 'text-secondary' ?> <?= $kartu_class ?>" <?= $kartu_attr ?> <?= ($menu == 'kartu') ? 'style="background-color: #0d6efd;"' : '' ?> href="<?= $kartu_link ?>">
            <i class="bi bi-sim me-2"></i> Kartu <?= !$can_kartu ? '<i class="bi bi-lock-fill ms-1" style="font-size:.75em"></i>' : '' ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link py-2 px-3 fw-medium <?= ($is_monitoring) ? 'active text-white' : 'text-secondary' ?>" <?= ($is_monitoring) ? 'style="background-color: #0d6efd;"' : '' ?> href="index.php?page=pengadaan&menu=monitoring&sub=aset">
            <i class="bi bi-display me-2"></i> Monitoring
        </a>
    </li>
</ul>

<!-- LEVEL 3: SUB ENTITAS & TOMBOL AKSI KANAN -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div class="d-flex align-items-center flex-wrap gap-2">
        <span class="text-secondary small fw-bold text-uppercase me-2" style="letter-spacing: 0.5px; font-size: 0.75rem;">SUB ENTITAS:</span>
        <div class="d-flex flex-wrap gap-2" role="group">
            <?php if ($is_barang): ?>
                <a href="<?= $can_pembelian_pengiriman ? 'index.php?page=pengadaan&menu=pembelian' : 'javascript:void(0);' ?>" class="btn btn-sm rounded-pill px-3 py-1 fw-medium sub-menu-link <?= ($sub == 'pembelian') ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white' ?>" data-menu="pembelian" <?= ($sub == 'pembelian') ? 'style="border-color: #0d6efd; background-color: #e7f1ff; color: #0d6efd;"' : '' ?>>
                    <?= ($sub == 'pembelian') ? '<i class="bi bi-check2 me-1"></i>' : '' ?><i class="bi bi-cart-plus me-1"></i> Pembelian (UI) <?= !$can_pembelian_pengiriman ? '<i class="bi bi-lock-fill ms-1" style="font-size:.75em"></i>' : '' ?>
                </a>
                <a href="<?= $can_pembelian_pengiriman ? 'index.php?page=pengadaan&menu=pengiriman' : 'javascript:void(0);' ?>" class="btn btn-sm rounded-pill px-3 py-1 fw-medium sub-menu-link <?= ($sub == 'pengiriman') ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white' ?>" data-menu="pengiriman" <?= ($sub == 'pengiriman') ? 'style="border-color: #0d6efd; background-color: #e7f1ff; color: #0d6efd;"' : '' ?>>
                    <?= ($sub == 'pengiriman') ? '<i class="bi bi-check2 me-1"></i>' : '' ?><i class="bi bi-truck me-1"></i> Pengiriman <?= !$can_pembelian_pengiriman ? '<i class="bi bi-lock-fill ms-1" style="font-size:.75em"></i>' : '' ?>
                </a>
                <a href="<?= $can_penerimaan ? 'index.php?page=pengadaan&menu=penerimaan' : 'javascript:void(0);' ?>" class="btn btn-sm rounded-pill px-3 py-1 fw-medium sub-menu-link <?= ($sub == 'penerimaan') ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white' ?>" data-menu="penerimaan" <?= ($sub == 'penerimaan') ? 'style="border-color: #0d6efd; background-color: #e7f1ff; color: #0d6efd;"' : '' ?>>
                    <?= ($sub == 'penerimaan') ? '<i class="bi bi-check2 me-1"></i>' : '' ?><i class="bi bi-box-arrow-in-down me-1"></i> Penerimaan <?= !$can_penerimaan ? '<i class="bi bi-lock-fill ms-1" style="font-size:.75em"></i>' : '' ?>
                </a>
            <?php elseif ($menu === 'kartu'): ?>
                <a href="index.php?page=pengadaan&menu=kartu&sub=aktivasi" class="btn btn-sm rounded-pill px-3 py-1 fw-medium <?= ($sub == 'aktivasi') ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white' ?>" <?= ($sub == 'aktivasi') ? 'style="border-color: #0d6efd; background-color: #e7f1ff; color: #0d6efd;"' : '' ?>>
                    <?= ($sub == 'aktivasi') ? '<i class="bi bi-check2 me-1"></i>' : '' ?><i class="bi bi-sim me-1"></i> Aktivasi Perdana
                </a>
                <a href="index.php?page=pengadaan&menu=kartu&sub=pulsa" class="btn btn-sm rounded-pill px-3 py-1 fw-medium <?= ($sub == 'pulsa') ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white' ?>" <?= ($sub == 'pulsa') ? 'style="border-color: #0d6efd; background-color: #e7f1ff; color: #0d6efd;"' : '' ?>>
                    <?= ($sub == 'pulsa') ? '<i class="bi bi-check2 me-1"></i>' : '' ?><i class="bi bi-wallet2 me-1"></i> Isi Pulsa
                </a>
            <?php elseif ($is_monitoring): ?>
                <a href="index.php?page=pengadaan&menu=monitoring&sub=aset" class="btn btn-sm rounded-pill px-3 py-1 fw-medium <?= ($sub == 'aset') ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white' ?>" <?= ($sub == 'aset') ? 'style="border-color: #0d6efd; background-color: #e7f1ff; color: #0d6efd;"' : '' ?>>
                    <?= ($sub == 'aset') ? '<i class="bi bi-check2 me-1"></i>' : '' ?><i class="bi bi-box-seam me-1"></i> Aset
                </a>
                <a href="index.php?page=pengadaan&menu=monitoring&sub=pembelian" class="btn btn-sm rounded-pill px-3 py-1 fw-medium <?= ($sub == 'pembelian') ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white' ?>" <?= ($sub == 'pembelian') ? 'style="border-color: #0d6efd; background-color: #e7f1ff; color: #0d6efd;"' : '' ?>>
                    <?= ($sub == 'pembelian') ? '<i class="bi bi-check2 me-1"></i>' : '' ?><i class="bi bi-cart-check me-1"></i> Pembelian Perdana
                </a>
                <a href="index.php?page=pengadaan&menu=monitoring&sub=pengiriman" class="btn btn-sm rounded-pill px-3 py-1 fw-medium <?= ($sub == 'pengiriman') ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white' ?>" <?= ($sub == 'pengiriman') ? 'style="border-color: #0d6efd; background-color: #e7f1ff; color: #0d6efd;"' : '' ?>>
                    <?= ($sub == 'pengiriman') ? '<i class="bi bi-check2 me-1"></i>' : '' ?><i class="bi bi-truck me-1"></i> Pengiriman
                </a>
                <a href="index.php?page=pengadaan&menu=monitoring&sub=penerimaan" class="btn btn-sm rounded-pill px-3 py-1 fw-medium <?= ($sub == 'penerimaan') ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white' ?>" <?= ($sub == 'penerimaan') ? 'style="border-color: #0d6efd; background-color: #e7f1ff; color: #0d6efd;"' : '' ?>>
                    <?= ($sub == 'penerimaan') ? '<i class="bi bi-check2 me-1"></i>' : '' ?><i class="bi bi-box-arrow-in-down me-1"></i> Penerimaan
                </a>
                <a href="index.php?page=pengadaan&menu=monitoring&sub=pulsa" class="btn btn-sm rounded-pill px-3 py-1 fw-medium <?= ($sub == 'pulsa') ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white' ?>" <?= ($sub == 'pulsa') ? 'style="border-color: #0d6efd; background-color: #e7f1ff; color: #0d6efd;"' : '' ?>>
                    <?= ($sub == 'pulsa') ? '<i class="bi bi-check2 me-1"></i>' : '' ?><i class="bi bi-wallet2 me-1"></i> Riwayat Pulsa
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!$is_monitoring): ?>
    <div class="btn-group rounded-pill border bg-white shadow-sm" role="group">
        <a href="index.php?page=pengadaan&menu=<?= $menu ?>&sub=<?= $sub ?>&view=daftar" class="btn btn-sm rounded-pill <?= ($view == 'daftar') ? 'btn-primary fw-bold' : 'btn-light text-muted' ?> px-3">
            Daftar Data
        </a>
        <?php if ($sub !== 'penerimaan'): ?>
        <a href="index.php?page=pengadaan&menu=<?= $menu ?>&sub=<?= $sub ?>&view=baru" class="btn btn-sm rounded-pill <?= ($view == 'baru') ? 'btn-primary fw-bold' : 'btn-light text-muted' ?> px-3">
            <i class="bi bi-plus-lg me-1"></i> Form Baru
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
        if ($can_kartu) {
            if ($sub === 'aktivasi') {
                if ($view === 'daftar') require_once __DIR__ . '/kartu_aktivasi_daftar.php';
                elseif ($view === 'baru') require_once __DIR__ . '/kartu_aktivasi_baru.php';
            }
            elseif ($sub === 'pulsa') {
                if ($view === 'daftar') require_once __DIR__ . '/kartu_pulsa_daftar.php';
                elseif ($view === 'baru') require_once __DIR__ . '/kartu_pulsa_baru.php';
            }
        }
    }
    elseif ($is_monitoring) {
        require_once __DIR__ . '/monitoring.php'; 
    }
    ?>
</div>

<!-- Skrip SweetAlert2 untuk Pencegatan Tautan Terkunci -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    var ditolak = <?= json_encode($ditolak_list); ?>;
    
    function tolak() {
        var pesan = "Anda tidak memiliki wewenang untuk membuka menu ini.";
        if (window.Swal) {
            Swal.fire({
                icon: "error", 
                title: "Akses ditolak", 
                text: pesan, 
                confirmButtonText: "OK", 
                confirmButtonColor: "#0d6efd"
            });
        } else {
            alert("Akses ditolak\n" + pesan);
        }
    }

    document.querySelectorAll(".sub-menu-link").forEach(function (a) {
        var menuName = a.getAttribute("data-menu");
        if (ditolak.indexOf(menuName) > -1) {
            a.style.opacity = ".6";
            if (!a.querySelector(".bi-lock-fill")) {
                a.insertAdjacentHTML("beforeend", ' <i class="bi bi-lock-fill ms-1" style="font-size:.75em"></i>');
            }
        }
    });

    document.addEventListener("click", function (e) {
        var a = e.target.closest(".sub-menu-link"); 
        if (!a) return;
        var menuName = a.getAttribute("data-menu");
        if (ditolak.indexOf(menuName) > -1) {
            e.preventDefault(); 
            e.stopPropagation(); 
            tolak();
        }
    }, true);
})();
</script>