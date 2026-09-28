<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-cart me-2"></i>Keranjang Pembelian</h1>
    <a href="pembelian.php?view=daftar" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<!-- Informasi Header -->
<div class="alert alert-primary shadow-sm mb-4">
    <strong>No. Form:</strong> <?= htmlspecialchars($no_formulir) ?> &bull; 
    <strong>Tanggal:</strong> <?= htmlspecialchars($formulir['TglBeli']) ?> &bull;
    <strong>Pembuat:</strong> <?= htmlspecialchars($formulir['NamaAkun']) ?>
</div>

<!-- Form Input (Hanya tampil jika status masih Draft/TIDAK) -->
<?php if ($formulir['StatusData'] === 'TIDAK'): ?>
<div class="card shadow mb-4">
    <div class="card-header bg-white">
        <ul class="nav nav-tabs card-header-tabs" id="pembelianTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold" data-bs-toggle="tab" data-bs-target="#manual" type="button" role="tab"><i class="bi bi-keyboard me-1"></i>Input Manual</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-success" data-bs-toggle="tab" data-bs-target="#excel" type="button" role="tab"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Upload CSV</button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content">
            <!-- TAB MANUAL -->
            <div class="tab-pane fade show active" id="manual" role="tabpanel">
                <form action="../../modules/pengadaan/proses_pembelian.php?action=add_item" method="POST">
                    <input type="hidden" name="metode" value="manual">
                    <input type="hidden" name="no_formulir" value="<?= htmlspecialchars($no_formulir) ?>">
                    
                    <div class="row align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Nomor Ref (NoRef)</label>
                            <input type="text" class="form-control" name="no_ref" placeholder="Contoh: S41-001" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Harga Beli (Rp)</label>
                            <input type="number" class="form-control" name="harga" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Stiker QC</label>
                            <select class="form-select" name="stiker_qc" required>
                                <option value="ADA">ADA</option>
                                <option value="TIDAK">TIDAK</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Cacat Fisik</label>
                            <select class="form-select" name="cacat_fisik" required>
                                <option value="TIDAK">TIDAK</option>
                                <option value="YA">YA</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>Tambah</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- TAB EXCEL -->
            <div class="tab-pane fade" id="excel" role="tabpanel">
                <form action="../../modules/pengadaan/proses_pembelian.php?action=add_item" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="metode" value="excel">
                    <input type="hidden" name="no_formulir" value="<?= htmlspecialchars($no_formulir) ?>">
                    <div class="row align-items-end">
                        <div class="col-md-9">
                            <label class="form-label fw-bold">Pilih File Data (.csv)</label>
                            <input class="form-control" type="file" name="file_excel" accept=".csv" required>
                            <small class="form-text">Format header: <i>NoRef;HargaBeli;StikerQC;CacatFisik</i></small>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-success w-100"><i class="bi bi-cloud-upload me-1"></i>Upload Massal</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Tabel Daftar Aset -->
<div class="card shadow mb-4 border-0">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Barang Terinput</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">NO REF</th>
                        <th>STIKER QC</th>
                        <th>CACAT FISIK</th>
                        <th class="pe-4 text-end">HARGA BELI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total = 0;
                    if (!empty($items)):
                        foreach ($items as $item): 
                            $total += $item['HargaBeli'];
                    ?>
                        <tr>
                            <td class="fw-bold ps-4"><?= htmlspecialchars($item['NoRef']) ?></td>
                            <td><?= htmlspecialchars($item['StikerQC']) ?></td>
                            <td><?= htmlspecialchars($item['CacatFisik']) ?></td>
                            <td class="pe-4 text-end">Rp <?= number_format($item['HargaBeli'], 0, ',', '.') ?></td>
                        </tr>
                    <?php 
                        endforeach; 
                    else: 
                    ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada barang di keranjang ini.</td></tr>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($items)): ?>
                <tfoot class="bg-light">
                    <tr>
                        <td colspan="3" class="text-end fw-bold py-3">TOTAL BIAYA:</td>
                        <td class="text-end pe-4 fw-bold text-primary fs-5 py-3">Rp <?= number_format($total, 0, ',', '.') ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
    
    <!-- Tombol Eksekusi -->
    <?php if ($formulir['StatusData'] === 'TIDAK' && !empty($items)): ?>
    <div class="card-footer bg-white p-3">
        <form action="../../modules/pengadaan/proses_pembelian.php?action=execute_form" method="POST">
            <input type="hidden" name="no_formulir" value="<?= htmlspecialchars($no_formulir) ?>">
            <button type="submit" class="btn btn-danger btn-lg w-100 fw-bold" onclick="return confirm('Yakin ingin eksekusi? Aset akan didaftarkan permanen ke Master Barang.')">
                <i class="bi bi-check2-all me-2"></i>FINALISASI & EKSEKUSI PEMBELIAN
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>