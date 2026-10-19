<?php
// templates/penggunaan/input_dil.php  -  Input DIL
// Variabel dari modules/penggunaan/input_dil.php:
//   $kpi, $csrf, $boleh_monitor, $pesan_sukses, $pesan_error, $detail_import

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
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-3"><i class="bi bi-person-plus me-2 text-primary"></i>Tambah Pelanggan</h6>
        <form method="POST" action="<?= pg_e(pg_url('input_dil')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
            <input type="hidden" name="aksi" value="tambah">
            <div class="col-md-3">
                <label for="idpel" class="form-label small fw-bold text-secondary text-uppercase">IdPel</label>
                <input type="text" class="form-control font-monospace" id="idpel" name="idpel" maxlength="12" minlength="12"
                       pattern="[A-Za-z0-9]{12}" placeholder="12 karakter" required>
            </div>
            <div class="col-md-5">
                <label for="nama" class="form-label small fw-bold text-secondary text-uppercase">Nama Pelanggan</label>
                <input type="text" class="form-control" id="nama" name="nama" maxlength="50" placeholder="Maks. 50 karakter" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary text-uppercase">Status</label>
                <div><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-50 px-3 py-2">AKTIF (otomatis)</span></div>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg me-1"></i> Simpan</button>
            </div>
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
                <p class="small text-muted mb-2">File <strong>.xlsx</strong> (maks. 10 MB), sheet pertama, baris pertama boleh berupa judul:</p>
                <table class="table table-sm table-bordered small text-center mb-3" style="max-width:320px;">
                    <thead class="table-light"><tr><th>A</th><th>B</th></tr></thead>
                    <tbody><tr><td>IdPel</td><td>Nama Pelanggan</td></tr><tr><td>123456789012</td><td>BUDI SANTOSO</td></tr></tbody>
                </table>
                <ul class="small text-muted ps-3">
                    <li>Semua pelanggan yang diimpor otomatis berstatus <strong>AKTIF</strong>.</li>
                    <li>IdPel yang sudah ada dilewati, data lama tidak diubah.</li>
                    <li>IdPel angka kurang dari 12 digit dilengkapi nol di depan.</li>
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