<?php
// templates/perencanaan/upload_riwayat.php  -  Input: Unggah Riwayat Pelunasan (XLSX)
// Variabel dari modules/perencanaan/upload_riwayat.php

echo pr_header([
    'title'    => 'Unggah Riwayat Pelunasan',
    'subtitle' => 'Unggah file Excel (.xlsx)',
    'action'   => 'upload_riwayat',
    'buttons'  => [['label' => 'Lihat Data Riwayat', 'icon' => 'bi-clock-history', 'href' => pr_url('data_riwayat'), 'class' => 'btn btn-outline-primary btn-sm px-3 rounded-2']],
]);

if ($pesan_sukses) echo pr_alert('success', pr_e($pesan_sukses) . ' <a href="' . pr_e(pr_url('data_riwayat')) . '" class="alert-link">Lihat datanya &rarr;</a>');
if ($pesan_error)  echo pr_alert('danger', pr_e($pesan_error));
if (!$unit_siap)   echo pr_alert('warning', 'Unit akun Anda belum lengkap, unggah dinonaktifkan. Lengkapi UnitUp / UnitAp / UnitUpi di Master Pengguna.');

if ($hasil): ?>
<div class="row g-3 mb-4">
    <?= pr_kpi('Baris Dibaca', pr_n($hasil['dibaca']), pr_e($hasil['nama']) . ' · ' . $hasil['durasi'] . ' detik', 'bi-file-earmark-spreadsheet', 'primary') ?>
    <?= pr_kpi('Tersimpan', pr_n($hasil['tersimpan']), 'Baru atau diperbarui', 'bi-check2-circle', 'success', true) ?>
    <?= pr_kpi('Duplikat di File', pr_n($hasil['duplikat']), 'IdPel + Bulan sama, dipakai baris terakhir', 'bi-files', 'warning', $hasil['duplikat'] > 0) ?>
    <?= pr_kpi('Ditolak', pr_n($hasil['ditolak']), 'Format data tidak valid', 'bi-x-octagon', 'danger', $hasil['ditolak'] > 0) ?>
</div>
<?php endif; ?>

<div class="mb-4">
    <!-- FORM UNGGAH -->
    <div>
        <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
            <div class="card-header bg-white py-3 px-4 border-bottom">
                <span class="fw-bold text-primary fs-6">Unggah File Excel</span>
            </div>
            <form method="POST" action="<?= pr_e(pr_url('upload_riwayat')) ?>" enctype="multipart/form-data" id="formUnggah">
                <div class="card-body px-4 py-4">
                    <label for="file_xlsx" class="form-label fw-bold small text-uppercase text-secondary" style="letter-spacing:.5px;">File .xlsx</label>
                    <input type="file" name="file_xlsx" id="file_xlsx" class="form-control form-control-lg" accept=".xlsx" required <?= $unit_siap ? '' : 'disabled' ?>>
                    <div class="form-text">Sheet pertama dibaca. Batas server: <?= pr_e(ini_get('upload_max_filesize')) ?> per file.</div>
                </div>
                <div class="card-footer bg-white border-top py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="text-muted small"><i class="bi bi-arrow-repeat me-1"></i> IdPel + Bulan yang sudah ada akan diperbarui, bukan digandakan.</span>
                    <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm rounded-2" id="btnUnggah" <?= $unit_siap ? '' : 'disabled' ?>>
                        <i class="bi bi-cloud-arrow-up me-1"></i> Unggah &amp; Proses
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (!empty($ditolak)): ?>
<div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
        <span class="fw-bold text-primary fs-6">Baris Ditolak</span>
        <span class="text-muted small">Menampilkan <?= count($ditolak) ?> dari <?= pr_n($hasil['ditolak'] ?? count($ditolak)) ?> baris</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-secondary text-uppercase pr-thead"><tr><th class="ps-4" style="width:110px;">Baris Excel</th><th>Nilai Idpel</th><th class="pe-4">Alasan</th></tr></thead>
            <tbody>
                <?php foreach ($ditolak as [$no, $idp, $alasan]): ?>
                <tr>
                    <td class="ps-4 font-monospace"><?= (int) $no ?></td>
                    <td class="font-monospace small"><?= pr_e($idp ?: '(kosong)') ?></td>
                    <td class="pe-4"><?= pr_badge($alasan, 'danger', 'bi-x-circle') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- CONTOH FORMAT EXCEL -->
