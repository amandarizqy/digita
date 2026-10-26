<?php
// templates/penggunaan/input_perintah.php  -  Input Perintah: pilih IdPel (yang memiliki aset terpasang) + perintah, lalu kirim
// Variabel dari modules/penggunaan/input_perintah.php:
//   $daftar_perintah, $data, $total_data, $ringkas, $keyword, $relay, $limit, $hal, $csrf, $u, $unit_siap, $maks_kirim,
//   $format_tgl_tiket, $boleh_monitor, $pesan_sukses, $pesan_error

$url_pr = fn(array $ubah = []) => pg_url('input_perintah', array_merge(
    ['keyword' => $keyword, 'relay' => $relay, 'limit' => $limit == 25 ? '' : $limit], $ubah));

echo pg_header([
    'title'    => 'Input Perintah',
    'subtitle' => 'Pilih IdPel yang memiliki aset terpasang, pilih perintah, lalu kirim lewat SMS Gateway. Wilayah: ' . $u['label'] . '.',
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
    <?php foreach ([['IdPel dengan Aset Terpasang', $ringkas['total'], 'text-dark'], ['Relay ON', $ringkas['on'], 'text-success'], ['Relay OFF', $ringkas['off'], 'text-danger']] as [$lbl, $val, $cls]): ?>
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
            <?= pg_hidden('input_perintah') ?>
            <div class="col-md-5">
                <input type="text" name="keyword" value="<?= pg_e($keyword) ?>" class="form-control form-control-sm" placeholder="Cari IdPel, nama, NoRef, nomor barang, atau IMEI...">
            </div>
            <div class="col-md-2">
                <select name="relay" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Relay">
                    <option value="">Semua relay</option>
                    <option value="ON" <?= $relay === 'ON' ? 'selected' : '' ?>>Relay ON</option>
                    <option value="OFF" <?= $relay === 'OFF' ? 'selected' : '' ?>>Relay OFF</option>
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
                <?php if ($keyword !== '' || $relay !== ''): ?><a href="<?= pg_e(pg_url('input_perintah')) ?>" class="btn btn-sm btn-outline-secondary px-3">Reset</a><?php endif; ?>
            </div>
        </form>
    </div>

    <form method="POST" action="<?= pg_e($url_pr(['hal' => $hal > 1 ? $hal : ''])) ?>" id="formPerintah">
        <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
        <input type="hidden" name="f_keyword" value="<?= pg_e($keyword) ?>">
        <input type="hidden" name="f_relay" value="<?= pg_e($relay) ?>">
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
                            <option value="<?= pg_e($kode) ?>" data-bahaya="<?= $c['bahaya'] ? '1' : '0' ?>" data-ket="<?= pg_e($c['label']) ?>"><?= pg_e($kode) ?> — <?= pg_e($c['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 d-none" id="boxJenis">
                        <label for="jenis" class="form-label small fw-bold text-secondary text-uppercase">Jenis Tiket</label>
                        <select class="form-select" id="jenis" name="jenis">
                            <option value="">Pilih jenis tiket...</option>
                            <option value="PREVENTIF">PREVENTIF</option>
                            <option value="KOREKTIF">KOREKTIF</option>
                        </select>
                    </div>
                    <div class="col-lg d-flex align-items-center justify-content-lg-end gap-3 flex-wrap">
                        <span class="small text-muted" id="infoPilih">0 dipilih</span>
                        <button type="submit" class="btn btn-primary px-4 shadow-sm" id="btnKirim" disabled><i class="bi bi-send me-1"></i>Kirim Perintah</button>
                    </div>
                </div>
                <!-- <div class="small text-muted mt-2">
                    Maksimal <?= (int) $maks_kirim ?> IdPel per pengiriman; pilihan tidak terbawa antar halaman. SMS dikirim ke semua aset IdPel terpilih.
                    <strong>PLNOFF</strong> menerbitkan tiket putus dan <strong>PLNON</strong> tiket nyala, dengan nomor
                    <span class="font-monospace">T2.&lt;UnitUp&gt;<?= $format_tgl_tiket === 'ymd' ? 'yymmdd' : 'yyyymmdd' ?>&lt;4 digit urut&gt;</span>.
                    Tiket putus tidak dibuat baru bila IdPel sudah punya tiket putus aktif; tiket nyala hanya dibuat bila sudah ada tiket putus aktif dan belum punya tiket nyala.
                    <strong>PLNSTATUS</strong> tidak menerbitkan tiket.
                </div> -->
            </div>
        </div>

        <!-- DAFTAR IDPEL -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="text-secondary text-uppercase" style="font-size:.75rem;letter-spacing:.3px;white-space:nowrap;">
                        <th class="ps-4" style="width:40px"><input type="checkbox" class="form-check-input" id="cekSemua" aria-label="Pilih semua di halaman"></th>
                        <th>IdPel</th><th>Nama Pelanggan</th><th class="text-center">Aset</th><th>NoRef</th><th>Nomor Barang</th><th>IMEI Modem</th><th>Unit UP</th><th class="pe-4 text-center">Relay</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$data): ?>
                    <tr><td colspan="9" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                        <?= ($keyword !== '' || $relay !== '') ? 'Tidak ada IdPel yang cocok dengan filter.' : 'Belum ada IdPel dengan aset terpasang di wilayah Anda.' ?></td></tr>
                <?php else: foreach ($data as $r):
                    $bisa = pr_nomor($r['NomorBarang'] ?? '') !== null || (int) $r['jml'] > 1; $on = $r['StatusRelay'] === 'ON'; ?>
                    <tr class="<?= $bisa ? '' : 'text-muted' ?>">
                        <td class="ps-4"><input type="checkbox" class="form-check-input cek-baris" name="pilih[]" value="<?= pg_e($r['IdPel']) ?>" <?= $bisa ? '' : 'disabled' ?> aria-label="Pilih <?= pg_e($r['IdPel']) ?>"></td>
                        <td class="font-monospace fw-semibold"><?= pg_e($r['IdPel']) ?></td>
                        <td><?= $r['NamaPelanggan'] === null ? '-' : pg_e($r['NamaPelanggan']) ?></td>
                        <td class="text-center"><?= (int) $r['jml'] ?></td>
                        <td class="font-monospace small"><?= pg_e($r['NoRef']) ?></td>
                        <td class="font-monospace"><?= pr_nomor($r['NomorBarang'] ?? '') !== null ? pg_e($r['NomorBarang']) : '<span class="badge bg-warning bg-opacity-10 text-warning-emphasis border border-warning border-opacity-50">Tidak valid</span>' ?></td>
                        <td class="font-monospace small"><?= $r['ImeiModem'] === null ? '-' : pg_e($r['ImeiModem']) ?></td>
                        <td class="font-monospace"><?= $r['UnitUp'] === null ? '-' : pg_e($r['UnitUp']) ?></td>
                        <td class="pe-4 text-center"><span class="badge bg-<?= $on ? 'success' : 'danger' ?> bg-opacity-10 text-<?= $on ? 'success' : 'danger' ?> border border-<?= $on ? 'success' : 'danger' ?> border-opacity-50"><?= pg_e($r['StatusRelay'] ?? '-') ?></span></td>
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
    var boxJenis = document.getElementById('boxJenis'), jenis = document.getElementById('jenis');
    function butuhJenis() { return cmd.value === 'PLNOFF' || cmd.value === 'PLNON'; }
    function segarkan() {
        var n = pilih();
        info.textContent = n + ' dipilih' + (n > maks ? ' — melebihi batas ' + maks : '');
        boxJenis.classList.toggle('d-none', !butuhJenis());
        jenis.required = butuhJenis();
        btn.disabled = !(n > 0 && n <= maks && cmd.value && (!butuhJenis() || jenis.value));
    }
    jenis.addEventListener('change', segarkan);
    cmd.addEventListener('change', segarkan);
    document.querySelectorAll('.cek-baris').forEach(function (c) { c.addEventListener('change', segarkan); });
    if (semua) semua.addEventListener('change', function () {
        document.querySelectorAll('.cek-baris:not(:disabled)').forEach(function (c) { c.checked = semua.checked; });
        segarkan();
    });
    form.addEventListener('submit', function (e) {
        var o = cmd.options[cmd.selectedIndex];
        var pesan = 'Kirim perintah ' + cmd.value + ' (' + o.dataset.ket + ') untuk ' + pilih() + ' IdPel terpilih'
                  + (butuhJenis() ? ' dengan tiket ' + jenis.value : '') + '?';
        if (o.dataset.bahaya === '1') pesan += '\n\nPERHATIAN: perintah ini langsung memengaruhi aliran listrik pelanggan dan tidak dapat dibatalkan.';
        if (!confirm(pesan)) e.preventDefault();
    });
    segarkan();
})();
</script>