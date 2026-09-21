<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="bi bi-upload"></i> Data Riwayat Pelunasan</h4>
    <a href="?module=perencanaan" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali ke Dashboard</a>
</div>
  <!-- Kolom Tabel Data -->
    <div>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold">Data Riwayat Pelunasan (Terbaru)</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm" style="font-size: 0.9rem;">
                        <thead class="table-light">
                            <tr>
                                <th>IdPel</th>
                                <th>ThBlRek</th>
                                <th>TglBayar</th>
                                <th>RpBK</th>
                                <th>RpTag</th>
                                <th>UP/AP/UPI</th>
                                <th>Waktu Input</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Looping data dari backend -->
                            <?php if (!empty($data_riwayat)): ?>
                                <?php foreach ($data_riwayat as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['IdPel']) ?></td>
                                        <td><?= htmlspecialchars($row['ThBlRek']) ?></td>
                                        <td><?= htmlspecialchars($row['TglBayar']) ?></td>
                                        <td>Rp <?= number_format((float)$row['RpBK'], 0, ',', '.') ?></td>
                                        <td>Rp <?= number_format((float)$row['RpTag'], 0, ',', '.') ?></td>
                                        <td><?= htmlspecialchars($row['UnitUp']) ?> / <?= htmlspecialchars($row['UnitAp']) ?> / <?= htmlspecialchars($row['UnitUpi']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($row['WaktuData'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Belum ada data riwayat pelunasan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>