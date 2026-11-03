<?php
// templates/penggunaan/monitor_status_barang.php  -  Monitoring Status Barang
// Variabel dari modules/penggunaan/monitor_status_barang.php:
//   $data, $total_data, $ringkas, $keyword, $pasang, $status, $relay, $proses, $limit, $hal, $f (filter unit), $u, $unit_siap,
//   $tiket_putus, $tiket_nyala, $out_by, $in_by, $last9, $ada_status, $status_sms, $pesan_sms, $pesan_error

$par = $f['par_unit'] ?? [];
$url_sb = fn(array $ubah = []) => pg_url('monitor_status_barang', array_merge($par,
    ['pasang' => $pasang, 'status' => $status, 'relay' => $relay, 'proses' => $proses, 'keyword' => $keyword, 'limit' => $limit == 25 ? '' : $limit], $ubah));

echo pg_header([
    'title'    => 'Monitoring Status Barang',
    'subtitle' => 'Terpasang atau belum, aktif atau nonaktif, tiket yang berjalan, dan pesan yang sedang dikirim. Wilayah: ' . ($f['label'] ?? $u['label']) . '.',
    'action'   => 'monitor_status_barang',
]);

if (!empty($pesan_error)) echo pg_alert('danger', $pesan_error);
if (!$unit_siap) return;
echo pg_filter_unit_html($f, $u, 'monitor_status_barang');
if ($f['perlu_filter'] || !empty($pesan_error)) return;
if (!empty($pesan_sms)) echo pg_alert('warning', $pesan_sms);
?>

