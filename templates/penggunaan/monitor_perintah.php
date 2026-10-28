<?php
// templates/penggunaan/monitor_perintah.php  -  Monitoring Perintah
// Variabel dari modules/penggunaan/monitor_perintah.php:
//   $tab, $data, $total_data, $kartu, $keyword, $status, $limit, $hal, $status_sms, $ada_status, $ada_processed,
//   $batas_sms, $u, $unit_siap, $boleh_input, $pesan_error

$tiket = $tab === 'putus' || $tab === 'nyala';
$url_mp = fn(array $ubah = []) => pg_url('monitor_perintah', array_merge(
    ['tab' => $tab === 'putus' ? '' : $tab, 'status' => $status, 'keyword' => $keyword, 'limit' => $limit == 25 ? '' : $limit], $ubah));

echo pg_header([
    'title'    => 'Monitoring Perintah',
    'subtitle' => 'Tiket putus dan nyala, serta SMS perintah keluar dan balasan perangkat. Wilayah: ' . $u['label'] . '.',
    'action'   => 'monitor_perintah',
    'buttons'  => $boleh_input ? [['label' => 'Input Perintah', 'icon' => 'bi-plus-lg', 'href' => pg_url('input_perintah')]] : [],
]);

if (!empty($pesan_error)) echo pg_alert('danger', $pesan_error);

$daftar_tab = ['putus' => ['Tiket Putus', 'bi-plug'], 'nyala' => ['Tiket Nyala', 'bi-lightning-charge'], 'keluar' => ['SMS Keluar', 'bi-send'], 'masuk' => ['SMS Masuk', 'bi-inbox']];
?>

<ul class="nav nav-tabs mb-3">
    <?php foreach ($daftar_tab as $k => [$lbl, $ic]): ?>
    <li class="nav-item"><a class="nav-link <?= $tab === $k ? 'active fw-semibold' : 'text-secondary' ?>"
        href="<?= pg_e(pg_url('monitor_perintah', ['tab' => $k === 'putus' ? '' : $k])) ?>"><i class="bi <?= $ic ?> me-1"></i><?= $lbl ?></a></li>
    <?php endforeach; ?>
</ul>

<?php if (!$unit_siap || !empty($pesan_error)) return; ?>

