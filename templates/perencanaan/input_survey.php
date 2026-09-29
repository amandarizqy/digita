<!-- templates/perencanaan/input_survey.php -->

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="bi bi-ui-checks"></i> Input Survey SR</h4>
    <a href="?module=perencanaan" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali ke Dashboard</a>
</div>

<!-- Notifikasi -->
<?php if (!empty($pesan_sukses)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $pesan_sukses ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($pesan_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $pesan_error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- CARD RINGKASAN JUMLAH -->
<div class="row g-3 mb-4">
    <!-- Card Sudah Memiliki Info SR -->
    <div class="col-md-6">
        <div class="card shadow-sm border-0 border-start border-success border-4 h-100" 
             data-bs-toggle="modal" data-bs-target="#modalSudahSR" style="cursor: pointer;">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted mb-1">Sudah Memiliki Info Survey SR</h6>
                    <h2 class="fw-bold text-success mb-0"><?= number_format($total_sudah) ?></h2>
                    <small class="text-primary"><i class="bi bi-eye"></i> Klik untuk lihat 10 sampel data</small>
                </div>
                <div class="bg-success bg-opacity-10 p-3 rounded-circle text-success">
                    <i class="bi bi-check-circle-fill fs-2"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card Belum Memiliki Info SR -->
    <div class="col-md-6">
        <div class="card shadow-sm border-0 border-start border-warning border-4 h-100" 
             data-bs-toggle="modal" data-bs-target="#modalBelumSR" style="cursor: pointer;">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted mb-1">Belum Memiliki Info Survey SR</h6>
                    <h2 class="fw-bold text-warning mb-0"><?= number_format($total_belum) ?></h2>
                    <small class="text-primary"><i class="bi bi-eye"></i> Klik untuk lihat 10 sampel data</small>
                </div>
                <div class="bg-warning bg-opacity-10 p-3 rounded-circle text-warning">
                    <i class="bi bi-exclamation-circle-fill fs-2"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FORM INPUT SURVEY SR -->
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm border-primary border-top border-3">
            <div class="card-header bg-white fw-bold py-3">
                <i class="bi bi-pencil-square me-1"></i> Form Input Survey Sambungan Rumah (SR)
            </div>
            <div class="card-body p-4">
                <form action="?module=perencanaan&action=input_survey" method="POST">
                    
                    <div class="mb-4">
                        <label class="form-label fw-semibold">ID Pelanggan (Idpel)</label>
                        <input type="text" name="Idpel" class="form-control form-control-lg" maxlength="12" required placeholder="Masukkan Idpel pelanggan...">
                        <div class="form-text">Pastikan Idpel terdaftar di sistem DIL.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Status Sambungan SR</label>
                        <p class="small text-muted mb-2">Apakah pelanggan ini terhubung dengan pelanggan lain?</p>
                        
                        <div class="card p-3 mb-2 border">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="PosisiSR" id="sr0" value="0" checked>
                                <label class="form-check-label ms-2" for="sr0">
                                    <strong class="text-success">0 — Tidak Terhubung (Mandiri)</strong>
                                    <div class="small text-muted">Pelanggan tidak memiliki sambungan paralel/deret dengan rumah lain.</div>
                                </label>
                            </div>
                        </div>

                        <div class="card p-3 border">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="PosisiSR" id="sr1" value="1">
                                <label class="form-check-label ms-2" for="sr1">
                                    <strong class="text-warning">1 — Terhubung dengan Pelanggan Lain</strong>
                                    <div class="small text-muted">Pelanggan terhubung atau berbagi jalur SR dengan pelanggan lain.</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="simpan_data" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-save me-1"></i> Simpan Hasil Survey
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL 1: SUDAH SURVEY (10 BARIS) -->
<div class="modal fade" id="modalSudahSR" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i> Sampel Pelanggan Sudah Survey SR (10 Pertama)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 50px;">No</th>
                                <th>Idpel</th>
                                <th>Nama Pelanggan</th>
                                <th class="text-center">Posisi SR</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($list_sudah)): ?>
                                <?php $no = 1; foreach ($list_sudah as $row): ?>
                                    <tr>
                                        <td class="text-center"><?= $no++ ?></td>
                                        <td><code><?= htmlspecialchars($row['Idpel']) ?></code></td>
                                        <td><strong><?= htmlspecialchars($row['NamaPelanggan']) ?></strong></td>
                                        <td class="text-center">
                                            <span class="badge <?= $row['PosisiSR'] == '1' ? 'bg-warning text-dark' : 'bg-success' ?>">
                                                <?= $row['PosisiSR'] == '1' ? '1 (Terhubung)' : '0 (Mandiri)' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">Belum ada data pelanggan yang di-survey.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <small class="text-muted">Menampilkan 10 data teratas dari total <?= number_format($total_sudah) ?> data.</small>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL 2: BELUM SURVEY (10 BARIS) -->
<div class="modal fade" id="modalBelumSR" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-exclamation-circle me-2"></i> Sampel Pelanggan Belum Survey SR (10 Pertama)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 50px;">No</th>
                                <th>Idpel</th>
                                <th>Nama Pelanggan</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($list_belum)): ?>
                                <?php $no = 1; foreach ($list_belum as $row): ?>
                                    <tr>
                                        <td class="text-center"><?= $no++ ?></td>
                                        <td><code><?= htmlspecialchars($row['Idpel']) ?></code></td>
                                        <td><strong><?= htmlspecialchars($row['NamaPelanggan']) ?></strong></td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary">Belum Di-survey</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">Semua pelanggan DIL sudah memiliki data survey SR.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <small class="text-muted">Menampilkan 10 data teratas dari total <?= number_format($total_belum) ?> data.</small>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>