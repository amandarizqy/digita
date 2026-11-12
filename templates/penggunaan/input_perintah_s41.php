<?php
// templates/penggunaan/input_perintah.php  -  Input Perintah: pilih tiket / perangkat + perintah, lalu kirim lewat SMS Gateway
// Variabel dari modules/penggunaan/input_perintah.php:
//   $sumber, $tiket_cfg, $saran_cmd, $daftar_perintah, $data, $total_data, $ringkas, $keyword, $relay, $status, $limit, $hal,
//   $csrf, $u, $unit_siap, $maks_kirim, $boleh_monitor, $pesan_sukses, $pesan_error

$tiket_mode = $sumber !== 'barang';
$url_pr = fn(array $ubah = []) => pg_url('input_perintah', array_merge(
    ['sumber' => $sumber === 'putus' ? '' : $sumber, 'status' => $status === 'AKTIF' ? '' : $status, 'keyword' => $keyword, 'relay' => $relay,
     'tindakan' => $tindakan === 'BELUM' ? '' : $tindakan, 'limit' => $limit == 25 ? '' : $limit], $ubah));

echo pg_header([
    'title'    => 'Input Perintah',
    'subtitle' => 'Kirim perintah ke perangkat lewat SMS Gateway, dari tiket atau langsung dari perangkat. Wilayah: ' . $u['label'] . '.',
    'action'   => 'input_perintah',
    'buttons'  => $boleh_monitor ? [['label' => 'Monitoring Perintah', 'icon' => 'bi-display', 'href' => pg_url('monitor_perintah'),
                                     'class' => 'btn btn-outline-primary btn-sm px-3 shadow-sm rounded-2 bg-white']] : [],
]);

if (!empty($pesan_sukses)) echo pg_alert('success', $pesan_sukses);
if (!empty($pesan_error))  echo pg_alert('danger', $pesan_error);

if (!$unit_siap) {
    echo pg_alert('danger', 'Akun Anda belum memiliki <strong>Unit UPI</strong>, sehingga halaman ini tidak dapat dipakai. Hubungi administrator.');
    return;
}

$tab = ['putus' => ['Tiket Putus', 'bi-plug'], 'nyala' => ['Tiket Nyala', 'bi-lightning-charge'], 'barang' => ['Perangkat (manual)', 'bi-cpu']];
?>

<!-- SUMBER -->
<ul class="nav nav-tabs mb-3">
    <?php foreach ($tab as $k => [$lbl, $ic]): ?>
    <li class="nav-item"><a class="nav-link <?= $sumber === $k ? 'active fw-semibold' : 'text-secondary' ?>"
        href="<?= pg_e(pg_url('input_perintah', ['sumber' => $k === 'putus' ? '' : $k])) ?>"><i class="bi <?= $ic ?> me-1"></i><?= $lbl ?></a></li>
    <?php endforeach; ?>
</ul>

