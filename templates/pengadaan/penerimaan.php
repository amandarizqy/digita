<?php 
$no_formulir_valid = $_GET['no_form'] ?? ($formulir['NoFormulir'] ?? '');
$status_kirim = $formulir['StatusPengiriman'] ?? 'DIKIRIM';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-box-arrow-in-down me-2"></i>Inspeksi & Penerimaan Inbound: <?= htmlspecialchars($no_formulir_valid) ?></h1>
    <a href="index.php?page=pengadaan&menu=penerimaan&view=daftar" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<!-- Informasi Header -->
<div class="card shadow-sm mb-4 border-0">
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <span class="text-muted small d-block">No. Pengiriman / Formulir</span>
                <strong class="text-primary"><?= htmlspecialchars($no_formulir_valid) ?></strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted small d-block">Tanggal Kirim</span>
                <strong><?= htmlspecialchars($formulir['TglFormulir'] ?? '-') ?></strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted small d-block">Pengirim (UI)</span>
                <strong><?= htmlspecialchars($formulir['NamaAkun'] ?? '-') ?></strong>
            </div>
            <div class="col-md-2 text-md-end">
                <span class="text-muted small d-block">Status Sistem</span>
                <?php if ($status_kirim === 'DITERIMA'): ?>
                    <span class="badge bg-primary px-3 py-2">Diterima</span>
                <?php else: ?>
                    <span class="badge bg-info text-dark px-3 py-2">Diperjalanan</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Form Penerimaan Barang -->
<form action="modules/pengadaan/proses_penerimaan.php?action=terima_barang" method="POST">
    <input type="hidden" name="no_pengiriman" value="<?= htmlspecialchars($no_formulir_valid) ?>">

    <div class="card shadow mb-4 border-0">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Barang Masuk & Cek Fisik (QC)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">NO REF (BARCODE/STIKER)</th>
                            <th>STIKER QC</th>
                            <th>CACAT FISIK</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($items)): foreach ($items as $item): ?>
                            <tr>
                                <td class="ps-4">
                                    <input type="hidden" name="item_ref[]" value="<?= htmlspecialchars($item['NoRef']) ?>">
                                    <i class="bi bi-tag me-1 text-muted"></i><strong><?= htmlspecialchars($item['NoRef']) ?></strong>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm w-auto" name="qc[<?= htmlspecialchars($item['NoRef']) ?>]" <?= ($status_kirim === 'DITERIMA') ? 'disabled' : '' ?>>
                                        <option value="ADA" <?= ($item['StikerQC'] === 'ADA') ? 'selected' : '' ?>>ADA</option>
                                        <option value="TIDAK" <?= ($item['StikerQC'] === 'TIDAK') ? 'selected' : '' ?>>TIDAK</option>
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm w-auto text-danger fw-bold" name="cacat[<?= htmlspecialchars($item['NoRef']) ?>]" <?= ($status_kirim === 'DITERIMA') ? 'disabled' : '' ?>>
                                        <option value="TIDAK" <?= ($item['CacatFisik'] === 'TIDAK') ? 'selected' : '' ?>>TIDAK</option>
                                        <option value="ADA" <?= ($item['CacatFisik'] === 'ADA') ? 'selected' : '' ?>>ADA</option>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="3" class="text-center py-4 text-muted">Tidak ada item barang dalam pengiriman ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($status_kirim !== 'DITERIMA' && !empty($items)): ?>
        <div class="card-footer bg-white p-3 text-end border-top">
            <button type="submit" class="btn btn-success px-4 fw-bold" onclick="return confirm('Proses penerimaan barang ini? Lokasi aset akan otomatis berpindah ke unit Anda.')">
                <i class="bi bi-check-circle-fill me-2"></i>PROSES TERIMA BARANG
            </button>
        </div>
        <?php endif; ?>
    </div>
</form>