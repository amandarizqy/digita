<?php
// templates/perencanaan/hasil_survey.php  -  Monitoring: Hasil Survey SR
// Variabel dari modules/perencanaan/hasil_survey.php
// Tambahan: $tingkat_akses ('UPI'|'AP'|'UP'|null), $nama_upi, $nama_ap, $nama_up, $sel_ap, $sel_up,
//           $opsi_ap, $opsi_up (kode => SingkatanNama), $scope_label

// Filter unit (hanya untuk tingkat UPI/AP) ikut terbawa di semua URL
$unit_params = [];
if ($tingkat_akses === 'UPI' || $tingkat_akses === 'AP') {
    $unit_params = ['unit_ap' => $tingkat_akses === 'UPI' ? $sel_ap : '', 'unit_up' => $sel_up];
}

$url_survey = function (array $ubah = []) use ($keyword, $posisi_sr, $limit, $unit_params) {
    return pr_url('hasil_survey', array_merge(['keyword' => $keyword, 'posisi_sr' => $posisi_sr, 'limit' => $limit == 10 ? '' : $limit], $unit_params, $ubah));
};

echo pr_header([
    'title'    => 'Hasil Survey SR',
    'subtitle' => 'Posisi SR tiap pelanggan: mandiri, atau saling tergantung dengan pelanggan lain pada lokasi yang sama.',
    'action'   => 'hasil_survey',
    'periode'  => true,
    'buttons'  => [['label' => 'Input Survey Baru', 'icon' => 'bi-plus-lg', 'href' => pr_url('input_survey')]],
]);

if (!empty($pesan_sukses)) echo pr_alert('success', $pesan_sukses);
if (!empty($pesan_error))  echo pr_alert('danger', $pesan_error);
?>

