<div class="card shadow mb-4 border-0">
    <div class="card-body p-4">
        
        <ul class="nav nav-pills mb-4" id="pills-tab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold px-4" data-bs-toggle="pill" data-bs-target="#manual" type="button"><i class="bi bi-keyboard me-1"></i>Input Manual</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link bg-success bg-opacity-10 text-success fw-bold ms-2 px-4 border border-success border-opacity-25" data-bs-toggle="pill" data-bs-target="#excel" type="button"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Upload CSV</button>
            </li>
        </ul>
        
        <div class="tab-content" id="pills-tabContent">
            <!-- Form Manual -->
            <div class="tab-pane fade show active" id="manual" role="tabpanel">
                <form action="modules/pengadaan/proses_kartu.php?action=aktivasi" method="POST">
                    <input type="hidden" name="metode" value="manual">
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted small">SIM ID (ICCID 16-20 Digit)</label>
                            <input type="text" class="form-control" name="sim_id" placeholder="Masukkan ICCID di belakang kartu" required autofocus>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted small">Nomor Akun (Mulai dari 62...)</label>
                            <input type="text" class="form-control" name="nomor_akun" placeholder="Contoh: 62812345678" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold text-muted small">Provider</label>
                            <select class="form-select" name="kode_provider" required>
                                <option value="">-- Pilih Provider --</option>
                                <?php foreach ($data['providers'] as $p): ?>
                                    <option value="<?= $p['KodeProvider'] ?>"><?= $p['NamaProvider'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold text-muted small">Produk</label>
                            <select class="form-select" name="kode_produk" required>
                                <option value="">-- Pilih Produk --</option>
                                <?php foreach ($data['products'] as $pr): ?>
                                    <option value="<?= $pr['KodeProduk'] ?>"><?= $pr['NamaProduk'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold text-muted small">Jenis Pembayaran</label>
                            <select class="form-select" name="jenis_produk" required>
                                <option value="PRABAYAR">Prabayar (Prepaid)</option>
                                <option value="PASKABAYAR">Paskabayar (Postpaid)</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold text-muted small">Tgl Aktivasi</label>
                            <input type="date" class="form-control" name="tgl_aktifasi" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <hr class="text-secondary mb-4">
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i>Simpan Aktivasi</button>
                    </div>
                </form>
            </div>

            <!-- Form Upload Excel -->
            <div class="tab-pane fade" id="excel" role="tabpanel">
                <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                    <div>
                        Gunakan fitur ini untuk mengaktivasi kartu perdana dalam jumlah besar. <br>
                        Pastikan format header file CSV Anda adalah: <strong class="text-dark">SimId;NomorAkun;KodeProvider;KodeProduk;JenisProduk;TglAktifasi</strong>
                    </div>
                </div>
                <form action="modules/pengadaan/proses_kartu.php?action=aktivasi" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="metode" value="excel">
                    <div class="row align-items-end mb-4">
                        <div class="col-md-8">
                            <label class="form-label fw-bold text-muted small">Upload File CSV Aktivasi (Delimiter ';')</label>
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