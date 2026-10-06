<?php
// templates/perencanaan/proses_risiko.php  -  Klasifikasi Level Risiko
// Variabel dari modules/perencanaan/proses_risiko.php

$url_daftar = function (array $ubah = []) use ($status_filter, $keyword) {
    return pr_url('proses_risiko', array_merge(['status_filter' => $status_filter === 'semua' ? '' : $status_filter, 'keyword' => $keyword], $ubah));
};

echo pr_header([
    'title'    => 'Klasifikasi Level Risiko',
    'subtitle' => 'Hitung kuadran, level kemungkinan, level dampak, dan Skala Prioritas 1-9 untuk setiap pelanggan.',
    'action'   => 'proses_risiko',
    'badge'    => 'Periode ' . $periode_filter,
    'buttons'  => [[
        'label' => 'Proses Semua Data', 'icon' => 'bi-lightning-charge-fill',
        'attrs' => 'type="submit" form="formRisiko" name="proses_semua" value="1" onclick="return confirm(\'Kalkulasi ulang SELURUH data Periode ' . pr_e($periode_filter) . ' sekaligus? Data tanpa Posisi SR / Level Kepentingan otomatis dilewati.\');"',
    ]],
]);

if (!empty($pesan_sukses)) echo pr_alert('success', $pesan_sukses);
if (!empty($pesan_error))  echo pr_alert('danger', $pesan_error);
?>

<!-- KARTU METRIK KPI -->
<div class="row g-3 mb-4">
    <?= pr_kpi('Pelanggan DIL', pr_n($total_dil), 'Populasi pelanggan terdaftar', 'bi-people', 'primary') ?>
    <?= pr_kpi('Terklasifikasi', pr_n($total_sudah) . ' <span class="fs-6 fw-semibold text-muted">/ ' . pr_n($total_dil) . '</span>',
               pr_pct($total_sudah, $total_dil) . '% memiliki Skala Prioritas', 'bi-shield-check', 'success', true) ?>
    <?= pr_kpi('Belum Ada Posisi SR', pr_n($total_belum_sr), 'Isi lewat <a href="' . pr_e(pr_url('input_survey')) . '" class="text-decoration-none">Survey SR</a>', 'bi-ui-checks', 'secondary') ?>
    <?= pr_kpi('Belum Ada Kepentingan', pr_n($total_belum_kepentingan), 'Isi lewat <a href="' . pr_e(pr_url('input_kepentingan')) . '" class="text-decoration-none">Tingkat Kepentingan</a>', 'bi-flag', 'warning') ?>
</div>

<!-- ATURAN KLASIFIKASI (dilipat agar tabel tetap di atas lipatan layar) -->
<div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
    <button class="btn text-start d-flex justify-content-between align-items-center py-3 px-4 border-0 collapsed" type="button"
            data-bs-toggle="collapse" data-bs-target="#collapseAturan" aria-expanded="false" aria-controls="collapseAturan">
        <span class="fw-bold text-primary fs-6"><i class="bi bi-info-circle me-2"></i>Aturan Klasifikasi Risiko</span>
        <span class="text-muted small">Tampilkan / sembunyikan <i class="bi bi-chevron-down ms-1"></i></span>
    </button>
    <div class="collapse" id="collapseAturan">
        <div class="card-body border-top px-4 py-4">
            <div class="row g-3">
                <div class="col-md-6 col-xl-3">
                    <div class="p-3 rounded-3 border h-100 bg-warning bg-opacity-10 border-warning-subtle">
                        <h6 class="fw-bold text-warning-emphasis mb-2"><i class="bi bi-exclamation-diamond me-1"></i> Syarat Wajib</h6>
                        <p class="small text-muted mb-2">Data diproses hanya jika <strong>keduanya</strong> sudah terisi:</p>
                        <ul class="small mb-0 ps-3">
                            <li><strong>Posisi SR</strong> dari Survey SR</li>
                            <li><strong>Level Kepentingan</strong> (Rendah / Moderat / Tinggi)</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="p-3 rounded-3 border h-100">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-1-circle me-1"></i> Level Kemungkinan</h6>
                        <p class="small text-muted mb-2">Matriks 3x3: <strong>Kepentingan</strong> x <strong>Keterlambatan</strong> menghasilkan kuadran 1-9.</p>
                        <ul class="small mb-0 ps-3">
                            <li>Kuadran 1-2: Sangat jarang terjadi</li>
                            <li>Kuadran 3-5: Bisa terjadi</li>
                            <li>Kuadran 6-9: Sangat mungkin terjadi</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="p-3 rounded-3 border h-100">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-2-circle me-1"></i> Level Dampak</h6>
                        <p class="small text-muted mb-2">Total tagihan Periode <?= pr_e($periode_filter) ?> dibandingkan seluruh populasi.</p>
                        <ul class="small mb-0 ps-3">
                            <li>&le; Persentil 10: Sangat rendah</li>
                            <li>P10 s/d P70: Moderat</li>
                            <li>&gt; Persentil 70: Sangat tinggi</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="p-3 rounded-3 border h-100">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-3-circle me-1"></i> Skala Prioritas</h6>
                        <p class="small text-muted mb-2">Kemungkinan x Dampak menghasilkan skala 1-9.</p>
                        <?= pr_badge('9 · TERTINGGI', 'danger') ?> <?= pr_badge('1 · TERENDAH', 'success') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?= pr_pills('STATUS:', [
    ['Semua',                 $url_daftar(['status_filter' => '', 'hal' => '']),                   $status_filter === 'semua',             null],
    ['Belum Ada Posisi SR',   $url_daftar(['status_filter' => 'belum_sr', 'hal' => '']),           $status_filter === 'belum_sr',          $total_belum_sr],
    ['Belum Ada Kepentingan', $url_daftar(['status_filter' => 'belum_kepentingan', 'hal' => '']),  $status_filter === 'belum_kepentingan', $total_belum_kepentingan],
    ['Belum Diproses',        $url_daftar(['status_filter' => 'belum', 'hal' => '']),              $status_filter === 'belum',             $total_belum],
    ['Sudah Diproses',        $url_daftar(['status_filter' => 'sudah', 'hal' => '']),              $status_filter === 'sudah',             $total_sudah],
]) ?>

