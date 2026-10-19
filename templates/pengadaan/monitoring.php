<div class="card shadow mb-4 border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="m-0 font-weight-bold text-primary text-uppercase mb-2 mb-md-0">
            <i class="bi bi-eye me-1"></i> Monitoring Data: <?= htmlspecialchars($sub === 'pembelian' ? 'Aktivasi Perdana' : $sub) ?>
        </h6>
        
        <div class="d-flex align-items-center flex-wrap gap-3">
            <!-- DROPDOWN LIMIT PAGING -->
            <form action="index.php" method="GET" class="d-flex align-items-center gap-2 mb-0">
                <input type="hidden" name="page" value="pengadaan">
                <input type="hidden" name="menu" value="monitoring">
                <input type="hidden" name="sub" value="<?= htmlspecialchars($sub) ?>">
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

            <!-- FORM SMART SEARCH -->
            <form action="index.php" method="GET" class="d-flex align-items-center mb-0">
                <input type="hidden" name="page" value="pengadaan">
                <input type="hidden" name="menu" value="monitoring">
                <input type="hidden" name="sub" value="<?= htmlspecialchars($sub) ?>">
                <input type="hidden" name="limit" value="<?= htmlspecialchars($paging['limit'] ?? 10) ?>">
                
                <div class="input-group input-group-sm" style="width: 250px;">
                    <input type="text" class="form-control" name="q" placeholder="Cari data..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
                    <?php if (!empty($_GET['q'])): ?>
                        <a href="index.php?page=pengadaan&menu=monitoring&sub=<?= htmlspecialchars($sub) ?>&limit=<?= htmlspecialchars($paging['limit'] ?? 10) ?>" class="btn btn-outline-secondary" title="Reset Pencarian"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted">
                    <?php if ($sub === 'aset'): ?>
                        <tr>
                            <th class="ps-4 py-3">NO REF (BARCODE)</th>
                            <th>UNIT UPI</th>
                            <th>UNIT AP</th>
                            <th>UNIT UP</th>
                            <th class="pe-4">WAKTU DATA</th>
                        </tr>
                    <?php elseif ($sub === 'pembelian'): ?>
                        <tr>
                            <th class="ps-4 py-3">SIM ID (ICCID)</th>
                            <th>NOMOR KARTU</th>
                            <th>PROVIDER / PRODUK</th>
                            <th>JENIS</th>
                            <th>UNIT KERJA</th>
                            <th class="pe-4">TGL AKTIVASI</th>
                        </tr>
                    <?php elseif ($sub === 'pengiriman' || $sub === 'penerimaan'): ?>
                        <tr>
                            <th class="ps-4 py-3">NO FORMULIR</th>
                            <th>TANGGAL</th>
                            <th>AKUN PEMPROSES</th>
                            <th class="pe-4">STATUS</th>
                        </tr>
                    <?php elseif ($sub === 'pulsa'): ?>
                        <tr>
                            <th class="ps-4 py-3">SIM ID</th>
                            <th>NOMOR KARTU</th>
                            <th>JUMLAH KREDIT (Rp)</th>
                            <th class="pe-4">TGL PULSA TERAKHIR</th>
                        </tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <?php if (!empty($list_monitoring)): foreach ($list_monitoring as $row): ?>
                        <tr>
                            <?php if ($sub === 'aset'): ?>
                                <td class="fw-bold text-primary ps-4"><?= htmlspecialchars($row['NoRef']) ?></td>
                                <td><?= htmlspecialchars($row['NamaUpi'] ?? $row['UnitUpi'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['NamaAp'] ?? $row['UnitAp'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['NamaUp'] ?? $row['UnitUp'] ?? '-') ?></td>
                                <td class="pe-4"><?= htmlspecialchars($row['WaktuData']) ?></td>
                            <?php elseif ($sub === 'pembelian'): ?>
                                <td class="fw-bold text-primary ps-4"><?= htmlspecialchars($row['SimId']) ?></td>
                                <td>+<?= htmlspecialchars($row['NomorAkun']) ?></td>
                                <td><?= htmlspecialchars($row['NamaProvider'] ?? '-') ?> - <?= htmlspecialchars($row['NamaProduk'] ?? '-') ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($row['JenisProduk']) ?></span></td>
                                <td><small class="text-muted"><?= htmlspecialchars(($row['NamaAp'] ?? '-') !== '-' ? $row['NamaAp'] : ($row['NamaUp'] ?? '-')) ?></small></td>
                                <td class="pe-4"><?= htmlspecialchars($row['TglAktifasi']) ?></td>
                            <?php elseif ($sub === 'pengiriman' || $sub === 'penerimaan'): ?>
                                <td class="fw-bold text-primary ps-4"><?= htmlspecialchars($row['NoFormulir']) ?></td>
                                <td><?= htmlspecialchars($row['TglFormulir'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['NamaAkun']) ?></td>
                                <td class="pe-4"><span class="badge bg-info text-dark"><?= htmlspecialchars($row['StatusData'] ?? $row['StatusPengiriman'] ?? '-') ?></span></td>
                            <?php elseif ($sub === 'pulsa'): ?>
                                <td class="fw-bold text-primary ps-4"><?= htmlspecialchars($row['SimId']) ?></td>
                                <td>+<?= htmlspecialchars($row['NomorAkun']) ?></td>
                                <td class="fw-bold text-success">Rp <?= number_format($row['JumlahKredit'], 0, ',', '.') ?></td>
                                <td class="pe-4"><?= htmlspecialchars($row['TglPulsaTerakhir']) ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr>
                            <td colspan="<?= ($sub === 'pembelian') ? 6 : 5 ?>" class="text-center py-5 text-muted">
                                <i class="bi bi-search fs-1 d-block mb-2 text-secondary"></i>Data tidak ditemukan atau tidak sesuai dengan wilayah hierarki dan kata kunci pencarian.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- FOOTER PAGINASI MONITORING -->
        <?php if (!empty($paging) && $paging['total_pages'] > 1): ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap p-3 border-top bg-light">
            <div class="small text-muted mb-2 mb-md-0">
                Menampilkan halaman <strong><?= $paging['current_page'] ?></strong> dari <strong><?= $paging['total_pages'] ?></strong> (Total <strong><?= $paging['total_data'] ?></strong> data)
            </div>
            
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= ($paging['current_page'] <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="index.php?page=pengadaan&menu=monitoring&sub=<?= htmlspecialchars($sub) ?>&limit=<?= $paging['limit'] ?>&p=<?= $paging['current_page'] - 1 ?><?= !empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '' ?>">
                            <i class="bi bi-chevron-left"></i> Prev
                        </a>
                    </li>

                    <?php 
                    $current = $paging['current_page'];
                    $total_p = $paging['total_pages'];
                    $start = max(1, $current - 2);
                    $end = min($total_p, $current + 2);

                    if ($start > 1) {
                        echo '<li class="page-item"><a class="page-link" href="index.php?page=pengadaan&menu=monitoring&sub=' . $sub . '&limit=' . $paging['limit'] . '&p=1' . (!empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '') . '">1</a></li>';
                        if ($start > 2) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                    }

                    for ($i = $start; $i <= $end; $i++): 
                    ?>
                        <li class="page-item <?= ($i == $current) ? 'active' : '' ?>">
                            <a class="page-link" href="index.php?page=pengadaan&menu=monitoring&sub=<?= htmlspecialchars($sub) ?>&limit=<?= $paging['limit'] ?>&p=<?= $i ?><?= !empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '' ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php 
                    endfor; 

                    if ($end < $total_p) {
                        if ($end < $total_p - 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                        echo '<li class="page-item"><a class="page-link" href="index.php?page=pengadaan&menu=monitoring&sub=' . $sub . '&limit=' . $paging['limit'] . '&p=' . $total_p . (!empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '') . '">' . $total_p . '</a></li>';
                    }
                    ?>

                    <li class="page-item <?= ($paging['current_page'] >= $paging['total_pages']) ? 'disabled' : '' ?>">
                        <a class="page-link" href="index.php?page=pengadaan&menu=monitoring&sub=<?= htmlspecialchars($sub) ?>&limit=<?= $paging['limit'] ?>&p=<?= $paging['current_page'] + 1 ?><?= !empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '' ?>">
                            Next <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

    </div>
</div>