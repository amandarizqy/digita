<?php
// templates/perencanaan/hasil_kepentingan.php  -  Monitoring: Hasil Tingkat Kepentingan
// Variabel dari modules/perencanaan/hasil_kepentingan.php
// Tambahan: $tingkat_akses ('UPI'|'AP'|'UP'|null), $nama_upi, $nama_ap, $nama_up, $sel_ap, $sel_up,
//           $opsi_ap, $opsi_up (kode => SingkatanNama), $scope_label

$total_terisi = array_sum($per_level);

// Filter unit (hanya untuk tingkat UPI/AP) ikut terbawa di semua URL
$unit_params = [];
if ($tingkat_akses === 'UPI' || $tingkat_akses === 'AP') {
    $unit_params = ['unit_ap' => $tingkat_akses === 'UPI' ? $sel_ap : '', 'unit_up' => $sel_up];
}

$url_kep = function (array $ubah = []) use ($keyword, $level, $limit, $unit_params) {
    return pr_url('hasil_kepentingan', array_merge(['keyword' => $keyword, 'level' => $level, 'limit' => $limit == 10 ? '' : $limit], $unit_params, $ubah));
};

echo pr_header([
    'title'    => 'Hasil Tingkat Kepentingan',
    'subtitle' => 'Seberapa penting tiap pelanggan secara bisnis. Menjadi sumbu Kepentingan pada matriks kemungkinan.',
    'action'   => 'hasil_kepentingan',
    'periode'  => true,
    'buttons'  => [['label' => 'Input Tingkat Kepentingan', 'icon' => 'bi-plus-lg', 'href' => pr_url('input_kepentingan')]],
]);

if (!empty($pesan_error)) echo pr_alert('danger', pr_e($pesan_error));
?>

<!-- WILAYAH DATA: sesuai tingkat akses (UPI: filter AP+UP | AP: filter UP | UP: tanpa filter) -->
<?php if ($tingkat_akses !== null): ?>
<div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
    <div class="card-body px-4 py-3">
        <?php if ($tingkat_akses !== 'UP'): ?>
        <form method="GET" action="" id="formUnit" class="row g-3 align-items-end">
            <?= pr_hidden('hasil_kepentingan', ['keyword' => $keyword, 'level' => $level, 'limit' => $limit == 10 ? '' : $limit]) ?>
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
                <a href="<?= pr_e(pr_url('hasil_kepentingan')) ?>" class="btn btn-outline-secondary btn-sm px-3"><i class="bi bi-x-circle me-1"></i> Reset Wilayah</a>
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
    <?= pr_kpi('Sudah Diisi', pr_n($total_terisi) . ' <span class="fs-6 fw-semibold text-muted">/ ' . pr_n($total_dil) . '</span>', pr_pct($total_terisi, $total_dil) . '% pelanggan DIL', 'bi-bar-chart-line', 'primary') ?>
    <?= pr_kpi('Tinggi', pr_n($per_level['TINGGI']), pr_pct($per_level['TINGGI'], $total_terisi) . '% dari yang terisi', 'bi-exclamation-octagon', 'danger', true) ?>
    <?= pr_kpi('Moderat', pr_n($per_level['MODERAT']), pr_pct($per_level['MODERAT'], $total_terisi) . '% dari yang terisi', 'bi-exclamation-triangle', 'warning', true) ?>
    <?= pr_kpi('Rendah', pr_n($per_level['RENDAH']), pr_pct($per_level['RENDAH'], $total_terisi) . '% dari yang terisi', 'bi-shield-check', 'success', true) ?>
</div>

<?= pr_pills('LEVEL:', [
    ['Semua',   $url_kep(['level' => '', 'hal' => '']),        $level === '',        $total_terisi],
    ['Tinggi',  $url_kep(['level' => 'TINGGI', 'hal' => '']),  $level === 'TINGGI',  $per_level['TINGGI']],
    ['Moderat', $url_kep(['level' => 'MODERAT', 'hal' => '']), $level === 'MODERAT', $per_level['MODERAT']],
    ['Rendah',  $url_kep(['level' => 'RENDAH', 'hal' => '']),  $level === 'RENDAH',  $per_level['RENDAH']],
]) ?>

<!-- TABEL -->
<div class="card border-0 shadow-sm rounded-4 bg-white">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="fw-bold text-primary fs-6">Daftar Tingkat Kepentingan Pelanggan</span>
        </div>
        <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-2">
            <?= pr_hidden('hasil_kepentingan', array_merge(['level' => $level], $unit_params)) ?>
            <div class="input-group input-group-sm" style="width:250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="keyword" class="form-control bg-light border-start-0" value="<?= pr_e($keyword) ?>" placeholder="Cari IDPel / nama...">
            </div>
            <select name="limit" class="form-select form-select-sm bg-light" style="width:auto;" onchange="this.form.submit()" aria-label="Jumlah baris">
                <?php foreach ([10, 25, 50, 100] as $n): ?>
                    <option value="<?= $n ?>" <?= $limit === $n ? 'selected' : '' ?>><?= $n ?> baris</option>
                <?php endforeach; ?>
            </select>
            <?php if ($keyword !== '' || $level !== ''): ?>
                <a href="<?= pr_e($url_kep(['keyword' => '', 'level' => '', 'hal' => ''])) ?>" class="btn btn-outline-secondary btn-sm" title="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></a>
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
                        <th>Kepentingan</th>
                        <th>Posisi SR</th>
                        <th>Unit</th>
                        <th class="pe-4">Skala Prioritas</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($list_hasil)): $no = $offset + 1; foreach ($list_hasil as $row): ?>
                    <tr>
                        <td class="ps-4 text-muted small"><?= $no++ ?></td>
                        <td class="fw-bold text-dark font-monospace"><?= pr_e($row['IdPel']) ?></td>
                        <td class="fw-semibold text-dark text-uppercase small"><?= pr_e($row['NamaPelanggan'] ?? '— (tidak ada di DIL)') ?></td>
                        <td><?= pr_badge_level($row['LevelKepentingan']) ?></td>
                        <td><?= pr_badge_sr($row['PosisiSR']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= pr_e($row['NamaUnitUp'] ?? '-') ?></span></td>
                        <td class="pe-4"><?= (int) $row['SkalaPrioritas'] > 0 ? pr_badge_prioritas($row['SkalaPrioritas']) : pr_badge('BELUM DIPROSES', 'secondary', 'bi-hourglass-split') ?></td>
                    </tr>
                <?php endforeach; else:
                    echo pr_empty_row(7, $total_terisi === 0 ? 'Belum ada Tingkat Kepentingan yang diisi.' : 'Tidak ada data yang cocok dengan filter ini.', 'bi-bar-chart-line');
                endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?= pr_footer_tabel($hal, $limit, $total_rows, fn($h) => $url_kep(['hal' => $h])) ?>
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