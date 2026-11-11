<?php
// templates/pengaturan/bot.php -> TAMPILAN halaman Daftar Bot (Tabel bot_akses)
// Variabel yang disiapkan oleh modules/pengaturan/bot.php:
// $bots, $pesan, $errors, $form_isi, $buka_form, $jenis_opsi, $jenis_info, $root_bot, $root_konfig, $daftar_ap, $daftar_up
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$root_tampil  = $root_bot !== '' ? $root_bot : str_replace('\\', '/', (string) $root_konfig);
?>
<div class="container-fluid" style="max-width: 1200px;">

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
            — <strong>folder ini belum ada.</strong> Buat folder tersebut (atau definisikan <code>BOT_ROOT</code>) agar jalur folder bisa dibuat otomatis.
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
                        $aktif = $b['StatusData'] === 'AKTIF';
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
                            <div><i class="bi bi-archive text-success me-1" title="Direktori Arsip"></i> <strong>Arsip:</strong> <code><?= $e($b['DirektoriArsip']) ?></code></div>
                        </td>
                        <td class="small">
                            <div>UPI: <code><?= $e($b['UnitUpi'] ?: '-') ?></code></div>
                            <div>AP: <code><?= $e($b['UnitAp'] ?: 'Semua AP') ?></code></div>
                            <div>UP: <code><?= $e($b['UnitUp'] ?: 'Semua UP') ?></code></div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= $aktif ? 'success' : 'secondary' ?>"><?= $aktif ? 'Aktif' : 'Non-Aktif' ?></span>
                        </td>
                        <td class="small">
                            <?= $b['WaktuData'] ? $e(date('d/m/Y H:i', strtotime($b['WaktuData']))) : '-' ?>
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

                    <!-- Pilihan Wilayah Unit AP & UP -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="botUnitAp">Unit AP</label>
                        <select class="form-select" id="botUnitAp" name="unit_ap">
                            <option value="">-- Semua Unit AP --</option>
                            <?php if (!empty($daftar_ap)): ?>
                                <?php foreach ($daftar_ap as $ap): ?>
                                    <option value="<?= $e($ap['UnitAp']) ?>"><?= $e($ap['UnitAp'] . ' - ' . ($ap['NamaUnit'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="botUnitUp">Unit UP</label>
                        <select class="form-select" id="botUnitUp" name="unit_up">
                            <option value="">-- Semua Unit UP --</option>
                            <?php if (!empty($daftar_up)): ?>
                                <?php foreach ($daftar_up as $up): ?>
                                    <option value="<?= $e($up['UnitUp']) ?>" data-ap="<?= $e($up['UnitAp'] ?? '') ?>"><?= $e($up['UnitUp'] . ' - ' . ($up['NamaUnit'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <!-- Hidden Unit UPI -->
                    <input type="hidden" id="botUnitUpi" name="unit_upi" value="<?= $e($_SESSION['UnitUpi'] ?? '13') ?>">

                    <div class="col-12"><hr class="my-1"><div class="small text-muted">Jalur direktori ditulis relatif terhadap lokasi utama bot (gunakan karakter <code>/</code>).</div></div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="botDirUtama"><i class="bi bi-folder-symlink text-primary me-1"></i> Direktori Utama</label>
                        <input type="text" class="form-control font-monospace" id="botDirUtama" name="direktori_utama" maxlength="255" placeholder="contoh: 56/56610/pelunasan" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="botDirArsip"><i class="bi bi-archive text-success me-1"></i> Direktori Arsip</label>
                        <input type="text" class="form-control font-monospace" id="botDirArsip" name="direktori_arsip" maxlength="255" placeholder="contoh: 56/56610/pelunasan/Arsip" required>
                    </div>
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
    var jenisInfo = <?= json_encode(array_map(fn($j) => $j['format'] . ' (Tabel Tujuan: ' . $j['tabel'] . ')', $jenis_info), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var jenisEl = document.getElementById('botJenisInfo');
    
    function tampilJenis() { jenisEl.textContent = jenisInfo[f.jenis.value] || ''; }
    f.jenis.addEventListener('change', tampilJenis);

    // Fungsi otomatisasi format direktori: [unitupi]/[unitup]/[jenisbot] dan [unitupi]/[unitup]/[jenisbot]/Arsip
    function updateOtomatisDirektori() {
        if (f.id.value == '0') {
            var upi = f.unitUpi.value.trim() || '13';
            var up = f.unitUp.value.trim() || 'umum';
            var jenis = f.jenis.value.trim();

            if (jenis) {
                var basePath = upi + '/' + up + '/' + jenis;
                f.dirUtama.value = basePath;
                f.dirArsip.value = basePath + '/Arsip';
            }
        }
    }

    function filterUnitUp() {
        var selectedAp = f.unitAp.value;
        var options = f.unitUp.querySelectorAll('option');
        
        options.forEach(function (opt) {
            if (opt.value === '') {
                opt.style.display = '';
                return;
            }
            var apAttr = opt.getAttribute('data-ap');
            if (!selectedAp || apAttr === selectedAp) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        });
    }

    function isi(d) {
        f.id.value = d.id || 0;
        f.jenis.value = d.jenis || 'PELUNASAN'; 
        tampilJenis(); 
        f.status.value = d.status || 'AKTIF';
        f.dirUtama.value = d.direktori_utama || ''; 
        f.dirArsip.value = d.direktori_arsip || '';
        f.unitAp.value = d.unit_ap || ''; 
        f.unitUp.value = d.unit_up || ''; 
        f.unitUpi.value = d.unit_upi || '<?= $_SESSION['UnitUpi'] ?? '13' ?>';
        
        filterUnitUp();

        var ubah = (d.id || 0) > 0;
        judul.textContent = ubah ? 'Ubah Akses Bot #' + d.id : 'Tambah Akses Bot';

        if (!ubah && !f.dirUtama.value) {
            updateOtomatisDirektori();
        }
    }

    f.jenis.addEventListener('change', updateOtomatisDirektori);
    f.unitUp.addEventListener('change', updateOtomatisDirektori);
    f.unitAp.addEventListener('change', function () {
        filterUnitUp();
        updateOtomatisDirektori();
    });

    document.getElementById('btnTambahBot').addEventListener('click', function () { 
        isi({}); 
        updateOtomatisDirektori();
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
                    html: 'Konfigurasi bot <b>' + nama + '</b> akan dihapus.',
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