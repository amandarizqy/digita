<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    require_once __DIR__ . '/../../config/database.php';
}
require_once __DIR__ . '/../../functions/pemeliharaan_helper.php';

if (!ph_akses_order()) {
    echo '<div class="alert alert-warning">Anda tidak memiliki akses ke Order Pemeliharaan.</div>';
    return;
}

$sub_ini     = 'order_pemeliharaan';
$pesan_error = '';

/* ======================================================================
 * 1. PROSES SIMPAN (POST)
 * ==================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();
    $nama_akun = substr(trim((string) ($_SESSION['NamaAkun'] ?? '')), 0, 20);

    // ---- A. SIMPAN ORDER PERINTAH KERJA ----
    if ($_POST['action'] === 'tambah_order_pk') {
        try {
            $no_form_order = trim((string)($_POST['no_form_order'] ?? ''));
            $tgl_order     = ph_tanggal($_POST['tgl_order'] ?? '');
            $kd_petugas    = $_POST['kd_petugas'] ?? '';
            $id_pels       = (array)($_POST['id_pel_list'] ?? []);

            if (empty($no_form_order)) {
                $no_form_order = 'ORD-HAR-' . date('Ymd') . '-' . rand(1000, 9999);
            }
            if (empty($id_pels)) {
                throw new Exception("Pilih minimal satu ID Pelanggan!");
            }

            $conn->beginTransaction();

            $conn->prepare("INSERT INTO frm_order (NoFormOrder, TglOrder, KdPetugas, NamaAkun, StatusData, WaktuData) VALUES (?, ?, ?, ?, 'AKTIF', NOW())")
                 ->execute([$no_form_order, $tgl_order, $kd_petugas, $nama_akun]);

            $st_pk = $conn->prepare("INSERT INTO frm_pk (NoFpk, TglFpk, JenisPK, IdPel, SimId, ImeiModem, NomorBarang, NamaAkun, StatusData, WaktuData) VALUES (?, ?, 'PEMELIHARAAN', ?, ?, ?, ?, ?, 'AKTIF', NOW())");
            $st_det = $conn->prepare("INSERT INTO frm_order_detil (NoFormOrder, NoFpk) VALUES (?, ?)");

            foreach ($id_pels as $id_pel) {
                $st_b = $conn->prepare("SELECT NoRef, SimId, ImeiModem FROM master_barang WHERE IdPel = ? AND StatusData = 'AKTIF' LIMIT 1");
                $st_b->execute([$id_pel]);
                $b = $st_b->fetch(PDO::FETCH_ASSOC);

                $no_fpk = 'PK-HAR-' . date('Ymd') . '-' . rand(1000, 9999);

                $st_pk->execute([
                    $no_fpk, $tgl_order, $id_pel, 
                    $b['SimId'] ?? null, $b['ImeiModem'] ?? null, $b['NoRef'] ?? null, $nama_akun
                ]);

                $st_det->execute([$no_form_order, $no_fpk]);
            }

            $conn->commit();
            ph_redirect($sub_ini, 'sukses', "Order Perintah Kerja ($no_form_order) berhasil diterbitkan.");
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            ph_redirect($sub_ini, 'gagal', "Gagal simpan Order: " . $e->getMessage());
        }
    }

    // ---- B. SIMPAN PK PEMELIHARAAN (FORMULIR UTAMA) ----
    if ($_POST['action'] === 'tambah_pk_pemeliharaan') {
        try {
            $no_fpk = trim((string)($_POST['no_fpk'] ?? ''));
            $tgl    = ph_tanggal($_POST['tgl_fpk'] ?? '');
            $id_pel = trim((string)($_POST['id_pel'] ?? ''));
            $petugas= $_POST['kd_petugas'] ?? '';
            $posisi = $_POST['posisi_pasang'] ?? 'PANGKAL';
            $relay  = $_POST['kondisi_relay'] ?? 'BERFUNGSI';
            $lat    = $_POST['latitude'] ?? '';
            $long   = $_POST['longitude'] ?? '';

            if (empty($no_fpk)) $no_fpk = 'PK-HAR-' . date('Ymd') . '-' . rand(1000, 9999);
            $no_fba = 'BA-HAR-' . date('Ymd') . '-' . rand(1000, 9999);

            $st_b = $conn->prepare("SELECT NoRef, SimId, ImeiModem FROM master_barang WHERE IdPel = ? AND StatusData = 'AKTIF' LIMIT 1");
            $st_b->execute([$id_pel]);
            $b = $st_b->fetch(PDO::FETCH_ASSOC);

            $conn->beginTransaction();

            $conn->prepare("INSERT INTO frm_pk (NoFpk, TglFpk, JenisPK, IdPel, SimId, ImeiModem, NomorBarang, LongDil, LatDil, NamaAkun, StatusData, WaktuData) VALUES (?, ?, 'PEMELIHARAAN', ?, ?, ?, ?, ?, ?, ?, 'AKTIF', NOW())")
                 ->execute([$no_fpk, $tgl, $id_pel, $b['SimId'] ?? null, $b['ImeiModem'] ?? null, $b['NoRef'] ?? null, $long, $lat, $nama_akun]);

            $conn->prepare("INSERT INTO frm_ba (NoFba, TglFba, JenisPekerjaan, KdPetugas, NoFpk, IdPel, NamaAkun, StatusData, WaktuData) VALUES (?, ?, 'PEMELIHARAAN', ?, ?, ?, ?, 'AKTIF', NOW())")
                 ->execute([$no_fba, $tgl, $petugas, $no_fpk, $id_pel, $nama_akun]);

            $conn->prepare("INSERT INTO frm_ba_detil (NoFba, StatusPekerjaan, NoRef, SimId, ImeiModem, Long, Lat, PosisiPasang, LampuIndikator) VALUES (?, 'PASANG', ?, ?, ?, ?, ?, ?, ?)")
                 ->execute([$no_fba, $b['NoRef'] ?? null, $b['SimId'] ?? null, $b['ImeiModem'] ?? null, $long, $lat, $posisi, ($relay === 'BERFUNGSI' ? 'MENYALA' : 'TIDAK')]);

            $conn->commit();
            ph_redirect($sub_ini, 'sukses', "PK Pemeliharaan ($no_fpk) berhasil disimpan.");
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            ph_redirect($sub_ini, 'gagal', "Gagal simpan PK: " . $e->getMessage());
        }
    }
}

/* ======================================================================
 * 2. READ DATA
 * ==================================================================== */
