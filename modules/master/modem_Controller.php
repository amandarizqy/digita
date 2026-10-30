<?php
// Sub-menu: Master Modem GSM (Filter Unit Hirarkis & SIM ID dari master_nomor)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    require_once __DIR__ . '/../../config/database.php';
}
require_once __DIR__ . '/../../functions/pemeliharaan_helper.php';
require_once __DIR__ . '/../../functions/master_ui.php';

if (!isset($_SESSION['NamaAkun'])) {
    header("Location: " . (defined('BASE_URL') ? BASE_URL : '') . "/modules/auth/login.php");
    exit;
}

$sub_ini = 'modem';
$role    = ph_role();
$is_ulp  = (substr($role, -3) === '.UP');

/** Cek apakah modem termasuk wewenang pengguna yang sedang login. */
function modem_dalam_scope(PDO $conn, string $imei): bool
{
    [$sc_sql, $sc_par] = ph_scope('p');
    $st = $conn->prepare("SELECT p.ImeiModem FROM master_modem p WHERE 1=1" . $sc_sql);
    $st->execute($sc_par);
    return in_array($imei, $st->fetchAll(PDO::FETCH_COLUMN), true);
}

/* ======================================================================
 * 1. PROSES TAMBAH / EDIT / AKTIF-NONAKTIF (POST)
 * ==================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();
    $aksi = $_POST['action'];

    // ---- A. TAMBAH MODEM ----
    if ($aksi === 'tambah_modem') {
        try {
            $imei        = trim((string) ($_POST['imei_modem'] ?? ''));
            $keterangan  = trim((string) ($_POST['keterangan'] ?? ''));
            $sim_id      = trim((string) ($_POST['sim_id'] ?? ''));
            $sim_id      = ($sim_id === '') ? null : $sim_id;
            
            $ip_data     = trim((string) ($_POST['ip_server_data'] ?? '10.96.100.123'));
            $ip_engine   = trim((string) ($_POST['ip_server_engine'] ?? '10.96.100.20'));
            $port_engine = trim((string) ($_POST['port_engine'] ?? 'COM13'));
            $status      = (($_POST['status_data'] ?? 'AKTIF') === 'TIDAK') ? 'TIDAK' : 'AKTIF';

            if ($imei === '') {
                ph_redirect($sub_ini, 'gagal', 'IMEI Modem wajib diisi.');
            }
            if ($keterangan === '') {
                ph_redirect($sub_ini, 'gagal', 'Nama modem wajib diisi.');
            }

            $up_pilih = trim((string) ($_POST['unit_up'] ?? ''));
            $st = $conn->prepare("SELECT UnitUp, UnitAp, UnitUpi FROM master_up WHERE UnitUp = ? AND StatusData = 'AKTIF'");
            $st->execute([$up_pilih]);
            $unit = $st->fetch(PDO::FETCH_ASSOC);

            if (!$unit) {
                ph_redirect($sub_ini, 'gagal', 'Unit ULP wajib dipilih dengan benar.');
            }

            $upi = $unit['UnitUpi'] ?: '56';

            $conn->prepare("INSERT INTO master_modem 
                (ImeiModem, Keterangan, SimId, IpServerData, IpServerEngine, PortEngine, UnitUp, UnitAp, UnitUpi, StatusData, WaktuData) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())")
                ->execute([$imei, $keterangan, $sim_id, $ip_data, $ip_engine, $port_engine, $unit['UnitUp'], $unit['UnitAp'], $upi, $status]);

            ph_redirect($sub_ini, 'sukses', "Modem '$keterangan' ($imei) berhasil ditambahkan.");
        } catch (Throwable $e) {
            error_log('tambah_modem error: ' . $e->getMessage());
            $msg = $e->getMessage();
            if (stripos($msg, 'foreign key') !== false) {
                $msg = 'SIM ID tidak terdaftar di master nomor.';
            } elseif (stripos($msg, 'Duplicate') !== false) {
                $msg = 'IMEI sudah terdaftar.';
            }
            ph_redirect($sub_ini, 'gagal', 'Gagal menambah modem: ' . $msg);
        }
    }

    // ---- B. EDIT MODEM ----
    if ($aksi === 'edit_modem') {
        try {
            $imei        = trim((string) ($_POST['imei_modem'] ?? ''));
            $keterangan  = trim((string) ($_POST['keterangan'] ?? ''));
            $sim_id      = trim((string) ($_POST['sim_id'] ?? ''));
            $sim_id      = ($sim_id === '') ? null : $sim_id;
            
            $ip_data     = trim((string) ($_POST['ip_server_data'] ?? '10.96.100.123'));
            $ip_engine   = trim((string) ($_POST['ip_server_engine'] ?? '10.96.100.20'));
            $port_engine = trim((string) ($_POST['port_engine'] ?? 'COM13'));
            $status      = (($_POST['status_data'] ?? 'AKTIF') === 'TIDAK') ? 'TIDAK' : 'AKTIF';

            if ($keterangan === '') {
                ph_redirect($sub_ini, 'gagal', 'Nama modem wajib diisi.');
            }
            if (!modem_dalam_scope($conn, $imei)) {
                ph_redirect($sub_ini, 'gagal', 'Modem tidak ditemukan atau di luar wewenang Anda.');
            }

            $conn->prepare("UPDATE master_modem SET 
                Keterangan = ?, SimId = ?, IpServerData = ?, IpServerEngine = ?, PortEngine = ?, StatusData = ?, WaktuData = NOW() 
                WHERE ImeiModem = ?")
                ->execute([$keterangan, $sim_id, $ip_data, $ip_engine, $port_engine, $status, $imei]);

            ph_redirect($sub_ini, 'sukses', "Data modem '$keterangan' ($imei) berhasil diperbarui.");
        } catch (Throwable $e) {
            error_log('edit_modem error: ' . $e->getMessage());
            $msg = $e->getMessage();
            if (stripos($msg, 'foreign key') !== false) {
                $msg = 'SIM ID tidak terdaftar di master nomor.';
            }
            ph_redirect($sub_ini, 'gagal', 'Gagal mengedit modem: ' . $msg);
        }
    }

    // ---- C. TOGGLE STATUS ----
    if ($aksi === 'toggle_status') {
        try {
            $imei = trim((string) ($_POST['imei_modem'] ?? ''));

            if (!modem_dalam_scope($conn, $imei)) {
                ph_redirect($sub_ini, 'gagal', 'Modem tidak ditemukan atau di luar wewenang Anda.');
            }

            $conn->prepare("UPDATE master_modem 
                SET StatusData = IF(StatusData = 'AKTIF', 'TIDAK', 'AKTIF'), WaktuData = NOW() 
                WHERE ImeiModem = ?")
                ->execute([$imei]);

            ph_redirect($sub_ini, 'sukses', "Status modem ($imei) berhasil diubah.");
        } catch (Throwable $e) {
            error_log('toggle_modem error: ' . $e->getMessage());
            ph_redirect($sub_ini, 'gagal', 'Gagal mengubah status modem: ' . $e->getMessage());
        }
    }
}

/* ======================================================================
 * 2. BACA DATA (JOIN SIM ID DARI MASTER NOMOR)
 * ==================================================================== */
