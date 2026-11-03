<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    require_once __DIR__ . '/../../config/database.php';
}

$data_mon_mutasi = [];
$q = trim($_GET['q'] ?? '');

try {
    $unit_up = $_SESSION['UnitUp'] ?? '';
    $sql = "SELECT b.NoFba as NoFormPasang, b.TglFba as TglPasang, b.IdPel, bd.NoRef, bd.PosisiPasang 
            FROM frm_ba b
            JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.StatusPekerjaan = 'PASANG'
            WHERE b.JenisPekerjaan = 'PEMASANGAN'";
    $params = [];
    if (!empty($unit_up) && $_SESSION['KodeHak'] !== 'SA.KP') {
        $sql .= " AND b.UnitUp = ?";
        $params[] = $unit_up;
    }
    if (!empty($q)) {
        $sql .= " AND (b.NoFba LIKE ? OR b.IdPel LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    $sql .= " ORDER BY b.WaktuData DESC LIMIT 50";
    
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
                            <td class="ps-3 text-muted"><?= $idx + 1 ?></td>
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
</div>