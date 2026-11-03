<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($conn)) require_once __DIR__ . '/../../config/database.php';

$data_riwayat = [];
$q = trim($_GET['q'] ?? '');

try {
    $unit_up = $_SESSION['UnitUp'] ?? '';
    $is_sa   = ($_SESSION['KodeHak'] ?? '') === 'SA.KP';
    
    $sql = "SELECT d.*, f.TglUji, f.NamaAkun, f.UnitUp 
            FROM formulir_pengujian_detil d
            JOIN formulir_pengujian f ON f.NoFormulir = d.NoFormulir
            WHERE f.StatusData = 'AKTIF'";
    $params = [];
    if (!$is_sa && !empty($unit_up)) {
        $sql .= " AND f.UnitUp = ?";
        $params[] = $unit_up;
    }
    if (!empty($q)) {
        $sql .= " AND (d.NoRef LIKE ? OR d.ImeiModem LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    $sql .= " ORDER BY f.WaktuData DESC LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data_riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>

<div class="card border-0 shadow-sm rounded-4 bg-white p-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 px-2 pt-2">
        <div>
            <h5 class="fw-bold text-primary mb-0">Monitoring Riwayat Pengujian Perangkat</h5>
            <p class="text-muted small mb-0">Log detail hasil pengujian fisik dan fungsional alat S41.</p>
        </div>
        <div>
            <form action="index.php" method="GET" class="input-group input-group-sm">
                <input type="hidden" name="page" value="pemasangan">
                <input type="hidden" name="menu" value="monitoring">
                <input type="hidden" name="sub" value="riwayat_pengujian">
                <input type="text" class="form-control" name="q" placeholder="Cari NoRef / IMEI..." value="<?= htmlspecialchars($q) ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-dark border-top border-bottom">
                <tr>
                    <th class="ps-3" style="width:5%;">#</th>
                    <th>NO FORMULIR</th>
                    <th>NO REF ALAT</th>
                    <th>SIM ID / IMEI</th>
                    <th>KONDISI PHASA</th>
                    <th>LAMPU INDIKATOR</th>
                    <th>TGL UJI</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data_riwayat)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada riwayat pengujian.</td></tr>
                <?php else: ?>
                    <?php foreach ($data_riwayat as $idx => $row): ?>
                        <tr>
                            <td class="ps-3 text-muted"><?= $idx + 1 ?></td>
                            <td class="fw-bold font-monospace text-primary"><?= htmlspecialchars($row['NoFormulir']) ?></td>
                            <td class="font-monospace text-dark"><?= htmlspecialchars($row['NoRef']) ?></td>
                            <td class="font-monospace text-muted small">
                                <div>SIM: <?= htmlspecialchars($row['SimId'] ?? '-') ?></div>
                                <div>IMEI: <?= htmlspecialchars($row['ImeiModem'] ?? '-') ?></div>
                            </td>
                            <td><?= htmlspecialchars($row['TeganganPhasa'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['LampuIndikator'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['TglUji'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>