$pesan_flash = ph_flash_html();

[$sc_sql, $sc_par] = ph_scope('p');

$st = $conn->prepare("SELECT COUNT(*) FROM master_modem p WHERE 1=1" . $sc_sql);
$st->execute($sc_par);
$total_modem = (int) $st->fetchColumn();

$st = $conn->prepare("SELECT COUNT(*) FROM master_modem p WHERE p.StatusData = 'AKTIF'" . $sc_sql);
$st->execute($sc_par);
$total_modem_aktif = (int) $st->fetchColumn();
$persentase_aktif  = ($total_modem > 0) ? round(($total_modem_aktif / $total_modem) * 100) : 0;

[$data_modem, $total_hasil, $hal, $total_hal, $q] = ph_daftar(
    $conn,
    "p.ImeiModem, p.Keterangan, p.SimId, p.IpServerData, p.IpServerEngine, p.PortEngine, p.UnitUp, p.UnitAp, p.UnitUpi, p.StatusData, p.WaktuData",
    "master_modem p"
);

// Pilihan AP (UP3) & UP (ULP)
$list_ap = $conn->query("SELECT UnitAp, SingkatanNama, UnitUpi FROM master_ap WHERE StatusData = 'AKTIF' ORDER BY UnitAp ASC")->fetchAll(PDO::FETCH_ASSOC);
$list_up = $conn->query("SELECT UnitUp, SingkatanNama, UnitAp, UnitUpi FROM master_up WHERE StatusData = 'AKTIF' ORDER BY UnitUp ASC")->fetchAll(PDO::FETCH_ASSOC);

