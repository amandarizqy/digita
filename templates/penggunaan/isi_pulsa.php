<?php
// templates/penggunaan/isi_pulsa.php  -  Isi Pulsa: (1) pilih SIM, (2) isi data transaksi
// Variabel dari modules/penggunaan/isi_pulsa.php:
//   $unit_siap, $u, $keyword, $hal, $limit, $cari, $total_cari, $sim_pilih, $csrf, $hanya_prabayar, $boleh_monitor, $pesan_sukses, $pesan_error

$url_ip = fn(array $ubah = []) => pg_url('isi_pulsa', array_merge(['keyword' => $keyword], $ubah));

echo pg_header([
    'title'    => 'Isi Pulsa',
    'subtitle' => 'Catat pengisian pulsa SIM' . ($hanya_prabayar ? ' prabayar' : '') . ' (tabel transaksi pulsa). Wilayah: ' . $u['label'] . '.',
    'action'   => 'isi_pulsa',
    'buttons'  => $boleh_monitor ? [['label' => 'Monitoring Isi Pulsa', 'icon' => 'bi-display', 'href' => pg_url('monitor_isi_pulsa'),
                                     'class' => 'btn btn-outline-primary btn-sm px-3 shadow-sm rounded-2 bg-white']] : [],
]);

if (!empty($pesan_sukses)) echo pg_alert('success', $pesan_sukses);
if (!empty($pesan_error))  echo pg_alert('danger', $pesan_error);

if (!$unit_siap) {
    echo pg_alert('danger', 'Akun Anda belum memiliki <strong>Unit UPI</strong>, sehingga halaman ini tidak dapat dipakai. Hubungi administrator.');
    return;
}
$tgl_fmt = fn($v) => $v === null ? '-' : pg_e($v);

// Kredit saat ini: NULL dianggap 0 (angka polos untuk input/JS, angka terformat untuk tampilan)
$kredit_sekarang     = (int) ($sim_pilih['JumlahKredit'] ?? 0);
$kredit_sekarang_fmt = number_format($kredit_sekarang, 0, ',', '.');
?>

<!-- LANGKAH 1: PILIH SIM -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4 pb-2">
        <h6 class="fw-bold mb-3"><span class="badge <?= $sim_pilih ? 'bg-success' : 'bg-primary' ?> rounded-circle me-2"><?= $sim_pilih ? '✓' : '1' ?></span>Cari &amp; Pilih SIM
            <span class="fw-normal text-muted small">(aktif<?= $hanya_prabayar ? ', prabayar' : '' ?>, di wilayah Anda)</span></h6>
        <form method="GET" action="" class="row g-2 align-items-center mb-3">
            <?= pg_hidden('isi_pulsa') ?>
            <div class="col-md-8">
                <input type="text" name="keyword" value="<?= pg_e($keyword) ?>" class="form-control form-control-sm" placeholder="Cari nomor SIM atau SIM ID...">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary px-3"><i class="bi bi-search me-1"></i>Cari</button>
                <?php if ($keyword !== ''): ?><a href="<?= pg_e(pg_url('isi_pulsa')) ?>" class="btn btn-sm btn-outline-secondary px-3">Reset</a><?php endif; ?>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="text-secondary text-uppercase" style="font-size:.75rem;letter-spacing:.3px;white-space:nowrap;">
                    <th class="ps-4">Nomor SIM</th><th>SIM ID</th><th>Jenis</th><th>Tgl Pulsa Terakhir</th><th>Tgl Tempo</th><th class="text-end">Jumlah Kredit</th><th class="text-end pe-4">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$cari): ?>
                <tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-1 text-secondary"></i>
                    <?= $keyword !== '' ? 'Tidak ada SIM yang cocok.' : 'Belum ada SIM aktif' . ($hanya_prabayar ? ' prabayar' : '') . ' di wilayah Anda.' ?></td></tr>
            <?php else: foreach ($cari as $r): $dipilih = $sim_pilih && $sim_pilih['SimId'] === $r['SimId']; ?>
                <tr class="<?= $dipilih ? 'table-primary' : '' ?>">
                    <td class="ps-4 font-monospace fw-semibold"><?= $r['NomorAkun'] === null ? '-' : pg_e($r['NomorAkun']) ?></td>
                    <td class="font-monospace small"><?= pg_e($r['SimId']) ?></td>
                    <td><span class="badge bg-secondary bg-opacity-10 text-secondary border"><?= pg_e($r['JenisProduk']) ?></span></td>
                    <td><?= $tgl_fmt($r['TglPulsaTerakhir']) ?></td>
                    <td><?= $tgl_fmt($r['TglTempo']) ?></td>
                    <td class="text-end"><?= number_format((int) ($r['JumlahKredit'] ?? 0), 0, ',', '.') ?></td>
                    <td class="text-end pe-4">
                        <?php if ($dipilih): ?><span class="badge bg-primary"><i class="bi bi-check2 me-1"></i>Dipilih</span>
                        <?php else: ?><a href="<?= pg_e($url_ip(['sim' => $r['SimId'], 'hal' => $hal > 1 ? $hal : ''])) ?>" class="btn btn-sm btn-outline-primary">Pilih</a><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?= pg_footer_tabel($hal, $limit, $total_cari, fn($h) => $url_ip(['sim' => $sim_pilih['SimId'] ?? '', 'hal' => $h > 1 ? $h : ''])) ?>
