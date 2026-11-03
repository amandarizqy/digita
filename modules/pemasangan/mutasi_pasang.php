<?php
$unit_up = $_SESSION['UnitUp'] ?? '';
$view = $_GET['view'] ?? 'daftar';
$q = trim($_GET['q'] ?? '');

if ($view === 'daftar') {
    $sql = "SELECT o.NoFormOrder, o.TglOrder, o.NamaAkun, o.StatusData, o.WaktuData,
                   (SELECT COUNT(NoFpk) FROM frm_order_detil d WHERE d.NoFormOrder = o.NoFormOrder) as TotalTarget,
                   (SELECT COUNT(d.NoFpk) FROM frm_order_detil d 
                    JOIN frm_ba b ON d.NoFpk = b.NoFpk AND b.JenisPekerjaan = 'PEMASANGAN'
                    WHERE d.NoFormOrder = o.NoFormOrder AND b.StatusData = 'AKTIF') as TotalSelesai
            FROM frm_order o
            WHERE o.UnitUp = :up AND (o.NoFormOrder LIKE :q)
            ORDER BY o.WaktuData DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':up' => $unit_up, ':q' => "%$q%"]);
    $list_mutasi = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card border-0 shadow-sm rounded-4 bg-white p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="fw-bold text-primary mb-1">Mutasi & Eksekusi Pemasangan Lapangan</h5>
            <p class="text-muted small mb-0">Pencatatan Berita Acara (BA) pemasangan perangkat S41 berdasarkan Surat Perintah Kerja (SPK).</p>
        </div>
        <div>
            <form action="index.php" method="GET" class="input-group input-group-sm">
                <input type="hidden" name="page" value="pemasangan">
                <input type="hidden" name="menu" value="eksekusi">
                <input type="hidden" name="sub" value="mutasi_pasang">
                <input type="text" class="form-control" name="q" placeholder="Cari No Order..." value="<?= htmlspecialchars($q) ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-dark border-top border-bottom">
                <tr>
                    <th class="ps-3" style="width:5%;">#</th>
                    <th>NO FORM ORDER</th>
                    <th>TGL ORDER</th>
                    <th>PEMBUAT</th>
                    <th class="text-center">PROGRESS PEMASANGAN</th>
                    <th class="text-center pe-3" style="width:15%;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($list_mutasi)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data Order untuk mutasi pasang.</td></tr>
                <?php else: ?>
                    <?php foreach ($list_mutasi as $idx => $row): ?>
                        <tr>
                            <td class="ps-3 text-muted"><?= $idx + 1 ?></td>
                            <td class="fw-bold font-monospace text-dark"><?= htmlspecialchars($row['NoFormOrder']) ?></td>
                            <td><?= htmlspecialchars($row['TglOrder']) ?></td>
                            <td><?= htmlspecialchars($row['NamaAkun']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-info bg-opacity-10 text-info font-monospace px-3 py-2">
                                    <?= $row['TotalSelesai'] ?> / <?= $row['TotalTarget'] ?> Selesai
                                </span>
                            </td>
                            <td class="text-center pe-3">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=detail&no_form=<?= urlencode($row['NoFormOrder']) ?>" class="btn btn-sm btn-outline-primary px-3">
                                        <i class="bi bi-tools me-1"></i> Eksekusi
                                    </a>
                                    <!-- Tombol Hapus Order SPK jika belum ada pemasangan yang selesai -->
                                    <?php if ($row['TotalSelesai'] == 0 && $row['StatusData'] === 'AKTIF'): ?>
                                        <a href="javascript:void(0);" onclick="confirmDeleteOrder('<?= urlencode($row['NoFormOrder']) ?>')" class="btn btn-sm btn-outline-danger px-2 py-1" title="Hapus SPK">
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
</div>

<script>
function confirmDeleteOrder(noOrder) {
    Swal.fire({
        title: 'Batalkan & Hapus SPK?',
        text: "SPK (Order) yang dihapus akan melepaskan ikatan pada FPK di dalamnya.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "modules/pemasangan/proses_order.php?action=hapus_order&no_form=" + noOrder;
        }
    });
}
</script>

<?php 
} elseif ($view === 'detail') {
    $no_form = $_GET['no_form'] ?? '';
    $stmt_head = $conn->prepare("SELECT * FROM frm_order WHERE NoFormOrder = ? AND UnitUp = ?");
    $stmt_head->execute([$no_form, $unit_up]);
    $formulir = $stmt_head->fetch(PDO::FETCH_ASSOC);
    
    if (!$formulir) {
        echo '<div class="alert alert-danger">Surat Perintah Kerja (Order) tidak ditemukan atau bukan milik Unit Anda.</div>';
        return;
    }

    $stmt_detil = $conn->prepare("
        SELECT od.NoFpk, pk.IdPel, pk.NoRef, pk.SimId, pk.ImeiModem,
               b.NoFba as NoFormPasang, b.TglFba as TglPasang, bd.PosisiPasang, b.StatusData as StatusPasang
        FROM frm_order_detil od
        JOIN frm_pk pk ON od.NoFpk = pk.NoFpk
        LEFT JOIN frm_ba b ON od.NoFpk = b.NoFpk AND b.JenisPekerjaan = 'PEMASANGAN'
        LEFT JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.NoRef = pk.NoRef AND bd.StatusPekerjaan = 'PASANG'
        WHERE od.NoFormOrder = ?
    ");
    $stmt_detil->execute([$no_form]);
    $items = $stmt_detil->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card border-0 shadow-sm rounded-4 bg-white p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold text-primary mb-1">Eksekusi Mutasi Pasang: <?= htmlspecialchars($no_form) ?></h5>
            <p class="text-muted small mb-0">Daftar perangkat dan pelanggan dalam SPK yang siap dipasang di lapangan.</p>
        </div>
        <a href="index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=daftar" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th class="text-center" style="width:5%;">#</th>
                    <th>NO FPK</th>
                    <th>IDPEL</th>
                    <th>NO REF / IMEI</th>
                    <th class="text-center">STATUS PASANG</th>
                    <th class="text-center" style="width:15%;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="6" class="text-center text-muted">Tidak ada target FPK dalam order ini.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $idx => $it): ?>
                        <?php $sudah_pasang = ($it['StatusPasang'] === 'AKTIF'); ?>
                        <tr>
                            <td class="text-center"><?= $idx + 1 ?></td>
                            <td class="fw-bold font-monospace text-primary"><?= htmlspecialchars($it['NoFpk']) ?></td>
                            <td class="font-monospace"><?= htmlspecialchars($it['IdPel']) ?></td>
                            <td class="font-monospace">
                                <div>Ref: <?= htmlspecialchars($it['NoRef']) ?></div>
                                <small class="text-muted">IMEI: <?= htmlspecialchars($it['ImeiModem'] ?? '-') ?></small>
                            </td>
                            <td class="text-center">
                                <?php if ($sudah_pasang): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle me-1"></i> Terpasang</span>
                                <?php else: ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning">Belum Dipasang</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <a href="modules/pemasangan/proses_mutasi.php?no_form=<?= urlencode($no_form) ?>&noref=<?= urlencode($it['NoRef']) ?>&no_fpk=<?= urlencode($it['NoFpk']) ?>" class="btn btn-sm <?= $sudah_pasang ? 'btn-outline-success' : 'btn-primary' ?> px-3">
                                    <?= $sudah_pasang ? 'Detail BA' : 'Input BA' ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php } ?>