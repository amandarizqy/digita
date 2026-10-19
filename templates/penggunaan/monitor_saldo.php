<?php
// templates/penggunaan/monitor_saldo.php  -  Monitoring Saldo
// Variabel dari modules/penggunaan/monitor_saldo.php:
//   $data, $total_data, $ringkas, $keyword, $bulan_f, $limit, $hal, $unit_siap, $label_wilayah, $boleh_input, $pesan_error

$url_sd = fn(array $ubah = []) => pg_url('monitor_saldo', array_merge(
    ['keyword' => $keyword, 'bulan' => $bulan_f, 'limit' => $limit == 10 ? '' : $limit], $ubah));

echo pg_header([
    'title'    => 'Monitoring Saldo',
    'subtitle' => 'Saldo tagihan pelanggan per periode (DPP). Wilayah: ' . $label_wilayah . '.',
    'action'   => 'monitor_saldo',
    'buttons'  => $boleh_input ? [['label' => 'Input Saldo', 'icon' => 'bi-plus-lg', 'href' => pg_url('input_saldo')]] : [],
]);

if (!empty($pesan_error)) echo pg_alert('danger', $pesan_error);
if (!$unit_siap) return;
?>

<!-- RINGKASAN -->
<div class="row g-3 mb-4">
    <?php foreach ([['Baris Saldo', pg_n($ringkas['baris'])], ['Pelanggan', pg_n($ringkas['pelanggan'])], ['Total Saldo', 'Rp ' . pg_n($ringkas['rp'])]] as [$lbl, $val]): ?>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 py-3 px-4 bg-white h-100">
            <span class="text-muted fw-bold text-uppercase" style="font-size:.75rem;letter-spacing:.5px;"><?= pg_e($lbl) ?></span>
            <h3 class="fw-bold mt-1 mb-0 text-dark fs-4"><?= pg_e($val) ?></h3>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- DAFTAR -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4 pb-2">
        <form method="GET" action="" class="row g-2 align-items-center mb-3">
            <?= pg_hidden('monitor_saldo') ?>
            <div class="col-md-4">
                <input type="text" name="keyword" value="<?= pg_e($keyword) ?>" class="form-control form-control-sm" placeholder="Cari IdPel atau nama...">
            </div>
            <div class="col-md-3">
                <input type="text" name="bulan" value="<?= pg_e($bulan_f) ?>" class="form-control form-control-sm font-monospace" maxlength="8" placeholder="ThBlRek (mis. 202501)">
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
                <?php if ($keyword !== '' || $bulan_f !== ''): ?><a href="<?= pg_e(pg_url('monitor_saldo')) ?>" class="btn btn-sm btn-outline-secondary px-3">Reset</a><?php endif; ?>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="text-secondary text-uppercase" style="font-size:.75rem;letter-spacing:.3px;white-space:nowrap;">
                    <th class="ps-4">IdPel</th><th>Nama Pelanggan</th><th>ThBlRek</th><th class="text-end">Saldo (RpTag)</th><th>Kode UP</th><th class="pe-4">Waktu Data</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$data): ?>
                <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                    <?= ($keyword !== '' || $bulan_f !== '') ? 'Tidak ada data yang cocok dengan filter.' : 'Belum ada data saldo di wilayah Anda.' ?></td></tr>
            <?php else: foreach ($data as $r): ?>
                <tr>
                    <td class="ps-4 font-monospace fw-semibold"><?= pg_e($r['IdPel']) ?></td>
                    <td><?= $r['NamaPelanggan'] === null ? '<span class="text-muted">-</span>' : pg_e($r['NamaPelanggan']) ?></td>
                    <td class="font-monospace"><?= pg_e($r['ThBlRek']) ?></td>
                    <td class="text-end fw-semibold">Rp <?= pg_n($r['RpTag']) ?></td>
                    <td class="font-monospace"><?= pg_e($r['KodeUp']) ?></td>
                    <td class="text-muted small pe-4"><?= pg_e($r['WaktuData']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?= pg_footer_tabel($hal, $limit, $total_data, fn($h) => $url_sd(['hal' => $h > 1 ? $h : ''])) ?>
</div>