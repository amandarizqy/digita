<?php
// templates/penggunaan/pemutusan_amr.php  -  Pemutusan AMR
// Halaman: kartu Sesi ID + Create. Tiket yang diambil/dibuat tampil sebagai kartu POP-UP (modal) berisi keterangan pelanggan
// yang ringkas dan tombol bertahap: 1. PPOB -> 2. MDMS / Lunas -> 3. Ready OFF / Gagal OFF (Ulangi = kembali ke langkah 1). Memerlukan Bootstrap 5 JS (sudah dimuat layout).
// Variabel dari modules/penggunaan/pemutusan_amr.php:
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
    $detail_tiket = $tiket . ' · ' . $nama . ' (' . $idpel . ')';      // keterangan di kartu konfirmasi
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
                <input type="text" class="form-control font-monospace" id="f_sesi" value="<?= pg_e($sesi) ?>" placeholder="Dibuat saat Create" readonly>
                <button type="submit" name="aksi" value="create" class="btn btn-primary px-4" <?= $ada || !$unit_siap ? 'disabled' : '' ?>><i class="bi bi-plus-circle me-1"></i> Create</button>
                <button type="submit" name="aksi" value="hapus_sesi" class="btn btn-outline-danger px-3" formnovalidate <?= $sesi === '' || !$unit_siap ? 'disabled' : '' ?>
                        data-konfirmasi="<?= pg_e($ada ? 'Tiket yang sedang berjalan akan dilepas kembali ke antrean.' : 'Sesi ini akan dihapus.') ?>" data-judul="Hapus sesi?" data-detail="Sesi <?= pg_e($sesi) ?>" data-ya="Ya, hapus sesi"><i class="bi bi-trash3 me-1"></i> Hapus Sesi</button>
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

