<!-- templates/perencanaan/data_riwayat.php -->

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="bi bi-upload"></i> Data Riwayat Pelunasan</h4>
    <a href="?module=perencanaan&action=upload_riwayat" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Input Riwayat Baru
    </a>
    <a href="?module=perencanaan" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali ke Dashboard</a>
</div>

<!-- Menampilkan Pesan Notifikasi -->
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

<!-- Card Tabel Data -->
<div class="card shadow-sm">
    <div class="card-header bg-white fw-bold d-flex align-items-center justify-content-between">
        <span><i class="bi bi-table"></i> Data Riwayat Pelunasan (Terbaru)</span>
    </div>

    <div class="card-body">
        <!-- Form Filter & Pencarian -->
        <form method="GET" action="" class="row g-2 mb-3 align-items-center">
            <!-- Hidden Input untuk menjaga modul & action -->
            <input type="hidden" name="module" value="perencanaan">
            <input type="hidden" name="action" value="data_riwayat">

            <!-- Pilih Jumlah Data per Halaman -->
            <div class="col-auto d-flex align-items-center gap-2">
                <label class="form-label mb-0 small text-muted">Tampilkan:</label>
                <select name="limit" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                    <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                    <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                </select>
                <span class="small text-muted">data</span>
            </div>

            <!-- Field Pencarian -->
            <div class="col-auto ms-auto d-flex gap-2">
                <input type="text" name="keyword" class="form-control form-control-sm" placeholder="Cari IdPel / ThBlRek / Unit..." value="<?= htmlspecialchars($keyword) ?>">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Cari</button>
                <?php if (!empty($keyword)): ?>
                    <a href="?module=perencanaan&action=data_riwayat" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i> Reset</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Tabel Data -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm align-middle" style="font-size: 0.9rem;">
                <thead class="table-light">
                    <tr>
                        <th>IdPel</th>
                        <th>ThBlRek</th>
                        <th>TglBayar</th>
                        <th>RpBK</th>
                        <th>RpTag</th>
                        <th>UP/AP/UPI</th>
                        <th>Waktu Input</th>
                        <th class="text-center" style="width: 80px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data_riwayat)): ?>
                        <?php foreach ($data_riwayat as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['IdPel']) ?></td>
                                <td><?= htmlspecialchars($row['ThBlRek']) ?></td>
                                <td><?= htmlspecialchars($row['TglBayar'] ?? '-') ?></td>
                                <td>Rp <?= number_format((float)($row['RpBK'] ?? 0), 0, ',', '.') ?></td>
                                <td>Rp <?= number_format((float)($row['RpTag'] ?? 0), 0, ',', '.') ?></td>
                                <td><?= htmlspecialchars($row['UnitUp']) ?> / <?= htmlspecialchars($row['UnitAp']) ?> / <?= htmlspecialchars($row['UnitUpi']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($row['WaktuData'])) ?></td>
                                <td class="text-center">
                                    <!-- Tombol Hapus -->
                                    <a href="?module=perencanaan&action=data_riwayat&act=delete&idpel=<?= urlencode($row['IdPel']) ?>&thblrek=<?= urlencode($row['ThBlRek']) ?>&keyword=<?= urlencode($keyword) ?>&limit=<?= $limit ?>&page=<?= $page ?>" 
                                       class="btn btn-outline-danger btn-sm py-0 px-2" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus data IdPel: <?= htmlspecialchars($row['IdPel']) ?> (Rek: <?= htmlspecialchars($row['ThBlRek']) ?>)?');"
                                       title="Hapus Data">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-3">Belum ada data riwayat pelunasan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Footer Tabel: Informasi & Paginasi -->
        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
            <div class="text-muted small">
                Menampilkan <?= count($data_riwayat) > 0 ? $offset + 1 : 0 ?> - <?= min($offset + $limit, $total_data) ?> dari <?= $total_data ?> data
            </div>

            <?php if ($total_pages > 1): ?>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <!-- Tombol Previous -->
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?module=perencanaan&action=data_riwayat&keyword=<?= urlencode($keyword) ?>&limit=<?= $limit ?>&page=<?= $page - 1 ?>">Previous</a>
                        </li>

                        <!-- Loop Nomor Halaman -->
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page   = min($total_pages, $page + 2);
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                <a class="page-link" href="?module=perencanaan&action=data_riwayat&keyword=<?= urlencode($keyword) ?>&limit=<?= $limit ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <!-- Tombol Next -->
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?module=perencanaan&action=data_riwayat&keyword=<?= urlencode($keyword) ?>&limit=<?= $limit ?>&page=<?= $page + 1 ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>