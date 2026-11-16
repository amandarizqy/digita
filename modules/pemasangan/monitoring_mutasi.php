<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    require_once __DIR__ . '/../../config/database.php';
}

$data_mon_mutasi = [];
$q = trim($_GET['q'] ?? '');

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page  = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($limit < 1) $limit = 10;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    $unit_up = $_SESSION['UnitUp'] ?? '';
    
    // BUG FIX: Ubah 'PEMASANGAN' menjadi 'PASANG'
    $where_clause = "WHERE b.JenisPekerjaan = 'PASANG'";
    $params = [];
    
    if (!empty($unit_up) && $_SESSION['KodeHak'] !== 'SA.KP') {
        $where_clause .= " AND b.UnitUp = ?";
        $params[] = $unit_up;
    }
    if (!empty($q)) {
        $where_clause .= " AND (b.NoFba LIKE ? OR b.IdPel LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    
    $sql_count = "SELECT COUNT(*) FROM frm_ba b JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.StatusPekerjaan = 'PASANG' $where_clause";
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->execute($params);
    $total_data = $stmt_count->fetchColumn();
    $total_pages = ceil($total_data / $limit);
    
    $sql = "SELECT b.NoFba as NoFormPasang, b.TglFba as TglPasang, b.IdPel, bd.NoRef, bd.PosisiPasang 
            FROM frm_ba b
            JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.StatusPekerjaan = 'PASANG'
            $where_clause
            ORDER BY b.WaktuData DESC LIMIT $limit OFFSET $offset";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data_mon_mutasi = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>

<div class="card border-0 shadow-sm rounded-4 bg-white p-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 px-2 pt-2">
        <div>
            <h5 class="fw-bold text-primary mb-0">Monitoring Laporan Mutasi Pasang</h5>
            <p class="text-muted small mb-0">Riwayat Berita Acara (BA) dan realisasi pemasangan perangkat S41.</p>
        </div>
        <div>
            <form action="index.php" method="GET" class="input-group input-group-sm">
                <input type="hidden" name="page" value="pemasangan">
                <input type="hidden" name="menu" value="monitoring">
                <input type="hidden" name="sub" value="monitoring_mutasi">
                <?php if(isset($_GET['limit'])): ?><input type="hidden" name="limit" value="<?= htmlspecialchars($_GET['limit']) ?>"><?php endif; ?>
                <input type="text" class="form-control" name="q" placeholder="Cari No BA / IdPel..." value="<?= htmlspecialchars($q) ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-dark border-top border-bottom">
                <tr>
                    <th class="ps-3" style="width:5%;">#</th>
                    <th>NO FORM PASANG (BA)</th>
                    <th>TGL PASANG</th>
                    <th>IDPEL</th>
                    <th>NO REF ALAT</th>
                    <th>POSISI PASANG</th>
                    <th class="text-center pe-3">STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data_mon_mutasi)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data monitoring mutasi pasang.</td></tr>
                <?php else: ?>
                    <?php foreach ($data_mon_mutasi as $idx => $row): ?>
                        <tr>
                            <td class="ps-3 text-muted"><?= $offset + $idx + 1 ?></td>
                            <td class="fw-bold font-monospace text-primary"><?= htmlspecialchars($row['NoFormPasang']) ?></td>
                            <td><?= htmlspecialchars($row['TglPasang']) ?></td>
                            <td class="font-monospace"><?= htmlspecialchars($row['IdPel'] ?? '-') ?></td>
                            <td class="font-monospace text-muted"><?= htmlspecialchars($row['NoRef']) ?></td>
                            <td><?= htmlspecialchars($row['PosisiPasang'] ?? '-') ?></td>
                            <td class="text-center">
                                <span class="badge bg-success bg-opacity-10 text-success">AKTIF / TERPASANG</span>
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
                <option value="?page=pemasangan&menu=monitoring&sub=monitoring_mutasi&limit=5&q=<?= urlencode($q) ?>" <?= $limit == 5 ? 'selected' : '' ?>>5</option>
                <option value="?page=pemasangan&menu=monitoring&sub=monitoring_mutasi&limit=10&q=<?= urlencode($q) ?>" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                <option value="?page=pemasangan&menu=monitoring&sub=monitoring_mutasi&limit=20&q=<?= urlencode($q) ?>" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
                <option value="?page=pemasangan&menu=monitoring&sub=monitoring_mutasi&limit=30&q=<?= urlencode($q) ?>" <?= $limit == 30 ? 'selected' : '' ?>>30</option>
            </select>
            <span class="small text-muted">dari <?= $total_data ?> data</span>
        </div>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?page=pemasangan&menu=monitoring&sub=monitoring_mutasi&limit=<?= $limit ?>&p=<?= $page - 1 ?>&q=<?= urlencode($q) ?>">Prev</a></li>
                <?php 
                $start_page = max(1, $page - 2); $end_page = min($total_pages, $page + 2);
                for($i = $start_page; $i <= $end_page; $i++): 
                ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="?page=pemasangan&menu=monitoring&sub=monitoring_mutasi&limit=<?= $limit ?>&p=<?= $i ?>&q=<?= urlencode($q) ?>"><?= $i ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>"><a class="page-link" href="?page=pemasangan&menu=monitoring&sub=monitoring_mutasi&limit=<?= $limit ?>&p=<?= $page + 1 ?>&q=<?= urlencode($q) ?>">Next</a></li>
            </ul>
        </nav>
    </div>
</div>