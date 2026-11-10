<?php
// templates/penggunaan/penyalaan_amr.php  -  Penyalaan AMR
// Halaman: kartu Sesi ID + Create. Tiket yang diambil/dibuat tampil sebagai kartu POP-UP (modal) berisi keterangan pelanggan
// yang ringkas dan 8 tombol (salin / buka PPOB / Lunas / buka MDMS / Ready / Gagal). Memerlukan Bootstrap 5 JS (sudah dimuat layout).
// Variabel dari modules/penggunaan/penyalaan_amr.php:
//   $halaman, $judul, $pk, $fk (penyalaan), $putus, $csrf, $unit_siap, $sesi, $row (tiket berjalan | null), $sisa, $link,
//   $pesan_sukses, $pesan_error, $pesan_info

$ada    = $row !== null;
$sufiks = $putus ? 'OFF' : 'ON';

echo pg_header([
    'title'    => $judul,
    'subtitle' => $putus ? 'Create mengambil tiket pemutusan berikutnya, lalu cek di PPOB, proses di MDMS, dan catat hasilnya.'
                         : 'Create mengambil tiket penyalaan berikutnya, lalu cek di PPOB, proses di MDMS, dan catat hasilnya.',
    'action'   => $halaman,
]);

if (!empty($pesan_sukses)) echo pg_alert('success', $pesan_sukses);
if (!empty($pesan_error))  echo pg_alert('danger', $pesan_error);
if (!empty($pesan_info))   echo pg_alert('warning', $pesan_info, 'bi-info-circle-fill');
if (!$unit_siap) echo pg_alert('warning', 'Unit akun Anda belum diatur, sehingga tiket tidak bisa dibuat. Hubungi administrator.', 'bi-exclamation-triangle-fill');

if ($ada) {
    $tiket  = (string) $row[$pk];
    $nama   = $row['nama'] !== '' ? $row['nama'] : '(nama belum ada di DIL)';
    $idpel  = (string) $row['IdPel'];
    $jenis  = (string) $row['JenisTiket'];
    $meter  = (string) $row['meter_tpl'];
    $hp     = (string) $row['hp_tpl'];
    // keterangan ringkas: [label, nilai]
    $rincian = [];
    if (!$putus) $rincian[] = ['Tiket Putus', (string) $row[$fk]];
    $rincian[] = ['No. TUL', (string) ($row['NoTulTampil'] ?? '')];
    $rincian[] = ['Periode', trim((string) $row['ThblRek'])];
    $rincian[] = ['No. Meter', $meter];
    $rincian[] = ['No. HP', $hp];
    $rincian[] = ['Dibuat', (string) $row['WaktuData']];
}
?>
<!-- KARTU SESI -->
<div class="card border-0 shadow-sm rounded-4 d-flex">
    <div class="card-body p-4">
        <form method="POST" action="<?= pg_e(pg_url($halaman)) ?>" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
            <label for="f_sesi" class="form-label fw-semibold text-secondary">Sesi ID :</label>
            <div class="input-group">
                <input type="text" class="form-control font-monospace" id="f_sesi" value="<?= pg_e($ada ? $sesi : '') ?>" placeholder="Dibuat saat Create" readonly>
                <button type="submit" name="aksi" value="create" class="btn btn-primary px-4" <?= $ada || !$unit_siap ? 'disabled' : '' ?>><i class="bi bi-plus-circle me-1"></i> Create</button>
            </div>
            <?php if ($sisa !== null): ?><div class="form-text">Tiket yang masih bisa diambil/dibuat di wilayah Anda: <strong><?= pg_n($sisa) ?></strong></div><?php endif; ?>
        </form>

        <?php if ($ada): ?>
        <div class="d-flex align-items-center justify-content-between gap-3 mt-3 p-3 rounded-3 bg-primary bg-opacity-10 border border-primary border-opacity-25">
            <div class="small" style="min-width:0;">
                <div class="text-muted">Tiket berjalan</div>
                <div class="fw-bold font-monospace text-break"><?= pg_e($tiket) ?></div>
                <div class="text-truncate"><?= pg_e($nama) ?> &middot; <span class="font-monospace"><?= pg_e($idpel) ?></span></div>
            </div>
            <button type="button" class="btn btn-primary btn-sm px-3 flex-shrink-0" data-bs-toggle="modal" data-bs-target="#modalTiket"><i class="bi bi-box-arrow-up-right me-1"></i> Buka tiket</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($ada): ?>
