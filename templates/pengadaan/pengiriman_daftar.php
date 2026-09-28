<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-truck me-2"></i>Daftar Pengiriman Barang (Outbound)</h1>
    <a href="pengiriman.php?view=baru" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Buat Pengiriman Baru</a>
</div>

<div class="card shadow mb-4 border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted">
                    <tr>
                        <th class="fw-bold border-bottom-0 py-3 ps-4">NO FORMULIR</th>
                        <th class="fw-bold border-bottom-0 py-3">TANGGAL</th>
                        <th class="fw-bold border-bottom-0 py-3">PENGIRIM</th>
                        <th class="fw-bold border-bottom-0 py-3 text-center">JML BARANG</th>
                        <th class="fw-bold border-bottom-0 py-3 text-center">STATUS SISTEM</th>
                        <th class="fw-bold border-bottom-0 py-3 text-center">PENGIRIMAN</th>
                        <th class="fw-bold border-bottom-0 py-3 text-center pe-4">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($list_pengiriman)): ?>
                        <?php foreach ($list_pengiriman as $row): ?>
                            <tr>
                                <td class="fw-bold text-primary ps-4"><?= htmlspecialchars($row['NoFormulir']) ?></td>
                                <td><?= htmlspecialchars($row['TglFormulir']) ?></td>
                                <td><?= htmlspecialchars($row['NamaAkun']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($row['TotalItem'] ?? '0') ?> Unit</td>
                                
                                <td class="text-center">
                                    <?php if($row['StatusData'] == 'AKTIF'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2">Final</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2">Draft</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="text-center">
                                    <?php if($row['StatusPengiriman'] == 'DITERIMA'): ?>
                                        <span class="badge bg-primary px-3 py-2"><i class="bi bi-box-seam me-1"></i> Diterima</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark px-3 py-2"><i class="bi bi-truck me-1"></i> Diperjalanan</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="text-center pe-4">
                                    <a href="pengiriman.php?view=detail&no_form=<?= urlencode($row['NoFormulir']) ?>" class="btn btn-sm btn-outline-primary" title="Buka/Edit">
                                        <i class="bi <?php echo ($row['StatusData'] == 'AKTIF') ? 'bi-eye' : 'bi-pencil'; ?>"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i> Belum ada riwayat pengiriman.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>