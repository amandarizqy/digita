<?php
// templates/pengaturan/bot.php -> TAMPILAN halaman Daftar Bot (Tabel bot_akses)
// Variabel yang disiapkan oleh modules/pengaturan/bot.php:
// $bots, $pesan, $errors, $form_isi, $buka_form, $jenis_opsi, $jenis_info, $root_bot, $root_konfig, $daftar_ap, $daftar_up
// Fungsi/konstanta dari bot_helper.php: bot_tingkat(), BOT_KOL_JALAN, BOT_KOL_STATUS, BOT_KOL_PESAN
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$root_tampil = $root_bot !== '' ? $root_bot : str_replace('\\', '/', (string) $root_konfig);
$fmt_waktu   = fn($v) => $v ? $e(date('d/m/Y H:i', strtotime((string) $v))) : '-';

$hak_sa    = (($_SESSION['KodeHak'] ?? '') === 'SA.KP');
$upi_ses   = trim((string) ($_SESSION['UnitUpi'] ?? ''));
$kunci_ap  = $hak_sa ? '' : trim((string) ($_SESSION['UnitAp'] ?? ''));   // akun terikat AP/UP: pilihan otomatis terisi
$kunci_up  = $hak_sa ? '' : trim((string) ($_SESSION['UnitUp'] ?? ''));
$kol_unit  = $hak_sa ? 'col-md-4' : 'col-md-6';

// Daftar UPI untuk pilihan SA.KP (diambil dari master_ap yang sudah dimuat bot.php)
$daftar_upi = array_values(array_unique(array_map(fn($a) => (string) $a['UnitUpi'], $daftar_ap)));
sort($daftar_upi);

