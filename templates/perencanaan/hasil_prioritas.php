<?php
// templates/perencanaan/hasil_prioritas.php  -  Monitoring: Hasil Skala Prioritas
// Variabel dari modules/perencanaan/hasil_prioritas.php

$skala_usang_n = count(array_filter($ringkasan, fn($r) => $r['usang']));
$skala_ada     = count(array_filter($ringkasan, fn($r) => $r['jumlah_hasil'] > 0));
$total_tagihan = array_sum(array_column($ringkasan, 'total_tagihan'));

echo pr_header([
    'title'    => 'Hasil Skala Prioritas',
    'subtitle' => 'Peringkat pelanggan di dalam tiap Skala Prioritas. Peringkat 1 adalah yang paling perlu ditangani.',
    'action'   => 'hasil_prioritas',
    'badge'    => 'Periode ' . $periode_filter,
    'buttons'  => [
        ['label' => 'Cetak', 'icon' => 'bi-printer', 'class' => 'btn btn-outline-secondary btn-sm px-3 rounded-2', 'attrs' => 'type="button" onclick="window.print()"'],
        ['label' => 'Proses Pemeringkatan', 'icon' => 'bi-lightning-charge-fill', 'href' => pr_url('proses_prioritas', ['skala' => $skala_filter ?: ''])],
    ],
]);

if (!empty($pesan_error)) echo pr_alert('danger', pr_e($pesan_error));
if ($tabel_belum_ada) {
    echo pr_alert('warning', 'Tabel <code>hasil_prioritas</code> belum dibuat. Jalankan <code>hasil_prioritas.sql</code> sekali di database, lalu proses lewat <a href="' . pr_e(pr_url('proses_prioritas')) . '" class="alert-link">Skala Prioritas</a>.');
}
?>

<!-- KARTU METRIK KPI -->
<div class="row g-3 mb-4">
    <?= pr_kpi('Pelanggan Diperingkat', pr_n($total_hasil_semua), 'Tersebar di ' . $skala_ada . ' skala', 'bi-people', 'primary') ?>
    <?= pr_kpi('Total Tagihan', pr_rp_ringkas($total_tagihan), 'Rp ' . pr_n($total_tagihan) . ' · pelanggan diperingkat', 'bi-cash-stack', 'success', true) ?>
    <?= pr_kpi('Perlu Update', (string) $skala_usang_n, 'Skala yang tidak sinkron dengan klasifikasi', 'bi-arrow-repeat', 'danger', $skala_usang_n > 0) ?>
    <?= pr_kpi('Skala Terpilih', $skala_filter > 0 ? 'Prioritas ' . $skala_filter : 'Semua', $skala_filter > 0 ? pr_e(labelSkalaPrioritas($skala_filter)) : 'Pilih skala pada matriks di bawah', 'bi-funnel', 'warning') ?>
</div>

<!-- MATRIKS SKALA (klik untuk memfilter) -->
<div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-bold text-primary fs-6">Sebaran Skala Prioritas</span>
        <a href="<?= pr_e($buat_url(['skala' => '', 'hal' => ''])) ?>" class="btn btn-sm rounded-pill px-3 py-1 fw-medium <?= $skala_filter === 0 ? 'btn-outline-primary active' : 'btn-outline-secondary bg-white text-secondary' ?>">
            <?= $skala_filter === 0 ? '<i class="bi bi-check2 me-1"></i>' : '' ?>Semua Skala (<?= pr_n($total_hasil_semua) ?>)
        </a>
    </div>
    <div class="card-body px-4 py-4">
        <?php
        $grid = [];
        for ($n = 1; $n <= 9; $n++) {
            $rg = $ringkasan[$n];
            if ($rg['jumlah_hasil'] === 0)  $note = $rg['jumlah_kategori'] > 0 ? pr_badge('BELUM DIPERINGKAT', 'warning', 'bi-hourglass-split') : '<span class="text-muted">Kosong</span>';
            elseif ($rg['usang'])           $note = pr_badge('PERLU UPDATE', 'danger', 'bi-arrow-repeat');
            else                            $note = pr_badge('TERBARU', 'success', 'bi-check2-circle');
            $grid[$n] = ['count' => $rg['jumlah_hasil'], 'note' => $note, 'href' => $buat_url(['skala' => $n, 'hal' => ''])];
        }
        echo pr_skala_grid($grid, 'link', $skala_filter);
        ?>
    </div>
</div>