// Pilihan SIM ID dari master_nomor
$list_sim = [];
try {
    $list_sim = $conn->query("SELECT SimId, NomorAkun FROM master_nomor WHERE StatusData = 'AKTIF' ORDER BY SimId ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $list_sim = [];
}

// Hak akses tab
$akses_unit     = in_array($role, ['SA.KP', 'SF.UI'], true);
$akses_pengguna = $akses_unit;
$akses_provider = $akses_unit;
$akses_modem    = true;
$akses_pesan    = in_array($role, ['SA.KP', 'AM.UI'], true);

$page_title = "Master - Digitas";

ob_start();
?>

<?= ms_header([
    'title'    => 'Master Modem GSM',
    'subtitle' => 'Kelola modem gateway, kartu SIM, serta IP server dan port engine telemetri relai S41.',
    'tombol'   => ['label' => 'Tambah Modem', 'target' => 'modalTambahModem', 'icon' => 'bi-plus-lg'],
], ['unit' => $akses_unit, 'pengguna' => $akses_pengguna, 'provider' => $akses_provider, 'modem' => $akses_modem, 'pesan' => $akses_pesan], 'pesan', [
    ['label' => 'Provider SIM',                'href' => 'index.php?page=master&sub=provider', 'on' => false, 'locked' => !$akses_provider],
    ['label' => 'Modem GSM',                   'href' => 'index.php?page=master&sub=modem',    'on' => true,  'locked' => false],
    ['label' => 'Perintah Baku (AT Commands)', 'href' => 'index.php?page=master&sub=pesan',    'on' => false, 'locked' => !$akses_pesan],
]) ?>

<?= $pesan_flash ?>

<!-- KARTU METRIK KPI -->
<div class="row g-3 mb-4">
    <?= ms_kpi('TOTAL MODEM TERDAFTAR', ms_kpi_nilai($total_modem, 'Modem'), 'Modem gateway terdaftar', 'bi-hdd-network', 'primary') ?>
    <?= ms_kpi('STATUS MODEM ONLINE', ms_n($total_modem_aktif) . ' <span class="fs-6 fw-semibold text-muted">/ ' . ms_n($total_modem) . ' Aktif</span>', $persentase_aktif . '% port engine & gateway server terhubung', 'bi-cpu', 'success', true) ?>
</div>

<!-- TABEL MASTER MODEM -->
<div class="card border-0 shadow-sm rounded-4 bg-white">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <span class="fw-bold text-primary fs-6">Daftar Master Modem Gateway</span>
        <?= ph_kotak_cari($sub_ini, $q, 'Cari IMEI, IP, Port...') ?>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 ms-table">
                <thead class="table-light text-secondary text-uppercase ms-thead">
                    <tr>
                        <th class="ps-4" style="width: 40px;">#</th>
                        <th>IMEI MODEM / NAMA</th>
                        <th>KODE UNIT (UPI / AP / UP)</th>
                        <th>SIM ID / NO. AKUN</th>
                        <th>IP SERVER (DATA / ENGINE)</th>
                        <th>PORT</th>
                        <th>STATUS DATA</th>
                        <th>WAKTU DATA</th>
                        <th class="text-end pe-4" style="width: 100px;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data_modem)): ?>
                        <?= ms_empty_row(9, 'Belum ada data modem terdaftar.') ?>
                    <?php else: ?>
                        <?php foreach ($data_modem as $idx => $row): ?>
                            <?php
                                $modem_aktif = (($row['StatusData'] ?? '') === 'AKTIF');
                                $nama_modem  = trim((string) ($row['Keterangan'] ?? ''));
                            ?>
                            <tr>
                                <td class="ps-4 text-muted small"><?= ($hal - 1) * PH_PER_HALAMAN + $idx + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-hdd-rack text-primary fs-5"></i>
                                        <div>
                                            <div class="font-monospace fw-bold text-dark"><?= ph_e($row['ImeiModem']) ?></div>
                                            <?php if ($nama_modem !== ''): ?>
                                                <div class="small text-muted fw-medium"><?= ph_e($nama_modem) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small font-monospace">
                                        <span class="badge bg-light text-secondary border">UPI: <?= ph_e($row['UnitUpi'] ?? '56') ?></span>
                                        <span class="badge bg-light text-secondary border">AP: <?= ph_e($row['UnitAp'] ?? '-') ?></span>
                                        <span class="badge bg-light text-secondary border">UP: <?= ph_e($row['UnitUp'] ?? '-') ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($row['SimId'])): ?>
                                        <div class="font-monospace small fw-bold text-info-emphasis"><?= ph_e($row['SimId']) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic small">Tanpa SIM</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small font-monospace">
                                        <div class="text-muted">Data: <span class="text-danger"><?= ph_e($row['IpServerData'] ?? '10.96.100.123') ?></span></div>
                                        <div class="text-muted">Engine: <span class="text-danger"><?= ph_e($row['IpServerEngine'] ?? '10.96.100.20') ?></span></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace"><?= ph_e($row['PortEngine'] ?? 'COM13') ?></span>
                                </td>
                                <td><?= ms_badge_status($row['StatusData'] ?? '') ?></td>
                                <td class="small text-muted font-monospace"><?= ph_e($row['WaktuData'] ?? '-') ?></td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <form method="POST" action="index.php?page=master&sub=modem" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="imei_modem" value="<?= ph_e($row['ImeiModem']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary py-1 px-2"
                                                    title="<?= $modem_aktif ? 'Nonaktifkan modem' : 'Aktifkan modem' ?>"
                                                    data-confirm="<?= ph_e(($modem_aktif ? 'Nonaktifkan' : 'Aktifkan') . ' modem ' . ($nama_modem !== '' ? $nama_modem : $row['ImeiModem']) . '?') ?>">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 btn-edit-modem"
                                                title="Edit Modem"
                                                data-imei="<?= ph_e($row['ImeiModem']) ?>"
                                                data-ket="<?= ph_e($row['Keterangan'] ?? '') ?>"
                                                data-sim="<?= ph_e($row['SimId'] ?? '') ?>"
                                                data-ipdata="<?= ph_e($row['IpServerData'] ?? '10.96.100.123') ?>"
                                                data-ipengine="<?= ph_e($row['IpServerEngine'] ?? '10.96.100.20') ?>"
                                                data-port="<?= ph_e($row['PortEngine'] ?? 'COM13') ?>"
                                                data-status="<?= ph_e($row['StatusData'] ?? 'AKTIF') ?>">
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

    <div class="card-footer bg-white py-3 px-4 border-top">
        <?= ph_pager($sub_ini, $total_hasil, $hal, $total_hal, $q) ?>
    </div>
