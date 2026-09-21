<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-truck me-2"></i>Pengiriman Perangkat S41</h1>
    <a href="index.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<?php if (isset($_GET['status']) && $_GET['status'] == 'sukses'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>Data pengiriman berhasil disimpan dan kepemilikan alat telah diperbarui!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header bg-white">
        <!-- Navigasi Tabs: Konsisten dengan Pembelian -->
        <ul class="nav nav-tabs card-header-tabs" id="pengirimanTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual" type="button" role="tab"><i class="bi bi-keyboard me-1"></i>Input Manual</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-success" id="excel-tab" data-bs-toggle="tab" data-bs-target="#excel" type="button" role="tab"><i class="bi bi-file-earmark-excel me-1"></i>Upload Data (CSV)</button>
            </li>
        </ul>
    </div>
    
    <div class="card-body pt-4">
        <div class="tab-content" id="pengirimanTabsContent">
            
            <!-- TAB 1: INPUT MANUAL -->
            <div class="tab-pane fade show active" id="manual" role="tabpanel">
                <form action="proses_pengiriman.php" method="POST">
                    <input type="hidden" name="metode" value="manual">
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tanggal Pengiriman</label>
                            <input type="date" name="tgl_kirim" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Unit Tujuan (UP/ULP)</label>
                            <select name="tujuan_up" class="form-select select2-unit" required>
                                <option value="" selected disabled>-- Pilih Unit Tujuan --</option>
                                <?php foreach($unit_list as $unit): ?>
                                    <option value="<?= htmlspecialchars($unit['UnitUp']) ?>">
                                        <?= htmlspecialchars($unit['NamaUp']) ?> (<?= htmlspecialchars($unit['NamaAp']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Nomor Referensi Alat (NoRef)</label>
                            <input type="text" name="no_ref" class="form-control" placeholder="Contoh: S41-001" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Stiker QC</label>
                            <select name="stiker_qc" class="form-select">
                                <option value="ADA">ADA</option>
                                <option value="TIDAK">TIDAK</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Cacat Fisik</label>
                            <select name="cacat_fisik" class="form-select">
                                <option value="TIDAK">TIDAK</option>
                                <option value="YA">YA</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Proses Pengiriman</button>
                </form>
            </div>

            <!-- TAB 2: UPLOAD CSV -->
            <div class="tab-pane fade" id="excel" role="tabpanel">
                <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                    <div>
                        Gunakan fitur ini untuk mengirimkan alat dalam jumlah banyak. Format file sama dengan form pembelian, <strong>kolom harga otomatis diabaikan</strong> oleh sistem.
                        <br>
                        <a href="../../assets/templates/Template_Pembelian_S41.csv" class="alert-link fw-bold text-decoration-underline"><i class="bi bi-download me-1"></i>Unduh Template CSV</a>
                    </div>
                </div>

                <form action="proses_pengiriman.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="metode" value="excel">
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tanggal Pengiriman</label>
                            <input type="date" name="tgl_kirim_massal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Unit Tujuan (UP/ULP) Untuk Semua Alat</label>
                            <select name="tujuan_up_massal" class="form-select select2-unit" required>
                                <option value="" selected disabled>-- Pilih Unit Tujuan --</option>
                                <?php foreach($unit_list as $unit): ?>
                                    <option value="<?= htmlspecialchars($unit['UnitUp']) ?>">
                                        <?= htmlspecialchars($unit['NamaUp']) ?> (<?= htmlspecialchars($unit['NamaAp']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Pilih File Data (.csv)</label>
                        <input class="form-control form-control-lg" type="file" name="file_excel" accept=".csv" required>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-cloud-upload me-1"></i>Proses & Kirim Massal</button>
                </form>
            </div>

        </div>
    </div>
</div>

<!-- Sertakan jQuery (Syarat Select2) dan Library Select2 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Script Inisialisasi -->
<script>
$(document).ready(function() {
    $('.select2-unit').select2({
        placeholder: "-- Ketik untuk mencari Unit Tujuan --",
        allowClear: true,
        width: '100%',
        theme: 'classic' // Tema yang cukup menyatu dengan Bootstrap
    });
});
</script>