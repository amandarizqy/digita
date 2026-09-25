<!-- templates/perencanaan/proses_risiko.php -->

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>
        <i class="bi bi-cpu-fill text-primary me-2"></i> Modul Klasifikasi Risiko Pelanggan
        <span class="badge bg-dark ms-2 align-middle"><i class="bi bi-calendar3 me-1"></i>Periode <?= htmlspecialchars($periode_filter) ?></span>
    </h4>
    <a href="?module=perencanaan" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali ke Dashboard</a>
</div>

<!-- Alert Notifikasi -->
<?php if (!empty($pesan_sukses)): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= $pesan_sukses ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($pesan_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $pesan_error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- KARTU PENJELASAN PROSES & ATURAN RISIKO -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-info-circle-fill me-2"></i> PENJELASAN ATURAN PROSES RISIKO</h6>
        <button class="btn btn-sm btn-light text-primary fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePenjelasan">
            <i class="bi bi-chevron-down me-1"></i> Tampilkan / Sembunyikan
        </button>
    </div>
    <div class="collapse show" id="collapsePenjelasan">
        <div class="card-body bg-light">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="p-3 bg-white rounded border h-100 border-warning border-2">
                        <h6 class="fw-bold text-warning-emphasis mb-2"><i class="bi bi-exclamation-diamond-fill me-1"></i> Syarat Wajib</h6>
                        <small class="text-muted d-block mb-2">Data hanya diproses jika <strong>Posisi SR</strong> sudah diisi (menentukan Level Kepentingan).</small>
                        <ul class="small mb-0 ps-3">
                            <li>Belum diisi SR → <strong>dilewati</strong>, tidak dihitung</li>
                            <li>Sudah diisi SR → boleh dihitung</li>
                        </ul>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="p-3 bg-white rounded border h-100">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-1-circle me-1"></i> Level Kemungkinan</h6>
                        <small class="text-muted d-block mb-2">Ditentukan dari Matriks 3x3 (Level Kepentingan x Level Keterlambatan).</small>
                        <ul class="small mb-0 ps-3">
                            <li>Kuadran 1-2: <strong>SANGAT JARANG TERJADI</strong></li>
                            <li>Kuadran 3-5: <strong>BISA TERJADI</strong></li>
                            <li>Kuadran 6-9: <strong>SANGAT MUNGKIN TERJADI</strong></li>
                        </ul>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="p-3 bg-white rounded border h-100">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-2-circle me-1"></i> Level Dampak</h6>
                        <small class="text-muted d-block mb-2">Pemeringkatan tagihan Periode <?= htmlspecialchars($periode_filter) ?> terhadap <strong>seluruh populasi data</strong>.</small>
                        <ul class="small mb-0 ps-3">
                            <li>≤ Persentil 10% (P10): <strong>SANGAT RENDAH</strong></li>
                            <li>P10 s/d Persentil 70% (P70): <strong>MODERAT</strong></li>
                            <li>> Persentil 70% (P70): <strong>SANGAT TINGGI</strong></li>
                        </ul>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="p-3 bg-white rounded border h-100">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-3-circle me-1"></i> Output & Sinkronisasi</h6>
                        <small class="text-muted d-block mb-2">Output berupa <strong>Skala Prioritas 1-9</strong>.</small>
                        <span class="badge bg-danger">Prioritas 9: Tertinggi</span>
                        <span class="badge bg-success ms-1">Prioritas 1: Terendah</span>
                        <small class="d-block mt-2 text-muted">* Otomatis tersimpan ke <code>kategorisasi_risiko</code> (Periode <?= htmlspecialchars($periode_filter) ?>) & <code>dil.IndexPrioritas</code> menggunakan metode UPSERT.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- KARTU STATISTIK PELANGGAN -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-primary text-white h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-white-50 text-uppercase fw-semibold mb-1">Total Pelanggan DIL</h6>
                    <h3 class="fw-bold mb-0"><?= number_format($total_dil) ?></h3>
                </div>
                <i class="bi bi-people fs-1 opacity-50"></i>
            </div>
        </div>
    </div>

    <!-- KARTU BARU: DATA BELUM PUNYA POSISI SR (tidak akan ikut diproses) -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-secondary text-white h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-white-50 text-uppercase fw-bold mb-1"><i class="bi bi-slash-circle me-1"></i> Belum Ada Posisi SR</h6>
                    <h3 class="fw-bold mb-0"><?= number_format($total_belum_sr) ?></h3>
                    <small class="text-white-50">Wajib diisi dulu agar bisa dikalkulasi</small>
                </div>
                <i class="bi bi-person-fill-exclamation fs-1 opacity-50"></i>
            </div>
        </div>
    </div>

    <!-- KARTU KHUSUS: PELANGGAN BELUM DIPROSES -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-warning text-dark h-100 border-start border-4 border-danger">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-dark-50 text-uppercase fw-bold mb-1"><i class="bi bi-exclamation-circle me-1"></i> Belum Diproses</h6>
                    <h3 class="fw-bold mb-0 text-danger"><?= number_format($total_belum) ?></h3>
                    <small class="text-muted">Pelanggan butuh kalkulasi</small>
                </div>
                <i class="bi bi-hourglass-split fs-1 text-danger opacity-75"></i>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-success text-white h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-white-50 text-uppercase fw-semibold mb-1">Sudah Diproses</h6>
                    <h3 class="fw-bold mb-0"><?= number_format($total_sudah) ?></h3>
                    <small class="text-white-50">Memiliki Skala Prioritas</small>
                </div>
                <i class="bi bi-shield-check fs-1 opacity-50"></i>
            </div>
        </div>
    </div>
</div>

<!-- SEARCH & FILTER STATUS -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-2 align-items-center">
            <input type="hidden" name="module" value="perencanaan">
            <input type="hidden" name="action" value="proses_risiko">

            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="keyword" class="form-control" value="<?= htmlspecialchars($keyword) ?>" placeholder="Cari IDPel / Nama Pelanggan...">
                </div>
            </div>

            <div class="col-md-4">
                <select name="status_filter" class="form-select" onchange="this.form.submit()">
                    <option value="semua" <?= $status_filter === 'semua' ? 'selected' : '' ?>>-- Semua Status Pelanggan --</option>
                    <option value="belum_sr" <?= $status_filter === 'belum_sr' ? 'selected' : '' ?>>🚫 Belum Ada Posisi SR (<?= $total_belum_sr ?>)</option>
                    <option value="belum" <?= $status_filter === 'belum' ? 'selected' : '' ?>>⚠️ Pelanggan Belum Diproses (<?= $total_belum ?>)</option>
                    <option value="sudah" <?= $status_filter === 'sudah' ? 'selected' : '' ?>>✅ Pelanggan Sudah Diproses (<?= $total_sudah ?>)</option>
                </select>
            </div>

            <div class="col-md-2 d-grid">
                <a href="?module=perencanaan&action=proses_risiko" class="btn btn-outline-secondary">Reset Filter</a>
            </div>
        </form>
    </div>
</div>

<!-- FORM UTAMA & TABLE PREVIEW DATA -->
<form method="POST" action="?module=perencanaan&action=proses_risiko" id="formRisiko">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center py-3 gap-2">
            <h6 class="fw-bold mb-0 text-secondary">
                <i class="bi bi-table me-1"></i> Data Pelanggan & Status Risiko
            </h6>
            
            <div class="d-flex gap-2">
                <!-- Tombol Proses Terpilih -->
                <button type="submit" name="proses_risiko" class="btn btn-outline-primary btn-sm px-3" onclick="return confirm('Proses hanya data yang dicentang? (Data tanpa Posisi SR otomatis dilewati)');">
                    <i class="bi bi-check2-square me-1"></i> Proses Data Terpilih
                </button>

                <!-- Tombol PROSES SEMUA (Instan Bulk SQL) -->
                <button type="submit" name="proses_semua" class="btn btn-danger btn-sm px-3 fw-bold" onclick="return confirm('PERHATIAN: Kalkulasi ulang SELURUH data Periode <?= htmlspecialchars($periode_filter) ?> secara sekaligus? (Data tanpa Posisi SR otomatis dilewati)');">
                    <i class="bi bi-lightning-charge-fill me-1"></i> Proses Semua Data
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 40px;">
                                <input type="checkbox" class="form-check-input" id="checkAll">
                            </th>
                            <th>ID Pelanggan (IdPel)</th>
                            <th>Nama Pelanggan</th>
                            <th class="text-center">Posisi SR</th>
                            <th class="text-center">Keterlambatan</th>
                            <th class="text-center">Dampak</th>
                            <th class="text-center">Skala Prioritas</th>
                            <th class="text-center" style="width: 120px;">Aksi CRUD</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($list_preview)): ?>
                            <?php foreach ($list_preview as $row): ?>
                                <?php
                                    $is_belum   = empty($row['SkalaPrioritas']) || $row['SkalaPrioritas'] == 0;
                                    $sr_kosong  = $row['PosisiSR'] === null || trim((string) $row['PosisiSR']) === '';
                                ?>
                                <tr class="<?= $sr_kosong ? 'table-secondary' : ($is_belum ? 'table-warning' : '') ?>">
                                    <td class="text-center">
                                        <input type="checkbox" name="idpel_list[]" value="<?= htmlspecialchars($row['Idpel']) ?>" class="form-check-input check-item">
                                    </td>
                                    <td><code><?= htmlspecialchars($row['Idpel']) ?></code></td>
                                    <td><strong><?= htmlspecialchars($row['NamaPelanggan'] ?? '— (Tidak ada di DIL)') ?></strong></td>
                                    
                                    <!-- CRUD READ/UPDATE: EDIT POSISI SR -->
                                    <td class="text-center">
                                        <?php if ($sr_kosong): ?>
                                            <button type="button" class="btn btn-sm btn-warning text-dark fw-bold"
                                                    onclick="editSR('<?= htmlspecialchars($row['Idpel']) ?>', '0')" title="Klik untuk Isi Posisi SR (wajib sebelum diproses)">
                                                <i class="bi bi-exclamation-triangle-fill me-1"></i> Belum Diisi
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm <?= $row['PosisiSR'] == '1' ? 'btn-success' : 'btn-outline-secondary' ?>" 
                                                    onclick="editSR('<?= htmlspecialchars($row['Idpel']) ?>', '<?= $row['PosisiSR'] ?>')" title="Klik untuk Ubah SR">
                                                <?= $row['PosisiSR'] == '1' ? 'SR (Saluran Resmi)' : 'Non-SR' ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border">
                                            <?= htmlspecialchars($row['LevelKeterlambatan'] ?? '-') ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border">
                                            <?= htmlspecialchars($row['LevelDampak'] ?? '-') ?>
                                        </span>
                                    </td>
                                    
                                    <!-- BADGE KARTU STATUS PELANGGAN -->
                                    <td class="text-center">
                                        <?php if (!$is_belum): ?>
                                            <span class="badge bg-danger fs-6">
                                                Prioritas <?= $row['SkalaPrioritas'] ?>
                                            </span>
                                        <?php elseif ($sr_kosong): ?>
                                            <span class="badge bg-secondary fw-bold">
                                                🚫 Tidak Bisa Diproses (SR Kosong)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark border border-warning fw-bold">
                                                ⚠️ Belum Diproses
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- CRUD DELETE / RESET -->
                                    <td class="text-center">
                                        <?php if (!$is_belum): ?>
                                            <a href="?module=perencanaan&action=proses_risiko&action_crud=hapus&idpel=<?= urlencode($row['Idpel']) ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Reset status risiko Periode <?= htmlspecialchars($periode_filter) ?> untuk IDPel <?= $row['Idpel'] ?>?');" title="Reset/Hapus Risiko">
                                                <i class="bi bi-trash"></i> Reset
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    Data tidak ditemukan.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</form>