<!-- RINGKASAN -->
<div class="row g-3 mb-4">
    <?php foreach ($kartu as [$lbl, $val, $cls, $f]): ?>
    <div class="col-md-6 col-xl-3">
        <a href="<?= pg_e($url_mp(['status' => $f, 'hal' => ''])) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm rounded-4 py-3 px-4 bg-white h-100">
                <span class="text-muted fw-bold text-uppercase" style="font-size:.7rem;letter-spacing:.5px;"><?= pg_e($lbl) ?></span>
                <h3 class="fw-bold mt-1 mb-0 fs-4 <?= $cls ?>"><?= pg_n($val) ?></h3>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4 pb-2">
        <form method="GET" action="" class="row g-2 align-items-center mb-3">
            <?= pg_hidden('monitor_perintah', ['tab' => $tab === 'putus' ? '' : $tab]) ?>
            <div class="col-md-5">
                <input type="text" name="keyword" value="<?= pg_e($keyword) ?>" class="form-control form-control-sm"
                       placeholder="<?= $tiket ? 'Cari nomor tiket, IdPel, nomor barang, atau nama...' : 'Cari nomor, isi pesan, tiket, IdPel, atau nama...' ?>">
            </div>
            <?php if ($tiket || $ada_status || $ada_processed): ?>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Status">
                    <option value="">Semua status</option>
                    <?php if ($tiket): ?>
                        <option value="AKTIF" <?= $status === 'AKTIF' ? 'selected' : '' ?>>Aktif</option>
                        <option value="TIDAK" <?= $status === 'TIDAK' ? 'selected' : '' ?>>Nonaktif</option>
                    <?php elseif ($ada_status): foreach ($status_sms as $k => [$lb]): ?>
                        <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $lb ?></option>
                    <?php endforeach; else: ?>
                        <option value="true" <?= $status === 'true' ? 'selected' : '' ?>>Sudah diproses</option>
                        <option value="false" <?= $status === 'false' ? 'selected' : '' ?>>Belum diproses</option>
                    <?php endif; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ([10, 25, 50, 100] as $l): ?><option value="<?= $l ?>" <?= $limit === $l ? 'selected' : '' ?>><?= $l ?> / halaman</option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary px-3"><i class="bi bi-search me-1"></i>Cari</button>
                <?php if ($keyword !== '' || $status !== ''): ?><a href="<?= pg_e(pg_url('monitor_perintah', ['tab' => $tab === 'putus' ? '' : $tab])) ?>" class="btn btn-sm btn-outline-secondary px-3">Reset</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="text-secondary text-uppercase" style="font-size:.75rem;letter-spacing:.3px;white-space:nowrap;">
                <?php if ($tiket): ?>
                    <th class="ps-4"><?= $tab === 'putus' ? 'Tiket Putus' : 'Tiket Nyala' ?></th><th>Jenis</th><th>IdPel</th><th>Nama Pelanggan</th><th>ThBlRek</th><th>Nomor Barang</th>
                    <th><?= $tab === 'putus' ? 'Tiket Nyala' : 'Tiket Putus' ?></th><th>Pembuat</th><th>Waktu</th><th class="pe-4 text-center">Status</th>
                <?php else: ?>
                    <th class="ps-4">Waktu</th><th><?= $tab === 'keluar' ? 'Tujuan' : 'Pengirim' ?></th><th>Isi Pesan</th><?= $tab === 'keluar' ? '<th>Tiket</th>' : '' ?><th>IdPel</th><th>Nama Pelanggan</th><th>NoRef</th>
                    <?php if ($ada_status || $ada_processed): ?><th class="pe-4 text-center"><?= $tab === 'keluar' ? 'Status' : 'Diproses' ?></th><?php endif; ?>
                <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php $kolom = $tiket ? 10 : (6 + ($tab === 'keluar' ? 1 : 0) + (($ada_status || $ada_processed) ? 1 : 0)); ?>
            <?php if (!$data): ?>
                <tr><td colspan="<?= $kolom ?>" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                    <?= ($keyword !== '' || $status !== '') ? 'Tidak ada data yang cocok dengan filter.' : 'Belum ada data di wilayah Anda.' ?></td></tr>
            <?php elseif ($tiket): foreach ($data as $r): $aktif = $r['StatusData'] === 'AKTIF'; $kor = $r['JenisTiket'] === 'KOREKTIF'; ?>
                <tr>
                    <td class="ps-4 font-monospace fw-semibold small"><?= pg_e($r['Kode']) ?></td>
                    <td><span class="badge bg-<?= $kor ? 'danger' : 'info' ?> bg-opacity-10 text-<?= $kor ? 'danger' : 'info' ?> border border-<?= $kor ? 'danger' : 'info' ?> border-opacity-50"><?= pg_e($r['JenisTiket']) ?></span></td>
                    <td class="font-monospace"><?= pg_e($r['IdPel']) ?></td>
                    <td><?= $r['NamaPelanggan'] === null ? '-' : pg_e($r['NamaPelanggan']) ?></td>
                    <td class="font-monospace"><?= pg_e($r['ThBlRek']) ?></td>
                    <td class="font-monospace"><?= pg_e($r['NomorBarang']) ?></td>
                    <td class="font-monospace small"><?= $r['Rujukan'] === null ? '<span class="text-muted">belum ada</span>' : pg_e($r['Rujukan']) ?></td>
                    <td><?= pg_e($r['NamaAkun']) ?></td>
                    <td class="text-muted small text-nowrap"><?= pg_e($r['WaktuData']) ?></td>
                    <td class="pe-4 text-center"><span class="badge bg-<?= $aktif ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $aktif ? 'success' : 'secondary' ?> border border-<?= $aktif ? 'success' : 'secondary' ?> border-opacity-50"><?= $aktif ? 'AKTIF' : 'NONAKTIF' ?></span></td>
                </tr>
            <?php endforeach; else: foreach ($data as $r): ?>
                <tr>
                    <td class="ps-4 text-muted small text-nowrap"><?= $r['Waktu'] === null ? '-' : pg_e($r['Waktu']) ?></td>
                    <td class="font-monospace"><?= pg_e($r['Nomor']) ?></td>
                    <td><?= pg_e($r['TextDecoded']) ?></td>
                    <?php if ($tab === 'keluar'): ?><td class="font-monospace small"><?= $r['Tiket'] === null ? '<span class="text-muted">-</span>' : pg_e($r['Tiket']) ?></td><?php endif; ?>
                    <td class="font-monospace"><?= $r['IdPel'] === null ? '-' : pg_e($r['IdPel']) ?></td>
                    <td><?= $r['NamaPelanggan'] === null ? '-' : pg_e($r['NamaPelanggan']) ?></td>
                    <td class="font-monospace small"><?= $r['NoRef'] === null ? '-' : pg_e($r['NoRef']) ?></td>
                    <?php if ($ada_status): [$lbl, $wr] = $status_sms[$r['Status']] ?? [(string) $r['Status'], 'secondary']; ?>
                        <td class="text-center pe-4"><span class="badge bg-<?= $wr ?> bg-opacity-10 text-<?= $wr ?> border border-<?= $wr ?> border-opacity-50"><?= pg_e($lbl) ?></span></td>
                    <?php elseif ($ada_processed): ?>
                        <td class="text-center pe-4"><?= strtolower((string) $r['Status']) === 'true' ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-hourglass-split text-warning"></i>' ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?= pg_footer_tabel($hal, $limit, $total_data, fn($h) => $url_mp(['hal' => $h > 1 ? $h : ''])) ?>
    <?php if (!$tiket): ?><div class="px-4 pb-3 small text-muted">Hanya <?= pg_n($batas_sms) ?> SMS terakhir yang diperiksa, dan hanya yang cocok dengan nomor perangkat di wilayah Anda. Status TERKIRIM berarti modem menerima SMS; konfirmasi eksekusi ada pada balasan di SMS Masuk.</div><?php endif; ?>
</div>