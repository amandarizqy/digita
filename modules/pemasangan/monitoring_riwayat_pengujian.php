<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($conn)) require_once __DIR__ . '/../../config/database.php';

$data_riwayat = [];
$q = trim($_GET['q'] ?? '');

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page  = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($limit < 1) $limit = 10;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    $unit_up = $_SESSION['UnitUp'] ?? '';
    $is_sa   = ($_SESSION['KodeHak'] ?? '') === 'SA.KP';
    
    $where_clause = "WHERE f.StatusData = 'AKTIF'";
    $params = [];
    
    if (!$is_sa && !empty($unit_up)) {
        $where_clause .= " AND f.UnitUp = ?";
        $params[] = $unit_up;
    }
    if (!empty($q)) {
        $where_clause .= " AND (d.NoRef LIKE ? OR d.ImeiModem LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    
    $sql_count = "SELECT COUNT(*) FROM formulir_pengujian_detil d JOIN formulir_pengujian f ON f.NoFormulir = d.NoFormulir $where_clause";
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->execute($params);
    $total_data = $stmt_count->fetchColumn();
    $total_pages = ceil($total_data / $limit);
    
    $sql = "SELECT d.*, f.TglUji, f.NamaAkun, f.UnitUp 
            FROM formulir_pengujian_detil d
            JOIN formulir_pengujian f ON f.NoFormulir = d.NoFormulir
            $where_clause
            ORDER BY f.WaktuData DESC LIMIT $limit OFFSET $offset";
    
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
                <?php if(isset($_GET['limit'])): ?><input type="hidden" name="limit" value="<?= htmlspecialchars($_GET['limit']) ?>"><?php endif; ?>
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
                            <td class="ps-3 text-muted"><?= $offset + $idx + 1 ?></td>
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

    <div class="d-flex justify-content-between align-items-center mt-3 px-2">
        <div class="d-flex align-items-center gap-2">
            <span class="small text-muted">Tampilkan</span>
            <select class="form-select form-select-sm w-auto" onchange="window.location.href=this.value">
                <option value="?page=pemasangan&menu=monitoring&sub=riwayat_pengujian&limit=5&q=<?= urlencode($q) ?>" <?= $limit == 5 ? 'selected' : '' ?>>5</option>
                <option value="?page=pemasangan&menu=monitoring&sub=riwayat_pengujian&limit=10&q=<?= urlencode($q) ?>" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                <option value="?page=pemasangan&menu=monitoring&sub=riwayat_pengujian&limit=20&q=<?= urlencode($q) ?>" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
                <option value="?page=pemasangan&menu=monitoring&sub=riwayat_pengujian&limit=30&q=<?= urlencode($q) ?>" <?= $limit == 30 ? 'selected' : '' ?>>30</option>
            </select>
            <span class="small text-muted">dari <?= $total_data ?> data</span>
        </div>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?page=pemasangan&menu=monitoring&sub=riwayat_pengujian&limit=<?= $limit ?>&p=<?= $page - 1 ?>&q=<?= urlencode($q) ?>">Prev</a></li>
                <?php 
                $start_page = max(1, $page - 2); $end_page = min($total_pages, $page + 2);
                for($i = $start_page; $i <= $end_page; $i++): 
                ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="?page=pemasangan&menu=monitoring&sub=riwayat_pengujian&limit=<?= $limit ?>&p=<?= $i ?>&q=<?= urlencode($q) ?>"><?= $i ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>"><a class="page-link" href="?page=pemasangan&menu=monitoring&sub=riwayat_pengujian&limit=<?= $limit ?>&p=<?= $page + 1 ?>&q=<?= urlencode($q) ?>">Next</a></li>
            </ul>
        </nav>
    </div>
</div>