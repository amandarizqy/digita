<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Load Koneksi Database
$root_path = $_SERVER['DOCUMENT_ROOT'];
if (file_exists($root_path . '/config/database.php')) {
    require_once $root_path . '/config/database.php';
} else {
    require_once __DIR__ . '/../../config/database.php';
}

$sub = $_GET['sub'] ?? 'eksekusi_order';
$pesan_sukses = '';
$pesan_error = '';

// 2. Handling Form Submit (Tambah, Edit Order / Mutasi)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ACTION 1: TAMBAH ORDER BARU
    if ($_POST['action'] === 'tambah_order') {
        try {
            $no_order = 'ORD-' . date('Ym') . '-' . sprintf("%03d", rand(1, 999));
            $stmt = $conn->prepare("INSERT INTO pemeliharaan_order (NoOrder, TglOrder, IdPel, NamaPelanggan, JenisPemeliharaan, UraianPekerjaan, KodeUp, KodeAp, KodeUpi, NamaPetugas, StatusEksekusi, WaktuData) VALUES (:no_order, :tgl, :idpel, :nama, :jenis, :uraian, :up, :ap, :upi, :petugas, 'PENDING', NOW())");
            $stmt->execute([
                ':no_order' => $no_order,
                ':tgl' => $_POST['tgl_order'],
                ':idpel' => $_POST['id_pel'],
                ':nama' => $_POST['nama_pelanggan'],
                ':jenis' => $_POST['jenis_pemeliharaan'],
                ':uraian' => $_POST['uraian_pekerjaan'],
                ':up' => $_SESSION['UnitUp'] ?? '56610',
                ':ap' => $_SESSION['UnitAp'] ?? '56610',
                ':upi' => $_SESSION['UnitUpi'] ?? '56',
                ':petugas' => $_POST['nama_petugas']
            ]);
            $pesan_sukses = "Order pemeliharaan ($no_order) berhasil disimpan!";
        } catch (PDOException $e) {
            $pesan_error = "Gagal menyimpan order: " . $e->getMessage();
        }
    } 
    
    // ACTION 2: UPDATE / EDIT ORDER
    elseif ($_POST['action'] === 'edit_order') {
        try {
            $tgl_selesai = ($_POST['status_eksekusi'] === 'SELESAI') ? date('Y-m-d H:i:s') : null;
            $stmt = $conn->prepare("UPDATE pemeliharaan_order SET NamaPelanggan = :nama, JenisPemeliharaan = :jenis, StatusEksekusi = :status, NamaPetugas = :petugas, UraianPekerjaan = :uraian, TglSelesai = :tgl_selesai, WaktuData = NOW() WHERE NoOrder = :no_order");
            $stmt->execute([
                ':nama' => $_POST['nama_pelanggan'],
                ':jenis' => $_POST['jenis_pemeliharaan'],
                ':status' => $_POST['status_eksekusi'],
                ':petugas' => $_POST['nama_petugas'],
                ':uraian' => $_POST['uraian_pekerjaan'],
                ':tgl_selesai' => $tgl_selesai,
                ':no_order' => $_POST['no_order']
            ]);
            $pesan_sukses = "Data Order " . $_POST['no_order'] . " berhasil diperbarui!";
        } catch (PDOException $e) {
            $pesan_error = "Gagal memperbarui order: " . $e->getMessage();
        }
    }

    // ACTION 3: TAMBAH MUTASI PERANGKAT
    elseif ($_POST['action'] === 'tambah_mutasi') {
        try {
            $no_mutasi = 'MUT-' . date('Ym') . '-' . sprintf("%03d", rand(1, 999));
            $stmt = $conn->prepare("INSERT INTO pemeliharaan_mutasi (NoMutasi, NoOrder, TglMutasi, NoRefLama, NoRefBaru, ImeiLama, ImeiBaru, AlasanMutasi, PetugasEksekusi, KodeUp, KodeAp, KodeUpi, WaktuData) VALUES (:nomutasi, :noorder, :tgl, :reflama, :refbaru, :imeilama, :imeibaru, :alasan, :petugas, :up, :ap, :upi, NOW())");
            $stmt->execute([
                ':nomutasi' => $no_mutasi,
                ':noorder' => $_POST['no_order'],
                ':tgl' => $_POST['tgl_mutasi'],
                ':reflama' => $_POST['ref_lama'],
                ':refbaru' => $_POST['ref_baru'],
                ':imeilama' => $_POST['imei_lama'],
                ':imeibaru' => $_POST['imei_baru'],
                ':alasan' => $_POST['alasan_mutasi'],
                ':petugas' => $_POST['petugas_eksekusi'],
                ':up' => $_SESSION['UnitUp'] ?? '56610',
                ':ap' => $_SESSION['UnitAp'] ?? '56610',
                ':upi' => $_SESSION['UnitUpi'] ?? '56'
            ]);
            $pesan_sukses = "Data Mutasi ($no_mutasi) berhasil disimpan!";
        } catch (PDOException $e) {
            $pesan_error = "Gagal menyimpan mutasi: " . $e->getMessage();
        }
    }
}

