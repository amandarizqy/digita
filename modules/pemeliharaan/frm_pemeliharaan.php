<?php
$pesan_sukses = '';
$pesan_error  = '';

// 1. Fetch Master Data Lengkap dari Database
$list_barang_full = [];
$list_ap          = [];
$list_idpel_pem   = [];
$list_sim_card    = [];
$list_modem       = [];

try {
    // Master Perangkat / Barang
    $stmt_brg = $conn->query("SELECT NoRef, SimId, ImeiModem 
                              FROM master_barang 
                              WHERE StatusData = 'AKTIF' AND NoRef IS NOT NULL AND NoRef != '' 
                              ORDER BY NoRef ASC");
    $list_barang_full = $stmt_brg->fetchAll(PDO::FETCH_ASSOC);

    // Master AP
    $stmt_ap = $conn->query("SELECT UnitAp, SingkatanNama, NamaUnit 
                             FROM master_ap 
                             WHERE StatusData = 'AKTIF' 
                             ORDER BY UnitAp ASC");
    $list_ap = $stmt_ap->fetchAll(PDO::FETCH_ASSOC);

    // ID Pelanggan
    $stmt_idpel = $conn->query("SELECT DISTINCT IdPel 
                                FROM master_barang 
                                WHERE StatusData = 'AKTIF' AND IdPel IS NOT NULL AND IdPel != '' 
                                ORDER BY IdPel ASC");
    $list_idpel_pem = $stmt_idpel->fetchAll(PDO::FETCH_ASSOC);

    // Master SIM Card (Untuk Mutasi)
    $stmt_sim = $conn->query("SELECT SimId, NoKartu FROM master_nomor WHERE StatusData = 'AKTIF' ORDER BY SimId ASC");
    $list_sim_card = $stmt_sim->fetchAll(PDO::FETCH_ASSOC);

    // Master Modem (Untuk Mutasi)
    $stmt_modem = $conn->query("SELECT ImeiModem FROM master_modem WHERE StatusData = 'AKTIF' ORDER BY ImeiModem ASC");
    $list_modem = $stmt_modem->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Safe Fallback
}

// 2. Handling Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    $input_petugas = !empty($_POST['kd_petugas']) ? strtoupper(trim($_POST['kd_petugas'])) : 'IPM.001';
    $input_relay   = !empty($_POST['kondisi_relay']) ? strtoupper(trim($_POST['kondisi_relay'])) : 'BERFUNGSI';
    $input_status  = !empty($_POST['status_data']) ? strtoupper(trim($_POST['status_data'])) : 'AKTIF';
    $input_posisi  = !empty($_POST['posisi_pasang']) ? strtoupper(trim($_POST['posisi_pasang'])) : 'PANGKAL';
    $input_akun    = !empty($_SESSION['NamaAkun']) ? substr(trim($_SESSION['NamaAkun']), 0, 20) : 'alam.setiawan';

    // A. TAMBAH ORDER PEMELIHARAAN BARU
    if ($_POST['action'] === 'tambah_pemeliharaan') {
        try {
            $unit_ap  = !empty($_POST['unit_ap']) ? substr($_POST['unit_ap'], 0, 5) : '56610';
            $unit_up  = !empty($_POST['unit_up']) ? substr($_POST['unit_up'], 0, 5) : '56610';
            $unit_upi = !empty($_POST['unit_upi']) ? substr($_POST['unit_upi'], 0, 2) : '56';

            $no_form_order = substr($unit_up . date('mYdHis'), 0, 20);

            // Upload Foto Pasang & Foto Barang (BLOB)
            $foto_pasang = null;
            if (isset($_FILES['foto_pasang']) && $_FILES['foto_pasang']['error'] === UPLOAD_ERR_OK) {
                $foto_pasang = file_get_contents($_FILES['foto_pasang']['tmp_name']);
            }

            $foto_barang = null;
            if (isset($_FILES['foto_barang']) && $_FILES['foto_barang']['error'] === UPLOAD_ERR_OK) {
                $foto_barang = file_get_contents($_FILES['foto_barang']['tmp_name']);
            }

            $arr_no_ref = $_POST['no_ref'] ?? [];
            $arr_sim_id = $_POST['sim_id'] ?? [];
            $arr_imei   = $_POST['imei_modem'] ?? [];
            $id_pel_val = !empty($_POST['id_pel']) ? substr($_POST['id_pel'], 0, 12) : null;

            $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
            $conn->beginTransaction();

            // Insert Header (frm_order_har)
            $stmt_hdr_har = $conn->prepare("INSERT INTO frm_order_har 
                (NoFormOrder, TglOrder, KdPetugas, NamaAkun, UnitUp, UnitAp, UnitUpi, StatusData, WaktuData) 
                VALUES (:no_order, :tgl, :kd_petugas, :nama_akun, :up, :ap, :upi, :status_data, NOW())");

            $stmt_hdr_har->execute([
                ':no_order'    => $no_form_order,
                ':tgl'         => $_POST['tgl_pemeliharaan'],
                ':kd_petugas'  => $input_petugas,
                ':nama_akun'   => $input_akun,
                ':up'          => $unit_up,
                ':ap'          => $unit_ap,
                ':upi'         => $unit_upi,
                ':status_data' => $input_status
            ]);

            // Insert Detail & Fisik
            $stmt_dtl_har = $conn->prepare("INSERT INTO frm_order_har_detil 
                (NoFormOrder, NoRef, SimId, ImeiModem, IdPel) 
                VALUES (:no_order, :no_ref, :sim_id, :imei, :id_pel)");

            $stmt_pemeliharaan = $conn->prepare("INSERT INTO frm_pemeliharaan 
                (NoFormPemeliharaan, NoFormOrder, TglPemeliharaan, KdPetugas, NamaAkun, NoRef, SimId, ImeiModem, IdPel, Longitude, Latitude, PosisiPasang, FotoPasang, FotoBarang, KondisiRelay, UnitUp, UnitAp, UnitUpi, StatusData, WaktuData) 
                VALUES (:no_form, :no_order, :tgl, :kd_petugas, :nama_akun, :no_ref, :sim_id, :imei, :id_pel, :long, :lat, :posisi, :foto_p, :foto_b, :relay, :up, :ap, :upi, :status_data, NOW())");

            $item_tersimpan = 0;
            foreach ($arr_no_ref as $index => $no_ref_val) {
                $clean_ref = trim($no_ref_val);
                if (empty($clean_ref)) continue;

                $sim_id_val  = !empty($arr_sim_id[$index]) ? substr($arr_sim_id[$index], 0, 25) : null;
                $imei_val    = !empty($arr_imei[$index]) ? substr($arr_imei[$index], 0, 30) : null;
                $no_form_pem = substr($no_form_order . sprintf("%02d", $index + 1), 0, 20);

                $stmt_dtl_har->execute([
                    ':no_order' => $no_form_order,
                    ':no_ref'   => substr($clean_ref, 0, 30),
                    ':sim_id'   => $sim_id_val,
                    ':imei'     => $imei_val,
                    ':id_pel'   => $id_pel_val
                ]);

                $stmt_pemeliharaan->execute([
                    ':no_form'      => $no_form_pem,
                    ':no_order'     => $no_form_order,
                    ':tgl'          => $_POST['tgl_pemeliharaan'],
                    ':kd_petugas'   => $input_petugas,
                    ':nama_akun'    => $input_akun,
                    ':no_ref'       => substr($clean_ref, 0, 30),
                    ':sim_id'       => $sim_id_val,
                    ':imei'         => $imei_val,
                    ':id_pel'       => $id_pel_val,
                    ':long'         => !empty($_POST['longitude']) ? substr($_POST['longitude'], 0, 50) : null,
                    ':lat'          => !empty($_POST['latitude']) ? substr($_POST['latitude'], 0, 50) : null,
                    ':posisi'       => $input_posisi,
                    ':foto_p'       => $foto_pasang,
                    ':foto_b'       => $foto_barang,
                    ':relay'        => $input_relay,
                    ':up'           => $unit_up,
                    ':ap'           => $unit_ap,
                    ':upi'          => $unit_upi,
                    ':status_data'  => $input_status
                ]);
                $item_tersimpan++;
            }

            $conn->commit();
            $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
            $pesan_sukses = "Berhasil menyimpan order ($no_form_order) dan $item_tersimpan item ke database!";
        } catch (PDOException $e) {
            $conn->rollBack();
            $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
            $pesan_error = "Gagal menyimpan data: " . $e->getMessage();
        }
    }

    // B. EDIT PEMELIHARAAN
    if ($_POST['action'] === 'edit_pemeliharaan') {
        try {
            $no_form_pem = $_POST['no_form_pemeliharaan'];
            
            $sql_update = "UPDATE frm_pemeliharaan SET 
                           TglPemeliharaan = :tgl,
                           KdPetugas = :kd_petugas,
                           Longitude = :long,
                           Latitude = :lat,
                           PosisiPasang = :posisi,
                           KondisiRelay = :relay,
                           StatusData = :status";

            $params = [
                ':tgl'        => $_POST['tgl_pemeliharaan'],
                ':kd_petugas' => $input_petugas,
                ':long'       => !empty($_POST['longitude']) ? $_POST['longitude'] : null,
                ':lat'        => !empty($_POST['latitude']) ? $_POST['latitude'] : null,
                ':posisi'     => $input_posisi,
                ':relay'      => $input_relay,
                ':status'     => $input_status,
                ':no_form'    => $no_form_pem
            ];

            if (isset($_FILES['foto_pasang']) && $_FILES['foto_pasang']['error'] === UPLOAD_ERR_OK) {
                $sql_update .= ", FotoPasang = :foto_p";
                $params[':foto_p'] = file_get_contents($_FILES['foto_pasang']['tmp_name']);
            }
            if (isset($_FILES['foto_barang']) && $_FILES['foto_barang']['error'] === UPLOAD_ERR_OK) {
                $sql_update .= ", FotoBarang = :foto_b";
                $params[':foto_b'] = file_get_contents($_FILES['foto_barang']['tmp_name']);
            }

            $sql_update .= " WHERE NoFormPemeliharaan = :no_form";

            $stmt_edit = $conn->prepare($sql_update);
            $stmt_edit->execute($params);

            $pesan_sukses = "Berhasil memperbarui data pemeliharaan ($no_form_pem)!";
        } catch (PDOException $e) {
            $pesan_error = "Gagal memperbarui data: " . $e->getMessage();
        }
    }

    // C. PROSES MUTASI PEMELIHARAAN (SIM CARD / IMEI MODEM)
    if ($_POST['action'] === 'simpan_mutasi') {
        try {
            $no_form_pem = $_POST['no_form_pemeliharaan'];
            $sim_baru    = !empty($_POST['sim_id_baru']) ? $_POST['sim_id_baru'] : null;
            $imei_baru   = !empty($_POST['imei_baru']) ? $_POST['imei_baru'] : null;

            $conn->beginTransaction();

            $sql_mutasi = "UPDATE frm_pemeliharaan SET ";
            $updates = [];
            $params = [':no_form' => $no_form_pem];

            if (!empty($sim_baru)) {
                $updates[] = "SimId = :sim_baru";
                $params[':sim_baru'] = $sim_baru;
            }
            if (!empty($imei_baru)) {
                $updates[] = "ImeiModem = :imei_baru";
                $params[':imei_baru'] = $imei_baru;
            }

            if (!empty($updates)) {
                $sql_mutasi .= implode(", ", $updates) . " WHERE NoFormPemeliharaan = :no_form";
                $stmt_mut = $conn->prepare($sql_mutasi);
                $stmt_mut->execute($params);
            }

            $conn->commit();
            $pesan_sukses = "Berhasil melakukan mutasi perangkat untuk No Form: $no_form_pem!";
        } catch (PDOException $e) {
            $conn->rollBack();
            $pesan_error = "Gagal memproses mutasi: " . $e->getMessage();
        }
    }
}

// 3. Fetch Data Utama
$data_pemeliharaan = [];
try {
    $sql = "SELECT p.* FROM frm_pemeliharaan p ORDER BY p.WaktuData DESC";
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

// Menentukan Sub-Halaman dari URL Bawaan DIGITA
$sub_page = $_GET['sub'] ?? 'order_pemeliharaan';
?>

<!-- LIBRARY CSS SELECT2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    .table-compact thead th {
        font-size: 0.75rem !important;
        font-weight: 700 !important;
        color: #6c757d !important;
        padding: 0.6rem 0.35rem !important;
    }
    .table-compact tbody td {
        padding: 0.5rem 0.35rem !important;
        font-size: 0.81rem !important;
        word-break: break-word !important;
    }
    .table-compact .badge {
        font-size: 0.7rem !important;
        padding: 0.3em 0.45em !important;
    }
    .preview-img-box {
        width: 100%;
        height: 220px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #dee2e6;
        background-color: #f8f9fa;
    }
    .select2-container--open {
        z-index: 99999 !important;
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

<?php if ($sub_page === 'mutasi_pemeliharaan'): ?>

    <!-- SUB TAMPILAN 1: TABEL MUTASI PEMELIHARAAN -->
    <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
        <div class="d-flex justify-content-between align-items-center mb-3 px-2 pt-2">
            <h5 class="fw-bold text-primary mb-0">Riwayat Mutasi Pemeliharaan</h5>
            <div class="input-group style-search" style="width: 240px;">
                <span class="input-group-text bg-light border-end-0 rounded-start-3 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="searchTableMutasi" class="form-control bg-light border-start-0 rounded-end-3 text-muted small" placeholder="Cari data mutasi...">
            </div>
        </div>

        <div class="w-100">
            <table class="table table-hover align-middle mb-0 table-compact w-100">
                <thead class="table-light border-top border-bottom">
                    <tr>
                        <th class="ps-2 text-center" style="width: 4%;">#</th>
                        <th style="width: 22%;">NO FORM PEMELIHARAAN</th>
                        <th style="width: 20%;">IDPEL / NO REF</th>
                        <th style="width: 20%;">SIM ID TERPASANG</th>
                        <th style="width: 18%;">IMEI MODEM TERPASANG</th>
                        <th style="width: 8%;">RELAY</th>
                        <th class="pe-2 text-center" style="width: 8%;">AKSI</th>
                    </tr>
                </thead>
                <tbody class="fw-medium text-dark">
                    <?php if (empty($data_pemeliharaan)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data pemeliharaan untuk dimutasi.</td></tr>
                    <?php else: ?>
                        <?php foreach ($data_pemeliharaan as $idx => $row): ?>
                            <tr class="border-bottom">
                                <td class="ps-2 text-center text-muted small"><?= $idx + 1 ?></td>
                                <td class="fw-bold text-dark font-monospace"><?= htmlspecialchars($row['NoFormPemeliharaan']) ?></td>
                                <td>IDPEL: <?= htmlspecialchars($row['IdPel']) ?><br><small class="text-muted">REF: <?= htmlspecialchars($row['NoRef']) ?></small></td>
                                <td><span class="badge bg-info bg-opacity-10 text-info font-monospace"><?= htmlspecialchars($row['SimId'] ?? '-') ?></span></td>
                                <td class="font-monospace"><?= htmlspecialchars($row['ImeiModem'] ?? '-') ?></td>
                                <td><span class="badge bg-success-subtle text-success"><?= htmlspecialchars($row['KondisiRelay']) ?></span></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-3 btn-buka-mutasi"
                                            data-noform="<?= htmlspecialchars($row['NoFormPemeliharaan']) ?>"
                                            data-sim="<?= htmlspecialchars($row['SimId'] ?? '-') ?>"
                                            data-imei="<?= htmlspecialchars($row['ImeiModem'] ?? '-') ?>">
                                        <i class="bi bi-arrow-left-right me-1"></i> Mutasi
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($sub_page === 'monitoring_order'): ?>

    <!-- SUB TAMPILAN 2: MONITORING ORDER PEMELIHARAAN -->
    <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
        <div class="d-flex justify-content-between align-items-center mb-3 px-2 pt-2">
            <div>
                <h5 class="fw-bold text-primary mb-1">Monitoring Laporan Order Pemeliharaan</h5>
                <p class="text-muted small mb-0">Rekapitulasi status pengerjaan berita acara pemeliharaan.</p>
            </div>
            <div class="input-group style-search" style="width: 240px;">
                <span class="input-group-text bg-light border-end-0 rounded-start-3 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="searchTableMonitoring" class="form-control bg-light border-start-0 rounded-end-3 text-muted small" placeholder="Cari data monitoring...">
            </div>
        </div>

        <div class="w-100">
            <table class="table table-hover align-middle mb-0 table-compact w-100">
                <thead class="table-light border-top border-bottom">
                    <tr>
                        <th class="ps-2 text-center" style="width: 3%;">#</th>
                        <th style="width: 18%;">NO FORM PEMELIHARAAN</th>
                        <th style="width: 12%;">TGL PEMELIHARAAN</th>
                        <th style="width: 16%;">IDPEL / NO REF</th>
                        <th style="width: 15%;">SIM ID / NO AKUN</th>
                        <th style="width: 13%;">IMEI MODEM</th>
                        <th style="width: 10%;">KONDISI RELAY</th>
                        <th style="width: 8%;">STATUS DATA</th>
                        <th style="width: 11%;">WAKTU DATA</th>
                        <th class="pe-2 text-center" style="width: 6%;">FOTO</th>
                    </tr>
                </thead>
                <tbody class="fw-medium text-dark">
                    <?php if (empty($data_pemeliharaan)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">Belum ada data monitoring pemeliharaan.</td></tr>
                    <?php else: ?>
                        <?php foreach ($data_pemeliharaan as $idx => $row): ?>
                            <?php 
                                $img_pasang_base64 = !empty($row['FotoPasang']) ? 'data:image/jpeg;base64,' . base64_encode($row['FotoPasang']) : '';
                                $img_barang_base64 = !empty($row['FotoBarang']) ? 'data:image/jpeg;base64,' . base64_encode($row['FotoBarang']) : '';
                            ?>
                            <tr class="border-bottom">
                                <td class="ps-2 text-center text-muted small"><?= $idx + 1 ?></td>
                                <td class="fw-bold text-dark font-monospace"><?= htmlspecialchars($row['NoFormPemeliharaan']) ?></td>
                                <td><?= htmlspecialchars($row['TglPemeliharaan']) ?></td>
                                <td>IDPEL: <?= htmlspecialchars($row['IdPel']) ?><br><small class="text-muted">REF: <?= htmlspecialchars($row['NoRef']) ?></small></td>
                                <td>
                                    <span class="badge bg-info bg-opacity-10 text-info font-monospace d-block text-truncate mb-1"><?= htmlspecialchars($row['SimId'] ?? '-') ?></span>
                                    <small class="text-muted font-monospace"><?= htmlspecialchars($row['NamaAkun'] ?? '-') ?></small>
                                </td>
                                <td class="font-monospace"><?= htmlspecialchars($row['ImeiModem'] ?? '-') ?></td>
                                <td><span class="badge bg-success-subtle text-success"><?= htmlspecialchars($row['KondisiRelay']) ?></span></td>
                                <td><span class="badge bg-success-subtle text-success">TERKIRIM</span></td>
                                <td><small class="text-muted font-monospace"><?= htmlspecialchars($row['WaktuData']) ?></small></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-light text-primary border-0 rounded-circle btn-foto-monitoring-custom" 
                                            data-noform="<?= htmlspecialchars($row['NoFormPemeliharaan']) ?>"
                                            data-fotopasang="<?= $img_pasang_base64 ?>"
                                            data-fotobarang="<?= $img_barang_base64 ?>"
                                            title="Lihat Foto Dokumentasi">
                                        <i class="bi bi-image fs-5"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>

    <!-- SUB TAMPILAN 3: ORDER PEMELIHARAAN (DEFAULT) -->
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

    <!-- Kartu Tabel Order Pemeliharaan -->
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

        <div class="w-100">
            <table class="table table-hover align-middle mb-0 table-compact w-100" id="tablePemeliharaan">
                <thead class="table-light border-top border-bottom">
                    <tr>
                        <th class="ps-2 text-center" style="width: 3%;">#</th>
                        <th style="width: 15%;">NO FORM PEMELIHARAAN</th>
                        <th style="width: 10%;">TGL / PETUGAS</th>
                        <th style="width: 13%;">IDPEL / NO REF</th>
                        <th style="width: 13%;">SIM ID</th>
                        <th style="width: 12%;">IMEI MODEM</th>
                        <th style="width: 12%;">KOORDINAT GPS</th>
                        <th style="width: 10%;">KONDISI RELAY</th>
                        <th style="width: 7%;">STATUS</th>
                        <th class="pe-2 text-center" style="width: 8%;">AKSI</th>
                    </tr>
                </thead>
                <tbody class="fw-medium text-dark">
                    <?php if (empty($data_pemeliharaan)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">Belum ada data order pemeliharaan.</td></tr>
                    <?php else: ?>
                        <?php foreach ($data_pemeliharaan as $idx => $row): ?>
                            <?php 
                                $img_pasang_base64 = !empty($row['FotoPasang']) ? 'data:image/jpeg;base64,' . base64_encode($row['FotoPasang']) : '';
                                $img_barang_base64 = !empty($row['FotoBarang']) ? 'data:image/jpeg;base64,' . base64_encode($row['FotoBarang']) : '';
                            ?>
                            <tr class="border-bottom">
                                <td class="ps-2 text-center text-muted small"><?= $idx + 1 ?></td>
                                <td class="fw-bold text-dark font-monospace"><?= htmlspecialchars($row['NoFormPemeliharaan']) ?></td>
                                <td><?= htmlspecialchars($row['TglPemeliharaan']) ?><br><small class="text-muted"><?= htmlspecialchars($row['KdPetugas']) ?></small></td>
                                <td>IDPEL: <?= htmlspecialchars($row['IdPel']) ?><br><small class="text-muted">REF: <?= htmlspecialchars($row['NoRef']) ?></small></td>
                                <td><span class="badge bg-info bg-opacity-10 text-info font-monospace"><?= htmlspecialchars($row['SimId'] ?? '-') ?></span></td>
                                <td class="font-monospace"><?= htmlspecialchars($row['ImeiModem'] ?? '-') ?></td>
                                <td class="font-monospace small"><?= htmlspecialchars($row['Latitude'] ?? '') ?>, <?= htmlspecialchars($row['Longitude'] ?? '') ?></td>
                                <td><span class="badge bg-success-subtle text-success"><?= htmlspecialchars($row['KondisiRelay']) ?></span></td>
                                <td><span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($row['StatusData']) ?></span></td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-info btn-detail-pemeliharaan" 
                                                data-noform="<?= htmlspecialchars($row['NoFormPemeliharaan']) ?>"
                                                data-noorder="<?= htmlspecialchars($row['NoFormOrder']) ?>"
                                                data-tgl="<?= htmlspecialchars($row['TglPemeliharaan']) ?>"
                                                data-petugas="<?= htmlspecialchars($row['KdPetugas']) ?>"
                                                data-idpel="<?= htmlspecialchars($row['IdPel']) ?>"
                                                data-noref="<?= htmlspecialchars($row['NoRef']) ?>"
                                                data-sim="<?= htmlspecialchars($row['SimId'] ?? '-') ?>"
                                                data-imei="<?= htmlspecialchars($row['ImeiModem'] ?? '-') ?>"
                                                data-lat="<?= htmlspecialchars($row['Latitude'] ?? '-') ?>"
                                                data-long="<?= htmlspecialchars($row['Longitude'] ?? '-') ?>"
                                                data-posisi="<?= htmlspecialchars($row['PosisiPasang']) ?>"
                                                data-relay="<?= htmlspecialchars($row['KondisiRelay']) ?>"
                                                data-fotopasang="<?= $img_pasang_base64 ?>"
                                                data-fotobarang="<?= $img_barang_base64 ?>"
                                                title="Lihat Detail & Foto">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-edit-pemeliharaan" 
                                                data-noform="<?= htmlspecialchars($row['NoFormPemeliharaan']) ?>"
                                                data-tgl="<?= htmlspecialchars($row['TglPemeliharaan']) ?>"
                                                data-petugas="<?= htmlspecialchars($row['KdPetugas']) ?>"
                                                data-lat="<?= htmlspecialchars($row['Latitude'] ?? '') ?>"
                                                data-long="<?= htmlspecialchars($row['Longitude'] ?? '') ?>"
                                                data-posisi="<?= htmlspecialchars($row['PosisiPasang']) ?>"
                                                data-relay="<?= htmlspecialchars($row['KondisiRelay']) ?>"
                                                data-status="<?= htmlspecialchars($row['StatusData']) ?>"
                                                title="Edit Data">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<!-- MODAL DUAL FOTO MONITORING UNIK (TERBARU & ANTI-BENTROK) -->
<div class="modal fade" id="modalMonitoringFotoDual" tabindex="-1" aria-hidden="true" style="z-index: 100000 !important;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div>
                    <h5 class="modal-title fw-bold text-dark mb-1">Foto Hasil Pemeliharaan (Dokumentasi Lengkap)</h5>
                    <small class="text-muted font-monospace" id="monNoFormPemeliharaanDual"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <!-- Foto Pasang -->
                    <div class="col-md-6 text-center">
                        <label class="form-label text-muted fw-bold small text-uppercase mb-2"><i class="bi bi-camera me-1"></i>Foto Pemasangan / Lapangan</label>
                        <div id="monBoxFotoPasangDual">
                            <img id="monImgFotoPasangDual" src="" class="preview-img-box d-none" alt="Foto Pasang">
                            <div id="monNoFotoPasangDual" class="preview-img-box d-flex align-items-center justify-content-center text-muted">
                                <i class="bi bi-image me-2"></i>Tidak ada foto pemasangan
                            </div>
                        </div>
                    </div>
                    <!-- Foto Fisik Barang -->
                    <div class="col-md-6 text-center">
                        <label class="form-label text-muted fw-bold small text-uppercase mb-2"><i class="bi bi-box-seam me-1"></i>Foto Fisik Perangkat / Barang</label>
                        <div id="monBoxFotoBarangDual">
                            <img id="monImgFotoBarangDual" src="" class="preview-img-box d-none" alt="Foto Barang">
                            <div id="monNoFotoBarangDual" class="preview-img-box d-flex align-items-center justify-content-center text-muted">
                                <i class="bi bi-image me-2"></i>Tidak ada foto barang
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top p-3 justify-content-end">
                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL MUTASI / GANTI PERANGKAT -->
<div class="modal fade" id="modalMutasi" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark">Mutasi / Ganti Perangkat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4 text-start">
                    <input type="hidden" name="action" value="simpan_mutasi">
                    <input type="hidden" name="no_form_pemeliharaan" id="mutasiNoForm">

                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">NO FORM PEMELIHARAAN</label>
                        <input type="text" id="displayMutasiNoForm" class="form-control rounded-3 bg-light font-monospace fw-bold" readonly>
                    </div>

                    <div class="card bg-light border-0 p-3 mb-3 rounded-3">
                        <div class="text-uppercase text-muted fw-bold small mb-2"><i class="bi bi-info-circle me-1"></i> PERANGKAT TERPASANG SAAT INI</div>
                        <div class="row small font-monospace">
                            <div class="col-6">SIM ID: <br><strong id="displayMutasiSim" class="text-primary"></strong></div>
                            <div class="col-6">IMEI Modem: <br><strong id="displayMutasiImei" class="text-secondary"></strong></div>
                        </div>
                    </div>

                    <!-- UPDATE SIM CARD BARU (SEARCHABLE SELECT2) -->
                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">UPDATE SIM CARD BARU</label>
                        <select name="sim_id_baru" id="selectSimBaru" class="form-select select2-mutasi" style="width: 100%;">
                            <option value="">-- Tanpa SIM Card (Sama / Lepas) --</option>
                            <?php foreach ($list_sim_card as $sim): ?>
                                <option value="<?= htmlspecialchars($sim['SimId']); ?>">
                                    <?= htmlspecialchars($sim['SimId']); ?> (<?= htmlspecialchars($sim['NoKartu'] ?? ''); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- UPDATE IMEI MODEM BARU (SEARCHABLE SELECT2) -->
                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">UPDATE IMEI MODEM BARU</label>
                        <select name="imei_baru" id="selectImeiBaru" class="form-select select2-mutasi" style="width: 100%;">
                            <option value="">-- Tanpa Modem (Sama / Lepas) --</option>
                            <?php foreach ($list_modem as $modem): ?>
                                <option value="<?= htmlspecialchars($modem['ImeiModem']); ?>">
                                    <?= htmlspecialchars($modem['ImeiModem']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-footer border-top p-3 justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3">Simpan Mutasi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL LIHAT DETAIL & PREVIEW FOTO BLOB (TAB ORDER) -->
<div class="modal fade" id="modalDetailPemeliharaan" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-info-circle text-primary me-2"></i>Detail Pemeliharaan Perangkat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-4 bg-light p-3 rounded-3 border">
                    <div class="col-md-6">
                        <small class="text-muted d-block fw-bold">NO FORM PEMELIHARAAN</small>
                        <span id="detNoForm" class="fw-bold font-monospace text-primary"></span>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block fw-bold">NO FORM ORDER</small>
                        <span id="detNoOrder" class="fw-bold font-monospace text-secondary"></span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block fw-bold">ID PELANGGAN</small>
                        <span id="detIdPel" class="fw-semibold"></span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block fw-bold">NO REF PERANGKAT</small>
                        <span id="detNoRef" class="fw-semibold"></span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block fw-bold">SIM ID / IMEI</small>
                        <span id="detSimImei" class="font-monospace small"></span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block fw-bold">KONDISI RELAY</small>
                        <span id="detRelay" class="badge bg-success"></span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block fw-bold">POSISI PASANG</small>
                        <span id="detPosisi" class="fw-semibold"></span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block fw-bold">KOORDINAT GPS</small>
                        <span id="detGps" class="font-monospace small"></span>
                    </div>
                </div>

                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-images me-2"></i>Dokumentasi Foto Perangkat</h6>
                <div class="row g-3">
                    <div class="col-md-6 text-center">
                        <label class="form-label text-muted fw-bold small">FOTO PASANG</label>
                        <div id="boxFotoPasang">
                            <img id="imgFotoPasang" src="" class="preview-img-box d-none" alt="Foto Pasang">
                            <div id="noFotoPasang" class="preview-img-box d-flex align-items-center justify-content-center text-muted">
                                <i class="bi bi-image me-2"></i>Tidak ada foto pasang
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 text-center">
                        <label class="form-label text-muted fw-bold small">FOTO BARANG</label>
                        <div id="boxFotoBarang">
                            <img id="imgFotoBarang" src="" class="preview-img-box d-none" alt="Foto Barang">
                            <div id="noFotoBarang" class="preview-img-box d-flex align-items-center justify-content-center text-muted">
                                <i class="bi bi-image me-2"></i>Tidak ada foto barang
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top p-3">
                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL EDIT PEMELIHARAAN -->
<div class="modal fade" id="modalEditPemeliharaan" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark">Edit Data Pemeliharaan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4 text-start">
                    <input type="hidden" name="action" value="edit_pemeliharaan">
                    <input type="hidden" name="no_form_pemeliharaan" id="editNoFormPemeliharaan">

                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">TANGGAL PEMELIHARAAN *</label>
                        <input type="date" name="tgl_pemeliharaan" id="editTglPemeliharaan" class="form-control rounded-3" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">KODE PETUGAS *</label>
                        <select name="kd_petugas" id="editKdPetugas" class="form-select rounded-3" required>
                            <option value="IPM.001">IPM.001</option>
                            <option value="IPM.002">IPM.002</option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">LONGITUDE</label>
                            <input type="text" name="longitude" id="editLongitude" class="form-control rounded-3 font-monospace">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">LATITUDE</label>
                            <input type="text" name="latitude" id="editLatitude" class="form-control rounded-3 font-monospace">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">POSISI PASANG *</label>
                            <select name="posisi_pasang" id="editPosisiPasang" class="form-select rounded-3">
                                <option value="PANGKAL">PANGKAL</option>
                                <option value="TENGAH">TENGAH</option>
                                <option value="UJUNG">UJUNG</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">KONDISI RELAY *</label>
                            <select name="kondisi_relay" id="editKondisiRelay" class="form-select rounded-3" required>
                                <option value="BERFUNGSI">BERFUNGSI</option>
                                <option value="TIDAK BERFUNGSI">TIDAK BERFUNGSI</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">GANTI FOTO PASANG</label>
                            <input type="file" name="foto_pasang" class="form-control rounded-3" accept="image/*">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">GANTI FOTO BARANG</label>
                            <input type="file" name="foto_barang" class="form-control rounded-3" accept="image/*">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">STATUS DATA *</label>
                        <select name="status_data" id="editStatusData" class="form-select rounded-3" required>
                            <option value="AKTIF">AKTIF</option>
                            <option value="TIDAK">TIDAK AKTIF</option>
                        </select>
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

<!-- Modal Tambah Order Pemeliharaan Baru -->
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

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">UNIT UPI</label>
                            <input type="text" class="form-control rounded-3 bg-light text-muted fw-semibold" value="UID BANTEN (56)" readonly>
                            <input type="hidden" name="unit_upi" value="56">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">UNIT AP *</label>
                            <select name="unit_ap" id="selectUnitAp" class="form-select rounded-3" required>
                                <option value="">-- Pilih Unit AP --</option>
                                <?php foreach ($list_ap as $ap): ?>
                                    <option value="<?= htmlspecialchars($ap['UnitAp']); ?>"><?= htmlspecialchars($ap['SingkatanNama']); ?> (<?= htmlspecialchars($ap['UnitAp']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">UNIT UP *</label>
                            <select name="unit_up" id="selectUnitUp" class="form-select rounded-3" required disabled>
                                <option value="">-- Pilih AP Dahulu --</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">TANGGAL PEMELIHARAAN *</label>
                            <input type="date" name="tgl_pemeliharaan" class="form-control rounded-3" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">KODE PETUGAS *</label>
                            <select name="kd_petugas" class="form-select rounded-3" required>
                                <option value="IPM.001">IPM.001</option>
                                <option value="IPM.002">IPM.002</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">ID PELANGGAN *</label>
                            <select name="id_pel" id="selectIdPelTambah" class="form-select select2-searchable" style="width: 100%;" required>
                                <option value="">-- Cari / Ketik ID Pelanggan --</option>
                                <?php foreach ($list_idpel_pem as $pel): ?>
                                    <option value="<?= htmlspecialchars($pel['IdPel']); ?>"><?= htmlspecialchars($pel['IdPel']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-0">DAFTAR PERANGKAT / BARANG (WORK ORDER) *</label>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-3" id="btnTambahBarang">
                                <i class="bi bi-plus-lg me-1"></i> Tambah Barang
                            </button>
                        </div>

                        <div class="table-responsive border rounded-3 p-2 bg-light">
                            <table class="table table-bordered align-middle mb-0 bg-white" id="tableDetailBarang">
                                <thead class="table-light small text-uppercase fw-bold text-muted">
                                    <tr>
                                        <th style="width: 38%;">NO REF PERANGKAT (SEARCHABLE) *</th>
                                        <th style="width: 28%;">SIM ID (AUTO)</th>
                                        <th style="width: 28%;">IMEI MODEM (AUTO)</th>
                                        <th class="text-center" style="width: 6%;">#</th>
                                    </tr>
                                </thead>
                                <tbody id="containerBarang">
                                    <tr class="row-barang">
                                        <td>
                                            <select name="no_ref[]" class="form-select form-select-sm select2-noref select2-searchable" style="width: 100%;" required>
                                                <option value="">-- Cari No Ref Perangkat --</option>
                                                <?php foreach ($list_barang_full as $brg): ?>
                                                    <option value="<?= htmlspecialchars($brg['NoRef']); ?>" 
                                                            data-sim="<?= htmlspecialchars($brg['SimId'] ?? ''); ?>" 
                                                            data-imei="<?= htmlspecialchars($brg['ImeiModem'] ?? ''); ?>">
                                                        <?= htmlspecialchars($brg['NoRef']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="sim_id[]" class="form-control form-control-sm bg-light font-monospace input-simid" placeholder="Auto SIM ID" readonly>
                                        </td>
                                        <td>
                                            <input type="text" name="imei_modem[]" class="form-control form-control-sm bg-light font-monospace input-imei" placeholder="Auto IMEI Modem" readonly>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger p-1 border-0 btn-hapus-baris" disabled>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
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
                                <option value="TENGAH">TENGAH</option>
                                <option value="UJUNG">UJUNG</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">KONDISI RELAY *</label>
                            <select name="kondisi_relay" class="form-select rounded-3" required>
                                <option value="BERFUNGSI">BERFUNGSI</option>
                                <option value="TIDAK BERFUNGSI">TIDAK BERFUNGSI</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">FOTO PASANG (BLOB)</label>
                            <input type="file" name="foto_pasang" class="form-control rounded-3" accept="image/*">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">FOTO BARANG (BLOB)</label>
                            <input type="file" name="foto_barang" class="form-control rounded-3" accept="image/*">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">STATUS DATA *</label>
                        <select name="status_data" class="form-select rounded-3" required>
                            <option value="AKTIF">AKTIF</option>
                            <option value="TIDAK">TIDAK AKTIF</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer border-top p-3 justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3">Simpan Work Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- LIBRARY JS JQUERY & SELECT2 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {

    function initSelect2Elements(container) {
        container.find('.select2-searchable').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#modalTambahPemeliharaan'),
            placeholder: '-- Cari Data --',
            allowClear: true
        });
    }

    // Modal Form Tambah Events
    $('#modalTambahPemeliharaan').on('shown.bs.modal', function () {
        initSelect2Elements($(this));
    });

    // BUKA MODAL MUTASI & INISIALISASI KOTAK PENCARIAN (SELECT2)
    $(document).on('click', '.btn-buka-mutasi', function() {
        var btn = $(this);
        $('#mutasiNoForm').val(btn.data('noform'));
        $('#displayMutasiNoForm').val(btn.data('noform'));
        $('#displayMutasiSim').text(btn.data('sim'));
        $('#displayMutasiImei').text(btn.data('imei'));

        $('#modalMutasi').modal('show');
    });

    $('#modalMutasi').on('shown.bs.modal', function () {
        $('.select2-mutasi').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#modalMutasi'),
            placeholder: '-- Cari atau Ketik Nomor SIM / IMEI --',
            allowClear: true
        });
    });

    // EVENT KLIK TOMBOL FOTO DUAL PADA MONITORING ORDER (TAMPILKAN 2 FOTO ANTI-BENTROK)
    $(document).on('click', '.btn-foto-monitoring-custom', function(e) {
        e.preventDefault();
        var btn = $(this);
        $('#monNoFormPemeliharaanDual').text('No Form: ' + btn.data('noform'));

        var fotoPasang = btn.data('fotopasang');
        if (fotoPasang && fotoPasang !== '') {
            $('#monImgFotoPasangDual').attr('src', fotoPasang).removeClass('d-none');
            $('#monNoFotoPasangDual').addClass('d-none');
        } else {
            $('#monImgFotoPasangDual').addClass('d-none');
            $('#monNoFotoPasangDual').removeClass('d-none');
        }

        var fotoBarang = btn.data('fotobarang');
        if (fotoBarang && fotoBarang !== '') {
            $('#monImgFotoBarangDual').attr('src', fotoBarang).removeClass('d-none');
            $('#monNoFotoBarangDual').addClass('d-none');
        } else {
            $('#monImgFotoBarangDual').addClass('d-none');
            $('#monNoFotoBarangDual').removeClass('d-none');
        }

        var myModal = new bootstrap.Modal(document.getElementById('modalMonitoringFotoDual'));
        myModal.show();
    });

    // AUTO-FILL EVENT: Saat NoRef dipilih, isi otomatis SIM ID dan IMEI Modem
    $(document).on('change', '.select2-noref', function() {
        var selectedOption = $(this).find('option:selected');
        var simVal  = selectedOption.data('sim') || '';
        var imeiVal = selectedOption.data('imei') || '';

        var $row = $(this).closest('tr');
        $row.find('.input-simid').val(simVal);
        $row.find('.input-imei').val(imeiVal);
    });

    // Dynamic Row Multi-Item Barang
    $("#btnTambahBarang").click(function() {
        var $firstRow = $("#containerBarang .row-barang:first");
        $firstRow.find('.select2-searchable').select2('destroy');

        var $newRow = $firstRow.clone();
        $newRow.find('select').val('');
        $newRow.find('input').val('');

        $("#containerBarang").append($newRow);
        initSelect2Elements($('#modalTambahPemeliharaan'));

        $newRow.find('.btn-hapus-baris').prop('disabled', false).click(function() {
            $(this).closest('tr').remove();
        });
    });

    // Cascading Dropdown Unit AP ke Unit UP
    $("#selectUnitAp").change(function() {
        var unitApVal = $(this).val();
        var $selectUp = $("#selectUnitUp");

        $selectUp.html('<option value="">-- Memuat Data UP... --</option>').prop("disabled", true);

        if (unitApVal !== "") {
            fetch('modules/pemeliharaan/get_unit_up.php?unit_ap=' + encodeURIComponent(unitApVal))
                .then(response => response.json())
                .then(data => {
                    $selectUp.html('<option value="">-- Pilih Unit UP --</option>');
                    if (data.length > 0) {
                        $.each(data, function(i, item) {
                            $selectUp.append($('<option>', {
                                value: item.UnitUp,
                                text: item.SingkatanNama + ' (' + item.UnitUp + ')'
                            }));
                        });
                        $selectUp.prop("disabled", false);
                    } else {
                        $selectUp.html('<option value="">-- Tidak Ada Data UP --</option>');
                    }
                })
                .catch(error => {
                    $selectUp.html('<option value="">-- Gagal Memuat Data --</option>');
                });
        } else {
            $selectUp.html('<option value="">-- Pilih AP Dahulu --</option>');
        }
    });

    // EVENT KLIK LIHAT DETAIL & PREVIEW FOTO BLOB (TAB ORDER)
    $(document).on('click', '.btn-detail-pemeliharaan', function() {
        var btn = $(this);
        $('#detNoForm').text(btn.data('noform'));
        $('#detNoOrder').text(btn.data('noorder'));
        $('#detIdPel').text(btn.data('idpel'));
        $('#detNoRef').text(btn.data('noref'));
        $('#detSimImei').text(btn.data('sim') + ' / ' + btn.data('imei'));
        $('#detRelay').text(btn.data('relay'));
        $('#detPosisi').text(btn.data('posisi'));
        $('#detGps').text(btn.data('lat') + ', ' + btn.data('long'));

        var fotoPasang = btn.data('fotopasang');
        if (fotoPasang && fotoPasang !== '') {
            $('#imgFotoPasang').attr('src', fotoPasang).removeClass('d-none');
            $('#noFotoPasang').addClass('d-none');
        } else {
            $('#imgFotoPasang').addClass('d-none');
            $('#noFotoPasang').removeClass('d-none');
        }

        var fotoBarang = btn.data('fotobarang');
        if (fotoBarang && fotoBarang !== '') {
            $('#imgFotoBarang').attr('src', fotoBarang).removeClass('d-none');
            $('#noFotoBarang').addClass('d-none');
        } else {
            $('#imgFotoBarang').addClass('d-none');
            $('#noFotoBarang').removeClass('d-none');
        }

        $('#modalDetailPemeliharaan').modal('show');
    });

    // EVENT KLIK EDIT DATA
    $(document).on('click', '.btn-edit-pemeliharaan', function() {
        var btn = $(this);
        $('#editNoFormPemeliharaan').val(btn.data('noform'));
        $('#editTglPemeliharaan').val(btn.data('tgl'));
        $('#editKdPetugas').val(btn.data('petugas'));
        $('#editLongitude').val(btn.data('long'));
        $('#editLatitude').val(btn.data('lat'));
        $('#editPosisiPasang').val(btn.data('posisi'));
        $('#editKondisiRelay').val(btn.data('relay'));
        $('#editStatusData').val(btn.data('status'));

        $('#modalEditPemeliharaan').modal('show');
    });
});
</script>