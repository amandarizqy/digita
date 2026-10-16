<div class="card shadow mb-4 border-0">
    <div class="card-body p-0">
        <!-- Baris Limit Paging -->
        <div class="d-flex justify-content-end align-items-center px-4 pt-3 pb-2">
            <form action="index.php" method="GET" class="d-flex align-items-center gap-2">
                <input type="hidden" name="page" value="pengadaan">
                <input type="hidden" name="menu" value="penerimaan">
                <input type="hidden" name="view" value="daftar">
                
                <label class="small text-muted fw-bold mb-0">Tampilkan:</label>
                <select name="limit" class="form-select form-select-sm" style="width: 75px;" onchange="this.form.submit()">
                    <option value="5" <?= (($paging['limit'] ?? 10) == 5) ? 'selected' : '' ?>>5</option>
                    <option value="10" <?= (($paging['limit'] ?? 10) == 10) ? 'selected' : '' ?>>10</option>
                    <option value="20" <?= (($paging['limit'] ?? 10) == 20) ? 'selected' : '' ?>>20</option>
                    <option value="30" <?= (($paging['limit'] ?? 10) == 30) ? 'selected' : '' ?>>30</option>
                </select>
                <span class="small text-muted">data</span>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">NO PENGIRIMAN</th>
                        <th>TGL KIRIM</th>
                        <th>PENGIRIM (UI)</th>
                        <th class="text-center">JML BARANG</th>
                        <th class="text-center">STATUS KEDATANGAN</th>
                        <th class="text-end pe-4">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($list_inbound)): foreach ($list_inbound as $row): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-primary"><?= htmlspecialchars($row['NoFormulir']) ?></td>
                            <td><?= htmlspecialchars($row['TglFormulir']) ?></td>
                            <td><?= htmlspecialchars($row['NamaAkun']) ?></td>
                            <td class="text-center"><?= htmlspecialchars($row['TotalItem']) ?> Unit</td>
                            <td class="text-center">
                                <?php if($row['StatusPengiriman'] == 'DITERIMA'): ?>
                                    <span class="badge bg-success px-3 py-2"><i class="bi bi-box-seam me-1"></i> Diterima (<?= $row['TglTerima'] ?>)</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark px-3 py-2"><i class="bi bi-truck me-1"></i> Dalam Perjalanan</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <a href="index.php?page=pengadaan&menu=penerimaan&view=detail&no_form=<?= urlencode($row['NoFormulir']) ?>" class="btn btn-sm btn-outline-primary px-3">
                                    <i class="bi <?= ($row['StatusPengiriman'] == 'DITERIMA') ? 'bi-eye' : 'bi-box-arrow-in-down' ?> me-1"></i> 
                                    <?= ($row['StatusPengiriman'] == 'DITERIMA') ? 'Detail' : 'Proses Terima' ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>Tidak ada jadwal kedatangan barang untuk unit Anda.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- FOOTER PAGINASI PENERIMAAN -->
        <?php if (!empty($paging) && $paging['total_pages'] > 1): ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap p-3 border-top bg-light">
            <div class="small text-muted mb-2 mb-md-0">
                Menampilkan halaman <strong><?= $paging['current_page'] ?></strong> dari <strong><?= $paging['total_pages'] ?></strong> (Total <strong><?= $paging['total_data'] ?></strong> data)
            </div>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= ($paging['current_page'] <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="index.php?page=pengadaan&menu=penerimaan&view=daftar&limit=<?= $paging['limit'] ?>&p=<?= $paging['current_page'] - 1 ?>"><i class="bi bi-chevron-left"></i> Prev</a>
                    </li>
                    <?php 
                    $current = $paging['current_page']; $total_p = $paging['total_pages'];
                    $start = max(1, $current - 2); $end = min($total_p, $current + 2);
                    for ($i = $start; $i <= $end; $i++): 
                    ?>
                        <li class="page-item <?= ($i == $current) ? 'active' : '' ?>">
                            <a class="page-link" href="index.php?page=pengadaan&menu=penerimaan&view=daftar&limit=<?= $paging['limit'] ?>&p=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($paging['current_page'] >= $paging['total_pages']) ? 'disabled' : '' ?>">
                        <a class="page-link" href="index.php?page=pengadaan&menu=penerimaan&view=daftar&limit=<?= $paging['limit'] ?>&p=<?= $paging['current_page'] + 1 ?>">Next <i class="bi bi-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>