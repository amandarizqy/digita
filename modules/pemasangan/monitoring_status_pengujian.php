<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($conn)) require_once __DIR__ . '/../../config/database.php';

$data_mon = [];
$q = trim($_GET['q'] ?? '');

try {
    $unit_up = $_SESSION['UnitUp'] ?? '';
    $is_sa   = ($_SESSION['KodeHak'] ?? '') === 'SA.KP';
    
    $sql = "SELECT f.NoFormulir, f.TglUji, f.NamaAkun, f.StatusData, f.UnitUp,
                   (SELECT COUNT(NoRef) FROM formulir_pengujian_detil d WHERE d.NoFormulir = f.NoFormulir) as TotalAlat
            FROM formulir_pengujian f
            WHERE f.StatusData = 'AKTIF'";
    $params = [];
    if (!$is_sa && !empty($unit_up)) {
        $sql .= " AND f.UnitUp = ?";
        $params[] = $unit_up;
    }
    if (!empty($q)) {
        $sql .= " AND f.NoFormulir LIKE ?";
        $params[] = "%$q%";
    }
    $sql .= " ORDER BY f.WaktuData DESC LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data_mon = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>

<div class="card border-0 shadow-sm rounded-4 bg-white p-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 px-2 pt-2">
        <div>
            <h5 class="fw-bold text-primary mb-0">Monitoring Status Pengujian Perangkat</h5>
            <p class="text-muted small mb-0">Pemantauan formulir pengujian pra-eksekusi perangkat S41 yang telah difinalisasi.</p>
        </div>
        <div>
            <form action="index.php" method="GET" class="input-group input-group-sm">
                <input type="hidden" name="page" value="pemasangan">
                <input type="hidden" name="menu" value="monitoring">
                <input type="hidden" name="sub" value="status_pengujian">
                <input type="text" class="form-control" name="q" placeholder="Cari No Formulir..." value="<?= htmlspecialchars($q) ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-dark border-top border-bottom">
                <tr>
                    <th class="ps-3" style="width:5%;">#</th>
                    <th>NO FORMULIR UJI</th>
                    <th>TGL UJI</th>
                    <th>UNIT UP</th>
                    <th>PENGUJI</th>
                    <th class="text-center">TOTAL ALAT</th>
                    <th class="text-center pe-3">STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data_mon)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data status pengujian.</td></tr>
                <?php else: ?>
                    <?php foreach ($data_mon as $idx => $row): ?>
                        <tr>
                            <td class="ps-3 text-muted"><?= $idx + 1 ?></td>
                            <td class="fw-bold font-monospace text-primary"><?= htmlspecialchars($row['NoFormulir']) ?></td>
                            <td><?= htmlspecialchars($row['TglUji']) ?></td>
                            <td><?= htmlspecialchars($row['UnitUp']) ?></td>
                            <td><?= htmlspecialchars($row['NamaAkun']) ?></td>
                            <td class="text-center fw-bold"><?= $row['TotalAlat'] ?> Unit</td>
                            <td class="text-center">
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-1">
                                    <i class="bi bi-check-circle me-1"></i> LOLOS UJI
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>