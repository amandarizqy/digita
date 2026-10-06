<div class="card shadow mb-4 border-0">
    <div class="card-body p-4">
        
        <ul class="nav nav-pills mb-4" id="pills-tab-pulsa" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold px-4" data-bs-toggle="pill" data-bs-target="#manual_pulsa" type="button"><i class="bi bi-keyboard me-1"></i>Input Manual</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link bg-success bg-opacity-10 text-success fw-bold ms-2 px-4 border border-success border-opacity-25" data-bs-toggle="pill" data-bs-target="#excel_pulsa" type="button"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Upload CSV</button>
            </li>
        </ul>
        
        <div class="tab-content" id="pills-tabContentPulsa">
            <!-- Form Manual -->
            <div class="tab-pane fade show active" id="manual_pulsa" role="tabpanel">
                <form action="modules/pengadaan/proses_kartu.php?action=pulsa" method="POST">
                    <input type="hidden" name="metode" value="manual">
                    <div class="row mb-3">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-muted small">SIM ID (Tujuan)</label>
                            <input type="text" class="form-control" name="sim_id" placeholder="Masukkan ICCID Kartu" required autofocus>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-muted small">Nominal Pengisian (Rp)</label>
                            <input type="number" class="form-control" name="nominal" placeholder="Contoh: 100000" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-muted small">Tanggal Isi Pulsa</label>
                            <input type="date" class="form-control" name="tgl_isi" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <hr class="text-secondary mb-4">
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-wallet2 me-2"></i>Simpan Saldo</button>
                    </div>
                </form>
            </div>

            <!-- Form Upload Excel -->
            <div class="tab-pane fade" id="excel_pulsa" role="tabpanel">
                <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                    <div>
                        Catat riwayat pengisian pulsa secara massal via CSV.<br>
                        Pastikan format header file CSV Anda adalah: <strong class="text-dark">SimId;Nominal;TglIsi</strong>
                    </div>
                </div>
                <form action="modules/pengadaan/proses_kartu.php?action=pulsa" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="metode" value="excel">
                    <div class="row align-items-end mb-4">
                        <div class="col-md-8">
                            <label class="form-label fw-bold text-muted small">Upload File CSV Isi Pulsa (Delimiter ';')</label>
                            <input type="file" class="form-control form-control-lg" name="file_excel" accept=".csv" required>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-success btn-lg w-100"><i class="bi bi-cloud-upload me-2"></i>Proses Massal</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>