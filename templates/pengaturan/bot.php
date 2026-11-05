<?php
// templates/pengaturan/bot.php -> TAMPILAN halaman Daftar Bot
// Variabel yang disiapkan oleh modules/pengaturan/bot.php:
// $bots, $pesan, $errors, $form_isi, $buka_form, $jenis_opsi, $jenis_info, $pola_opsi, $root_bot, $root_konfig, $tingkat_bot, $daftar_ap, $daftar_up
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$badge_status = ['BELUM' => 'secondary', 'BERHASIL' => 'success', 'SEBAGIAN' => 'warning', 'GAGAL' => 'danger'];
$root_tampil  = $root_bot !== '' ? $root_bot : str_replace('\\', '/', (string) $root_konfig);
?>
<div class="container-fluid" style="max-width: 1200px;">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-robot me-2"></i>Daftar Bot</h4>
            <p class="text-muted mb-0">Tambah, ubah, lalu mulai bot unggah file dan atur tujuan foldernya. Bot berstatus <strong>Aktif</strong> dijalankan otomatis oleh bot.</p>
        </div>
        <button type="button" class="btn btn-primary" id="btnTambahBot">
            <i class="bi bi-plus-lg me-1"></i> Tambah Bot
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
        Folder bot: <code><?= $e($root_tampil) ?></code>
        <?php if ($root_bot === ''): ?>
            — <strong>folder ini belum ada.</strong> Buat folder tersebut (atau definisikan <code>BOT_ROOT</code>) supaya folder bot bisa dibuat otomatis.
        <?php else: ?>
            — semua folder di bawah ditulis <strong>relatif</strong> terhadap folder ini.
        <?php endif; ?>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Bot</th>
                        <th>Jenis</th>
                        <th>Folder (relatif)</th>
                        <th>Wilayah</th>
                        <th class="text-center">Status</th>
                        <th>Terakhir Jalan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$bots): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">Belum ada bot. Klik <strong>Tambah Bot</strong> untuk membuat yang pertama.</td></tr>
                <?php endif; ?>
                <?php foreach ($bots as $b): ?>
                    <?php
                        $data = [
                            'id' => (int) $b['IdBot'], 'kode' => $b['KodeBot'], 'nama' => $b['NamaBot'], 'jenis' => $b['JenisBot'],
                            'keterangan' => (string) ($b['Keterangan'] ?? ''), 'folder_masuk' => $b['FolderMasuk'],
                            'folder_berhasil' => $b['FolderBerhasil'], 'folder_gagal' => $b['FolderGagal'],
                            'pola' => $b['PolaFile'], 'status' => $b['StatusData'],
                            'unit_upi' => $b['UnitUpi'], 'unit_ap' => $b['UnitAp'], 'unit_up' => $b['UnitUp']
                        ];
                        $aktif = $b['StatusData'] === 'AKTIF';
                    ?>
                    <tr class="<?= $aktif ? '' : 'text-muted' ?>">
                        <td>
                            <div class="fw-semibold"><?= $e($b['NamaBot']) ?></div>
                            <div class="small text-muted"><code><?= $e($b['KodeBot']) ?></code><?= !empty($b['Keterangan']) ? ' · ' . $e($b['Keterangan']) : '' ?></div>
                        </td>
                        <td><span class="badge bg-primary-subtle text-primary-emphasis"><?= $e($jenis_opsi[$b['JenisBot']] ?? $b['JenisBot']) ?></span></td>
                        <td class="small">
                            <div><i class="bi bi-box-arrow-in-right text-primary me-1" title="Masuk"></i><code><?= $e($b['FolderMasuk']) ?></code></div>
                            <div><i class="bi bi-check-circle text-success me-1" title="Berhasil"></i><code><?= $e($b['FolderBerhasil']) ?></code></div>
                            <div><i class="bi bi-x-circle text-danger me-1" title="Gagal"></i><code><?= $e($b['FolderGagal']) ?></code></div>
                            <div class="text-muted">Pola: <?= $e($b['PolaFile']) ?></div>
                        </td>
                        <td class="small">
                            <div>UPI: <code><?= $e($b['UnitUpi'] ?: '-') ?></code></div>
                            <div>AP: <code><?= $e($b['UnitAp'] ?: 'Semua AP') ?></code></div>
                            <div>UP: <code><?= $e($b['UnitUp'] ?: 'Semua UP') ?></code></div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= $aktif ? 'success' : 'secondary' ?>"><?= $aktif ? 'Aktif' : 'Berhenti' ?></span>
                        </td>
                        <td class="small">
                            <?php if ($b['TerakhirJalan']): ?>
                                <div><?= $e(date('d/m/Y H:i', strtotime($b['TerakhirJalan']))) ?></div>
                            <?php endif; ?>
                            <span class="badge bg-<?= $badge_status[$b['StatusTerakhir']] ?? 'secondary' ?>"><?= $e($b['StatusTerakhir']) ?></span>
                            <?php if ($b['PesanTerakhir']): ?><div class="text-muted"><?= $e($b['PesanTerakhir']) ?></div><?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <form method="post" action="" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="status">
                                <input type="hidden" name="id" value="<?= (int) $b['IdBot'] ?>">
                                <input type="hidden" name="ke" value="<?= $aktif ? 'TIDAK' : 'AKTIF' ?>">
                                <?php if ($aktif): ?>
                                    <button type="submit" class="btn btn-sm btn-outline-warning"><i class="bi bi-stop-fill"></i> Hentikan</button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-play-fill"></i> Mulai</button>
                                <?php endif; ?>
                            </form>
                            <button type="button" class="btn btn-sm btn-outline-primary btn-ubah-bot"
                                    data-bot="<?= $e(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
                                <i class="bi bi-pencil-square"></i> Ubah
                            </button>
                            <form method="post" action="" class="d-inline form-hapus-bot" data-nama="<?= $e($b['NamaBot']) ?>">
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

