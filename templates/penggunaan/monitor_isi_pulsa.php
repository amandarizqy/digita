<?php
// templates/penggunaan/monitor_isi_pulsa.php  -  Monitoring Isi Pulsa
// Variabel dari modules/penggunaan/monitor_isi_pulsa.php:
//   $data, $total_data, $ringkas, $keyword, $status, $tahun, $daftar_tahun, $bulan_f, $limit, $hal, $u, $unit_siap, $boleh_input, $pesan_error,
//   $sel_ap, $sel_up, $opsi_ap, $opsi_up, $perlu_filter, $label_wilayah, $par_unit

$nama_bulan = ['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni',
               '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'];
$url_mi = fn(array $ubah = []) => pg_url('monitor_isi_pulsa', array_merge(
    $par_unit, ['tahun' => $tahun, 'bulan' => $bulan_f, 'status' => $status, 'keyword' => $keyword, 'limit' => $limit == 25 ? '' : $limit], $ubah));

echo pg_header([
    'title'    => 'Monitoring Isi Pulsa',
    'subtitle' => 'Riwayat pengisian pulsa SIM. Wilayah: ' . $label_wilayah . ($tahun !== '' ? ' · Periode: ' . ($bulan_f !== '' ? $nama_bulan[$bulan_f] . ' ' : '') . $tahun : '') . '.',
    'action'   => 'monitor_isi_pulsa',
    'buttons'  => $boleh_input ? [['label' => 'Isi Pulsa', 'icon' => 'bi-plus-lg', 'href' => pg_url('isi_pulsa')]] : [],
]);

if (!empty($pesan_error)) echo pg_alert('danger', $pesan_error);
if (!$unit_siap) return;
?>

<?php if ($u['ap'] === '' || $u['up'] === ''): ?>
<!-- FILTER UNIT (akun tanpa AP / UP di session) -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-3"><span class="badge <?= $perlu_filter ? 'bg-primary' : 'bg-success' ?> rounded-circle me-2"><?= $perlu_filter ? '1' : '✓' ?></span>Filter Unit
            <span class="fw-normal text-muted small">(pilih dahulu untuk menampilkan data)</span></h6>
        <form method="GET" action="" class="row g-2 align-items-end">
            <?= pg_hidden('monitor_isi_pulsa') ?>
            <?php if ($u['ap'] === ''): ?>
            <div class="col-md-4">
                <label for="unit_ap" class="form-label small fw-bold text-secondary text-uppercase">Unit AP</label>
                <select class="form-select form-select-sm" id="unit_ap" name="unit_ap" onchange="var up=document.getElementById('unit_up'); if (up) up.value=''; this.form.submit();">
                    <option value="">Pilih Unit AP...</option>
                    <?php foreach ($opsi_ap as $k => $n): ?><option value="<?= pg_e($k) ?>" <?= (string) $k === $sel_ap ? 'selected' : '' ?>><?= pg_e($n) ?></option><?php endforeach; ?>
                </select>
            </div>
            <?php else: ?><input type="hidden" name="unit_ap" value="<?= pg_e($sel_ap) ?>"><?php endif; ?>
            <?php if ($u['up'] === ''): ?>
            <div class="col-md-4">
                <label for="unit_up" class="form-label small fw-bold text-secondary text-uppercase">Unit UP <span class="fw-normal text-muted text-lowercase">(opsional)</span></label>
                <select class="form-select form-select-sm" id="unit_up" name="unit_up" onchange="this.form.submit();" <?= $sel_ap === '' ? 'disabled' : '' ?>>
                    <option value="">Semua UP</option>
                    <?php foreach ($opsi_up as $k => $n): ?><option value="<?= pg_e($k) ?>" <?= (string) $k === $sel_up ? 'selected' : '' ?>><?= pg_e($n) ?></option><?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </form>
        <?php if ($perlu_filter): ?><div class="form-text mt-2">Pilih Unit AP terlebih dahulu, lalu (bila perlu) persempit ke satu Unit UP.</div><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php
if ($perlu_filter) return;
if (!empty($pesan_error)) return;
if ($tahun === '') {
    echo pg_alert('info', 'Belum ada transaksi isi pulsa pada wilayah <strong>' . pg_e($label_wilayah) . '</strong>. Jika datanya ada di database, pastikan kolom KodeUpi, KodeAp, dan KodeUp pada baris transaksi tersebut sama dengan unit yang dipilih.');
    return;
}
?>

