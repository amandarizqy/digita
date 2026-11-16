<?php
// templates/penggunaan/monitor_amr.php  -  Monitoring AMR
// Variabel dari modules/penggunaan/monitor_amr.php:
//   $halaman, $judul, $konfig, $tab, $c, $nyala, $data, $total_data, $kartu, $daftar_status, $keyword, $status, $jenis, $dari, $sampai,
//   $limit, $hal, $u, $unit_siap, $waktu_data, $pesan_error

$url = fn(array $ubah = []) => pg_url($halaman, array_merge([
    'tab' => $tab === 'putus' ? '' : $tab, 'status' => $status, 'keyword' => $keyword, 'jenis' => $jenis,
    'dari' => $dari, 'sampai' => $sampai, 'limit' => $limit == 25 ? '' : $limit], $ubah));
$ada_filter = $keyword !== '' || $status !== '' || $jenis !== '' || $dari !== '' || $sampai !== '';

// warna lencana status
$warna = fn(string $s): string => ['ANTREAN' => 'secondary', 'DIPROSES' => 'primary', 'GAGAL' => 'danger', 'LUNAS' => 'warning', 'NONAKTIF' => 'dark'][$s] ?? ($s === $c['berhasil'] ? 'success' : 'secondary');

echo pg_header([
    'title'    => $judul,
    'subtitle' => 'Tiket pemutusan dan penyalaan beserta hasil tindakannya (hanya baca). Wilayah: ' . $u['label'] . '.',
    'action'   => $halaman,
]);

if (!empty($pesan_error)) echo pg_alert('danger', $pesan_error);
?>

<ul class="nav nav-tabs mb-3">
    <?php foreach ($konfig as $k => $cf): ?>
    <li class="nav-item"><a class="nav-link <?= $tab === $k ? 'active fw-semibold' : 'text-secondary' ?>"
        href="<?= pg_e(pg_url($halaman, ['tab' => $k === 'putus' ? '' : $k])) ?>"><i class="bi <?= pg_e($cf['ikon']) ?> me-1"></i><?= pg_e($cf['label']) ?></a></li>
    <?php endforeach; ?>
</ul>

<?php if (!$unit_siap || !empty($pesan_error)) return; ?>

