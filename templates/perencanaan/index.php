<?php
// templates/perencanaan/index.php  -  Ringkasan Perencanaan
// Variabel dari modules/perencanaan/dashboard.php

$langkah = [
    [
        'no' => 1, 'judul' => 'Riwayat Pelunasan', 'grup' => 'INPUT', 'icon' => 'bi-upload',
        'deskripsi' => 'Tagihan dan tanggal bayar pelanggan sebagai bahan hitung keterlambatan.',
        'selesai' => $riwayat_pelanggan, 'total' => $total_dil, 'satuan' => 'pelanggan',
        'buka' => 'upload_riwayat', 'hasil' => 'data_riwayat',
    ],
    [
        'no' => 2, 'judul' => 'Tingkat Kepentingan', 'grup' => 'INPUT', 'icon' => 'bi-pencil-square',
        'deskripsi' => 'Rendah / Moderat / Tinggi per pelanggan, menjadi sumbu matriks kemungkinan.',
        'selesai' => $kepentingan_ok, 'total' => $total_dil, 'satuan' => 'pelanggan',
        'buka' => 'input_kepentingan', 'hasil' => 'hasil_kepentingan',
    ],
    [
        'no' => 3, 'judul' => 'Survey SR', 'grup' => 'INPUT', 'icon' => 'bi-ui-checks',
        'deskripsi' => 'Posisi SR: apakah lokasi pelanggan mandiri atau tergantung pelanggan lain.',
        'selesai' => $sr_ok, 'total' => $total_dil, 'satuan' => 'pelanggan',
        'buka' => 'input_survey', 'hasil' => 'hasil_survey',
    ],
    [
        'no' => 4, 'judul' => 'Klasifikasi Level Risiko', 'grup' => 'PROSES', 'icon' => 'bi-diagram-3',
        'deskripsi' => 'Hitung kuadran, level kemungkinan, level dampak, lalu Skala Prioritas 1-9.',
        'selesai' => $klasifikasi_ok, 'total' => $total_dil, 'satuan' => 'pelanggan',
        'buka' => 'proses_risiko', 'hasil' => 'hasil_risiko',
    ],
    [
        'no' => 5, 'judul' => 'Skala Prioritas', 'grup' => 'PROSES', 'icon' => 'bi-sort-numeric-down',
        'deskripsi' => 'Peringkatkan pelanggan di dalam satu skala berdasarkan nominal, telat, dan tunggakan.',
        'selesai' => $peringkat_ok, 'total' => $klasifikasi_ok, 'satuan' => 'terklasifikasi',
        'buka' => 'proses_prioritas', 'hasil' => 'hasil_prioritas',
    ],
];

$perhatian = [];
if ($belum_sr > 0)          $perhatian[] = ['bi-ui-checks', 'secondary', pr_n($belum_sr) . ' pelanggan belum punya Posisi SR', 'Isi lewat Survey SR', pr_url('input_survey')];
if ($belum_kepentingan > 0) $perhatian[] = ['bi-flag', 'secondary', pr_n($belum_kepentingan) . ' pelanggan belum punya Tingkat Kepentingan', 'Isi lewat Tingkat Kepentingan', pr_url('input_kepentingan')];
if ($skala_belum > 0)       $perhatian[] = ['bi-hourglass-split', 'warning', $skala_belum . ' skala belum diperingkat', 'Buka Skala Prioritas', pr_url('proses_prioritas')];
if ($skala_usang > 0)       $perhatian[] = ['bi-arrow-repeat', 'danger', $skala_usang . ' skala perlu diperingkat ulang', 'Jumlah pelanggan berubah sejak diperingkat', pr_url('proses_prioritas')];

echo pr_header([
    'title'    => 'Modul Perencanaan',
    'subtitle' => 'Petakan risiko keterlambatan bayar pelanggan S41: dari riwayat pelunasan, klasifikasi level risiko, hingga skala prioritas penanganan.',
    'action'   => '',
    'badge'    => 'Periode ' . $periode,
    'buttons'  => [['label' => 'Klasifikasi Risiko', 'icon' => 'bi-lightning-charge-fill', 'href' => pr_url('proses_risiko')]],
]);
?>

<!-- KARTU METRIK KPI -->
<div class="row g-3 mb-4">
    <?= pr_kpi('Pelanggan DIL', pr_n($total_dil),
               'Riwayat tagihan: <strong>' . pr_n($riwayat_baris) . '</strong> baris', 'bi-people', 'primary') ?>
    <?= pr_kpi('Terklasifikasi', pr_n($klasifikasi_ok) . ' <span class="fs-6 fw-semibold text-muted">/ ' . pr_n($total_dil) . '</span>',
               pr_pct($klasifikasi_ok, $total_dil) . '% memiliki Skala Prioritas', 'bi-shield-check', 'success', true) ?>
    <?= pr_kpi('Prioritas Tinggi', pr_n($tier['tinggi']),
               'Skala 7-9 · perlu penanganan segera', 'bi-exclamation-octagon', 'danger', true) ?>
    <?= pr_kpi('Sudah Diperingkat', pr_n($peringkat_ok) . ' <span class="fs-6 fw-semibold text-muted">/ ' . pr_n($klasifikasi_ok) . '</span>',
               pr_pct($peringkat_ok, $klasifikasi_ok) . '% dari yang terklasifikasi', 'bi-list-ol', 'warning') ?>
</div>

