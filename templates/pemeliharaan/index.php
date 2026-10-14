<div class="container-fluid px-4 py-3">
    <!-- Header Title & Action Button -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">Modul Pemeliharaan & Mutasi S41</h3>
            <p class="text-muted small mb-0">Kelola eksekusi perintah kerja, perbaikan perangkat, serta mutasi alat meter/modem S41.</p>
        </div>
        <?php if ($sub === 'eksekusi_order'): ?>
            <button class="btn btn-primary fw-medium px-3 rounded-3" data-bs-toggle="modal" data-bs-target="#modalTambahOrder">
                <i class="bi bi-plus-lg me-1"></i> Buat Order Baru
            </button>
        <?php elseif ($sub === 'eksekusi_mutasi'): ?>
            <button class="btn btn-success fw-medium px-3 rounded-3 text-white" data-bs-toggle="modal" data-bs-target="#modalTambahMutasi">
                <i class="bi bi-arrow-repeat me-1"></i> Catat Mutasi Baru
            </button>
        <?php endif; ?>
    </div>

    <?php if (!empty($pesan_sukses)): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= $pesan_sukses ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($pesan_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $pesan_error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Sub Navigasi Pills -->
    <div class="card border-0 shadow-sm rounded-4 p-2 mb-3 bg-white">
        <ul class="nav nav-pills nav-fill gap-2">
            <li class="nav-item">
                <a class="nav-link rounded-3 fw-semibold <?= $sub === 'eksekusi_order' ? 'active bg-primary' : 'text-secondary' ?>" href="/index.php?page=pemeliharaan&sub=eksekusi_order">
                    <i class="bi bi-tools me-1"></i> Eksekusi: Order Pemeliharaan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-3 fw-semibold <?= $sub === 'eksekusi_mutasi' ? 'active bg-primary' : 'text-secondary' ?>" href="/index.php?page=pemeliharaan&sub=eksekusi_mutasi">
                    <i class="bi bi-arrow-left-right me-1"></i> Eksekusi: Mutasi Pemeliharaan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-3 fw-semibold <?= $sub === 'monitoring_order' ? 'active bg-primary' : 'text-secondary' ?>" href="/index.php?page=pemeliharaan&sub=monitoring_order">
                    <i class="bi bi-display me-1"></i> Monitoring: Order
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-3 fw-semibold <?= $sub === 'monitoring_mutasi' ? 'active bg-primary' : 'text-secondary' ?>" href="/index.php?page=pemeliharaan&sub=monitoring_mutasi">
                    <i class="bi bi-file-earmark-bar-graph me-1"></i> Monitoring: Mutasi
                </a>
            </li>
        </ul>
    </div>

    <!-- Statistik Kartu Ringkasan -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-uppercase text-muted fw-bold small mb-1">TOTAL ORDER PEMELIHARAAN</div>
                        <div class="h2 fw-bold mb-0 text-dark"><?= $total_order ?> <span class="fs-6 fw-normal text-muted">Pekerjaan</span></div>
                        <div class="small text-muted mt-1">Registrasi tiket perbaikan & pemeliharaan</div>
                    </div>
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-4 fs-2">
                        <i class="bi bi-wrench-adjustable"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-uppercase text-muted fw-bold small mb-1">STATUS PENGERJAAN</div>
                        <div class="h2 fw-bold mb-0 text-dark"><?= $total_selesai ?> <span class="fs-6 text-muted">/ <?= $total_order ?> Selesai</span></div>
                        <div class="small text-muted mt-1"><?= $total_proses ?> Pekerjaan sedang dalam proses perbaikan</div>
                    </div>
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-4 fs-2">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAMPILAN SUB 1: EKSEKUSI ORDER -->
    <?php if ($sub === 'eksekusi_order'): ?>
        <div class="card border-0 shadow-sm rounded-4 bg-white">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-primary mb-0">Daftar Order Pemeliharaan</h5>
                    <div class="w-25">
                        <input type="text" class="form-control form-control-sm rounded-3" placeholder="Cari No Order / Pelanggan...">
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted text-uppercase small">
                            <tr>
                                <th>#</th>
                                <th>NO ORDER</th>
                                <th>TGL ORDER</th>
                                <th>ID PELANGGAN</th>
                                <th>NAMA PELANGGAN</th>
                                <th>JENIS</th>
                                <th>PETUGAS</th>
                                <th>STATUS</th>
                            </tr>
                        </thead>
                        <tbody class="fw-medium">
                            <?php if (empty($data_order)): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">Belum ada data order pemeliharaan.</td></tr>
                            <?php else: ?>
                                <?php foreach ($data_order as $idx => $row): ?>
                                    <tr>
                                        <td><?= $idx + 1 ?></td>
                                        <td class="fw-bold text-primary"><?= htmlspecialchars($row['NoOrder']) ?></td>
                                        <td><?= htmlspecialchars($row['TglOrder']) ?></td>
                                        <td><?= htmlspecialchars($row['IdPel']) ?></td>
                                        <td><?= htmlspecialchars($row['NamaPelanggan']) ?></td>
                                        <td><span class="badge bg-info text-dark px-2 py-1"><?= $row['JenisPemeliharaan'] ?></span></td>
                                        <td><?= htmlspecialchars($row['NamaPetugas']) ?></td>
                                        <td>
                                            <?php if ($row['StatusEksekusi'] === 'SELESAI'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1"><i class="bi bi-check-circle me-1"></i> SELESAI</span>
                                            <?php elseif ($row['StatusEksekusi'] === 'PROSES'): ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-1"><i class="bi bi-clock me-1"></i> PROSES</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 py-1">PENDING</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <!-- TAMPILAN SUB 2: EKSEKUSI MUTASI -->
    <?php elseif ($sub === 'eksekusi_mutasi'): ?>
        <div class="card border-0 shadow-sm rounded-4 bg-white">
            <div class="card-body p-4">
                <h5 class="fw-bold text-primary mb-3">Riwayat Mutasi Perangkat S41</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted text-uppercase small">
                            <tr>
                                <th>#</th>
                                <th>NO MUTASI</th>
                                <th>NO ORDER</th>
                                <th>TGL MUTASI</th>
                                <th>REF LAMA -> BARU</th>
                                <th>ALASAN MUTASI</th>
                                <th>PETUGAS</th>
                            </tr>
                        </thead>
                        <tbody class="fw-medium">
                            <?php if (empty($data_mutasi)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada riwayat mutasi perangkat.</td></tr>
                            <?php else: ?>
                                <?php foreach ($data_mutasi as $idx => $m): ?>
                                    <tr>
                                        <td><?= $idx + 1 ?></td>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($m['NoMutasi']) ?></td>
                                        <td class="text-primary"><?= htmlspecialchars($m['NoOrder']) ?></td>
                                        <td><?= htmlspecialchars($m['TglMutasi']) ?></td>
                                        <td>
                                            <span class="badge bg-light text-danger border px-2 py-1"><?= htmlspecialchars($m['NoRefLama']) ?></span>
                                            <i class="bi bi-arrow-right mx-1 text-muted"></i>
                                            <span class="badge bg-light text-success border px-2 py-1"><?= htmlspecialchars($m['NoRefBaru']) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($m['AlasanMutasi']) ?></td>
                                        <td><?= htmlspecialchars($m['PetugasEksekusi']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <!-- TAMPILAN SUB 3 & 4: MONITORING -->
    <?php else: ?>
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-primary mb-0">Rekapitulasi Laporan Monitoring Pemeliharaan</h5>
                <button class="btn btn-outline-secondary btn-sm rounded-3" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak PDF / Laporan</button>
            </div>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-dark small">
                        <tr>
                            <th>NO ORDER</th>
                            <th>TANGGAL</th>
                            <th>PELANGGAN</th>
                            <th>JENIS</th>
                            <th>PETUGAS LAPANGAN</th>
                            <th>STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data_order as $row): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($row['NoOrder']) ?></td>
                                <td><?= htmlspecialchars($row['TglOrder']) ?></td>
                                <td><?= htmlspecialchars($row['NamaPelanggan']) ?> (<?= htmlspecialchars($row['IdPel']) ?>)</td>
                                <td><?= htmlspecialchars($row['JenisPemeliharaan']) ?></td>
                                <td><?= htmlspecialchars($row['NamaPetugas']) ?></td>
                                <td><span class="badge bg-success"><?= htmlspecialchars($row['StatusEksekusi']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Form Tambah Order Pemeliharaan -->
<div class="modal fade" id="modalTambahOrder" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-1"></i> Form Order Pemeliharaan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="tambah_order">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Tanggal Order</label>
                        <input type="date" name="tgl_order" class="form-control rounded-3" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">ID Pelanggan</label>
                        <input type="text" name="id_pel" class="form-control rounded-3" placeholder="560000000001" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Pelanggan</label>
                        <input type="text" name="nama_pelanggan" class="form-control rounded-3" placeholder="PT Makmur Jaya" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Jenis Pemeliharaan</label>
                        <select name="jenis_pemeliharaan" class="form-select rounded-3">
                            <option value="RUTIN">RUTIN</option>
                            <option value="KOREKTIF">KOREKTIF</option>
                            <option value="DARURAT">DARURAT</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Petugas Lapangan</label>
                        <input type="text" name="nama_petugas" class="form-control rounded-3" placeholder="Nama Petugas" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Uraian Pekerjaan</label>
                        <textarea name="uraian_pekerjaan" class="form-control rounded-3" rows="3" placeholder="Detail perbaikan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4">Simpan Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Form Tambah Mutasi -->
<div class="modal fade" id="modalTambahMutasi" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-success text-white rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-repeat me-1"></i> Catat Mutasi Perangkat</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="tambah_mutasi">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Pilih No Order Referensi</label>
                        <select name="no_order" class="form-select rounded-3" required>
                            <?php foreach ($data_order as $o): ?>
                                <option value="<?= $o['NoOrder'] ?>"><?= $o['NoOrder'] ?> - <?= $o['NamaPelanggan'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Tanggal Mutasi</label>
                        <input type="date" name="tgl_mutasi" class="form-control rounded-3" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold">No Ref Lama</label>
                            <input type="text" name="ref_lama" class="form-control rounded-3" placeholder="S41-001" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold">No Ref Baru</label>
                            <input type="text" name="ref_baru" class="form-control rounded-3" placeholder="S41-016" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold">IMEI Lama</label>
                            <input type="text" name="imei_lama" class="form-control rounded-3" placeholder="IMEI Lama">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold">IMEI Baru</label>
                            <input type="text" name="imei_baru" class="form-control rounded-3" placeholder="IMEI Baru">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Alasan Mutasi</label>
                        <textarea name="alasan_mutasi" class="form-control rounded-3" rows="2" placeholder="Sebab pergantian..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Petugas Eksekusi</label>
                        <input type="text" name="petugas_eksekusi" class="form-control rounded-3" placeholder="Nama Eksekutor" required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success text-white rounded-3 px-4">Simpan Mutasi</button>
                </div>
            </form>
        </div>
    </div>
</div>