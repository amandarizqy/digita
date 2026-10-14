<?php
// templates/perencanaan/hasil_risiko.php  -  Monitoring: Hasil Klasifikasi Level Risiko
// Variabel dari modules/perencanaan/hasil_risiko.php
// Tambahan: $tingkat_akses ('UPI'|'AP'|'UP'|null), $nama_upi, $nama_ap, $nama_up, $sel_ap, $sel_up,
//           $opsi_ap, $opsi_up (kode => SingkatanNama), $scope_label

$total_terklasifikasi = $total_p_tinggi + $total_p_sedang + $total_p_rendah;

// Filter unit (hanya untuk tingkat UPI/AP) ikut terbawa di semua URL
$unit_params = [];
if ($tingkat_akses === 'UPI' || $tingkat_akses === 'AP') {
    $unit_params = ['unit_ap' => $tingkat_akses === 'UPI' ? $sel_ap : '', 'unit_up' => $sel_up];
}

$url_hasil = function (array $ubah = []) use ($keyword, $prioritas, $posisi_sr, $level_dampak, $tingkat, $unit_params) {
    return pr_url('hasil_risiko', array_merge([
        'keyword' => $keyword, 'prioritas' => $prioritas, 'posisi_sr' => $posisi_sr,
        'level_dampak' => $level_dampak, 'tingkat' => $tingkat,
    ], $unit_params, $ubah));
};

echo pr_header([
    'title'    => 'Hasil Klasifikasi Level Risiko',
    'subtitle' => 'Daftar pelanggan beserta kuadran, level kemungkinan, level dampak, dan Skala Prioritas hasil klasifikasi.',
    'action'   => 'hasil_risiko',
    'periode'  => true,
    'buttons'  => [
        ['label' => 'Klasifikasi Ulang', 'icon' => 'bi-lightning-charge-fill', 'href' => pr_url('proses_risiko')],
    ],
]);

if (!empty($pesan_error)) echo pr_alert('danger', pr_e($pesan_error));
?>

<!-- WILAYAH DATA: sesuai tingkat akses (UPI: filter AP+UP | AP: filter UP | UP: tanpa filter) -->
<?php if ($tingkat_akses !== null): ?>
<div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
    <div class="card-body px-4 py-3">
        <?php if ($tingkat_akses !== 'UP'): ?>
        <form method="GET" action="" id="formUnit" class="row g-3 align-items-end">
            <?= pr_hidden('hasil_risiko', ['keyword' => $keyword, 'prioritas' => $prioritas, 'posisi_sr' => $posisi_sr, 'level_dampak' => $level_dampak, 'tingkat' => $tingkat]) ?>
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
                <a href="<?= pr_e(pr_url('hasil_risiko')) ?>" class="btn btn-outline-secondary btn-sm px-3"><i class="bi bi-x-circle me-1"></i> Reset Wilayah</a>
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
    <?= pr_kpi('Terklasifikasi', pr_n($total_terklasifikasi), 'Pelanggan dengan Skala Prioritas', 'bi-people', 'primary') ?>
    <?= pr_kpi('Prioritas Tinggi', pr_n($total_p_tinggi), 'Skala 7-9 · penanganan segera · ' . pr_pct($total_p_tinggi, $total_terklasifikasi) . '%', 'bi-exclamation-octagon', 'danger', true) ?>
    <?= pr_kpi('Prioritas Sedang', pr_n($total_p_sedang), 'Skala 4-6 · pemantauan rutin · ' . pr_pct($total_p_sedang, $total_terklasifikasi) . '%', 'bi-exclamation-triangle', 'warning', true) ?>
    <?= pr_kpi('Prioritas Rendah', pr_n($total_p_rendah), 'Skala 1-3 · risiko terkendali · ' . pr_pct($total_p_rendah, $total_terklasifikasi) . '%', 'bi-shield-check', 'success', true) ?>
</div>

<?= pr_pills('TINGKAT:', [
    ['Semua',  $url_hasil(['tingkat' => '', 'hal' => '']),       $tingkat === '',        $total_terklasifikasi],
    ['Tinggi (7-9)', $url_hasil(['tingkat' => 'tinggi', 'hal' => '']), $tingkat === 'tinggi', $total_p_tinggi],
    ['Sedang (4-6)', $url_hasil(['tingkat' => 'sedang', 'hal' => '']), $tingkat === 'sedang', $total_p_sedang],
    ['Rendah (1-3)', $url_hasil(['tingkat' => 'rendah', 'hal' => '']), $tingkat === 'rendah', $total_p_rendah],
]) ?>