<div class="row g-4">
    <!-- ALUR KERJA -->
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="fw-bold text-primary fs-6">Alur Kerja Perencanaan</span>
                <span class="badge bg-light text-secondary border font-monospace py-1 px-2">5 tahap</span>
            </div>
            <div class="card-body p-0">
                <?php foreach ($langkah as $i => $l):
                    $persen = $l['total'] > 0 ? min(100, (int) round($l['selesai'] / $l['total'] * 100)) : 0;
                    $lengkap = $l['total'] > 0 && $l['selesai'] >= $l['total'];
                    $tone   = $lengkap ? 'success' : ($persen > 0 ? 'primary' : 'secondary');
                    [$fg, $bg] = pr_tone($tone);
                ?>
                <div class="d-flex gap-3 align-items-start px-4 py-3 <?= $i > 0 ? 'border-top' : '' ?>">
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold pr-step" style="background-color:<?= $bg ?>;color:<?= $fg ?>;">
                        <?= $lengkap ? '<i class="bi bi-check-lg"></i>' : $l['no'] ?>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <span class="fw-semibold text-dark"><i class="bi <?= $l['icon'] ?> text-muted me-1"></i> <?= pr_e($l['judul']) ?></span>
                                <span class="badge bg-light text-secondary border font-monospace ms-1" style="font-size:.65rem;"><?= $l['grup'] ?></span>
                            </div>
                            <div class="btn-group btn-group-sm">
                                <a href="<?= pr_e(pr_url($l['buka'])) ?>" class="btn btn-outline-primary py-1 px-3"><?= $l['grup'] === 'INPUT' ? 'Input' : 'Proses' ?></a>
                                <a href="<?= pr_e(pr_url($l['hasil'])) ?>" class="btn btn-outline-secondary py-1 px-3">Lihat Hasil</a>
                            </div>
                        </div>
                        <div class="text-muted small mb-2"><?= pr_e($l['deskripsi']) ?></div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="progress flex-grow-1" style="height:6px;" role="progressbar" aria-valuenow="<?= $persen ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar bg-<?= $tone ?>" style="width:<?= $persen ?>%"></div>
                            </div>
                            <span class="small text-muted font-monospace text-nowrap">
                                <?= pr_n($l['selesai']) ?> / <?= pr_n($l['total']) ?> <?= $l['satuan'] ?> · <strong class="text-dark"><?= $persen ?>%</strong>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- SEBARAN + PERHATIAN -->
    <div class="col-xl-4 d-flex flex-column gap-4">
        <div class="card border-0 shadow-sm rounded-4 bg-white">
            <div class="card-header bg-white py-3 px-4 border-bottom">
                <span class="fw-bold text-primary fs-6">Sebaran Tingkat Prioritas</span>
            </div>
            <div class="card-body px-4 py-3">
                <?php
                $sebaran = [
                    ['Tinggi (7-9)', 'danger',  $tier['tinggi'], 'tinggi'],
                    ['Sedang (4-6)', 'warning', $tier['sedang'], 'sedang'],
                    ['Rendah (1-3)', 'success', $tier['rendah'], 'rendah'],
                ];
                foreach ($sebaran as [$nama, $tone, $jml, $kunci]):
                    $persen = $klasifikasi_ok > 0 ? round($jml / $klasifikasi_ok * 100) : 0;
                ?>
                <a href="<?= pr_e(pr_url('hasil_risiko', ['tingkat' => $kunci])) ?>" class="text-decoration-none d-block mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="fw-semibold text-dark"><i class="bi bi-circle-fill text-<?= $tone ?> me-2" style="font-size:.6rem;"></i><?= $nama ?></span>
                        <span class="text-muted font-monospace"><?= pr_n($jml) ?> · <?= $persen ?>%</span>
                    </div>
                    <div class="progress" style="height:8px;"><div class="progress-bar bg-<?= $tone ?>" style="width:<?= $persen ?>%"></div></div>
                </a>
                <?php endforeach; ?>
                <?php if ($klasifikasi_ok === 0): ?>
                    <div class="text-muted small text-center pt-1">Belum ada pelanggan yang diklasifikasi.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 bg-white flex-grow-1">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                <span class="fw-bold text-primary fs-6">Perlu Perhatian</span>
                <?php if ($perhatian): ?><span class="badge bg-warning bg-opacity-10 text-warning-emphasis border border-warning font-monospace"><?= count($perhatian) ?></span><?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if ($perhatian): foreach ($perhatian as $i => [$icon, $tone, $judul, $ket, $href]): [$fg, $bg] = pr_tone($tone); ?>
                    <a href="<?= pr_e($href) ?>" class="d-flex gap-3 align-items-center px-4 py-3 text-decoration-none <?= $i > 0 ? 'border-top' : '' ?>">
                        <div class="rounded-circle d-flex align-items-center justify-content-center pr-step" style="background-color:<?= $bg ?>;color:<?= $fg ?>;"><i class="bi <?= $icon ?>"></i></div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold text-dark small"><?= pr_e($judul) ?></div>
                            <div class="text-muted" style="font-size:.75rem;"><?= pr_e($ket) ?></div>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                <?php endforeach; else: ?>
                    <div class="text-center text-muted py-4 px-4">
                        <i class="bi bi-check2-circle fs-1 d-block mb-2 text-success"></i>
                        <span class="small">Semua tahap sinkron. Tidak ada yang perlu ditindaklanjuti.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
