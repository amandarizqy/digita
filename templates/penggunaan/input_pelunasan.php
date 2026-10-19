<?php
// templates/penggunaan/input_pelunasan.php  -  Input Pelunasan: daftar saldo belum lunas siap diproses
// Variabel dari modules/penggunaan/input_pelunasan.php:
//   $data, $total_data, $ringkas, $keyword, $tahun, $daftar_tahun, $bulan_f, $limit, $hal, $csrf, $u, $unit_siap,
//   $boleh_monitor, $pesan_sukses, $pesan_error

$url_pl = fn(array $ubah = []) => pg_url('input_pelunasan', array_merge(
    ['tahun' => $tahun, 'bulan' => $bulan_f, 'keyword' => $keyword, 'limit' => $limit == 25 ? '' : $limit], $ubah));

$nama_bulan = ['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni',
               '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'];

echo pg_header([
    'title'    => 'Input Pelunasan',
    'subtitle' => 'Saldo tunggakan yang belum lunas. Isi tanggal bayar dan RpBK, lalu proses. Wilayah: ' . $u['label'] . '.',
    'action'   => 'input_pelunasan',
    'buttons'  => $boleh_monitor ? [['label' => 'Monitoring Pelunasan', 'icon' => 'bi-display', 'href' => pg_url('monitor_pelunasan'),
                                     'class' => 'btn btn-outline-primary btn-sm px-3 shadow-sm rounded-2 bg-white']] : [],
]);

if (!empty($pesan_sukses)) echo pg_alert('success', $pesan_sukses);
if (!empty($pesan_error))  echo pg_alert('danger', $pesan_error);

if (!$unit_siap) {
    echo pg_alert('danger', 'Akun Anda belum memiliki <strong>Unit UPI</strong>, sehingga halaman ini tidak dapat dipakai. Hubungi administrator.');
    return;
}
if ($tahun === '') {
    echo pg_alert('info', 'Belum ada data saldo tunggakan di wilayah Anda.' . (pga_boleh('input_saldo') ? ' Input dulu di <a href="' . pg_e(pg_url('input_saldo')) . '" class="alert-link">Input Saldo Tunggakan</a>.' : ''));
    return;
}
?>