<!-- INFO SKALA TERPILIH -->
<?php if ($skala_filter > 0 && $ringkasan[$skala_filter]['jumlah_hasil'] > 0): $rg = $ringkasan[$skala_filter]; ?>
<div class="card border-0 shadow-sm rounded-4 bg-white mb-4 border-start border-4 border-<?= warnaSkalaPrioritas($skala_filter) ?>">
    <div class="card-body px-4 py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <?= pr_badge_prioritas($skala_filter) ?>
                    <span class="text-muted small"><?= pr_e(labelSkalaPrioritas($skala_filter)) ?></span>
                </div>
                <div class="text-muted small">
                    Metode: <strong class="text-dark"><?= pr_e(labelMetodePeringkat($rg['metode'])) ?></strong>
                    <?php if ($rg['metode'] === 'gabungan'): ?>
                        (nominal <?= $rg['bobot'][0] ?>% · telat <?= $rg['bobot'][1] ?>% · tunggakan <?= $rg['bobot'][2] ?>%)
                    <?php endif; ?>
                    · Diproses <?= pr_e((string) $rg['terakhir']) ?><?= !empty($rg['oleh']) ? ' oleh ' . pr_e($rg['oleh']) : '' ?>
                </div>
            </div>
            <div class="d-flex gap-4">
                <div><div class="text-muted pr-label text-uppercase fw-bold">Pelanggan</div><div class="fw-bold fs-5"><?= pr_n($rg['jumlah_hasil']) ?></div></div>
                <div><div class="text-muted pr-label text-uppercase fw-bold">Total Tagihan</div><div class="fw-bold fs-5"><?= pr_rp($rg['total_tagihan']) ?></div></div>
                <div><div class="text-muted pr-label text-uppercase fw-bold">Rata-rata Skor</div><div class="fw-bold fs-5"><?= number_format($rg['avg_skor'], 2, ',', '.') ?></div></div>
            </div>
        </div>
        <?php if ($rg['usang']): ?>
            <div class="alert alert-warning border-0 rounded-3 mt-3 mb-0 py-2 small">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                Klasifikasi mencatat <?= pr_n($rg['jumlah_kategori']) ?> pelanggan pada skala ini, sedangkan peringkat baru memuat <?= pr_n($rg['jumlah_hasil']) ?>.
                <a href="<?= pr_e(pr_url('proses_prioritas', ['skala' => $skala_filter])) ?>" class="alert-link">Proses ulang skala ini</a> agar sinkron.
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- TABEL -->
<div class="card border-0 shadow-sm rounded-4 bg-white">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="fw-bold text-primary fs-6">Daftar Peringkat</span>
            <span class="badge bg-light text-secondary border font-monospace py-1 px-2">Tabel: hasil_prioritas</span>
        </div>
        <form method="GET" action="" class="input-group input-group-sm" style="max-width:280px;">
            <?= pr_hidden('hasil_prioritas', ['skala' => $skala_filter ?: '']) ?>
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" name="keyword" class="form-control bg-light border-start-0" value="<?= pr_e($keyword) ?>" placeholder="Cari IDPel / nama pelanggan...">
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary text-uppercase pr-thead">
                    <tr>
                        <th class="ps-4" style="width:90px;">Peringkat</th>
                        <?php if ($skala_filter === 0): ?><th>Prioritas</th><?php endif; ?>
                        <th>ID Pelanggan</th>
                        <th>Nama Pelanggan</th>
                        <th class="text-end">Total Tagihan</th>
                        <th class="text-center">Telat</th>
                        <th class="text-center">Lewat Bulan</th>
                        <th style="min-width:150px;">Skor</th>
                        <th>Kepentingan</th>
                        <th class="pe-4">Posisi SR</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($list_hasil)): foreach ($list_hasil as $r):
                    $rank = (int) $r['Peringkat'];
                    $skor = (float) $r['SkorPrioritas'];
                    $tone = warnaSkalaPrioritas($r['SkalaPrioritas']);
                ?>
                    <tr>
                        <td class="ps-4"><?= pr_badge('#' . $rank, $rank === 1 ? 'warning' : 'secondary') ?></td>
                        <?php if ($skala_filter === 0): ?><td><?= pr_badge_prioritas($r['SkalaPrioritas'], false) ?></td><?php endif; ?>
                        <td class="fw-bold text-dark font-monospace"><?= pr_e($r['IdPel']) ?></td>
                        <td class="fw-semibold text-dark text-uppercase small"><?= pr_e($r['NamaPelanggan'] ?? '-') ?></td>
                        <td class="text-end font-monospace small text-nowrap"><?= pr_rp($r['TotalTagihan']) ?></td>
                        <td class="text-center"><?= (int) $r['JumlahTelat'] ?></td>
                        <td class="text-center"><?= (int) $r['JumlahLewatBulan'] ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:6px;"><div class="progress-bar bg-<?= $tone ?>" style="width:<?= max(0, min(100, $skor)) ?>%"></div></div>
                                <span class="small fw-bold"><?= number_format($skor, 2, ',', '.') ?></span>
                            </div>
                        </td>
                        <td><?= pr_badge_level($r['LevelKepentingan']) ?></td>
                        <td class="pe-4"><?= pr_badge_sr($r['PosisiSR']) ?></td>
                    </tr>
                <?php endforeach; else:
                    echo pr_empty_row($skala_filter === 0 ? 10 : 9, ($tabel_belum_ada || $total_hasil_semua === 0)
                        ? 'Belum ada hasil pemeringkatan. Proses dulu lewat <a href="' . pr_e(pr_url('proses_prioritas')) . '">Skala Prioritas</a>.'
                        : 'Data tidak ditemukan untuk filter ini.', 'bi-list-ol');
                endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?= pr_footer_tabel($page, $per_page, $total_rows, fn($h) => $buat_url(['hal' => $h])) ?>
</div>
