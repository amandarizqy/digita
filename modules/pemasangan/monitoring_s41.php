<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($conn)) require_once __DIR__ . '/../../config/database.php';

$data_s41 = [];
$q = trim($_GET['q'] ?? '');

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page  = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($limit < 1) $limit = 10;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    $kode_hak = $_SESSION['KodeHak'] ?? $_SESSION['Role'] ?? '';
    $unit_up  = trim((string)($_SESSION['UnitUp'] ?? ''));
    $unit_ap  = trim((string)($_SESSION['UnitAp'] ?? ''));
    $unit_upi = trim((string)($_SESSION['UnitUpi'] ?? ''));
    $is_sa    = ($kode_hak === 'SA.KP');

    $where  = [];
    $params = [];

    // Filter unit sesuai user yang login
    if (!$is_sa) {
        if ($unit_up !== '') {
            $where[]  = "UnitUp = ?";
            $params[] = $unit_up;
        } elseif ($unit_ap !== '') {
            $where[]  = "UnitAp = ?";
            $params[] = $unit_ap;
        } elseif ($unit_upi !== '') {
            $where[]  = "UnitUpi = ?";
            $params[] = $unit_upi;
        } else {
            $where[] = "1 = 0";
        }
    }

    if ($q !== '') {
        $where[]  = "(NoRef LIKE ? OR IdPel LIKE ? OR Nama LIKE ? OR SimIdBarang LIKE ? OR ImeiModem LIKE ? OR NomorBarang LIKE ? OR NomorModem LIKE ?)";
        $like_q   = "%$q%";
        $params[] = $like_q;
        $params[] = $like_q;
        $params[] = $like_q;
        $params[] = $like_q;
        $params[] = $like_q;
        $params[] = $like_q;
        $params[] = $like_q;
    }

    $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $sql_count = "SELECT COUNT(*) FROM vw_terpasang_s41 $where_clause";
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->execute($params);
    $total_data = (int)$stmt_count->fetchColumn();
    $total_pages = max(1, (int)ceil($total_data / $limit));

    $sql = "SELECT NoRef, SimIdBarang, ImeiModem, StatusRelay, IdPel, Nama, GolTar, Daya,
                   UnitUp, UnitAp, UnitUpi, SimIdModem, NomorBarang, NomorModem
            FROM vw_terpasang_s41
            $where_clause
            ORDER BY UnitUp ASC, NoRef ASC
            LIMIT $limit OFFSET $offset";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data_s41 = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error SQL: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<div class="card border-0 shadow-sm rounded-4 bg-white p-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 px-2 pt-2">
        <div>
            <h5 class="fw-bold text-primary mb-0">Monitoring Perangkat S41 Terpasang</h5>
            <p class="text-muted small mb-0">Daftar perangkat S41 dan modem yang aktif terpasang pada pelanggan sesuai wilayah unit Anda.</p>
        </div>
        <div>
            <form action="index.php" method="GET" class="input-group input-group-sm">
                <input type="hidden" name="page" value="pemasangan">
                <input type="hidden" name="menu" value="monitoring">
                <input type="hidden" name="sub" value="monitoring_s41">
                <?php if (isset($_GET['limit'])): ?><input type="hidden" name="limit" value="<?= htmlspecialchars($_GET['limit']) ?>"><?php endif; ?>
                <input type="text" class="form-control" name="q" placeholder="Cari NoRef / IdPel / Nama..." value="<?= htmlspecialchars($q) ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-dark border-top border-bottom">
                <tr>
                    <th class="ps-3" style="width:4%;">#</th>
                    <th>NO REF S41</th>
                    <th>PELANGGAN</th>
                    <th>TARIF / DAYA</th>
                    <th>SIM &amp; NOMOR S41</th>
                    <th>IMEI &amp; SIM MODEM</th>
                    <th>NOMOR MODEM</th>
                    <th class="text-center">UNIT UP</th>
                    <th class="text-center pe-3">STATUS RELAY</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data_s41)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">Belum ada data perangkat S41 terpasang pada unit ini.</td></tr>
                <?php else: ?>
                    <?php foreach ($data_s41 as $idx => $row): ?>
                        <?php
                        $relay   = strtoupper(trim((string)($row['StatusRelay'] ?? '')));
                        $goltar  = trim((string)($row['GolTar'] ?? ''));
                        $daya    = ($row['Daya'] !== null && $row['Daya'] !== '') ? number_format((float)$row['Daya'], 0, ',', '.') . ' VA' : '-';
                        $tarif_daya = ($goltar !== '' ? $goltar : '-') . ' / ' . $daya;
                        ?>
                        <tr>
                            <td class="ps-3 text-muted"><?= $offset + $idx + 1 ?></td>
                            <td class="fw-bold font-monospace text-primary"><?= htmlspecialchars($row['NoRef']) ?></td>
                            <td>
                                <div class="font-monospace fw-semibold"><?= htmlspecialchars($row['IdPel'] ?? '-') ?></div>
                                <small class="text-dark fw-medium"><?= htmlspecialchars($row['Nama'] ?? '-') ?></small>
                            </td>
                            <td class="small fw-semibold text-nowrap"><?= htmlspecialchars($tarif_daya) ?></td>
                            <td class="font-monospace small">
                                <div><?= htmlspecialchars($row['SimIdBarang'] ?? '-') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($row['NomorBarang'] ?? '-') ?></small>
                            </td>
                            <td class="font-monospace small">
                                <div><?= htmlspecialchars($row['ImeiModem'] ?? '-') ?></div>
                                <small class="text-muted">SIM: <?= htmlspecialchars($row['SimIdModem'] ?? '-') ?></small>
                            </td>
                            <td class="font-monospace small"><?= htmlspecialchars($row['NomorModem'] ?? '-') ?></td>
                            <td class="text-center fw-semibold"><?= htmlspecialchars($row['UnitUp'] ?? '-') ?></td>
                            <td class="text-center pe-3">
                                <?php if ($relay === 'ON'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-1">
                                        <i class="bi bi-lightning-charge-fill me-1"></i> ON
                                    </span>
                                <?php elseif ($relay === 'OFF'): ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-1">
                                        <i class="bi bi-power me-1"></i> OFF
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-3 py-1">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3 px-2">
        <div class="d-flex align-items-center gap-2">
            <span class="small text-muted">Tampilkan</span>
            <select class="form-select form-select-sm w-auto" onchange="window.location.href=this.value">
                <option value="?page=pemasangan&menu=monitoring&sub=monitoring_s41&limit=5&q=<?= urlencode($q) ?>" <?= $limit == 5 ? 'selected' : '' ?>>5</option>
                <option value="?page=pemasangan&menu=monitoring&sub=monitoring_s41&limit=10&q=<?= urlencode($q) ?>" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                <option value="?page=pemasangan&menu=monitoring&sub=monitoring_s41&limit=20&q=<?= urlencode($q) ?>" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
                <option value="?page=pemasangan&menu=monitoring&sub=monitoring_s41&limit=30&q=<?= urlencode($q) ?>" <?= $limit == 30 ? 'selected' : '' ?>>30</option>
            </select>
            <span class="small text-muted">dari <?= $total_data ?> data</span>
        </div>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=pemasangan&menu=monitoring&sub=monitoring_s41&limit=<?= $limit ?>&p=<?= $page - 1 ?>&q=<?= urlencode($q) ?>">Prev</a>
                </li>
                <?php
                $start_page = max(1, $page - 2);
                $end_page   = min($total_pages, $page + 2);
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=pemasangan&menu=monitoring&sub=monitoring_s41&limit=<?= $limit ?>&p=<?= $i ?>&q=<?= urlencode($q) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=pemasangan&menu=monitoring&sub=monitoring_s41&limit=<?= $limit ?>&p=<?= $page + 1 ?>&q=<?= urlencode($q) ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
</div>