<!-- RINGKASAN -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 py-3 px-4 bg-white h-100">
            <span class="text-muted fw-bold text-uppercase" style="font-size:.75rem;letter-spacing:.5px;">Tagihan Belum Lunas</span>
            <h3 class="fw-bold mt-1 mb-0 fs-4 text-danger"><?= pg_n($ringkas['jumlah']) ?></h3>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 py-3 px-4 bg-white h-100">
            <span class="text-muted fw-bold text-uppercase" style="font-size:.75rem;letter-spacing:.5px;">Total Saldo Belum Lunas</span>
            <h3 class="fw-bold mt-1 mb-0 fs-4 text-dark">Rp <?= pg_n($ringkas['rp']) ?></h3>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4 pb-2">
        <!-- FILTER -->
        <form method="GET" action="" class="row g-2 align-items-center mb-3">
            <?= pg_hidden('input_pelunasan') ?>
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
                <?php if ($keyword !== '' || $bulan_f !== ''): ?><a href="<?= pg_e(pg_url('input_pelunasan', ['tahun' => $tahun])) ?>" class="btn btn-sm btn-outline-secondary px-3">Reset</a><?php endif; ?>
            </div>
        </form>
    </div>

    <!-- DAFTAR + PROSES -->
    <form method="POST" action="<?= pg_e($url_pl(['hal' => $hal > 1 ? $hal : ''])) ?>" id="formPelunasan">
        <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
        <input type="hidden" name="f_tahun" value="<?= pg_e($tahun) ?>">
        <input type="hidden" name="f_bulan" value="<?= pg_e($bulan_f) ?>">
        <input type="hidden" name="f_keyword" value="<?= pg_e($keyword) ?>">
        <input type="hidden" name="f_limit" value="<?= (int) $limit ?>">
        <input type="hidden" name="f_hal" value="<?= (int) $hal ?>">

        <?php if ($data): ?>
        <div class="px-4 pb-3 d-flex flex-wrap align-items-center gap-2">
            <label for="tgl_semua" class="small fw-bold text-secondary text-uppercase mb-0">Tanggal bayar semua baris</label>
            <input type="date" id="tgl_semua" class="form-control form-control-sm" style="width:auto" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnTerapkan">Terapkan</button>
            <span class="ms-auto small text-muted" id="infoPilih">0 dipilih</span>
            <button type="submit" class="btn btn-sm btn-primary px-3" id="btnBanyak" disabled
                    onclick="return confirm('Lunasi ' + document.querySelectorAll('.cek-baris:checked').length + ' tagihan terpilih?');">
                <i class="bi bi-check2-all me-1"></i>Lunasi Terpilih
            </button>
        </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="text-secondary text-uppercase" style="font-size:.75rem;letter-spacing:.3px;white-space:nowrap;">
                        <th class="ps-4" style="width:40px"><input type="checkbox" class="form-check-input" id="cekSemua" aria-label="Pilih semua"></th>
                        <th>IdPel</th><th>Nama Pelanggan</th><th>ThBlRek</th><th class="text-end">Saldo (RpTag)</th>
                        <th style="min-width:150px">Tanggal Bayar</th><th style="min-width:150px">RpBK</th><th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$data): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>
                        <?= ($keyword !== '' || $bulan_f !== '') ? 'Tidak ada tagihan belum lunas yang cocok dengan filter.' : 'Semua tagihan pada periode ini sudah lunas.' ?></td></tr>
                <?php else: foreach ($data as $r): $key = $r['IdPel'] . '_' . trim($r['ThBlRek']); ?>
                    <tr>
                        <td class="ps-4"><input type="checkbox" class="form-check-input cek-baris" name="pilih[]" value="<?= pg_e($key) ?>" aria-label="Pilih <?= pg_e($r['IdPel']) ?>"></td>
                        <td class="font-monospace fw-semibold"><?= pg_e($r['IdPel']) ?></td>
                        <td><?= $r['NamaPelanggan'] === null ? '<span class="text-muted">-</span>' : pg_e($r['NamaPelanggan']) ?></td>
                        <td class="font-monospace"><?= pg_e($r['ThBlRek']) ?></td>
                        <td class="text-end fw-semibold">Rp <?= pg_n($r['RpTag']) ?></td>
                        <td><input type="date" class="form-control form-control-sm tgl-baris" name="tgl[<?= pg_e($key) ?>]" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required></td>
                        <td><input type="number" class="form-control form-control-sm" name="rpbk[<?= pg_e($key) ?>]" min="0" step="1" value="0" required></td>
                        <td class="text-end pe-4">
                            <button type="submit" name="satu" value="<?= pg_e($key) ?>" class="btn btn-sm btn-outline-success"
                                    onclick="return confirm('Catat IdPel <?= pg_e($r['IdPel']) ?> periode <?= pg_e(trim($r['ThBlRek'])) ?> sebagai lunas?');">
                                <i class="bi bi-check-circle me-1"></i>Lunasi
                            </button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </form>
    <?= pg_footer_tabel($hal, $limit, $total_data, fn($h) => $url_pl(['hal' => $h > 1 ? $h : ''])) ?>
</div>

<script>
(function () {
    var semua = document.getElementById('cekSemua'), info = document.getElementById('infoPilih'),
        btn = document.getElementById('btnBanyak'), tglSemua = document.getElementById('tgl_semua');
    function hitung() {
        var n = document.querySelectorAll('.cek-baris:checked').length;
        if (info) info.textContent = n + ' dipilih';
        if (btn) btn.disabled = n === 0;
    }
    if (semua) semua.addEventListener('change', function () {
        document.querySelectorAll('.cek-baris').forEach(function (c) { c.checked = semua.checked; });
        hitung();
    });
    document.querySelectorAll('.cek-baris').forEach(function (c) { c.addEventListener('change', hitung); });
    var t = document.getElementById('btnTerapkan');
    if (t) t.addEventListener('click', function () {
        if (!tglSemua.value) return;
        document.querySelectorAll('.tgl-baris').forEach(function (i) { i.value = tglSemua.value; });
    });
    hitung();
})();
</script>