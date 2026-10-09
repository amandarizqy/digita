<?php 
$no_formulir_valid = $_GET['no_form'] ?? ($formulir['NoFormulir'] ?? '');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-box-seam me-2"></i>Keranjang Pengiriman: <?= htmlspecialchars($no_formulir_valid) ?></h1>
    <a href="index.php?page=pengadaan&menu=pengiriman&view=daftar" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Informasi Header -->
<div class="alert alert-info shadow-sm mb-4">
    <strong>No. Form:</strong> <?= htmlspecialchars($no_formulir_valid) ?> &bull; 
    <strong>Tanggal:</strong> <?= htmlspecialchars($formulir['TglFormulir'] ?? '-') ?> &bull;
    <strong>Tujuan:</strong> <?= htmlspecialchars($formulir['NamaAP'] ?? '-') ?><?= !empty($formulir['NamaUP']) && $formulir['NamaUP'] !== '-' ? ' - ' . htmlspecialchars($formulir['NamaUP']) : '' ?>
</div>

<!-- Form Input (Hanya tampil jika status masih Draft/TIDAK) -->
<?php if (($formulir['StatusData'] ?? 'TIDAK') === 'TIDAK'): ?>
<div class="card shadow mb-4">
    <div class="card-header bg-white">
        <ul class="nav nav-tabs card-header-tabs" id="pengirimanTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold" data-bs-toggle="tab" data-bs-target="#manual" type="button" role="tab"><i class="bi bi-upc-scan me-1"></i>Scan Manual</button>
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
                <form action="modules/pengadaan/proses_pengiriman.php?action=add_item" method="POST">
                    <input type="hidden" name="metode" value="manual">
                    <input type="hidden" name="no_formulir" value="<?= htmlspecialchars($no_formulir_valid) ?>">
                    
                    <div class="row align-items-end">
                        <div class="col-md-5">
                            <label class="form-label fw-bold">Nomor Ref Perangkat (Barcode)</label>
                            <input type="text" class="form-control" name="no_ref" placeholder="Scan Barcode S41..." required autofocus>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Kondisi Stiker QC</label>
                            <select class="form-select" name="stiker_qc" required>
                                <option value="ADA">ADA</option>
                                <option value="TIDAK">TIDAK</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Cacat Fisik</label>
                            <select class="form-select" name="cacat_fisik" required>
                                <option value="TIDAK">TIDAK</option>
                                <option value="ADA">ADA</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right me-1"></i>Masuk Truk</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- TAB EXCEL / CSV -->
            <div class="tab-pane fade" id="excel" role="tabpanel">
                <form action="modules/pengadaan/proses_pengiriman.php?action=add_item" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="metode" value="excel">
                    <input type="hidden" name="no_formulir" value="<?= htmlspecialchars($no_formulir_valid) ?>">
                    <div class="row align-items-end">
                        <div class="col-md-9">
                            <label class="form-label fw-bold">Pilih File CSV Perangkat (Pemisah Titik Koma ';')</label>
                            <input class="form-control" type="file" name="file_excel" accept=".csv" required>
                            <small class="form-text">Format header: <i>NoRef;StikerQC;CacatFisik</i></small>
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

<!-- Tabel Daftar Muatan Pengiriman -->
<div class="card shadow mb-4 border-0">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Muatan Pengiriman</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">NO URUT</th>
                        <th>NO REF PERANGKAT</th>
                        <th>STIKER QC</th>
                        <th>CACAT FISIK</th>
                        <th class="text-end pe-4">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (!empty($items)):
                        $no = 1;
                        foreach ($items as $item): 
                    ?>
                        <tr>
                            <td class="ps-4"><?= $no++ ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($item['NoRef']) ?></td>
                            <td><?= htmlspecialchars($item['StikerQC']) ?></td>
                            <td>
                                <?php if($item['CacatFisik'] == 'ADA'): ?>
                                    <span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle"></i> ADA</span>
                                <?php else: ?>
                                    TIDAK
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <form action="modules/pengadaan/proses_pengiriman.php?action=delete_item" method="POST" class="d-inline" onsubmit="return confirm('Hapus item ini dari keranjang pengiriman?')">
                                    <input type="hidden" name="no_formulir" value="<?= htmlspecialchars($no_formulir_valid) ?>">
                                    <input type="hidden" name="no_ref" value="<?= htmlspecialchars($item['NoRef']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Hapus Item">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php 
                        endforeach; 
                    else: 
                    ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada barang di truk pengiriman.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Tombol Reset & Eksekusi di Bagian Paling Bawah -->
    <?php if (($formulir['StatusData'] ?? 'TIDAK') === 'TIDAK' && !empty($items)): ?>
    <div class="card-footer bg-white p-3 d-flex gap-2 border-top">
        <form action="modules/pengadaan/proses_pengiriman.php?action=reset_list" method="POST" class="w-50" onsubmit="return confirm('Yakin ingin mereset dan menghapus seluruh daftar muatan pengiriman ini?')">
            <input type="hidden" name="no_formulir" value="<?= htmlspecialchars($no_formulir_valid) ?>">
            <button type="submit" class="btn btn-outline-danger btn-lg w-100 fw-bold">
                <i class="bi bi-arrow-counterclockwise me-2"></i>RESET LIST
            </button>
        </form>
        
        <form action="modules/pengadaan/proses_pengiriman.php?action=execute_form" method="POST" class="w-50">
            <input type="hidden" name="no_formulir" value="<?= htmlspecialchars($no_formulir_valid) ?>">
            <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold text-dark" onclick="return confirm('Truk siap berangkat? Data tidak dapat diubah setelah dieksekusi.')">
                <i class="bi bi-send-check me-2"></i>EKSEKUSI PENGIRIMAN
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>