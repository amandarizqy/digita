<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conn)) {
    require_once __DIR__ . '/../../config/database.php';
}

$user_role  = $_SESSION['KodeHak'] ?? $_SESSION['Role'] ?? 'GUEST';
$session_ap = $_SESSION['UnitAp'] ?? '';
$session_up = $_SESSION['UnitUp'] ?? '';

$data_mon_mutasi = [];
$pesan_error_monmut = '';
try {
    $where_clauses = [];
    $params_query = [];

    if (!in_array($user_role, ['SA.KP', 'SF.UI'])) {
        if (!empty($session_up)) {
            $where_clauses[] = "p.UnitUp = ?";
            $params_query[] = $session_up;
        } elseif (!empty($session_ap)) {
            $where_clauses[] = "p.UnitAp = ?";
            $params_query[] = $session_ap;
        }
    }

    $where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

    $sql_mon_mut = "SELECT p.*, b.SimId AS SimBarang, b.ImeiModem AS ImeiBarang 
                    FROM frm_pemeliharaan p 
                    LEFT JOIN master_barang b ON p.NoRef = b.NoRef 
                    $where_sql
                    ORDER BY p.WaktuData DESC LIMIT 100";
    $stmt_mon_mut = $conn->prepare($sql_mon_mut);
    $stmt_mon_mut->execute($params_query);
    $data_mon_mutasi = $stmt_mon_mut->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pesan_error_monmut = "Gagal memuat data monitoring mutasi: " . $e->getMessage();
}
?>

<style>
    .table-compact thead th { font-size: 0.75rem !important; font-weight: 700 !important; color: #6c757d !important; padding: 0.6rem 0.35rem !important; }
    .table-compact tbody td { padding: 0.5rem 0.35rem !important; font-size: 0.81rem !important; word-break: break-word !important; }
    .table-compact .badge { font-size: 0.7rem !important; padding: 0.3em 0.45em !important; }
</style>

<!-- Iframe Tersembunyi untuk Download Tanpa Trigger Spinner -->
<iframe id="iframeDownloadMutasi" style="display: none;"></iframe>

<?php if (!empty($pesan_error_monmut)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($pesan_error_monmut) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 bg-white p-3">
    <div class="d-flex justify-content-between align-items-center mb-3 px-2 pt-2">
        <div>
            <h5 class="fw-bold text-primary mb-0">Monitoring Laporan Mutasi Pemeliharaan</h5>
            <p class="text-muted small mb-0">Rekapitulasi riwayat pergantian kartu SIM dan Modem di lokasi pelanggan.</p>
        </div>
        <div class="d-flex gap-2">
            <div class="input-group style-search" style="width: 240px;">
                <span class="input-group-text bg-light border-end-0 rounded-start-3 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="searchMonMutasi" class="form-control bg-light border-start-0 rounded-end-3 text-muted small" placeholder="Cari data mutasi...">
            </div>
            <button type="button" id="btnExportMutasi" class="btn btn-outline-success rounded-3 fw-medium">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
            </button>
        </div>
    </div>

    <div class="w-100">
        <table class="table table-hover align-middle mb-0 table-compact w-100" id="tableMonMutasi">
            <thead class="table-light text-dark border-top border-bottom">
                <tr>
                    <th class="ps-2 text-center" style="width: 3%;">#</th>
                    <th style="width: 18%;">NO FORM PEMELIHARAAN</th>
                    <th style="width: 12%;">TGL PEMELIHARAAN</th>
                    <th style="width: 15%;">IDPEL / NO REF</th>
                    <th style="width: 15%;">SIM ID TERPASANG</th>
                    <th style="width: 15%;">IMEI MODEM TERPASANG</th>
                    <th style="width: 10%;">STATUS MUTASI</th>
                    <th class="pe-2" style="width: 12%;">WAKTU DATA</th>
                </tr>
            </thead>
            <tbody class="fw-medium text-dark">
                <?php if (empty($data_mon_mutasi)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada laporan mutasi pemeliharaan.</td></tr>
                <?php else: ?>
                    <?php foreach ($data_mon_mutasi as $idx => $row): ?>
                        <tr class="border-bottom">
                            <td class="ps-2 text-center text-muted small"><?= $idx + 1 ?></td>
                            <td class="fw-bold text-dark font-monospace"><?= htmlspecialchars($row['NoFormPemeliharaan']) ?></td>
                            <td class="text-secondary"><?= htmlspecialchars($row['TglPemeliharaan']) ?></td>
                            <td>
                                <div class="fw-bold text-dark">IDPEL: <?= htmlspecialchars($row['IdPel']) ?></div>
                                <div class="small text-muted">REF: <?= htmlspecialchars($row['NoRef']) ?></div>
                            </td>
                            <td>
                                <?php if (!empty($row['SimId'])): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info-emphasis border border-info-subtle font-monospace">
                                        <?= htmlspecialchars($row['SimId']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted fst-italic small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="font-monospace text-muted"><?= htmlspecialchars($row['ImeiModem'] ?? '-') ?></td>
                            <td>
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">TERMUTASI</span>
                            </td>
                            <td class="pe-2 font-monospace text-secondary small"><?= htmlspecialchars($row['WaktuData'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Pencarian tabel
    const searchMonMut = document.getElementById("searchMonMutasi");
    if (searchMonMut) {
        searchMonMut.addEventListener("keyup", function() {
            const filter = searchMonMut.value.toLowerCase();
            document.querySelectorAll("#tableMonMutasi tbody tr").forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(filter) ? "" : "none";
            });
        });
    }

    // Trigger unduh Excel via iframe
    const btnExport = document.getElementById("btnExportMutasi");
    if (btnExport) {
        btnExport.addEventListener("click", function(e) {
            e.preventDefault();
            document.getElementById("iframeDownloadMutasi").src = "modules/pemeliharaan/export_excel.php?type=mutasi";
        });
    }
});
</script>