<!-- KARTU POP-UP TIKET -->
<div class="modal fade" id="modalTiket" tabindex="-1" aria-labelledby="judulTiket" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" action="<?= pg_e(pg_url($halaman)) ?>" class="modal-content border-0 shadow-lg rounded-4" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
            <input type="hidden" name="tiket" value="<?= pg_e($tiket) ?>">

            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <div style="min-width:0;">
                    <div class="small text-muted text-uppercase fw-bold" style="letter-spacing:.5px;"><?= pg_e($judul) ?> &middot; Sesi <span class="font-monospace"><?= pg_e($sesi) ?></span></div>
                    <h5 class="modal-title font-monospace fw-bold text-break mt-1" id="judulTiket"><?= pg_e($tiket) ?></h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body px-4">
                <!-- keterangan pelanggan (ringkas) -->
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                    <div style="min-width:0;">
                        <div class="fs-5 fw-bold text-break"><?= pg_e($nama) ?></div>
                        <div class="font-monospace text-muted">IDPEL <?= pg_e($idpel) ?></div>
                    </div>
                    <span class="badge flex-shrink-0 px-3 py-2 <?= $jenis === 'KOREKTIF' ? 'bg-danger' : 'bg-primary' ?> bg-opacity-10 text-<?= $jenis === 'KOREKTIF' ? 'danger' : 'primary' ?> border border-opacity-50 border-<?= $jenis === 'KOREKTIF' ? 'danger' : 'primary' ?>"><?= pg_e($jenis) ?></span>
                </div>
                <div class="row g-2 mb-3">
                    <?php foreach ($rincian as [$label, $nilai]): ?>
                    <div class="col-6 col-md-4">
                        <div class="rounded-3 bg-light px-3 py-2 h-100">
                            <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.4px;"><?= pg_e($label) ?></div>
                            <div class="fw-semibold font-monospace small text-break"><?= trim($nilai) === '' ? '<span class="text-muted">-</span>' : pg_e($nilai) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- TOMBOL 4 x 2 -->
                <div class="row g-2 row-cols-2 row-cols-md-4">
                    <div class="col"><button type="button" class="btn btn-outline-secondary w-100" data-salin="<?= pg_e($idpel) ?>"><i class="bi bi-clipboard me-1"></i> Salin IDPEL</button></div>
                    <div class="col"><button type="button" class="btn btn-outline-secondary w-100" data-salin="<?= pg_e($hp) ?>"><i class="bi bi-clipboard me-1"></i> Salin No HP</button></div>
                    <div class="col"><a class="btn btn-outline-primary w-100" data-tautan="ppob" href="<?= pg_e($link['ppob']) ?>" <?= $link['ppob'] !== '#' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><i class="bi bi-box-arrow-up-right me-1"></i> Buka PPOB</a></div>
                    <div class="col"><button type="submit" name="aksi" value="lunas" class="btn btn-warning w-100" formnovalidate data-konfirmasi="Catat LUNAS untuk tiket ini dan lanjut ke tiket berikutnya?"><i class="bi bi-cash-coin me-1"></i> Lunas</button></div>

                    <div class="col"><button type="button" class="btn btn-outline-secondary w-100" data-salin="<?= pg_e($meter) ?>"><i class="bi bi-clipboard me-1"></i> Salin No Meter</button></div>
                    <div class="col"><a class="btn btn-outline-primary w-100" data-tautan="mdms" href="<?= pg_e($link['mdms']) ?>" <?= $link['mdms'] !== '#' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><i class="bi bi-box-arrow-up-right me-1"></i> Buka MDMS</a></div>
                    <div class="col"><button type="submit" name="aksi" value="ready" class="btn btn-success w-100" formnovalidate data-konfirmasi="Catat Ready <?= $sufiks ?> untuk tiket ini dan lanjut ke tiket berikutnya?"><i class="bi bi-check-circle me-1"></i> Ready <?= $sufiks ?></button></div>
                    <div class="col"><button type="submit" name="aksi" value="gagal" class="btn btn-outline-danger w-100" formnovalidate data-konfirmasi="Catat GAGAL <?= $sufiks ?> untuk tiket ini dan lanjut ke tiket berikutnya?"><i class="bi bi-x-circle me-1"></i> Gagal <?= $sufiks ?></button></div>
                </div>
                <div id="infoTombol" class="small text-muted mt-2" style="min-height:1.2em;"></div>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('modalTiket'), info = document.getElementById('infoTombol'), terkirim = false;
    function kabar(t) { info.textContent = t; clearTimeout(kabar.w); kabar.w = setTimeout(function () { info.textContent = ''; }, 2500); }

    // ---- kartu pop-up terbuka otomatis saat halaman dimuat ----
    window.addEventListener('load', function () {
        if (window.bootstrap && bootstrap.Modal) { bootstrap.Modal.getOrCreateInstance(modal).show(); }
        else { modal.classList.add('show'); modal.style.display = 'block'; modal.removeAttribute('aria-hidden'); }   // cadangan bila Bootstrap JS tidak termuat
    });

    // ---- tombol salin ----
    function salin(teks) {
        if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(teks);
        return new Promise(function (ok, gagal) {
            var t = document.createElement('textarea'); t.value = teks; t.style.position = 'fixed'; t.style.opacity = '0';
            modal.appendChild(t); t.select();                                  // di dalam modal agar fokus tidak ditahan modal
            try { document.execCommand('copy') ? ok() : gagal(); } catch (e) { gagal(e); } finally { t.remove(); }
        });
    }
    document.querySelectorAll('[data-salin]').forEach(function (b) {
        b.addEventListener('click', function () {
            var teks = (b.getAttribute('data-salin') || '').trim();
            if (!teks) { kabar('Datanya kosong, tidak ada yang disalin.'); return; }
            salin(teks).then(function () { kabar('Tersalin: ' + teks); }, function () { kabar('Gagal menyalin. Salin manual dari keterangan.'); });
        });
    });

    // ---- tautan yang belum diatur ('#') ----
    document.querySelectorAll('[data-tautan]').forEach(function (a) {
        if (a.getAttribute('href') === '#') a.addEventListener('click', function (e) { e.preventDefault(); kabar('Tautan belum diatur (isi $link di modules/penggunaan/penyalaan_amr.php).'); });
    });

    // ---- tombol hasil: konfirmasi, lalu cegah kirim ganda ----
    document.querySelectorAll('[data-konfirmasi]').forEach(function (b) {
        b.addEventListener('click', function (e) {
            if (terkirim || !confirm(b.getAttribute('data-konfirmasi'))) { e.preventDefault(); return; }
            terkirim = true;
        });
    });
    window.addEventListener('pageshow', function (e) { if (e.persisted) terkirim = false; });
})();
</script>
<?php endif; ?>