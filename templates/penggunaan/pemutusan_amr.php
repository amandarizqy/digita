<?php
// templates/penggunaan/pemutusan_amr.php  -  Pemutusan AMR (tabel trx_order_putus_amr)
// Variabel dari modules/penggunaan/pemutusan_amr.php:
//   $halaman, $judul, $pk, $fk (penyalaan), $putus, $csrf, $unit_siap, $u, $row (tiket terbuka | null), $isi, $daftar_putus, $link,
//   $pesan_sukses, $pesan_error, $pesan_info

$ada      = $row !== null;
$sufiks   = $putus ? 'OFF' : 'ON';
$url_form = pg_url($halaman, ['tiket' => $ada ? $row[$pk] : '']);

echo pg_header([
    'title'    => $judul,
    'subtitle' => $putus ? 'Buat tiket pemutusan dari No TUL, lalu proses di PPOB dan MDMS.'
                         : 'Buat tiket penyalaan dari tiket putus, lalu proses di PPOB dan MDMS.',
    'action'   => $halaman,
    'buttons'  => $ada ? [['label' => 'Tiket Baru', 'icon' => 'bi-plus-lg', 'href' => pg_url($halaman),
                           'class' => 'btn btn-outline-primary btn-sm px-3 shadow-sm rounded-2 bg-white']] : [],
]);

if (!empty($pesan_sukses)) echo pg_alert('success', $pesan_sukses);
if (!empty($pesan_error))  echo pg_alert('danger', $pesan_error);
if (!empty($pesan_info))   echo pg_alert('warning', $pesan_info, 'bi-info-circle-fill');
if (!$unit_siap) echo pg_alert('warning', 'Unit akun Anda belum diatur, sehingga tiket tidak bisa dibuat. Hubungi administrator.', 'bi-exclamation-triangle-fill');

// nilai tampil: tiket terbuka > isian terpulihkan > kosong
$v = [
    'sesi'   => $ada ? (string) $row['Sesi'] : '',
    'tiket'  => $ada ? (string) $row[$pk] : '',
    'putus'  => $ada ? (string) $row[$fk ?? $pk] : $isi['tiket_putus'],
    'jenis'  => $ada ? (string) $row['JenisTiket'] : $isi['jenis'],
    'notul'  => $ada ? (string) ($row['NoTulTampil'] ?? '') : $isi['notul'],
    'idpel'  => $ada ? (string) $row['IdPel'] : '',
    'meter'  => $ada ? (string) $row['NomorMeter'] : $isi['meter'],
    'hp'     => $ada ? (string) $row['NomorHp'] : $isi['hp'],
];
$kunci_awal = $putus ? $v['notul'] : $v['putus'];     // dipakai untuk pencarian otomatis setelah Create ditolak