</div>

<!-- LANGKAH 2: DATA TRANSAKSI -->
<div class="card border-0 shadow-sm rounded-4 mb-4 <?= $sim_pilih ? '' : 'opacity-50' ?>">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-3"><span class="badge bg-primary rounded-circle me-2">2</span>Data Isi Pulsa</h6>
        <?php if (!$sim_pilih): ?>
            <div class="small text-muted">Pilih SIM pada langkah 1 terlebih dahulu.</div>
        <?php else: ?>
        <form method="POST" action="<?= pg_e($url_ip(['sim' => $sim_pilih['SimId']])) ?>" class="row g-3 align-items-end" id="formPulsa">
            <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
            <input type="hidden" name="sim" value="<?= pg_e($sim_pilih['SimId']) ?>">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-secondary text-uppercase">Nomor SIM</label>
                <input type="text" class="form-control font-monospace bg-light" value="<?= pg_e($sim_pilih['NomorAkun'] ?? '-') ?>" disabled>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-secondary text-uppercase">Kredit Saat Ini</label>
                <input type="text" class="form-control bg-light" value="<?= pg_e($kredit_sekarang_fmt) ?>" disabled>
            </div>
            <div class="col-md-4">
                <label for="tgltrx" class="form-label small fw-bold text-secondary text-uppercase">Tanggal Transaksi</label>
                <input type="date" class="form-control" id="tgltrx" name="tgltrx" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-4">
                <label for="isikredit" class="form-label small fw-bold text-secondary text-uppercase">Isi Kredit</label>
                <input type="number" class="form-control" id="isikredit" name="isikredit" min="1" step="1" required>
            </div>
            <div class="col-md-4">
                <label for="jumlahkredit" class="form-label small fw-bold text-secondary text-uppercase">Jumlah Kredit (setelah isi)</label>
                <input type="number" class="form-control" id="jumlahkredit" name="jumlahkredit" min="0" step="1" value="<?= $kredit_sekarang ?>" required>
            </div>
            <div class="col-md-4">
                <label for="tgltempo" class="form-label small fw-bold text-secondary text-uppercase">Tanggal Tempo</label>
                <input type="date" class="form-control" id="tgltempo" name="tgltempo" value="<?= pg_e($sim_pilih['TglTempo'] ?? '') ?>" required>
            </div>
            <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="small text-muted">No. transaksi dibuat otomatis (TP + yymmdd + 4 digit urut). Unit mengikuti unit SIM. Data kredit &amp; tempo pada master SIM ikut diperbarui.</span>
                <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="bi bi-save me-1"></i>Simpan Transaksi</button>
            </div>
        </form>
        <script>
        (function () {
            var saat = <?= $kredit_sekarang ?>, isi = document.getElementById('isikredit'), jml = document.getElementById('jumlahkredit'), manual = false;
            jml.addEventListener('input', function () { manual = true; });
            isi.addEventListener('input', function () { if (!manual) jml.value = saat + (parseInt(isi.value, 10) || 0); });
        })();
        </script>
        <?php endif; ?>
    </div>
</div>