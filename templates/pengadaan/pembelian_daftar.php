<div class="card shadow mb-4 border-0">
    <div class="card-body p-0">
        <!-- Baris Filter Pencarian & Limit Paging -->
        <div class="d-flex justify-content-between align-items-center flex-wrap px-4 pt-3 pb-2 gap-2">
            <!-- Form Smart Search Pembelian -->
            <form action="index.php" method="GET" class="input-group input-group-sm" style="max-width: 320px;">
                <input type="hidden" name="page" value="pengadaan">
                <input type="hidden" name="menu" value="pembelian">
                <input type="hidden" name="view" value="daftar">
                <input type="hidden" name="limit" value="<?= htmlspecialchars($paging['limit'] ?? 10) ?>">

                <input type="text" class="form-control" name="q" placeholder="Cari No Formulir Pembelian..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
                <?php if (!empty($_GET['q'])): ?>
                    <a href="index.php?page=pengadaan&menu=pembelian&view=daftar" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </form>

            <!-- Dropdown Pilihan Jumlah Data Per Halaman -->
            <form action="index.php" method="GET" class="d-flex align-items-center gap-2">
                <input type="hidden" name="page" value="pengadaan">
                <input type="hidden" name="menu" value="pembelian">
                <input type="hidden" name="view" value="daftar">
                <?php if (!empty($_GET['q'])): ?>
                    <input type="hidden" name="q" value="<?= htmlspecialchars($_GET['q']) ?>">
                <?php endif; ?>
                
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
                <thead class="table-light text-muted">
                    <tr>
                        <th class="fw-bold border-bottom-0 py-3 ps-4">NO FORMULIR</th>
                        <th class="fw-bold border-bottom-0 py-3">TANGGAL BELI</th>
                        <th class="fw-bold border-bottom-0 py-3">TOTAL BIAYA</th>
                        <th class="fw-bold border-bottom-0 py-3 text-center">JML ITEM</th>
                        <th class="fw-bold border-bottom-0 py-3 text-center">STATUS</th>
                        <th class="fw-bold border-bottom-0 py-3 text-center pe-4">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($list_pembelian)): foreach ($list_pembelian as $row): ?>
                        <tr>
                            <td class="fw-bold text-primary ps-4"><?= htmlspecialchars($row['NoFormulir']) ?></td>
                            <td><?= htmlspecialchars($row['TglBeli']) ?></td>
                            <td class="fw-bold text-success">Rp <?= number_format($row['TotalBiaya'], 0, ',', '.') ?></td>
                            <td class="text-center"><?= htmlspecialchars($row['TotalItem']) ?> Unit</td>
                            <td class="text-center">
                                <?php if($row['StatusData'] == 'AKTIF'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2">Final</span>
                                <?php else: ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center pe-4">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="index.php?page=pengadaan&menu=pembelian&view=detail&no_form=<?= urlencode($row['NoFormulir']) ?>" class="btn btn-sm btn-outline-primary" title="<?= ($row['StatusData'] == 'AKTIF') ? 'Buka Detail' : 'Edit Draft' ?>">
                                        <i class="bi <?= ($row['StatusData'] == 'AKTIF') ? 'bi-eye' : 'bi-pencil' ?>"></i>
                                    </a>
                                    
                                    <?php if ($row['StatusData'] !== 'AKTIF'): ?>
                                        <form action="modules/pengadaan/proses_pembelian.php?action=delete_draft" method="POST" style="display:inline-block;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus draft formulir pembelian ini?');">
                                            <input type="hidden" name="no_formulir" value="<?= htmlspecialchars($row['NoFormulir']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Draft">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>Belum ada riwayat pembelian.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- FOOTER PAGINASI PEMBELIAN -->
        <?php if (!empty($paging) && $paging['total_pages'] > 1): ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap p-3 border-top bg-light">
            <div class="small text-muted mb-2 mb-md-0">
                Menampilkan halaman <strong><?= $paging['current_page'] ?></strong> dari <strong><?= $paging['total_pages'] ?></strong> (Total <strong><?= $paging['total_data'] ?></strong> data)
            </div>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= ($paging['current_page'] <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="index.php?page=pengadaan&menu=pembelian&view=daftar&limit=<?= $paging['limit'] ?>&p=<?= $paging['current_page'] - 1 ?><?= !empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '' ?>"><i class="bi bi-chevron-left"></i> Prev</a>
                    </li>
                    <?php 
                    $current = $paging['current_page']; $total_p = $paging['total_pages'];
                    $start = max(1, $current - 2); $end = min($total_p, $current + 2);
                    for ($i = $start; $i <= $end; $i++): 
                    ?>
                        <li class="page-item <?= ($i == $current) ? 'active' : '' ?>">
                            <a class="page-link" href="index.php?page=pengadaan&menu=pembelian&view=daftar&limit=<?= $paging['limit'] ?>&p=<?= $i ?><?= !empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '' ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($paging['current_page'] >= $paging['total_pages']) ? 'disabled' : '' ?>">
                        <a class="page-link" href="index.php?page=pengadaan&menu=pembelian&view=daftar&limit=<?= $paging['limit'] ?>&p=<?= $paging['current_page'] + 1 ?><?= !empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '' ?>">Next <i class="bi bi-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>