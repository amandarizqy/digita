<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold text-dark mb-1">Modul Pemasangan</h4>
        <p class="text-muted small mb-0">Manajemen pra-eksekusi (pengujian), eksekusi pemasangan, mutasi, dan monitoring perangkat S41.</p>
    </div>
</div>

<?php 
$is_pra_eksekusi = ($menu === 'pra_eksekusi'); 
$is_eksekusi     = ($menu === 'eksekusi');
$is_monitoring   = ($menu === 'monitoring');

// Daftar menu yang diblokir untuk script SweetAlert2 visual
$ditolak_list = [];
if (!$can_pra_eksekusi)  $ditolak_list[] = 'pra_eksekusi';
if (!$can_eksekusi)      $ditolak_list[] = 'eksekusi';
if (!$can_monitoring)    $ditolak_list[] = 'monitoring';

if (!$can_pengujian)     $ditolak_list[] = 'pengujian';
if (!$can_order_pasang)  $ditolak_list[] = 'order_pasang';
if (!$can_mutasi_pasang) $ditolak_list[] = 'mutasi_pasang';

if (!$can_monitoring) {
    $ditolak_list[] = 'status_pengujian';
    $ditolak_list[] = 'riwayat_pengujian';
    $ditolak_list[] = 'monitoring_order';
    $ditolak_list[] = 'monitoring_mutasi';
}

// Link navigasi default per Tab
$link_pra = $can_pra_eksekusi ? 'index.php?page=pemasangan&menu=pra_eksekusi&sub=pengujian' : 'javascript:void(0);';
$link_eks = $can_eksekusi ? ($can_order_pasang ? 'index.php?page=pemasangan&menu=eksekusi&sub=order_pasang' : 'index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang') : 'javascript:void(0);';
$link_mon = $can_monitoring ? 'index.php?page=pemasangan&menu=monitoring&sub=status_pengujian' : 'javascript:void(0);';
?>

<!-- LEVEL 2: NAVIGASI TAB MENU UTAMA -->
<ul class="nav nav-pills bg-white p-2 rounded-3 shadow-sm mb-3 border border-light-subtle" role="tablist">
    <li class="nav-item">
        <a class="nav-link py-2 px-3 fw-medium <?= ($is_pra_eksekusi) ? 'active text-white' : 'text-secondary' ?> <?= !$can_pra_eksekusi ? 'sub-menu-link' : '' ?>" <?= !$can_pra_eksekusi ? 'data-menu="pra_eksekusi"' : '' ?> <?= ($is_pra_eksekusi) ? 'style="background-color: #0d6efd;"' : (!$can_pra_eksekusi ? 'style="opacity: 0.6;"' : '') ?> href="<?= $link_pra ?>">
            <i class="bi bi-shield-check me-2"></i> Pra Eksekusi <?= !$can_pra_eksekusi ? '<i class="bi bi-lock-fill ms-1" style="font-size:.75em"></i>' : '' ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link py-2 px-3 fw-medium <?= ($is_eksekusi) ? 'active text-white' : 'text-secondary' ?> <?= !$can_eksekusi ? 'sub-menu-link' : '' ?>" <?= !$can_eksekusi ? 'data-menu="eksekusi"' : '' ?> <?= ($is_eksekusi) ? 'style="background-color: #0d6efd;"' : (!$can_eksekusi ? 'style="opacity: 0.6;"' : '') ?> href="<?= $link_eks ?>">
            <i class="bi bi-tools me-2"></i> Eksekusi <?= !$can_eksekusi ? '<i class="bi bi-lock-fill ms-1" style="font-size:.75em"></i>' : '' ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link py-2 px-3 fw-medium <?= ($is_monitoring) ? 'active text-white' : 'text-secondary' ?> <?= !$can_monitoring ? 'sub-menu-link' : '' ?>" <?= !$can_monitoring ? 'data-menu="monitoring"' : '' ?> <?= ($is_monitoring) ? 'style="background-color: #0d6efd;"' : (!$can_monitoring ? 'style="opacity: 0.6;"' : '') ?> href="<?= $link_mon ?>">
            <i class="bi bi-display me-2"></i> Monitoring <?= !$can_monitoring ? '<i class="bi bi-lock-fill ms-1" style="font-size:.75em"></i>' : '' ?>
        </a>
    </li>
