<div class="card shadow mb-4 border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap">
        <h6 class="m-0 font-weight-bold text-primary text-uppercase mb-2 mb-md-0">
            <i class="bi bi-eye me-1"></i> Monitoring Data: <?= htmlspecialchars($sub) ?>
        </h6>
        
        <!-- FORM SMART SEARCH -->
        <form action="index.php" method="GET" class="d-flex align-items-center mb-0">
            <input type="hidden" name="page" value="pengadaan">
            <input type="hidden" name="menu" value="monitoring">
            <input type="hidden" name="sub" value="<?= htmlspecialchars($sub) ?>">
            
            <div class="input-group input-group-sm" style="width: 280px;">
                <input type="text" class="form-control" name="q" placeholder="Cari data..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
                <?php if (!empty($_GET['q'])): ?>
                    <a href="index.php?page=pengadaan&menu=monitoring&sub=<?= htmlspecialchars($sub) ?>" class="btn btn-outline-secondary" title="Reset Pencarian"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted">
                    <?php if ($sub === 'aset'): ?>
                        <tr>
                            <th class="ps-4 py-3">NO REF (BARCODE)</th>
                            <th>UNIT AP</th>
                            <th>UNIT UP</th>
                            <th class="pe-4">WAKTU DATA</th>
                        </tr>
                    <?php elseif ($sub === 'pembelian'): ?>
                        <tr>
                            <th class="ps-4 py-3">NO FORMULIR</th>
                            <th>TANGGAL BELI</th>
                            <th>PEMBUAT</th>
                            <th class="pe-4">STATUS</th>
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
                                <td><?= htmlspecialchars($row['UnitAp'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['UnitUp'] ?? '-') ?></td>
                                <td class="pe-4"><?= htmlspecialchars($row['WaktuData']) ?></td>
                            <?php elseif ($sub === 'pembelian'): ?>
                                <td class="fw-bold text-primary ps-4"><?= htmlspecialchars($row['NoFormulir']) ?></td>
                                <td><?= htmlspecialchars($row['TglBeli']) ?></td>
                                <td><?= htmlspecialchars($row['NamaAkun']) ?></td>
                                <td class="pe-4"><span class="badge bg-success"><?= htmlspecialchars($row['StatusData']) ?></span></td>
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
                        <tr><td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-search fs-1 d-block mb-2 text-secondary"></i>Data tidak ditemukan atau tidak sesuai dengan kata kunci pencarian.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>