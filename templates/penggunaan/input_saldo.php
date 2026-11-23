<?php
// templates/penggunaan/input_saldo.php  -  Input Saldo Tunggakan (tabel dpp)
// Dua cara input (seperti Input DIL): Input Form (langkah 1-3) dan Upload Excel (pop-up).
// Urutan form: (1) pilih unit tujuan -> (2) cari & pilih IdPel dari aset barang (view aset barang) -> (3) isi periode & saldo.
// Variabel dari modules/penggunaan/input_saldo.php:
//   $unit_siap, $perlu_pilih, $sAp, $sUp, $sel_ap, $sel_up, $unit_lengkap, $opsi_ap, $opsi_up, $label_wilayah,
//   $keyword, $hal, $limit, $cari, $total_cari, $idpel_pilih, $info_pilih, $csrf, $boleh_monitor,
//   $pesan_sukses, $pesan_error, $detail_import

// parameter unit yang harus ikut di setiap tautan/form (hanya unit yang dipilih manual, bukan dari session)
$par_unit = ['unit_ap' => $sAp === '' ? $sel_ap : '', 'unit_up' => $sUp === '' ? $sel_up : ''];
$url_sd = fn(array $ubah = []) => pg_url('input_saldo', array_merge($par_unit, ['keyword' => $keyword], $ubah));
$hidden_unit = function () use ($par_unit) {
    $h = '';
    foreach ($par_unit as $k => $v) if ($v !== '') $h .= '<input type="hidden" name="' . $k . '" value="' . pg_e($v) . '">';
    return $h;
};

echo pg_header([
    'title'    => 'Input Saldo Tunggakan',
    'subtitle' => 'Saldo tagihan pelanggan per periode (tabel DPP). Input lewat form atau upload Excel.',
    'action'   => 'input_saldo',
    'buttons'  => !$unit_siap ? [] : array_values(array_filter([
        $boleh_monitor ? ['label' => 'Monitoring Saldo', 'icon' => 'bi-display', 'href' => pg_url('monitor_saldo'),
                          'class' => 'btn btn-outline-primary btn-sm px-3 shadow-sm rounded-2 bg-white'] : null,
    ])),
]);

if (!empty($pesan_sukses)) echo pg_alert('success', $pesan_sukses);
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
?>

<!-- PINTASAN: DUA CARA INPUT -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <a href="#bagian-form" class="card border-0 shadow-sm rounded-4 p-4 w-100 h-100 text-start bg-white text-decoration-none border-start border-4 border-primary">
            <div class="d-flex align-items-center gap-3">
                <span class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:52px;height:52px;"><i class="bi bi-pencil-square fs-4"></i></span>
                <span><span class="d-block fw-bold text-dark">Input Form</span><span class="small text-muted">Pilih unit, cari IdPel dari aset barang, lalu isi periode &amp; saldo satu per satu.</span></span>
            </div>
        </a>
    </div>
    <div class="col-md-6">
        <button type="button" class="card border-0 shadow-sm rounded-4 p-4 w-100 h-100 text-start bg-white" data-bs-toggle="modal" data-bs-target="#modalImportSaldo">
            <div class="d-flex align-items-center gap-3">
                <span class="rounded-circle bg-success bg-opacity-10 text-success d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:52px;height:52px;"><i class="bi bi-file-earmark-excel fs-4"></i></span>
                <span><span class="d-block fw-bold text-dark">Upload Excel</span><span class="small text-muted">Muat banyak saldo sekaligus dari file .xlsx (IdPel, ThBlRek, RpTag).</span></span>
            </div>
        </button>
    </div>
</div>