<!-- RINGKASAN (klik kartu = filter status) -->
<div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-3 mb-4">
    <?php foreach ($kartu as [$lbl, $val, $cls, $f, $ic]): $aktif_kartu = $status === $f; ?>
    <div class="col">
        <a href="<?= pg_e($url(['status' => $f, 'hal' => ''])) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm rounded-4 py-3 px-3 bg-white h-100 <?= $aktif_kartu ? 'border border-2 border-primary' : '' ?>">
                <span class="text-muted fw-bold text-uppercase" style="font-size:.7rem;letter-spacing:.5px;"><i class="bi <?= pg_e($ic) ?> me-1"></i><?= pg_e($lbl) ?></span>
                <h3 class="fw-bold mt-1 mb-0 fs-4 <?= $cls ?>"><?= pg_n($val) ?></h3>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4 pb-2">
        <form method="GET" action="" class="row g-2 align-items-center mb-3">
            <?= pg_hidden($halaman, ['tab' => $tab === 'putus' ? '' : $tab]) ?>
            <div class="col-md-4">
                <input type="text" name="keyword" value="<?= pg_e($keyword) ?>" class="form-control form-control-sm"
                       placeholder="Cari tiket, IdPel, nama, No TUL, sesi, operator, meter, HP...">
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Status">
                    <option value="">Semua status</option>
                    <?php foreach ($daftar_status as $s): ?><option value="<?= pg_e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= pg_e(ucfirst(strtolower($s))) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="jenis" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Jenis tiket">
                    <option value="">Semua jenis</option>
                    <option value="PREVENTIF" <?= $jenis === 'PREVENTIF' ? 'selected' : '' ?>>Preventif</option>
                    <option value="KOREKTIF" <?= $jenis === 'KOREKTIF' ? 'selected' : '' ?>>Korektif</option>
                </select>
            </div>
            <div class="col-6 col-md-2"><input type="date" name="dari" value="<?= pg_e($dari) ?>" class="form-control form-control-sm" title="Tiket dibuat dari tanggal" onchange="this.form.submit()"></div>
            <div class="col-6 col-md-2"><input type="date" name="sampai" value="<?= pg_e($sampai) ?>" class="form-control form-control-sm" title="Tiket dibuat sampai tanggal" onchange="this.form.submit()"></div>
            <div class="col-6 col-md-2">
                <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Baris per halaman">
                    <?php foreach ([10, 25, 50, 100] as $l): ?><option value="<?= $l ?>" <?= $limit === $l ? 'selected' : '' ?>><?= $l ?> / halaman</option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary px-3"><i class="bi bi-search me-1"></i>Cari</button>
                <?php if ($ada_filter): ?><a href="<?= pg_e(pg_url($halaman, ['tab' => $tab === 'putus' ? '' : $tab])) ?>" class="btn btn-sm btn-outline-secondary px-3">Reset</a><?php endif; ?>
            </div>
            <div class="col-md-6 d-flex align-items-center justify-content-md-end gap-3 small text-muted">
                <span>Data per <strong><?= pg_e($waktu_data) ?></strong></span>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="autoSegar">
                    <label class="form-check-label" for="autoSegar">Segarkan otomatis (15 dtk)</label>
                </div>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0" style="font-size:.85rem;">
            <thead class="table-light">
                <tr class="text-secondary text-uppercase" style="font-size:.72rem;letter-spacing:.3px;white-space:nowrap;">
                    <th class="ps-4"><?= $nyala ? 'Tiket Nyala' : 'Tiket Putus' ?></th>
                    <?php if ($nyala): ?><th>Tiket Putus</th><?php endif; ?>
                    <th>Jenis</th><th>No. TUL</th><th>IdPel</th><th>Nama Pelanggan</th><th>No. Meter</th><th>No. HP</th><th>Periode</th><th>UP</th>
                    <th>Sesi</th><th>Pengambil</th><th class="text-center">Status</th><th>Penindak</th><th>Waktu Tiket</th><th class="pe-4">Waktu Tindakan</th>
                </tr>
            </thead>
            <tbody>
            <?php $kolom = 14 + ($nyala ? 1 : 0); ?>
            <?php if (!$data): ?>
                <tr><td colspan="<?= $kolom ?>" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                    <?= $ada_filter ? 'Tidak ada data yang cocok dengan filter.' : 'Belum ada tiket di wilayah Anda.' ?></td></tr>
            <?php else: foreach ($data as $r): $w = $warna($r['Status']); $kor = $r['JenisTiket'] === 'KOREKTIF'; ?>
                <tr>
                    <td class="ps-4 font-monospace fw-semibold text-nowrap"><?= pg_e($r['Kode']) ?></td>
                    <?php if ($nyala): ?><td class="font-monospace small text-nowrap"><?= pg_e($r['TiketPutus']) ?></td><?php endif; ?>
                    <td><span class="badge bg-<?= $kor ? 'danger' : 'info' ?> bg-opacity-10 text-<?= $kor ? 'danger' : 'info' ?> border border-<?= $kor ? 'danger' : 'info' ?> border-opacity-50"><?= pg_e($r['JenisTiket']) ?></span></td>
                    <td class="font-monospace small text-nowrap"><?= $r['NoTul'] === null ? '-' : pg_e($r['NoTul']) ?></td>
                    <td class="font-monospace text-nowrap"><?= pg_e($r['IdPel']) ?></td>
                    <td><?= $r['NamaPelanggan'] === null ? '<span class="text-muted">-</span>' : pg_e($r['NamaPelanggan']) ?></td>
                    <td class="font-monospace small text-nowrap"><?= trim((string) $r['NomorMeter']) === '' ? '<span class="text-muted">-</span>' : pg_e($r['NomorMeter']) ?></td>
                    <td class="font-monospace small text-nowrap"><?= trim((string) $r['NomorHp']) === '' ? '<span class="text-muted">-</span>' : pg_e($r['NomorHp']) ?></td>
                    <td class="font-monospace small"><?= pg_e($r['Periode']) ?></td>
                    <td class="small"><?= pg_e($r['UnitUp']) ?></td>
                    <td class="font-monospace text-muted small text-nowrap"><?= trim((string) $r['Sesi']) === '' ? '-' : pg_e($r['Sesi']) ?></td>
                    <td class="small text-nowrap"><?= pg_e($r['Pengambil']) ?></td>
                    <td class="text-center"><span class="badge bg-<?= $w ?> bg-opacity-10 text-<?= $w === 'warning' ? 'warning-emphasis' : $w ?> border border-<?= $w ?> border-opacity-50 px-2 py-1"><?= pg_e($r['Status']) ?></span></td>
                    <td class="small text-nowrap"><?= $r['Penindak'] === null ? '<span class="text-muted">-</span>' : pg_e($r['Penindak']) ?></td>
                    <td class="text-muted small text-nowrap"><?= pg_e($r['WaktuData']) ?></td>
                    <td class="text-muted small text-nowrap pe-4"><?= $r['WaktuTindakan'] === null ? '-' : pg_e($r['WaktuTindakan']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?= pg_footer_tabel($hal, $limit, $total_data, fn($h) => $url(['hal' => $h > 1 ? $h : ''])) ?>
</div>

<script>
// Segarkan otomatis: muat ulang halaman tiap 15 detik (pilihan disimpan di browser). Ditunda saat tab tersembunyi atau saat pengguna sedang mengisi kolom.
(function () {
    var cb = document.getElementById('autoSegar'), KUNCI = 'pg_monitor_segar', MS = 15000, timer = null;
    function jadwal() {
        clearTimeout(timer);
        if (!cb.checked) return;
        timer = setTimeout(function () {
            var a = document.activeElement, mengetik = a && (a.tagName === 'INPUT' || a.tagName === 'SELECT') && a !== cb;
            if (document.hidden || mengetik) { jadwal(); return; }
            location.reload();
        }, MS);
    }
    try { cb.checked = localStorage.getItem(KUNCI) === '1'; } catch (e) {}
    cb.addEventListener('change', function () { try { localStorage.setItem(KUNCI, cb.checked ? '1' : '0'); } catch (e) {} jadwal(); });
    jadwal();
})();
</script>