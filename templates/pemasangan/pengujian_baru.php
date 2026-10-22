<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-bottom p-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h6 class="fw-bold mb-0 text-dark">PT PLN (PERSERO)</h6>
                <small class="text-muted d-block">UID BANTEN - UP3 CIKOKOL</small>
                <h5 class="fw-bold text-primary mt-2 mb-0">FORMULIR PENGUJIAN PEMASANGAN S41</h5>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                <span class="badge bg-light text-dark border p-2">
                    NOMOR FORMULIR: <span class="fw-bold text-primary">FM-2026-Z-<?= date('YmdHis') ?></span>
                </span>
            </div>
        </div>
    </div>

    <div class="card-body p-4">
        <form action="modules/pemasangan/proses_pengujian.php" method="POST">
            
            <!-- INFORMASI UMUM PENGUJIAN -->
            <div class="row bg-light p-3 rounded mb-4 border">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold small">Unit Layanan (UP) <span class="text-danger">*</span></label>
                    <input type="text" name="unit" class="form-control form-control-sm bg-white" value="<?= $_SESSION['UnitUp'] ?? '56610' ?>" readonly>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold small">Tanggal Uji <span class="text-danger">*</span></label>
                    <input type="date" name="tgl_uji" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold small">Nomor Kontak Admin / Penguji <span class="text-danger">*</span></label>
                    <input type="text" name="nomor_kontak" class="form-control form-control-sm" placeholder="+62 8xxx xxxx" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold small">Nomor Referensi Alat (NoRef) <span class="text-danger">*</span></label>
                    <select name="noref" class="form-select form-select-sm" required>
                        <option value="">-- Pilih NoRef Alat S41 --</option>
                        <?php foreach($list_alat as $alat): ?>
                            <option value="<?= $alat['NoRef'] ?>"><?= $alat['NoRef'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold small">Nomor SIM Card <span class="text-danger">*</span></label>
                    <select name="simid" class="form-select form-select-sm" required>
                        <option value="">-- Pilih SIM Card --</option>
                        <?php foreach($list_sim as $sim): ?>
                            <option value="<?= $sim['SimId'] ?>"><?= $sim['SimId'] ?> (<?= $sim['NomorAkun'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold small">IMEI Modem S41 <span class="text-danger">*</span></label>
                    <input type="text" name="imei_modem" class="form-control form-control-sm" placeholder="Contoh: 867749010776270" required maxlength="30">
                </div>
            </div>

            <!-- BAGIAN 1: KONDISI ADMIN -->
            <div class="border rounded p-3 mb-4">
                <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-person-gear me-2 text-primary"></i>KONDISI ADMIN</h6>
                
                <div class="row align-items-center mb-2">
                    <div class="col-md-3"><span class="fw-semibold small">SET ADMIN</span></div>
                    <div class="col-md-4">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="set_admin" id="set_admin_1" value="Berhasil" checked>
                            <label class="form-check-label small" for="set_admin_1">Berhasil</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="set_admin" id="set_admin_0" value="Gagal">
                            <label class="form-check-label small" for="set_admin_0">Gagal</label>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="no_admin_set" class="form-control form-control-sm" placeholder="Nomor Admin (+62 ...)">
                    </div>
                </div>

                <div class="row align-items-center mb-2">
                    <div class="col-md-3"><span class="fw-semibold small">DEL ADMIN</span></div>
                    <div class="col-md-9">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="del_admin" id="del_admin_1" value="Berhasil" checked>
                            <label class="form-check-label small" for="del_admin_1">Berhasil</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="del_admin" id="del_admin_0" value="Gagal">
                            <label class="form-check-label small" for="del_admin_0">Gagal</label>
                        </div>
                    </div>
                </div>

                <div class="row align-items-center mb-2">
                    <div class="col-md-3"><span class="fw-semibold small">RESET ADMIN</span></div>
                    <div class="col-md-9">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="reset_admin" id="reset_admin_1" value="Berhasil" checked>
                            <label class="form-check-label small" for="reset_admin_1">Berhasil</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="reset_admin" id="reset_admin_0" value="Gagal">
                            <label class="form-check-label small" for="reset_admin_0">Gagal</label>
                        </div>
                    </div>
                </div>

                <div class="row align-items-center">
                    <div class="col-md-3"><span class="fw-semibold small">LIST ADMIN</span></div>
                    <div class="col-md-9">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="list_admin" id="list_admin_1" value="Berhasil" checked>
                            <label class="form-check-label small" for="list_admin_1">Berhasil</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="list_admin" id="list_admin_0" value="Gagal">
                            <label class="form-check-label small" for="list_admin_0">Gagal</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BAGIAN 2: KONDISI AWAL -->
            <div class="border rounded p-3 mb-4">
                <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-power me-2 text-info"></i>KONDISI AWAL</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Cek Phasa</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="cek_phasa" id="phasa_1" value="Ada Tegangan" checked>
                                <label class="form-check-label small" for="phasa_1">Ada Tegangan</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="cek_phasa" id="phasa_0" value="Tidak Ada Tegangan">
                                <label class="form-check-label small" for="phasa_0">Tidak Ada Tegangan</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Lampu Indikator</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="lampu_awal" id="lampu_awal_1" value="Menyala" checked>
                                <label class="form-check-label small" for="lampu_awal_1">Menyala</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="lampu_awal" id="lampu_awal_0" value="Tidak Menyala">
                                <label class="form-check-label small" for="lampu_awal_0">Tidak Menyala</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Respon PLNSTATUS (Detik)</label>
                        <input type="number" name="detik_status_awal" class="form-control form-control-sm" placeholder="Contoh: 3" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Dijawab SMS?</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="jawab_status_awal" id="jawab_awal_1" value="Ya" checked>
                                <label class="form-check-label small" for="jawab_awal_1">Ya</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="jawab_status_awal" id="jawab_awal_0" value="Tidak">
                                <label class="form-check-label small" for="jawab_awal_0">Tidak</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Isi Status</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="isi_status_awal" id="isi_awal_1" value="Sesuai" checked>
                                <label class="form-check-label small" for="isi_awal_1">Sesuai</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="isi_status_awal" id="isi_awal_0" value="Tidak Sesuai">
                                <label class="form-check-label small" for="isi_awal_0">Tidak Sesuai</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BAGIAN 3: KONDISI DIPUTUS SEMENTARA (PLNOFF) -->
            <div class="border border-danger-subtle bg-danger-subtle bg-opacity-10 rounded p-3 mb-4">
                <h6 class="fw-bold text-danger border-bottom border-danger-subtle pb-2 mb-3"><i class="bi bi-toggle-off me-2"></i>KONDISI DIPUTUS SEMENTARA</h6>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Respon PLNOFF (Detik)</label>
                        <input type="number" name="detik_plnoff" class="form-control form-control-sm" placeholder="Contoh: 2" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Dijawab SMS?</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="jawab_plnoff" id="jawab_off_1" value="Ya" checked>
                                <label class="form-check-label small" for="jawab_off_1">Ya</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="jawab_plnoff" id="jawab_off_0" value="Tidak">
                                <label class="form-check-label small" for="jawab_off_0">Tidak</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Durasi Jawab SMS (Detik)</label>
                        <input type="number" name="durasi_jawab_off" class="form-control form-control-sm" placeholder="Contoh: 4">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Lampu Beban</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="lampu_off" id="lampu_off_0" value="Tidak Menyala" checked>
                                <label class="form-check-label small" for="lampu_off_0">Tidak Menyala</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="lampu_off" id="lampu_off_1" value="Menyala">
                                <label class="form-check-label small" for="lampu_off_1">Menyala</label>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 border-top pt-2 mt-2"><span class="fw-semibold small text-muted">Cek PLNSTATUS (Pasca Putus):</span></div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Respon PLNSTATUS (Detik)</label>
                        <input type="number" name="detik_status_off" class="form-control form-control-sm" placeholder="Contoh: 3">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Dijawab SMS?</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="jawab_status_off" id="jawab_soff_1" value="Ya" checked>
                                <label class="form-check-label small" for="jawab_soff_1">Ya</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="jawab_status_off" id="jawab_soff_0" value="Tidak">
                                <label class="form-check-label small" for="jawab_soff_0">Tidak</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Isi Status</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="isi_status_off" id="isi_soff_1" value="Sesuai" checked>
                                <label class="form-check-label small" for="isi_soff_1">Sesuai</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="isi_status_off" id="isi_soff_0" value="Tidak Sesuai">
                                <label class="form-check-label small" for="isi_soff_0">Tidak Sesuai</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BAGIAN 4: KONDISI DINYALAKAN KEMBALI (PLNON) -->
            <div class="border border-success-subtle bg-success-subtle bg-opacity-10 rounded p-3 mb-4">
                <h6 class="fw-bold text-success border-bottom border-success-subtle pb-2 mb-3"><i class="bi bi-toggle-on me-2"></i>KONDISI DINYALAKAN KEMBALI</h6>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Respon PLNON (Detik)</label>
                        <input type="number" name="detik_plnon" class="form-control form-control-sm" placeholder="Contoh: 2" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Dijawab SMS?</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="jawab_plnon" id="jawab_on_1" value="Ya" checked>
                                <label class="form-check-label small" for="jawab_on_1">Ya</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="jawab_plnon" id="jawab_on_0" value="Tidak">
                                <label class="form-check-label small" for="jawab_on_0">Tidak</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Durasi Jawab SMS (Detik)</label>
                        <input type="number" name="durasi_jawab_on" class="form-control form-control-sm" placeholder="Contoh: 4">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Lampu Beban</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="lampu_on" id="lampu_on_1" value="Menyala" checked>
                                <label class="form-check-label small" for="lampu_on_1">Menyala</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="lampu_on" id="lampu_on_0" value="Tidak Menyala">
                                <label class="form-check-label small" for="lampu_on_0">Tidak Menyala</label>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 border-top pt-2 mt-2"><span class="fw-semibold small text-muted">Cek PLNSTATUS (Pasca Nyala):</span></div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Respon PLNSTATUS (Detik)</label>
                        <input type="number" name="detik_status_on" class="form-control form-control-sm" placeholder="Contoh: 3">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Dijawab SMS?</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="jawab_status_on" id="jawab_son_1" value="Ya" checked>
                                <label class="form-check-label small" for="jawab_son_1">Ya</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="jawab_status_on" id="jawab_son_0" value="Tidak">
                                <label class="form-check-label small" for="jawab_son_0">Tidak</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Isi Status</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="isi_status_on" id="isi_son_1" value="Sesuai" checked>
                                <label class="form-check-label small" for="isi_son_1">Sesuai</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="isi_status_on" id="isi_son_0" value="Tidak Sesuai">
                                <label class="form-check-label small" for="isi_son_0">Tidak Sesuai</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CATATAN PENGUJIAN -->
            <div class="mb-4">
                <label class="form-label small fw-semibold">Catatan Pengujian</label>
                <textarea name="catatan" class="form-control form-control-sm" rows="3" placeholder="Tambahkan catatan khusus mengenai kondisi fisik atau respon alat jika ada..."></textarea>
            </div>

            <!-- KEPUTUSAN AKHIR -->
            <div class="bg-light p-3 rounded mb-4 border d-flex justify-content-between align-items-center">
                <div>
                    <span class="fw-bold text-dark d-block">Status Hasil Pengujian Akhir</span>
                    <small class="text-muted">Tentukan apakah alat S41 ini siap dipasang ke pelanggan atau ditolak.</small>
                </div>
                <div>
                    <select name="status_akhir" class="form-select fw-bold text-primary border-primary" required>
                        <option value="LOLOS UJI">✓ LOLOS UJI (SIAP PASANG)</option>
                        <option value="TIDAK LOLOS">✕ TIDAK LOLOS UJI</option>
                    </select>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 border-top pt-3">
                <a href="index.php?page=pemasangan&menu=pra_eksekusi&sub=pengujian&view=daftar" class="btn btn-sm btn-light border text-secondary px-4 fw-semibold">Batal</a>
                <button type="submit" class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-save me-1"></i> Simpan Formulir Pengujian</button>
            </div>
        </form>
    </div>
</div>