<div class="card shadow mb-4 border-0">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-wallet2 me-1"></i>Daftar Saldo, Pulsa & Masa Aktif Kartu S41</h6>
    </div>
    <div class="card-body p-0">
        <!-- Form Smart Search Kartu -->
        <form action="index.php" method="GET" class="mb-3 px-4 pt-3">
            <input type="hidden" name="page" value="pengadaan">
            <input type="hidden" name="menu" value="kartu">
            <input type="hidden" name="sub" value="<?= htmlspecialchars($sub) ?>">
            <input type="hidden" name="view" value="daftar">

            <div class="input-group input-group-sm" style="max-width: 320px;">
                <input type="text" class="form-control" name="q" placeholder="Cari SIM ID / No Kartu..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
                <?php if (!empty($_GET['q'])): ?>
                    <a href="index.php?page=pengadaan&menu=kartu&sub=<?= htmlspecialchars($sub) ?>&view=daftar" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted">
                    <tr>
                        <th class="ps-4 py-3">SIM ID (ICCID)</th>
                        <th>NOMOR KARTU</th>
                        <th>SALDO (Rp)</th>
                        <th>TGL ISI TERAKHIR</th>
                        <th>MASA AKTIF (ESTIMASI)</th>
                        <th class="pe-4 text-center">STATUS KARTU</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data['list_pulsa'])): foreach ($data['list_pulsa'] as $row): 
                        $sisa_hari = $row['SisaHari'] ?? 0;
                    ?>
                        <tr>
                            <td class="fw-bold text-primary ps-4"><?= htmlspecialchars($row['SimId']) ?></td>
                            <td>+<?= htmlspecialchars($row['NomorAkun']) ?></td>
                            <td class="fw-bold text-success">Rp <?= number_format($row['JumlahKredit'], 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars($row['TglPulsaTerakhir']) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($row['MasaAktif']) ?></td>
                            <td class="text-center pe-4">
                                <?php if ($sisa_hari < 0): ?>
                                    <span class="badge bg-danger px-3 py-2"><i class="bi bi-x-circle me-1"></i> Expired (Mati)</span>
                                <?php elseif ($sisa_hari <= 7): ?>
                                    <span class="badge bg-warning text-dark px-3 py-2"><i class="bi bi-exclamation-triangle me-1"></i> Hampir Habis (<?= $sisa_hari ?> hr)</span>
                                <?php else: ?>
                                    <span class="badge bg-success px-3 py-2"><i class="bi bi-check-circle me-1"></i> Aktif (<?= $sisa_hari ?> hr lagi)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-wallet fs-1 d-block mb-2 text-secondary"></i>Belum ada riwayat pengisian pulsa di unit ini.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>