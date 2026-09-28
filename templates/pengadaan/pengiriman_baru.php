<div class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-file-earmark-plus me-2"></i>Buat Draft Pengiriman</h1>
        <a href="pengiriman.php?view=daftar" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    </div>

    <div class="card shadow border-0">
        <div class="card-body">
            <form action="../../modules/pengadaan/proses_pengiriman.php?action=create_draft" method="POST">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold text-dark mb-3">Informasi Utama</h6>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Nomor Formulir Pengiriman</label>
                            <input type="text" class="form-control" name="no_formulir" placeholder="Contoh: 5612000120260921-F.B" required maxlength="20">
                            <small class="text-secondary">Gunakan format standar F.B</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Tanggal Pengiriman</label>
                            <input type="date" class="form-control" name="tgl_form" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="fw-bold text-dark mb-3">Unit Tujuan Distribusi</h6>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Unit Pelaksana (AP)</label>
                            <select class="form-select" name="kode_ap_tujuan" required>
                                <option value="">-- Pilih Area Tujuan --</option>
                                <?php foreach ($list_ap as $ap): ?>
                                    <option value="<?= $ap['UnitAp'] ?>"><?= $ap['NamaUnit'] ?> (<?= $ap['UnitAp'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Unit Layanan (UP) - Opsional</label>
                            <select class="form-select" name="kode_up_tujuan">
                                <option value="">-- Hanya isi jika langsung ke ULP --</option>
                                <?php foreach ($list_up as $up): ?>
                                    <option value="<?= $up['UnitUp'] ?>"><?= $up['NamaUnit'] ?> (<?= $up['UnitUp'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <hr class="text-secondary mb-4">
                
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i>Simpan Draft & Mulai Pilih Barang</button>
                </div>
            </form>
        </div>
    </div>
</div>