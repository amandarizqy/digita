<?php
// templates/penggunaan/monitor_tul.php  -  Monitoring TUL VI-01
// Variabel dari modules/penggunaan/monitor_tul.php:
//   $data, $total_data, $ringkas, $keyword, $tahun, $daftar_tahun, $tahun_n, $bulan_f, $status_f, $limit, $hal, $csrf,
//   $u, $unit_siap, $boleh_ubah, $boleh_upload, $pesan_sukses, $pesan_error

$url_tul = fn(array $ubah = []) => pg_url('monitor_tul', array_merge(
    ['tahun' => $tahun, 'bulan' => $bulan_f, 'status' => $status_f, 'keyword' => $keyword, 'limit' => $limit == 10 ? '' : $limit], $ubah));

$nama_bulan = ['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni',
               '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'];

echo pg_header([
    'title'    => 'Monitoring TUL VI-01',
    'subtitle' => 'Daftar TUL VI-01 yang sudah dicetak. Wilayah: ' . $u['label'] . ($tahun !== '' ? ' · Periode cetak: ' . ($bulan_f !== '' ? $nama_bulan[$bulan_f] . ' ' : '') . $tahun : '') . '.',
    'action'   => 'monitor_tul',
    'buttons'  => $boleh_upload ? [['label' => 'Upload TUL', 'icon' => 'bi-cloud-arrow-up', 'href' => pg_url('upload_tul')]] : [],
]);

if (!empty($pesan_sukses)) echo pg_alert('success', $pesan_sukses);
if (!empty($pesan_error))  echo pg_alert('danger', $pesan_error);
if (!$unit_siap) return;
if ($tahun === '') {
    echo pg_alert('info', 'Belum ada data TUL di wilayah Anda.');
    return;
}
$tetap = '<input type="hidden" name="csrf" value="' . pg_e($csrf) . '">'
       . '<input type="hidden" name="tahun" value="' . pg_e($tahun) . '"><input type="hidden" name="bulan" value="' . pg_e($bulan_f) . '">'
       . '<input type="hidden" name="filter_status" value="' . pg_e($status_f) . '"><input type="hidden" name="keyword" value="' . pg_e($keyword) . '">'
       . '<input type="hidden" name="limit" value="' . (int) $limit . '"><input type="hidden" name="hal" value="' . (int) $hal . '">';
?>

