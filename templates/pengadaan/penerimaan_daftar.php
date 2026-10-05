<div class="card shadow mb-4 border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted">
                    <tr>
                        <th class="fw-bold border-bottom-0 py-3 ps-4">NO PENGIRIMAN</th>
                        <th class="fw-bold border-bottom-0 py-3">TGL KIRIM</th>
                        <th class="fw-bold border-bottom-0 py-3">PENGIRIM (UI)</th>
                        <th class="fw-bold border-bottom-0 py-3 text-center">JML BARANG</th>
                        <th class="fw-bold border-bottom-0 py-3 text-center">STATUS KEDATANGAN</th>
                        <th class="fw-bold border-bottom-0 py-3 text-center pe-4">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($list_inbound)): ?>
                        <?php foreach ($list_inbound as $row): ?>
                            <tr>
                                <td class="fw-bold text-primary ps-4"><?= htmlspecialchars($row['NoFormulir']) ?></td>
                                <td><?= htmlspecialchars($row['TglFormulir']) ?></td>
                                <td><?= htmlspecialchars($row['NamaAkun']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($row['TotalItem'] ?? '0') ?> Unit</td>
                                
                                <td class="text-center">
                                    <?php if($row['StatusPengiriman'] == 'DIKIRIM'): ?>
                                        <span class="badge rounded-pill bg-warning text-dark px-3 py-2">
                                            <i class="bi bi-truck me-1"></i> Sedang Menuju Lokasi Anda
                                        </span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill bg-success px-3 py-2">
                                            <i class="bi bi-check-all me-1"></i> Sudah Anda Terima
                                        </span><br>
                                        <small class="text-muted" style="font-size:11px;">Tgl: <?= $row['TglTerima'] ?></small>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="text-center pe-4">
                                    <!-- PERBAIKAN LINK KE FRONT CONTROLLER INDEX.PHP -->
                                    <?php if($row['StatusPengiriman'] == 'DIKIRIM'): ?>
                                        <a href="index.php?page=pengadaan&menu=penerimaan&view=detail&no_form=<?= urlencode($row['NoFormulir']) ?>" class="btn btn-sm btn-primary fw-bold shadow-sm">
                                            <i class="bi bi-clipboard-check"></i> Inspeksi & Terima
                                        </a>
                                    <?php else: ?>
                                        <a href="index.php?page=pengadaan&menu=penerimaan&view=detail&no_form=<?= urlencode($row['NoFormulir']) ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-eye"></i> Detail
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-shield-check fs-1 d-block mb-2 text-secondary"></i> Tidak ada jadwal kedatangan barang untuk unit Anda.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>