<!-- Modal tambah / ubah bot -->
<div class="modal fade" id="modalBot" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="post" action="" class="modal-content border-0 shadow rounded-4" id="formBot" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="id" id="botId" value="0">
            <div class="modal-header">
                <h5 class="modal-title" id="judulModalBot">Tambah Bot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= $e($er) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="botKode">Kode Bot</label>
                        <input type="text" class="form-control" id="botKode" name="kode" maxlength="30" pattern="[a-z0-9_]{3,30}"
                               placeholder="upload_pelunasan" required>
                        <div class="form-text" id="botKodeInfo">Huruf kecil, angka, _. Tidak bisa diubah setelah disimpan.</div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="botNama">Nama Bot</label>
                        <input type="text" class="form-control" id="botNama" name="nama" maxlength="100" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="botJenis">Jenis</label>
                        <select class="form-select" id="botJenis" name="jenis" required>
                            <?php foreach ($jenis_opsi as $k => $v): ?><option value="<?= $e($k) ?>"><?= $e($v) ?></option><?php endforeach; ?>
                        </select>
                        <div class="form-text" id="botJenisInfo"></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="botPola">Pola file</label>
                        <select class="form-select" id="botPola" name="pola">
                            <?php foreach ($pola_opsi as $k => $v): ?><option value="<?= $e($k) ?>"><?= $e($v) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="botStatus">Status</label>
                        <select class="form-select" id="botStatus" name="status">
                            <option value="AKTIF">Aktif (langsung dimulai)</option>
                            <option value="TIDAK">Berhenti (belum dimulai)</option>
                        </select>
                    </div>

                    <!-- Pilihan Wilayah Unit AP & UP -->
                    <div class="col-md-6">
                        <label class="form-label" for="botUnitAp">Unit AP</label>
                        <select class="form-select" id="botUnitAp" name="unit_ap">
                            <option value="">-- Pilih Unit AP (Opsional/Semua) --</option>
                            <?php if (!empty($daftar_ap)): ?>
                                <?php foreach ($daftar_ap as $ap): ?>
                                    <option value="<?= $e($ap['UnitAp']) ?>"><?= $e($ap['UnitAp'] . ' - ' . ($ap['NamaAp'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="botUnitUp">Unit UP</label>
                        <select class="form-select" id="botUnitUp" name="unit_up">
                            <option value="">-- Pilih Unit UP (Opsional/Semua) --</option>
                            <?php if (!empty($daftar_up)): ?>
                                <?php foreach ($daftar_up as $up): ?>
                                    <option value="<?= $e($up['UnitUp']) ?>" data-ap="<?= $e($up['UnitAp'] ?? '') ?>"><?= $e($up['UnitUp'] . ' - ' . ($up['NamaUp'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <!-- Sembunyikan Unit UPI otomatis mengikuti akun / pilihan pertama -->
                    <input type="hidden" id="botUnitUpi" name="unit_upi" value="<?= $e($_SESSION['UnitUpi'] ?? '13') ?>">

                    <div class="col-12">
                        <label class="form-label" for="botKet">Keterangan <span class="text-muted">(opsional)</span></label>
                        <input type="text" class="form-control" id="botKet" name="keterangan" maxlength="255">
                    </div>

                    <div class="col-12"><hr class="my-1"><div class="small text-muted">Folder ditulis relatif terhadap folder bot, dipisah <code>/</code>. Folder dibuat otomatis saat disimpan.</div></div>
                    <div class="col-md-4">
                        <label class="form-label" for="botFm"><i class="bi bi-box-arrow-in-right text-primary"></i> Folder masuk</label>
                        <input type="text" class="form-control font-monospace" id="botFm" name="folder_masuk" maxlength="150" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="botFb"><i class="bi bi-check-circle text-success"></i> Pindah ke (berhasil)</label>
                        <input type="text" class="form-control font-monospace" id="botFb" name="folder_berhasil" maxlength="150" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="botFg"><i class="bi bi-x-circle text-danger"></i> Pindah ke (gagal)</label>
                        <input type="text" class="form-control font-monospace" id="botFg" name="folder_gagal" maxlength="150" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('modalBot');
    var modal = new bootstrap.Modal(el);
    var f = {
        id: document.getElementById('botId'), kode: document.getElementById('botKode'), nama: document.getElementById('botNama'),
        jenis: document.getElementById('botJenis'), pola: document.getElementById('botPola'), status: document.getElementById('botStatus'),
        ket: document.getElementById('botKet'), fm: document.getElementById('botFm'), fb: document.getElementById('botFb'), fg: document.getElementById('botFg'),
        unitAp: document.getElementById('botUnitAp'), unitUp: document.getElementById('botUnitUp'), unitUpi: document.getElementById('botUnitUpi')
    };
    var judul = document.getElementById('judulModalBot');
    var jenisInfo = <?= json_encode(array_map(fn($j) => $j['format'] . ' Tujuan: ' . $j['tabel'] . '.', $jenis_info), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var jenisEl = document.getElementById('botJenisInfo');
    function tampilJenis() { jenisEl.textContent = jenisInfo[f.jenis.value] || ''; }
    f.jenis.addEventListener('change', tampilJenis);

    function isi(d) {
        f.id.value = d.id || 0; f.kode.value = d.kode || ''; f.nama.value = d.nama || '';
        f.jenis.value = d.jenis || 'PELUNASAN'; tampilJenis(); f.pola.value = d.pola || '*.xlsx'; f.status.value = d.status || 'AKTIF';
        f.ket.value = d.keterangan || '';
        f.fm.value = d.folder_masuk || ''; f.fb.value = d.folder_berhasil || ''; f.fg.value = d.folder_gagal || '';
        f.unitAp.value = d.unit_ap || ''; f.unitUp.value = d.unit_up || ''; f.unitUpi.value = d.unit_upi || '<?= $_SESSION['UnitUpi'] ?? '13' ?>';
        
        var ubah = (d.id || 0) > 0;
        f.kode.readOnly = ubah;
        judul.textContent = ubah ? 'Ubah Bot' : 'Tambah Bot';
        [f.fm, f.fb, f.fg].forEach(function (x) { x.dataset.manual = ubah ? '1' : ''; });
    }

    // Saran folder otomatis dari kode bot
    f.kode.addEventListener('input', function () {
        var k = f.kode.value.replace(/[^a-z0-9_]/g, '');
        if (!f.fm.dataset.manual) f.fm.value = k ? k + '/masuk' : '';
        if (!f.fb.dataset.manual) f.fb.value = k ? k + '/berhasil' : '';
        if (!f.fg.dataset.manual) f.fg.value = k ? k + '/gagal' : '';
    });
    [f.fm, f.fb, f.fg].forEach(function (x) { x.addEventListener('input', function () { x.dataset.manual = '1'; }); });

    document.getElementById('btnTambahBot').addEventListener('click', function () { isi({}); modal.show(); });
    document.querySelectorAll('.btn-ubah-bot').forEach(function (b) {
        b.addEventListener('click', function () { isi(JSON.parse(b.getAttribute('data-bot'))); modal.show(); });
    });

    // Konfirmasi hapus
    document.querySelectorAll('.form-hapus-bot').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (form.dataset.ok) return;
            ev.preventDefault();
            var nama = form.getAttribute('data-nama');
            var lanjut = function () { form.dataset.ok = '1'; form.submit(); };
            if (window.Swal) {
                Swal.fire({ icon: 'warning', title: 'Hapus bot?', html: 'Bot <b></b> akan dihapus. Folder dan file di dalamnya tidak ikut dihapus.',
                            showCancelButton: true, confirmButtonText: 'Hapus', cancelButtonText: 'Batal', confirmButtonColor: '#dc3545',
                            didOpen: function (p) { p.querySelector('b').textContent = nama; } })
                    .then(function (r) { if (r.isConfirmed) lanjut(); });
            } else if (confirm('Hapus bot ' + nama + '?')) { lanjut(); }
        });
    });

<?php if ($form_isi !== null && $buka_form): ?>
    isi(<?= json_encode($form_isi, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
    modal.show();
<?php endif; ?>
});
</script>