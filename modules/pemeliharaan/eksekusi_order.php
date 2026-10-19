<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Load Koneksi Database jika belum
$root_path = $_SERVER['DOCUMENT_ROOT'];
if (!isset($conn)) {
    if (file_exists($root_path . '/config/database.php')) {
        require_once $root_path . '/config/database.php';
    } else {
        require_once __DIR__ . '/../../config/database.php';
    }
}

$pesan_sukses_eks = '';
$pesan_error_eks  = '';

// 2. Handling Eksekusi Order (Update Status & Data Pengecekan)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_eksekusi'])) {
    if ($_POST['action_eksekusi'] === 'proses_order') {
        try {
            $no_form_pem = $_POST['no_form_pemeliharaan'];
            $kondisi_relay = $_POST['kondisi_relay'];
            $posisi_pasang = $_POST['posisi_pasang'];
            $longitude     = $_POST['longitude'] ?? null;
            $latitude      = $_POST['latitude'] ?? null;
            $status_data   = $_POST['status_data'] ?? 'AKTIF';

            // Cek jika ada unggahan foto pengerjaan baru
            $sql_foto = "";
            $params = [
                ':relay'     => $kondisi_relay,
                ':posisi'    => $posisi_pasang,
                ':long'      => $longitude,
                ':lat'       => $latitude,
                ':status'    => $status_data,
                ':nama_akun' => $_SESSION['NamaAkun'] ?? 'PETUGAS LAPANGAN',
                ':no_form'   => $no_form_pem
            ];

            if (isset($_FILES['foto_pasang']) && $_FILES['foto_pasang']['error'] === UPLOAD_ERR_OK) {
                $sql_foto = ", FotoPasang = :foto";
                $params[':foto'] = file_get_contents($_FILES['foto_pasang']['tmp_name']);
            }

            $stmt = $conn->prepare("UPDATE frm_pemeliharaan SET 
                KondisiRelay = :relay, 
                PosisiPasang = :posisi, 
                Longitude = :long, 
                Latitude = :lat, 
                StatusData = :status, 
                NamaAkun = :nama_akun $sql_foto, 
                WaktuData = NOW() 
                WHERE NoFormPemeliharaan = :no_form");

            $stmt->execute($params);
            $pesan_sukses_eks = "Eksekusi Order ($no_form_pem) berhasil diperbarui dan disimpan!";
        } catch (PDOException $e) {
            $pesan_error_eks = "Gagal memproses eksekusi order: " . $e->getMessage();
        }
    }
}

// 3. Query Data Order Pemeliharaan yang Siap Dieksekusi
$data_eksekusi = [];
try {
    $sql_eks = "SELECT p.*, n.NomorAkun 
                FROM frm_pemeliharaan p 
                LEFT JOIN master_nomor n ON p.SimId = n.SimId 
                ORDER BY p.WaktuData DESC";
    $stmt_eks = $conn->query($sql_eks);
    $data_eksekusi = $stmt_eks->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pesan_error_eks = "Error Database: " . $e->getMessage();
}

$total_order = count($data_eksekusi);
$total_selesai = 0;
foreach ($data_eksekusi as $d) {
    if (($d['KondisiRelay'] ?? '') === 'BERFUNGSI') $total_selesai++;
}
?>

<?php if (!empty($pesan_sukses_eks)): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($pesan_sukses_eks) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($pesan_error_eks)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($pesan_error_eks) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Ringkasan Statistik Eksekusi Order -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-uppercase text-muted fw-bold small mb-1">TOTAL ORDER PEMELIHARAAN</div>
                    <div class="h2 fw-bold mb-0 text-dark"><?= $total_order ?> <span class="fs-6 fw-normal text-muted">Order</span></div>
                    <div class="small text-muted mt-1">Daftar penugasan pemeriksaan relay S41</div>
                </div>
                <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-4 fs-2">
                    <i class="bi bi-tools"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-uppercase text-muted fw-bold small mb-1">EKSEKUSI TERVERIFIKASI</div>
                    <div class="h2 fw-bold mb-0 text-dark"><?= $total_selesai ?> <span class="fs-6 text-muted">/ <?= $total_order ?> Selesai</span></div>
                    <div class="small text-muted mt-1">Perangkat beroperasi normal setelah eksekusi</div>
                </div>
                <div class="p-3 bg-success bg-opacity-10 text-success rounded-4 fs-2">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Daftar Eksekusi Order -->
