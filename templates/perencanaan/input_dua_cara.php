<?php
// templates/perencanaan/input_dua_cara.php  -  Template bersama Input Kepentingan & Input Survey SR
// Variabel: $cfg (konfigurasi halaman), $state (hasil pi_proses)
// $state tambahan: tingkat ('UP'|'AP'|null), nama_upi, nama_ap, nama_up, opsi_up (kode => SingkatanNama), sel_up, scope_label

$mode = $state['mode'];
$h    = $state['hasil'];

echo pr_header([
    'title'    => $cfg['judul'],
    'subtitle' => $cfg['subtitle'],
    'action'   => $cfg['action'],
    'buttons'  => [['label' => 'Lihat Hasil', 'icon' => 'bi-bar-chart-line', 'href' => pr_url($cfg['link_hasil']), 'class' => 'btn btn-outline-primary btn-sm px-3 rounded-2']],
]);

if ($state['sukses'])     echo pr_alert('success', pr_e($state['sukses']) . ' <a href="' . pr_e(pr_url($cfg['link_hasil'])) . '" class="alert-link">Lihat hasilnya &rarr;</a>');
if ($state['peringatan']) echo pr_alert('warning', pr_e($state['peringatan']), 'bi-exclamation-triangle-fill');
if ($state['error'])      echo pr_alert('danger', pr_e($state['error']));
if (!$state['unit_siap']) echo pr_alert('warning', 'Akun Anda harus memiliki Unit AP (dan Unit UPI), atau Unit UP, agar dapat menyimpan data. Lengkapi di Master Pengguna.');

if ($h): ?>
<div class="row g-3 mb-4">
    <?= pr_kpi('Baris Dibaca', pr_n($h['dibaca']), pr_e($h['nama']) . ' · ' . $h['durasi'] . ' detik', 'bi-file-earmark-spreadsheet', 'primary') ?>
    <?= pr_kpi('Data Baru', pr_n($h['baru']), 'Periode ' . $h['periode'], 'bi-plus-circle', 'success', true) ?>
    <?= pr_kpi('Diperbarui', pr_n($h['diperbarui']), $h['duplikat'] > 0 ? pr_n($h['duplikat']) . ' duplikat di file, dipakai baris terakhir' : 'Sudah ada, nilai diganti', 'bi-arrow-repeat', 'warning', $h['diperbarui'] > 0) ?>
    <?= pr_kpi('Ditolak', pr_n($h['ditolak']), 'Format data tidak valid', 'bi-x-octagon', 'danger', $h['ditolak'] > 0) ?>
</div>
<?php endif; ?>

