<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    require_once __DIR__ . '/../../config/database.php';
}
require_once __DIR__ . '/../../functions/pemeliharaan_helper.php';

$pesan_sukses_mut = '';
$pesan_error_mut  = '';

// 1. Ambil Data Master SIM Card & Modem Aktif untuk Pilihan Mutasi Baru
$list_sim_mutasi = [];
$list_modem_mutasi = [];
try {
    $list_sim_mutasi = $conn->query("SELECT SimId, NomorAkun FROM master_nomor WHERE StatusData = 'AKTIF' ORDER BY SimId ASC")->fetchAll(PDO::FETCH_ASSOC);
    $list_modem_mutasi = $conn->query("SELECT ImeiModem, Keterangan FROM master_modem WHERE StatusData = 'AKTIF' ORDER BY ImeiModem ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pesan_error_mut = "Error DB: " . $e->getMessage();
}

// 2. Proses Simpan Eksekusi Mutasi Perangkat (Bongkar & Pasang)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_mutasi'])) {
    if ($_POST['action_mutasi'] === 'simpan_mutasi') {
        try {
            $no_fba      = $_POST['no_fba'];
            $sim_id_baru = !empty($_POST['sim_id']) ? $_POST['sim_id'] : null;
            $imei_baru   = !empty($_POST['imei_modem']) ? $_POST['imei_modem'] : null;
            $noref_lama  = $_POST['noref_lama'] ?? null;
            $sim_lama    = $_POST['sim_id_lama'] ?? null;
            $imei_lama   = $_POST['imei_lama'] ?? null;

            $conn->beginTransaction();

            // A. Catat record BONGKAR (alat lama) ke frm_ba_detil
            $conn->prepare("INSERT INTO frm_ba_detil (NoFba, StatusPekerjaan, NoRef, SimId, ImeiModem) VALUES (?, 'BONGKAR', ?, ?, ?)")
                 ->execute([$no_fba, $noref_lama, $sim_lama, $imei_lama]);

            // B. Catat record PASANG (alat baru) ke frm_ba_detil
            $conn->prepare("INSERT INTO frm_ba_detil (NoFba, StatusPekerjaan, NoRef, SimId, ImeiModem) VALUES (?, 'PASANG', ?, ?, ?)")
                 ->execute([$no_fba, $noref_lama, $sim_id_baru, $imei_baru]);

            // C. Update master_barang dengan perangkat baru
            $conn->prepare("UPDATE master_barang SET SimId = ?, ImeiModem = ? WHERE NoRef = ?")
                 ->execute([$sim_id_baru, $imei_baru, $noref_lama]);

            $conn->commit();
            $pesan_sukses_mut = "Mutasi perangkat untuk BA $no_fba berhasil disimpan!";
        } catch (PDOException $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $pesan_error_mut = "Gagal memproses mutasi: " . $e->getMessage();
        }
    }
}

// 3. Ambil Daftar PK Pemeliharaan yang siap dimutasi
$data_mutasi = [];
try {
    $sql_mut = "SELECT b.NoFba, b.NoFpk, b.IdPel, bd.NoRef, bd.SimId, bd.ImeiModem, bd.LampuIndikator 
                FROM frm_ba b 
                JOIN frm_ba_detil bd ON b.NoFba = bd.NoFba 
                WHERE b.JenisPekerjaan = 'PEMELIHARAAN' AND bd.StatusPekerjaan = 'PASANG'
                ORDER BY b.WaktuData DESC";
    $data_mutasi = $conn->query($sql_mut)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pesan_error_mut = "Error Database: " . $e->getMessage();
}
?>

