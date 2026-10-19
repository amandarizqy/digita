<?php
$pesan_sukses = '';
$pesan_error = '';

// 1. Query SIM ID Aktif dari master_nomor
$list_sim_pem = [];
try {
    $stmt_sim = $conn->query("SELECT SimId, NomorAkun FROM master_nomor WHERE StatusData = 'AKTIF' ORDER BY SimId ASC");
    $list_sim_pem = $stmt_sim->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $list_sim_pem = [];
}

// 2. Query IMEI Modem Aktif
$list_modem_pem = [];
try {
    $sql_modem_filter = "SELECT ImeiModem 
                         FROM master_modem 
                         WHERE StatusData = 'AKTIF' 
                           AND ImeiModem NOT IN (
                               SELECT DISTINCT ImeiModem 
                               FROM frm_pemeliharaan 
                               WHERE StatusData = 'AKTIF' AND ImeiModem IS NOT NULL AND ImeiModem != ''
                           ) 
                         ORDER BY ImeiModem ASC";
    $stmt_modem = $conn->query($sql_modem_filter);
    $list_modem_pem = $stmt_modem->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $list_modem_pem = [];
}

// 3. Handling Form Submit (Tambah & Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Cleaning Input String agar Presisi dengan Tipe ENUM MySQL
    $input_relay  = strtoupper(trim($_POST['kondisi_relay'] ?? 'BERFUNGSI'));
    $input_status = strtoupper(trim($_POST['status_data'] ?? 'AKTIF'));
    $input_posisi = strtoupper(trim($_POST['posisi_pasang'] ?? 'PANGKAL'));

    if ($_POST['action'] === 'tambah_pemeliharaan') {
        try {
            $kode_up = $_POST['kode_up'] ?? $_SESSION['KodeUp'] ?? '56610';
            $no_form_pem = $kode_up . date('Ym') . sprintf("%04d", rand(1, 9999));

            $foto_pasang = null;
            if (isset($_FILES['foto_pasang']) && $_FILES['foto_pasang']['error'] === UPLOAD_ERR_OK) {
                $foto_pasang = file_get_contents($_FILES['foto_pasang']['tmp_name']);
            }

            $stmt = $conn->prepare("INSERT INTO frm_pemeliharaan 
                (NoFormPemeliharaan, NoFormOrder, TglPemeliharaan, KdPetugas, NoRef, SimId, ImeiModem, IdPel, Longitude, Latitude, PosisiPasang, FotoPasang, KondisiRelay, KodeUp, KodeAp, KodeUpi, StatusData, WaktuData, NamaAkun) 
                VALUES (:no_form, :no_order, :tgl, :kd_petugas, :no_ref, :sim_id, :imei, :id_pel, :long, :lat, :posisi, :foto, :relay, :up, :ap, :upi, :status_data, NOW(), :nama_akun)");

            $stmt->execute([
                ':no_form'      => $no_form_pem,
                ':no_order'     => !empty($_POST['no_form_order']) ? $_POST['no_form_order'] : null,
                ':tgl'          => $_POST['tgl_pemeliharaan'],
                ':kd_petugas'   => $_POST['kd_petugas'],
                ':no_ref'       => $_POST['no_ref'],
                ':sim_id'       => !empty($_POST['sim_id']) ? $_POST['sim_id'] : null,
                ':imei'         => !empty($_POST['imei_modem']) ? $_POST['imei_modem'] : null,
                ':id_pel'       => $_POST['id_pel'],
                ':long'         => $_POST['longitude'] ?? null,
                ':lat'          => $_POST['latitude'] ?? null,
                ':posisi'       => $input_posisi,
                ':foto'         => $foto_pasang,
                ':relay'        => $input_relay,
                ':up'           => $kode_up,
                ':ap'           => $_POST['kode_ap'] ?? $_SESSION['KodeAp'] ?? '56610',
                ':upi'          => $_POST['kode_upi'] ?? $_SESSION['KodeUpi'] ?? '56',
                ':status_data'  => $input_status,
                ':nama_akun'    => $_SESSION['NamaAkun'] ?? 'SUPER ADMIN'
            ]);

            $pesan_sukses = "Data Order Pemeliharaan ($no_form_pem) berhasil disimpan!";
        } catch (PDOException $e) {
            $pesan_error = "Gagal menyimpan data: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'edit_pemeliharaan') {
        try {
            $no_form_pem = $_POST['no_form_pemeliharaan'];

            $sql_foto = "";
            $params = [
                ':no_order'     => !empty($_POST['no_form_order']) ? $_POST['no_form_order'] : null,
                ':tgl'          => $_POST['tgl_pemeliharaan'],
                ':kd_petugas'   => $_POST['kd_petugas'],
                ':no_ref'       => $_POST['no_ref'],
                ':sim_id'       => !empty($_POST['sim_id']) ? $_POST['sim_id'] : null,
                ':imei'         => !empty($_POST['imei_modem']) ? $_POST['imei_modem'] : null,
                ':id_pel'       => $_POST['id_pel'],
                ':long'         => $_POST['longitude'] ?? null,
                ':lat'          => $_POST['latitude'] ?? null,
                ':posisi'       => $input_posisi,
                ':relay'        => $input_relay,
                ':status_data'  => $input_status,
                ':no_form'      => $no_form_pem
            ];

            if (isset($_FILES['foto_pasang']) && $_FILES['foto_pasang']['error'] === UPLOAD_ERR_OK) {
                $sql_foto = ", FotoPasang = :foto";
                $params[':foto'] = file_get_contents($_FILES['foto_pasang']['tmp_name']);
            }

            // STATEMENT UPDATE UTUH
            $stmt = $conn->prepare("UPDATE frm_pemeliharaan SET 
                NoFormOrder = :no_order, 
                TglPemeliharaan = :tgl, 
                KdPetugas = :kd_petugas, 
                NoRef = :no_ref, 
                SimId = :sim_id, 
                ImeiModem = :imei, 
                IdPel = :id_pel, 
                Longitude = :long, 
                Latitude = :lat, 
                PosisiPasang = :posisi, 
                KondisiRelay = :relay, 
                StatusData = :status_data $sql_foto, 
                WaktuData = NOW() 
                WHERE NoFormPemeliharaan = :no_form");

            $stmt->execute($params);
            $pesan_sukses = "Data Order Pemeliharaan ($no_form_pem) berhasil diperbarui!";
        } catch (PDOException $e) {
            $pesan_error = "Gagal memperbarui data: " . $e->getMessage();
        }
    }
}

// 4. Fetch Data
$data_pemeliharaan = [];
try {
    $sql = "SELECT p.*, n.NomorAkun 
            FROM frm_pemeliharaan p 
            LEFT JOIN master_nomor n ON p.SimId = n.SimId 
            ORDER BY p.WaktuData DESC";
    $stmt = $conn->query($sql);
    $data_pemeliharaan = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pesan_error = "Error Database: " . $e->getMessage();
}

$total_pemeliharaan = count($data_pemeliharaan);
$total_relay_baik = 0;
foreach ($data_pemeliharaan as $p) {
    if (strtoupper(trim($p['KondisiRelay'] ?? '')) === 'BERFUNGSI') $total_relay_baik++;
}
?>

<!-- STYLE CSS -->
<style>
    .table-compact thead th {
        font-size: 0.75rem !important;
        font-weight: 700 !important;
        color: #6c757d !important;
        letter-spacing: 0.5px;
        padding: 0.6rem 0.35rem !important;
        white-space: normal !important;
    }
    .table-compact tbody td {
        padding: 0.5rem 0.35rem !important;
        font-size: 0.81rem !important;
        white-space: normal !important;
        word-break: break-word !important;
    }
    .table-compact .badge {
        font-size: 0.7rem !important;
        padding: 0.3em 0.45em !important;
    }
    .select-scrollable {
        max-height: 200px;
        font-size: 0.82rem !important;
    }
    .modal .form-select option {
        padding: 6px;
        font-size: 0.82rem;
    }
    .modal-footer .btn {
        min-width: 110px;
        font-size: 0.85rem;
        font-weight: 600;
        padding: 0.45rem 1rem;
    }
    .fade-in-tab {
        animation: fadeIn 0.25s ease-in-out;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

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

<!-- KPI Ringkasan -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-uppercase text-muted fw-bold small mb-1">TOTAL ORDER PEMELIHARAAN</div>
                    <div class="h2 fw-bold mb-0 text-dark"><?= $total_pemeliharaan ?> <span class="fs-6 fw-normal text-muted">Berkas</span></div>
                </div>
                <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-4 fs-2">
                    <i class="bi bi-file-earmark-check"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-uppercase text-muted fw-bold small mb-1">KONDISI RELAY BAIK</div>
                    <div class="h2 fw-bold mb-0 text-dark"><?= $total_relay_baik ?> <span class="fs-6 text-muted">/ <?= $total_pemeliharaan ?> Normal</span></div>
                </div>
                <div class="p-3 bg-success bg-opacity-10 text-success rounded-4 fs-2">
                    <i class="bi bi-shield-check"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Kartu Tabel Utama -->
<div class="card border-0 shadow-sm rounded-4 bg-white p-3">
    <div class="d-flex justify-content-between align-items-center mb-3 px-2 pt-2">
        <h5 class="fw-bold text-primary mb-0">Daftar Order Pemeliharaan</h5>
        <div class="d-flex gap-2">
            <div class="input-group style-search" style="width: 240px;">
                <span class="input-group-text bg-light border-end-0 rounded-start-3 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="searchTable" class="form-control bg-light border-start-0 rounded-end-3 text-muted small" placeholder="Cari data...">
            </div>
            <button class="btn btn-primary fw-medium px-3 rounded-3" data-bs-toggle="modal" data-bs-target="#modalTambahPemeliharaan">
                <i class="bi bi-plus-lg me-1"></i> Buat Order Pemeliharaan
            </button>
        </div>
    </div>

    <!-- Container Tabel -->
    <div class="w-100 fade-in-tab">
        <table class="table table-hover align-middle mb-0 table-compact w-100" id="tablePemeliharaan">
            <thead class="table-light border-top border-bottom">
                <tr>
                    <th class="ps-2 text-center" style="width: 3%;">#</th>
                    <th style="width: 15%;">NO FORM PEMELIHARAAN</th>
                    <th style="width: 10%;">TGL PEMELIHARAAN</th>
                    <th style="width: 13%;">IDPEL / NO REF</th>
                    <th style="width: 13%;">SIM ID / NO AKUN</th>
                    <th style="width: 12%;">IMEI MODEM</th>
                    <th style="width: 12%;">KOORDINAT GPS</th>
                    <th style="width: 10%;">KONDISI RELAY</th>
                    <th style="width: 8%;">STATUS DATA</th>
                    <th class="pe-2 text-center" style="width: 7%;">AKSI</th>
                </tr>
            </thead>
            <tbody class="fw-medium text-dark">
                <?php if (empty($data_pemeliharaan)): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">Belum ada data order pemeliharaan.</td></tr>
                <?php else: ?>
                    <?php foreach ($data_pemeliharaan as $idx => $row): ?>
                        <?php 
                            $relay_val  = strtoupper(trim($row['KondisiRelay'] ?? ''));
                            $status_val = strtoupper(trim($row['StatusData'] ?? ''));
                        ?>
                        <tr class="border-bottom">
                            <td class="ps-2 text-center text-muted small"><?= $idx + 1 ?></td>
                            <td class="fw-bold text-dark font-monospace">
                                <?= htmlspecialchars($row['NoFormPemeliharaan']) ?>
                            </td>
                            <td class="text-secondary"><?= htmlspecialchars($row['TglPemeliharaan']) ?></td>
                            <td>
                                <div class="fw-bold text-dark">IDPEL: <?= htmlspecialchars($row['IdPel']) ?></div>
                                <div class="small text-muted">REF: <?= htmlspecialchars($row['NoRef']) ?></div>
                            </td>
                            <td>
                                <?php if (!empty($row['SimId'])): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info-emphasis border border-info-subtle font-monospace">
                                        <?= htmlspecialchars($row['SimId']) ?>
                                    </span>
                                    <?php if (!empty($row['NomorAkun'])): ?>
                                        <div class="small text-muted mt-1 font-monospace"><?= htmlspecialchars($row['NomorAkun']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted fst-italic small">Tanpa SIM</span>
                                <?php endif; ?>
                            </td>
                            <td class="font-monospace"><?= htmlspecialchars($row['ImeiModem'] ?? '-') ?></td>
                            <td class="font-monospace text-muted small">
                                <?= (!empty($row['Longitude']) && !empty($row['Latitude'])) ? htmlspecialchars($row['Latitude']) . ',<br>' . htmlspecialchars($row['Longitude']) : '-' ?>
                            </td>

                            <!-- BADGE KONDISI RELAY -->
                            <td>
                                <?php if ($relay_val === 'RUSAK'): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold">RUSAK</span>
                                <?php elseif ($relay_val === 'BERFUNGSI'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold">BERFUNGSI</span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle fw-bold">BELUM DISET</span>
                                <?php endif; ?>
                            </td>

                            <!-- BADGE STATUS DATA -->
                            <td>
                                <?php if ($status_val === 'AKTIF'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold">AKTIF</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold">TIDAK AKTIF</span>
                                <?php endif; ?>
                            </td>

                            <td class="pe-2 text-center">
                                <div class="btn-group btn-group-sm">
                                    <?php if (!empty($row['FotoPasang'])): ?>
                                        <button type="button" class="btn btn-outline-info p-1 lh-1 rounded-2 me-1" data-bs-toggle="modal" data-bs-target="#modalFoto<?= $idx ?>" title="Lihat Foto Pasang">
                                            <i class="bi bi-image"></i>
                                        </button>

                                        <!-- Modal Pop-up Foto Besar -->
                                        <div class="modal fade" id="modalFoto<?= $idx ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content border-0 rounded-4 shadow">
                                                    <div class="modal-header border-bottom py-3 px-4">
                                                        <h6 class="modal-title fw-bold text-dark">Foto Pasang - <?= htmlspecialchars($row['NoFormPemeliharaan']) ?></h6>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body text-center p-3">
                                                        <img src="data:image/jpeg;base64,<?= base64_encode($row['FotoPasang']) ?>" class="img-fluid rounded-3 shadow-sm" alt="Foto Pasang">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <button type="button" class="btn btn-outline-primary p-1 lh-1 rounded-2" data-bs-toggle="modal" data-bs-target="#modalEditPemeliharaan<?= $idx ?>" title="Edit Data">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Modal Edit Pemeliharaan -->
                        <div class="modal fade" id="modalEditPemeliharaan<?= $idx ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content border-0 rounded-4 shadow">
                                    <div class="modal-header border-0 pb-0 pt-4 px-4">
                                        <h5 class="modal-title fw-bold text-dark">Edit Order Pemeliharaan</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" enctype="multipart/form-data">
                                        <div class="modal-body p-4 text-start">
                                            <input type="hidden" name="action" value="edit_pemeliharaan">
                                            <input type="hidden" name="no_form_pemeliharaan" value="<?= htmlspecialchars($row['NoFormPemeliharaan']) ?>">

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label text-uppercase text-muted fw-bold small mb-1">NO FORM PEMELIHARAAN</label>
                                                    <input type="text" class="form-control rounded-3 bg-light text-muted" value="<?= htmlspecialchars($row['NoFormPemeliharaan']) ?>" readonly>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label text-uppercase text-muted fw-bold small mb-1">NO FORM ORDER REF</label>
                                                    <input type="text" name="no_form_order" class="form-control rounded-3" value="<?= htmlspecialchars($row['NoFormOrder'] ?? '') ?>">
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label text-uppercase text-muted fw-bold small mb-1">TGL PEMELIHARAAN *</label>
                                                    <input type="date" name="tgl_pemeliharaan" class="form-control rounded-3" value="<?= htmlspecialchars($row['TglPemeliharaan']) ?>" required>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label text-uppercase text-muted fw-bold small mb-1">KODE PETUGAS *</label>
                                                    <input type="text" name="kd_petugas" class="form-control rounded-3" value="<?= htmlspecialchars($row['KdPetugas']) ?>" required>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label text-uppercase text-muted fw-bold small mb-1">NO REF PERANGKAT *</label>
                                                    <input type="text" name="no_ref" class="form-control rounded-3" value="<?= htmlspecialchars($row['NoRef']) ?>" required>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label text-uppercase text-muted fw-bold small mb-1">ID PELANGGAN *</label>
                                                    <input type="text" name="id_pel" class="form-control rounded-3" value="<?= htmlspecialchars($row['IdPel']) ?>" required>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label text-uppercase text-muted fw-bold small mb-1">SIM ID / NO AKUN</label>
                                                    <select name="sim_id" class="form-select rounded-3 font-monospace select-scrollable">
                                                        <option value="">-- Tanpa SIM Card --</option>
                                                        <?php foreach ($list_sim_pem as $s): ?>
                                                            <option value="<?= htmlspecialchars($s['SimId']); ?>" <?= (($row['SimId'] ?? '') === $s['SimId']) ? 'selected' : ''; ?>>
                                                                <?= htmlspecialchars($s['SimId']); ?><?= !empty($s['NomorAkun']) ? ' (' . htmlspecialchars($s['NomorAkun']) . ')' : ''; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label text-uppercase text-muted fw-bold small mb-1">IMEI MODEM</label>
                                                    <select name="imei_modem" class="form-select rounded-3 font-monospace select-scrollable">
                                                        <option value="">-- Tanpa Modem --</option>
                                                        <?php 
                                                            $current_imei = $row['ImeiModem'] ?? '';
                                                            $modem_options = $list_modem_pem;
                                                            if (!empty($current_imei) && !in_array($current_imei, array_column($modem_options, 'ImeiModem'))) {
                                                                array_unshift($modem_options, ['ImeiModem' => $current_imei]);
                                                            }
                                                        ?>
                                                        <?php foreach ($modem_options as $m): ?>
                                                            <option value="<?= htmlspecialchars($m['ImeiModem']); ?>" <?= ($current_imei === $m['ImeiModem']) ? 'selected' : ''; ?>>
                                                                <?= htmlspecialchars($m['ImeiModem']); ?><?= ($current_imei === $m['ImeiModem']) ? ' (Terpasang)' : ''; ?>
                                                            </option>
                                                        <?php endforeach; ?>
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
                                                    <label class="form-label text-uppercase text-muted fw-bold small mb-1">POSISI PASANG *</label>
                                                    <select name="posisi_pasang" class="form-select rounded-3">
                                                        <option value="PANGKAL" <?= (strtoupper(trim($row['PosisiPasang'] ?? '')) === 'PANGKAL') ? 'selected' : '' ?>>PANGKAL</option>
                                                        <option value="UJUNGBERIKUTNYA" <?= (strtoupper(trim($row['PosisiPasang'] ?? '')) === 'UJUNGBERIKUTNYA') ? 'selected' : '' ?>>UJUNG BERIKUTNYA</option>
                                                    </select>
                                                </div>

                                                <!-- OPTION SELECT KONDISI RELAY -->
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label text-uppercase text-muted fw-bold small mb-1">KONDISI RELAY *</label>
                                                    <select name="kondisi_relay" class="form-select rounded-3" required>
                                                        <option value="BERFUNGSI" <?= (strtoupper(trim($row['KondisiRelay'] ?? '')) === 'BERFUNGSI') ? 'selected' : '' ?>>BERFUNGSI</option>
                                                        <option value="RUSAK" <?= (strtoupper(trim($row['KondisiRelay'] ?? '')) === 'RUSAK') ? 'selected' : '' ?>>RUSAK</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label text-uppercase text-muted fw-bold small mb-1">FOTO PASANG (UPDATE)</label>
                                                    <input type="file" name="foto_pasang" class="form-control rounded-3" accept="image/*">
                                                    
                                                    <?php if (!empty($row['FotoPasang'])): ?>
                                                        <div class="mt-2">
                                                            <span class="d-block small text-muted mb-1">Foto Tersimpan:</span>
                                                            <img src="data:image/jpeg;base64,<?= base64_encode($row['FotoPasang']) ?>" alt="Foto Pasang" class="img-thumbnail rounded-3 shadow-sm" style="max-height: 110px; object-fit: cover;">
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="d-block small text-muted fst-italic mt-1">Belum ada foto diunggah.</span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label text-uppercase text-muted fw-bold small mb-1">STATUS DATA *</label>
                                                    <select name="status_data" class="form-select rounded-3" required>
                                                        <option value="AKTIF" <?= (strtoupper(trim($row['StatusData'] ?? 'AKTIF')) === 'AKTIF') ? 'selected' : '' ?>>AKTIF</option>
                                                        <option value="TIDAK" <?= (strtoupper(trim($row['StatusData'] ?? '')) === 'TIDAK' || strtoupper(trim($row['StatusData'] ?? '')) === 'TIDAK AKTIF') ? 'selected' : '' ?>>TIDAK AKTIF</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="modal-footer border-top p-3 justify-content-end gap-2">
                                            <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary rounded-3">Simpan Perubahan</button>
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

<!-- Modal Tambah Pemeliharaan -->
<div class="modal fade" id="modalTambahPemeliharaan" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark">Form Order Pemeliharaan Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4 text-start">
                    <input type="hidden" name="action" value="tambah_pemeliharaan">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">NO FORM ORDER REF</label>
                            <input type="text" name="no_form_order" class="form-control rounded-3" placeholder="Contoh: ORD-202610-001">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">TANGGAL PEMELIHARAAN *</label>
                            <input type="date" name="tgl_pemeliharaan" class="form-control rounded-3" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">KODE PETUGAS *</label>
                            <input type="text" name="kd_petugas" class="form-control rounded-3" placeholder="Contoh: IPM.001" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">NO REF PERANGKAT *</label>
                            <input type="text" name="no_ref" class="form-control rounded-3" placeholder="Contoh: S41-001" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">ID PELANGGAN *</label>
                            <input type="text" name="id_pel" class="form-control rounded-3" placeholder="560000000001" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">SIM ID / NO AKUN</label>
                            <select name="sim_id" class="form-select rounded-3 font-monospace select-scrollable">
                                <option value="">-- Tanpa SIM Card --</option>
                                <?php foreach ($list_sim_pem as $s): ?>
                                    <option value="<?= htmlspecialchars($s['SimId']); ?>">
                                        <?= htmlspecialchars($s['SimId']); ?><?= !empty($s['NomorAkun']) ? ' (' . htmlspecialchars($s['NomorAkun']) . ')' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">IMEI MODEM</label>
                            <select name="imei_modem" class="form-select rounded-3 font-monospace select-scrollable">
                                <option value="">-- Tanpa Modem --</option>
                                <?php if (!empty($list_modem_pem)): ?>
                                    <?php foreach ($list_modem_pem as $m): ?>
                                        <option value="<?= htmlspecialchars($m['ImeiModem']); ?>">
                                            <?= htmlspecialchars($m['ImeiModem']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">LONGITUDE (GPS)</label>
                            <input type="text" name="longitude" class="form-control rounded-3 font-monospace" placeholder="Contoh: 106.84513">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">LATITUDE (GPS)</label>
                            <input type="text" name="latitude" class="form-control rounded-3 font-monospace" placeholder="Contoh: -6.2088">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">POSISI PASANG *</label>
                            <select name="posisi_pasang" class="form-select rounded-3">
                                <option value="PANGKAL">PANGKAL</option>
                                <option value="UJUNGBERIKUTNYA">UJUNG BERIKUTNYA</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">KONDISI RELAY *</label>
                            <select name="kondisi_relay" class="form-select rounded-3" required>
                                <option value="BERFUNGSI">BERFUNGSI</option>
                                <option value="RUSAK">RUSAK</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">FOTO PASANG (BLOB)</label>
                            <input type="file" name="foto_pasang" class="form-control rounded-3" accept="image/*">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">STATUS DATA *</label>
                            <select name="status_data" class="form-select rounded-3" required>
                                <option value="AKTIF">AKTIF</option>
                                <option value="TIDAK">TIDAK AKTIF</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top p-3 justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3">Simpan Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById("searchTable");
    if (searchInput) {
        searchInput.addEventListener("keyup", function() {
            const filter = searchInput.value.toLowerCase();
            const rows = document.querySelectorAll("#tablePemeliharaan tbody tr");
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? "" : "none";
            });
        });
    }
});
</script>