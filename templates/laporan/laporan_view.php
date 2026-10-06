<!-- templates/laporan/laporan_view.php -->
<!-- Tampilan tabel generik, dipakai untuk SEMUA jenis laporan -->

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="bi bi-file-earmark-text me-2"></i><?= htmlspecialchars($cfg['title'] ?? 'Laporan') ?></h4>
    <a href="?" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Kembali ke Dashboard Laporan
    </a>
</div>

<?php if (!empty($pesan_error)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i> <?= htmlspecialchars($pesan_error) ?>
    </div>
<?php else: ?>

    <div class="card shadow-sm">
        <div class="card-body">

            <!-- Filter Tanggal -->
            <form method="GET" action="" class="row g-2 mb-3 align-items-end">
                <input type="hidden" name="action" value="<?= htmlspecialchars($jenis) ?>">

                <?php if (!empty($cfg['date_column'])): ?>
                <div class="col-auto">
                    <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                    <input type="date" name="tgl_dari" class="form-control form-control-sm" value="<?= htmlspecialchars($tgl_dari) ?>">
                </div>
                <div class="col-auto">
                    <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                    <input type="date" name="tgl_sampai" class="form-control form-control-sm" value="<?= htmlspecialchars($tgl_sampai) ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Terapkan</button>
                    <?php if ($tgl_dari !== '' || $tgl_sampai !== ''): ?>
                        <a href="?action=<?= urlencode($jenis) ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-x-lg"></i> Reset
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="col-auto ms-auto d-flex align-items-center gap-2">
                    <label class="form-label small text-muted mb-0">Tampilkan:</label>
                    <select name="limit" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                        <?php foreach ([10, 25, 50, 100] as $opt): ?>
                            <option value="<?= $opt ?>" <?= $limit == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="small text-muted">data</span>
                </div>
            </form>

            <!-- Tabel -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm align-middle" style="font-size:.9rem">
                    <thead class="table-light">
                        <tr>
                            <?php foreach ($cfg['columns'] as $col): ?>
                                <th><?= htmlspecialchars($col['label']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($data_laporan)): ?>
                            <?php foreach ($data_laporan as $row): ?>
                                <tr>
                                    <?php foreach ($cfg['columns'] as $col): ?>
                                        <?php
                                        $val = $row[$col['field']] ?? null;
                                        switch ($col['format'] ?? null) {
                                            case 'rupiah':
                                                $tampil = 'Rp ' . number_format((float) $val, 0, ',', '.');
                                                break;
                                            case 'tanggal':
                                                $tampil = $val ? date('d/m/Y H:i', strtotime($val)) : '-';
                                                break;
                                            case 'tanggal_pendek':
                                                $tampil = $val ? date('d/m/Y', strtotime($val)) : '-';
                                                break;
                                            default:
                                                $tampil = htmlspecialchars($val ?? '-');
                                        }
                                        ?>
                                        <td><?= $tampil ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= count($cfg['columns']) ?>" class="text-center text-muted py-3">
                                    Belum ada data untuk laporan ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Footer & Paginasi -->
            <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                <div class="text-muted small">
                    Menampilkan <?= count($data_laporan) > 0 ? $offset + 1 : 0 ?>
                    - <?= min($offset + $limit, $total_data) ?> dari <?= $total_data ?> data
                </div>

                <?php if ($total_pages > 1): ?>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php
                            $qs = fn($p) => '?action=' . urlencode($jenis)
                                . '&tgl_dari=' . urlencode($tgl_dari)
                                . '&tgl_sampai=' . urlencode($tgl_sampai)
                                . '&limit=' . $limit . '&page=' . $p;
                            ?>
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $qs($page - 1) ?>">Previous</a>
                            </li>
                            <?php
                            $start = max(1, $page - 2);
                            $end   = min($total_pages, $page + 2);
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $qs($i) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $qs($page + 1) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>

        </div>
    </div>
<?php endif; ?>