// 3. Query Data dari Database
$data_order = [];
$data_mutasi = [];
try {
    $stmt_order = $conn->query("SELECT * FROM pemeliharaan_order ORDER BY WaktuData DESC");
    $data_order = $stmt_order->fetchAll(PDO::FETCH_ASSOC);

    $stmt_mutasi = $conn->query("SELECT * FROM pemeliharaan_mutasi ORDER BY WaktuData DESC");
    $data_mutasi = $stmt_mutasi->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pesan_error = "Error Database: " . $e->getMessage();
}

$total_order = count($data_order);
$total_selesai = 0;
$total_proses = 0;
foreach ($data_order as $o) {
    if (($o['StatusEksekusi'] ?? '') === 'SELESAI') $total_selesai++;
    if (($o['StatusEksekusi'] ?? '') === 'PROSES') $total_proses++;
}

// Set Judul Halaman
$page_title = "Modul Pemeliharaan - DIGITA S41";
?>

<div class="container-fluid px-2 py-2">
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
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($pesan_sukses) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($pesan_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($pesan_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Navigasi Menu Sub-Modul -->
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

    <!-- Ringkasan Statistik Kartu -->
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

    <!-- TAB 1: EKSEKUSI ORDER -->
    <?php if ($sub === 'eksekusi_order'): ?>
        <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
            <div class="d-flex justify-content-between align-items-center mb-3 px-2 pt-2">
                <h5 class="fw-bold text-primary mb-0">Daftar Order Pemeliharaan</h5>
                <div class="input-group style-search" style="width: 280px;">
                    <span class="input-group-text bg-light border-end-0 rounded-start-3 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchTable" class="form-control bg-light border-start-0 rounded-end-3 text-muted small" placeholder="Cari no order, nama pel...">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tableOrder">
                    <thead class="table-light text-dark fw-bold text-uppercase small border-top border-bottom">
                        <tr>
                            <th class="ps-3 py-3" style="width: 40px;">#</th>
                            <th class="py-3 text-nowrap">NO ORDER</th>
                            <th class="py-3 text-nowrap">JENIS</th>
                            <th class="py-3">NAMA PELANGGAN / IDPEL</th>
                            <th class="py-3 text-nowrap">PETUGAS</th>
                            <th class="py-3 text-nowrap">STATUS DATA</th>
                            <th class="py-3 text-nowrap">WAKTU DATA</th>
                            <th class="pe-3 py-3 text-center" style="width: 80px;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="fw-medium text-dark">
                        <?php if (empty($data_order)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada data order pemeliharaan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($data_order as $idx => $row): ?>
                                <tr class="border-bottom">
                                    <td class="ps-3 text-muted small"><?= $idx + 1 ?></td>
                                    <td class="fw-bold text-dark text-nowrap"><?= htmlspecialchars($row['NoOrder']) ?></td>
                                    
                                    <!-- BADGE WARNA BERDASARKAN JENIS PEMELIHARAAN -->
                                    <td class="text-nowrap">
                                        <?php if ($row['JenisPemeliharaan'] === 'DARURAT'): ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle rounded-2 px-2 py-1 fw-bold small">
                                                DARURAT
                                            </span>
                                        <?php elseif ($row['JenisPemeliharaan'] === 'KOREKTIF'): ?>
                                            <span class="badge bg-warning bg-opacity-10 text-warning-emphasis border border-warning-subtle rounded-2 px-2 py-1 fw-bold small">
                                                KOREKTIF
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-info bg-opacity-10 text-info-emphasis border border-info-subtle rounded-2 px-2 py-1 fw-bold small">
                                                RUTIN
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="fw-bold text-uppercase text-dark"><?= htmlspecialchars($row['NamaPelanggan']) ?></div>
                                        <div class="small text-muted">IDPEL: <?= htmlspecialchars($row['IdPel']) ?></div>
                                    </td>
                                    <td class="text-nowrap text-secondary"><?= htmlspecialchars($row['NamaPetugas']) ?></td>
                                    <td class="text-nowrap">
                                        <?php if ($row['StatusEksekusi'] === 'SELESAI'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 font-monospace">
                                                <i class="bi bi-check-circle me-1"></i>SELESAI
                                            </span>
                                        <?php elseif ($row['StatusEksekusi'] === 'PROSES'): ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-1 font-monospace">
                                                <i class="bi bi-clock me-1"></i>PROSES
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 py-1 font-monospace">
                                                <i class="bi bi-dash-circle me-1"></i>PENDING
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-nowrap font-monospace text-secondary align-middle" style="line-height: 1.3;">
                                        <div><?= !empty($row['WaktuData']) ? date('Y-m-d', strtotime($row['WaktuData'])) : date('Y-m-d', strtotime($row['TglOrder'])) ?></div>
                                        <div><?= !empty($row['WaktuData']) ? date('H:i:s', strtotime($row['WaktuData'])) : '08:00:00' ?></div>
                                    </td>

                                    <td class="pe-3 text-center">
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-3 px-2 py-1" data-bs-toggle="modal" data-bs-target="#modalEditOrder<?= $idx ?>" title="Edit Data Order">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                    </td>
                                </tr>

                                <!-- MODAL EDIT DATA ORDER -->
                                <div class="modal fade" id="modalEditOrder<?= $idx ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 rounded-4 shadow">
                                            <div class="modal-header border-0 pb-0 pt-4 px-4">
                                                <h5 class="modal-title fw-bold text-dark">Edit Data Order Pemeliharaan</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body p-4 text-start">
                                                    <input type="hidden" name="action" value="edit_order">
                                                    <input type="hidden" name="no_order" value="<?= htmlspecialchars($row['NoOrder']) ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">NO ORDER (PRIMARY KEY - LOCKED)</label>
                                                        <input type="text" class="form-control rounded-3 bg-light text-muted" value="<?= htmlspecialchars($row['NoOrder']) ?>" readonly>
                                                        <div class="form-text text-muted small mt-1">* No Order terkunci untuk menghindari kegagalan integritas data terikat.</div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">NAMA PELANGGAN *</label>
                                                        <input type="text" name="nama_pelanggan" class="form-control rounded-3" value="<?= htmlspecialchars($row['NamaPelanggan']) ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">JENIS PEMELIHARAAN *</label>
                                                        <select name="jenis_pemeliharaan" class="form-select rounded-3">
                                                            <option value="RUTIN" <?= $row['JenisPemeliharaan'] === 'RUTIN' ? 'selected' : '' ?>>RUTIN</option>
                                                            <option value="KOREKTIF" <?= $row['JenisPemeliharaan'] === 'KOREKTIF' ? 'selected' : '' ?>>KOREKTIF</option>
                                                            <option value="DARURAT" <?= $row['JenisPemeliharaan'] === 'DARURAT' ? 'selected' : '' ?>>DARURAT</option>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">STATUS EKSEKUSI *</label>
                                                        <select name="status_eksekusi" class="form-select rounded-3">
                                                            <option value="PENDING" <?= $row['StatusEksekusi'] === 'PENDING' ? 'selected' : '' ?>>PENDING</option>
                                                            <option value="PROSES" <?= $row['StatusEksekusi'] === 'PROSES' ? 'selected' : '' ?>>PROSES</option>
                                                            <option value="SELESAI" <?= $row['StatusEksekusi'] === 'SELESAI' ? 'selected' : '' ?>>SELESAI</option>
                                                            <option value="BATAL" <?= $row['StatusEksekusi'] === 'BATAL' ? 'selected' : '' ?>>BATAL</option>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">PETUGAS LAPANGAN *</label>
                                                        <input type="text" name="nama_petugas" class="form-control rounded-3" value="<?= htmlspecialchars($row['NamaPetugas']) ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">URAIAN PEKERJAAN</label>
                                                        <textarea name="uraian_pekerjaan" class="form-control rounded-3" rows="2"><?= htmlspecialchars($row['UraianPekerjaan'] ?? '') ?></textarea>
                                                    </div>
                                                </div>

                                                <div class="modal-footer border-0 p-4 pt-0 justify-content-end gap-2">
                                                    <button type="button" class="btn btn-outline-secondary px-4 rounded-3" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary px-4 rounded-3">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- TAB 2: EKSEKUSI MUTASI -->
    <?php elseif ($sub === 'eksekusi_mutasi'): ?>
        <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
            <h5 class="fw-bold text-primary mb-3 px-2 pt-2">Riwayat Mutasi Perangkat S41</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-dark fw-bold text-uppercase small border-top border-bottom">
                        <tr>
                            <th class="ps-3 py-3" style="width: 40px;">#</th>
                            <th class="py-3 text-nowrap">NO MUTASI</th>
                            <th class="py-3 text-nowrap">NO ORDER</th>
                            <th class="py-3 text-nowrap">TGL MUTASI</th>
                            <th class="py-3 text-nowrap">REF LAMA -> BARU</th>
                            <th class="py-3">ALASAN MUTASI</th>
                            <th class="py-3 text-nowrap">WAKTU DATA</th>
                            <th class="pe-3 py-3 text-nowrap">PETUGAS</th>
                        </tr>
                    </thead>
                    <tbody class="fw-medium text-dark">
                        <?php if (empty($data_mutasi)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada riwayat mutasi perangkat.</td></tr>
                        <?php else: ?>
                            <?php foreach ($data_mutasi as $idx => $m): ?>
                                <tr class="border-bottom">
                                    <td class="ps-3 text-muted small"><?= $idx + 1 ?></td>
                                    <td class="fw-bold text-dark text-nowrap"><?= htmlspecialchars($m['NoMutasi']) ?></td>
                                    <td class="text-primary text-nowrap fw-bold"><?= htmlspecialchars($m['NoOrder']) ?></td>
                                    <td class="text-nowrap text-muted small"><?= htmlspecialchars($m['TglMutasi']) ?></td>
                                    <td class="text-nowrap">
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle rounded-2 px-2 py-1"><?= htmlspecialchars($m['NoRefLama']) ?></span>
                                        <i class="bi bi-arrow-right mx-1 text-muted"></i>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle rounded-2 px-2 py-1"><?= htmlspecialchars($m['NoRefBaru']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($m['AlasanMutasi']) ?></td>
                                    <td class="text-nowrap font-monospace text-secondary align-middle" style="line-height: 1.3;">
                                        <div><?= !empty($m['WaktuData']) ? date('Y-m-d', strtotime($m['WaktuData'])) : date('Y-m-d', strtotime($m['TglMutasi'])) ?></div>
                                        <div><?= !empty($m['WaktuData']) ? date('H:i:s', strtotime($m['WaktuData'])) : '08:00:00' ?></div>
                                    </td>
                                    <td class="pe-3 text-nowrap"><?= htmlspecialchars($m['PetugasEksekusi']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- TAB 3: MONITORING ORDER -->
    <?php elseif ($sub === 'monitoring_order'): ?>
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-primary mb-0">Rekapitulasi Laporan Monitoring Pemeliharaan (Order)</h5>
                <button class="btn btn-outline-secondary btn-sm rounded-3" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak Laporan</button>
            </div>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-dark small">
                        <tr>
                            <th class="text-nowrap">NO ORDER</th>
                            <th class="text-nowrap">TANGGAL</th>
                            <th>PELANGGAN</th>
                            <th>JENIS</th>
                            <th>PETUGAS LAPANGAN</th>
                            <th>STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data_order)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">Belum ada data rekapitulasi order.</td></tr>
                        <?php else: ?>
                            <?php foreach ($data_order as $row): ?>
                                <tr>
                                    <td class="fw-bold text-nowrap"><?= htmlspecialchars($row['NoOrder']) ?></td>
                                    <td class="text-nowrap"><?= htmlspecialchars($row['TglOrder']) ?></td>
                                    <td><?= htmlspecialchars($row['NamaPelanggan']) ?> (<?= htmlspecialchars($row['IdPel']) ?>)</td>
                                    <td>
                                        <?php if ($row['JenisPemeliharaan'] === 'DARURAT'): ?>
                                            <span class="badge bg-danger">DARURAT</span>
                                        <?php elseif ($row['JenisPemeliharaan'] === 'KOREKTIF'): ?>
                                            <span class="badge bg-warning text-dark">KOREKTIF</span>
                                        <?php else: ?>
                                            <span class="badge bg-info text-dark">RUTIN</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['NamaPetugas']) ?></td>
                                    <td>
                                        <?php if ($row['StatusEksekusi'] === 'SELESAI'): ?>
                                            <span class="badge bg-success">SELESAI</span>
                                        <?php elseif ($row['StatusEksekusi'] === 'PROSES'): ?>
                                            <span class="badge bg-warning text-dark">PROSES</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">PENDING</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- TAB 4: MONITORING MUTASI -->
    <?php elseif ($sub === 'monitoring_mutasi'): ?>
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-success mb-0">Rekapitulasi Laporan Monitoring Mutasi Perangkat</h5>
                <button class="btn btn-outline-secondary btn-sm rounded-3" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak Laporan Mutasi</button>
            </div>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-success small text-uppercase">
                        <tr>
                            <th class="text-nowrap px-3 py-2" style="width: 140px;">NO MUTASI</th>
                            <th class="text-nowrap px-3 py-2" style="width: 150px;">NO ORDER REF</th>
                            <th class="text-nowrap px-3 py-2" style="width: 120px;">TGL MUTASI</th>
                            <th class="text-nowrap px-3 py-2" style="width: 180px;">REF LAMA <i class="bi bi-arrow-right"></i> BARU</th>
                            <th class="text-nowrap px-3 py-2" style="width: 220px;">IMEI LAMA <i class="bi bi-arrow-right"></i> BARU</th>
                            <th class="px-3 py-2">ALASAN MUTASI</th>
                            <th class="text-nowrap px-3 py-2" style="width: 160px;">PETUGAS EKSEKUTOR</th>
                        </tr>
                    </thead>
                    <tbody class="fw-medium">
                        <?php if (empty($data_mutasi)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data rekapitulasi mutasi perangkat.</td></tr>
                        <?php else: ?>
                            <?php foreach ($data_mutasi as $m): ?>
                                <tr>
                                    <td class="fw-bold text-dark text-nowrap px-3 align-middle"><?= htmlspecialchars($m['NoMutasi']) ?></td>
                                    <td class="text-primary fw-bold text-nowrap px-3 align-middle"><?= htmlspecialchars($m['NoOrder']) ?></td>
                                    <td class="text-nowrap px-3 align-middle"><?= htmlspecialchars($m['TglMutasi']) ?></td>
                                    <td class="text-nowrap px-3 align-middle">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle rounded-pill px-2 py-1"><?= htmlspecialchars($m['NoRefLama']) ?></span>
                                            <i class="bi bi-arrow-right text-muted small"></i>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle rounded-pill px-2 py-1"><?= htmlspecialchars($m['NoRefBaru']) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-nowrap px-3 align-middle">
                                        <div class="d-flex flex-column gap-1 small">
                                            <div><span class="text-muted small">Lama:</span> <code class="text-dark bg-light px-1 rounded"><?= htmlspecialchars($m['ImeiLama'] ?? '-') ?></code></div>
                                            <div><span class="text-muted small">Baru:</span> <code class="text-primary bg-light px-1 rounded"><?= htmlspecialchars($m['ImeiBaru'] ?? '-') ?></code></div>
                                        </div>
                                    </td>
                                    <td class="px-3 align-middle" style="min-width: 200px;"><?= htmlspecialchars($m['AlasanMutasi']) ?></td>
                                    <td class="text-nowrap px-3 align-middle"><?= htmlspecialchars($m['PetugasEksekusi']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Script Pencarian Realtime Tabel -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById("searchTable");
    if (searchInput) {
        searchInput.addEventListener("keyup", function() {
            const filter = searchInput.value.toLowerCase();
            const rows = document.querySelectorAll("#tableOrder tbody tr");
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? "" : "none";
            });
        });
    }
});
</script>

<!-- Modal Form Buat Order Baru -->
<div class="modal fade" id="modalTambahOrder" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark">Form Order Pemeliharaan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="tambah_order">
                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">TANGGAL ORDER *</label>
                        <input type="date" name="tgl_order" class="form-control rounded-3" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">ID PELANGGAN *</label>
                        <input type="text" name="id_pel" class="form-control rounded-3" placeholder="560000000001" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">NAMA PELANGGAN *</label>
                        <input type="text" name="nama_pelanggan" class="form-control rounded-3" placeholder="PT Makmur Jaya" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">JENIS PEMELIHARAAN *</label>
                        <select name="jenis_pemeliharaan" class="form-select rounded-3">
                            <option value="RUTIN">RUTIN</option>
                            <option value="KOREKTIF">KOREKTIF</option>
                            <option value="DARURAT">DARURAT</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">PETUGAS LAPANGAN *</label>
                        <input type="text" name="nama_petugas" class="form-control rounded-3" placeholder="Nama Petugas" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">URAIAN PEKERJAAN</label>
                        <textarea name="uraian_pekerjaan" class="form-control rounded-3" rows="3" placeholder="Detail perbaikan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0 justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary px-4 rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4 rounded-3">Simpan Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Form Catat Mutasi Baru -->
<div class="modal fade" id="modalTambahMutasi" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark">Catat Mutasi Perangkat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="tambah_mutasi">
                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">PILIH NO ORDER REFERENSI *</label>
                        <select name="no_order" class="form-select rounded-3" required>
                            <?php foreach ($data_order as $o): ?>
                                <option value="<?= htmlspecialchars($o['NoOrder']) ?>"><?= htmlspecialchars($o['NoOrder']) ?> - <?= htmlspecialchars($o['NamaPelanggan']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">TANGGAL MUTASI *</label>
                        <input type="date" name="tgl_mutasi" class="form-control rounded-3" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">NO REF LAMA *</label>
                            <input type="text" name="ref_lama" class="form-control rounded-3" placeholder="S41-001" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">NO REF BARU *</label>
                            <input type="text" name="ref_baru" class="form-control rounded-3" placeholder="S41-016" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">IMEI LAMA</label>
                            <input type="text" name="imei_lama" class="form-control rounded-3" placeholder="IMEI Lama">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">IMEI BARU</label>
                            <input type="text" name="imei_baru" class="form-control rounded-3" placeholder="IMEI Baru">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">ALASAN MUTASI</label>
                        <textarea name="alasan_mutasi" class="form-control rounded-3" rows="2" placeholder="Sebab pergantian..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">PETUGAS EKSEKUSI *</label>
                        <input type="text" name="petugas_eksekusi" class="form-control rounded-3" placeholder="Nama Eksekutor" required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0 justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary px-4 rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success text-white px-4 rounded-3">Simpan Mutasi</button>
                </div>
            </form>
        </div>
    </div>
</div>