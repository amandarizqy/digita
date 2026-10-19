<?php
// templates/penggunaan/input_perintah.php  -  Input Perintah: pilih aset barang + perintah, lalu kirim
// Variabel dari modules/penggunaan/input_perintah.php:
//   $perintah, $data, $total_data, $ringkas, $keyword, $status, $limit, $hal, $csrf, $u, $unit_siap, $maks_kirim,
//   $boleh_monitor, $pesan_sukses, $pesan_error

$url_pr = fn(array $ubah = []) => pg_url('input_perintah', array_merge(
    ['keyword' => $keyword, 'status' => $status, 'limit' => $limit == 25 ? '' : $limit], $ubah));

echo pg_header([
    'title'    => 'Input Perintah',
    'subtitle' => 'Pilih aset barang (perangkat), pilih perintah, lalu kirim. Wilayah: ' . $u['label'] . '.',
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
?>

<!-- RINGKASAN -->
<div class="row g-3 mb-4">
    <?php foreach ([['Aset Barang di Wilayah', pg_n($ringkas['total']), 'text-dark'], ['Siap Dikirimi Perintah', pg_n($ringkas['siap']), 'text-success']] as [$lbl, $val, $cls]): ?>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 py-3 px-4 bg-white h-100">
            <span class="text-muted fw-bold text-uppercase" style="font-size:.75rem;letter-spacing:.5px;"><?= pg_e($lbl) ?></span>
            <h3 class="fw-bold mt-1 mb-0 fs-4 <?= $cls ?>"><?= pg_e($val) ?></h3>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- FILTER PERANGKAT -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4 pb-2">
        <form method="GET" action="" class="row g-2 align-items-center mb-3">
            <?= pg_hidden('input_perintah') ?>
            <div class="col-md-5">
                <input type="text" name="keyword" value="<?= pg_e($keyword) ?>" class="form-control form-control-sm" placeholder="Cari NoRef, IMEI, nomor SIM, IdPel, atau nama...">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua status</option>
                    <option value="AKTIF" <?= $status === 'AKTIF' ? 'selected' : '' ?>>Aktif</option>
                    <option value="TIDAK" <?= $status === 'TIDAK' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
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
                <?php if ($keyword !== '' || $status !== ''): ?><a href="<?= pg_e(pg_url('input_perintah')) ?>" class="btn btn-sm btn-outline-secondary px-3">Reset</a><?php endif; ?>
            </div>
        </form>
    </div>

    <!-- PILIH PERANGKAT + PERINTAH -->
    <form method="POST" action="<?= pg_e($url_pr(['hal' => $hal > 1 ? $hal : ''])) ?>" id="formPerintah">
        <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
        <input type="hidden" name="f_keyword" value="<?= pg_e($keyword) ?>">
        <input type="hidden" name="f_status" value="<?= pg_e($status) ?>">
        <input type="hidden" name="f_limit" value="<?= (int) $limit ?>">
        <input type="hidden" name="f_hal" value="<?= (int) $hal ?>">

        <div class="px-4 pb-3">
            <div class="border rounded-3 bg-light p-3">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-5">
                        <label for="cmd" class="form-label small fw-bold text-secondary text-uppercase">Perintah</label>
                        <select class="form-select" id="cmd" name="cmd" required>
                            <option value="">Pilih perintah...</option>
                            <?php foreach ($perintah as $c): ?>
                            <option value="<?= (int) $c['Id'] ?>" data-isi="<?= pg_e($c['IsiPesan']) ?>" data-ket="<?= pg_e($c['Keterangan']) ?>"
                                    data-param="<?= pr_butuh_param($c['IsiPesan']) ? '1' : '0' ?>">
                                <?= pg_e($c['IsiPesan']) ?> — <?= pg_e($c['Keterangan']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$perintah): ?><div class="form-text text-danger">Belum ada perintah aktif di baku_outbox.</div><?php endif; ?>
                    </div>
                    <div class="col-lg-3 d-none" id="boxParam">
                        <label for="param" class="form-label small fw-bold text-secondary text-uppercase">Nomor Admin</label>
                        <div class="input-group"><span class="input-group-text">+62</span>
                            <input type="text" class="form-control" id="param" name="param" inputmode="numeric" placeholder="81234567890"></div>
                    </div>
                    <div class="col-lg d-flex align-items-center justify-content-lg-end gap-3 flex-wrap">
                        <span class="small text-muted" id="infoPilih">0 dipilih</span>
                        <button type="submit" class="btn btn-primary px-4 shadow-sm" id="btnKirim" disabled>
                            <i class="bi bi-send me-1"></i>Kirim Perintah
                        </button>
                    </div>
                </div>
                <div class="small text-muted mt-2" id="previewTeks">Maksimal <?= (int) $maks_kirim ?> perangkat per pengiriman. Pilihan tidak terbawa antar halaman.</div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="text-secondary text-uppercase" style="font-size:.75rem;letter-spacing:.3px;white-space:nowrap;">
                        <th class="ps-4" style="width:40px"><input type="checkbox" class="form-check-input" id="cekSemua" aria-label="Pilih semua di halaman"></th>
                        <th>NoRef</th><th>Nomor SIM</th><th>IMEI Modem</th><th>IdPel</th><th>Nama Pelanggan</th><th>Kode UP</th><th class="pe-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$data): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                        <?= ($keyword !== '' || $status !== '') ? 'Tidak ada perangkat yang cocok dengan filter.' : 'Belum ada aset barang di wilayah Anda.' ?></td></tr>
                <?php else: foreach ($data as $r):
                    $nonaktif = $r['StatusData'] === 'TIDAK';
                    $tanpa_sim = trim((string) $r['SimId']) === '';
                    $bisa = !$nonaktif && !$tanpa_sim;
                ?>
                    <tr class="<?= $bisa ? '' : 'text-muted' ?>">
                        <td class="ps-4"><input type="checkbox" class="form-check-input cek-baris" name="pilih[]" value="<?= pg_e($r['NoRef']) ?>" <?= $bisa ? '' : 'disabled' ?> aria-label="Pilih <?= pg_e($r['NoRef']) ?>"></td>
                        <td class="font-monospace fw-semibold"><?= pg_e($r['NoRef']) ?></td>
                        <td class="font-monospace"><?= $tanpa_sim ? '<span class="badge bg-warning bg-opacity-10 text-warning-emphasis border border-warning border-opacity-50">Belum ada</span>' : pg_e($r['SimId']) ?></td>
                        <td class="font-monospace"><?= $r['ImeiModem'] === null ? '-' : pg_e($r['ImeiModem']) ?></td>
                        <td class="font-monospace"><?= $r['IdPel'] === null ? '<span class="text-muted">belum terpasang</span>' : pg_e($r['IdPel']) ?></td>
                        <td><?= $r['NamaPelanggan'] === null ? '-' : pg_e($r['NamaPelanggan']) ?></td>
                        <td class="font-monospace"><?= $r['UnitUp'] === null ? '-' : pg_e($r['UnitUp']) ?></td>
                        <td class="pe-4 text-center">
                            <span class="badge <?= $nonaktif ? 'bg-secondary' : 'bg-success' ?> bg-opacity-10 text-<?= $nonaktif ? 'secondary' : 'success' ?> border border-<?= $nonaktif ? 'secondary' : 'success' ?> border-opacity-50"><?= $nonaktif ? 'NONAKTIF' : pg_e($r['StatusData'] ?? '-') ?></span>
                        </td>
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
        boxParam = document.getElementById('boxParam'), param = document.getElementById('param'),
        info = document.getElementById('infoPilih'), btn = document.getElementById('btnKirim'),
        semua = document.getElementById('cekSemua'), prev = document.getElementById('previewTeks'),
        maks = <?= (int) $maks_kirim ?>;
    var awal = prev.textContent;
    function pilih() { return document.querySelectorAll('.cek-baris:checked').length; }
    function segarkan() {
        var n = pilih(), o = cmd.options[cmd.selectedIndex];
        info.textContent = n + ' dipilih';
        var butuh = o && o.dataset.param === '1';
        boxParam.classList.toggle('d-none', !butuh);
        param.required = !!butuh;
        btn.disabled = !(n > 0 && n <= maks && o && o.value);
        if (o && o.value) prev.textContent = 'Teks yang dikirim: ' + o.dataset.isi + (butuh ? '<nomor admin>' : '') + (n > maks ? '  —  melebihi batas ' + maks + ' perangkat' : '');
        else prev.textContent = awal;
    }
    cmd.addEventListener('change', segarkan);
    document.querySelectorAll('.cek-baris').forEach(function (c) { c.addEventListener('change', segarkan); });
    if (semua) semua.addEventListener('change', function () {
        document.querySelectorAll('.cek-baris:not(:disabled)').forEach(function (c) { c.checked = semua.checked; });
        segarkan();
    });
    form.addEventListener('submit', function (e) {
        var o = cmd.options[cmd.selectedIndex];
        if (!confirm('Kirim perintah "' + o.dataset.isi + '" (' + o.dataset.ket + ') ke ' + pilih() + ' perangkat?\n\nPerintah yang sudah dikirim tidak dapat dibatalkan.')) e.preventDefault();
    });
    segarkan();
})();
</script>