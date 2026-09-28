<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-cart-plus me-2"></i>Daftar Pembelian S41</h1>
    <a href="pembelian.php?view=baru" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Buat Formulir Baru</a>
</div>

<div class="card shadow mb-4 border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted">
                    <tr>
                        <th class="fw-bold border-bottom-0 py-3 ps-4">NO FORMULIR</th>
                        <th class="fw-bold border-bottom-0 py-3">TGL BELI</th>
                        <th class="fw-bold border-bottom-0 py-3">TOTAL ITEM</th>
                        <th class="fw-bold border-bottom-0 py-3">TOTAL BIAYA</th>
                        <th class="fw-bold border-bottom-0 py-3 text-center">STATUS</th>
                        <th class="fw-bold border-bottom-0 py-3 text-center pe-4">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($list_pembelian)): ?>
                        <?php foreach ($list_pembelian as $row): ?>
                            <tr>
                                <td class="fw-bold text-primary ps-4">
                                    <?= htmlspecialchars($row['NoFormulir'] ?? '-') ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['TglBeli'] ?? '-') ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['TotalItem'] ?? '0') ?> Unit
                                </td>
                                <td>
                                    Rp <?= number_format((float)($row['TotalBiaya'] ?? 0), 0, ',', '.') ?>
                                </td>
                                <td class="text-center">
                                    <?php if(($row['StatusData'] ?? '') == 'AKTIF'): ?>
                                        <span class="badge rounded-pill bg-success bg-opacity-10 text-success px-3 py-2 border border-success border-opacity-25">
                                            <i class="bi bi-check-circle me-1"></i> Aktif
                                        </span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill bg-warning bg-opacity-10 text-warning px-3 py-2 border border-warning border-opacity-25">
                                            <i class="bi bi-pencil-square me-1"></i> Draft
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center pe-4">
                                    <a href="pembelian.php?view=detail&no_form=<?= urlencode($row['NoFormulir']) ?>" class="btn btn-sm btn-outline-primary me-1" title="Buka/Edit">
                                        <i class="bi <?php echo ($row['StatusData'] == 'AKTIF') ? 'bi-eye' : 'bi-pencil'; ?>"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                Belum ada data formulir pembelian.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>