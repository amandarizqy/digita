<?php
// templates/penggunaan/penyalaan_ami.php  -  Penyalaan AMI
// Variabel dari modules/penggunaan/penyalaan_ami.php:
//   $halaman, $judul, $pk, $fk (penyalaan), $putus, $csrf, $unit_siap, $sesi, $row (tiket berjalan | null), $sisa, $link,
//   $pesan_sukses, $pesan_error, $pesan_info

$ada    = $row !== null;
$sufiks = $putus ? 'OFF' : 'ON';

echo pg_header([
    'title'    => $judul,
    'subtitle' => $putus ? 'Create membuat tiket pemutusan, lalu cek di PPOB, proses di MDMS, dan catat hasilnya.'
                         : 'Create membuat tiket penyalaan dari tiket putus, lalu cek di PPOB, proses di MDMS, dan catat hasilnya.',
    'action'   => $halaman,
]);

if (!empty($pesan_sukses)) echo pg_alert('success', $pesan_sukses);
if (!empty($pesan_error))  echo pg_alert('danger', $pesan_error);
if (!empty($pesan_info))   echo pg_alert('warning', $pesan_info, 'bi-info-circle-fill');
if (!$unit_siap) echo pg_alert('warning', 'Unit akun Anda belum diatur, sehingga tiket tidak bisa dibuat. Hubungi administrator.', 'bi-exclamation-triangle-fill');

$v = [
    'tiket'  => $ada ? (string) $row[$pk] : '',
    'putus'  => $ada && !$putus ? (string) $row[$fk] : '',
    'jenis'  => $ada ? (string) $row['JenisTiket'] : '',
    'notul'  => $ada ? (string) ($row['NoTulTampil'] ?? '') : '',
    'idpel'  => $ada ? (string) $row['IdPel'] : '',
    'meter'  => $ada ? (string) $row['meter_tpl'] : '',
    'hp'     => $ada ? (string) $row['hp_tpl'] : '',
];
$kosong = 'Belum ada tiket, klik Create';

// satu baris form: label kiri, isian kanan (semua hanya baca)
$baris = function (string $label, string $id, string $nilai, string $tambah = '') use ($kosong) {
    return '<div class="row g-2 align-items-center mb-3"><label for="' . $id . '" class="col-sm-3 col-form-label fw-semibold text-secondary">' . pg_e($label) . ' :</label>'
         . '<div class="col-sm-9"><input type="text" class="form-control font-monospace" id="' . $id . '" value="' . pg_e($nilai) . '" placeholder="' . pg_e($kosong) . '" readonly>' . $tambah . '</div></div>';
};
?>
<div class="card border-0 shadow-sm rounded-4 d-flex align-items-stretch">
    <div class="card-body p-4">
        <form id="formTiket" method="POST" action="<?= pg_e(pg_url($halaman)) ?>" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
            <input type="hidden" name="tiket" value="<?= pg_e($v['tiket']) ?>">

            <!-- Pilihan IDPEL / No TUL (opsional) -->
            <div class="row g-2 align-items-center mb-3">
                <label for="f_target" class="col-sm-3 col-form-label fw-semibold text-secondary">Pilih IDPEL / No TUL :</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control font-monospace" id="f_target" name="target" maxlength="30" value="" placeholder="Opsional, kosongkan untuk tiket terlama" <?= $ada || !$unit_siap ? 'disabled' : 'autofocus' ?>>
                    <div class="form-text">Isi IDPEL atau No TUL yang ingin diproses lebih dulu, lalu klik Create. Tiket sesudahnya tetap diambil otomatis berurutan.</div>
                </div>
            </div>

            <!-- Sesi ID + Create -->
            <div class="row g-2 align-items-center mb-3">
                <label for="f_sesi" class="col-sm-3 col-form-label fw-semibold text-secondary">Sesi ID :</label>
                <div class="col-sm-9"><div class="input-group">
                    <input type="text" class="form-control font-monospace" id="f_sesi" value="<?= pg_e($ada ? $sesi : '') ?>" placeholder="Dibuat saat Create" readonly>
                    <button type="submit" name="aksi" value="create" class="btn btn-primary px-4" <?= $ada || !$unit_siap ? 'disabled' : '' ?>><i class="bi bi-plus-circle me-1"></i> Create</button>
                </div>
                <?php if ($sisa !== null): ?><div class="form-text">Tiket yang masih bisa diambil/dibuat di wilayah Anda: <strong><?= pg_n($sisa) ?></strong></div><?php endif; ?>
                </div>
            </div>

