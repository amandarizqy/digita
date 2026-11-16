<?php
// templates/penggunaan/shuntrip.php  -  Update Jenis Shuntrip
// Variabel dari modules/penggunaan/shuntrip.php:
//   $ringkas, $total_dil, $csrf, $u, $unit_siap, $form_lama, $buka_modal, $boleh_monitor, $detail_import,
//   $pesan_sukses, $pesan_error, $pesan_info, konstanta JENIS_SHUNTRIP

$lama = $form_lama ?? [];

echo pg_header([
    'title'    => 'Update Jenis Shuntrip',
    'subtitle' => 'Perbarui jenis shuntrip pelanggan (kolom JenisShuntrip pada DIL) berdasarkan IdPel. Wilayah: ' . $u['label'] . '.',
    'action'   => 'shuntrip',
    // 'buttons'  => !$unit_siap ? [] : array_values(array_filter([
    //     $boleh_monitor ? ['label' => 'Monitoring DIL', 'icon' => 'bi-display', 'href' => pg_url('monitor_dil'),
    //                       'class' => 'btn btn-outline-primary btn-sm px-3 shadow-sm rounded-2 bg-white'] : null,
    //     ['label' => 'Update Satu IdPel', 'icon' => 'bi-pencil-square', 'class' => 'btn btn-primary btn-sm px-3 shadow-sm rounded-2',
    //      'attrs' => 'data-bs-toggle="modal" data-bs-target="#modalShuntripSatu"'],
    //     ['label' => 'Upload Excel', 'icon' => 'bi-file-earmark-excel', 'class' => 'btn btn-success btn-sm px-3 shadow-sm rounded-2',
    //      'attrs' => 'data-bs-toggle="modal" data-bs-target="#modalShuntripExcel"'],
    // ])),
]);

if (!empty($pesan_sukses)) echo pg_alert('success', $pesan_sukses);
if (!empty($pesan_info))   echo pg_alert('info', $pesan_info);
if (!empty($pesan_error))  echo pg_alert('danger', $pesan_error);
if (!empty($detail_import)) {
    echo '<div class="alert alert-warning border-0 shadow-sm rounded-3 small"><strong>Baris yang dilewati'
       . (count($detail_import) >= 20 ? ' (20 pertama)' : '') . ':</strong><ul class="mb-0 mt-1">';
    foreach ($detail_import as $d) echo '<li>' . pg_e($d) . '</li>';
    echo '</ul></div>';
}
if (!$unit_siap) {
    echo pg_alert('danger', 'Akun Anda belum memiliki <strong>Unit UPI</strong>, sehingga halaman ini tidak dapat dipakai. Hubungi administrator.');
    return;
}
$warna = ['MCCB' => 'primary', 'RELAY' => 'success', 'ALARM' => 'danger', 'S41' => 'info', 'AMI' => 'warning', 'SDU' => 'secondary'];
?>

<!-- RINGKASAN: jumlah pelanggan per jenis shuntrip
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3 col-xl">
        <div class="card border-0 shadow-sm rounded-4 py-3 px-3 bg-white h-100">
            <span class="text-muted fw-bold text-uppercase" style="font-size:.68rem;letter-spacing:.4px;">Total Pelanggan</span>
            <h3 class="fw-bold mt-1 mb-0 fs-4 text-dark"><?= pg_n($total_dil) ?></h3>
        </div>
    </div>
    <?php foreach (JENIS_SHUNTRIP as $j): ?>
    <div class="col-6 col-md-3 col-xl">
        <div class="card border-0 shadow-sm rounded-4 py-3 px-3 bg-white h-100">
            <span class="text-muted fw-bold text-uppercase" style="font-size:.68rem;letter-spacing:.4px;"><?= pg_e($j) ?></span>
            <h3 class="fw-bold mt-1 mb-0 fs-4 text-<?= $warna[$j] ?? 'dark' ?>"><?= pg_n($ringkas[$j]) ?></h3>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="col-6 col-md-3 col-xl">
        <div class="card border-0 shadow-sm rounded-4 py-3 px-3 bg-white h-100">
            <span class="text-muted fw-bold text-uppercase" style="font-size:.68rem;letter-spacing:.4px;">Belum Diisi</span>
            <h3 class="fw-bold mt-1 mb-0 fs-4 text-warning"><?= pg_n($ringkas['_kosong']) ?></h3>
        </div>
    </div>
</div> -->

<!-- PINTASAN -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <button type="button" class="card border-0 shadow-sm rounded-4 p-4 w-100 text-start bg-white" data-bs-toggle="modal" data-bs-target="#modalShuntripSatu">
            <div class="d-flex align-items-center gap-3">
                <span class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center" style="width:52px;height:52px;"><i class="bi bi-pencil-square fs-4"></i></span>
                <span><span class="d-block fw-bold text-dark">Update Satu IdPel</span><span class="small text-muted">Isi IdPel dan pilih jenis shuntrip lewat form pop-up.</span></span>
            </div>
        </button>
    </div>
    <div class="col-md-6">
        <button type="button" class="card border-0 shadow-sm rounded-4 p-4 w-100 text-start bg-white" data-bs-toggle="modal" data-bs-target="#modalShuntripExcel">
            <div class="d-flex align-items-center gap-3">
                <span class="rounded-circle bg-success bg-opacity-10 text-success d-inline-flex align-items-center justify-content-center" style="width:52px;height:52px;"><i class="bi bi-file-earmark-excel fs-4"></i></span>
                <span><span class="d-block fw-bold text-dark">Upload Excel</span><span class="small text-muted">Perbarui banyak IdPel sekaligus dari file dengan kolom IdPel dan JenisShuntrip.</span></span>
            </div>
        </button>
    </div>
