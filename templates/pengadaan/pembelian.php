<?php 
$no_formulir_valid = $formulir['NoFormulir'] ?? ($_GET['no_form'] ?? '');

// PERBAIKAN: Hapus pemanggilan array $data[]
$formulir = $formulir ?? false;
$items = $items ?? [];
$no_formulir = $_GET['no_form'] ?? '';

// Proteksi: Jika data tidak ditemukan di database, tampilkan pesan error rapi, bukan crash PHP
if (!$formulir): 
?>
    <div class="alert alert-danger shadow-sm mt-4">
        <h4 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Data Tidak Ditemukan!</h4>
        <p>Formulir dengan nomor <strong><?= htmlspecialchars($no_formulir) ?></strong> tidak ditemukan di database.</p>
        <hr>
        <p class="mb-0 small">Kemungkinan penyebab: Nomor formulir melebihi batas <strong>17 karakter</strong> (Sesuai database) sehingga terpotong oleh sistem. Silakan kembali dan buat formulir baru dengan format yang lebih pendek.</p>
    </div>
    <a href="index.php?page=pengadaan&menu=barang&sub=pembelian&view=daftar" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar</a>
<?php 
    return; // Hentikan eksekusi kode HTML di bawahnya
endif; 
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-cart me-2"></i>Keranjang Pembelian</h1>
    <a href="index.php?page=pengadaan&menu=barang&sub=pembelian" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<!-- Informasi Header -->
<div class="alert alert-primary shadow-sm mb-4">
    <strong>No. Form:</strong> <?= htmlspecialchars($formulir['NoFormulir']) ?> &bull; 
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
                <form action="modules/pengadaan/proses_pembelian.php?action=add_item" method="POST">
                    <input type="hidden" name="metode" value="manual">
                    <input type="hidden" name="no_formulir" value="<?= htmlspecialchars($formulir['NoFormulir']) ?>">
                    
                    <div class="row align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted">Nomor Ref (NoRef)</label>
                            <input type="text" class="form-control" name="no_ref" placeholder="Contoh: S41-001" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted">Harga Beli (Rp)</label>
                            <input type="number" class="form-control" name="harga" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold small text-muted">Stiker QC</label>
                            <select class="form-select" name="stiker_qc" required>
                                <option value="ADA">ADA</option>
                                <option value="TIDAK">TIDAK</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold small text-muted">Cacat Fisik</label>
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
                <form action="modules/pengadaan/proses_pembelian.php?action=add_item" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="metode" value="excel">
                    <input type="hidden" name="no_formulir" value="<?= htmlspecialchars($formulir['NoFormulir']) ?>">
                    <div class="row align-items-end">
                        <div class="col-md-9">
                            <label class="form-label fw-bold small text-muted">Pilih File Data (.csv)</label>
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

<!-- Tabel Daftar Barang Terinput -->
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
                        <th>HARGA BELI</th>
                        <th class="text-end pe-4">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (!empty($items)):
                        foreach ($items as $item): 
                    ?>
                        <tr>
                            <td class="ps-4 fw-bold"><?= htmlspecialchars($item['NoRef']) ?></td>
                            <td><?= htmlspecialchars($item['StikerQC']) ?></td>
                            <td><?= htmlspecialchars($item['CacatFisik']) ?></td>
                            <td>Rp <?= number_format($item['HargaBeli'], 0, ',', '.') ?></td>
                            <td class="text-end pe-4">
                                <form action="modules/pengadaan/proses_pembelian.php?action=delete_item" method="POST" class="d-inline" onsubmit="return confirm('Hapus item ini dari keranjang?')">
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
                        <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada barang di keranjang.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Tombol Reset & Eksekusi di Bagian Paling Bawah -->
    <?php if (($formulir['StatusData'] ?? 'TIDAK') === 'TIDAK' && !empty($items)): ?>
    <div class="card-footer bg-white p-3 d-flex gap-2 border-top">
        <form action="modules/pengadaan/proses_pembelian.php?action=reset_list" method="POST" class="w-50" onsubmit="return confirm('Yakin ingin mereset dan menghapus seluruh daftar alat?')">
            <input type="hidden" name="no_formulir" value="<?= htmlspecialchars($no_formulir_valid) ?>">
            <button type="submit" class="btn btn-outline-danger btn-lg w-100 fw-bold">
                <i class="bi bi-arrow-counterclockwise me-2"></i>RESET LIST
            </button>
        </form>
        
        <form action="modules/pengadaan/proses_pembelian.php?action=execute_form" method="POST" class="w-50">
            <input type="hidden" name="no_formulir" value="<?= htmlspecialchars($no_formulir_valid) ?>">
            <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold text-dark" onclick="return confirm('Data siap dieksekusi?')">
                <i class="bi bi-send-check me-2"></i>FINALISASI & EKSEKUSI
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>