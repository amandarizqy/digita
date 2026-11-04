<?php
$unit_up = $_SESSION['UnitUp'] ?? '';
?>
<!-- Panggil SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container-fluid px-3 py-2">
    <!-- Header Modul dengan Tombol Aksi di Kanan Atas -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-1">Modul Pemasangan S41</h4>
            <p class="text-muted small mb-0">Kelola FPK, Order Perintah Kerja, Eksekusi Lapangan (Mutasi Pasang), dan Monitoring Perangkat S41.</p>
        </div>
        
        <!-- Tombol Aksi Kanan Atas Dinamis Sesuai Halaman -->
        <div>
            <?php if ($menu === 'pra_eksekusi' && $sub === 'pengujian' && $view === 'daftar' && $can_pengujian): ?>
                <a href="index.php?page=pemasangan&menu=pra_eksekusi&sub=pengujian&view=baru" class="btn btn-primary fw-bold px-3 rounded-3 shadow-sm btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Formulir Pengujian Baru
                </a>
            <?php elseif ($menu === 'eksekusi' && $sub === 'order_pasang' && $view === 'daftar' && $can_order_pasang): ?>
                <button type="button" class="btn btn-warning fw-bold text-dark px-3 rounded-3 shadow-sm btn-sm me-2" data-bs-toggle="modal" data-bs-target="#modalTambahFpk">
                    <i class="bi bi-file-earmark-plus me-1"></i> Buat FPK
                </button>
                <a href="index.php?page=pemasangan&menu=eksekusi&sub=order_pasang&view=baru" class="btn btn-primary fw-bold px-3 rounded-3 shadow-sm btn-sm">
                    <i class="bi bi-journal-plus me-1"></i> Buat Order
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- MAIN TAB (LEVEL 2) -->
    <div class="card border-0 shadow-sm rounded-4 p-2 mb-3 bg-white">
        <ul class="nav nav-pills d-flex flex-row align-items-center gap-2 mb-0 border-0">
            <!-- Tab Pra Eksekusi -->
            <li class="nav-item">
                <a class="nav-link text-nowrap rounded-3 px-4 py-2 fw-semibold <?= ($menu === 'pra_eksekusi') ? 'active bg-primary text-white' : 'text-secondary' ?>"
                   href="index.php?page=pemasangan&menu=pra_eksekusi&sub=pengujian">
                    <i class="bi bi-shield-check me-2"></i>Pra Eksekusi 
                    <?php if (!$can_pra_eksekusi): ?><i class="bi bi-lock-fill ms-1"></i><?php endif; ?>
                </a>
            </li>

            <!-- Tab Eksekusi -->
            <li class="nav-item">
                <a class="nav-link text-nowrap rounded-3 px-4 py-2 fw-semibold <?= ($menu === 'eksekusi') ? 'active bg-primary text-white' : 'text-secondary' ?>"
                   href="index.php?page=pemasangan&menu=eksekusi&sub=<?= $can_order_pasang ? 'order_pasang' : 'mutasi_pasang' ?>">
                    <i class="bi bi-tools me-2"></i>Eksekusi 
                    <?php if (!$can_eksekusi): ?><i class="bi bi-lock-fill ms-1"></i><?php endif; ?>
                </a>
            </li>

            <!-- Tab Monitoring -->
            <li class="nav-item">
                <a class="nav-link text-nowrap rounded-3 px-4 py-2 fw-semibold <?= ($menu === 'monitoring') ? 'active bg-primary text-white' : 'text-secondary' ?>"
                   href="index.php?page=pemasangan&menu=monitoring&sub=status_pengujian">
                    <i class="bi bi-display me-2"></i>Monitoring 
                    <?php if (!$can_monitoring): ?><i class="bi bi-lock-fill ms-1"></i><?php endif; ?>
                </a>
            </li>
        </ul>
    </div>

    <!-- SUB ENTITAS (LEVEL 3) -->
    <div class="d-flex align-items-center flex-wrap gap-2 mb-3 px-1">
        <span class="text-uppercase small fw-bold text-muted me-2">SUB ENTITAS:</span>

        <?php if ($menu === 'pra_eksekusi'): ?>
            <a href="index.php?page=pemasangan&menu=pra_eksekusi&sub=pengujian" class="btn btn-sm rounded-pill px-3 fw-semibold btn-primary shadow-sm">
                <i class="bi bi-check2 me-1"></i> Pengujian
            </a>
        <?php elseif ($menu === 'eksekusi'): ?>
            <!-- Order Pasang -->
            <a href="index.php?page=pemasangan&menu=eksekusi&sub=order_pasang" class="btn btn-sm rounded-pill px-3 fw-semibold <?= ($sub === 'order_pasang') ? 'btn-primary shadow-sm' : 'btn-light border text-secondary' ?>">
                Order Pasang <?php if (!$can_order_pasang): ?><i class="bi bi-lock-fill ms-1"></i><?php endif; ?>
            </a>

            <!-- Mutasi Pasang -->
            <a href="index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang" class="btn btn-sm rounded-pill px-3 fw-semibold <?= ($sub === 'mutasi_pasang') ? 'btn-primary shadow-sm' : 'btn-light border text-secondary' ?>">
                Mutasi Pasang <?php if (!$can_mutasi_pasang): ?><i class="bi bi-lock-fill ms-1"></i><?php endif; ?>
            </a>

        <?php else: ?>
            <a href="index.php?page=pemasangan&menu=monitoring&sub=status_pengujian" class="btn btn-sm rounded-pill px-3 fw-semibold <?= ($sub === 'status_pengujian') ? 'btn-primary shadow-sm' : 'btn-light border text-secondary' ?>">Status Pengujian</a>
            <a href="index.php?page=pemasangan&menu=monitoring&sub=riwayat_pengujian" class="btn btn-sm rounded-pill px-3 fw-semibold <?= ($sub === 'riwayat_pengujian') ? 'btn-primary shadow-sm' : 'btn-light border text-secondary' ?>">Riwayat Pengujian</a>
            <a href="index.php?page=pemasangan&menu=monitoring&sub=monitoring_order" class="btn btn-sm rounded-pill px-3 fw-semibold <?= ($sub === 'monitoring_order') ? 'btn-primary shadow-sm' : 'btn-light border text-secondary' ?>">Order Pasang</a>
            <a href="index.php?page=pemasangan&menu=monitoring&sub=monitoring_mutasi" class="btn btn-sm rounded-pill px-3 fw-semibold <?= ($sub === 'monitoring_mutasi') ? 'btn-primary shadow-sm' : 'btn-light border text-secondary' ?>">Mutasi Pasang</a>
        <?php endif; ?>
    </div>

    <!-- KONTEN UTAMA -->
    <?php
    if ($menu === 'pra_eksekusi' && $sub === 'pengujian') {
        if ($view === 'daftar' && file_exists(__DIR__ . '/pengujian_daftar.php')) include __DIR__ . '/pengujian_daftar.php';
        elseif ($view === 'baru' && file_exists(__DIR__ . '/pengujian_baru.php')) include __DIR__ . '/pengujian_baru.php';
        elseif ($view === 'detail' && file_exists(__DIR__ . '/pengujian_detail.php')) include __DIR__ . '/pengujian_detail.php';
    } elseif ($menu === 'eksekusi') {
        if ($sub === 'order_pasang' && file_exists(__DIR__ . '/../../modules/pemasangan/order_pasang.php')) include __DIR__ . '/../../modules/pemasangan/order_pasang.php';
        elseif ($sub === 'mutasi_pasang' && file_exists(__DIR__ . '/../../modules/pemasangan/mutasi_pasang.php')) include __DIR__ . '/../../modules/pemasangan/mutasi_pasang.php';
    } else {
        if ($sub === 'status_pengujian' && file_exists(__DIR__ . '/../../modules/pemasangan/monitoring_status_pengujian.php')) include __DIR__ . '/../../modules/pemasangan/monitoring_status_pengujian.php';
        elseif ($sub === 'riwayat_pengujian' && file_exists(__DIR__ . '/../../modules/pemasangan/monitoring_riwayat_pengujian.php')) include __DIR__ . '/../../modules/pemasangan/monitoring_riwayat_pengujian.php';
        elseif ($sub === 'monitoring_order' && file_exists(__DIR__ . '/../../modules/pemasangan/monitoring_order.php')) include __DIR__ . '/../../modules/pemasangan/monitoring_order.php';
        elseif ($sub === 'monitoring_mutasi' && file_exists(__DIR__ . '/../../modules/pemasangan/monitoring_mutasi.php')) include __DIR__ . '/../../modules/pemasangan/monitoring_mutasi.php';
    }
    ?>