</div>

<!-- MODAL TAMBAH MASTER MODEM BARU (Filter Hirarkis AP -> UP) -->
<div class="modal fade" id="modalTambahModem" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" action="index.php?page=master&sub=modem" class="modal-content border-0 shadow rounded-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="tambah_modem">
            <div class="modal-header border-bottom px-4 py-3">
                <h5 class="modal-title fw-bold">Tambah Master Modem Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">IMEI MODEM *</label>
                        <input type="text" name="imei_modem" class="form-control font-monospace" placeholder="Contoh: 867749010776270" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">PILIH SIM ID (DARI MASTER NOMOR)</label>
                        <select name="sim_id" class="form-select font-monospace">
                            <option value="">-- Tanpa SIM Card (NULL) --</option>
                            <?php foreach ($list_sim as $s): ?>
                                <option value="<?= ph_e($s['SimId']) ?>">
                                    <?= ph_e($s['SimId']) ?> <?= !empty($s['NomorAkun']) ? '(' . ph_e($s['NomorAkun']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">NAMA MODEM (KETERANGAN) *</label>
                        <input type="text" name="keterangan" class="form-control" placeholder="Contoh: MODEM BOX CIKOKOL / MODEM USB MASTER" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">KODE UPI (UID) *</label>
                        <select name="unit_upi" class="form-select bg-light" readonly>
                            <option value="56">56 - UID BANTEN</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">KODE AP (UP3) *</label>
                        <select name="unit_ap" id="selectTambahAp" class="form-select" required>
                            <option value="">-- Pilih UP3 --</option>
                            <?php foreach ($list_ap as $a): ?>
                                <option value="<?= ph_e($a['UnitAp']) ?>"><?= ph_e($a['UnitAp']) ?> - <?= ph_e($a['SingkatanNama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">KODE UP (ULP) *</label>
                        <select name="unit_up" id="selectTambahUp" class="form-select" required>
                            <option value="">-- Pilih AP Dulu --</option>
                            <?php foreach ($list_up as $u): ?>
                                <option value="<?= ph_e($u['UnitUp']) ?>" data-ap="<?= ph_e($u['UnitAp']) ?>">
                                    <?= ph_e($u['UnitUp']) ?> - <?= ph_e($u['SingkatanNama']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">IP SERVER DATA</label>
                        <input type="text" name="ip_server_data" class="form-control font-monospace" value="10.96.100.123">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">IP SERVER ENGINE</label>
                        <input type="text" name="ip_server_engine" class="form-control font-monospace" value="10.96.100.20">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">PORT ENGINE</label>
                        <input type="text" name="port_engine" class="form-control font-monospace" value="COM13">
                    </div>

                    <div class="col-12">
                        <label class="form-label">STATUS OPERASIONAL</label>
                        <select name="status_data" class="form-select">
                            <option value="AKTIF">AKTIF</option>
                            <option value="TIDAK">TIDAK AKTIF</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top px-4 py-3">
                <button type="button" class="btn btn-outline-secondary rounded-3 px-3" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary rounded-3 px-3">Simpan Data Modem</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT MODEM -->
<div class="modal fade" id="modalEditModem" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" action="index.php?page=master&sub=modem" class="modal-content border-0 shadow rounded-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_modem">
            <div class="modal-header border-bottom px-4 py-3">
                <h5 class="modal-title fw-bold">Edit Data Modem</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">IMEI MODEM (READ-ONLY)</label>
                        <input type="text" name="imei_modem" id="editImei" class="form-control font-monospace bg-light" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">PILIH SIM ID</label>
                        <select name="sim_id" id="editSim" class="form-select font-monospace">
                            <option value="">-- Tanpa SIM Card (NULL) --</option>
                            <?php foreach ($list_sim as $s): ?>
                                <option value="<?= ph_e($s['SimId']) ?>">
                                    <?= ph_e($s['SimId']) ?> <?= !empty($s['NomorAkun']) ? '(' . ph_e($s['NomorAkun']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">NAMA MODEM (KETERANGAN) *</label>
                        <input type="text" name="keterangan" id="editKet" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">IP SERVER DATA</label>
                        <input type="text" name="ip_server_data" id="editIpData" class="form-control font-monospace">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">IP SERVER ENGINE</label>
                        <input type="text" name="ip_server_engine" id="editIpEngine" class="form-control font-monospace">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">PORT ENGINE</label>
                        <input type="text" name="port_engine" id="editPort" class="form-control font-monospace">
                    </div>

                    <div class="col-12">
                        <label class="form-label">STATUS OPERASIONAL</label>
                        <select name="status_data" id="editStatus" class="form-select">
                            <option value="AKTIF">AKTIF</option>
                            <option value="TIDAK">TIDAK AKTIF</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top px-4 py-3">
                <button type="button" class="btn btn-outline-secondary rounded-3 px-3" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary rounded-3 px-3">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // ----------------------------------------------------
    // SCRIPT FILTRASI HIRARKI AP (UP3) -> UP (ULP)
    // ----------------------------------------------------
    var selectAp = document.getElementById("selectTambahAp");
    var selectUp = document.getElementById("selectTambahUp");

    if (selectAp && selectUp) {
        var upOptions = Array.from(selectUp.options);

        selectAp.addEventListener("change", function () {
            var selectedAp = this.value;
            selectUp.innerHTML = "";

            if (!selectedAp) {
                var defaultOpt = document.createElement("option");
                defaultOpt.value = "";
                defaultOpt.text = "-- Pilih AP Dulu --";
                selectUp.appendChild(defaultOpt);
                return;
            }

            var matchingOptions = upOptions.filter(function (opt) {
                return opt.getAttribute("data-ap") === selectedAp;
            });

            if (matchingOptions.length > 0) {
                matchingOptions.forEach(function (opt) {
                    selectUp.appendChild(opt.cloneNode(true));
                });
            } else {
                var emptyOpt = document.createElement("option");
                emptyOpt.value = "";
                emptyOpt.text = "-- Tidak ada ULP di UP3 ini --";
                selectUp.appendChild(emptyOpt);
            }
        });
    }

    // ----------------------------------------------------
    // SCRIPT POPULATE MODAL EDIT
    // ----------------------------------------------------
    var modalEditEl = document.getElementById("modalEditModem");

    document.addEventListener("click", function (e) {
        var btn = e.target.closest(".btn-edit-modem");
        if (!btn) { return; }
        e.preventDefault();

        document.getElementById("editImei").value     = btn.getAttribute("data-imei") || "";
        document.getElementById("editKet").value      = btn.getAttribute("data-ket") || "";
        document.getElementById("editSim").value      = btn.getAttribute("data-sim") || "";
        document.getElementById("editIpData").value   = btn.getAttribute("data-ipdata") || "10.96.100.123";
        document.getElementById("editIpEngine").value = btn.getAttribute("data-ipengine") || "10.96.100.20";
        document.getElementById("editPort").value     = btn.getAttribute("data-port") || "COM13";
        document.getElementById("editStatus").value   = btn.getAttribute("data-status") === "AKTIF" ? "AKTIF" : "TIDAK";

        bootstrap.Modal.getOrCreateInstance(modalEditEl).show();
    });
});
</script>

<?php
$content = ob_get_clean();

require_once __DIR__ . '/../../templates/layouts/base.php';