<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">Modul Pengadaan & Stok</h3>
        <p class="text-muted small">Kelola siklus logistik perangkat S41 mulai dari pembelian, pengiriman ke unit, hingga penerimaan di gudang.</p>
    </div>
    <a href="?menu=<?= $menu ?>&sub=baru" class="btn btn-primary btn-sm px-3 py-2">
        <i class="bi bi-plus-lg me-1"></i> Buat Formulir
    </a>
</div>

<!-- LEVEL 2: Menu Utama -->
<div class="card shadow-sm mb-4 border-0">
    <div class="card-body p-2">
        <ul class="nav nav-pills">
            <li class="nav-item">
                <a class="nav-link px-4 fw-bold <?= ($menu == 'pembelian') ? 'active' : 'text-secondary' ?>" href="?menu=pembelian">
                    <i class="bi bi-cart-plus me-2"></i> Pembelian (UI)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link px-4 fw-bold <?= ($menu == 'pengiriman') ? 'active' : 'text-secondary' ?>" href="?menu=pengiriman">
                    <i class="bi bi-truck me-2"></i> Pengiriman
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link px-4 fw-bold <?= ($menu == 'penerimaan') ? 'active' : 'text-secondary' ?>" href="?menu=penerimaan">
                    <i class="bi bi-box-arrow-in-down me-2"></i> Penerimaan
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- LEVEL 3: Sub Entitas (Hanya tampil jika bukan menu penerimaan) -->
<?php if ($menu != 'penerimaan'): ?>
<div class="d-flex align-items-center mb-4">
    <span class="text-muted fw-bold me-3" style="font-size: 0.75rem; letter-spacing: 1px;">SUB ENTITAS:</span>
    <a href="?menu=<?= $menu ?>&sub=daftar" class="btn rounded-pill me-2 px-4 btn-sm <?= ($sub == 'daftar') ? 'btn-outline-primary fw-bold' : 'btn-outline-secondary' ?>" <?= ($sub == 'daftar') ? 'style="background-color: #eff6ff;"' : '' ?>>
        Daftar Data
    </a>
    <a href="?menu=<?= $menu ?>&sub=baru" class="btn rounded-pill me-2 px-4 btn-sm <?= ($sub == 'baru') ? 'btn-outline-primary fw-bold' : 'btn-outline-secondary' ?>" <?= ($sub == 'baru') ? 'style="background-color: #eff6ff;"' : '' ?>>
        <i class="bi bi-plus-lg me-1"></i> Form Baru
    </a>
</div>
<?php endif; ?>

<!-- SUMMARY CARDS DINAMIS -->


<!-- TABEL DATA / FORM RENDER AREA -->
<div class="card-body p-0">
        <?php 
            $render_file = __DIR__ . '/' . $menu . '_' . $sub . '.php';
            if (file_exists($render_file)) {
                require_once $render_file;
            } else {
                echo '<div class="p-5 text-center text-muted"><i class="bi bi-file-earmark-x fs-1 d-block mb-3"></i>Modul <strong>' . htmlspecialchars($menu . '_' . $sub) . '.php</strong> belum dibuat.</div>';
            }
        ?>
    </div>