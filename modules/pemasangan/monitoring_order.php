<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($conn)) require_once __DIR__ . '/../../config/database.php';

$data_mon_order = [];
$q = trim($_GET['q'] ?? '');

try {
    $unit_up = $_SESSION['UnitUp'] ?? '';
    $is_sa   = ($_SESSION['KodeHak'] ?? '') === 'SA.KP';
    
    $sql = "SELECT o.NoFormOrder, o.TglOrder, o.NamaAkun, o.StatusData, p.NoFpk, p.IdPel, p.NomorBarang as NoRef, p.SimId, p.ImeiModem
            FROM frm_order o
            JOIN frm_order_detil od ON od.NoFormOrder = o.NoFormOrder
            JOIN frm_pk p ON p.NoFpk = od.NoFpk
            WHERE p.JenisPk = 'PEMASANGAN'";
    $params = [];
    if (!$is_sa && !empty($unit_up)) {
        $sql .= " AND o.UnitUp = ?";
        $params[] = $unit_up;
    }
    if (!empty($q)) {
        $sql .= " AND (o.NoFormOrder LIKE ? OR p.IdPel LIKE ? OR p.NoFpk LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    $sql .= " ORDER BY o.WaktuData DESC LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data_mon_order = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>

<div class="card border-0 shadow-sm rounded-4 bg-white p-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 px-2 pt-2">
        <div>
            <h5 class="fw-bold text-primary mb-0">Monitoring Laporan Order Pemasangan</h5>
            <p class="text-muted small mb-0">Rekapitulasi FPK dan Surat Perintah Kerja (SPK) Pemasangan.</p>
        </div>
        <div>
            <form action="index.php" method="GET" class="input-group input-group-sm">
                <input type="hidden" name="page" value="pemasangan">
                <input type="hidden" name="menu" value="monitoring">
                <input type="hidden" name="sub" value="monitoring_order">
                <input type="text" class="form-control" name="q" placeholder="Cari Order / IdPel..." value="<?= htmlspecialchars($q) ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-dark border-top border-bottom">
                <tr>
                    <th class="ps-3" style="width:5%;">#</th>
                    <th>NO ORDER / FPK</th>
                    <th>TGL ORDER</th>
                    <th>IDPEL / NO REF</th>
                    <th>SIM ID</th>
                    <th>IMEI MODEM</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data_mon_order)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data monitoring order pasang.</td></tr>
                <?php else: ?>
                    <?php foreach ($data_mon_order as $idx => $row): ?>
                        <tr>
                            <td class="ps-3 text-muted"><?= $idx + 1 ?></td>
                            <td>
                                <div class="font-monospace text-secondary small">ORDER: <?= htmlspecialchars($row['NoFormOrder']) ?></div>
                                <div class="fw-bold font-monospace text-primary">FPK: <?= htmlspecialchars($row['NoFpk']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($row['TglOrder']) ?></td>
                            <td class="font-monospace">
                                <div>IDPEL: <?= htmlspecialchars($row['IdPel']) ?></div>
                                <small class="text-muted">REF: <?= htmlspecialchars($row['NoRef'] ?? '-') ?></small>
                            </td>
                            <td class="font-monospace"><?= htmlspecialchars($row['SimId'] ?? '-') ?></td>
                            <td class="font-monospace text-muted"><?= htmlspecialchars($row['ImeiModem'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>