<!-- TABEL HASIL -->
<div class="card border-0 shadow-sm rounded-4 bg-white">
    <div class="card-header bg-white py-3 px-4 border-bottom">
        <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold text-primary fs-6">Daftar Pelanggan Berdasarkan Level Risiko</span>
            </div>
            <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-2">
                <?= pr_hidden('hasil_risiko', array_merge(['tingkat' => $tingkat], $unit_params)) ?>
                <div class="input-group input-group-sm" style="width:230px;">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="keyword" class="form-control bg-light border-start-0" value="<?= pr_e($keyword) ?>" placeholder="Cari IDPel / nama...">
                </div>
                <select name="prioritas" class="form-select form-select-sm bg-light" style="width:auto;" onchange="this.form.submit()" aria-label="Skala prioritas">
                    <option value="">Semua skala</option>
                    <?php for ($i = 9; $i >= 1; $i--): ?>
                        <option value="<?= $i ?>" <?= $prioritas === (string) $i ? 'selected' : '' ?>>Prioritas <?= $i ?> (<?= pr_n($per_skala[$i]) ?>)</option>
                    <?php endfor; ?>
                </select>
                <select name="posisi_sr" class="form-select form-select-sm bg-light" style="width:auto;" onchange="this.form.submit()" aria-label="Posisi SR">
                    <option value="">Semua posisi SR</option>
                    <option value="0" <?= $posisi_sr === '0' ? 'selected' : '' ?>>Mandiri</option>
                    <option value="1" <?= $posisi_sr === '1' ? 'selected' : '' ?>>Tergantung</option>
                </select>
                <select name="level_dampak" class="form-select form-select-sm bg-light" style="width:auto;" onchange="this.form.submit()" aria-label="Level dampak">
                    <option value="">Semua dampak</option>
                    <?php foreach (['SANGAT TINGGI', 'MODERAT', 'SANGAT RENDAH'] as $d): ?>
                        <option value="<?= $d ?>" <?= $level_dampak === $d ? 'selected' : '' ?>><?= ucfirst(strtolower($d)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($keyword !== '' || $prioritas !== '' || $posisi_sr !== '' || $level_dampak !== '' || $tingkat !== ''): ?>
                    <a href="<?= pr_e($url_hasil(['keyword' => '', 'prioritas' => '', 'posisi_sr' => '', 'level_dampak' => '', 'tingkat' => '', 'hal' => ''])) ?>" class="btn btn-outline-secondary btn-sm" title="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary text-uppercase pr-thead">
                    <tr>
                        <th class="ps-4" style="width:50px;">#</th>
                        <th>ID Pelanggan</th>
                        <th>Nama Pelanggan</th>
                        <th>Unit</th>
                        <th>Posisi SR</th>
                        <th>Kepentingan</th>
                        <th>Keterlambatan</th>
                        <th>Kemungkinan</th>
                        <th>Dampak</th>
                        <th class="text-center">Kuadran</th>
                        <th class="pe-4">Skala Prioritas</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($list_hasil)): $no = $offset + 1; foreach ($list_hasil as $row): ?>
                    <tr>
                        <td class="ps-4 text-muted small"><?= $no++ ?></td>
                        <td class="fw-bold text-dark font-monospace"><?= pr_e($row['Idpel']) ?></td>
                        <td class="fw-semibold text-dark text-uppercase small"><?= pr_e($row['NamaPelanggan']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= pr_e($row['NamaUnitUp'] ?? '-') ?></span></td>
                        <td><?= pr_badge_sr($row['PosisiSR']) ?></td>
                        <td><?= pr_badge_level($row['LevelKepentingan']) ?></td>
                        <td><?= pr_badge_level($row['LevelKeterlambatan']) ?></td>
                        <td><?= pr_badge_level($row['LevelKemungkinan']) ?></td>
                        <td><?= pr_badge_level($row['LevelDampak']) ?></td>
                        <td class="text-center"><span class="badge bg-light text-dark border font-monospace">K-<?= pr_e($row['Kuadran'] ?? '-') ?></span></td>
                        <td class="pe-4"><?= pr_badge_prioritas($row['SkalaPrioritas']) ?></td>
                    </tr>
                <?php endforeach; else:
                    echo pr_empty_row(11, $total_terklasifikasi === 0
                        ? 'Belum ada hasil klasifikasi. Jalankan <a href="' . pr_e(pr_url('proses_risiko')) . '">Klasifikasi Level Risiko</a> terlebih dahulu.'
                        : 'Tidak ada data yang cocok dengan filter yang dipilih.');
                endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?= pr_footer_tabel($hal, $limit, $total_rows, fn($h) => $url_hasil(['hal' => $h])) ?>
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