<!-- templates/perencanaan/hasil_survey.php -->

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="bi bi-table"></i> Data Hasil Survey SR</h4>
    <a href="?module=perencanaan&action=input_survey" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Input Survey Baru
    </a>
    <a href="?module=perencanaan" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali ke Dashboard</a>
</div>

<!-- Alert Notifikasi -->
<?php if (!empty($pesan_sukses)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $pesan_sukses ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($pesan_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $pesan_error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- CARD FILTER & PENCARIAN (AUTO SUBMIT) -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <input type="hidden" name="module" value="perencanaan">
            <input type="hidden" name="action" value="hasil_survey">

            <!-- Pencarian Keyword (Submit saat tekan Enter atau kehilangan fokus) -->
            <div class="col-md-5">
                <label class="form-label fw-semibold">Cari IdPel / Nama / Unit</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="keyword" class="form-control" value="<?= htmlspecialchars($keyword) ?>" placeholder="Ketik lalu tekan Enter..." onchange="this.form.submit()">
                </div>
            </div>

            <!-- Filter Status SR (Auto Submit saat opsi dipilih) -->
            <div class="col-md-3">
                <label class="form-label fw-semibold">Status Sambungan SR</label>
                <select name="posisi_sr" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Semua Status --</option>
                    <option value="0" <?= $posisi_sr === '0' ? 'selected' : '' ?>>0 — Tidak Terhubung (Mandiri)</option>
                    <option value="1" <?= $posisi_sr === '1' ? 'selected' : '' ?>>1 — Terhubung Pelanggan Lain</option>
                </select>
            </div>

            <!-- Limit per Halaman (Auto Submit saat opsi dipilih) -->
            <div class="col-md-2">
                <label class="form-label fw-semibold">Tampilkan</label>
                <select name="limit" class="form-select" onchange="this.form.submit()">
                    <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10 Data</option>
                    <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25 Data</option>
                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50 Data</option>
                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100 Data</option>
                </select>
            </div>

            <!-- Tombol Reset -->
            <div class="col-md-2 d-grid">
                <a href="?module=perencanaan&action=hasil_survey" class="btn btn-outline-secondary" title="Bersihkan Filter">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- TABEL HASIL SURVEY -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="fw-bold mb-0 text-secondary">
            Total Data Ditemukan: <span class="badge bg-primary fs-6"><?= number_format($total_data) ?></span>
        </h6>
        <small class="text-muted">Halaman <?= $page ?> dari <?= $total_pages ?></small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 60px;">No</th>
                        <th>ID Pelanggan (IdPel)</th>
                        <th>Nama Pelanggan</th>
                        <th class="text-center">Status Posisi SR</th>
                        <th class="text-center">Unit (UP / AP / UPI)</th>
                        <th class="text-center" style="width: 100px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data_hasil)): ?>
                        <?php $no = $offset + 1; foreach ($data_hasil as $row): ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td><code><?= htmlspecialchars($row['Idpel']) ?></code></td>
                                <td><strong><?= htmlspecialchars($row['NamaPelanggan'] ?? '— (Tidak ada di DIL)') ?></strong></td>
                                <td class="text-center">
                                    <span class="badge <?= $row['PosisiSR'] == '1' ? 'bg-warning text-dark' : 'bg-success' ?>">
                                        <?= $row['PosisiSR'] == '1' ? '1 — Terhubung' : '0 — Mandiri' ?>
                                    </span>
                                </td>
                                <td class="text-center small">
                                    <?= htmlspecialchars($row['UnitUp'] ?? '-') ?> / 
                                    <?= htmlspecialchars($row['UnitAp'] ?? '-') ?> / 
                                    <?= htmlspecialchars($row['UnitUpi'] ?? '-') ?>
                                </td>
                                <td class="text-center">
                                    <a href="?module=perencanaan&action=hasil_survey&act=delete&idpel=<?= urlencode($row['Idpel']) ?>" 
                                       class="btn btn-outline-danger btn-sm"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus data survey IdPel <?= htmlspecialchars($row['Idpel']) ?>?');"
                                       title="Hapus Data">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                Data hasil survey SR tidak ditemukan.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FOOTER NAVIGASI PAGINASI -->
    <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
            <small class="text-muted">
                Menampilkan <?= min($offset + 1, $total_data) ?> – <?= min($offset + $limit, $total_data) ?> dari <?= number_format($total_data) ?> data
            </small>

            <nav aria-label="Navigasi Halaman">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?module=perencanaan&action=hasil_survey&page=1&keyword=<?= urlencode($keyword) ?>&posisi_sr=<?= urlencode($posisi_sr) ?>&limit=<?= $limit ?>">First</a>
                    </li>
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?module=perencanaan&action=hasil_survey&page=<?= $page - 1 ?>&keyword=<?= urlencode($keyword) ?>&posisi_sr=<?= urlencode($posisi_sr) ?>&limit=<?= $limit ?>">&laquo;</a>
                    </li>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page   = min($total_pages, $page + 2);
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?module=perencanaan&action=hasil_survey&page=<?= $i ?>&keyword=<?= urlencode($keyword) ?>&posisi_sr=<?= urlencode($posisi_sr) ?>&limit=<?= $limit ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?module=perencanaan&action=hasil_survey&page=<?= $page + 1 ?>&keyword=<?= urlencode($keyword) ?>&posisi_sr=<?= urlencode($posisi_sr) ?>&limit=<?= $limit ?>">&raquo;</a>
                    </li>
                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?module=perencanaan&action=hasil_survey&page=<?= $total_pages ?>&keyword=<?= urlencode($keyword) ?>&posisi_sr=<?= urlencode($posisi_sr) ?>&limit=<?= $limit ?>">Last</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>