<!-- MODAL CRUD UPDATE POSISI SR -->
<div class="modal fade" id="modalEditSR" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="?module=perencanaan&action=proses_risiko">
                <input type="hidden" name="action_type" value="update_sr">
                <input type="hidden" name="idpel_target" id="modal_idpel_target">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Update Posisi SR Pelanggan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Ubah status Saluran Resmi (SR) untuk ID Pelanggan: <strong id="display_idpel"></strong></p>
                    <p class="small text-muted">Posisi SR wajib diisi (Non-SR maupun SR) sebelum data ini bisa ikut dikalkulasi risiko.</p>
                    <div class="form-group mb-3">
                        <label class="form-label fw-bold">Posisi SR</label>
                        <select name="posisi_sr" id="modal_posisi_sr" class="form-select">
                            <option value="0">Non-SR (Kepentingan Rendah)</option>
                            <option value="1">Saluran Resmi / SR (Kepentingan Tinggi)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SCRIPT CHECK ALL & MODAL TRIGGER -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkAll = document.getElementById('checkAll');
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.check-item');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
    }
});

function editSR(idpel, currentSR) {
    document.getElementById('modal_idpel_target').value = idpel;
    document.getElementById('display_idpel').innerText = idpel;
    document.getElementById('modal_posisi_sr').value = currentSR;
    
    var modalSR = new bootstrap.Modal(document.getElementById('modalEditSR'));
    modalSR.show();
}
</script>