<!-- WILAYAH DATA: sesuai tingkat akses (UPI: filter AP+UP | AP: filter UP | UP: tanpa filter) -->
<?php if ($tingkat_akses !== null): ?>
<div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
    <div class="card-body px-4 py-3">
        <?php if ($tingkat_akses !== 'UP'): ?>
        <form method="GET" action="" id="formUnit" class="row g-3 align-items-end">
            <?= pr_hidden('hasil_survey', ['keyword' => $keyword, 'posisi_sr' => $posisi_sr, 'limit' => $limit == 10 ? '' : $limit]) ?>
            <div class="col-12 col-md-3">
                <label class="form-label fw-bold small text-uppercase text-secondary mb-1" style="letter-spacing:.5px;"><i class="bi bi-diagram-3 me-1"></i> Unit UPI</label>
                <input type="text" class="form-control form-control-sm bg-light" value="<?= pr_e($nama_upi ?: '-') ?>" readonly>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label fw-bold small text-uppercase text-secondary mb-1" for="selUnitAp" style="letter-spacing:.5px;"><i class="bi bi-building me-1"></i> Unit AP</label>
                <?php if ($tingkat_akses === 'UPI'): ?>
                    <select name="unit_ap" id="selUnitAp" class="form-select form-select-sm">
                        <option value="">— Semua AP —</option>
                        <?php foreach ($opsi_ap as $kode_ap => $nama_ap_opt): ?>
                            <option value="<?= pr_e((string) $kode_ap) ?>" <?= (string) $kode_ap === (string) $sel_ap ? 'selected' : '' ?>><?= pr_e($nama_ap_opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="text" class="form-control form-control-sm bg-light" value="<?= pr_e($nama_ap ?: '-') ?>" readonly>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label fw-bold small text-uppercase text-secondary mb-1" for="selUnitUp" style="letter-spacing:.5px;"><i class="bi bi-geo-alt me-1"></i> Unit UP</label>
                <select name="unit_up" id="selUnitUp" class="form-select form-select-sm" <?= ($tingkat_akses === 'UPI' && $sel_ap === '') ? 'disabled' : '' ?>>
                    <option value=""><?= ($tingkat_akses === 'UPI' && $sel_ap === '') ? '— Pilih AP dahulu —' : '— Semua UP —' ?></option>
                    <?php foreach ($opsi_up as $kode_up => $nama_up_opt): ?>
                        <option value="<?= pr_e((string) $kode_up) ?>" <?= (string) $kode_up === (string) $sel_up ? 'selected' : '' ?>><?= pr_e($nama_up_opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <a href="<?= pr_e(pr_url('hasil_survey')) ?>" class="btn btn-outline-secondary btn-sm px-3"><i class="bi bi-x-circle me-1"></i> Reset Wilayah</a>
            </div>
        </form>
        <?php endif; ?>
        <div class="small text-muted <?= $tingkat_akses !== 'UP' ? 'mt-3' : '' ?>">
            <i class="bi bi-geo-alt-fill me-1"></i>
            <?php if ($tingkat_akses === 'UP'): ?>
                Menampilkan data unit Anda: <strong><?= pr_e($scope_label) ?></strong>.
            <?php else: ?>
                Wilayah data: <strong><?= pr_e($scope_label) ?></strong>.
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- KARTU METRIK KPI -->
<div class="row g-3 mb-4">
    <?= pr_kpi('Total Tersurvey', pr_n($total_tersurvey) . ' <span class="fs-6 fw-semibold text-muted">/ ' . pr_n($total_dil) . '</span>', pr_pct($total_tersurvey, $total_dil) . '% pelanggan DIL sudah punya Posisi SR', 'bi-clipboard-data', 'primary') ?>
    <?= pr_kpi('Mandiri', pr_n($total_mandiri), 'Posisi SR 0 · tidak tergantung pelanggan lain', 'bi-dash-circle', 'secondary', true) ?>
    <?= pr_kpi('Tergantung', pr_n($total_tergantung), 'Posisi SR 1 · terhubung pelanggan lain', 'bi-diagram-2', 'info', true) ?>
    <?= pr_kpi('Belum Tersurvey', pr_n(max(0, $total_dil - $total_tersurvey)), 'Isi lewat Input Survey SR', 'bi-hourglass-split', 'warning') ?>
</div>

<?= pr_pills('POSISI SR:', [
    ['Semua',      $url_survey(['posisi_sr' => '', 'hal' => '']),  $posisi_sr === '',  $total_tersurvey],
    ['Mandiri',    $url_survey(['posisi_sr' => '0', 'hal' => '']), $posisi_sr === '0', $total_mandiri],
    ['Tergantung', $url_survey(['posisi_sr' => '1', 'hal' => '']), $posisi_sr === '1', $total_tergantung],
]) ?>

<!-- TABEL -->
<div class="card border-0 shadow-sm rounded-4 bg-white">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="fw-bold text-primary fs-6">Daftar Hasil Survey SR</span>
        </div>
        <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-2">
            <?= pr_hidden('hasil_survey', array_merge(['posisi_sr' => $posisi_sr], $unit_params)) ?>
            <div class="input-group input-group-sm" style="width:260px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="keyword" class="form-control bg-light border-start-0" value="<?= pr_e($keyword) ?>" placeholder="Cari IDPel / nama / UP...">
            </div>
            <select name="limit" class="form-select form-select-sm bg-light" style="width:auto;" onchange="this.form.submit()" aria-label="Jumlah baris">
                <?php foreach ([10, 25, 50, 100] as $n): ?>
                    <option value="<?= $n ?>" <?= $limit === $n ? 'selected' : '' ?>><?= $n ?> baris</option>
                <?php endforeach; ?>
            </select>
            <?php if ($keyword !== '' || $posisi_sr !== ''): ?>
                <a href="<?= pr_e($url_survey(['keyword' => '', 'posisi_sr' => '', 'hal' => ''])) ?>" class="btn btn-outline-secondary btn-sm" title="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></a>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary text-uppercase pr-thead">
                    <tr>
                        <th class="ps-4" style="width:50px;">#</th>
                        <th>ID Pelanggan</th>
                        <th>Nama Pelanggan</th>
                        <th>Posisi SR</th>
                        <th>Unit (UP / AP / UPI)</th>
                        <th class="text-end pe-4" style="width:90px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($data_hasil)): $no = $offset + 1; foreach ($data_hasil as $row): ?>
                    <tr>
                        <td class="ps-4 text-muted small"><?= $no++ ?></td>
                        <td class="fw-bold text-dark font-monospace"><?= pr_e($row['Idpel']) ?></td>
                        <td class="fw-semibold text-dark text-uppercase small"><?= pr_e($row['NamaPelanggan'] ?? '— (tidak ada di DIL)') ?></td>
                        <td><?= pr_badge_sr($row['PosisiSR']) ?></td>
                        <td class="small text-muted">
                            <span class="badge bg-light text-dark border"><?= pr_e($row['NamaUnitUp'] ?? '-') ?></span>
                            <span class="badge bg-light text-dark border"><?= pr_e($row['NamaUnitAp'] ?? '-') ?></span>
                            <span class="badge bg-light text-secondary border"><?= pr_e($row['NamaUnitUpi'] ?? '-') ?></span>
                        </td>
                        <td class="text-end pe-4">
                            <a href="<?= pr_e($url_survey(['act' => 'delete', 'idpel' => $row['Idpel'], 'hal' => $page])) ?>"
                               class="btn btn-outline-danger btn-sm py-1 px-2" title="Hapus data"
                               onclick="return confirm(<?= pr_e(json_encode('Hapus SELURUH baris kategorisasi IdPel ' . $row['Idpel'] . '? Ini juga menghapus Tingkat Kepentingan dan hasil klasifikasi risikonya.')) ?>);">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; else: echo pr_empty_row(6, 'Data hasil survey SR tidak ditemukan.', 'bi-clipboard-data'); endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?= pr_footer_tabel($page, $limit, $total_data, fn($h) => $url_survey(['hal' => $h])) ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var f = document.getElementById('formUnit');
    if (!f) return;
    var ap = document.getElementById('selUnitAp'), up = document.getElementById('selUnitUp');
    if (ap) ap.addEventListener('change', function () { if (up) up.value = ''; f.submit(); });
    if (up) up.addEventListener('change', function () { f.submit(); });
});
</script>