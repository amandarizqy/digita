<?php
// templates/penggunaan/input_pelunasan.php  -  Input Pelunasan (form)
// Variabel dari modules/penggunaan/input_pelunasan.php:
//   $u, $unit_siap, $csrf, $idpel_cari, $cari_salah, $nama_cari, $tagihan, $boleh_monitor, $pesan_sukses, $pesan_error

echo pg_header([
    'title'    => 'Input Pelunasan',
    'subtitle' => 'Catat pembayaran tagihan pelanggan. Tagihan diambil dari Saldo; kode unit otomatis dari akun yang login.',
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
?>

<div class="small text-muted mb-3"><i class="bi bi-geo-alt me-1"></i>Wilayah: <strong><?= pg_e($u['label']) ?></strong></div>

<!-- LANGKAH 1: CARI TAGIHAN -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-3"><span class="badge bg-primary rounded-circle me-2">1</span>Cari Tagihan Pelanggan</h6>
        <form method="GET" action="" class="row g-3 align-items-end">
            <?= pg_hidden('input_pelunasan') ?>
            <div class="col-md-5">
                <label for="idpel_cari" class="form-label small fw-bold text-secondary text-uppercase">IdPel</label>
                <input type="text" class="form-control font-monospace" id="idpel_cari" name="idpel" maxlength="12" pattern="\d{1,12}"
                       value="<?= pg_e($idpel_cari ?? ($_GET['idpel'] ?? '')) ?>" placeholder="12 digit" required autofocus>
            </div>
            <div class="col-md-3 d-grid">
                <button type="submit" class="btn btn-primary shadow-sm"><i class="bi bi-search me-1"></i> Cari Tagihan</button>
            </div>
        </form>
        <?php if ($cari_salah): ?><div class="text-danger small mt-2">IdPel harus berupa angka (maksimal 12 digit).</div><?php endif; ?>
    </div>
</div>

<?php if ($idpel_cari !== null): ?>
<!-- LANGKAH 2: CATAT PELUNASAN -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-3"><span class="badge bg-primary rounded-circle me-2">2</span>Catat Pelunasan</h6>

        <?php if (!$tagihan): ?>
            <div class="alert alert-warning border-0 mb-0">
                <i class="bi bi-exclamation-triangle me-2"></i>Tidak ada tagihan untuk IdPel <strong class="font-monospace"><?= pg_e($idpel_cari) ?></strong>
                di wilayah Anda.
                <?php if (pga_boleh('input_saldo')): ?>Input dulu di <a href="<?= pg_e(pg_url('input_saldo')) ?>" class="alert-link">Input Saldo</a>.<?php endif; ?>
            </div>
        <?php else: ?>
        <form method="POST" action="<?= pg_e(pg_url('input_pelunasan', ['idpel' => $idpel_cari])) ?>" class="row g-3" id="formPelunasan">
            <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
            <input type="hidden" name="idpel" value="<?= pg_e($idpel_cari) ?>">

            <div class="col-md-4">
                <label class="form-label small fw-bold text-secondary text-uppercase">IdPel</label>
                <input type="text" class="form-control font-monospace bg-light" value="<?= pg_e($idpel_cari) ?>" disabled>
            </div>
            <div class="col-md-8">
                <label class="form-label small fw-bold text-secondary text-uppercase">Nama Pelanggan</label>
                <input type="text" class="form-control bg-light" value="<?= $nama_cari !== '' ? pg_e($nama_cari) : '-' ?>" disabled>
            </div>

            <div class="col-md-4">
                <label for="thblrek" class="form-label small fw-bold text-secondary text-uppercase">Periode Tagihan (ThBlRek)</label>
                <select class="form-select font-monospace" id="thblrek" name="thblrek" required>
                    <option value="">Pilih periode...</option>
                    <?php foreach ($tagihan as $t): $lunas = $t['TglBayar'] !== null; ?>
                    <option value="<?= pg_e($t['ThBlRek']) ?>" data-rptag="<?= (int) $t['RpTag'] ?>"
                            data-tgl="<?= pg_e($t['TglBayar'] ?? '') ?>" data-rpbk="<?= $t['RpBK'] === null ? '' : (int) $t['RpBK'] ?>">
                        <?= pg_e($t['ThBlRek']) ?> — Rp <?= pg_n($t['RpTag']) ?> — <?= $lunas ? 'sudah lunas (' . pg_e($t['TglBayar']) . ')' : 'belum lunas' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-secondary text-uppercase">Tagihan (RpTag)</label>
                <div class="input-group"><span class="input-group-text">Rp</span>
                    <input type="text" class="form-control bg-light text-end" id="rptag_tampil" value="-" disabled></div>
                <div class="form-text">Diambil dari Saldo (dpp).</div>
            </div>
            <div class="col-md-4">
                <label for="rpbk" class="form-label small fw-bold text-secondary text-uppercase">Biaya Keterlambatan (RpBK)</label>
                <div class="input-group"><span class="input-group-text">Rp</span>
                    <input type="number" class="form-control" id="rpbk" name="rpbk" min="0" step="1" value="0" required></div>
            </div>

            <div class="col-md-4">
                <label for="tglbayar" class="form-label small fw-bold text-secondary text-uppercase">Tanggal Bayar</label>
                <input type="date" class="form-control" id="tglbayar" name="tglbayar" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-8 d-flex align-items-end justify-content-between flex-wrap gap-2">
                <span class="small text-muted" id="info_lunas"></span>
                <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="bi bi-save me-1"></i> Simpan Pelunasan</button>
            </div>
        </form>
        <script>
        (function () {
            var sel = document.getElementById('thblrek'), rp = document.getElementById('rptag_tampil'),
                tgl = document.getElementById('tglbayar'), bk = document.getElementById('rpbk'), info = document.getElementById('info_lunas'),
                today = tgl.value;
            sel.addEventListener('change', function () {
                var o = sel.options[sel.selectedIndex];
                if (!o.value) { rp.value = '-'; info.textContent = ''; return; }
                rp.value = Number(o.dataset.rptag).toLocaleString('id-ID');
                if (o.dataset.tgl) {            // sudah ada pelunasan: tampilkan datanya, simpan = memperbarui
                    tgl.value = o.dataset.tgl; bk.value = o.dataset.rpbk || 0;
                    info.innerHTML = '<i class="bi bi-info-circle me-1"></i>Periode ini sudah dilunasi; menyimpan akan memperbarui data.';
                } else { tgl.value = today; bk.value = 0; info.textContent = ''; }
            });
        })();
        </script>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>