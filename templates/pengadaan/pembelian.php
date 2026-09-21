<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-cart-plus me-2"></i>Formulir Pembelian</h1>
    <a href="index.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<div class="card shadow mb-4">
    <div class="card-header bg-white">
        <!-- Navigasi Tabs -->
        <ul class="nav nav-tabs card-header-tabs" id="pembelianTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual" type="button" role="tab"><i class="bi bi-keyboard me-1"></i>Input Manual</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-success" id="excel-tab" data-bs-toggle="tab" data-bs-target="#excel" type="button" role="tab"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Upload Data (CSV)</button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content" id="pembelianTabsContent">
            
            <!-- TAB 1: INPUT MANUAL -->
            <div class="tab-pane fade show active" id="manual" role="tabpanel">
                <form action="proses_pembelian.php" method="POST">
                    <input type="hidden" name="metode" value="manual">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tanggal Beli</label>
                            <input type="date" class="form-control" name="tgl_beli" required value="<?= date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nomor Referensi Alat (NoRef)</label>
                            <input type="text" class="form-control" name="no_ref" placeholder="Contoh: S41-001" required>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Harga Beli (Rp)</label>
                            <input type="number" class="form-control" name="harga" placeholder="Contoh: 1500000" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Stiker QC</label>
                            <select class="form-select" name="stiker_qc" required>
                                <option value="ADA">ADA</option>
                                <option value="TIDAK">TIDAK</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Cacat Fisik</label>
                            <select class="form-select" name="cacat_fisik" required>
                                <option value="TIDAK">TIDAK</option>
                                <option value="YA">YA</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Data</button>
                </form>
            </div>

            <!-- TAB 2: UPLOAD EXCEL (CSV) -->
            <div class="tab-pane fade" id="excel" role="tabpanel">
                <div class="alert alert-info d-flex align-items-center" role="alert">
                    <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                    <div>
                        Gunakan fitur ini untuk memasukkan data barang dalam jumlah banyak sekaligus. 
                        Pastikan format file sesuai dengan template standar. <br>
                        <a href="../../assets/templates/Template_Pembelian_S41.csv" class="alert-link fw-bold text-decoration-underline"><i class="bi bi-download me-1"></i>Unduh Template CSV</a>
                    </div>
                </div>
                
                <form action="proses_pembelian.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="metode" value="excel">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tanggal Beli (Untuk Semua Barang)</label>
                            <input type="date" class="form-control" name="tgl_beli_massal" required value="<?= date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Pilih File Data (.csv)</label>
                        <input class="form-control form-control-lg" type="file" name="file_excel" accept=".csv" required>
                        <div class="form-text">Pastikan file Excel sudah di-Save As ke format "CSV (Comma delimited)".</div>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-cloud-upload me-1"></i>Proses & Simpan Massal</button>
                </form>
            </div>

        </div>
    </div>
</div>