<!-- TABEL KLASIFIKASI -->
<div class="card border-0 shadow-sm rounded-4 bg-white">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="fw-bold text-primary fs-6">Daftar Pelanggan &amp; Status Klasifikasi</span>
            <span class="badge bg-light text-secondary border font-monospace py-1 px-2">Tabel: kategorisasi_risiko</span>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <form method="GET" action="" class="input-group input-group-sm" style="max-width:260px;">
                <?= pr_hidden('proses_risiko', ['status_filter' => $status_filter === 'semua' ? '' : $status_filter]) ?>
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="keyword" class="form-control bg-light border-start-0" value="<?= pr_e($keyword) ?>" placeholder="Cari IDPel / nama pelanggan...">
            </form>
            <button type="submit" form="formRisiko" name="proses_risiko" value="1" class="btn btn-outline-primary btn-sm px-3"
                    onclick="return confirm('Proses hanya data yang dicentang? Data tanpa Posisi SR / Level Kepentingan otomatis dilewati.');">
                <i class="bi bi-check2-square me-1"></i> Proses Terpilih
            </button>
        </div>
    </div>

    <form method="POST" action="<?= pr_e(pr_url('proses_risiko')) ?>" id="formRisiko">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary text-uppercase pr-thead">
                        <tr>
                            <th class="ps-4" style="width:40px;"><input type="checkbox" class="form-check-input" id="checkAll" aria-label="Pilih semua di halaman ini"></th>
                            <th>ID Pelanggan</th>
                            <th>Nama Pelanggan</th>
                            <th>Posisi SR</th>
                            <th>Kepentingan</th>
                            <th>Keterlambatan</th>
                            <th>Dampak</th>
                            <th>Skala Prioritas</th>
                            <th class="text-end pe-4" style="width:90px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($list_preview)): foreach ($list_preview as $row):
                        $belum_diproses  = empty($row['SkalaPrioritas']) || (int) $row['SkalaPrioritas'] === 0;
                        $sr_kosong       = $row['PosisiSR'] === null || trim((string) $row['PosisiSR']) === '';
                        $kp              = strtoupper(trim((string) ($row['LevelKepentingan'] ?? '')));
                        $kp_kosong       = !in_array($kp, ['RENDAH', 'MODERAT', 'TINGGI'], true);
                        $tidak_lengkap   = $sr_kosong || $kp_kosong;
                    ?>
                        <tr>
                            <td class="ps-4"><input type="checkbox" name="idpel_list[]" value="<?= pr_e($row['Idpel']) ?>" class="form-check-input check-item"></td>
                            <td class="fw-bold text-dark font-monospace"><?= pr_e($row['Idpel']) ?></td>
                            <td class="fw-semibold text-dark text-uppercase small"><?= pr_e($row['NamaPelanggan'] ?? '— (tidak ada di DIL)') ?></td>
                            <td class="text-nowrap">
                                <button type="button" class="btn p-0 border-0 bg-transparent btn-edit-sr" title="Klik untuk ubah Posisi SR"
                                        data-idpel="<?= pr_e($row['Idpel']) ?>" data-sr="<?= $sr_kosong ? '0' : pr_e($row['PosisiSR']) ?>">
                                    <?= pr_badge_sr($row['PosisiSR']) ?> <i class="bi bi-pencil-square text-muted ms-1 small"></i>
                                </button>
                            </td>
                            <td><?= $kp_kosong ? pr_badge('BELUM DIISI', 'warning', 'bi-exclamation-triangle') : pr_badge_level($kp) ?></td>
                            <td><?= pr_badge_level($row['LevelKeterlambatan']) ?></td>
                            <td><?= pr_badge_level($row['LevelDampak']) ?></td>
                            <td>
                                <?php if (!$belum_diproses): ?>
                                    <?= pr_badge_prioritas($row['SkalaPrioritas']) ?>
                                <?php elseif ($tidak_lengkap): ?>
                                    <?= pr_badge('DATA BELUM LENGKAP', 'secondary', 'bi-slash-circle') ?>
                                <?php else: ?>
                                    <?= pr_badge('BELUM DIPROSES', 'warning', 'bi-hourglass-split') ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <?php if (!$belum_diproses): ?>
                                    <a href="<?= pr_e($url_daftar(['action_crud' => 'hapus', 'idpel' => $row['Idpel'], 'hal' => $hal])) ?>"
                                       class="btn btn-outline-danger btn-sm py-1 px-2" title="Reset hasil klasifikasi"
                                       onclick="return confirm(<?= pr_e(json_encode('Reset hasil klasifikasi Periode ' . $periode_filter . ' untuk IDPel ' . $row['Idpel'] . '?')) ?>);">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; else: echo pr_empty_row(9, 'Tidak ada pelanggan yang cocok dengan filter ini.'); endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <?= pr_footer_tabel($hal, $limit, $total_rows, fn($h) => $url_daftar(['hal' => $h])) ?>
