<?php
// templates/penggunaan/input_saldo.php  -  Input Saldo Tunggakan (tabel dpp)
// Urutan: (1) pilih unit tujuan -> (2) cari & pilih IdPel dari aset barang (master_barang) -> (3) isi periode & saldo.
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
    'subtitle' => 'Saldo tagihan pelanggan per periode (tabel DPP). Pilih unit, cari IdPel dari aset barang, lalu isi saldo.',
    'action'   => 'input_saldo',
    'buttons'  => !$unit_siap ? [] : array_values(array_filter([
        $boleh_monitor ? ['label' => 'Monitoring Saldo', 'icon' => 'bi-display', 'href' => pg_url('monitor_saldo'),
                          'class' => 'btn btn-outline-primary btn-sm px-3 shadow-sm rounded-2 bg-white'] : null,
        $unit_lengkap ? ['label' => 'Import Excel', 'icon' => 'bi-file-earmark-excel',
                         'class' => 'btn btn-success btn-sm px-3 shadow-sm rounded-2',
                         'attrs' => 'data-bs-toggle="modal" data-bs-target="#modalImportSaldo"'] : null,
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

<!-- LANGKAH 1: UNIT TUJUAN -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-3"><span class="badge <?= $unit_lengkap ? 'bg-success' : 'bg-primary' ?> rounded-circle me-2"><?= $unit_lengkap ? '✓' : '1' ?></span>Pilih Unit Tujuan</h6>
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

<!-- LANGKAH 2: CARI & PILIH IDPEL (master_barang <-> dil) -->
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
                <input type="text" name="keyword" value="<?= pg_e($keyword) ?>" class="form-control form-control-sm" placeholder="Cari IdPel, nama, NoRef, IMEI, atau nomor SIM...">
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
                    <th class="ps-4">IdPel</th><th>Nama Pelanggan</th><th>NoRef</th><th>Nomor SIM</th><th class="text-center">Aset</th><th class="text-end pe-4">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$cari): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-1 text-secondary"></i>
                    <?= $keyword !== '' ? 'Tidak ada IdPel yang cocok pada unit ini.' : 'Belum ada aset barang terpasang (IdPel terisi dan terdaftar di DIL) pada unit ini.' ?></td></tr>
            <?php else: foreach ($cari as $r): $dipilih = $idpel_pilih === $r['IdPel']; ?>
                <tr class="<?= $dipilih ? 'table-primary' : '' ?>">
                    <td class="ps-4 font-monospace fw-semibold"><?= pg_e($r['IdPel']) ?></td>
                    <td><?= $r['NamaPelanggan'] === null ? '<span class="text-muted">-</span>' : pg_e($r['NamaPelanggan']) ?></td>
                    <td class="font-monospace small"><?= pg_e($r['NoRef']) ?></td>
                    <td class="font-monospace small"><?= $r['SimId'] === null ? '-' : pg_e($r['SimId']) ?></td>
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
                <input type="month" class="form-control" id="thblrek" name="thblrek" max="<?= date('Y-m') ?>" required>
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
        <div class="form-text mt-2">Data dengan IdPel + ThBlRek yang sama akan diperbarui, bukan digandakan. Unit UPI/AP/UP mengikuti unit tujuan.</div>
        <?php endif; ?>
    </div>
</div>

<?php if ($unit_lengkap): ?>
<!-- MODAL IMPORT EXCEL -->
<div class="modal fade" id="modalImportSaldo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= pg_e($url_sd()) ?>" enctype="multipart/form-data" class="modal-content border-0 shadow rounded-4">
            <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
            <input type="hidden" name="aksi" value="import">
            <?= $hidden_unit() ?>
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-excel text-success me-2"></i>Import Saldo Tunggakan dari Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="small text-muted mb-2"><i class="bi bi-geo-alt me-1"></i>Unit tujuan: <strong><?= pg_e($label_wilayah) ?></strong></div>
                <p class="small text-muted mb-2">File <strong>.xlsx</strong> (maks. 10 MB), sheet pertama. Baris judul dicari otomatis; urutan kolom bebas:</p>
                <table class="table table-sm table-bordered small text-center mb-3">
                    <thead class="table-light"><tr><th>IdPel</th><th>ThBlRek <span class="text-muted fw-normal">(atau Bulan)</span></th><th>RpTag <span class="text-muted fw-normal">(atau Saldo)</span></th></tr></thead>
                    <tbody><tr><td>123456789012</td><td>202501</td><td>150000</td></tr></tbody>
                </table>
                <ul class="small text-muted ps-3">
                    <li>Unit UPI/AP/UP disimpan otomatis sesuai unit tujuan di atas.</li>
                    <li>IdPel yang sudah terdaftar akan mengubah data yang lama (pada ThBlRek yang sama).</li>
                    <li>IdPel kurang dari 12 karakter tidak akan disimpan.</li>
                    <li>IdPel harus sudah ada di DIL; yang tidak ada di DIL dilewati dan tidak ditambahkan.</li>
                </ul>
                <input type="file" name="file_xlsx" class="form-control" accept=".xlsx" required>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success px-4"><i class="bi bi-upload me-1"></i>Import</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>