// satu baris form: label kiri, isian kanan
$baris = function (string $label, string $isi_html, string $id = '') {
    return '<div class="row g-2 align-items-center mb-3"><label' . ($id ? ' for="' . $id . '"' : '') . ' class="col-sm-3 col-form-label fw-semibold text-secondary">'
         . pg_e($label) . ' :</label><div class="col-sm-9">' . $isi_html . '</div></div>';
};
$ro  = $ada ? ' readonly' : '';
?>
<div class="card border-0 shadow-sm rounded-4 d-flex align-item-center">
    <div class="card-body p-4">
        <form id="formTiket" method="POST" action="<?= pg_e($url_form) ?>" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
            <input type="hidden" name="tiket" value="<?= pg_e($v['tiket']) ?>">

            <?php
            // ---- Sesi ID + Create ----
            echo $baris('Sesi ID',
                '<div class="input-group">'
                . '<input type="text" class="form-control font-monospace" id="f_sesi" value="' . pg_e($v['sesi']) . '" placeholder="Dibuat otomatis saat Create" readonly>'
                . '<button type="submit" name="aksi" value="create" id="btnCreate" class="btn btn-primary px-4"' . ($ada || !$unit_siap ? ' disabled' : '') . '><i class="bi bi-plus-circle me-1"></i> Create</button>'
                . '</div>', 'f_sesi');

            // ---- Tiket ----
            if (!$putus) {
                echo $baris('Tiket Nyala', '<input type="text" class="form-control font-monospace" id="f_tiket" value="' . pg_e($v['tiket']) . '" placeholder="Dibuat otomatis saat Create" readonly>', 'f_tiket');
                echo $baris('Tiket Putus',
                    '<input type="text" class="form-control font-monospace" id="f_key" name="tiket_putus" maxlength="23" list="saranPutus" value="' . pg_e($v['putus']) . '"'
                    . ($ada ? ' readonly' : ' required placeholder="Pilih / tempel ID tiket putus"') . '>'
                    . '<datalist id="saranPutus">' . implode('', array_map(fn($d) => '<option value="' . pg_e($d['id']) . '">' . pg_e($d['IdPel']) . '</option>', $daftar_putus)) . '</datalist>', 'f_key');
            } else {
                echo $baris('Tiket Putus', '<input type="text" class="form-control font-monospace" id="f_tiket" value="' . pg_e($v['tiket']) . '" placeholder="Dibuat otomatis saat Create" readonly>', 'f_tiket');
            }

            // ---- Jenis tiket (default PREVENTIF) ----
            $opsi = '';
            foreach (['PREVENTIF', 'KOREKTIF'] as $j) $opsi .= '<option value="' . $j . '"' . ($v['jenis'] === $j ? ' selected' : '') . '>' . $j . '</option>';
            echo $baris('Jenis Tiket', '<select class="form-select" id="f_jenis" name="jenis"' . ($ada ? ' disabled' : '') . '>' . $opsi . '</select>', 'f_jenis');

            // ---- No TUL ----
            if ($putus) {
                echo $baris('No TUL', '<input type="text" class="form-control font-monospace" id="f_key" name="notul" maxlength="30" value="' . pg_e($v['notul']) . '"'
                    . ($ada ? ' readonly' : ' required placeholder="Tempel No TUL lalu tekan Enter"') . '>'
                    . '<div id="pesanCari" class="form-text"></div>', 'f_key');
            } else {
                echo $baris('No TUL', '<input type="text" class="form-control font-monospace" id="f_notul" value="' . pg_e($v['notul']) . '" readonly>'
                    . '<div id="pesanCari" class="form-text"></div>', 'f_notul');
            }

            // ---- IDPEL, Nomor Meter, Nomor HP ----
            echo $baris('IDPEL', '<input type="text" class="form-control font-monospace" id="f_idpel" value="' . pg_e($v['idpel']) . '" placeholder="Terisi otomatis" readonly>'
                . '<div id="namaPel" class="form-text">' . ($ada ? pg_e($row['nama']) : '') . '</div>', 'f_idpel');
            echo $baris('Nomor Meter', '<input type="text" class="form-control font-monospace" id="f_meter" name="meter" maxlength="15" value="' . pg_e($v['meter']) . '"' . $ro . '>', 'f_meter');
            echo $baris('Nomor Hp', '<input type="text" class="form-control font-monospace" id="f_hp" name="hp" maxlength="20" inputmode="tel" value="' . pg_e($v['hp']) . '"' . $ro . '>', 'f_hp');
            ?>

            <?php if ($ada): ?>
            <div class="small text-muted mb-3">
                Status <span class="badge bg-<?= $row['StatusData'] === 'AKTIF' ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $row['StatusData'] === 'AKTIF' ? 'success' : 'secondary' ?> border border-opacity-50 border-<?= $row['StatusData'] === 'AKTIF' ? 'success' : 'secondary' ?>"><?= pg_e($row['StatusData']) ?></span>
                · dibuat oleh <strong><?= pg_e($row['NamaAkun']) ?></strong> · <?= pg_e($row['WaktuData']) ?>
            </div>
            <?php endif; ?>

            <hr class="my-3">

            <!-- TOMBOL 4 x 2 -->
            <div class="row g-2 row-cols-2 row-cols-md-4" id="gridTombol">
                <div class="col"><button type="button" class="btn btn-outline-secondary w-100" data-salin="#f_idpel"><i class="bi bi-clipboard me-1"></i> Salin IDPEL</button></div>
                <div class="col"><button type="button" class="btn btn-outline-secondary w-100" data-salin="#f_hp"><i class="bi bi-clipboard me-1"></i> Salin No HP</button></div>
                <div class="col"><a class="btn btn-outline-primary w-100" data-tautan="ppob" href="<?= pg_e($link['ppob']) ?>" <?= $link['ppob'] !== '#' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><i class="bi bi-box-arrow-up-right me-1"></i> Buka PPOB</a></div>
                <div class="col"><a class="btn btn-outline-warning w-100" data-tautan="lunas" href="<?= pg_e($link['lunas']) ?>" <?= $link['lunas'] !== '#' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><i class="bi bi-cash-coin me-1"></i> Lunas</a></div>

                <div class="col"><button type="button" class="btn btn-outline-secondary w-100" data-salin="#f_meter"><i class="bi bi-clipboard me-1"></i> Salin No Meter</button></div>
                <div class="col"><a class="btn btn-outline-primary w-100" data-tautan="mdms" href="<?= pg_e($link['mdms']) ?>" <?= $link['mdms'] !== '#' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><i class="bi bi-box-arrow-up-right me-1"></i> Buka MDMS</a></div>
                <div class="col"><button type="submit" name="aksi" value="ready" class="btn btn-success w-100" formnovalidate data-konfirmasi="Ready <?= $sufiks ?> untuk tiket ini?" <?= $ada ? '' : 'disabled' ?>><i class="bi bi-check-circle me-1"></i> Ready <?= $sufiks ?></button></div>
                <div class="col"><button type="submit" name="aksi" value="gagal" class="btn btn-outline-danger w-100" formnovalidate data-konfirmasi="Tandai GAGAL <?= $sufiks ?> untuk tiket ini?" <?= $ada ? '' : 'disabled' ?>><i class="bi bi-x-circle me-1"></i> Gagal <?= $sufiks ?></button></div>
            </div>
            <div id="infoTombol" class="small text-muted mt-2" style="min-height:1.2em;"></div>
        </form>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('formTiket'), info = document.getElementById('infoTombol');
    var ada = <?= $ada ? 'true' : 'false' ?>, putus = <?= $putus ? 'true' : 'false' ?>;
    var urlCari = <?= json_encode(pg_url($halaman)) ?>;

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
        if (a.getAttribute('href') === '#') a.addEventListener('click', function (e) { e.preventDefault(); kabar('Tautan belum diatur (isi $link di modules/penggunaan/pemutusan_amr.php).'); });
    });

    // ---- konfirmasi Ready / Gagal ----
    document.querySelectorAll('[data-konfirmasi]').forEach(function (b) {
        b.addEventListener('click', function (e) { if (!confirm(b.getAttribute('data-konfirmasi'))) e.preventDefault(); });
    });

    // ---- pencarian otomatis (No TUL / tiket putus) ----
    if (ada) return;
    var key = document.getElementById('f_key'), pesan = document.getElementById('pesanCari'), nama = document.getElementById('namaPel'),
        idpel = document.getElementById('f_idpel'), meter = document.getElementById('f_meter'), hp = document.getElementById('f_hp'),
        notul = document.getElementById('f_notul'), btn = document.getElementById('btnCreate'), terakhir = '';

    function kosongkan() { idpel.value = ''; nama.textContent = ''; if (notul) notul.value = ''; }
    function cari() {
        var k = key.value.trim();
        if (k === '' || k === terakhir) return;
        terakhir = k; pesan.className = 'form-text'; pesan.textContent = 'Mencari…';
        fetch(urlCari + '&cari=' + encodeURIComponent(k), {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (key.value.trim() !== k) return;                       // isian berubah saat menunggu
                if (!j.ok) { kosongkan(); pesan.className = 'form-text text-danger'; pesan.textContent = j.pesan || 'Tidak ditemukan.'; return; }
                var d = j.data;
                idpel.value = d.idpel || ''; nama.textContent = d.nama || '(nama belum ada di DIL)';
                if (notul) notul.value = d.notul || '';
                meter.value = d.meter || ''; hp.value = d.hp || '';
                pesan.className = 'form-text ' + (d.duplikat ? 'text-danger' : (j.pesan ? 'text-warning' : 'text-success'));
                pesan.textContent = j.pesan || 'Data ditemukan.';
                if (btn) btn.disabled = !!d.duplikat;
            })
            .catch(function () { pesan.className = 'form-text text-danger'; pesan.textContent = 'Gagal menghubungi server.'; });
    }
    key.addEventListener('change', cari);
    key.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); cari(); } });
    key.addEventListener('input', function () { terakhir = ''; if (btn) btn.disabled = false; });
    if (key.value.trim() !== '') cari();                                  // isian terpulihkan setelah Create ditolak
})();
</script>