$kelas_status = ['BERHASIL' => 'success', 'GAGAL' => 'danger', 'SEBAGIAN' => 'warning', 'TUNDA' => 'warning'];
?>
<div class="container-fluid" style="max-width: 1280px;">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-robot me-2"></i>Daftar Akses Bot</h4>
            <p class="text-muted mb-0">Kelola direktori utama, arsip, serta cakupan unit wilayah untuk bot otomatisasi.</p>
        </div>
        <button type="button" class="btn btn-primary" id="btnTambahBot">
            <i class="bi bi-plus-lg me-1"></i> Tambah Konfigurasi Bot
        </button>
    </div>

    <?php if (!empty($pesan)): ?>
        <div class="alert alert-<?= $e($pesan['tipe']) ?> alert-dismissible fade show border-0 shadow-sm">
            <?= $pesan['teks'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endif; ?>

    <div class="alert alert-<?= $root_bot !== '' ? 'info' : 'warning' ?> border-0 shadow-sm small">
        <i class="bi bi-folder2-open me-1"></i>
        Folder Utama Bot: <code><?= $e($root_tampil) ?></code>
        <?php if ($root_bot === ''): ?>
            — <strong>folder ini belum ada.</strong> Buat folder tersebut (atau atur <code>BOT_ROOT</code> di <code>config/bot.php</code>) agar jalur folder bisa dibuat otomatis.
        <?php else: ?>
            — semua direktori di bawah ditulis secara <strong>relatif</strong> terhadap folder ini.
        <?php endif; ?>
        
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>Jenis Bot</th>
                        <th>Direktori Utama & Arsip (Relatif)</th>
                        <th>Wilayah Unit</th>
                        <th class="text-center">Status</th>
                        <th>Waktu Data</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$bots): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">Belum ada konfigurasi bot. Klik <strong>Tambah Konfigurasi Bot</strong> untuk menambahkan data baru.</td></tr>
                <?php endif; ?>
                <?php foreach ($bots as $b): ?>
                    <?php
                        $data = [
                            'id' => (int) $b['IdBot'],
                            'jenis' => $b['JenisBot'],
                            'direktori_utama' => $b['DirektoriUtama'],
                            'direktori_arsip' => $b['DirektoriArsip'],
                            'status' => $b['StatusData'],
                            'unit_upi' => $b['UnitUpi'],
                            'unit_ap' => $b['UnitAp'],
                            'unit_up' => $b['UnitUp']
                        ];
                        $aktif    = $b['StatusData'] === 'AKTIF';
                        $tingkat  = bot_tingkat($b);                       // UP | AP | UPI
                        $ada_cron = array_key_exists(BOT_KOL_JALAN, $b);   // kolom status terakhir ada di tabel
                        $t_jalan  = $b[BOT_KOL_JALAN]  ?? null;
                        $t_status = (string) ($b[BOT_KOL_STATUS] ?? '');
                        $t_pesan  = (string) ($b[BOT_KOL_PESAN]  ?? '');
                    ?>
                    <tr class="<?= $aktif ? '' : 'text-muted' ?>">
                        <td class="fw-bold">#<?= (int) $b['IdBot'] ?></td>
                        <td>
                            <span class="badge bg-primary-subtle text-primary-emphasis fs-6 fw-semibold">
                                <?= $e($jenis_opsi[$b['JenisBot']] ?? $b['JenisBot']) ?>
                            </span>
                        </td>
                        <td class="small">
                            <div><i class="bi bi-folder-symlink text-primary me-1" title="Direktori Utama"></i> <strong>Utama:</strong> <code><?= $e($b['DirektoriUtama']) ?></code></div>
                            <?php if ($tingkat !== 'UP'): ?>
                                <div class="text-muted ms-4">↳ file di <code><?= $e($b['DirektoriUtama']) ?>/&lt;KodeUP&gt;/</code></div>
                            <?php endif; ?>
                            <div><i class="bi bi-archive text-success me-1" title="Direktori Arsip"></i> <strong>Arsip:</strong> <code><?= $e($b['DirektoriArsip']) ?></code></div>
                        </td>
                        <td class="small">
                            <div><span class="badge bg-secondary-subtle text-secondary-emphasis mb-1">Level <?= $e($tingkat) ?></span></div>
                            <div>UPI: <code><?= $e($b['UnitUpi'] ?: '-') ?></code></div>
                            <div>AP: <code><?= $e($b['UnitAp'] ?: 'Semua AP') ?></code></div>
                            <div>UP: <code><?= $e($b['UnitUp'] ?: 'Semua UP') ?></code></div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= $aktif ? 'success' : 'secondary' ?>"><?= $aktif ? 'Aktif' : 'Non-Aktif' ?></span>
                        </td>
                        <td class="small" style="min-width: 220px;">
                            <div title="Waktu konfigurasi bot ini terakhir disimpan">
                                <i class="bi bi-gear me-1 text-muted"></i>Konfigurasi: <?= $fmt_waktu($b['WaktuData'] ?? null) ?>
                            </div>
                            <?php if ($ada_cron): ?>
                                <div class="mt-1" title="Waktu cron terakhir memeriksa folder bot ini">
                                    <i class="bi bi-clock-history me-1 text-muted"></i>Cron:
                                    <?php if ($t_jalan): ?>
                                        <?= $fmt_waktu($t_jalan) ?>
                                        <?php if ($t_status !== ''): ?>
                                            <span class="badge bg-<?= $kelas_status[$t_status] ?? 'secondary' ?> ms-1"><?= $e($t_status) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">belum pernah jalan</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($t_pesan !== ''): ?>
                                    <div class="text-muted text-truncate" style="max-width: 280px;" title="<?= $e($t_pesan) ?>"><?= $e($t_pesan) ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <form method="post" action="" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="status">
                                <input type="hidden" name="id" value="<?= (int) $b['IdBot'] ?>">
                                <input type="hidden" name="ke" value="<?= $aktif ? 'TIDAK' : 'AKTIF' ?>">
                                <?php if ($aktif): ?>
                                    <button type="submit" class="btn btn-sm btn-outline-warning"><i class="bi bi-stop-fill"></i> Non-Aktifkan</button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-play-fill"></i> Aktifkan</button>
                                <?php endif; ?>
                            </form>
                            <button type="button" class="btn btn-sm btn-outline-primary btn-ubah-bot"
                                    data-bot="<?= $e(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
                                <i class="bi bi-pencil-square"></i> Ubah
                            </button>
                            <form method="post" action="" class="d-inline form-hapus-bot" data-nama="<?= $e($b['JenisBot']) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id" value="<?= (int) $b['IdBot'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah / Ubah Bot Akses -->