<?php if (!empty($pesan_sukses_mut)): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3 mx-3 mt-3"><i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($pesan_sukses_mut) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (!empty($pesan_error_mut)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 mx-3 mt-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($pesan_error_mut) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 bg-white p-3">
    <div class="d-flex justify-content-between align-items-center mb-3 px-2 pt-2">
        <h5 class="fw-bold text-primary mb-0">Riwayat & Eksekusi Mutasi Perangkat Pemeliharaan</h5>
    </div>

    <div class="w-100 table-responsive">
        <table class="table table-hover align-middle mb-0 table-compact w-100">
            <thead class="table-light border-top border-bottom">
                <tr>
                    <th class="ps-2 text-center" style="width: 4%;">#</th>
                    <th>NO FBA / PK</th>
                    <th>IDPEL / NO REF</th>
                    <th>SIM ID TERPASANG</th>
                    <th>IMEI MODEM TERPASANG</th>
                    <th class="text-center">AKSI MUTASI</th>
                </tr>
            </thead>
            <tbody class="fw-medium text-dark">
                <?php if (empty($data_mutasi)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">Belum ada data mutasi pemeliharaan. Silakan buat PK pemeliharaan terlebih dahulu pada menu Order Pemeliharaan.</td></tr>
                <?php else: ?>
                    <?php foreach ($data_mutasi as $idx => $row): ?>
                        <tr class="border-bottom">
                            <td class="ps-2 text-center text-muted small"><?= $idx + 1 ?></td>
                            <td class="fw-bold font-monospace"><?= htmlspecialchars($row['NoFba']) ?><br><small class="text-muted">PK: <?= htmlspecialchars($row['NoFpk']) ?></small></td>
                            <td>IDPEL: <?= htmlspecialchars($row['IdPel']) ?><br><small class="text-muted">REF: <?= htmlspecialchars($row['NoRef'] ?? '-') ?></small></td>
                            <td class="font-monospace"><span class="badge bg-info bg-opacity-10 text-info font-monospace"><?= htmlspecialchars($row['SimId'] ?? '-') ?></span></td>
                            <td class="font-monospace"><?= htmlspecialchars($row['ImeiModem'] ?? '-') ?></td>
                            <td class="pe-2 text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-3 px-3 py-1 fw-bold" data-bs-toggle="modal" data-bs-target="#modalMutasiPerangkat<?= $idx ?>">
                                    <i class="bi bi-arrow-left-right me-1"></i> Ganti Alat
                                </button>
                            </td>
                        </tr>

                        <!-- MODAL POPUP MUTASI PERANGKAT -->
                        <div class="modal fade" id="modalMutasiPerangkat<?= $idx ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 rounded-4 shadow">
                                    <div class="modal-header border-0 pb-0 pt-4 px-4">
                                        <h5 class="modal-title fw-bold text-dark"><i class="bi bi-arrow-left-right me-2 text-primary"></i>Mutasi / Penggantian Perangkat</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body p-4 text-start">
                                            <input type="hidden" name="action_mutasi" value="simpan_mutasi">
                                            <input type="hidden" name="no_fba" value="<?= htmlspecialchars($row['NoFba']) ?>">
                                            <input type="hidden" name="noref_lama" value="<?= htmlspecialchars($row['NoRef']) ?>">
                                            <input type="hidden" name="sim_id_lama" value="<?= htmlspecialchars($row['SimId']) ?>">
                                            <input type="hidden" name="imei_lama" value="<?= htmlspecialchars($row['ImeiModem']) ?>">

                                            <div class="alert alert-light border small mb-3">
                                                <div><strong>ID Pelanggan:</strong> <?= htmlspecialchars($row['IdPel']) ?></div>
                                                <div><strong>Perangkat Lama (Bongkar):</strong> SIM: <?= htmlspecialchars($row['SimId'] ?? '-') ?> | IMEI: <?= htmlspecialchars($row['ImeiModem'] ?? '-') ?></div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label text-uppercase text-muted fw-bold small mb-1">PILIH SIM CARD BARU *</label>
                                                <select name="sim_id" class="form-select rounded-3" required>
                                                    <option value="">-- Pilih SIM Baru --</option>
                                                    <?php foreach ($list_sim_mutasi as $s): ?>
                                                        <option value="<?= htmlspecialchars($s['SimId']); ?>"><?= htmlspecialchars($s['SimId']); ?> (<?= htmlspecialchars($s['NomorAkun']); ?>)</option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label text-uppercase text-muted fw-bold small mb-1">PILIH MODEM BARU *</label>
                                                <select name="imei_modem" class="form-select rounded-3" required>
                                                    <option value="">-- Pilih IMEI Modem Baru --</option>
                                                    <?php foreach ($list_modem_mutasi as $m): ?>
                                                        <option value="<?= htmlspecialchars($m['ImeiModem']); ?>"><?= htmlspecialchars($m['ImeiModem']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="modal-footer border-top p-3 justify-content-end gap-2 bg-light">
                                            <button type="button" class="btn btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary fw-bold rounded-3 px-4">Simpan Mutasi</button>
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