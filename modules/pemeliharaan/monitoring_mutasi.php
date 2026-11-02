<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    require_once __DIR__ . '/../../config/database.php';
}
require_once __DIR__ . '/../../functions/pemeliharaan_helper.php';

if (!ph_akses_monitoring()) {
    echo '<div class="alert alert-warning">Akses ditolak.</div>';
    return;
}

$sub_ini = 'monitoring_mutasi';
$data_mon_mutasi = [];
$total_hasil = 0; $hal = 1; $total_hal = 1; $q = '';

try {
    $nama_sql = ph_sql_nama_modem($conn);
    [$data_mon_mutasi, $total_hasil, $hal, $total_hal, $q] = ph_daftar(
        $conn,
        "b.NoFba, b.NoFpk, b.TglFba, b.IdPel, bd.NoRef, bd.SimId, bd.ImeiModem, $nama_sql AS NamaModem, b.WaktuData,
         bk.SimId AS SimAwal, bk.ImeiModem AS ImeiAwal, (bk.NoFba IS NOT NULL) AS Termutasi",
        "frm_ba b
         JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.StatusPekerjaan = 'PASANG'
         LEFT JOIN frm_ba_detil bk ON bk.NoFba = b.NoFba AND bk.StatusPekerjaan = 'BONGKAR'
         LEFT JOIN master_modem mm ON mm.ImeiModem = bd.ImeiModem",
        "AND b.JenisPekerjaan = 'PEMELIHARAAN'",
        [],
        ['alias' => 'b', 'urut' => 'b.WaktuData DESC, b.NoFba DESC', 'cari' => ph_kolom_cari($conn, false)]
    );
} catch (PDOException $e) {}

$url_cetak_laporan = BASE_URL . '/modules/pemeliharaan/cetak_laporan.php?type=mutasi';
?>

<div class="card border-0 shadow-sm rounded-4 bg-white p-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 px-2 pt-2">
        <div>
            <h5 class="fw-bold text-primary mb-0">Monitoring Laporan Mutasi Pemeliharaan</h5>
            <p class="text-muted small mb-0">Riwayat penggantian SIM / modem perangkat S41.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?= ph_kotak_cari($sub_ini, $q) ?>
            <a href="<?= ph_e($url_cetak_laporan) ?>" target="_blank" class="btn btn-outline-primary fw-bold px-3 rounded-3 shadow-sm">
                <i class="bi bi-printer me-1"></i> Cetak Laporan PDF
            </a>
        </div>
    </div>

    <div class="w-100 table-responsive">
        <table class="table table-hover align-middle mb-0 w-100">
            <thead class="table-light text-dark border-top border-bottom">
                <tr>
                    <th class="ps-2 text-center" style="width:3%;">#</th>
                    <th style="width:17%;">NO BA / PK</th>
                    <th style="width:10%;">TGL BA</th>
                    <th style="width:15%;">IDPEL / NO REF</th>
                    <th style="width:15%;">SIM ID</th>
                    <th style="width:17%;">MODEM</th>
                    <th style="width:12%;">STATUS MUTASI</th>
                    <th class="pe-2" style="width:11%;">WAKTU DATA</th>
                </tr>
            </thead>
            <tbody class="fw-medium text-dark">
                <?php if (empty($data_mon_mutasi)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">Belum ada data mutasi.</td></tr>
                <?php else: ?>
                    <?php foreach ($data_mon_mutasi as $idx => $row): ?>
                        <?php $mut = !empty($row['Termutasi']); ?>
                        <tr class="border-bottom">
                            <td class="ps-2 text-center text-muted small"><?= ($hal - 1) * PH_PER_HALAMAN + $idx + 1 ?></td>
                            <td>
                                <div class="fw-bold text-dark font-monospace"><?= ph_e($row['NoFba']) ?></div>
                                <div class="small text-primary font-monospace">PK: <?= ph_e($row['NoFpk'] ?? '-') ?></div>
                            </td>
                            <td><?= ph_e($row['TglFba']) ?></td>
                            <td>IDPEL: <?= ph_e($row['IdPel']) ?><br><small class="text-muted">REF: <?= ph_e($row['NoRef'] ?? '-') ?></small></td>
                            <td class="font-monospace"><?= ph_e($row['SimId'] ?: '-') ?></td>
                            <td class="font-monospace"><?= ph_e($row['ImeiModem'] ?: '-') ?></td>
                            <td><?= $mut ? '<span class="badge bg-warning-subtle text-warning-emphasis">TERMUTASI</span>' : '<span class="badge bg-secondary-subtle text-secondary">TETAP</span>' ?></td>
                            <td class="pe-2 font-monospace text-secondary small"><?= ph_e($row['WaktuData'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= ph_pager($sub_ini, $total_hasil, $hal, $total_hal, $q) ?>
</div>