</div>

<!-- POP-UP: SATU IDPEL -->
<div class="modal fade" id="modalShuntripSatu" tabindex="-1" aria-labelledby="judulShuntripSatu" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= pg_e(pg_url('shuntrip')) ?>" class="modal-content border-0 shadow rounded-4">
            <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
            <input type="hidden" name="aksi" value="satu">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="judulShuntripSatu"><i class="bi bi-pencil-square text-primary me-2"></i>Update Satu IdPel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="st_idpel" class="form-label small fw-bold text-secondary text-uppercase">IdPel *</label>
                    <input type="text" class="form-control font-monospace" id="st_idpel" name="idpel" maxlength="12" minlength="12" pattern="[A-Za-z0-9]{12}"
                           placeholder="12 karakter" value="<?= pg_e($lama['idpel'] ?? '') ?>" required autocomplete="off">
                </div>
                <div class="mb-3">
                    <label for="st_jenis" class="form-label small fw-bold text-secondary text-uppercase">Jenis Shuntrip *</label>
                    <select class="form-select" id="st_jenis" name="jenis" required>
                        <option value="">- pilih -</option>
                        <?php foreach (JENIS_SHUNTRIP as $j): ?><option value="<?= pg_e($j) ?>" <?= ($lama['jenis'] ?? '') === $j ? 'selected' : '' ?>><?= pg_e($j) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="st_lanjut" name="lanjut" value="1" <?= !empty($lama['lanjut']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="st_lanjut">Tetap buka form setelah simpan (untuk IdPel berikutnya)</label>
                </div>
                <div class="small text-muted">Hanya IdPel yang sudah ada di DIL pada wilayah Anda yang bisa diperbarui; kolom lain tidak berubah.</div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- POP-UP: UPLOAD EXCEL -->
<div class="modal fade" id="modalShuntripExcel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= pg_e(pg_url('shuntrip')) ?>" enctype="multipart/form-data" class="modal-content border-0 shadow rounded-4">
            <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
            <input type="hidden" name="aksi" value="excel">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-excel text-success me-2"></i>Upload Excel Jenis Shuntrip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">File <strong>.xlsx</strong> (maks. 10 MB), sheet pertama. Baris judul dicari otomatis; urutan kolom bebas:</p>
                <table class="table table-sm table-bordered small text-center mb-3">
                    <thead class="table-light"><tr><th>IdPel</th><th>JenisShuntrip</th></tr></thead>
                    <tbody><tr><td>546100000119</td><td>MCCB</td></tr><tr><td>546100000168</td><td>RELAY</td></tr></tbody>
                </table>
                <ul class="small text-muted ps-3">
                    <li>Nilai yang diterima: <strong><?= pg_e(implode(', ', JENIS_SHUNTRIP)) ?></strong> (huruf besar/kecil bebas).</li>
                    <li>Hanya IdPel yang sudah ada di DIL pada wilayah Anda yang diperbarui; yang lain dilewati dan dilaporkan.</li>
                    <li>Nilai yang sama dengan data sekarang dilewati. Bila satu IdPel muncul dua kali, baris terakhir dipakai.</li>
                    <li>Semua perubahan disimpan dalam satu transaksi; bila terjadi error, tidak ada data yang berubah.</li>
                </ul>
                <input type="file" name="file_xlsx" class="form-control" accept=".xlsx" required>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success px-4"><i class="bi bi-upload me-1"></i>Upload &amp; Proses</button>
            </div>
        </form>
    </div>
</div>

<?php if (($buka_modal ?? '') === 'satu'): ?>
<script>
// buka kembali pop-up satu IdPel (gagal simpan, atau "tetap buka form" dicentang)
document.addEventListener('DOMContentLoaded', function () {
    var m = document.getElementById('modalShuntripSatu'); if (!m || !window.bootstrap) return;
    m.addEventListener('shown.bs.modal', function () { var i = document.getElementById('st_idpel'); if (i) { i.focus(); i.select(); } }, { once: true });
    bootstrap.Modal.getOrCreateInstance(m).show();
});
</script>
<?php endif; ?>

<!-- Loader saat Simpan / Upload -->
<script>
(function () {
    function tampil(teks) {
        var ov = document.getElementById('page-loader');
        if (ov && typeof window.showPageLoader === 'function') {
            var t = ov.querySelector('.txt'); if (t && teks) t.textContent = teks;
            window.showPageLoader(); ov.classList.add('show'); return;
        }
        if (document.getElementById('st-loader-cadangan')) return;
        var d = document.createElement('div'); d.id = 'st-loader-cadangan';
        d.style.cssText = 'position:fixed;inset:0;z-index:99999;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(255,255,255,.8);font-weight:600;color:#0b253a';
        d.innerHTML = '<div style="width:56px;height:56px;border:5px solid #dbe7f5;border-top-color:#0d6efd;border-radius:50%;animation:stspin .8s linear infinite"></div><div style="margin-top:14px"></div><style>@keyframes stspin{to{transform:rotate(360deg)}}</style>';
        d.children[1].textContent = teks || 'Memproses…';
        document.body.appendChild(d);
    }
    document.querySelectorAll('form').forEach(function (f) {
        var a = f.querySelector('input[name="aksi"]');
        if (!a || (a.value !== 'satu' && a.value !== 'excel')) return;
        f.addEventListener('submit', function (e) { if (!e.defaultPrevented) tampil(a.value === 'excel' ? 'Memproses file Excel, mohon tunggu…' : 'Menyimpan…'); });
    });
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        var c = document.getElementById('st-loader-cadangan'); if (c) c.remove();
        if (typeof window.hidePageLoader === 'function') window.hidePageLoader();
    });
})();
</script>