<?php
if ($putus) {
    echo $baris('Tiket Putus', 'f_tiket', $v['tiket']);
} else {
    echo $baris('Tiket Nyala', 'f_tiket', $v['tiket']);
    echo $baris('Tiket Putus', 'f_putus', $v['putus']);
}
echo $baris('Jenis Tiket', 'f_jenis', $v['jenis']);
echo $baris('No TUL', 'f_notul', $v['notul']);
echo $baris('IDPEL', 'f_idpel', $v['idpel'], '<div class="form-text">' . ($ada ? pg_e($row['nama'] !== '' ? $row['nama'] : '(nama belum ada di DIL)') : '') . '</div>');
echo $baris('Nomor Meter', 'f_meter', $v['meter']);
echo $baris('Nomor Hp', 'f_hp', $v['hp']);
?>
            <?php if ($ada): ?>
            <div class="small text-muted mb-3">Dibuat <?= pg_e($row['WaktuData']) ?></div>
            <?php endif; ?>

            <hr class="my-3">

            <!-- TOMBOL 4 x 2 -->
            <div class="row g-2 row-cols-2 row-cols-md-4" id="gridTombol">
                <div class="col"><button type="button" class="btn btn-outline-secondary w-100" data-salin="#f_idpel"><i class="bi bi-clipboard me-1"></i> Salin IDPEL</button></div>
                <div class="col"><button type="button" class="btn btn-outline-secondary w-100" data-salin="#f_hp"><i class="bi bi-clipboard me-1"></i> Salin No HP</button></div>
                <div class="col"><a class="btn btn-outline-primary w-100" data-tautan="ppob" href="<?= pg_e($link['ppob']) ?>" <?= $link['ppob'] !== '#' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><i class="bi bi-box-arrow-up-right me-1"></i> Buka PPOB</a></div>
                <div class="col"><button type="submit" name="aksi" value="lunas" class="btn btn-warning w-100" formnovalidate data-konfirmasi="Catat LUNAS untuk tiket ini dan lanjut ke tiket berikutnya?" <?= $ada ? '' : 'disabled' ?>><i class="bi bi-cash-coin me-1"></i> Lunas</button></div>

                <div class="col"><button type="button" class="btn btn-outline-secondary w-100" data-salin="#f_meter"><i class="bi bi-clipboard me-1"></i> Salin No Meter</button></div>
                <div class="col"><a class="btn btn-outline-primary w-100" data-tautan="mdms" href="<?= pg_e($link['mdms']) ?>" <?= $link['mdms'] !== '#' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><i class="bi bi-box-arrow-up-right me-1"></i> Buka MDMS</a></div>
                <div class="col"><button type="submit" name="aksi" value="ready" class="btn btn-success w-100" formnovalidate data-konfirmasi="Catat Ready <?= $sufiks ?> untuk tiket ini dan lanjut ke tiket berikutnya?" <?= $ada ? '' : 'disabled' ?>><i class="bi bi-check-circle me-1"></i> Ready <?= $sufiks ?></button></div>
                <div class="col"><button type="submit" name="aksi" value="gagal" class="btn btn-outline-danger w-100" formnovalidate data-konfirmasi="Catat GAGAL <?= $sufiks ?> untuk tiket ini dan lanjut ke tiket berikutnya?" <?= $ada ? '' : 'disabled' ?>><i class="bi bi-x-circle me-1"></i> Gagal <?= $sufiks ?></button></div>
            </div>
            <div id="infoTombol" class="small text-muted mt-2" style="min-height:1.2em;"></div>
        </form>
    </div>
</div>

<script>
(function () {
    var info = document.getElementById('infoTombol'), terkirim = false;
    function kabar(t) { info.textContent = t; clearTimeout(kabar.w); kabar.w = setTimeout(function () { info.textContent = ''; }, 2500); }

    // ---- tombol salin ----
    function salin(teks) {
        if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(teks);
        return new Promise(function (ok, gagal) {
            var t = document.createElement('textarea'); t.value = teks; t.style.position = 'fixed'; t.style.opacity = '0';
            document.body.appendChild(t); t.select();
            try { document.execCommand('copy') ? ok() : gagal(); } catch (e) { gagal(e); } finally { t.remove(); }
        });
    }
    document.querySelectorAll('[data-salin]').forEach(function (b) {
        b.addEventListener('click', function () {
            var el = document.querySelector(b.getAttribute('data-salin')), teks = el ? el.value.trim() : '';
            if (!teks) { kabar('Isian masih kosong, tidak ada yang disalin.'); return; }
            salin(teks).then(function () { kabar('Tersalin: ' + teks); }, function () { kabar('Gagal menyalin. Salin manual dari kolom isian.'); });
        });
    });

    // ---- tautan yang belum diatur ('#') ----
    document.querySelectorAll('[data-tautan]').forEach(function (a) {
        if (a.getAttribute('href') === '#') a.addEventListener('click', function (e) { e.preventDefault(); kabar('Tautan belum diatur (isi $link di modules/penggunaan/penyalaan_ami.php).'); });
    });

    // ---- tombol hasil: konfirmasi, lalu cegah kirim ganda ----
    document.querySelectorAll('[data-konfirmasi]').forEach(function (b) {
        b.addEventListener('click', function (e) {
            if (terkirim || !confirm(b.getAttribute('data-konfirmasi'))) { e.preventDefault(); return; }
            terkirim = true;
        });
    });
    // kembali lewat tombol Back: izinkan kirim lagi
    window.addEventListener('pageshow', function (e) { if (e.persisted) terkirim = false; });
})();
</script>