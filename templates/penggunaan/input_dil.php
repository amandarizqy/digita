<?php
// templates/penggunaan/input_dil.php  -  Input DIL
// Variabel dari modules/penggunaan/input_dil.php:
//   $kpi, $csrf, $unit_awal, $boleh_monitor, $pesan_sukses, $pesan_error, $detail_import

echo pg_header([
    'title'    => 'Input DIL',
    'subtitle' => 'Tambah pelanggan ke Daftar Induk Langganan, secara manual atau lewat import Excel.',
    'action'   => 'input_dil',
    'buttons'  => array_values(array_filter([
        $boleh_monitor ? ['label' => 'Monitoring DIL', 'icon' => 'bi-display', 'href' => pg_url('monitor_dil'),
                          'class' => 'btn btn-outline-primary btn-sm px-3 shadow-sm rounded-2 bg-white'] : null,
        ['label' => 'Import Excel', 'icon' => 'bi-file-earmark-excel',
         'class' => 'btn btn-success btn-sm px-3 shadow-sm rounded-2',
         'attrs' => 'data-bs-toggle="modal" data-bs-target="#modalImportDil"'],
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
?>

<!-- RINGKASAN -->
<div class="row g-3 mb-4">
    <?php foreach ([['Total Pelanggan', $kpi['total'], 'text-dark'], ['Aktif', $kpi['AKTIF'], 'text-success'], ['Nonaktif', $kpi['TIDAK'], 'text-secondary']] as [$lbl, $val, $cls]): ?>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 py-3 px-4 bg-white h-100">
            <span class="text-muted fw-bold text-uppercase" style="font-size:.75rem;letter-spacing:.5px;"><?= pg_e($lbl) ?></span>
            <h3 class="fw-bold mt-1 mb-0 <?= $cls ?>"><?= pg_n($val) ?></h3>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- FORM TAMBAH -->
<?php
// [name, label, maxlength, kolom bootstrap, atribut tambahan]
$fld = function (string $name, string $label, int $max, string $col = 'col-md-3', string $extra = '', string $val = '') {
    $id = 'f_' . $name;
    return '<div class="' . $col . '"><label for="' . $id . '" class="form-label small fw-bold text-secondary text-uppercase">' . pg_e($label) . '</label>'
         . '<input type="text" class="form-control form-control-sm" id="' . $id . '" name="' . $name . '" maxlength="' . $max . '" value="' . pg_e($val) . '" ' . $extra . '></div>';
};
$judul = fn(string $t, string $ic) => '<div class="col-12"><div class="small fw-bold text-primary border-bottom pb-1 mt-2"><i class="bi ' . $ic . ' me-1"></i>' . $t . '</div></div>';
?>
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0"><i class="bi bi-person-plus me-2 text-primary"></i>Tambah Pelanggan</h6>
            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-50 px-3 py-2">Status AKTIF (otomatis)</span>
        </div>
        <form method="POST" action="<?= pg_e(pg_url('input_dil')) ?>" class="row g-3">
            <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
            <input type="hidden" name="aksi" value="tambah">

            <?= $judul('Identitas Pelanggan', 'bi-person-vcard') ?>
            <?= $fld('idpel', 'IdPel *', 12, 'col-md-3', 'minlength="12" pattern="[A-Za-z0-9]{12}" placeholder="12 karakter" required style="font-family:monospace"') ?>
            <?= $fld('nama', 'Nama *', 50, 'col-md-5', 'placeholder="Maks. 50 karakter" required') ?>
            <?= $fld('nomortlp', 'No. Telepon', 20, 'col-md-2', 'inputmode="tel"') ?>
            <?= $fld('nomorhp', 'No. HP', 20, 'col-md-2', 'inputmode="tel"') ?>
            <?= $fld('alamat', 'Alamat', 100, 'col-12', 'placeholder="Maks. 100 karakter"') ?>

            <?= $judul('Data Layanan & Meter', 'bi-lightning-charge') ?>
            <?= $fld('goltar', 'Gol. Tarif', 5, 'col-md-2', 'placeholder="mis. R1M"') ?>
            <?= $fld('daya', 'Daya (VA)', 9, 'col-md-2', 'inputmode="numeric" pattern="[0-9]*" placeholder="mis. 900"') ?>
            <div class="col-md-2">
                <label for="f_tegukur" class="form-label small fw-bold text-secondary text-uppercase">Tegangan Ukur</label>
                <select class="form-select form-select-sm" id="f_tegukur" name="tegukur">
                    <option value="">- pilih -</option>
                    <option value="TR">TR</option><option value="TM">TM</option><option value="TT">TT</option>
                </select>
            </div>
            <?= $fld('jenismk', 'Jenis MK', 10, 'col-md-2', 'placeholder="mis. N"') ?>
            <?= $fld('kdpembmeter', 'Kd. Pemb. Meter', 1, 'col-md-2', 'placeholder="A/M/P/L/E"') ?>
            <?= $fld('nomorkwh', 'Nomor kWh Meter', 15, 'col-md-2', 'inputmode="numeric"') ?>
            <?= $fld('aktifasi', 'Aktifasi', 15, 'col-md-3', 'placeholder="mis. AKTIF (A)"') ?>
            <?= $fld('produkprabayar', 'Produk Prabayar', 50, 'col-md-4') ?>

            <?= $judul('Unit', 'bi-building') ?>
            <?= $fld('unitupi', 'Unit UPI', 2, 'col-md-2', '', $unit_awal['UnitUpi']) ?>
            <?= $fld('unitap', 'Unit AP', 5, 'col-md-2', '', $unit_awal['UnitAp']) ?>
            <?= $fld('unitup', 'Unit UP', 5, 'col-md-2', '', $unit_awal['UnitUp']) ?>
            <div class="col-md-6 d-flex align-items-end justify-content-end gap-2">
                <button type="reset" class="btn btn-light btn-sm px-3">Kosongkan</button>
                <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm"><i class="bi bi-plus-lg me-1"></i> Simpan</button>
            </div>
            <div class="col-12 small text-muted">* wajib diisi. Index Prioritas diisi otomatis oleh modul Perencanaan.</div>
        </form>
    </div>
</div>

<!-- MODAL IMPORT EXCEL -->
<div class="modal fade" id="modalImportDil" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= pg_e(pg_url('input_dil')) ?>" enctype="multipart/form-data" class="modal-content border-0 shadow rounded-4">
            <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
            <input type="hidden" name="aksi" value="import">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-excel text-success me-2"></i>Import DIL dari Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">File <strong>.xlsx</strong> (maks. 10 MB) hasil <strong>Monitoring DIL Detail</strong>, sheet pertama. Baris judul dicari otomatis, jadi kop laporan di atasnya boleh ada.</p>
                <p class="small text-muted mb-1">Kolom yang dibaca (berdasarkan judul kolom):</p>
                <div class="small font-monospace text-secondary mb-3">IDPEL, NAMA, ALAMAT, NO TLP, NO HP, TARIF, DAYA, JENIS MK, TEGUKUR, KDPEMBMETER, NOMORKWH, AKTIFASI, PRODUK PRABAYAR, UNITUP, UNITAP, UNITUPI</div>
                <ul class="small text-muted ps-3">
                    <li>Data dimuat ke tabel penampung <code>transit_dil</code>, dipindahkan ke DIL, lalu penampung dikosongkan.</li>
                    <li>Semua pelanggan yang diimpor otomatis berstatus <strong>AKTIF</strong>.</li>
                    <li>IdPel yang sudah ada dilewati, data lama tidak diubah.</li>
                    <li>IDPEL dan NAMA wajib ada di judul kolom.</li>
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

<!-- Loader saat Simpan / Import. Memakai window.showPageLoader (assets/js/loader.js); bila file aset itu
     tidak termuat (404 / cache lama), overlay cadangan di bawah tetap tampil. -->
<script>
(function () {
    function tampil(teks) {
        var ov = document.getElementById('page-loader');
        if (ov && typeof window.showPageLoader === 'function') {
            var t = ov.querySelector('.txt'); if (t && teks) t.textContent = teks;
            window.showPageLoader();
            ov.classList.add('show');                      // langsung, tanpa menunggu timer
            return;
        }
        if (document.getElementById('dil-loader-cadangan')) return;
        var d = document.createElement('div');
        d.id = 'dil-loader-cadangan';
        d.style.cssText = 'position:fixed;inset:0;z-index:99999;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(255,255,255,.8);font-weight:600;color:#0b253a';
        d.innerHTML = '<div style="width:56px;height:56px;border:5px solid #dbe7f5;border-top-color:#0d6efd;border-radius:50%;animation:dilspin .8s linear infinite"></div>'
                    + '<div style="margin-top:14px"></div><style>@keyframes dilspin{to{transform:rotate(360deg)}}</style>';
        d.children[1].textContent = teks || 'Memproses…';
        document.body.appendChild(d);
    }
    document.querySelectorAll('form').forEach(function (f) {
        var aksi = f.querySelector('input[name="aksi"]');
        if (!aksi || (aksi.value !== 'tambah' && aksi.value !== 'import')) return;
        f.addEventListener('submit', function (e) {
            if (e.defaultPrevented) return;
            tampil(aksi.value === 'import' ? 'Mengimpor data DIL, mohon tunggu…' : 'Menyimpan…');
        });
    });
    // kembali lewat tombol Back: pastikan overlay tidak tertinggal
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        var c = document.getElementById('dil-loader-cadangan'); if (c) c.remove();
        if (typeof window.hidePageLoader === 'function') window.hidePageLoader();
    });
})();
</script>