<!-- RINGKASAN -->
<div class="row g-3 mb-4">
    <?php $kartu = $tiket_mode
        ? [['Total ' . $tiket_cfg[$sumber]['label'], $ringkas['total'], 'text-dark'], ['Belum Ada Tindakan', $ringkas['a'], 'text-danger'], ['Sudah Ada Tindakan', $ringkas['b'], 'text-success']]
        : [['Perangkat Terpasang', $ringkas['total'], 'text-dark'], ['Relay ON', $ringkas['a'], 'text-success'], ['Relay OFF', $ringkas['b'], 'text-danger']];
    foreach ($kartu as [$lbl, $val, $cls]): ?>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 py-3 px-4 bg-white h-100">
            <span class="text-muted fw-bold text-uppercase" style="font-size:.75rem;letter-spacing:.5px;"><?= pg_e($lbl) ?></span>
            <h3 class="fw-bold mt-1 mb-0 fs-4 <?= $cls ?>"><?= pg_n($val) ?></h3>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4 pb-2">
        <!-- FILTER -->
        <form method="GET" action="" class="row g-2 align-items-center mb-3">
            <?= pg_hidden('input_perintah', ['sumber' => $sumber === 'putus' ? '' : $sumber]) ?>
            <div class="col-md-3">
                <input type="text" name="keyword" value="<?= pg_e($keyword) ?>" class="form-control form-control-sm"
                       placeholder="<?= $tiket_mode ? 'Cari nomor tiket, IdPel, nomor barang, atau nama...' : 'Cari NoRef, nomor barang, IMEI, IdPel, atau nama...' ?>">
            </div>
            <div class="col-md-2">
                <?php if ($tiket_mode): ?>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Status tiket">
                    <option value="AKTIF" <?= $status === 'AKTIF' ? 'selected' : '' ?>>Tiket aktif</option>
                    <option value="TIDAK" <?= $status === 'TIDAK' ? 'selected' : '' ?>>Tiket nonaktif</option>
                    <option value="SEMUA" <?= $status === 'SEMUA' ? 'selected' : '' ?>>Semua tiket</option>
                </select>
                <?php else: ?>
                <select name="relay" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Relay">
                    <option value="">Semua relay</option>
                    <option value="ON" <?= $relay === 'ON' ? 'selected' : '' ?>>Relay ON</option>
                    <option value="OFF" <?= $relay === 'OFF' ? 'selected' : '' ?>>Relay OFF</option>
                </select>
                <?php endif; ?>
            </div>
            <?php if ($tiket_mode): ?>
            <div class="col-md-2">
                <select name="tindakan" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Status tindakan">
                    <option value="BELUM" <?= $tindakan === 'BELUM' ? 'selected' : '' ?>>Belum ada tindakan</option>
                    <option value="SUDAH" <?= $tindakan === 'SUDAH' ? 'selected' : '' ?>>Sudah ada tindakan</option>
                    <option value="SEMUA" <?= $tindakan === 'SEMUA' ? 'selected' : '' ?>>Semua tindakan</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ([10, 25, 50, 100] as $l): ?>
                    <option value="<?= $l ?>" <?= $limit === $l ? 'selected' : '' ?>><?= $l ?> / halaman</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary px-3"><i class="bi bi-search me-1"></i>Cari</button>
                <?php if ($keyword !== '' || $relay !== '' || $status !== 'AKTIF' || ($tiket_mode && $tindakan !== 'BELUM')): ?><a href="<?= pg_e(pg_url('input_perintah', ['sumber' => $sumber === 'putus' ? '' : $sumber])) ?>" class="btn btn-sm btn-outline-secondary px-3">Reset</a><?php endif; ?>
            </div>
        </form>
    </div>

    <form method="POST" action="<?= pg_e($url_pr(['hal' => $hal > 1 ? $hal : ''])) ?>" id="formPerintah">
        <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
        <input type="hidden" name="sumber" value="<?= pg_e($sumber) ?>">
        <input type="hidden" name="f_keyword" value="<?= pg_e($keyword) ?>">
        <input type="hidden" name="f_relay" value="<?= pg_e($relay) ?>">
        <input type="hidden" name="f_status" value="<?= pg_e($status) ?>">
        <input type="hidden" name="f_tindakan" value="<?= pg_e($tindakan) ?>">
        <input type="hidden" name="f_limit" value="<?= (int) $limit ?>">
        <input type="hidden" name="f_hal" value="<?= (int) $hal ?>">

        <!-- PILIH PERINTAH -->
        <div class="px-4 pb-3">
            <div class="border rounded-3 bg-light p-3">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-5">
                        <label for="cmd" class="form-label small fw-bold text-secondary text-uppercase">Perintah</label>
                        <select class="form-select" id="cmd" name="cmd" required>
                            <option value="">Pilih perintah...</option>
                            <?php foreach ($daftar_perintah as $kode => $c): ?>
                            <option value="<?= pg_e($kode) ?>" <?= $kode === $saran_cmd ? 'selected' : '' ?> data-bahaya="<?= $c['bahaya'] ? '1' : '0' ?>" data-ket="<?= pg_e($c['label']) ?>"><?= pg_e($kode) ?> — <?= pg_e($c['label']) ?><?= $kode === $saran_cmd ? ' (disarankan)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg d-flex align-items-center justify-content-lg-end gap-3 flex-wrap">
                        <span class="small text-muted" id="infoPilih">0 dipilih</span>
                        <button type="submit" class="btn btn-primary px-4 shadow-sm" id="btnKirim" disabled><i class="bi bi-send me-1"></i>Kirim Perintah</button>
                    </div>
                </div>
                
            </div>
        </div>

        <!-- DAFTAR -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="text-secondary text-uppercase" style="font-size:.75rem;letter-spacing:.3px;white-space:nowrap;">
                        <th class="ps-4" style="width:40px"><input type="checkbox" class="form-check-input" id="cekSemua" aria-label="Pilih semua di halaman"></th>
                        <?php if ($tiket_mode): ?>
                        <th><?= pg_e($tiket_cfg[$sumber]['label']) ?></th><th>Jenis</th><th>IdPel</th><th>Nama Pelanggan</th><th>ThBlRek</th><th>Nomor Barang</th>
                        <?= $sumber === 'putus' ? '<th>Tiket Nyala</th>' : '' ?><th>Tindakan</th><th class="pe-4 text-center">Status</th>
                        <?php else: ?>
                        <th>NoRef</th><th>Nomor Barang</th><th>IMEI Modem</th><th>IdPel</th><th>Nama Pelanggan</th><th>Unit UP</th><th class="pe-4 text-center">Relay</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php $kolom = $tiket_mode ? ($sumber === 'putus' ? 10 : 9) : 8; ?>
                <?php if (!$data): ?>
                    <tr><td colspan="<?= $kolom ?>" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                        <?= ($keyword !== '' || $relay !== '' || $status !== 'AKTIF' || ($tiket_mode && $tindakan !== 'BELUM')) ? 'Tidak ada data yang cocok dengan filter.' : ($tiket_mode ? 'Belum ada tiket aktif di wilayah Anda.' : 'Belum ada perangkat terpasang di wilayah Anda.') ?></td></tr>
                <?php elseif ($tiket_mode): foreach ($data as $r):
                    $bisa = pr_nomor($r['NomorBarang'] ?? '') !== null; $aktif = $r['StatusData'] === 'AKTIF'; ?>
                    <tr class="<?= $bisa ? '' : 'text-muted' ?>">
                        <td class="ps-4"><input type="checkbox" class="form-check-input cek-baris" name="pilih[]" value="<?= pg_e($r['Kode']) ?>" data-sudah="<?= $r['AdaTindakan'] === null ? '0' : '1' ?>" <?= $bisa ? '' : 'disabled' ?> aria-label="Pilih <?= pg_e($r['Kode']) ?>"></td>
                        <td class="font-monospace fw-semibold small"><?= pg_e($r['Kode']) ?></td>
                        <td><span class="badge bg-<?= $r['JenisTiket'] === 'KOREKTIF' ? 'danger' : 'info' ?> bg-opacity-10 text-<?= $r['JenisTiket'] === 'KOREKTIF' ? 'danger' : 'info' ?> border border-<?= $r['JenisTiket'] === 'KOREKTIF' ? 'danger' : 'info' ?> border-opacity-50"><?= pg_e($r['JenisTiket']) ?></span></td>
                        <td class="font-monospace"><?= pg_e($r['IdPel']) ?></td>
                        <td><?= $r['NamaPelanggan'] === null ? '-' : pg_e($r['NamaPelanggan']) ?></td>
                        <td class="font-monospace"><?= pg_e($r['ThBlRek']) ?></td>
                        <td class="font-monospace"><?= $bisa ? pg_e($r['NomorBarang']) : '<span class="badge bg-warning bg-opacity-10 text-warning-emphasis border border-warning border-opacity-50">Tidak valid</span>' ?></td>
                        <?php if ($sumber === 'putus'): ?><td class="font-monospace small"><?= $r['TiketNyala'] === null ? '<span class="text-muted">belum ada</span>' : pg_e($r['TiketNyala']) ?></td><?php endif; ?>
                        <td class="small text-nowrap">
                            <?php if ($r['AdaTindakan'] === null): ?>
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-50">Belum ada</span>
                            <?php else: ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-50">Sudah</span>
                            <div class="text-muted" style="font-size:.7rem"><?= pg_e((string) $r['WaktuTindakan']) ?> · SMS #<?= pg_e((string) $r['SmsId']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="pe-4 text-center"><span class="badge bg-<?= $aktif ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $aktif ? 'success' : 'secondary' ?> border border-<?= $aktif ? 'success' : 'secondary' ?> border-opacity-50"><?= $aktif ? 'AKTIF' : 'NONAKTIF' ?></span></td>
                    </tr>
                <?php endforeach; else: foreach ($data as $r):
                    $bisa = pr_nomor($r['NomorBarang'] ?? '') !== null; $on = pr_relay_on($r['StatusRelay'] ?? ''); $wr = $on === null ? 'secondary' : ($on ? 'success' : 'danger'); ?>
                    <tr class="<?= $bisa ? '' : 'text-muted' ?>">
                        <td class="ps-4"><input type="checkbox" class="form-check-input cek-baris" name="pilih[]" value="<?= pg_e($r['NoRef']) ?>" <?= $bisa ? '' : 'disabled' ?> aria-label="Pilih <?= pg_e($r['NoRef']) ?>"></td>
                        <td class="font-monospace fw-semibold"><?= pg_e($r['NoRef']) ?></td>
                        <td class="font-monospace"><?= $bisa ? pg_e($r['NomorBarang']) : '<span class="badge bg-warning bg-opacity-10 text-warning-emphasis border border-warning border-opacity-50">Tidak valid</span>' ?></td>
                        <td class="font-monospace small"><?= $r['ImeiModem'] === null ? '-' : pg_e($r['ImeiModem']) ?></td>
                        <td class="font-monospace"><?= $r['IdPel'] === null ? '-' : pg_e($r['IdPel']) ?></td>
                        <td><?= $r['NamaPelanggan'] === null ? '-' : pg_e($r['NamaPelanggan']) ?></td>
                        <td class="font-monospace"><?= $r['UnitUp'] === null ? '-' : pg_e($r['UnitUp']) ?></td>
                        <td class="pe-4 text-center"><?php if (($r['StatusRelay'] ?? '') === ''): ?>-<?php else: ?><span class="badge bg-<?= $wr ?> bg-opacity-10 text-<?= $wr ?> border border-<?= $wr ?> border-opacity-50"><?= pg_e($r['StatusRelay']) ?></span><?php endif; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </form>
    <?= pg_footer_tabel($hal, $limit, $total_data, fn($h) => $url_pr(['hal' => $h > 1 ? $h : ''])) ?>