<!-- LANGKAH 1: UNIT TUJUAN -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-3"><span class="badge <?= $unit_lengkap ? 'bg-success' : 'bg-primary' ?> rounded-circle me-2"><?= $unit_lengkap ? '✓' : '1' ?></span>Pilih Unit Tujuan <span class="fw-normal text-muted small">(untuk Input Form; Upload Excel memilih unitnya sendiri di pop-up)</span></h6>
        <?php if (!$perlu_pilih): ?>
            <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i>Wilayah akun Anda: <strong><?= pg_e($label_wilayah) ?></strong> (otomatis dari akun).</div>
        <?php else: ?>
        <form method="GET" action="" class="row g-2 align-items-end" id="formUnit">
            <?= pg_hidden('input_saldo') ?>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-secondary text-uppercase">UPI</label>
                <input type="text" class="form-control form-control-sm bg-light" value="<?= pg_e($label_wilayah !== '' ? explode(' › ', $label_wilayah)[0] : '') ?>" disabled>
            </div>
            <div class="col-md-4">
                <label for="unit_ap" class="form-label small fw-bold text-secondary text-uppercase">Unit AP</label>
                <?php if ($sAp !== ''): ?>
                    <input type="hidden" name="unit_ap" value="<?= pg_e($sAp) ?>">
                    <input type="text" class="form-control form-control-sm bg-light" value="<?= pg_e($opsi_ap[$sAp] ?? $sAp) ?>" disabled>
                <?php else: ?>
                    <select class="form-select form-select-sm" id="unit_ap" name="unit_ap" onchange="document.getElementById('unit_up').value=''; this.form.submit();">
                        <option value="">Pilih Unit AP...</option>
                        <?php foreach ($opsi_ap as $k => $n): ?><option value="<?= pg_e($k) ?>" <?= (string) $k === $sel_ap ? 'selected' : '' ?>><?= pg_e($n) ?></option><?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label for="unit_up" class="form-label small fw-bold text-secondary text-uppercase">Unit UP</label>
                <select class="form-select form-select-sm" id="unit_up" name="unit_up" onchange="this.form.submit();" <?= $sel_ap === '' ? 'disabled' : '' ?>>
                    <option value="">Pilih Unit UP...</option>
                    <?php foreach ($opsi_up[$sel_ap] ?? [] as $k => $n): ?><option value="<?= pg_e($k) ?>" <?= (string) $k === $sel_up ? 'selected' : '' ?>><?= pg_e($n) ?></option><?php endforeach; ?>
                </select>
            </div>
        </form>
        <?php if (!$unit_lengkap): ?><div class="form-text mt-2">Pilih Unit AP, lalu Unit UP, untuk melanjutkan ke pencarian IdPel.</div><?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div id="bagian-form" style="scroll-margin-top:80px">
<!-- LANGKAH 2: CARI & PILIH IDPEL (view aset barang <-> dil) -->
<div class="card border-0 shadow-sm rounded-4 mb-4 <?= $unit_lengkap ? '' : 'opacity-50' ?>">
    <div class="card-body p-4 pb-2">
        <h6 class="fw-bold mb-3"><span class="badge <?= $idpel_pilih ? 'bg-success' : 'bg-primary' ?> rounded-circle me-2"><?= $idpel_pilih ? '✓' : '2' ?></span>Cari &amp; Pilih IdPel
            <span class="fw-normal text-muted small">(dari aset barang di unit terpilih, harus terdaftar di DIL)</span></h6>
        <?php if (!$unit_lengkap): ?>
            <div class="small text-muted pb-3">Selesaikan pemilihan unit terlebih dahulu.</div>
        <?php else: ?>
        <form method="GET" action="" class="row g-2 align-items-center mb-3">
            <?= pg_hidden('input_saldo') ?><?= $hidden_unit() ?>
            <div class="col-md-8">
                <input type="text" name="keyword" value="<?= pg_e($keyword) ?>" class="form-control form-control-sm" placeholder="Cari IdPel, nama, NoRef, SIM ID, IMEI, atau nomor barang...">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary px-3"><i class="bi bi-search me-1"></i>Cari</button>
                <?php if ($keyword !== ''): ?><a href="<?= pg_e(pg_url('input_saldo', $par_unit)) ?>" class="btn btn-sm btn-outline-secondary px-3">Reset</a><?php endif; ?>
            </div>
        </form>
        <?php endif; ?>
    </div>
    <?php if ($unit_lengkap): ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="text-secondary text-uppercase" style="font-size:.75rem;letter-spacing:.3px;white-space:nowrap;">
                    <th class="ps-4">IdPel</th><th>Nama Pelanggan</th><th>NoRef</th><th>SIM ID Barang</th><th>IMEI Modem</th><th>Nomor Barang</th><th class="text-center">Aset</th><th class="text-end pe-4">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$cari): ?>
                <tr><td colspan="8" class="text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-1 text-secondary"></i>
                    <?= $keyword !== '' ? 'Tidak ada IdPel yang cocok pada unit ini.' : 'Belum ada aset barang terpasang (IdPel terisi dan terdaftar di DIL) pada unit ini.' ?></td></tr>
            <?php else: foreach ($cari as $r): $dipilih = $idpel_pilih === $r['IdPel']; ?>
                <tr class="<?= $dipilih ? 'table-primary' : '' ?>">
                    <td class="ps-4 font-monospace fw-semibold"><?= pg_e($r['IdPel']) ?></td>
                    <td><?= $r['NamaPelanggan'] === null ? '<span class="text-muted">-</span>' : pg_e($r['NamaPelanggan']) ?></td>
                    <td class="font-monospace small"><?= pg_e($r['NoRef']) ?></td>
                    <td class="font-monospace small"><?= $r['SimIdBarang'] === null ? '-' : pg_e($r['SimIdBarang']) ?></td>
                    <td class="font-monospace small"><?= $r['ImeiModem'] === null ? '-' : pg_e($r['ImeiModem']) ?></td>
                    <td class="font-monospace small"><?= $r['NomorBarang'] === null ? '-' : pg_e($r['NomorBarang']) ?></td>
                    <td class="text-center"><?= (int) $r['jml'] ?></td>
                    <td class="text-end pe-4">
                        <?php if ($dipilih): ?><span class="badge bg-primary"><i class="bi bi-check2 me-1"></i>Dipilih</span>
                        <?php else: ?><a href="<?= pg_e($url_sd(['idpel' => $r['IdPel'], 'hal' => $hal > 1 ? $hal : ''])) ?>" class="btn btn-sm btn-outline-primary">Pilih</a><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?= pg_footer_tabel($hal, $limit, $total_cari, fn($h) => $url_sd(['idpel' => $idpel_pilih ?? '', 'hal' => $h > 1 ? $h : ''])) ?>
    <?php endif; ?>