<div class="card border-0 shadow-sm rounded-4 bg-white p-3">
    <div class="d-flex justify-content-between align-items-center mb-3 px-2 pt-2">
        <h5 class="fw-bold text-primary mb-0">Daftar Eksekusi Order Pemeliharaan</h5>
        <div class="input-group style-search" style="width: 280px;">
            <span class="input-group-text bg-light border-end-0 rounded-start-3 text-muted"><i class="bi bi-search"></i></span>
            <input type="text" id="searchEksekusi" class="form-control bg-light border-start-0 rounded-end-3 text-muted small" placeholder="Cari no order, idpel, petugas...">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="tableEksekusi">
            <thead class="table-light text-dark fw-bold text-uppercase small border-top border-bottom">
                <tr>
                    <th class="ps-3 py-3" style="width: 40px;">#</th>
                    <th class="py-3 text-nowrap">NO FORM / ORDER</th>
                    <th class="py-3 text-nowrap">TGL & PETUGAS</th>
                    <th class="py-3 text-nowrap">IDPEL / NO REF</th>
                    <th class="py-3 text-nowrap">SIM ID / NO AKUN</th>
                    <th class="py-3 text-nowrap">KONDISI RELAY</th>
                    <th class="py-3 text-nowrap">KOORDINAT GPS</th>
                    <th class="pe-3 py-3 text-center" style="width: 120px;">ACTION</th>
                </tr>
            </thead>
            <tbody class="fw-medium text-dark">
                <?php if (empty($data_eksekusi)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada order pemeliharaan yang perlu dieksekusi.</td></tr>
                <?php else: ?>
                    <?php foreach ($data_eksekusi as $idx => $row): ?>
                        <tr class="border-bottom">
                            <td class="ps-3 text-muted small"><?= $idx + 1 ?></td>
                            <td class="fw-bold text-dark text-nowrap">
                                <?= htmlspecialchars($row['NoFormPemeliharaan']) ?>
                                <?php if (!empty($row['NoFormOrder'])): ?>
                                    <div class="small text-primary font-monospace">Order: <?= htmlspecialchars($row['NoFormOrder']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <div class="text-dark fw-semibold"><?= htmlspecialchars($row['TglPemeliharaan']) ?></div>
                                <div class="small text-muted"><i class="bi bi-person me-1"></i><?= htmlspecialchars($row['KdPetugas']) ?></div>
                            </td>
                            <td class="text-nowrap">
                                <div class="fw-bold text-dark">IDPEL: <?= htmlspecialchars($row['IdPel']) ?></div>
                                <div class="small text-muted">REF: <?= htmlspecialchars($row['NoRef']) ?></div>
                            </td>
                            <td class="text-nowrap">
                                <?php if (!empty($row['SimId'])): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info-emphasis border border-info-subtle rounded-3 px-2 py-1 font-monospace">
                                        <?= htmlspecialchars($row['SimId']) ?>
                                    </span>
                                    <?php if (!empty($row['NomorAkun'])): ?>
                                        <div class="small text-muted mt-1 font-monospace"><i class="bi bi-telephone-fill me-1"></i><?= htmlspecialchars($row['NomorAkun']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted fst-italic small">Tanpa SIM</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <?php if (($row['KondisiRelay'] ?? '') === 'BERFUNGSI'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle rounded-2 px-2 py-1"><i class="bi bi-check-lg me-1"></i>BERFUNGSI</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle rounded-2 px-2 py-1"><i class="bi bi-x-lg me-1"></i>RUSAK</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap font-monospace small">
                                <?php if (!empty($row['Longitude']) && !empty($row['Latitude'])): ?>
                                    <a href="https://maps.google.com/?q=<?= htmlspecialchars($row['Latitude']) ?>,<?= htmlspecialchars($row['Longitude']) ?>" target="_blank" class="text-primary text-decoration-none">
                                        <i class="bi bi-geo-alt-fill me-1 text-danger"></i><?= htmlspecialchars($row['Latitude']) ?>, <?= htmlspecialchars($row['Longitude']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Belum Set</span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-3 text-center">
                                <button type="button" class="btn btn-sm btn-primary rounded-3 px-3 py-1" data-bs-toggle="modal" data-bs-target="#modalEksekusiOrder<?= $idx ?>">
                                    <i class="bi bi-play-circle me-1"></i> Eksekusi
                                </button>
                            </td>
                        </tr>

                        <!-- Modal Form Eksekusi Order -->
                        <div class="modal fade" id="modalEksekusiOrder<?= $idx ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content border-0 rounded-4 shadow">
                                    <div class="modal-header border-0 pb-0 pt-4 px-4">
                                        <h5 class="modal-title fw-bold text-dark"><i class="bi bi-tools text-primary me-2"></i>Eksekusi Order Lapangan</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" enctype="multipart/form-data">
                                        <div class="modal-body p-4 text-start">
                                            <input type="hidden" name="action_eksekusi" value="proses_order">
                                            <input type="hidden" name="no_form_pemeliharaan" value="<?= htmlspecialchars($row['NoFormPemeliharaan']) ?>">

                                            <div class="alert alert-light border rounded-3 mb-3 p-3">
                                                <div class="row g-2 small">
                                                    <div class="col-md-6"><strong>NO FORM:</strong> <?= htmlspecialchars($row['NoFormPemeliharaan']) ?></div>
                                                    <div class="col-md-6"><strong>ID PELANGGAN:</strong> <?= htmlspecialchars($row['IdPel']) ?></div>
                                                    <div class="col-md-6"><strong>PETUGAS:</strong> <?= htmlspecialchars($row['KdPetugas']) ?></div>
                                                    <div class="col-md-6"><strong>NO REF S41:</strong> <?= htmlspecialchars($row['NoRef']) ?></div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label text-uppercase text-muted fw-bold small mb-1">HASIL KONDISI RELAY *</label>
                                                    <select name="kondisi_relay" class="form-select rounded-3" required>
                                                        <option value="BERFUNGSI" <?= ($row['KondisiRelay'] ?? '') === 'BERFUNGSI' ? 'selected' : '' ?>>BERFUNGSI (NORMAL)</option>
                                                        <option value="RUSAK" <?= ($row['KondisiRelay'] ?? '') === 'RUSAK' ? 'selected' : '' ?>>RUSAK (PERLU REPARASI/MUTASI)</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label text-uppercase text-muted fw-bold small mb-1">POSISI PASANG PERANGKAT *</label>
                                                    <select name="posisi_pasang" class="form-select rounded-3" required>
                                                        <option value="PANGKAL" <?= ($row['PosisiPasang'] ?? '') === 'PANGKAL' ? 'selected' : '' ?>>PANGKAL</option>
                                                        <option value="UJUNGBERIKUTNYA" <?= ($row['PosisiPasang'] ?? '') === 'UJUNGBERIKUTNYA' ? 'selected' : '' ?>>UJUNG BERIKUTNYA</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label text-uppercase text-muted fw-bold small mb-1">LONGITUDE (GPS)</label>
                                                    <input type="text" name="longitude" class="form-control rounded-3 font-monospace" value="<?= htmlspecialchars($row['Longitude'] ?? '') ?>" placeholder="Contoh: 106.84513">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label text-uppercase text-muted fw-bold small mb-1">LATITUDE (GPS)</label>
                                                    <input type="text" name="latitude" class="form-control rounded-3 font-monospace" value="<?= htmlspecialchars($row['Latitude'] ?? '') ?>" placeholder="Contoh: -6.2088">
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label text-uppercase text-muted fw-bold small mb-1">UNGGAH FOTO BUKTI LAPANGAN</label>
                                                    <input type="file" name="foto_pasang" class="form-control rounded-3" accept="image/*">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label text-uppercase text-muted fw-bold small mb-1">STATUS PEMELIHARAAN *</label>
                                                    <select name="status_data" class="form-select rounded-3">
                                                        <option value="AKTIF" <?= ($row['StatusData'] ?? 'AKTIF') === 'AKTIF' ? 'selected' : '' ?>>AKTIF (SELESAI)</option>
                                                        <option value="TIDAK" <?= ($row['StatusData'] ?? '') === 'TIDAK' ? 'selected' : '' ?>>TIDAK / PENDING</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="modal-footer border-0 p-4 pt-0 justify-content-end gap-2">
                                            <button type="button" class="btn btn-outline-secondary px-4 rounded-3" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary px-4 rounded-3">Simpan Hasil Eksekusi</button>
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

<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchEks = document.getElementById("searchEksekusi");
    if (searchEks) {
        searchEks.addEventListener("keyup", function() {
            const filter = searchEks.value.toLowerCase();
            const rows = document.querySelectorAll("#tableEksekusi tbody tr");
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? "" : "none";
            });
        });
    }
});
</script>