</div>

<!-- Logika Penanganan SweetAlert Berdasarkan Parameter URL -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');

        if (status === 'sukses_fpk') {
            Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'FPK Pemasangan berhasil dibuat.', confirmButtonColor: '#0d6efd' });
        } else if (status === 'sukses_order') {
            Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Order / SPK Pemasangan berhasil dibuat.', confirmButtonColor: '#0d6efd' });
        } else if (status === 'sukses_mutasi') {
            Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Berita Acara Pemasangan berhasil disimpan.', confirmButtonColor: '#0d6efd' });
        } else if (status === 'sukses_final') {
            Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Status Order berhasil difinalisasi.', confirmButtonColor: '#0d6efd' });
        } else if (status === 'sukses') {
            Swal.fire({ icon: 'success', title: 'Selesai!', text: 'Tindakan berhasil dilakukan.', confirmButtonColor: '#0d6efd' });
        }

        // Hapus parameter status dari URL agar alert tidak muncul lagi saat di-refresh
        if (status) {
            window.history.replaceState(null, null, window.location.pathname + '?' + urlParams.toString().replace(/&?status=[^&]*/g, ''));
        }
    });

    function confirmDeleteFpk(url) {
        Swal.fire({
            title: 'Hapus FPK?',
            text: "FPK ini akan dihapus permanen dari sistem.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
        return false;
    }
</script>