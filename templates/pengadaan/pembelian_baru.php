<div class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-file-earmark-plus me-2"></i>Buat Draft Formulir</h1>
        <a href="pembelian.php?view=daftar" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    </div>

    <div class="card shadow border-0">
        <div class="card-body">
            <form action="../../modules/pengadaan/proses_pembelian.php?action=create_draft" method="POST">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold text-dark mb-3">Informasi Utama</h6>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Nomor Formulir</label>
                            <input type="text" class="form-control" name="no_formulir" placeholder="KODEUPIXXXYYYYMMDD-F.A" required maxlength="17">
                            <small class="text-secondary">Gunakan format standar PLN UID.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Tanggal Pembelian</label>
                            <input type="date" class="form-control" name="tgl_beli" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="fw-bold text-dark mb-3">Pemetaan Tujuan (Opsional)</h6>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Unit Induk (UPI)</label>
                            <select class="form-select" name="kode_upi">
                                <option value="">-- Pilih UPI --</option>
                                <option value="56">Unit Induk Distribusi Banten (56)</option>
                                <option value="54">Unit Induk Distribusi Jakarta Raya (54)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Unit Pelaksana (AP)</label>
                            <select class="form-select" name="kode_ap">
                                <option value="">-- Kosongkan jika belum ada --</option>
                                <option value="56610">UP3 Cikokol</option>
                                <option value="56100">UP3 Banten Utara</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Unit Layanan (UP)</label>
                            <select class="form-select" name="kode_up">
                                <option value="">-- Kosongkan jika belum ada --</option>
                                <option value="56610">ULP Cikokol</option>
                                <option value="56120">ULP Cilegon</option>
                            </select>
                        </div>
                    </div>
                </div>

                <hr class="text-secondary mb-4">
                
                <div class="d-flex justify-content-end">
                    <button type="reset" class="btn btn-outline-secondary me-2 px-4">Reset</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i>Simpan & Isi Keranjang Barang</button>
                </div>
            </form>
        </div>
    </div>
</div>