</ul>

<!-- LEVEL 3: SUB ENTITAS & TOMBOL AKSI KANAN -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div class="d-flex align-items-center flex-wrap gap-2">
        <span class="text-secondary small fw-bold text-uppercase me-2" style="letter-spacing: 0.5px; font-size: 0.75rem;">SUB ENTITAS:</span>
        <div class="d-flex flex-wrap gap-2" role="group">
            <?php if ($is_pra_eksekusi): ?>
                <a href="<?= $can_pengujian ? 'index.php?page=pemasangan&menu=pra_eksekusi&sub=pengujian' : 'javascript:void(0);' ?>" class="btn btn-sm rounded-pill px-3 py-1 fw-medium <?= !$can_pengujian ? 'sub-menu-link' : '' ?> <?= ($sub == 'pengujian') ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white' ?>" data-menu="pengujian" <?= ($sub == 'pengujian') ? 'style="border-color: #0d6efd; background-color: #e7f1ff; color: #0d6efd;"' : (!$can_pengujian ? 'style="opacity: 0.6;"' : '') ?>>
                    <?= ($sub == 'pengujian') ? '<i class="bi bi-check2 me-1"></i>' : '' ?><i class="bi bi-check-circle me-1"></i> Pengujian Alat & SIM <?= !$can_pengujian ? '<i class="bi bi-lock-fill ms-1" style="font-size:.75em"></i>' : '' ?>
                </a>
            <?php elseif ($is_eksekusi): ?>
                <a href="<?= $can_order_pasang ? 'index.php?page=pemasangan&menu=eksekusi&sub=order_pasang' : 'javascript:void(0);' ?>" class="btn btn-sm rounded-pill px-3 py-1 fw-medium <?= !$can_order_pasang ? 'sub-menu-link' : '' ?> <?= ($sub == 'order_pasang') ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white' ?>" data-menu="order_pasang" <?= ($sub == 'order_pasang') ? 'style="border-color: #0d6efd; background-color: #e7f1ff; color: #0d6efd;"' : (!$can_order_pasang ? 'style="opacity: 0.6;"' : '') ?>>
                    <?= ($sub == 'order_pasang') ? '<i class="bi bi-check2 me-1"></i>' : '' ?><i class="bi bi-node-plus me-1"></i> Order Pasang <?= !$can_order_pasang ? '<i class="bi bi-lock-fill ms-1" style="font-size:.75em"></i>' : '' ?>
                </a>
                <a href="<?= $can_mutasi_pasang ? 'index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang' : 'javascript:void(0);' ?>" class="btn btn-sm rounded-pill px-3 py-1 fw-medium <?= !$can_mutasi_pasang ? 'sub-menu-link' : '' ?> <?= ($sub == 'mutasi_pasang') ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white' ?>" data-menu="mutasi_pasang" <?= ($sub == 'mutasi_pasang') ? 'style="border-color: #0d6efd; background-color: #e7f1ff; color: #0d6efd;"' : (!$can_mutasi_pasang ? 'style="opacity: 0.6;"' : '') ?>>
                    <?= ($sub == 'mutasi_pasang') ? '<i class="bi bi-check2 me-1"></i>' : '' ?><i class="bi bi-arrow-left-right me-1"></i> Mutasi Pasang <?= !$can_mutasi_pasang ? '<i class="bi bi-lock-fill ms-1" style="font-size:.75em"></i>' : '' ?>
                </a>
            <?php elseif ($is_monitoring): ?>
                <a href="index.php?page=pemasangan&menu=monitoring&sub=status_pengujian" class="btn btn-sm rounded-pill px-3 py-1 fw-medium <?= ($sub == 'status_pengujian') ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white' ?>" <?= ($sub == 'status_pengujian') ? 'style="border-color: #0d6efd; background-color: #e7f1ff; color: #0d6efd;"' : '' ?>>
                    <?= ($sub == 'status_pengujian') ? '<i class="bi bi-check2 me-1"></i>' : '' ?><i class="bi bi-clipboard-data me-1"></i> Status Pengujian
                </a>
                <a href="index.php?page=pemasangan&menu=monitoring&sub=riwayat_pengujian" class="btn btn-sm rounded-pill px-3 py-1 fw-medium <?= ($sub == 'riwayat_pengujian') ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white' ?>" <?= ($sub == 'riwayat_pengujian') ? 'style="border-color: #0d6efd; background-color: #e7f1ff; color: #0d6efd;"' : '' ?>>
                    <?= ($sub == 'riwayat_pengujian') ? '<i class="bi bi-check2 me-1"></i>' : '' ?><i class="bi bi-clock-history me-1"></i> Riwayat Pengujian
                </a>
                <a href="index.php?page=pemasangan&menu=monitoring&sub=order_pasang" class="btn btn-sm rounded-pill px-3 py-1 fw-medium <?= ($sub == 'order_pasang') ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white' ?>" <?= ($sub == 'order_pasang') ? 'style="border-color: #0d6efd; background-color: #e7f1ff; color: #0d6efd;"' : '' ?>>
                    <?= ($sub == 'order_pasang') ? '<i class="bi bi-check2 me-1"></i>' : '' ?><i class="bi bi-map me-1"></i> Order Pasang
                </a>
                <a href="index.php?page=pemasangan&menu=monitoring&sub=mutasi_pasang" class="btn btn-sm rounded-pill px-3 py-1 fw-medium <?= ($sub == 'mutasi_pasang') ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white' ?>" <?= ($sub == 'mutasi_pasang') ? 'style="border-color: #0d6efd; background-color: #e7f1ff; color: #0d6efd;"' : '' ?>>
                    <?= ($sub == 'mutasi_pasang') ? '<i class="bi bi-check2 me-1"></i>' : '' ?><i class="bi bi-shuffle me-1"></i> Mutasi Pasang
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- TOMBOL KANAN (Daftar / Form Baru) -->
    <?php if (!$is_monitoring && $sub !== 'riwayat_pengujian'): ?>
    <div class="btn-group rounded-pill border bg-white shadow-sm" role="group">
        <a href="index.php?page=pemasangan&menu=<?= $menu ?>&sub=<?= $sub ?>&view=daftar" class="btn btn-sm rounded-pill <?= ($view == 'daftar') ? 'btn-primary fw-bold' : 'btn-light text-muted' ?> px-3">
            Daftar Data
        </a>
        <a href="index.php?page=pemasangan&menu=<?= $menu ?>&sub=<?= $sub ?>&view=baru" class="btn btn-sm rounded-pill <?= ($view == 'baru') ? 'btn-primary fw-bold' : 'btn-light text-muted' ?> px-3">
            <i class="bi bi-plus-lg me-1"></i> Form Baru
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- KONTEN DINAMIS DIMUAT DI SINI -->
<div class="mt-2">
    <?php 
        if ($sub === 'pengujian' && $view === 'baru') {
            require_once __DIR__ . '/pengujian_baru.php';
        } else {
            // Tampilan default / placeholder jika tidak ada view yang cocok
            echo '<div class="card shadow-sm border-0"><div class="card-body p-5 text-center">';
            echo '<h5 class="text-muted"><i class="bi bi-tools fs-1 text-light d-block mb-3"></i> Tampilan '.strtoupper(str_replace('_', ' ', $sub)).'</h5>';
            echo '<p class="text-muted small">Silakan pilih menu Form Baru atau Daftar Data di atas.</p>';
            echo '</div></div>';
        }
    ?>
    
</div>

<!-- SCRIPT SWEETALERT UNTUK VISUALISASI GEMBOK DAN TOLAK AKSES -->
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