<!-- templates/perencanaan/hasil_risiko.php -->

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-bar-chart-line-fill text-success me-2"></i> Laporan Hasil Evaluasi Risiko</h4>
        <p class="text-muted small mb-0">Menampilkan daftar klasifikasi dan prioritas risiko pelanggan yang telah diproses.</p>
    </div>
    <div>
        <a href="?module=perencanaan" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
        </a>
    </div>
</div>

<!-- Alert Notifikasi Jika Error -->
<?php if (!empty($pesan_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $pesan_error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- KARTU RINGKASAN STATISTIK -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-danger text-white h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-white-50 text-uppercase fw-semibold mb-1">Prioritas Tinggi (7 - 9)</h6>
                    <h3 class="fw-bold mb-0"><?= number_format($total_p_tinggi) ?></h3>
                    <small class="text-white-50">Perlu penanganan segera</small>
                </div>
                <i class="bi bi-exclamation-octagon fs-1 opacity-50"></i>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-warning text-dark h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-dark-50 text-uppercase fw-bold mb-1">Prioritas Sedang (4 - 6)</h6>
                    <h3 class="fw-bold mb-0 text-dark"><?= number_format($total_p_sedang) ?></h3>
                    <small class="text-muted">Pemantauan rutin</small>
                </div>
                <i class="bi bi-exclamation-triangle fs-1 opacity-50"></i>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-success text-white h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-white-50 text-uppercase fw-semibold mb-1">Prioritas Rendah (1 - 3)</h6>
                    <h3 class="fw-bold mb-0"><?= number_format($total_p_rendah) ?></h3>
                    <small class="text-white-50">Risiko terkendali</small>
                </div>
                <i class="bi bi-shield-check fs-1 opacity-50"></i>
            </div>
        </div>
    </div>
</div>

<!-- KARTU SEARCH & MULTI-FILTER -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-2">
            <input type="hidden" name="module" value="perencanaan">
            <input type="hidden" name="action" value="hasil_risiko">

            <!-- Keyword Search -->
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">Pencarian</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="keyword" class="form-control" value="<?= htmlspecialchars($keyword) ?>" placeholder="IDPel / Nama Pelanggan...">
                </div>
            </div>

            <!-- Filter Skala Prioritas -->
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">Skala Prioritas</label>
                <select name="prioritas" class="form-select form-select-sm">
                    <option value="">-- Semua Prioritas --</option>
                    <?php for ($i = 9; $i >= 1; $i--): ?>
                        <option value="<?= $i ?>" <?= $prioritas === (string)$i ? 'selected' : '' ?>>Prioritas <?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Filter Posisi SR -->
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Posisi SR</label>
                <select name="posisi_sr" class="form-select form-select-sm">
                    <option value="">-- Semua Status --</option>
                    <option value="1" <?= $posisi_sr === '1' ? 'selected' : '' ?>>Saluran Resmi (SR)</option>
                    <option value="0" <?= $posisi_sr === '0' ? 'selected' : '' ?>>Non-SR</option>
                </select>
            </div>

            <!-- Filter Level Dampak -->
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Level Dampak</label>
                <select name="level_dampak" class="form-select form-select-sm">
                    <option value="">-- Semua Dampak --</option>
                    <option value="SANGAT TINGGI" <?= $level_dampak === 'SANGAT TINGGI' ? 'selected' : '' ?>>SANGAT TINGGI</option>
                    <option value="MODERAT" <?= $level_dampak === 'MODERAT' ? 'selected' : '' ?>>MODERAT</option>
                    <option value="SANGAT RENDAH" <?= $level_dampak === 'SANGAT RENDAH' ? 'selected' : '' ?>>SANGAT RENDAH</option>
                </select>
            </div>

            <!-- Tombol Submit & Reset -->
            <div class="col-md-2 d-flex align-items-end gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-filter me-1"></i> Filter
                </button>
                <a href="?module=perencanaan&action=hasil_risiko" class="btn btn-outline-secondary btn-sm" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- TABEL DATA READ-ONLY HASIL RISIKO -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="fw-bold mb-0 text-secondary">
            <i class="bi bi-table me-1"></i> Daftar Pelanggan Berdasarkan Tingkat Risiko
        </h6>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 50px;">No</th>
                        <th>ID Pelanggan</th>
                        <th>Nama Pelanggan</th>
                        <th class="text-center">Status SR</th>
                        <th class="text-center">Keterlambatan</th>
                        <th class="text-center">Dampak</th>
                        <th class="text-center">Kemungkinan</th>
                        <th class="text-center">Kuadran</th>
                        <th class="text-center">Skala Prioritas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($list_hasil)): ?>
                        <?php $no = $offset + 1; ?>
                        <?php foreach ($list_hasil as $row): ?>
                            <tr>
                                <td class="text-center text-muted"><?= $no++ ?></td>
                                <td><code><?= htmlspecialchars($row['Idpel']) ?></code></td>
                                <td><strong><?= htmlspecialchars($row['NamaPelanggan']) ?></strong></td>
                                
                                <td class="text-center">
                                    <?php if ($row['PosisiSR'] == '1'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success">SR</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border">Non-SR</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center">
                                    <span class="badge bg-light text-dark border">
                                        <?= htmlspecialchars($row['LevelKeterlambatan'] ?? '-') ?>
                                    </span>
                                </td>

                                <td class="text-center">
                                    <span class="badge bg-light text-dark border">
                                        <?= htmlspecialchars($row['LevelDampak'] ?? '-') ?>
                                    </span>
                                </td>

                                <td class="text-center">
                                    <small class="text-muted fw-semibold">
                                        <?= htmlspecialchars($row['LevelKemungkinan'] ?? '-') ?>
                                    </small>
                                </td>

                                <td class="text-center">
                                    <span class="badge bg-info text-dark">
                                        K-<?= htmlspecialchars($row['Kuadran'] ?? '-') ?>
                                    </span>
                                </td>

                                <!-- BADGE SKALA PRIORITAS -->
                                <td class="text-center">
                                    <?php 
                                        $p = (int)$row['SkalaPrioritas'];
                                        $badge_class = 'bg-secondary';
                                        if ($p >= 7) $badge_class = 'bg-danger';
                                        elseif ($p >= 4) $badge_class = 'bg-warning text-dark';
                                        elseif ($p >= 1) $badge_class = 'bg-success';
                                    ?>
                                    <span class="badge <?= $badge_class ?> fs-6 px-3">
                                        Prioritas <?= $p ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2 text-secondary"></i>
                                Tidak ada data hasil risiko yang cocok dengan filter yang dipilih.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- COMPONENT PAGINASI -->
    <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center py-3">
            <small class="text-muted mb-2 mb-md-0">
                Menampilkan data ke-<strong><?= min($offset + 1, $total_rows) ?></strong> s/d <strong><?= min($offset + $limit, $total_rows) ?></strong> dari total <strong><?= number_format($total_rows) ?></strong> data
            </small>

            <nav aria-label="Navigasi Halaman">
                <ul class="pagination pagination-sm mb-0">
                    <!-- Base Query URL Parameter -->
                    <?php 
                        $query_params = $_GET;
                        unset($query_params['page']); 
                        $base_url = '?' . http_build_query($query_params) . '&page=';
                    ?>

                    <!-- Tombol First & Previous -->
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $base_url . ($page - 1) ?>"><i class="bi bi-chevron-left"></i></a>
                    </li>

                    <!-- Nomor Halaman -->
                    <?php
                        $start_page = max(1, $page - 2);
                        $end_page   = min($total_pages, $page + 2);

                        if ($start_page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="' . $base_url . '1">1</a></li>';
                            if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }

                        for ($i = $start_page; $i <= $end_page; $i++) {
                            $active = ($i === $page) ? 'active' : '';
                            echo '<li class="page-item ' . $active . '"><a class="page-link" href="' . $base_url . $i . '">' . $i . '</a></li>';
                        }

                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            echo '<li class="page-item"><a class="page-link" href="' . $base_url . $total_pages . '">' . $total_pages . '</a></li>';
                        }
                    ?>

                    <!-- Tombol Next -->
                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $base_url . ($page + 1) ?>"><i class="bi bi-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>