<!-- KARTU KONFIRMASI: pengganti dialog "localhost says" bawaan browser (dipakai semua tombol ber-atribut data-konfirmasi) -->
<div id="kartuKonfirmasi" role="dialog" aria-modal="true" aria-labelledby="kkJudul" style="display:none;position:fixed;inset:0;z-index:2000;align-items:center;justify-content:center;padding:1rem;background:rgba(15,23,42,.55);">
    <div class="card border-0 shadow-lg rounded-4" style="width:min(420px,100%);">
        <div class="card-body p-4 text-center">
            <div id="kkIkon" class="mx-auto mb-3"></div>
            <h5 id="kkJudul" class="fw-bold mb-1"></h5>
            <p id="kkPesan" class="text-secondary mb-3"></p>
            <div id="kkDetail" class="small font-monospace text-break bg-light rounded-3 px-3 py-2 mb-3"></div>
            <div class="d-flex gap-2">
                <button type="button" id="kkBatal" class="btn btn-outline-secondary flex-fill">Batal</button>
                <button type="button" id="kkYa" class="btn flex-fill fw-semibold">Ya, lanjutkan</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var kartu = document.getElementById('kartuKonfirmasi'), ikon = document.getElementById('kkIkon'), judul = document.getElementById('kkJudul'),
        pesan = document.getElementById('kkPesan'), detail = document.getElementById('kkDetail'), ya = document.getElementById('kkYa'), batal = document.getElementById('kkBatal'),
        terkirim = false, selesai = null, fokusAwal = null;
    var IKON = {danger: 'bi-exclamation-octagon-fill', warning: 'bi-exclamation-triangle-fill', success: 'bi-check-circle-fill', primary: 'bi-question-circle-fill'};

    function tutup(hasil) {
        kartu.style.display = 'none';
        document.removeEventListener('keydown', keyboard, true);
        var cb = selesai; selesai = null;
        if (fokusAwal && fokusAwal.focus) { try { fokusAwal.focus(); } catch (e) {} }
        if (cb) cb(hasil);
    }
    function keyboard(e) {
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); tutup(false); }
        else if (e.key === 'Tab') {                                            // fokus tetap di dalam kartu
            var urut = [batal, ya], i = urut.indexOf(document.activeElement);
            e.preventDefault(); urut[(i + (e.shiftKey ? -1 : 1) + urut.length) % urut.length].focus();
        }
    }
    function tanya(b) {
        return new Promise(function (lanjut) {
            selesai = lanjut; fokusAwal = b;
            var nada = (b.className.match(/btn-(?:outline-)?(danger|success|warning|primary)\b/) || [])[1] || 'primary';
            ikon.className = 'mx-auto mb-3 rounded-circle d-flex align-items-center justify-content-center bg-' + nada + ' bg-opacity-10 text-' + nada;
            ikon.style.cssText = 'width:60px;height:60px;font-size:1.7rem;';
            ikon.innerHTML = '<i class="bi ' + IKON[nada] + '"></i>';
            judul.textContent  = b.getAttribute('data-judul') || 'Konfirmasi';
            pesan.textContent  = b.getAttribute('data-konfirmasi') || '';
            var d = b.getAttribute('data-detail') || ''; detail.textContent = d; detail.style.display = d ? '' : 'none';
            ya.textContent = b.getAttribute('data-ya') || 'Ya, lanjutkan';
            ya.className = 'btn flex-fill fw-semibold btn-' + nada;
            (document.querySelector('.modal.show') || document.body).appendChild(kartu);   // di dalam kartu pop-up tiket bila terbuka, agar fokus tidak direbut modal Bootstrap
            kartu.style.display = 'flex';
            document.addEventListener('keydown', keyboard, true);
            ya.focus();
        });
    }
    batal.addEventListener('click', function () { tutup(false); });
    ya.addEventListener('click', function () { tutup(true); });
    kartu.addEventListener('mousedown', function (e) { if (e.target === kartu) tutup(false); });   // klik area gelap = batal

    // tombol ber-data-konfirmasi: tahan dulu, tanya lewat kartu, baru lanjutkan submit (sekali saja)
    document.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('[data-konfirmasi]') : null;
        if (!b || b.disabled) return;
        if (b.__lanjut) { b.__lanjut = false; return; }                           // klik lanjutan setelah dikonfirmasi
        e.preventDefault(); e.stopPropagation();
        if (terkirim || selesai) return;
        tanya(b).then(function (setuju) { if (!setuju) return; terkirim = true; b.__lanjut = true; b.click(); });
    }, true);
    window.addEventListener('pageshow', function (e) { if (e.persisted) terkirim = false; });
})();
</script>

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

                <!-- LANGKAH TOMBOL: 1. PPOB  ->  2. MDMS / Lunas  ->  3. Ready OFF / Gagal OFF -->
                <div class="rounded-3 border bg-white p-3">
                    <div id="penandaLangkah" class="small fw-semibold text-secondary mb-2"></div>

                    <div class="d-flex gap-2" data-langkah="1">
                        <button type="button" class="btn btn-outline-dark" data-ulangi><i class="bi bi-arrow-counterclockwise me-1"></i> Ulangi</button>
                        <a id="btnPpob" class="btn btn-primary flex-fill fw-semibold" data-tautan="ppob" href="<?= pg_e($link['ppob']) ?>" <?= $link['ppob'] !== '#' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><i class="bi bi-wallet2 me-1"></i> PPOB</a>
                    </div>

                    <div class="d-flex gap-2 d-none" data-langkah="2">
                        <button type="button" class="btn btn-outline-dark" data-ulangi><i class="bi bi-arrow-counterclockwise me-1"></i> Ulangi</button>
                        <a id="btnMdms" class="btn flex-fill fw-semibold text-white" style="background:#6f42c1;border-color:#6f42c1;" data-tautan="mdms" href="<?= pg_e($link['mdms']) ?>" <?= $link['mdms'] !== '#' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><i class="bi bi-hdd-network me-1"></i> MDMS</a>
                        <button type="submit" name="aksi" value="lunas" class="btn btn-warning flex-fill fw-semibold" formnovalidate data-konfirmasi="Pelanggan sudah lunas. Hasil yang dicatat: <?= pg_e($jenis_tindakan['lunas']) ?>." data-judul="Catat Lunas?" data-detail="<?= pg_e($detail_tiket) ?>" data-ya="Ya, catat Lunas"><i class="bi bi-cash-coin me-1"></i> Lunas</button>
                    </div>

                    <div class="d-flex gap-2 d-none" data-langkah="3">
                        <button type="button" class="btn btn-outline-dark" data-ulangi><i class="bi bi-arrow-counterclockwise me-1"></i> Ulangi</button>
                        <button type="submit" name="aksi" value="ready_off" class="btn btn-danger flex-fill fw-semibold" formnovalidate data-konfirmasi="Meter berstatus OFF. Hasil yang dicatat: <?= pg_e($jenis_tindakan['ready_off']) ?>." data-judul="Konfirmasi Ready OFF" data-detail="<?= pg_e($detail_tiket) ?>" data-ya="Ya, catat <?= pg_e($jenis_tindakan['ready_off']) ?>"><i class="bi bi-power me-1"></i> Ready OFF</button>
                        <button type="submit" name="aksi" value="gagal_off" class="btn btn-success flex-fill fw-semibold" formnovalidate data-konfirmasi="Meter berstatus ON. Hasil yang dicatat: <?= pg_e($jenis_tindakan['gagal_off']) ?>." data-judul="Konfirmasi Gagal OFF" data-detail="<?= pg_e($detail_tiket) ?>" data-ya="Ya, catat <?= pg_e($jenis_tindakan['gagal_off']) ?>"><i class="bi bi-power me-1"></i> Gagal OFF</button>
                    </div>
                    <div class="small text-muted mt-2 d-none" data-langkah="3">Ready OFF = <strong><?= pg_e($jenis_tindakan['ready_off']) ?></strong> &middot; Gagal OFF = <strong><?= pg_e($jenis_tindakan['gagal_off']) ?></strong></div>
                </div>
                <div id="infoTombol" class="small text-muted mt-2" style="min-height:1.2em;"></div>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('modalTiket'), info = document.getElementById('infoTombol'), penanda = document.getElementById('penandaLangkah');
    // Cara PPOB menyalin: 'terpisah' = 3 salinan berurutan (HP, No Meter, IDPEL; isi clipboard saat ini = IDPEL, sisanya di riwayat clipboard),
    //                    'gabung'   = 1 salinan berisi 3 baris (IDPEL, No Meter, No HP).
    var MODE_SALIN = 'terpisah';
    var data = {idpel: <?= json_encode($idpel) ?>, meter: <?= json_encode($meter) ?>, hp: <?= json_encode($hp) ?>};
    function kabar(t) { info.textContent = t; clearTimeout(kabar.w); kabar.w = setTimeout(function () { info.textContent = ''; }, 3500); }

    // ---- kartu pop-up terbuka otomatis saat halaman dimuat ----
    window.addEventListener('load', function () {
        if (window.bootstrap && bootstrap.Modal) { bootstrap.Modal.getOrCreateInstance(modal).show(); }
        else { modal.classList.add('show'); modal.style.display = 'block'; modal.removeAttribute('aria-hidden'); }   // cadangan bila Bootstrap JS tidak termuat
    });

    // ---- langkah tombol ----
    var judul = {1: 'Langkah 1 dari 3 \u00b7 Cek tagihan di PPOB',
                 2: 'Langkah 2 dari 3 \u00b7 Proses di MDMS, atau tandai Lunas bila sudah dibayar',
                 3: 'Langkah 3 dari 3 \u00b7 Catat kondisi meter setelah proses di MDMS'};
    function tampil(n) {
        document.querySelectorAll('[data-langkah]').forEach(function (el) { el.classList.toggle('d-none', Number(el.getAttribute('data-langkah')) !== n); });
        penanda.textContent = judul[n];
    }
    tampil(1);
    document.querySelectorAll('[data-ulangi]').forEach(function (b) { b.addEventListener('click', function () { tampil(1); kabar('Kembali ke langkah 1.'); }); });

    // ---- salin ke clipboard ----
    function salin(teks) {
        if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(teks);
        return new Promise(function (ok, gagal) {
            var t = document.createElement('textarea'); t.value = teks; t.style.position = 'fixed'; t.style.opacity = '0';
            modal.appendChild(t); t.select();                                  // di dalam modal agar fokus tidak ditahan modal
            try { document.execCommand('copy') ? ok() : gagal(); } catch (e) { gagal(e); } finally { t.remove(); }
        });
    }
    function salinData() {
        var item = [['No HP', data.hp], ['No Meter', data.meter], ['IDPEL', data.idpel]].filter(function (x) { return String(x[1]).trim() !== ''; });
        if (!item.length) { kabar('IDPEL, No Meter dan No HP kosong, tidak ada yang disalin.'); return; }
        var kosong = [['IDPEL', data.idpel], ['No Meter', data.meter], ['No HP', data.hp]].filter(function (x) { return String(x[1]).trim() === ''; }).map(function (x) { return x[0]; });
        var catatan = kosong.length ? ' (' + kosong.join(', ') + ' kosong, dilewati)' : '';
        var nama = item.slice().reverse().map(function (x) { return x[0]; }).join(', ');
        var proses = MODE_SALIN === 'gabung'
            ? salin(item.slice().reverse().map(function (x) { return x[1]; }).join('\n'))
            : item.reduce(function (p, x) { return p.then(function () { return salin(String(x[1]).trim()); }); }, Promise.resolve());
        proses.then(function () { kabar('Tersalin ke clipboard: ' + nama + catatan); },
                    function () { kabar('Gagal menyalin otomatis. Izinkan akses clipboard atau salin manual dari keterangan.'); });
    }

    // ---- PPOB: salin data, buka tautan, lanjut langkah 2 ----
    // ---- MDMS: buka tautan, lanjut langkah 3 ----
    function pasang(id, tautan, sesudah, nama) {
        var a = document.getElementById(id);
        a.addEventListener('click', function (e) {
            if (a.getAttribute('href') === '#') { e.preventDefault(); kabar('Tautan ' + nama + ' belum diatur (isi $link di modules/penggunaan/pemutusan_amr.php).'); }
            sesudah();
        });
    }
    pasang('btnPpob', 'ppob', function () { salinData(); tampil(2); }, 'PPOB');
    pasang('btnMdms', 'mdms', function () { tampil(3); }, 'MDMS');

})();
</script>
<?php endif; ?>