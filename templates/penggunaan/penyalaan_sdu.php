<?php
// templates/penggunaan/penyalaan_sdu.php  -  Penyalaan SDU
// Halaman: kartu Sesi ID + Create. Tiket yang diambil/dibuat tampil sebagai kartu POP-UP (modal) berisi keterangan pelanggan
// yang ringkas dan tombol bertahap langsung catat hasil (Ready / Gagal). Memerlukan Bootstrap 5 JS (sudah dimuat layout).
// Variabel dari modules/penggunaan/penyalaan_sdu.php:
//   $halaman, $judul, $pk, $fk (penyalaan), $putus, $csrf, $unit_siap, $sesi, $row (tiket berjalan | null), $sisa, $link,
//   $pesan_sukses, $pesan_error, $pesan_info

$ada    = $row !== null;
$sufiks = $putus ? 'OFF' : 'ON';

echo pg_header([
    'title'    => $judul,
    'subtitle' => $putus ? 'Create mengambil tiket pemutusan berikutnya, lalu catat hasilnya.'
                         : 'Create mengambil tiket penyalaan berikutnya, lalu catat hasilnya.',
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
    $rincian[] = ['Periode', trim((string) $row['ThBlRek'])];
    $rincian[] = ['No. Meter', $meter];
    $rincian[] = ['No. HP', $hp];
    $rincian[] = ['Skala Prioritas', trim((string) ($row['SkalaPrioritas'] ?? ''))];
    $rincian[] = ['Peringkat', trim((string) ($row['Peringkat'] ?? ''))];
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
                <button type="submit" name="aksi" value="create" class="btn btn-primary px-4" <?= $sesi !== '' || !$unit_siap ? 'disabled' : '' ?>><i class="bi bi-plus-circle me-1"></i> Create</button>
                <button type="submit" name="aksi" value="hapus_sesi" class="btn btn-outline-danger px-3" formnovalidate <?= $sesi === '' || !$unit_siap ? 'disabled' : '' ?>
                        data-konfirmasi="<?= pg_e($ada ? 'Tiket yang sedang berjalan akan dilepas kembali ke antrean.' : 'Sesi ini akan dihapus.') ?>" data-judul="Hapus sesi?" data-detail="Sesi <?= pg_e($sesi) ?>" data-ya="Ya, hapus sesi"><i class="bi bi-trash3 me-1"></i> Hapus Sesi</button>
            </div>
            <div class="form-text d-flex align-items-center gap-2">
                <span id="denyut" class="d-inline-block rounded-circle bg-secondary" style="width:8px;height:8px;transition:opacity .3s;"></span>
                <span>Tiket tersedia di wilayah Anda: <strong id="jmlSisa"><?= $sisa === null ? '-' : pg_n($sisa) ?></strong> <span class="text-muted" id="waktuCek"></span></span>
            </div>
            <div id="peringatanData" class="alert alert-warning py-2 px-3 small mt-2 mb-0 d-none">Data tiket berubah (mungkin dari tab atau sesi lain). <a href="" class="alert-link" onclick="location.reload(); return false;">Muat ulang</a></div>
        </form>

        <?php if ($ada): ?>
        <div class="d-flex align-items-center justify-content-between gap-3 mt-3 p-3 rounded-3 bg-primary bg-opacity-10 border border-primary border-opacity-25">
            <div class="small" style="min-width:0;">
                <div class="text-muted">Tiket berjalan</div>
                <div class="fw-bold font-monospace text-break"><?= pg_e($tiket) ?></div>
                <div class="text-truncate"><?= pg_e($nama) ?> · <span class="font-monospace"><?= pg_e($idpel) ?></span></div>
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

<!-- PENGECEKAN BERKALA: jumlah tiket tersedia diperbarui otomatis (multiuser); berhenti saat tab tidak terlihat -->
<script>
(function () {
    var URL_CEK = <?= json_encode(pg_url($halaman, ['hitung' => 1])) ?>, INTERVAL = 5000, MAKS = 30000,
        ada = <?= $ada ? 'true' : 'false' ?>, tiketSaya = <?= json_encode($ada ? (string) $row[$pk] : '') ?>;
    var jml = document.getElementById('jmlSisa'), waktu = document.getElementById('waktuCek'), denyut = document.getElementById('denyut'),
        peringatan = document.getElementById('peringatanData'), btn = document.querySelector('button[name="aksi"][value="create"]'),
        jeda = INTERVAL, timer = null, jalan = false;
    function atur(ms) { clearTimeout(timer); timer = setTimeout(cek, ms); }
    function cek() {
        if (document.hidden) { atur(jeda); return; }
        if (jalan) return;
        jalan = true;
        fetch(URL_CEK, {credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'application/json'}})
            .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(function (j) {
                if (j.sisa !== null && j.sisa !== undefined) {
                    jml.textContent = Number(j.sisa).toLocaleString('id-ID');
                }
                waktu.textContent = '\u00b7 dicek ' + j.waktu;
                denyut.className = 'd-inline-block rounded-circle bg-success'; denyut.style.opacity = '.35'; setTimeout(function () { denyut.style.opacity = '1'; }, 300);
                if (!ada && j.tiket) { location.reload(); }   // sesi baru mendapat tiket (diambil otomatis server) -> muat ulang, pop-up terbuka
                else if (j.tiket !== tiketSaya) peringatan.classList.remove('d-none');       // tiket sesi ini berubah dari tempat lain
                jeda = INTERVAL;
            })
            .catch(function () { denyut.className = 'd-inline-block rounded-circle bg-danger'; jeda = Math.min(jeda * 2, MAKS); })   // gagal: perlambat
            .then(function () { jalan = false; atur(jeda); });
    }
    document.addEventListener('visibilitychange', function () { if (!document.hidden) atur(300); });
    atur(INTERVAL);
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
                    <div class="small text-muted text-uppercase fw-bold" style="letter-spacing:.5px;"><?= pg_e($judul) ?> · Sesi <span class="font-monospace"><?= pg_e($sesi) ?></span></div>
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

                <!-- LANGKAH TOMBOL: Langsung 1 tahap (Ready / Gagal) -->
                <div class="rounded-3 border bg-white p-3">
                    <div id="penandaLangkah" class="small fw-semibold text-secondary mb-2"></div>

                    <div class="d-flex flex-wrap gap-2" data-langkah="1">
                        <button type="submit" name="aksi" value="ready_on" class="btn btn-success flex-fill fw-semibold" formnovalidate data-konfirmasi="Meter berstatus ON. Hasil yang dicatat: <?= pg_e($jenis_tindakan['ready_on']) ?>." data-judul="Konfirmasi Ready ON" data-detail="<?= pg_e($detail_tiket) ?>" data-ya="Ya, catat <?= pg_e($jenis_tindakan['ready_on']) ?>"><i class="bi bi-power me-1"></i> Ready ON</button>
                        <button type="submit" name="aksi" value="gagal_on" class="btn btn-danger flex-fill fw-semibold" formnovalidate data-konfirmasi="Meter berstatus OFF. Hasil yang dicatat: <?= pg_e($jenis_tindakan['gagal_on']) ?>." data-judul="Konfirmasi Gagal ON" data-detail="<?= pg_e($detail_tiket) ?>" data-ya="Ya, catat <?= pg_e($jenis_tindakan['gagal_on']) ?>"><i class="bi bi-power me-1"></i> Gagal ON</button>
                        <?php if (strtoupper(trim((string) $jenis)) === 'KOREKTIF'): ?>
                        <button type="submit" name="aksi" value="belum" class="btn btn-secondary flex-fill fw-semibold" formnovalidate data-konfirmasi="Pelanggan belum lunas. Hasil yang dicatat: <?= pg_e($jenis_tindakan['belum']) ?>." data-judul="Catat Belum Lunas?" data-detail="<?= pg_e($detail_tiket) ?>" data-ya="Ya, catat Belum Lunas"><i class="bi bi-x-circle me-1"></i> Belum Lunas</button>
                        <?php endif; ?>
                    </div>
                    <div class="small text-muted mt-2" data-langkah="1">Gagal ON = <strong><?= pg_e($jenis_tindakan['gagal_on']) ?></strong> · Ready ON = <strong><?= pg_e($jenis_tindakan['ready_on']) ?></strong></div>
                </div>
                <div id="infoTombol" class="small text-muted mt-2" style="min-height:1.2em;"></div>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('modalTiket'), info = document.getElementById('infoTombol'), penanda = document.getElementById('penandaLangkah');
    
    // ---- kartu pop-up terbuka otomatis saat halaman dimuat ----
    window.addEventListener('load', function () {
        if (window.bootstrap && bootstrap.Modal) { bootstrap.Modal.getOrCreateInstance(modal).show(); }
        else { modal.classList.add('show'); modal.style.display = 'block'; modal.removeAttribute('aria-hidden'); }
    });

    // ---- langkah tombol (1 tahap saja) ----
    var judul = {
        1: 'Pilih hasil tindakan: Ready ON, Gagal ON, atau Belum Lunas (tiket korektif)'
    };
    function tampil(n) {
        document.querySelectorAll('[data-langkah]').forEach(function (el) { el.classList.toggle('d-none', Number(el.getAttribute('data-langkah')) !== n); });
        penanda.textContent = judul[n] || '';
    }
    tampil(1);
})();
</script>
<?php endif; ?>