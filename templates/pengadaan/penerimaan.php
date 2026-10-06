<?php 
$formulir = $formulir ?? false;
$items = $items ?? [];

if (!$formulir):
?>
    <div class="alert alert-danger shadow-sm mt-4">
        <h4>Data Pengiriman Tidak Ditemukan!</h4>
        <a href="index.php?page=pengadaan&menu=penerimaan" class="btn btn-secondary mt-2">Kembali ke Daftar Inbound</a>
    </div>
<?php return; endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-clipboard-check me-2"></i>Inspeksi & Penerimaan Inbound</h1>
    <a href="index.php?page=pengadaan&menu=penerimaan" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<!-- Informasi Header Pengiriman -->
<div class="card shadow-sm border-0 mb-4 p-4 bg-white">
    <div class="row">
        <div class="col-md-4">
            <span class="text-muted small d-block">No. Pengiriman / Formulir</span>
            <h5 class="fw-bold text-primary mb-0"><?= htmlspecialchars($formulir['NoFormulir']) ?></h5>
        </div>
        <div class="col-md-3">
            <span class="text-muted small d-block">Tanggal Kirim</span>
            <span class="fw-bold"><?= htmlspecialchars($formulir['TglFormulir']) ?></span>
        </div>
        <div class="col-md-3">
            <span class="text-muted small d-block">Pengirim (UI)</span>
            <span class="fw-bold"><?= htmlspecialchars($formulir['NamaAkun']) ?></span>
        </div>
        <div class="col-md-2 text-end">
            <span class="text-muted small d-block">Status Sistem</span>
            <span class="badge bg-<?= ($formulir['StatusPengiriman'] == 'DITERIMA') ? 'success' : 'warning text-dark' ?>">
                <?= htmlspecialchars($formulir['StatusPengiriman']) ?>
            </span>
        </div>
    </div>
</div>

<!-- Form Konfirmasi Penerimaan -->
<form action="modules/pengadaan/proses_penerimaan.php?action=terima_barang" method="POST">
    <input type="hidden" name="no_pengiriman" value="<?= htmlspecialchars($formulir['NoFormulir']) ?>">

    <div class="card shadow mb-4 border-0">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Barang Masuk & Cek Fisik (QC)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted">
                        <tr>
                            <th class="ps-4 py-3">NO REF (BARCODE/STIKER)</th>
                            <th class="text-center">STIKER QC</th>
                            <th class="text-center pe-4">CACAT FISIK</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($items)): foreach ($items as $item): ?>
                            <tr>
                                <td class="fw-bold ps-4 text-dark">
                                    <input type="hidden" name="item_ref[]" value="<?= htmlspecialchars($item['NoRef']) ?>">
                                    <i class="bi bi-tag me-2 text-primary"></i><?= htmlspecialchars($item['NoRef']) ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($formulir['StatusPengiriman'] == 'DIKIRIM'): ?>
                                        <select class="form-select form-select-sm w-50 mx-auto" name="qc[<?= htmlspecialchars($item['NoRef']) ?>]">
                                            <option value="ADA" <?= ($item['StikerQC'] == 'ADA') ? 'selected' : '' ?>>ADA</option>
                                            <option value="TIDAK" <?= ($item['StikerQC'] == 'TIDAK') ? 'selected' : '' ?>>TIDAK</option>
                                        </select>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($item['StikerQC']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center pe-4">
                                    <?php if ($formulir['StatusPengiriman'] == 'DIKIRIM'): ?>
                                        <select class="form-select form-select-sm w-50 mx-auto" name="cacat[<?= htmlspecialchars($item['NoRef']) ?>]">
                                            <option value="TIDAK" <?= ($item['CacatFisik'] == 'TIDAK') ? 'selected' : '' ?>>TIDAK</option>
                                            <option value="YA" <?= ($item['CacatFisik'] == 'YA') ? 'selected' : '' ?>>YA</option>
                                        </select>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($item['CacatFisik']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="3" class="text-center py-4 text-muted">Tidak ada item dalam pengiriman ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tombol Aksi Final Terima Barang -->
        <?php if ($formulir['StatusPengiriman'] == 'DIKIRIM'): ?>
        <div class="card-footer bg-white p-3 text-end">
            <button type="submit" class="btn btn-success btn-lg fw-bold px-5" onclick="return confirm('Apakah Anda yakin barang sudah diterima lengkap dan lolos inspeksi? Kepemilikan aset akan berpindah ke unit Anda.')">
                <i class="bi bi-check-circle-fill me-2"></i>KONFIRMASI TERIMA & SIMPAN KE STOK UNIT
            </button>
        </div>
        <?php endif; ?>
    </div>
</form>