</div>

<!-- LANGKAH 3: INPUT SALDO -->
<div class="card border-0 shadow-sm rounded-4 mb-4 <?= $idpel_pilih ? '' : 'opacity-50' ?>">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-3"><span class="badge bg-primary rounded-circle me-2">3</span>Input Saldo Tunggakan</h6>
        <?php if (!$idpel_pilih): ?>
            <div class="small text-muted">Pilih IdPel pada langkah 2 terlebih dahulu.</div>
        <?php else: ?>
        <form method="POST" action="<?= pg_e($url_sd(['idpel' => $idpel_pilih])) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
            <input type="hidden" name="aksi" value="tambah">
            <input type="hidden" name="idpel" value="<?= pg_e($idpel_pilih) ?>">
            <?= $hidden_unit() ?>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-secondary text-uppercase">IdPel</label>
                <input type="text" class="form-control font-monospace bg-light" value="<?= pg_e($idpel_pilih) ?>" disabled>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-secondary text-uppercase">Nama Pelanggan</label>
                <input type="text" class="form-control bg-light" value="<?= $info_pilih['nama'] === null ? '-' : pg_e($info_pilih['nama']) ?>" disabled>
            </div>
            <div class="col-md-2">
                <label for="thblrek" class="form-label small fw-bold text-secondary text-uppercase">ThBlRek</label>
                <input type="month" class="form-control" id="thblrek" name="thblrek" value="<?= date('Y-m', strtotime('first day of this month')) ?>" required>
            </div>
            <div class="col-md-2">
                <label for="rptag" class="form-label small fw-bold text-secondary text-uppercase">Saldo (RpTag)</label>
                <div class="input-group"><span class="input-group-text">Rp</span>
                    <input type="number" class="form-control" id="rptag" name="rptag" min="0" step="1" required></div>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary shadow-sm"><i class="bi bi-save me-1"></i> Simpan</button>
            </div>
        </form>
        <div class="form-text mt-2">ThBlRek otomatis diisi bulan depan; bulan dan tahun lain (termasuk yang belum berjalan) bisa dipilih. Data IdPel + ThBlRek yang sudah ada tidak akan diperbarui. Unit UPI/AP/UP mengikuti unit tujuan.</div>
        <?php endif; ?>
    </div>
</div>

</div><!-- /#bagian-form -->