<!-- RINGKASAN -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Jumlah TUL', pg_n($ringkas['tul']), pg_n($ringkas['aktif']) . ' aktif · ' . pg_n($ringkas['tidak']) . ' nonaktif', 'text-dark'],
        ['Pelanggan', pg_n($ringkas['pelanggan']), 'IdPel unik', 'text-primary'],
        ['Total Tagihan', 'Rp ' . pg_n($ringkas['rp_tag']), 'Jumlah RpTag', 'text-danger'],
        ['Total Lembar', pg_n($ringkas['lembar']), 'Lembar rekening', 'text-warning'],
    ] as [$lbl, $val, $ket, $cls]): ?>
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 py-3 px-4 bg-white h-100">
            <span class="text-muted fw-bold text-uppercase" style="font-size:.75rem;letter-spacing:.5px;"><?= pg_e($lbl) ?></span>
            <h3 class="fw-bold mt-1 mb-0 fs-4 <?= $cls ?>"><?= pg_e($val) ?></h3>
            <span class="text-muted small"><?= pg_e($ket) ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- DAFTAR -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4 pb-2">
        <form method="GET" action="" class="row g-2 align-items-center mb-3">
            <?= pg_hidden('monitor_tul') ?>
            <div class="col-md-2">
                <select name="tahun" class="form-select form-select-sm fw-semibold" onchange="this.form.submit()" aria-label="Tahun">
                    <?php foreach ($daftar_tahun as $y): ?>
                    <option value="<?= pg_e($y) ?>" <?= (string) $y === $tahun ? 'selected' : '' ?>>Tahun <?= pg_e($y) ?> — <?= pg_n($tahun_n[$y] ?? 0) ?> TUL</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="bulan" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Bulan cetak">
                    <option value="">Semua bulan</option>
                    <?php foreach ($nama_bulan as $k => $n): ?>
                    <option value="<?= $k ?>" <?= $bulan_f === $k ? 'selected' : '' ?>><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Status">
                    <option value="">Semua status</option>
                    <option value="AKTIF" <?= $status_f === 'AKTIF' ? 'selected' : '' ?>>Aktif</option>
                    <option value="TIDAK" <?= $status_f === 'TIDAK' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="keyword" value="<?= pg_e($keyword) ?>" class="form-control form-control-sm" placeholder="Cari IdPel, nomer TUL, atau nama...">
            </div>
            <div class="col-md-1">
                <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Baris per halaman">
                    <?php foreach ([10, 25, 50, 100] as $l): ?>
                    <option value="<?= $l ?>" <?= $limit === $l ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary px-3"><i class="bi bi-search me-1"></i>Cari</button>
                <?php if ($keyword !== '' || $bulan_f !== '' || $status_f !== ''): ?><a href="<?= pg_e(pg_url('monitor_tul', ['tahun' => $tahun])) ?>" class="btn btn-sm btn-outline-secondary px-3">Reset</a><?php endif; ?>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="text-secondary text-uppercase" style="font-size:.75rem;letter-spacing:.3px;white-space:nowrap;">
                    <th class="ps-4">Nomer TUL</th><th>Waktu Cetak</th><th>IdPel</th><th>Nama Pelanggan</th><th>Periode Rekening</th>
                    <th class="text-center">Lembar</th><th class="text-end">Tagihan (RpTag)</th><th class="text-end">Stand Baca</th><th>Tgl Baca</th>
                    <th>Nomor Pengirim</th><th>Unit UP</th><th class="text-center">Status</th><th <?= $boleh_ubah ? '' : 'class="pe-4"' ?>>Waktu Data</th>
                    <?php if ($boleh_ubah): ?><th class="text-end pe-4">Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (!$data): ?>
                <tr><td colspan="<?= $boleh_ubah ? 14 : 13 ?>" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                    <?= ($keyword !== '' || $bulan_f !== '' || $status_f !== '') ? 'Tidak ada data yang cocok dengan filter.' : 'Belum ada data TUL pada periode ini.' ?></td></tr>
            <?php else: foreach ($data as $r): $aktif = $r['StatusData'] === 'AKTIF'; ?>
                <tr>
                    <td class="ps-4 font-monospace small fw-semibold text-nowrap"><?= pg_e($r['NoTul']) ?></td>
                    <td class="small text-nowrap"><?= pg_e($r['WaktuCetak']) ?></td>
                    <td class="font-monospace fw-semibold"><?= pg_e($r['IdPel']) ?></td>
                    <td><?= $r['NamaPelanggan'] === null ? '<span class="text-muted">-</span>' : pg_e($r['NamaPelanggan']) ?></td>
                    <td class="font-monospace small text-nowrap"><?= pg_e($r['ThBlRekAwal']) ?><?= $r['ThBlRekAwal'] !== $r['ThBlRekAkhir'] ? ' – ' . pg_e($r['ThBlRekAkhir']) : '' ?></td>
                    <td class="text-center"><?= pg_n($r['Lembar']) ?></td>
                    <td class="text-end fw-semibold text-nowrap">Rp <?= pg_n($r['RpTag']) ?></td>
                    <td class="text-end"><?= $r['StandBaca'] === null ? '<span class="text-muted">-</span>' : pg_n($r['StandBaca']) ?></td>
                    <td class="text-nowrap"><?= $r['TglBaca'] === null ? '<span class="text-muted">-</span>' : pg_e($r['TglBaca']) ?></td>
                    <td class="font-monospace small"><?= ($r['NomorPengirim'] ?? '') === '' ? '<span class="text-muted">-</span>' : pg_e($r['NomorPengirim']) ?></td>
                    <td class="font-monospace"><?= pg_e($r['UnitUp']) ?></td>
                    <td class="text-center">
                        <span class="badge bg-<?= $aktif ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $aktif ? 'success' : 'secondary' ?> border border-<?= $aktif ? 'success' : 'secondary' ?> border-opacity-50 px-2 py-1"><?= $aktif ? 'AKTIF' : 'NONAKTIF' ?></span>
                    </td>
                    <td class="text-muted small text-nowrap <?= $boleh_ubah ? '' : 'pe-4' ?>"><?= pg_e($r['WaktuData']) ?></td>
                    <?php if ($boleh_ubah): ?>
                    <td class="text-end pe-4 text-nowrap">
                        <form method="POST" action="<?= pg_e(pg_url('monitor_tul')) ?>" class="d-inline"
                              data-konfirmasi="<?= $aktif ? 'Nonaktifkan' : 'Aktifkan' ?> TUL <?= pg_e($r['NoTul']) ?>?" data-judul="<?= $aktif ? 'Nonaktifkan' : 'Aktifkan' ?> data?" data-ya="Ya, <?= $aktif ? 'Nonaktifkan' : 'Aktifkan' ?>"<?= $aktif ? ' data-bahaya="1"' : '' ?>>
                            <?= $tetap ?>
                            <input type="hidden" name="notul" value="<?= pg_e($r['NoTul']) ?>">
                            <input type="hidden" name="status_baru" value="<?= $aktif ? 'TIDAK' : 'AKTIF' ?>">
                            <button type="submit" class="btn btn-sm <?= $aktif ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                <i class="bi <?= $aktif ? 'bi-slash-circle' : 'bi-check-circle' ?> me-1"></i><?= $aktif ? 'Nonaktifkan' : 'Aktifkan' ?>
                            </button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?= pg_footer_tabel($hal, $limit, $total_data, fn($h) => $url_tul(['hal' => $h > 1 ? $h : ''])) ?>
</div>

<?php include __DIR__ . '/../_konfirmasi.php'; ?>
