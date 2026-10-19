<?php
// templates/penggunaan/monitor_pelunasan.php  -  Monitoring Pelunasan
// Variabel dari modules/penggunaan/monitor_pelunasan.php:
//   $data, $total_data, $ringkas, $keyword, $tahun, $daftar_tahun, $bulan_f, $limit, $hal, $u, $unit_siap, $boleh_input, $pesan_error

$url_pl = fn(array $ubah = []) => pg_url('monitor_pelunasan', array_merge(
    ['tahun' => $tahun, 'bulan' => $bulan_f, 'keyword' => $keyword, 'limit' => $limit == 10 ? '' : $limit], $ubah));

$nama_bulan = ['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni',
               '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'];

echo pg_header([
    'title'    => 'Monitoring Pelunasan',
    'subtitle' => 'Riwayat pelunasan tagihan pelanggan. Wilayah: ' . $u['label'] . ($tahun !== '' ? ' · Periode: ' . ($bulan_f !== '' ? $nama_bulan[$bulan_f] . ' ' : '') . $tahun : '') . '.',
    'action'   => 'monitor_pelunasan',
    'buttons'  => $boleh_input ? [['label' => 'Input Pelunasan', 'icon' => 'bi-plus-lg', 'href' => pg_url('input_pelunasan')]] : [],
]);

if (!empty($pesan_error)) echo pg_alert('danger', $pesan_error);
if (!$unit_siap) return;
if ($tahun === '') {
    echo pg_alert('info', 'Belum ada data tagihan atau pelunasan di wilayah Anda.');
    return;
}
?>

<!-- RINGKASAN -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Pelunasan Tercatat', pg_n($ringkas['lunas']), 'text-dark'],
        ['Total Tagihan Lunas', 'Rp ' . pg_n($ringkas['rp_tag']), 'text-success'],
        ['Total Biaya Keterlambatan', 'Rp ' . pg_n($ringkas['rp_bk']), 'text-warning'],
        ['Tagihan Belum Lunas', pg_n($ringkas['belum']), 'text-danger'],
    ] as [$lbl, $val, $cls]): ?>
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 py-3 px-4 bg-white h-100">
            <span class="text-muted fw-bold text-uppercase" style="font-size:.75rem;letter-spacing:.5px;"><?= pg_e($lbl) ?></span>
            <h3 class="fw-bold mt-1 mb-0 fs-4 <?= $cls ?>"><?= pg_e($val) ?></h3>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- DAFTAR -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4 pb-2">
        <form method="GET" action="" class="row g-2 align-items-center mb-3">
            <?= pg_hidden('monitor_pelunasan') ?>
            <div class="col-md-2">
                <select name="tahun" class="form-select form-select-sm fw-semibold" onchange="this.form.submit()" aria-label="Tahun">
                    <?php foreach ($daftar_tahun as $y): ?>
                    <option value="<?= pg_e($y) ?>" <?= (string) $y === $tahun ? 'selected' : '' ?>>Tahun <?= pg_e($y) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="bulan" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Bulan">
                    <option value="">Semua bulan</option>
                    <?php foreach ($nama_bulan as $k => $n): ?>
                    <option value="<?= $k ?>" <?= $bulan_f === $k ? 'selected' : '' ?>><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="keyword" value="<?= pg_e($keyword) ?>" class="form-control form-control-sm" placeholder="Cari IdPel atau nama...">
            </div>
            <div class="col-md-2">
                <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ([10, 25, 50, 100] as $l): ?>
                    <option value="<?= $l ?>" <?= $limit === $l ? 'selected' : '' ?>><?= $l ?> / halaman</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary px-3"><i class="bi bi-search me-1"></i>Cari</button>
                <?php if ($keyword !== '' || $bulan_f !== ''): ?><a href="<?= pg_e(pg_url('monitor_pelunasan', ['tahun' => $tahun])) ?>" class="btn btn-sm btn-outline-secondary px-3">Reset</a><?php endif; ?>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="text-secondary text-uppercase" style="font-size:.75rem;letter-spacing:.3px;white-space:nowrap;">
                    <th class="ps-4">IdPel</th><th>Nama Pelanggan</th><th>ThBlRek</th><th>Tgl Bayar</th>
                    <th class="text-end">Tagihan (RpTag)</th><th class="text-end">RpBK</th><th>Kode UP</th><th class="pe-4">Waktu Data</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$data): ?>
                <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                    <?= ($keyword !== '' || $bulan_f !== '') ? 'Tidak ada data yang cocok dengan filter.' : 'Belum ada data pelunasan di wilayah Anda.' ?></td></tr>
            <?php else: foreach ($data as $r): ?>
                <tr>
                    <td class="ps-4 font-monospace fw-semibold"><?= pg_e($r['IdPel']) ?></td>
                    <td><?= $r['NamaPelanggan'] === null ? '<span class="text-muted">-</span>' : pg_e($r['NamaPelanggan']) ?></td>
                    <td class="font-monospace"><?= pg_e($r['ThBlRek']) ?></td>
                    <td><?= $r['TglBayar'] === null ? '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-50">BELUM</span>' : pg_e($r['TglBayar']) ?></td>
                    <td class="text-end fw-semibold">Rp <?= pg_n($r['RpTag']) ?></td>
                    <td class="text-end">Rp <?= pg_n($r['RpBK']) ?></td>
                    <td class="font-monospace"><?= pg_e($r['UnitUp']) ?></td>
                    <td class="text-muted small pe-4"><?= pg_e($r['WaktuData']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?= pg_footer_tabel($hal, $limit, $total_data, fn($h) => $url_pl(['hal' => $h > 1 ? $h : ''])) ?>
</div>