<?php
$contoh_kolom = ['No', 'TglBuku', 'Idpel', 'TglTransaksi', 'Trf', 'Daya', 'Bulan', 'Kelp', 'KDPP', 'RpPTL', 'RpBPJU', 'RpPPN', 'RpMaterai', 'RpLain2', 'RpTagihan', 'RpBK', 'Petugas', 'Kode'];
$contoh_wajib = ['Idpel', 'TglTransaksi', 'Bulan', 'RpTagihan', 'RpBK'];
$contoh_baris = [
    [1, 20251101, 546000000011, 20251101, 'R1',  1300, 202511, 1, '014000091001',   75124,  3005, 0, 0, 0,   78129, 0, 'DANAMON', 'U'],
    [2, 20251101, 546000000028, 20251101, 'R1',  2200, 202511, 1, '014000091002',  996583, 39863, 0, 0, 0, 1036446, 0, 'BUKOPIN', 'U'],
    [3, 20251101, 546000000036, 20251101, 'R1M',  900, 202511, 3, '014000091003',  188726,  5662, 0, 0, 0,  194388, 0, 'BCA',     'U'],
];
?>
<div class="card border-0 shadow-sm rounded-4 bg-white">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-bold text-primary fs-6"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Contoh Format Excel</span>
        <span class="text-muted small">Judul kolom di baris pertama · sheet pertama · .xlsx · <i class="bi bi-arrow-left-right"></i> geser tabel untuk melihat semua kolom</span>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle mb-0 small text-nowrap">
            <thead>
                <tr>
                    <?php foreach ($contoh_kolom as $k): $wajib = in_array($k, $contoh_wajib, true); ?>
                        <th class="px-2 py-2 <?= $wajib ? 'bg-primary bg-opacity-10 text-primary' : 'table-light text-secondary' ?>"><?= pr_e($k) ?><?= $wajib ? ' *' : '' ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contoh_baris as $b): ?>
                <tr>
                    <?php foreach ($b as $i => $v): $wajib = in_array($contoh_kolom[$i], $contoh_wajib, true); ?>
                        <td class="px-2 font-monospace <?= is_int($v) ? 'text-end' : '' ?> <?= $wajib ? 'bg-primary bg-opacity-10 fw-semibold' : 'text-muted' ?>"><?= pr_e((string) $v) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-body px-4 py-3 border-top">
        <div class="row g-3 small">
            <div class="col-md-6">
                <div class="fw-bold text-primary mb-1">* Kolom yang dibaca</div>
                <ul class="mb-0 ps-3 text-muted">
                    <li><strong>Idpel</strong>: 12 digit angka (format ilmiah seperti 5,46E+11 tetap terbaca)</li>
                    <li><strong>Bulan</strong>: YYYYMM, contoh <span class="font-monospace">202511</span></li>
                    <li><strong>TglTransaksi</strong>: YYYYMMDD, contoh <span class="font-monospace">20251101</span></li>
                    <li><strong>RpTagihan</strong> dan <strong>RpBK</strong>: angka</li>
                </ul>
            </div>
            <div class="col-md-6">
                <div class="fw-bold text-primary mb-1">Catatan</div>
                <ul class="mb-0 ps-3 text-muted">
                    <li>Kolom lain (No, TglBuku, Trf, Daya, dst.) boleh ada, akan diabaikan.</li>
                    <li>Huruf besar/kecil pada judul kolom tidak berpengaruh.</li>
                    <li>UnitUp, UnitAp, dan UnitUpi otomatis dari akun yang login.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('formUnggah').addEventListener('submit', function () {
    var b = document.getElementById('btnUnggah');
    b.disabled = true;
    b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';
});
</script>