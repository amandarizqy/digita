<?php
// Ambil parameter paginasi & pencarian
$q = trim($_GET['q'] ?? '');
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page  = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($limit < 1) $limit = 10;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Set parameter hak akses
$unit_up = $_SESSION['UnitUp'] ?? '';
$is_sa   = ($_SESSION['KodeHak'] ?? '') === 'SA.KP';
$where_clause = "WHERE 1=1";
$params = [];

if (!$is_sa && !empty($unit_up)) {
    $where_clause .= " AND f.UnitUp = ?";
    $params[] = $unit_up;
}
if (!empty($q)) {
    $where_clause .= " AND f.NoFormulir LIKE ?";
    $params[] = "%$q%";
}

// 1. Hitung total data untuk Paginasi
$sql_count = "SELECT COUNT(*) FROM formulir_pengujian f $where_clause";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->execute($params);
$total_data = $stmt_count->fetchColumn();
$total_pages = ceil($total_data / $limit);

// 2. Query Utama dengan Limit dan Offset
$sql = "SELECT f.NoFormulir, f.TglUji, f.NamaAkun, f.StatusData, f.WaktuData,
               (SELECT COUNT(NoRef) FROM formulir_pengujian_detil d WHERE d.NoFormulir = f.NoFormulir) as TotalAlat
        FROM formulir_pengujian f
        $where_clause
        ORDER BY f.WaktuData DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$list_pengujian = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card shadow mb-4 border-0">
    <div class="card-body p-0">
        <form action="index.php" method="GET" class="mb-3 px-4 pt-3">
            <input type="hidden" name="page" value="pemasangan">
            <input type="hidden" name="menu" value="pra_eksekusi">
            <input type="hidden" name="sub" value="pengujian">
            <input type="hidden" name="view" value="daftar">
            <?php if(isset($_GET['limit'])): ?><input type="hidden" name="limit" value="<?= htmlspecialchars($_GET['limit']) ?>"><?php endif; ?>
            <div class="input-group input-group-sm" style="max-width: 340px;">
                <input type="text" class="form-control" name="q" placeholder="Cari No Formulir..." value="<?= htmlspecialchars($q) ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted">
                    <tr>
                        <th class="fw-bold border-bottom-0 py-3 ps-4" style="width:5%;">#</th>
                        <th class="fw-bold border-bottom-0 py-3">NO FORMULIR</th>
                        <th class="fw-bold border-bottom-0 py-3">TGL UJI</th>
                        <th class="fw-bold border-bottom-0 py-3 text-center">TOTAL ALAT</th>
                        <th class="fw-bold border-bottom-0 py-3">PENGUJI</th>
                        <th class="fw-bold border-bottom-0 py-3 text-center">STATUS</th>
                        <th class="fw-bold border-bottom-0 py-3 text-center pe-4">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($list_pengujian)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data pengujian.</td></tr>
                    <?php else: ?>
                        <?php foreach ($list_pengujian as $idx => $row): ?>
                            <tr>
                                <td class="ps-4 text-muted"><?= $offset + $idx + 1 ?></td>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($row['NoFormulir']) ?></td>
                                <td><?= htmlspecialchars($row['TglUji']) ?></td>
                                <td class="text-center fw-bold"><?= $row['TotalAlat'] ?> Unit</td>
                                <td><i class="bi bi-person me-1 text-muted"></i><?= htmlspecialchars($row['NamaAkun']) ?></td>
                                <td class="text-center">
                                    <?php if($row['StatusData'] == 'AKTIF'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2"><i class="bi bi-check-circle me-1"></i> Selesai</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-3 py-2"><i class="bi bi-pencil-square me-1"></i> Draft</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center pe-4">
                                    <div class="d-flex justify-content-center gap-1">
                                        <a href="index.php?page=pemasangan&menu=pra_eksekusi&sub=pengujian&view=detail&no_form=<?= urlencode($row['NoFormulir']) ?>" class="btn btn-sm btn-outline-primary" title="Buka Detail">
                                            <i class="bi <?= ($row['StatusData'] == 'AKTIF') ? 'bi-eye' : 'bi-pencil' ?>"></i> Detail
                                        </a>
                                        <?php if($row['StatusData'] == 'TIDAK'): ?>
                                            <a href="javascript:void(0);" onclick="confirmDeleteDraft('<?= urlencode($row['NoFormulir']) ?>')" class="btn btn-sm btn-outline-danger" title="Hapus Draft">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginasi -->
        <div class="d-flex justify-content-between align-items-center p-3 border-top bg-white">
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted">Tampilkan</span>
                <select class="form-select form-select-sm w-auto" onchange="window.location.href=this.value">
                    <option value="?page=pemasangan&menu=pra_eksekusi&sub=pengujian&view=daftar&limit=5&q=<?= urlencode($q) ?>" <?= $limit == 5 ? 'selected' : '' ?>>5</option>
                    <option value="?page=pemasangan&menu=pra_eksekusi&sub=pengujian&view=daftar&limit=10&q=<?= urlencode($q) ?>" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                    <option value="?page=pemasangan&menu=pra_eksekusi&sub=pengujian&view=daftar&limit=20&q=<?= urlencode($q) ?>" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
                    <option value="?page=pemasangan&menu=pra_eksekusi&sub=pengujian&view=daftar&limit=30&q=<?= urlencode($q) ?>" <?= $limit == 30 ? 'selected' : '' ?>>30</option>
                </select>
                <span class="small text-muted">dari <?= $total_data ?> data</span>
            </div>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?page=pemasangan&menu=pra_eksekusi&sub=pengujian&view=daftar&limit=<?= $limit ?>&p=<?= $page - 1 ?>&q=<?= urlencode($q) ?>">Prev</a></li>
                    <?php 
                    $start_page = max(1, $page - 2); $end_page = min($total_pages, $page + 2);
                    for($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="?page=pemasangan&menu=pra_eksekusi&sub=pengujian&view=daftar&limit=<?= $limit ?>&p=<?= $i ?>&q=<?= urlencode($q) ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>"><a class="page-link" href="?page=pemasangan&menu=pra_eksekusi&sub=pengujian&view=daftar&limit=<?= $limit ?>&p=<?= $page + 1 ?>&q=<?= urlencode($q) ?>">Next</a></li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<script>
function confirmDeleteDraft(noForm) {
    Swal.fire({
        title: 'Hapus Draft Pengujian?',
        text: "Data formulir pengujian ini akan dihapus permanen.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "modules/pemasangan/proses_pengujian.php?action=hapus_draft&no_form=" + noForm;
        }
    });
}
</script>