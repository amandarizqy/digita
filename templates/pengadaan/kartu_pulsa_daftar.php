<div class="card shadow mb-4 border-0">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-wallet2 me-1"></i>Daftar Saldo & Isi Pulsa S41</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted">
                    <tr>
                        <th class="ps-4 py-3">SIM ID (ICCID)</th>
                        <th>NOMOR KARTU</th>
                        <th>SALDO TERAKHIR (Rp)</th>
                        <th class="pe-4">TGL ISI TERAKHIR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data['list_pulsa'])): foreach ($data['list_pulsa'] as $row): ?>
                        <tr>
                            <td class="fw-bold text-primary ps-4"><?= htmlspecialchars($row['SimId']) ?></td>
                            <td>+<?= htmlspecialchars($row['NomorAkun']) ?></td>
                            <td class="fw-bold text-success">Rp <?= number_format($row['JumlahKredit'], 0, ',', '.') ?></td>
                            <td class="pe-4"><?= htmlspecialchars($row['TglPulsaTerakhir']) ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">
                            <i class="bi bi-wallet fs-1 d-block mb-2 text-secondary"></i>Belum ada riwayat pengisian pulsa di unit ini.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>