</div>

<!-- MODAL UBAH POSISI SR -->
<div class="modal fade" id="modalEditSR" tabindex="-1" aria-labelledby="judulModalSR" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST" action="<?= pr_e(pr_url('proses_risiko')) ?>">
                <input type="hidden" name="action_type" value="update_sr">
                <input type="hidden" name="idpel_target" id="modal_idpel_target">
                <div class="modal-header border-bottom px-4 py-3">
                    <h5 class="modal-title fw-bold" id="judulModalSR"><i class="bi bi-pencil-square text-primary me-2"></i>Ubah Posisi SR</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body px-4 py-4">
                    <p class="mb-2">ID Pelanggan: <strong class="font-monospace" id="display_idpel"></strong></p>
                    <p class="small text-muted">
                        Posisi SR menandai apakah lokasi sambungan pelanggan ini <strong>saling tergantung / paralel</strong> dengan
                        pelanggan lain. Data ini <strong>tidak menentukan Level Kepentingan</strong>.
                    </p>
                    <label class="form-label fw-bold small text-uppercase text-secondary" for="modal_posisi_sr" style="letter-spacing:.5px;">Posisi SR</label>
                    <select name="posisi_sr" id="modal_posisi_sr" class="form-select">
                        <option value="0">0 — Tidak tergantung (mandiri)</option>
                        <option value="1">1 — Tergantung dengan pelanggan lain</option>
                    </select>
                </div>
                <div class="modal-footer border-top px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var semua = document.getElementById('checkAll');
    if (semua) {
        semua.addEventListener('change', function () {
            document.querySelectorAll('.check-item').forEach(function (cb) { cb.checked = semua.checked; });
        });
    }
    var modalEl = document.getElementById('modalEditSR');
    document.querySelectorAll('.btn-edit-sr').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('modal_idpel_target').value = btn.dataset.idpel;
            document.getElementById('display_idpel').textContent = btn.dataset.idpel;
            document.getElementById('modal_posisi_sr').value = btn.dataset.sr;
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    });
});
</script>
