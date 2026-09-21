<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-box-arrow-in-down me-2"></i>Penerimaan Perangkat S41</h1>
</div>

<?php if (isset($_GET['status']) && $_GET['status'] == 'sukses'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>Penerimaan barang berhasil dikonfirmasi! Alat siap digunakan.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Pengiriman Menunggu Konfirmasi</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th width="5%">No</th>
                        <th>No Formulir</th>
                        <th>Tanggal Kirim</th>
                        <th>Pengirim</th>
                        <th>Unit Tujuan</th>
                        <th width="15%" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($pengiriman_pending) > 0): ?>
                        <?php $no = 1; foreach ($pengiriman_pending as $row): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($row['NoFormulir']) ?></td>
                                <td><?= htmlspecialchars($row['TglFormulir']) ?></td>
                                <td><?= htmlspecialchars($row['Pengirim']) ?></td>
                                <td><?= htmlspecialchars($row['KodeUp']) ?></td>
                                <td class="text-center">
                                    <form action="proses_penerimaan.php" method="POST">
                                        <input type="hidden" name="no_formulir" value="<?= htmlspecialchars($row['NoFormulir']) ?>">
                                        <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Konfirmasi bahwa fisik barang telah diterima dengan baik?');">
                                            <i class="bi bi-check2-square me-1"></i> Terima Barang
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                Tidak ada paket perangkat S41 yang sedang dalam perjalanan ke unit Anda.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>