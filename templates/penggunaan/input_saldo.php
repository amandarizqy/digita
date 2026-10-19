<?php
// templates/penggunaan/input_saldo.php  -  Input Saldo (tabel dpp)
// Variabel dari modules/penggunaan/input_saldo.php:
//   $csrf, $unit_siap, $perlu_pilih, $sAp, $sUp, $peta_unit, $label_wilayah, $boleh_monitor,
//   $pesan_sukses, $pesan_error, $detail_import

echo pg_header([
    'title'    => 'Input Saldo',
    'subtitle' => 'Saldo tagihan pelanggan per periode (tabel DPP). Kode UPI/AP/UP otomatis dari akun yang login.',
    'action'   => 'input_saldo',
    'buttons'  => !$unit_siap ? [] : array_values(array_filter([
        $boleh_monitor ? ['label' => 'Monitoring Saldo', 'icon' => 'bi-display', 'href' => pg_url('monitor_saldo'),
                          'class' => 'btn btn-outline-primary btn-sm px-3 shadow-sm rounded-2 bg-white'] : null,
        ['label' => 'Import Excel', 'icon' => 'bi-file-earmark-excel',
         'class' => 'btn btn-success btn-sm px-3 shadow-sm rounded-2',
         'attrs' => 'data-bs-toggle="modal" data-bs-target="#modalImportSaldo"'],
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

/** Kolom wilayah tujuan: tampil tetap bila dari session, pilihan AP/UP bila belum ada. $p = awalan id. */
$unit_fields = function (string $p) use ($perlu_pilih, $sAp, $sUp, $peta_unit, $label_wilayah) {
    if (!$perlu_pilih) return '<div class="small text-muted"><i class="bi bi-geo-alt me-1"></i>Wilayah: <strong>' . pg_e($label_wilayah) . '</strong></div>';
    $h  = '<div class="small text-muted mb-2"><i class="bi bi-geo-alt me-1"></i>Wilayah: <strong>' . pg_e($label_wilayah) . '</strong> — pilih unit tujuan:</div><div class="row g-2">';
    if ($sAp === '') {
        $h .= '<div class="col"><select class="form-select form-select-sm" name="unit_ap" id="' . $p . '_ap" required><option value="">Unit AP...</option>';
        foreach ($peta_unit as $a) $h .= '<option value="' . pg_e($a['kode']) . '">' . pg_e($a['nama']) . '</option>';
        $h .= '</select></div>';
    } else {
        $h .= '<input type="hidden" name="unit_ap" id="' . $p . '_ap" value="' . pg_e($sAp) . '">';
    }
    $h .= '<div class="col"><select class="form-select form-select-sm" name="unit_up" id="' . $p . '_up" required><option value="">Unit UP...</option></select></div></div>';
    return $h;
};
?>

<!-- FORM TAMBAH -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-3"><i class="bi bi-plus-circle me-2 text-primary"></i>Input Saldo Manual</h6>
        <form method="POST" action="<?= pg_e(pg_url('input_saldo')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
            <input type="hidden" name="aksi" value="tambah">
            <div class="col-md-3">
                <label for="idpel" class="form-label small fw-bold text-secondary text-uppercase">IdPel</label>
                <input type="text" class="form-control font-monospace" id="idpel" name="idpel" maxlength="12" pattern="\d{1,12}" placeholder="12 digit" required>
            </div>
            <div class="col-md-3">
                <label for="thblrek" class="form-label small fw-bold text-secondary text-uppercase">ThBlRek</label>
                <input type="text" class="form-control font-monospace" id="thblrek" name="thblrek" maxlength="8" pattern="\d{6}(\d{2})?" placeholder="YYYYMM / YYYYMMDD" required>
            </div>
            <div class="col-md-4">
                <label for="rptag" class="form-label small fw-bold text-secondary text-uppercase">Saldo (RpTag)</label>
                <div class="input-group"><span class="input-group-text">Rp</span>
                    <input type="number" class="form-control" id="rptag" name="rptag" min="0" step="1" required></div>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary shadow-sm"><i class="bi bi-save me-1"></i> Simpan</button>
            </div>
            <div class="col-12"><?= $unit_fields('tb') ?></div>
        </form>
        <div class="form-text mt-2">Data dengan IdPel + ThBlRek yang sama akan diperbarui, bukan digandakan.</div>
    </div>
</div>

<?php if ($boleh_monitor): ?>
<div class="text-center text-muted small mb-4">
    Ingin melihat atau mencari data saldo? Buka
    <a href="<?= pg_e(pg_url('monitor_saldo')) ?>" class="fw-semibold text-decoration-none"><i class="bi bi-display me-1"></i>Monitoring Saldo</a>.
</div>
<?php endif; ?>

<!-- MODAL IMPORT EXCEL -->
<div class="modal fade" id="modalImportSaldo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= pg_e(pg_url('input_saldo')) ?>" enctype="multipart/form-data" class="modal-content border-0 shadow rounded-4">
            <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
            <input type="hidden" name="aksi" value="import">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-excel text-success me-2"></i>Import Saldo dari Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">File <strong>.xlsx</strong> (maks. 10 MB), sheet pertama. Baris judul dicari otomatis; urutan kolom bebas:</p>
                <table class="table table-sm table-bordered small text-center mb-3">
                    <thead class="table-light"><tr><th>IdPel</th><th>ThBlRek <span class="text-muted fw-normal">(atau Bulan)</span></th><th>RpTag <span class="text-muted fw-normal">(atau Saldo)</span></th></tr></thead>
                    <tbody><tr><td>123456789012</td><td>202501</td><td>150000</td></tr></tbody>
                </table>
                <ul class="small text-muted ps-3">
                    <li>Kode UPI/AP/UP disimpan otomatis sesuai wilayah akun.</li>
                    <li>IdPel + ThBlRek yang sudah ada diperbarui.</li>
                    <li>Semua baris disimpan dalam satu transaksi; bila gagal, tidak ada yang tersimpan.</li>
                </ul>
                <div class="mb-3"><?= $unit_fields('im') ?></div>
                <input type="file" name="file_xlsx" class="form-control" accept=".xlsx" required>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success px-4"><i class="bi bi-upload me-1"></i>Import</button>
            </div>
        </form>
    </div>
</div>

<?php if ($perlu_pilih): ?>
<script>
(function () {
    var peta = <?= json_encode($peta_unit, JSON_UNESCAPED_UNICODE) ?>;
    ['tb', 'im'].forEach(function (p) {
        var ap = document.getElementById(p + '_ap'), up = document.getElementById(p + '_up');
        if (!ap || !up) return;
        function isi() {
            var a = peta.filter(function (x) { return x.kode === ap.value; })[0];
            up.innerHTML = '<option value="">Unit UP...</option>';
            (a ? a.ups : []).forEach(function (u) { var o = document.createElement('option'); o.value = u.kode; o.textContent = u.nama; up.appendChild(o); });
            if (a && a.ups.length === 1) up.value = a.ups[0].kode;
        }
        ap.addEventListener('change', isi);
        isi();
    });
})();
</script>
<?php endif; ?>