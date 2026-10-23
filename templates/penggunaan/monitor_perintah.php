<?php
// templates/penggunaan/monitor_perintah.php  -  Monitoring Perintah
// Variabel dari modules/penggunaan/monitor_perintah.php:
//   $data, $total_data, $ringkas, $balasan, $pesan_balasan, $status_map, $daftar_perintah, $keyword, $status, $cmd,
//   $limit, $hal, $u, $unit_siap, $boleh_input, $pesan_error

$url_mp = fn(array $ubah = []) => pg_url('monitor_perintah', array_merge(
    ['keyword' => $keyword, 'status' => $status, 'cmd' => $cmd, 'limit' => $limit == 25 ? '' : $limit], $ubah));

echo pg_header([
    'title'    => 'Monitoring Perintah',
    'subtitle' => 'Perintah yang dikirim ke perangkat lewat SMS Gateway dan balasannya. Wilayah: ' . $u['label'] . '.',
    'action'   => 'monitor_perintah',
    'buttons'  => $boleh_input ? [['label' => 'Input Perintah', 'icon' => 'bi-plus-lg', 'href' => pg_url('input_perintah')]] : [],
]);

if (!empty($pesan_error)) echo pg_alert('danger', $pesan_error);
if (!$unit_siap || !empty($pesan_error)) return;
?>

<!-- RINGKASAN -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Total Perintah', $ringkas['total'], 'text-dark', ''],
        ['Antrean', $ringkas['Reserved'], 'text-warning', 'Reserved'],
        ['Terkirim', $ringkas['SendingOK'], 'text-success', 'SendingOK'],
        ['Gagal', $ringkas['SendingError'], 'text-danger', 'SendingError'],
    ] as [$lbl, $val, $cls, $f]): ?>
    <div class="col-md-6 col-xl-3">
        <a href="<?= pg_e($url_mp(['status' => $f, 'hal' => ''])) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm rounded-4 py-3 px-4 bg-white h-100">
                <span class="text-muted fw-bold text-uppercase" style="font-size:.75rem;letter-spacing:.5px;"><?= pg_e($lbl) ?></span>
                <h3 class="fw-bold mt-1 mb-0 fs-4 <?= $cls ?>"><?= pg_n($val) ?></h3>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- DAFTAR PERINTAH -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4 pb-2">
        <form method="GET" action="" class="row g-2 align-items-center mb-3">
            <?= pg_hidden('monitor_perintah') ?>
            <div class="col-md-4">
                <input type="text" name="keyword" value="<?= pg_e($keyword) ?>" class="form-control form-control-sm" placeholder="Cari tiket, NoRef, IdPel, nomor tujuan, atau nama...">
            </div>
            <div class="col-md-2">
                <select name="cmd" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua perintah</option>
                    <?php foreach ($daftar_perintah as $c): ?><option value="<?= pg_e($c) ?>" <?= $cmd === $c ? 'selected' : '' ?>><?= pg_e($c) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua status</option>
                    <?php foreach ($status_map as $k => [$lbl]): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ([10, 25, 50, 100] as $l): ?><option value="<?= $l ?>" <?= $limit === $l ? 'selected' : '' ?>><?= $l ?> / halaman</option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary px-3"><i class="bi bi-search me-1"></i>Cari</button>
                <?php if ($keyword !== '' || $status !== '' || $cmd !== ''): ?><a href="<?= pg_e(pg_url('monitor_perintah')) ?>" class="btn btn-sm btn-outline-secondary px-3">Reset</a><?php endif; ?>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="text-secondary text-uppercase" style="font-size:.75rem;letter-spacing:.3px;white-space:nowrap;">
                    <th class="ps-4">ID</th><th>Perintah</th><th>Tiket</th><th>NoRef</th><th>Nomor Tujuan</th><th>IdPel</th><th>Nama Pelanggan</th><th>Pengirim</th><th class="text-center pe-4">Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$data): ?>
                <tr><td colspan="9" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                    <?= ($keyword !== '' || $status !== '' || $cmd !== '') ? 'Tidak ada perintah yang cocok dengan filter.' : 'Belum ada perintah yang dikirim dari aplikasi di wilayah Anda.' ?></td></tr>
            <?php else: foreach ($data as $r): [$lbl, $warna] = $status_map[$r['Status']] ?? [(string) $r['Status'], 'secondary']; ?>
                <tr>
                    <td class="ps-4 font-monospace text-muted"><?= (int) $r['ID'] ?></td>
                    <td><span class="badge bg-dark bg-opacity-10 text-dark border font-monospace"><?= pg_e($r['TextDecoded']) ?></span></td>
                    <td class="font-monospace small"><?= $r['Tiket'] === null ? '<span class="text-muted">manual</span>' : pg_e($r['Tiket']) ?></td>
                    <td class="font-monospace small"><?= pg_e($r['NoRef']) ?></td>
                    <td class="font-monospace"><?= pg_e($r['DestinationNumber']) ?></td>
                    <td class="font-monospace"><?= $r['IdPel'] === null ? '-' : pg_e($r['IdPel']) ?></td>
                    <td><?= $r['NamaPelanggan'] === null ? '-' : pg_e($r['NamaPelanggan']) ?></td>
                    <td><?= pg_e($r['Pengirim']) ?></td>
                    <td class="text-center pe-4"><span class="badge bg-<?= $warna ?> bg-opacity-10 text-<?= $warna ?> border border-<?= $warna ?> border-opacity-50 px-2 py-1"><?= pg_e($lbl) ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?= pg_footer_tabel($hal, $limit, $total_data, fn($h) => $url_mp(['hal' => $h > 1 ? $h : ''])) ?>
    <div class="px-4 pb-3 small text-muted">TERKIRIM berarti modem menerima SMS untuk dikirim; konfirmasi bahwa perangkat menjalankan perintah ada pada balasan di bawah.</div>
</div>

<!-- BALASAN PERANGKAT -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4 pb-2">
        <h6 class="fw-bold mb-1"><i class="bi bi-chat-left-text me-2 text-primary"></i>Balasan Terbaru dari Perangkat</h6>
        <p class="small text-muted mb-3">Dari 500 SMS masuk terakhir yang cocok dengan perangkat di wilayah Anda (15 teratas).</p>
        <?php if ($pesan_balasan): ?><div class="alert alert-warning small border-0"><?= $pesan_balasan ?></div><?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="text-secondary text-uppercase" style="font-size:.75rem;letter-spacing:.3px;white-space:nowrap;">
                    <th class="ps-4">Waktu</th><th>Pengirim</th><th>NoRef</th><th>IdPel</th><th>Nama Pelanggan</th><th>Isi Pesan</th><th class="pe-4 text-center">Diproses</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$balasan): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada balasan dari perangkat di wilayah Anda.</td></tr>
            <?php else: foreach ($balasan as $r): $diproses = in_array(strtolower((string) $r['Processed']), ['true', '1'], true); ?>
                <tr>
                    <td class="ps-4 text-muted small text-nowrap"><?= pg_e($r['ReceivingDateTime']) ?></td>
                    <td class="font-monospace"><?= pg_e($r['SenderNumber']) ?></td>
                    <td class="font-monospace small"><?= pg_e($r['NoRef']) ?></td>
                    <td class="font-monospace"><?= $r['IdPel'] === null ? '-' : pg_e($r['IdPel']) ?></td>
                    <td><?= $r['NamaPelanggan'] === null ? '-' : pg_e($r['NamaPelanggan']) ?></td>
                    <td><?= pg_e($r['TextDecoded']) ?></td>
                    <td class="text-center pe-4"><?= $diproses ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-hourglass-split text-warning"></i>' ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>