$pesan_flash = ph_flash_html();

[$data_pemeliharaan, $total_hasil, $hal, $total_hal, $q] = ph_daftar(
    $conn,
    "p.NoFpk, b.NoFba, p.TglFpk, b.KdPetugas, p.IdPel, bd.NoRef, bd.SimId, bd.ImeiModem,
     p.LongDil AS Longitude, p.LatDil AS Latitude, bd.PosisiPasang, bd.LampuIndikator AS KondisiRelay, p.StatusData",
    "frm_pk p 
     LEFT JOIN frm_ba b ON p.NoFpk = b.NoFpk 
     LEFT JOIN frm_ba_detil bd ON b.NoFba = bd.NoFba",
    "AND p.JenisPK = 'PEMELIHARAAN'"
);

$list_idpel_pem = [];
try {
    $st = $conn->prepare("SELECT DISTINCT IdPel, NoRef, SimId, ImeiModem FROM master_barang WHERE StatusData = 'AKTIF' AND IdPel IS NOT NULL AND IdPel != '' ORDER BY IdPel ASC");
    $st->execute();
    $list_idpel_pem = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pesan_error = 'Gagal memuat daftar pelanggan terpasang.';
}
?>

<!-- Leaflet CSS & JS untuk Maps -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
    .pln-form-box { border: 1.5px solid #212529; font-size: 0.85rem; }
    .pln-form-box th, .pln-form-box td { border: 1px solid #212529 !important; padding: 6px 10px; vertical-align: middle; }
    .pln-header-title { font-weight: 700; letter-spacing: 0.5px; }
</style>

<?= $pesan_flash ?>
<div class="card border-0 shadow-sm rounded-4 bg-white p-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 px-2 pt-2">
        <h5 class="fw-bold text-primary mb-0">Daftar Order Perintah Kerja (Pemeliharaan)</h5>
        <div class="d-flex gap-2">
            <?= ph_kotak_cari($sub_ini, $q) ?>
            
            <!-- Tombol Buat Order -->
            <button type="button" class="btn btn-primary fw-bold px-3 rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalBuatOrder">
                <i class="bi bi-file-earmark-plus me-1"></i> + Buat Order
            </button>

            <!-- Tombol Buat PK -->
            <button type="button" class="btn btn-warning fw-bold px-3 rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalBuatPK">
                <i class="bi bi-tools me-1"></i> + Buat PK
            </button>
        </div>
    </div>

    <div class="w-100 table-responsive">
        <table class="table table-hover align-middle mb-0 table-compact w-100">
            <thead class="table-light border-top border-bottom">
                <tr>
                    <th class="ps-2 text-center" style="width:3%;">#</th>
                    <th>NO PK / NO BA</th>
                    <th>TGL / PETUGAS</th>
                    <th>IDPEL / NO REF</th>
                    <th>SIM ID</th>
                    <th>IMEI MODEM</th>
                    <th>KOORDINAT GPS</th>
                    <th>INDIKATOR</th>
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody class="fw-medium text-dark">
                <?php if (empty($data_pemeliharaan)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">Belum ada order pemeliharaan.</td></tr>
                <?php else: ?>
                    <?php foreach ($data_pemeliharaan as $idx => $row): ?>
                        <tr class="border-bottom">
                            <td class="ps-2 text-center text-muted small"><?= ($hal - 1) * PH_PER_HALAMAN + $idx + 1 ?></td>
                            <td>
                                <div class="fw-bold text-dark font-monospace"><?= ph_e($row['NoFpk']) ?></div>
                                <div class="small text-primary font-monospace">BA: <?= ph_e($row['NoFba'] ?? '-') ?></div>
                            </td>
                            <td><?= ph_e($row['TglFpk']) ?><br><small class="text-muted"><?= ph_e($row['KdPetugas'] ?? '-') ?></small></td>
                            <td>IDPEL: <?= ph_e($row['IdPel']) ?><br><small class="text-muted">REF: <?= ph_e($row['NoRef'] ?? '-') ?></small></td>
                            <td><span class="badge bg-info bg-opacity-10 text-info font-monospace"><?= ph_e($row['SimId'] ?? '-') ?></span></td>
                            <td class="font-monospace"><?= ph_e($row['ImeiModem'] ?? '-') ?></td>
                            <td class="font-monospace small"><?= ph_e(($row['Latitude'] ?? '-') . ', ' . ($row['Longitude'] ?? '-')) ?></td>
                            <td><span class="badge bg-<?= ($row['KondisiRelay'] ?? '') === 'MENYALA' ? 'success' : 'danger' ?>"><?= ph_e($row['KondisiRelay'] ?? '-') ?></span></td>
                            <td><?= ph_badge_status($row['StatusData']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= ph_pager($sub_ini, $total_hasil, $hal, $total_hal, $q) ?>
</div>

<!-- ======================================================================
 * MODAL 1: FORMULIR ORDER PERINTAH KERJA (TAMPILAN BERSIH & ELEGAN)
 * ==================================================================== -->
<div class="modal fade" id="modalBuatOrder" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <h5 class="modal-title fw-bold text-primary"><i class="bi bi-file-earmark-text me-2"></i>Formulir Order Perintah Kerja</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="tambah_order_pk">
                <div class="modal-body p-4 text-start">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">NO ORDER *</label>
                            <input type="text" name="no_form_order" class="form-control rounded-3 font-monospace bg-light" placeholder="ORD-HAR-YYYYMMDD-XXXX" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">TANGGAL ORDER *</label>
                            <input type="date" name="tgl_order" class="form-control rounded-3 bg-light" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">NAMA / KODE PETUGAS *</label>
                        <select name="kd_petugas" class="form-select rounded-3 bg-light" required>
                            <?php foreach (PH_PETUGAS as $p): ?>
                                <option value="<?= $p ?>"><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">PILIH DAFTAR PELANGGAN *</label>
                        <div class="border rounded-3 p-3 bg-light" style="max-height: 220px; overflow-y: auto;">
                            <?php foreach ($list_idpel_pem as $pel): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="id_pel_list[]" value="<?= ph_e($pel['IdPel']) ?>" id="pel_<?= ph_e($pel['IdPel']) ?>">
                                    <label class="form-check-label font-monospace small text-dark" for="pel_<?= ph_e($pel['IdPel']) ?>">
                                        <strong>IDPEL: <?= ph_e($pel['IdPel']) ?></strong> | SIM: <?= ph_e($pel['SimId']) ?> | Ref: <?= ph_e($pel['NoRef']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted mt-1 d-block">* Centang kotak pelanggan yang ingin dimasukkan ke dalam lembar order kerja.</small>
                    </div>
                </div>
                <div class="modal-footer border-top p-3 justify-content-end gap-2 bg-light">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary font-weight-bold rounded-3 px-4">Terbitkan Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================================
 * MODAL 2: FORMULIR PERINTAH KERJA (MIRIP DOKUMEN RESMI PLN CIKOKOL)
 * ==================================================================== -->
<div class="modal fade" id="modalBuatPK" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 rounded-4 shadow">
            
            <!-- HEADER RESMI PLN -->
            <div class="modal-header border-bottom bg-white py-3 px-4">
                <div class="row w-100 align-items-center">
                    <div class="col-md-5">
                        <h6 class="fw-bold mb-0 text-dark small">PT PLN (PERSERO) UID BANTEN</h6>
                        <span class="fw-bold text-dark">UP3 CIKOKOL</span>
                    </div>
                    <div class="col-md-4 text-center">
                        <h5 class="fw-bold text-dark mb-0 pln-header-title">FORMULIR PERINTAH KERJA</h5>
                    </div>
                    <div class="col-md-3 text-end">
                        <span class="border border-dark px-2 py-1 fw-bold small font-monospace">FM-2026-Z</span>
                    </div>
                </div>
            </div>

            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="tambah_pk_pemeliharaan">
                <div class="modal-body p-4 bg-white">
                    
                    <!-- KOTAK INFORMASI UTAMA -->
                    <table class="table pln-form-box mb-3 w-100">
                        <tr>
                            <td class="bg-light fw-bold" style="width: 18%;">Unit</td>
                            <td style="width: 32%;">UP3 Cikokol / ULP Terkait</td>
                            <td class="bg-light fw-bold" style="width: 18%;">Tanggal</td>
                            <td style="width: 32%;"><input type="date" name="tgl_fpk" class="form-control form-control-sm border-0 bg-transparent fw-bold" value="<?= date('Y-m-d') ?>" required></td>
                        </tr>
                        <tr>
                            <td class="bg-light fw-bold">Jenis PK</td>
                            <td>
                                <span class="badge bg-warning text-dark px-2 py-1 fw-bold">PEMELIHARAAN</span>
                                <input type="hidden" name="jenis_pk" value="PEMELIHARAAN">
                            </td>
                            <td class="bg-light fw-bold">Nomor Form (No Fpk)</td>
                            <td><input type="text" name="no_fpk" class="form-control form-control-sm border-0 bg-transparent font-monospace fw-bold text-danger" placeholder="PK-HAR-YYYYMMDD-XXXX" required></td>
                        </tr>
                        <tr>
                            <td class="bg-light fw-bold">Id Pelanggan</td>
                            <td>
                                <select name="id_pel" id="selectIdPelPk" class="form-select form-select-sm border-0 bg-transparent fw-bold" required>
                                    <option value="">-- Pilih ID Pelanggan --</option>
                                    <?php foreach ($list_idpel_pem as $pel): ?>
                                        <option value="<?= ph_e($pel['IdPel']); ?>" 
                                                data-noref="<?= ph_e($pel['NoRef']); ?>" 
                                                data-sim="<?= ph_e($pel['SimId']); ?>" 
                                                data-imei="<?= ph_e($pel['ImeiModem']); ?>">
                                            <?= ph_e($pel['IdPel']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="bg-light fw-bold">Petugas</td>
                            <td>
                                <select name="kd_petugas" class="form-select form-select-sm border-0 bg-transparent fw-bold" required>
                                    <?php foreach (PH_PETUGAS as $p): ?>
                                        <option value="<?= $p ?>"><?= $p ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td class="bg-light fw-bold">Tikor DIL (Koordinat)</td>
                            <td colspan="3">
                                <div class="d-flex align-items-center gap-3">
                                    <span>Long: <input type="text" name="longitude" id="lngInput" class="d-inline-block form-control form-control-sm font-monospace border-0 bg-transparent w-auto" placeholder="106.xxxx" readonly required></span>
                                    <span>Lat: <input type="text" name="latitude" id="latInput" class="d-inline-block form-control form-control-sm font-monospace border-0 bg-transparent w-auto" placeholder="-6.xxxx" readonly required></span>
                                    <button type="button" class="btn btn-sm btn-success py-0 px-2 ms-auto" id="btnGetGPS"><i class="bi bi-geo-alt-fill me-1"></i> Ambil GPS Saya</button>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <!-- MAPS KECIL PROPORSIONAL -->
                    <div id="mapPemeliharaan" style="height: 140px; width: 100%;" class="rounded-3 border mb-3"></div>

                    <!-- TABEL BONGKAR VS PASANG (MENYERUPAI DOKUMEN FISIK) -->
                    <table class="table pln-form-box text-center mb-3">
                        <thead>
                            <tr class="bg-light">
                                <th style="width: 20%;">MODEM / S41</th>
                                <th style="width: 40%;" class="text-danger">BONGKAR (EKSISTING)</th>
                                <th style="width: 40%;" class="text-success">PASANG (HASIL PEMELIHARAAN)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="bg-light text-start fw-bold small">NoRef / IMEI S41</td>
                                <td><span id="pkNoRef" class="font-monospace fw-bold text-danger">-</span></td>
                                <td class="text-muted small fst-italic">Sesuai Eksisting</td>
                            </tr>
                            <tr>
                                <td class="bg-light text-start fw-bold small">Nomor SIM / IMEI Modem</td>
                                <td><span id="pkSimImei" class="font-monospace small">- / -</span></td>
                                <td class="text-muted small fst-italic">Sesuai Eksisting</td>
                            </tr>
                            <tr>
                                <td class="bg-light text-start fw-bold small">Posisi / Lampu Indikator</td>
                                <td><span class="text-muted">-</span></td>
                                <td>
                                    <div class="row g-1">
                                        <div class="col-6">
                                            <select name="posisi_pasang" class="form-select form-select-sm">
                                                <?php foreach (PH_POSISI as $pos): ?>
                                                    <option value="<?= $pos ?>"><?= $pos ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <select name="kondisi_relay" class="form-select form-select-sm" required>
                                                <option value="BERFUNGSI">MENYALA</option>
                                                <option value="TIDAK BERFUNGSI">TIDAK</option>
                                            </select>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                </div>
                <div class="modal-footer border-top p-3 justify-content-end gap-2 bg-light">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning font-weight-bold rounded-3 px-4">Simpan PK Pemeliharaan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // 1. Auto Fill Data Eksisting ke Tabel Bongkar
    var selIdPel = document.getElementById("selectIdPelPk");
    if (selIdPel) {
        selIdPel.addEventListener("change", function () {
            var opt = this.options[this.selectedIndex];
            document.getElementById("pkNoRef").textContent = opt.getAttribute("data-noref") || "-";
            var sim = opt.getAttribute("data-sim") || "-";
            var imei = opt.getAttribute("data-imei") || "-";
            document.getElementById("pkSimImei").textContent = sim + " / " + imei;
        });
    }

    // 2. Peta Interaktif Leaflet
    var defaultLat = -6.1783; 
    var defaultLng = 106.6321;
    var map, marker;

    var modalPK = document.getElementById('modalBuatPK');
    modalPK.addEventListener('shown.bs.modal', function () {
        if (!map) {
            map = L.map('mapPemeliharaan').setView([defaultLat, defaultLng], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            marker = L.marker([defaultLat, defaultLng], {draggable: true}).addTo(map);

            function updateInputs(lat, lng) {
                document.getElementById('latInput').value = lat.toFixed(6);
                document.getElementById('lngInput').value = lng.toFixed(6);
            }

            map.on('click', function(e) {
                marker.setLatLng(e.latlng);
                updateInputs(e.latlng.lat, e.latlng.lng);
            });

            marker.on('dragend', function(e) {
                var position = marker.getLatLng();
                updateInputs(position.lat, position.lng);
            });
        }
        map.invalidateSize();
    });

    // 3. Tombol GPS
    document.getElementById('btnGetGPS').addEventListener('click', function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                var lat = position.coords.latitude;
                var lng = position.coords.longitude;
                map.setView([lat, lng], 16);
                marker.setLatLng([lat, lng]);
                document.getElementById('latInput').value = lat.toFixed(6);
                document.getElementById('lngInput').value = lng.toFixed(6);
            }, function() {
                alert('Gagal mengambil lokasi GPS.');
            });
        } else {
            alert('Browser tidak mendukung GPS.');
        }
    });
});
</script>