</div>

<script>
(function () {
    var form = document.getElementById('formPerintah'), cmd = document.getElementById('cmd'),
        info = document.getElementById('infoPilih'), btn = document.getElementById('btnKirim'),
        semua = document.getElementById('cekSemua'), maks = <?= (int) $maks_kirim ?>;
    function pilih() { return document.querySelectorAll('.cek-baris:checked').length; }
    function segarkan() {
        var n = pilih();
        info.textContent = n + ' dipilih' + (n > maks ? ' — melebihi batas ' + maks : '');
        btn.disabled = !(n > 0 && n <= maks && cmd.value);
    }
    cmd.addEventListener('change', segarkan);
    document.querySelectorAll('.cek-baris').forEach(function (c) { c.addEventListener('change', segarkan); });
    if (semua) semua.addEventListener('change', function () {
        document.querySelectorAll('.cek-baris:not(:disabled)').forEach(function (c) { c.checked = semua.checked; });
        segarkan();
    });
    function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    form.addEventListener('submit', function (e) {
        var o = cmd.options[cmd.selectedIndex], bahaya = o.dataset.bahaya === '1', n = pilih(),
            ulang = document.querySelectorAll('.cek-baris:checked[data-sudah="1"]').length;
        var html = 'Kirim perintah <strong>' + esc(cmd.value) + '</strong> (' + esc(o.dataset.ket) + ') untuk <strong>' + n + '</strong> data terpilih?';
        if (ulang > 0) html += '<br><br>' + ulang + ' di antaranya <strong>sudah punya tindakan</strong>; perintah akan dikirim <strong>ulang</strong>.';
        if (bahaya) html += '<br><br><span class="text-danger fw-semibold">PERHATIAN: perintah ini langsung memengaruhi aliran listrik pelanggan dan tidak dapat dibatalkan.</span>';

        if (typeof Swal === 'undefined') {            // cadangan bila SweetAlert2 tidak termuat
            if (!confirm(html.replace(/<br>/g, '\n').replace(/<[^>]+>/g, ''))) e.preventDefault();
            return;
        }
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Kirim perintah?',
            html: html,
            showCancelButton: true,
            confirmButtonText: 'Ya, Kirim',
            cancelButtonText: 'Batal',
            confirmButtonColor: bahaya ? '#dc3545' : '#0d6efd',
            cancelButtonColor: '#6c757d',
            reverseButtons: true,
            focusCancel: bahaya
        }).then(function (r) { if (r.isConfirmed) form.submit(); });   // submit() tidak memicu event submit lagi
    });
    segarkan();
})();
</script>