<!-- RINGKASAN -->
<div class="row g-3 mb-4">
    <?php foreach ([['Transaksi', pg_n($ringkas['trx']), 'text-dark'], ['Total Isi Kredit', pg_n($ringkas['isi']), 'text-success'], ['SIM Diisi', pg_n($ringkas['sim']), 'text-primary']] as [$lbl, $val, $cls]): ?>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 py-3 px-4 bg-white h-100">
            <span class="text-muted fw-bold text-uppercase" style="font-size:.75rem;letter-spacing:.5px;"><?= pg_e($lbl) ?></span>
            <h3 class="fw-bold mt-1 mb-0 fs-4 <?= $cls ?>"><?= pg_e($val) ?></h3>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4 pb-2">
        <form method="GET" action="" class="row g-2 align-items-center mb-3">
            <?= pg_hidden('monitor_isi_pulsa', $par_unit) ?>
            <div class="col-md-2">
                <select name="tahun" class="form-select form-select-sm fw-semibold" onchange="this.form.submit()" aria-label="Tahun">
                    <?php foreach ($daftar_tahun as $y): ?><option value="<?= pg_e($y) ?>" <?= (string) $y === $tahun ? 'selected' : '' ?>>Tahun <?= pg_e($y) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="bulan" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Bulan">
                    <option value="">Semua bulan</option>
                    <?php foreach ($nama_bulan as $k => $n): ?><option value="<?= $k ?>" <?= $bulan_f === $k ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Status">
                    <option value="">Semua status</option>
                    <option value="AKTIF" <?= $status === 'AKTIF' ? 'selected' : '' ?>>Aktif</option>
                    <option value="TIDAK" <?= $status === 'TIDAK' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="keyword" value="<?= pg_e($keyword) ?>" class="form-control form-control-sm" placeholder="Cari no. transaksi, SIM, atau akun...">
            </div>
            <div class="col-md-1">
                <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Jumlah baris">
                    <?php foreach ([10, 25, 50, 100] as $l): ?><option value="<?= $l ?>" <?= $limit === $l ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary px-3"><i class="bi bi-search me-1"></i>Cari</button>
                <?php if ($keyword !== '' || $bulan_f !== '' || $status !== ''): ?><a href="<?= pg_e(pg_url('monitor_isi_pulsa', array_merge($par_unit, ['tahun' => $tahun]))) ?>" class="btn btn-sm btn-outline-secondary px-3">Reset</a><?php endif; ?>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="text-secondary text-uppercase" style="font-size:.75rem;letter-spacing:.3px;white-space:nowrap;">
                    <th class="ps-4">No. Transaksi</th><th>Tgl Transaksi</th><th>Nomor SIM</th><th class="text-end">Isi Kredit</th><th class="text-end">Jumlah Kredit</th>
                    <th>Tgl Tempo</th><th>Petugas</th><th>Kode UP</th><th class="pe-4 text-center">Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$data): ?>
                <tr><td colspan="9" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                    <?= ($keyword !== '' || $bulan_f !== '' || $status !== '') ? 'Tidak ada transaksi yang cocok dengan filter.' : 'Belum ada transaksi pada periode ini.' ?></td></tr>
            <?php else: foreach ($data as $r): $aktif = $r['StatusData'] === 'AKTIF'; ?>
                <tr>
                    <td class="ps-4 font-monospace fw-semibold"><?= pg_e($r['NoTrx']) ?></td>
                    <td><?= pg_e($r['TglTrx']) ?></td>
                    <td class="font-monospace"><?= $r['NomorAkun'] === null ? '<span class="text-muted small">' . pg_e($r['SimId']) . '</span>' : pg_e($r['NomorAkun']) ?></td>
                    <td class="text-end fw-semibold"><?= pg_n($r['IsiKredit']) ?></td>
                    <td class="text-end"><?= pg_n($r['JumlahKredit']) ?></td>
                    <td><?= $r['TglTempo'] === null ? '-' : pg_e($r['TglTempo']) ?></td>
                    <td><?= pg_e($r['NamaAkun']) ?></td>
                    <td class="font-monospace"><?= pg_e($r['KodeUp']) ?></td>
                    <td class="pe-4 text-center"><span class="badge bg-<?= $aktif ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $aktif ? 'success' : 'secondary' ?> border border-<?= $aktif ? 'success' : 'secondary' ?> border-opacity-50"><?= $aktif ? 'AKTIF' : 'NONAKTIF' ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?= pg_footer_tabel($hal, $limit, $total_data, fn($h) => $url_mi(['hal' => $h > 1 ? $h : ''])) ?>
</div>