<div class="modal fade" id="modalBot" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="post" action="" class="modal-content border-0 shadow rounded-4" id="formBot" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="id" id="botId" value="0">
            <div class="modal-header">
                <h5 class="modal-title" id="judulModalBot">Tambah Akses Bot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= $e($er) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold" for="botJenis">Jenis Bot</label>
                        <select class="form-select" id="botJenis" name="jenis" required>
                            <?php foreach ($jenis_opsi as $k => $v): ?><option value="<?= $e($k) ?>"><?= $e($v) ?></option><?php endforeach; ?>
                        </select>
                        <div class="form-text" id="botJenisInfo"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="botStatus">Status</label>
                        <select class="form-select" id="botStatus" name="status">
                            <option value="AKTIF">Aktif</option>
                            <option value="TIDAK">Non-Aktif</option>
                        </select>
                    </div>

                    <!-- Wilayah: UPI (SA.KP memilih; akun lain terkunci pada UPI-nya) -> AP -> UP -->
                    <?php if ($hak_sa): ?>
                        <div class="<?= $kol_unit ?>">
                            <label class="form-label fw-semibold" for="botUnitUpi">Unit UPI</label>
                            <select class="form-select" id="botUnitUpi" name="unit_upi" required>
                                <option value="">-- Pilih UPI --</option>
                                <?php foreach ($daftar_upi as $upi): ?>
                                    <option value="<?= $e($upi) ?>"><?= $e($upi) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" id="botUnitUpi" name="unit_upi" value="<?= $e($upi_ses) ?>">
                    <?php endif; ?>
                    <div class="<?= $kol_unit ?>">
                        <label class="form-label fw-semibold" for="botUnitAp">Unit AP</label>
                        <select class="form-select" id="botUnitAp" name="unit_ap">
                            <option value="">-- Semua Unit AP --</option>
                            <?php foreach ($daftar_ap as $ap): ?>
                                <option value="<?= $e($ap['UnitAp']) ?>" data-upi="<?= $e($ap['UnitUpi'] ?? '') ?>"><?= $e($ap['UnitAp'] . ' - ' . ($ap['NamaUnit'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="<?= $kol_unit ?>">
                        <label class="form-label fw-semibold" for="botUnitUp">Unit UP</label>
                        <select class="form-select" id="botUnitUp" name="unit_up">
                            <option value="">-- Semua Unit UP --</option>
                            <?php foreach ($daftar_up as $up): ?>
                                <option value="<?= $e($up['UnitUp']) ?>" data-ap="<?= $e($up['UnitAp'] ?? '') ?>" data-upi="<?= $e($up['UnitUpi'] ?? '') ?>"><?= $e($up['UnitUp'] . ' - ' . ($up['NamaUnit'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 small text-muted" id="botLevelInfo"></div>

                    <div class="col-12"><hr class="my-1"><div class="small text-muted">Jalur direktori ditulis relatif terhadap lokasi utama bot (gunakan karakter <code>/</code>; hanya huruf, angka, <code>_</code> dan <code>-</code>). Nama folder <code>log</code> dicadangkan untuk log cron, dan Direktori Utama tidak boleh sama/bertumpuk dengan bot lain.</div></div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="botDirUtama"><i class="bi bi-folder-symlink text-primary me-1"></i> Direktori Utama</label>
                        <input type="text" class="form-control font-monospace" id="botDirUtama" name="direktori_utama" maxlength="255" placeholder="contoh: 13/56610/PELUNASAN" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="botDirArsip"><i class="bi bi-archive text-success me-1"></i> Direktori Arsip</label>
                        <input type="text" class="form-control font-monospace" id="botDirArsip" name="direktori_arsip" maxlength="255" placeholder="contoh: 13/56610/PELUNASAN/Arsip" required>
                    </div>
                    <div class="col-12 small text-muted" id="botDirInfo"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan Konfigurasi</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('modalBot');
    var modal = new bootstrap.Modal(el);
    var f = {
        id: document.getElementById('botId'),
        jenis: document.getElementById('botJenis'),
        status: document.getElementById('botStatus'),
        dirUtama: document.getElementById('botDirUtama'),
        dirArsip: document.getElementById('botDirArsip'),
        unitAp: document.getElementById('botUnitAp'),
        unitUp: document.getElementById('botUnitUp'),
        unitUpi: document.getElementById('botUnitUpi')
    };
    var judul = document.getElementById('judulModalBot');
    var jenisEl = document.getElementById('botJenisInfo');
    var levelEl = document.getElementById('botLevelInfo');
    var dirInfoEl = document.getElementById('botDirInfo');
    var jenisInfo = <?= json_encode(array_map(fn($j) => $j['format'] . ' (Tabel Tujuan: ' . $j['tabel'] . ')', $jenis_info), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    // Akun terikat AP/UP: pilihan otomatis terisi (server tetap memaksa sesuai cakupan akun)
    var kunci = <?= json_encode(['upi' => $upi_ses, 'ap' => $kunci_ap, 'up' => $kunci_up], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var dirty = false;   // true bila direktori sudah diketik/diedit manual -> jangan ditimpa isian otomatis

    function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

    function level() { return f.unitUp.value ? 'UP' : (f.unitAp.value ? 'AP' : 'UPI'); }

    function tampilInfo() {
        jenisEl.textContent = jenisInfo[f.jenis.value] || '';
        var l = level();
        levelEl.innerHTML = 'Cakupan bot: <strong>Level ' + l + '</strong>'
            + (l === 'UP' ? ' (satu UP).' : l === 'AP' ? ' (semua UP aktif di bawah AP ini).' : ' (semua UP aktif di bawah UPI ini).');
        dirInfoEl.innerHTML = l === 'UP'
            ? 'Taruh file <code>.xlsx</code> <strong>langsung</strong> di Direktori Utama. Hasil dipindah ke <code>Arsip/berhasil|gagal/tanggal</code>.'
            : 'Taruh file di subfolder kode UP: <code>Direktori Utama/&lt;KodeUP&gt;/</code>'
              + (f.jenis.value === 'DIL' ? ' (khusus DIL boleh langsung di Direktori Utama; unit dibaca dari isi file)' : '')
              + '. Hasil dipindah ke <code>Arsip/&lt;KodeUP&gt;/berhasil|gagal/tanggal</code>. Subfolder UP dibuat otomatis.';
    }

    // Sembunyikan pilihan AP/UP yang tidak sesuai UPI/AP terpilih
    function filterUnit() {
        var upi = f.unitUpi.value, ap = f.unitAp.value;
        Array.prototype.forEach.call(f.unitAp.options, function (o) {
            if (o.value === '') return;
            var ok = !upi || o.getAttribute('data-upi') === upi;
            o.hidden = !ok; o.disabled = !ok;
        });
        if (f.unitAp.value && f.unitAp.selectedOptions[0] && f.unitAp.selectedOptions[0].disabled) { f.unitAp.value = ''; ap = ''; }
        Array.prototype.forEach.call(f.unitUp.options, function (o) {
            if (o.value === '') return;
            var ok = (!upi || o.getAttribute('data-upi') === upi) && (!ap || o.getAttribute('data-ap') === ap);
            o.hidden = !ok; o.disabled = !ok;
        });
        if (f.unitUp.value && f.unitUp.selectedOptions[0] && f.unitUp.selectedOptions[0].disabled) f.unitUp.value = '';
    }

    // Isian otomatis (hanya bot baru): <UPI>/<UP atau AP>/<JENIS> dan .../Arsip. Level UPI: <UPI>/<JENIS>.
    function updateOtomatisDirektori() {
        if (String(f.id.value) !== '0' || dirty) return;
        var upi = (f.unitUpi.value || '').trim();
        var jenis = (f.jenis.value || '').trim();
        if (!upi || !jenis) return;
        var seg = [upi];
        if (f.unitUp.value) seg.push(f.unitUp.value); else if (f.unitAp.value) seg.push(f.unitAp.value);
        seg.push(jenis);
        var base = seg.join('/');
        f.dirUtama.value = base;
        f.dirArsip.value = base + '/Arsip';
    }

    function isi(d) {
        f.id.value = d.id || 0;
        f.jenis.value = d.jenis || 'PELUNASAN';
        f.status.value = d.status || 'AKTIF';
        f.dirUtama.value = d.direktori_utama || '';
        f.dirArsip.value = d.direktori_arsip || '';
        dirty = !!(d.direktori_utama || d.direktori_arsip);

        f.unitUpi.value = d.unit_upi || kunci.upi || '';
        if (!f.unitUpi.value && f.unitUpi.tagName === 'SELECT' && f.unitUpi.options.length === 2) f.unitUpi.selectedIndex = 1;   // hanya satu UPI
        filterUnit();
        f.unitAp.value = d.unit_ap || (d.id ? '' : kunci.ap) || '';
        filterUnit();
        f.unitUp.value = d.unit_up || (d.id ? '' : kunci.up) || '';
        filterUnit();

        var ubah = (d.id || 0) > 0;
        judul.textContent = ubah ? 'Ubah Akses Bot #' + d.id : 'Tambah Akses Bot';
        tampilInfo();
        if (!ubah) updateOtomatisDirektori();
    }

    f.dirUtama.addEventListener('input', function () { dirty = true; });
    f.dirArsip.addEventListener('input', function () { dirty = true; });

    f.jenis.addEventListener('change', function () { tampilInfo(); updateOtomatisDirektori(); });
    f.unitUpi.addEventListener('change', function () { filterUnit(); tampilInfo(); updateOtomatisDirektori(); });
    f.unitAp.addEventListener('change', function () {
        var o = f.unitAp.selectedOptions[0];
        if (o && o.value && !f.unitUpi.value) f.unitUpi.value = o.getAttribute('data-upi') || '';
        filterUnit(); tampilInfo(); updateOtomatisDirektori();
    });
    f.unitUp.addEventListener('change', function () {
        var o = f.unitUp.selectedOptions[0];
        if (o && o.value) {      // UP dipilih -> AP & UPI ikut terisi
            if (!f.unitAp.value) f.unitAp.value = o.getAttribute('data-ap') || '';
            if (!f.unitUpi.value) f.unitUpi.value = o.getAttribute('data-upi') || '';
        }
        filterUnit(); tampilInfo(); updateOtomatisDirektori();
    });

    document.getElementById('btnTambahBot').addEventListener('click', function () {
        isi({});
        modal.show();
    });

    document.querySelectorAll('.btn-ubah-bot').forEach(function (b) {
        b.addEventListener('click', function () {
            isi(JSON.parse(b.getAttribute('data-bot')));
            modal.show();
        });
    });

    // Konfirmasi hapus
    document.querySelectorAll('.form-hapus-bot').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (form.dataset.ok) return;
            ev.preventDefault();
            var nama = form.getAttribute('data-nama');
            var lanjut = function () { form.dataset.ok = '1'; form.submit(); };
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Hapus konfigurasi bot?',
                    html: 'Konfigurasi bot <b>' + esc(nama) + '</b> akan dihapus. Folder dan file di server tidak ikut dihapus.',
                    showCancelButton: true,
                    confirmButtonText: 'Hapus',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#dc3545'
                }).then(function (r) { if (r.isConfirmed) lanjut(); });
            } else if (confirm('Hapus konfigurasi bot ' + nama + '?')) { lanjut(); }
        });
    });

<?php if ($form_isi !== null && $buka_form): ?>
    isi(<?= json_encode($form_isi, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
    modal.show();
<?php endif; ?>
});
</script>