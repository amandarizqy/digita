<?php
// templates/perencanaan/hasil_kepentingan.php  -  Monitoring: Hasil Tingkat Kepentingan
// Variabel dari modules/perencanaan/hasil_kepentingan.php

$total_terisi = array_sum($per_level);

$url_kep = function (array $ubah = []) use ($keyword, $level, $limit) {
    return pr_url('hasil_kepentingan', array_merge(['keyword' => $keyword, 'level' => $level, 'limit' => $limit == 10 ? '' : $limit], $ubah));
};

echo pr_header([
    'title'    => 'Hasil Tingkat Kepentingan',
    'subtitle' => 'Seberapa penting tiap pelanggan secara bisnis. Menjadi sumbu Kepentingan pada matriks kemungkinan.',
    'action'   => 'hasil_kepentingan',
    'badge'    => 'Periode ' . $periode,
    'buttons'  => [['label' => 'Input Tingkat Kepentingan', 'icon' => 'bi-plus-lg', 'href' => pr_url('input_kepentingan')]],
]);

if (!empty($pesan_error)) echo pr_alert('danger', pr_e($pesan_error));
?>

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
            <span class="badge bg-light text-secondary border font-monospace py-1 px-2">Tabel: kategorisasi_risiko</span>
        </div>
        <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-2">
            <?= pr_hidden('hasil_kepentingan', ['level' => $level]) ?>
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
                <a href="<?= pr_e(pr_url('hasil_kepentingan')) ?>" class="btn btn-outline-secondary btn-sm" title="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></a>
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
                        <td><span class="badge bg-light text-dark border font-monospace"><?= pr_e($row['UnitUp'] ?? '-') ?></span></td>
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