<!-- RINGKASAN -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Total Barang', $ringkas['total'], 'text-dark', ['pasang' => '', 'status' => '', 'proses' => '']],
        ['Terpasang', $ringkas['pasang'], 'text-success', ['pasang' => 'ya', 'status' => '', 'proses' => '']],
        ['Belum Terpasang', $ringkas['total'] - $ringkas['pasang'], 'text-secondary', ['pasang' => 'tidak', 'status' => '', 'proses' => '']],
        ['Aktif', $ringkas['aktif'], 'text-primary', ['pasang' => '', 'status' => 'AKTIF', 'proses' => '']],
        ['Tiket Aktif', $ringkas['tiket'], 'text-danger', ['pasang' => '', 'status' => '', 'proses' => 'ya']],
        ['Sedang Mengirim Pesan', $ringkas['kirim'], 'text-warning', null],
    ] as [$lbl, $val, $cls, $flt]): ?>
    <div class="col-6 col-xl-2">
        <?php if ($flt !== null): ?><a href="<?= pg_e($url_sb($flt + ['hal' => ''])) ?>" class="text-decoration-none"><?php endif; ?>
        <div class="card border-0 shadow-sm rounded-4 py-3 px-3 bg-white h-100">
            <span class="text-muted fw-bold text-uppercase" style="font-size:.68rem;letter-spacing:.4px;"><?= pg_e($lbl) ?></span>
            <h3 class="fw-bold mt-1 mb-0 fs-4 <?= $cls ?>"><?= pg_n($val) ?></h3>
        </div>
        <?php if ($flt !== null): ?></a><?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4 pb-2">
        <form method="GET" action="" class="row g-2 align-items-center mb-3">
            <?= pg_hidden('monitor_status_barang', $par) ?>
            <div class="col-md-3">
                <input type="text" name="keyword" value="<?= pg_e($keyword) ?>" class="form-control form-control-sm" placeholder="Cari NoRef, IdPel, nama, SIM, IMEI, nomor...">
            </div>
            <div class="col-md-2">
                <select name="pasang" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Pemasangan">
                    <option value="">Semua pemasangan</option>
                    <option value="ya" <?= $pasang === 'ya' ? 'selected' : '' ?>>Terpasang</option>
                    <option value="tidak" <?= $pasang === 'tidak' ? 'selected' : '' ?>>Belum terpasang</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Status">
                    <option value="">Semua status</option>
                    <option value="AKTIF" <?= $status === 'AKTIF' ? 'selected' : '' ?>>Aktif</option>
                    <option value="TIDAK" <?= $status === 'TIDAK' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
            <div class="col-md-1">
                <select name="relay" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Relay">
                    <option value="">Relay</option>
                    <option value="ON" <?= $relay === 'ON' ? 'selected' : '' ?>>ON</option>
                    <option value="OFF" <?= $relay === 'OFF' ? 'selected' : '' ?>>OFF</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="proses" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Tiket">
                    <option value="">Semua tiket</option>
                    <option value="ya" <?= $proses === 'ya' ? 'selected' : '' ?>>Punya tiket aktif</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Jumlah baris" style="max-width:70px">
                    <?php foreach ([10, 25, 50, 100] as $l): ?><option value="<?= $l ?>" <?= $limit === $l ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-primary px-3"><i class="bi bi-search"></i></button>
                <?php if ($keyword !== '' || $pasang !== '' || $status !== '' || $relay !== '' || $proses !== ''): ?><a href="<?= pg_e(pg_url('monitor_status_barang', $par)) ?>" class="btn btn-sm btn-outline-secondary px-2">Reset</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="text-secondary text-uppercase" style="font-size:.72rem;letter-spacing:.3px;white-space:nowrap;">
                    <th class="ps-4">Barang</th><th>Pelanggan</th><th>Pemasangan</th><th class="text-center">Status</th><th class="text-center">Relay</th>
                    <th>Tiket Aktif</th><th>Pesan Keluar</th><th class="pe-4">Balasan Terakhir</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$data): ?>
                <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                    <?= ($keyword !== '' || $pasang !== '' || $status !== '' || $relay !== '' || $proses !== '') ? 'Tidak ada barang yang cocok dengan filter.' : 'Belum ada barang di wilayah ini.' ?></td></tr>
            <?php else: foreach ($data as $r):
                $terpasang = $r['IdPel'] !== null; $siap = (int) $r['Siap'] === 1; $aktif = $r['StatusData'] === 'AKTIF';
                $nb = (string) $r['NomorBarang']; $k9 = $nb !== '' ? $last9($nb) : '';
                $tp = $nb !== '' ? ($tiket_putus[$nb] ?? null) : null; $tn = $nb !== '' ? ($tiket_nyala[$nb] ?? null) : null;
                $out = $k9 !== '' ? ($out_by[$k9] ?? null) : null; $in = $k9 !== '' ? ($in_by[$k9] ?? null) : null;
            ?>
                <tr>
                    <td class="ps-4">
                        <div class="font-monospace fw-semibold small"><?= pg_e($r['NoRef']) ?></div>
                        <div class="text-muted" style="font-size:.75rem"><?= $nb !== '' ? pg_e($nb) : 'nomor belum tersedia' ?> · <?= $r['UnitUp'] === null ? '-' : pg_e($r['UnitUp']) ?></div>
                    </td>
                    <td>
                        <?php if ($terpasang): ?>
                            <div class="font-monospace small"><?= pg_e($r['IdPel']) ?></div>
                            <div class="text-muted" style="font-size:.75rem"><?= $r['NamaPelanggan'] === null ? '-' : pg_e($r['NamaPelanggan']) ?></div>
                        <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$terpasang): ?><span class="badge bg-secondary bg-opacity-10 text-secondary border"><i class="bi bi-box-seam me-1"></i>BELUM TERPASANG</span>
                        <?php elseif ($siap): ?><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-50"><i class="bi bi-check-circle me-1"></i>TERPASANG</span>
                        <?php else: ?><span class="badge bg-warning bg-opacity-10 text-warning-emphasis border border-warning border-opacity-50" title="IdPel terisi, tetapi SIM / IMEI / relay / unit belum lengkap sehingga belum masuk view terpasang"><i class="bi bi-exclamation-triangle me-1"></i>TERPASANG · data belum lengkap</span><?php endif; ?>
                    </td>
                    <td class="text-center"><span class="badge bg-<?= $aktif ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $aktif ? 'success' : 'secondary' ?> border border-<?= $aktif ? 'success' : 'secondary' ?> border-opacity-50"><?= $aktif ? 'AKTIF' : 'NONAKTIF' ?></span></td>
                    <td class="text-center">
                        <?php if ($r['StatusRelay'] === null): ?><span class="text-muted">-</span>
                        <?php else: $on = $r['StatusRelay'] === 'ON'; ?><span class="badge bg-<?= $on ? 'success' : 'danger' ?>"><?= pg_e($r['StatusRelay']) ?></span><?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$tp && !$tn): ?><span class="text-muted">-</span><?php endif; ?>
                        <?php if ($tp): ?><div><span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-50">PUTUS</span> <span class="font-monospace" style="font-size:.72rem"><?= pg_e($tp['Tiket']) ?></span> <span class="text-muted" style="font-size:.7rem"><?= pg_e($tp['JenisTiket']) ?></span></div><?php endif; ?>
                        <?php if ($tn): ?><div><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-50">NYALA</span> <span class="font-monospace" style="font-size:.72rem"><?= pg_e($tn['Tiket']) ?></span> <span class="text-muted" style="font-size:.7rem"><?= pg_e($tn['JenisTiket']) ?></span></div><?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$out): ?><span class="text-muted">-</span>
                        <?php else:
                            [$lb, $wr] = $ada_status ? ($status_sms[$out['Status']] ?? [(string) $out['Status'], 'secondary']) : ['MENGIRIM', 'warning']; ?>
                            <span class="badge bg-<?= $wr ?> bg-opacity-10 text-<?= $wr ?> border border-<?= $wr ?> border-opacity-50"><?php if ($lb === 'MENGIRIM'): ?><i class="bi bi-hourglass-split me-1"></i><?php endif; ?><?= pg_e($lb) ?></span>
                            <span class="font-monospace small"><?= pg_e($out['TextDecoded']) ?></span>
                            <?php if ($out['Waktu'] !== null): ?><div class="text-muted" style="font-size:.7rem"><?= pg_e($out['Waktu']) ?></div><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="pe-4">
                        <?php if (!$in): ?><span class="text-muted">-</span>
                        <?php else: ?><div class="small"><?= pg_e(mb_strimwidth((string) $in['TextDecoded'], 0, 48, '…')) ?></div>
                            <?php if ($in['Waktu'] !== null): ?><div class="text-muted" style="font-size:.7rem"><?= pg_e($in['Waktu']) ?></div><?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?= pg_footer_tabel($hal, $limit, $total_data, fn($h) => $url_sb(['hal' => $h > 1 ? $h : ''])) ?>
    <div class="px-4 pb-3 small text-muted">
        Tiket aktif dan SMS dilacak lewat nomor barang pada view terpasang; barang yang datanya belum lengkap tidak terlacak. Pesan keluar dan balasan diambil dari
        <?= pg_n($batas_sms ?? 2000) ?> SMS terakhir. "Mengirim" berarti SMS masih antre di outbox dan belum diambil gateway.
    </div>
</div>