<!-- POP-UP: UPLOAD EXCEL (unit tujuan dipilih di sini, tidak perlu menyelesaikan langkah 1) -->
<div class="modal fade" id="modalImportSaldo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= pg_e(pg_url('input_saldo')) ?>" enctype="multipart/form-data" class="modal-content border-0 shadow rounded-4" id="formImportSaldo">
            <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
            <input type="hidden" name="aksi" value="import">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-excel text-success me-2"></i>Upload Saldo Tunggakan dari Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="small fw-bold text-secondary text-uppercase mb-2">Unit tujuan</p>
                <?php if (!$perlu_pilih): ?>
                    <div class="small text-muted mb-3"><i class="bi bi-geo-alt me-1"></i><strong><?= pg_e($label_wilayah) ?></strong> (otomatis dari akun).</div>
                <?php else: ?>
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small text-secondary mb-1">UPI</label>
                        <input type="text" class="form-control form-control-sm bg-light" value="<?= pg_e($label_wilayah !== '' ? explode(' › ', $label_wilayah)[0] : '') ?>" disabled>
                    </div>
                    <div class="col-md-4">
                        <label for="ix_ap" class="form-label small text-secondary mb-1">Unit AP *</label>
                        <?php if ($sAp !== ''): ?>
                            <input type="hidden" name="unit_ap" id="ix_ap" value="<?= pg_e($sAp) ?>">
                            <input type="text" class="form-control form-control-sm bg-light" value="<?= pg_e($opsi_ap[$sAp] ?? $sAp) ?>" disabled>
                        <?php else: ?>
                            <select class="form-select form-select-sm" id="ix_ap" name="unit_ap" required>
                                <option value="">Pilih Unit AP...</option>
                                <?php foreach ($opsi_ap as $k => $n): ?><option value="<?= pg_e($k) ?>" <?= (string) $k === $sel_ap ? 'selected' : '' ?>><?= pg_e($n) ?></option><?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label for="ix_up" class="form-label small text-secondary mb-1">Unit UP *</label>
                        <select class="form-select form-select-sm" id="ix_up" name="unit_up" required>
                            <option value="">Pilih Unit UP...</option>
                        </select>
                    </div>
                </div>
                <?php endif; ?>

                <p class="small text-muted mb-2">File <strong>.xlsx</strong> (maks. 10 MB), sheet pertama. Baris judul dicari otomatis; urutan kolom bebas:</p>
                <table class="table table-sm table-bordered small text-center mb-3">
                    <thead class="table-light"><tr><th>IdPel</th><th>ThBlRek <span class="text-muted fw-normal">(atau Bulan)</span></th><th>RpTag <span class="text-muted fw-normal">(atau Saldo)</span></th></tr></thead>
                    <tbody><tr><td>123456789012</td><td>202501</td><td>150000</td></tr></tbody>
                </table>
                <ul class="small text-muted ps-3">
                    <li>ThBlRek berformat <code>YYYYMM</code> (mis. 202501) atau <code>YYYYMMDD</code>; RpTag angka tidak negatif.</li>
                    <li>Unit UPI/AP/UP disimpan otomatis sesuai unit tujuan di atas.</li>
                    <li>IdPel + ThBlRek yang sudah ada di DPP tidak diperbarui (dilewati); hanya data baru yang disimpan.</li>
                    <li>IdPel harus persis 12 digit dan sudah terdaftar di DIL; yang tidak memenuhi dilewati.</li>
                    <li>Semua data disimpan dalam satu transaksi; bila terjadi error, tidak ada data yang tersimpan.</li>
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

<?php if ($perlu_pilih): ?>
<!-- Pop-up Upload Excel: Unit UP mengikuti Unit AP yang dipilih -->
<script>
(function () {
    var ap = document.getElementById('ix_ap'), up = document.getElementById('ix_up');
    if (!ap || !up) return;
    var peta = <?= json_encode($peta_unit, JSON_UNESCAPED_UNICODE) ?>;
    function isi(pilih) {
        up.innerHTML = '<option value="">Pilih Unit UP...</option>';
        var u = peta.filter(function (x) { return x.kode === ap.value; })[0];
        (u ? u.ups : []).forEach(function (x) {
            var o = document.createElement('option'); o.value = x.kode; o.textContent = x.nama;
            if (x.kode === pilih) o.selected = true; up.appendChild(o);
        });
        up.disabled = !u;
    }
    ap.addEventListener('change', function () { isi(''); });
    isi(<?= json_encode($sel_up) ?>);
})();
</script>
<?php endif; ?>

<!-- Loader saat Simpan / Upload (memakai window.showPageLoader bila ada; selain itu overlay cadangan) -->
<script>
(function () {
    function tampil(teks) {
        var ov = document.getElementById('page-loader');
        if (ov && typeof window.showPageLoader === 'function') {
            var t = ov.querySelector('.txt'); if (t && teks) t.textContent = teks;
            window.showPageLoader(); ov.classList.add('show'); return;
        }
        if (document.getElementById('saldo-loader-cadangan')) return;
        var d = document.createElement('div');
        d.id = 'saldo-loader-cadangan';
        d.style.cssText = 'position:fixed;inset:0;z-index:99999;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(255,255,255,.8);font-weight:600;color:#0b253a';
        d.innerHTML = '<div style="width:56px;height:56px;border:5px solid #dbe7f5;border-top-color:#0d6efd;border-radius:50%;animation:saldospin .8s linear infinite"></div><div style="margin-top:14px"></div><style>@keyframes saldospin{to{transform:rotate(360deg)}}</style>';
        d.children[1].textContent = teks || 'Memproses…';
        document.body.appendChild(d);
    }
    document.querySelectorAll('form').forEach(function (f) {
        var aksi = f.querySelector('input[name="aksi"]');
        if (!aksi || ['tambah', 'import'].indexOf(aksi.value) < 0) return;
        f.addEventListener('submit', function (e) {
            if (e.defaultPrevented) return;
            tampil(aksi.value === 'import' ? 'Memproses file Excel, mohon tunggu…' : 'Menyimpan…');
        });
    });
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        var c = document.getElementById('saldo-loader-cadangan'); if (c) c.remove();
        if (typeof window.hidePageLoader === 'function') window.hidePageLoader();
    });
})();
</script>