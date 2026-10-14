<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-bottom py-3">
        <h5 class="card-title m-0 fw-bold"><i class="bi bi-shield-check text-primary me-2"></i> Form Pengujian Alat & SIM (S41)</h5>
    </div>
    <div class="card-body p-4">
        <!-- Form action mengarah ke controller untuk memproses POST -->
        <form action="../../modules/pemasangan/proses_pengujian.php" method="POST">
            
            <!-- BAGIAN 1: IDENTIFIKASI & PAIRING -->
            <h6 class="fw-bold text-secondary mb-3 pb-2 border-bottom">1. Identifikasi & Pairing</h6>
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Nomor Referensi Alat (NoRef) <span class="text-danger">*</span></label>
                    <select name="noref" class="form-select" required>
                        <option value="">-- Pilih Alat S41 --</option>
                        <?php foreach($list_alat as $alat): ?>
                            <option value="<?= $alat['NoRef'] ?>"><?= $alat['NoRef'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Alat yang tersedia di gudang UP Anda.</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Nomor SIM (SimId) <span class="text-danger">*</span></label>
                    <select name="simid" class="form-select" required>
                        <option value="">-- Pilih SIM Card --</option>
                        <?php foreach($list_sim as $sim): ?>
                            <option value="<?= $sim['SimId'] ?>"><?= $sim['SimId'] ?> (<?= $sim['NomorAkun'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">IMEI Modem S41 <span class="text-danger">*</span></label>
                    <input type="text" name="imei_modem" class="form-control" placeholder="Contoh: 867749010776270" required maxlength="30">
                </div>
            </div>

            <!-- BAGIAN 2: INSPEKSI FISIK & VISUAL -->
            <h6 class="fw-bold text-secondary mb-3 pb-2 border-bottom">2. Inspeksi Fisik & Visual</h6>
            <div class="row mb-4 bg-light p-3 rounded">
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-semibold">Cacat Fisik Box/Kabel?</label>
                    <select name="cacat_fisik" class="form-select" required>
                        <option value="TIDAK">TIDAK ADA</option>
                        <option value="ADA">ADA CACAT</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-semibold">Stiker QC Pabrik?</label>
                    <select name="stiker_qc" class="form-select" required>
                        <option value="ADA">ADA</option>
                        <option value="TIDAK">TIDAK ADA</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-semibold">Tegangan Phasa (AC)</label>
                    <select name="tegangan_phasa" class="form-select" required>
                        <option value="ADA">MENGALIR (ADA)</option>
                        <option value="TIDAK">TIDAK ADA</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-semibold">Lampu Indikator Modul</label>
                    <select name="lampu_indikator" class="form-select" required>
                        <option value="AKTIF">MENYALA/AKTIF</option>
                        <option value="TIDAK">MATI</option>
                    </select>
                </div>
            </div>

            <!-- BAGIAN 3: PENGUJIAN FUNGSIONALITAS SMS -->
            <h6 class="fw-bold text-secondary mb-3 pb-2 border-bottom">3. Pengujian Fungsionalitas SMS (Relay & Balasan)</h6>
            
            <!-- Manajemen Admin -->
            <div class="row mb-3">
                <div class="col-12"><label class="form-label fw-bold text-primary">A. Manajemen Admin</label></div>
                <div class="col-md-3"><label class="form-label small">Perintah SETADMIN</label><select name="set_admin" class="form-select form-select-sm"><option value="BERHASIL">BERHASIL</option><option value="GAGAL">GAGAL</option></select></div>
                <div class="col-md-3"><label class="form-label small">Perintah LISTADMIN</label><select name="list_admin" class="form-select form-select-sm"><option value="BERHASIL">BERHASIL</option><option value="GAGAL">GAGAL</option></select></div>
                <div class="col-md-3"><label class="form-label small">Perintah DELADMIN</label><select name="del_admin" class="form-select form-select-sm"><option value="BERHASIL">BERHASIL</option><option value="GAGAL">GAGAL</option></select></div>
                <div class="col-md-3"><label class="form-label small">Perintah RESETADMIN</label><select name="reset_admin" class="form-select form-select-sm"><option value="BERHASIL">BERHASIL</option><option value="GAGAL">GAGAL</option></select></div>
            </div>

            <!-- Uji PLNSTATUS Awal -->
            <div class="row mb-3">
                <div class="col-12"><label class="form-label fw-bold text-primary">B. Uji Status Awal (PLNSTATUS)</label></div>
                <div class="col-md-4"><label class="form-label small">Durasi Balasan (Detik)</label><input type="number" name="detik_status_awal" class="form-control form-control-sm" placeholder="Misal: 4" required></div>
                <div class="col-md-4"><label class="form-label small">Apakah Ada Balasan?</label><select name="jawaban_status_awal" class="form-select form-select-sm"><option value="ADA">ADA</option><option value="TIDAK">TIDAK ADA</option></select></div>
                <div class="col-md-4"><label class="form-label small">Isi Teks Status</label><select name="isi_status_awal" class="form-select form-select-sm"><option value="SESUAI">SESUAI</option><option value="TIDAK">TIDAK SESUAI</option></select></div>
            </div>

            <!-- Uji PLNOFF (Putus) -->
            <div class="row mb-3 border-start border-danger border-4 ps-2">
                <div class="col-12"><label class="form-label fw-bold text-danger">C. Uji Pemutusan (PLNOFF)</label></div>
                <div class="col-md-3"><label class="form-label small">Durasi Putus Relay (Detik)</label><input type="number" name="detik_putus" class="form-control form-control-sm" placeholder="Misal: 2"></div>
                <div class="col-md-3"><label class="form-label small">Lampu Indikator Beban</label><select name="lampu_diputus" class="form-select form-select-sm"><option value="PADAM">PADAM</option><option value="MENYALA">MENYALA (GAGAL)</option></select></div>
                <div class="col-md-3"><label class="form-label small">Apakah Ada Balasan?</label><select name="jawaban_putus" class="form-select form-select-sm"><option value="ADA">ADA</option><option value="TIDAK">TIDAK ADA</option></select></div>
                <div class="col-md-3"><label class="form-label small">Durasi Terima Balasan (Detik)</label><input type="number" name="detik_jawaban_putus" class="form-control form-control-sm" placeholder="Misal: 5"></div>
            </div>

            <!-- Uji PLNON (Nyala) -->
            <div class="row mb-4 border-start border-success border-4 ps-2">
                <div class="col-12"><label class="form-label fw-bold text-success">D. Uji Penyambungan (PLNON)</label></div>
                <div class="col-md-3"><label class="form-label small">Durasi Nyala Relay (Detik)</label><input type="number" name="detik_nyala" class="form-control form-control-sm" placeholder="Misal: 2"></div>
                <div class="col-md-3"><label class="form-label small">Lampu Indikator Beban</label><select name="lampu_menyala" class="form-select form-select-sm"><option value="MENYALA">MENYALA</option><option value="PADAM">PADAM (GAGAL)</option></select></div>
                <div class="col-md-3"><label class="form-label small">Apakah Ada Balasan?</label><select name="jawaban_nyala" class="form-select form-select-sm"><option value="ADA">ADA</option><option value="TIDAK">TIDAK ADA</option></select></div>
                <div class="col-md-3"><label class="form-label small">Durasi Terima Balasan (Detik)</label><input type="number" name="detik_jawaban_nyala" class="form-control form-control-sm" placeholder="Misal: 5"></div>
            </div>

            <!-- TOMBOL AKSI -->
            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <a href="index.php?page=pemasangan&menu=pra_eksekusi&sub=pengujian&view=daftar" class="btn btn-light border text-secondary px-4 fw-semibold">Batal</a>
                <button type="submit" class="btn btn-primary px-4 fw-semibold"><i class="bi bi-save me-1"></i> Simpan Hasil Uji</button>
            </div>
        </form>
    </div>
</div>