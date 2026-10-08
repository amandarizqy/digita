<div class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-file-earmark-plus me-2"></i>Buat Draft Pengiriman</h1>
        <a href="index.php?page=pengadaan&menu=pengiriman&view=daftar" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    </div>

    <div class="card shadow border-0">
        <div class="card-body">
            <form action="modules/pengadaan/proses_pengiriman.php?action=create_draft" method="POST" id="formPengiriman">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold text-dark mb-3">Informasi Utama</h6>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Tanggal Pengiriman</label>
                            <input type="text" class="form-control bg-light" value="<?= date('d-m-Y') ?>" readonly>
                            <input type="hidden" name="tgl_form" value="<?= date('Y-m-d') ?>">
                            <small class="text-secondary">Tanggal otomatis diset ke hari ini. Nomor Formulir (F.B) akan digenerate otomatis.</small>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="fw-bold text-dark mb-3">Pilih Tujuan Pengiriman (Pilih Salah Satu Saja)</h6>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Unit Pelaksana (AP)</label>
                            <select class="form-select" name="kode_ap_tujuan" id="selectAP">
                                <option value="">-- Pilih Area Tujuan (AP) --</option>
                                <?php foreach ($list_ap as $ap): ?>
                                    <option value="<?= htmlspecialchars($ap['UnitAp']) ?>"><?= htmlspecialchars($ap['NamaUnit']) ?> (<?= htmlspecialchars($ap['UnitAp']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="text-center text-muted small my-2 fw-bold">- ATAU -</div>

                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Unit Layanan (UP)</label>
                            <select class="form-select" name="kode_up_tujuan" id="selectUP">
                                <option value="">-- Pilih Layanan Tujuan (UP) --</option>
                                <?php foreach ($list_up as $up): ?>
                                    <option value="<?= htmlspecialchars($up['UnitUp']) ?>"><?= htmlspecialchars($up['NamaUnit']) ?> (<?= htmlspecialchars($up['UnitUp']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <small class="text-danger" id="warningTujuan" style="display:none;">Harap pilih salah satu tujuan (AP saja atau UP saja).</small>
                    </div>
                </div>

                <hr class="text-secondary mb-4">
                
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary px-4" id="btnSubmit"><i class="bi bi-save me-2"></i>Simpan Draft & Mulai Pilih Barang</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAP = document.getElementById('selectAP');
    const selectUP = document.getElementById('selectUP');
    const form = document.getElementById('formPengiriman');
    const warning = document.getElementById('warningTujuan');

    selectAP.addEventListener('change', function() {
        if (this.value !== "") {
            selectUP.value = "";
        }
    });

    selectUP.addEventListener('change', function() {
        if (this.value !== "") {
            selectAP.value = "";
        }
    });

    form.addEventListener('submit', function(e) {
        if (selectAP.value === "" && selectUP.value === "") {
            e.preventDefault();
            warning.style.display = 'block';
        } else {
            warning.style.display = 'none';
        }
    });
});
</script>