<!-- WILAYAH TUJUAN: UP terkunci | AP memilih UP -->
<?php if ($state['tingkat'] !== null): ?>
<div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
    <div class="card-body px-4 py-3">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label fw-bold small text-uppercase text-secondary mb-1" style="letter-spacing:.5px;"><i class="bi bi-diagram-3 me-1"></i> Unit UPI</label>
                <input type="text" class="form-control form-control-sm bg-light" value="<?= pr_e($state['nama_upi'] ?: '-') ?>" readonly>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label fw-bold small text-uppercase text-secondary mb-1" style="letter-spacing:.5px;"><i class="bi bi-building me-1"></i> Unit AP</label>
                <input type="text" class="form-control form-control-sm bg-light" value="<?= pr_e($state['nama_ap'] ?: '-') ?>" readonly>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label fw-bold small text-uppercase text-secondary mb-1" for="sel_up_tujuan" style="letter-spacing:.5px;"><i class="bi bi-geo-alt me-1"></i> Unit UP <?= $state['tingkat'] === 'AP' ? '<span class="text-danger">*</span>' : '' ?></label>
                <?php if ($state['tingkat'] === 'UP'): ?>
                    <input type="text" class="form-control form-control-sm bg-light" value="<?= pr_e($state['nama_up'] ?: '-') ?>" readonly>
                <?php else: ?>
                    <select id="sel_up_tujuan" class="form-select form-select-sm">
                        <option value="">— Pilih Unit UP —</option>
                        <?php foreach ($state['opsi_up'] as $kode_up => $nama_up_opt): ?>
                            <option value="<?= pr_e((string) $kode_up) ?>" <?= (string) $kode_up === (string) $state['sel_up'] ? 'selected' : '' ?>><?= pr_e($nama_up_opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
        </div>
        <div class="small text-muted mt-3">
            <i class="bi bi-geo-alt-fill me-1"></i>
            <?php if ($state['tingkat'] === 'UP'): ?>
                Data disimpan untuk unit Anda: <strong><?= pr_e($state['scope_label']) ?></strong>.
            <?php else: ?>
                Pilih UP tujuan di bawah AP Anda. Data disimpan untuk wilayah <strong><?= pr_e($state['scope_label']) ?><?= $state['sel_up'] === '' ? ' › (belum dipilih)' : '' ?></strong>.
            <?php endif; ?>
            Pelanggan yang sudah tercatat di unit lain akan dilewati.
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
    <div class="card-header bg-white px-4 pt-3 pb-0 border-bottom">
        <ul class="nav nav-tabs border-0" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-medium <?= $mode === 'upload' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabUpload" type="button" role="tab"><i class="bi bi-cloud-arrow-up me-2"></i>Upload Excel</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-medium <?= $mode === 'manual' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabManual" type="button" role="tab"><i class="bi bi-pencil-square me-2"></i>Input Satu-satu</button>
            </li>
        </ul>
    </div>
    <div class="tab-content">

        <!-- UPLOAD -->
        <div class="tab-pane fade <?= $mode === 'upload' ? 'show active' : '' ?>" id="tabUpload" role="tabpanel">
            <form method="POST" action="<?= pr_e(pr_url($cfg['action'])) ?>" enctype="multipart/form-data" class="form-proses">
                <input type="hidden" name="mode" value="upload">
                <input type="hidden" name="unit_up" class="unit-up-hidden" value="<?= pr_e($state['sel_up']) ?>">
                <div class="card-body px-4 py-4">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="periode_up" class="form-label fw-bold small text-uppercase text-secondary" style="letter-spacing:.5px;">Periode (Tahun)</label>
                            <input type="number" name="periode" id="periode_up" class="form-control" min="1901" max="2155" step="1" value="<?= pr_e($state['periode']) ?>" required>
                        </div>
                        <div class="col-md-9">
                            <label for="file_xlsx" class="form-label fw-bold small text-uppercase text-secondary" style="letter-spacing:.5px;">File .xlsx</label>
                            <input type="file" name="file_xlsx" id="file_xlsx" class="form-control" accept=".xlsx" required <?= $state['unit_siap'] ? '' : 'disabled' ?>>
                        </div>
                    </div>
                    <div class="form-text mt-2">
                        File berisi 2 kolom: <strong>Idpel</strong> dan <strong><?= pr_e($cfg['nama_excel']) ?></strong> (<?= pr_e($cfg['format_excel']) ?>). Judul kolom di baris pertama, sheet pertama. Batas server: <?= pr_e(ini_get('upload_max_filesize')) ?>.
                    </div>
                </div>
                <div class="card-footer bg-white border-top py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="text-muted small"><i class="bi bi-arrow-repeat me-1"></i> Pelanggan + periode yang sudah ada akan diperbarui nilainya.</span>
                    <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm rounded-2" <?= $state['unit_siap'] ? '' : 'disabled' ?>><i class="bi bi-cloud-arrow-up me-1"></i> Unggah &amp; Simpan</button>
                </div>
            </form>
        </div>

        <!-- MANUAL -->
        <div class="tab-pane fade <?= $mode === 'manual' ? 'show active' : '' ?>" id="tabManual" role="tabpanel">
            <form method="POST" action="<?= pr_e(pr_url($cfg['action'])) ?>" class="form-proses">
                <input type="hidden" name="mode" value="manual">
                <input type="hidden" name="unit_up" class="unit-up-hidden" value="<?= pr_e($state['sel_up']) ?>">
                <div class="card-body px-4 py-4">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="periode_mn" class="form-label fw-bold small text-uppercase text-secondary" style="letter-spacing:.5px;">Periode (Tahun)</label>
                            <input type="number" name="periode" id="periode_mn" class="form-control" min="1901" max="2155" step="1" value="<?= pr_e($state['periode']) ?>" required>
                        </div>
                        <div class="col-md-5">
                            <label for="idpel_mn" class="form-label fw-bold small text-uppercase text-secondary" style="letter-spacing:.5px;">ID Pelanggan</label>
                            <input type="text" name="idpel" id="idpel_mn" class="form-control font-monospace" inputmode="numeric" pattern="\d{12}" maxlength="12" placeholder="12 digit, contoh 541200010035" value="<?= pr_e($state['idpel']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="nilai_mn" class="form-label fw-bold small text-uppercase text-secondary" style="letter-spacing:.5px;"><?= pr_e($cfg['label_nilai']) ?></label>
                            <select name="nilai" id="nilai_mn" class="form-select" required>
                                <option value="">— Pilih —</option>
                                <?php foreach ($cfg['opsi'] as $k => $label): ?>
                                    <option value="<?= pr_e((string) $k) ?>" <?= $state['nilai'] === (string) $k ? 'selected' : '' ?>><?= pr_e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white border-top py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="text-muted small"><i class="bi bi-person-badge me-1"></i> Wilayah: <strong><?= pr_e($state['scope_label']) ?></strong></span>
                    <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm rounded-2" <?= $state['unit_siap'] ? '' : 'disabled' ?>><i class="bi bi-check2 me-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (!empty($state['ditolak'])): ?>
<div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
        <span class="fw-bold text-primary fs-6">Baris Ditolak</span>
        <span class="text-muted small">Menampilkan <?= count($state['ditolak']) ?> dari <?= pr_n($h['ditolak'] ?? count($state['ditolak'])) ?> baris</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-secondary text-uppercase pr-thead"><tr><th class="ps-4" style="width:110px;">Baris Excel</th><th>Nilai Idpel</th><th class="pe-4">Alasan</th></tr></thead>
            <tbody>
                <?php foreach ($state['ditolak'] as [$no, $idp, $alasan]): ?>
                <tr>
                    <td class="ps-4 font-monospace"><?= (int) $no ?></td>
                    <td class="font-monospace small"><?= pr_e($idp ?: '(kosong)') ?></td>
                    <td class="pe-4"><?= pr_badge($alasan, 'danger', 'bi-x-circle') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    // Pilihan UP (hanya akun tingkat AP) disalin ke kedua form (Upload & Satu-satu)
    var selUp = document.getElementById('sel_up_tujuan');
    function sinkron() {
        document.querySelectorAll('.unit-up-hidden').forEach(function (h) { if (selUp) h.value = selUp.value; });
    }
    if (selUp) selUp.addEventListener('change', sinkron);
    sinkron();

    document.querySelectorAll('.form-proses').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            if (selUp && !selUp.value) {
                e.preventDefault();
                alert('Pilih Unit UP tujuan terlebih dahulu.');
                selUp.focus();
                return;
            }
            sinkron();
            var b = f.querySelector('button[type=submit]');
            setTimeout(function () { b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...'; }, 0);
        });
    });
})();
</script>