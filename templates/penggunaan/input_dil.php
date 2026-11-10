<?php
// templates/penggunaan/input_dil.php  -  Input DIL
// Variabel dari modules/penggunaan/input_dil.php:
//   $csrf, $unit_awal, $form_lama, $boleh_monitor, $pesan_sukses, $pesan_error, $detail_import, konstanta JENIS_SHUNTRIP
// Form tambah pelanggan tampil sebagai pop-up bertab (Identitas -> Layanan & Meter -> Unit), jadi tidak perlu scroll halaman.

$lama = $form_lama ?? [];   // isian terakhir bila penyimpanan gagal -> pop-up dibuka lagi otomatis
unset($lama['unitupi']);     // Unit UPI terkunci: selalu memakai UPI akun, bukan isian lama

echo pg_header([
    'title'    => 'Input DIL',
    'subtitle' => 'Tambah pelanggan ke Daftar Induk Langganan, secara manual atau lewat import Excel.',
    'action'   => 'input_dil',
    'buttons'  => array_values(array_filter([
        $boleh_monitor ? ['label' => 'Monitoring DIL', 'icon' => 'bi-display', 'href' => pg_url('monitor_dil'),
                          'class' => 'btn btn-outline-primary btn-sm px-3 shadow-sm rounded-2 bg-white'] : null,
        // ['label' => 'Tambah Pelanggan', 'icon' => 'bi-person-plus',
        //  'class' => 'btn btn-primary btn-sm px-3 shadow-sm rounded-2',
        //  'attrs' => 'data-bs-toggle="modal" data-bs-target="#modalTambahDil"'],
        // ['label' => 'Import Excel', 'icon' => 'bi-file-earmark-excel',
        //  'class' => 'btn btn-success btn-sm px-3 shadow-sm rounded-2',
        //  'attrs' => 'data-bs-toggle="modal" data-bs-target="#modalImportDil"'],
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

// ---- pembantu isian form ----
$val = fn(string $k, string $def = '') => $lama[$k] ?? $def;
$fld = function (string $name, string $label, int $max, string $col = 'col-md-6', string $extra = '', string $def = '') use ($val) {
    $id = 'f_' . $name;
    return '<div class="' . $col . '"><label for="' . $id . '" class="form-label small fw-bold text-secondary text-uppercase">' . pg_e($label) . '</label>'
         . '<input type="text" class="form-control" id="' . $id . '" name="' . $name . '" maxlength="' . $max . '" value="' . pg_e($val($name, $def)) . '" ' . $extra . '></div>';
};
$opsi = function (string $name, string $label, array $pilihan, string $col = 'col-md-6') use ($val) {
    $id = 'f_' . $name; $h = '<div class="' . $col . '"><label for="' . $id . '" class="form-label small fw-bold text-secondary text-uppercase">' . pg_e($label) . '</label>'
        . '<select class="form-select" id="' . $id . '" name="' . $name . '"><option value="">- pilih -</option>';
    foreach ($pilihan as $p) $h .= '<option value="' . pg_e($p) . '"' . ($val($name) === $p ? ' selected' : '') . '>' . pg_e($p) . '</option>';
    return $h . '</select></div>';
};
?>

<!-- PINTASAN -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <button type="button" class="card border-0 shadow-sm rounded-4 p-4 w-100 text-start bg-white" data-bs-toggle="modal" data-bs-target="#modalTambahDil">
            <div class="d-flex align-items-center gap-3">
                <span class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center" style="width:52px;height:52px;"><i class="bi bi-person-plus fs-4"></i></span>
                <span><span class="d-block fw-bold text-dark">Tambah Pelanggan</span><span class="small text-muted">Isi satu pelanggan lewat form pop-up (status otomatis AKTIF).</span></span>
            </div>
        </button>
    </div>
    <div class="col-md-6">
        <button type="button" class="card border-0 shadow-sm rounded-4 p-4 w-100 text-start bg-white" data-bs-toggle="modal" data-bs-target="#modalImportDil">
            <div class="d-flex align-items-center gap-3">
                <span class="rounded-circle bg-success bg-opacity-10 text-success d-inline-flex align-items-center justify-content-center" style="width:52px;height:52px;"><i class="bi bi-file-earmark-excel fs-4"></i></span>
                <span><span class="d-block fw-bold text-dark">Import Excel</span><span class="small text-muted">Muat banyak pelanggan sekaligus dari file Monitoring DIL Detail.</span></span>
            </div>
        </button>
    </div>
</div>

<!-- POP-UP: TAMBAH PELANGGAN -->
<div class="modal fade" id="modalTambahDil" tabindex="-1" aria-labelledby="judulTambahDil" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form method="POST" action="<?= pg_e(pg_url('input_dil')) ?>" id="formTambahDil" class="modal-content border-0 shadow rounded-4">
            <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
            <input type="hidden" name="aksi" value="tambah">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bold" id="judulTambahDil"><i class="bi bi-person-plus text-primary me-2"></i>Tambah Pelanggan</h5>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-50 mt-1">Status AKTIF (otomatis)</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body pt-3">
                <ul class="nav nav-pills nav-fill gap-2 mb-4" id="tabDil" role="tablist">
                    <li class="nav-item"><button type="button" class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-identitas" role="tab"><i class="bi bi-person-vcard me-1"></i>1. Identitas</button></li>
                    <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-layanan" role="tab"><i class="bi bi-lightning-charge me-1"></i>2. Layanan &amp; Meter</button></li>
                    <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-unit" role="tab"><i class="bi bi-building me-1"></i>3. Unit</button></li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-identitas" role="tabpanel">
                        <div class="row g-3">
                            <?= $fld('idpel', 'IdPel *', 12, 'col-md-5', 'minlength="12" pattern="[A-Za-z0-9]{12}" placeholder="12 karakter" required style="font-family:monospace"') ?>
                            <?= $fld('nama', 'Nama *', 50, 'col-md-7', 'placeholder="Maks. 50 karakter" required') ?>
                            <?= $fld('nomortlp', 'No. Telepon', 20, 'col-md-6', 'inputmode="tel"') ?>
                            <?= $fld('nomorhp', 'No. HP', 20, 'col-md-6', 'inputmode="tel"') ?>
                            <?= $fld('alamat', 'Alamat', 100, 'col-12', 'placeholder="Maks. 100 karakter"') ?>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-layanan" role="tabpanel">
                        <div class="row g-3">
                            <?= $fld('goltar', 'Gol. Tarif', 5, 'col-md-4', 'placeholder="mis. R1M"') ?>
                            <?= $fld('daya', 'Daya (VA)', 9, 'col-md-4', 'inputmode="numeric" pattern="[0-9]*" placeholder="mis. 900"') ?>
                            <?= $opsi('tegukur', 'Tegangan Ukur', ['TR', 'TM', 'TT'], 'col-md-4') ?>
                            <?= $fld('jenismk', 'Jenis MK', 10, 'col-md-4', 'placeholder="mis. N"') ?>
                            <?= $fld('kdpembmeter', 'Kd. Pemb. Meter', 1, 'col-md-4', 'placeholder="A/M/P/L/E"') ?>
                            <?= $fld('nomormeter', 'Nomor Meter', 15, 'col-md-4', 'inputmode="numeric"') ?>
                            <?= $opsi('produkprabayar', 'Produk Prabayar', ['Prabayar Satu Arah', 'Prabayar Dua Arah - NFC', 'Prabayar Dua Arah - Modem'], 'col-md-5') ?>
                            <?= $opsi('aktifasi', 'Aktifasi', ['NON AKTIF (N,O)', 'AKTIF'], 'col-md-3') ?>
                            <?= $opsi('jenisshuntrip', 'Jenis Shuntrip', JENIS_SHUNTRIP, 'col-md-4') ?>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-unit" role="tabpanel">
                        <div class="row g-3">
                            <?= $fld('unitupi', 'Unit UPI (terkunci)', 2, 'col-md-4', 'readonly tabindex="-1" style="background-color:#e9ecef;cursor:not-allowed" title="Terkunci sesuai UPI akun Anda"', $unit_awal['UnitUpi']) ?>
                            <?= $fld('unitap', 'Unit AP', 5, 'col-md-4', '', $unit_awal['UnitAp']) ?>
                            <?= $fld('unitup', 'Unit UP', 5, 'col-md-4', '', $unit_awal['UnitUp']) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0 justify-content-between">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="reset" class="btn btn-light btn-sm px-3" id="btnKosongkanDil">Kosongkan</button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" id="btnSebelumnya"><i class="bi bi-chevron-left"></i> Sebelumnya</button>
                    <button type="button" class="btn btn-outline-primary btn-sm px-3" id="btnBerikutnya">Berikutnya <i class="bi bi-chevron-right"></i></button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnSimpanDil"><i class="bi bi-save me-1"></i> Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- POP-UP: IMPORT EXCEL -->
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
                <div class="small font-monospace text-secondary mb-3">IDPEL, NAMA, ALAMAT, NO TLP, NO HP, TARIF, DAYA, JENIS MK, TEGUKUR, KDPEMBMETER, NOMORKWH (disimpan ke Nomor Meter), AKTIFASI, PRODUK PRABAYAR, UNITUP, UNITAP, UNITUPI</div>
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

<!-- Navigasi tab pop-up tambah: Sebelumnya / Berikutnya, lompat ke tab yang berisi isian tidak valid, buka otomatis bila ada isian lama -->
<script>
(function () {
    var form = document.getElementById('formTambahDil'); if (!form) return;
    var tabs = Array.prototype.slice.call(form.querySelectorAll('#tabDil [data-bs-target]'));
    var btnPrev = document.getElementById('btnSebelumnya'), btnNext = document.getElementById('btnBerikutnya'), btnSimpan = document.getElementById('btnSimpanDil');
    function aktif() { return tabs.findIndex(function (t) { return t.classList.contains('active'); }); }
    function tampilkan(i) { if (i >= 0 && i < tabs.length) bootstrap.Tab.getOrCreateInstance(tabs[i]).show(); }
    function atur() {
        var i = aktif();
        btnPrev.classList.toggle('d-none', i <= 0);
        btnNext.classList.toggle('d-none', i >= tabs.length - 1);
    }
    tabs.forEach(function (t) { t.addEventListener('shown.bs.tab', atur); });
    btnPrev.addEventListener('click', function () { tampilkan(aktif() - 1); });
    btnNext.addEventListener('click', function () { tampilkan(aktif() + 1); });
    // isian wajib/format yang salah bisa berada di tab yang sedang tersembunyi: pindah ke tab itu lalu tampilkan pesannya
    btnSimpan.addEventListener('click', function (e) {
        if (form.checkValidity()) return;
        e.preventDefault();
        var salah = form.querySelector(':invalid'), pane = salah && salah.closest('.tab-pane');
        var idx = tabs.findIndex(function (t) { return pane && t.getAttribute('data-bs-target') === '#' + pane.id; });
        if (idx >= 0 && idx !== aktif()) {
            tabs[idx].addEventListener('shown.bs.tab', function once() { tabs[idx].removeEventListener('shown.bs.tab', once); form.reportValidity(); });
            tampilkan(idx);
        } else { form.reportValidity(); }
    });
    // "Kosongkan": kembali ke tab pertama
    document.getElementById('btnKosongkanDil').addEventListener('click', function () { setTimeout(function () { tampilkan(0); }, 0); });
    atur();
    <?php if (!empty($lama)): ?>
    document.addEventListener('DOMContentLoaded', function () { bootstrap.Modal.getOrCreateInstance(document.getElementById('modalTambahDil')).show(); });
    <?php endif; ?>
})();
</script>

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