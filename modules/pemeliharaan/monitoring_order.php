<?php
// Sub-menu: Monitoring Order Pemeliharaan (hanya baca)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    require_once __DIR__ . '/../../config/database.php';
}
require_once __DIR__ . '/../../functions/pemeliharaan_helper.php';

if (!ph_akses_monitoring()) {
    echo '<div class="alert alert-warning">Anda tidak memiliki akses ke Monitoring.</div>';
    return;
}

$sub_ini = 'monitoring_order';
$data_mon_order = [];
$total_hasil = 0; $hal = 1; $total_hal = 1; $q = '';
$pesan_error = '';

try {
    // Mengambil data monitoring order pemeliharaan dari relasi frm_pk, frm_ba, dan frm_ba_detil
    [$data_mon_order, $total_hasil, $hal, $total_hal, $q] = ph_daftar(
        $conn,
        "p.NoFpk, b.NoFba, p.TglFpk, b.KdPetugas, p.IdPel, bd.NoRef, bd.SimId, bd.ImeiModem,
         bd.LampuIndikator AS KondisiRelay, p.StatusData, p.WaktuData",
        "frm_pk p 
         LEFT JOIN frm_ba b ON p.NoFpk = b.NoFpk 
         LEFT JOIN frm_ba_detil bd ON b.NoFba = bd.NoFba",
        "AND p.JenisPK = 'PEMELIHARAAN'"
    );
} catch (PDOException $e) {
    error_log('pemeliharaan monitoring_order: ' . $e->getMessage());
    $pesan_error = 'Gagal memuat data monitoring. Hubungi administrator.';
}
?>

<style>
    .table-compact thead th { font-size: 0.75rem !important; font-weight: 700 !important; color: #6c757d !important; padding: 0.6rem 0.35rem !important; white-space: normal !important; }
    .table-compact tbody td { padding: 0.5rem 0.35rem !important; font-size: 0.81rem !important; white-space: normal !important; word-break: break-word !important; }
    .table-compact .badge { font-size: 0.7rem !important; padding: 0.3em 0.45em !important; }
</style>

<?php if ($pesan_error): ?>
    <div class="alert alert-danger rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= ph_e($pesan_error) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 bg-white p-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 px-2 pt-2">
        <div>
            <h5 class="fw-bold text-primary mb-0">Monitoring Laporan Order Pemeliharaan</h5>
            <p class="text-muted small mb-0">Rekapitulasi status pengerjaan perintah kerja pemeliharaan perangkat S41.</p>
        </div>
        <div class="d-flex gap-2">
            <?= ph_kotak_cari($sub_ini, $q) ?>
            <?= ph_tombol_export('order', $q) ?>
        </div>
    </div>

    <div class="w-100 table-responsive">
        <table class="table table-hover align-middle mb-0 table-compact w-100" id="tableMonOrder">
            <thead class="table-light text-dark border-top border-bottom">
                <tr>
                    <th class="ps-2 text-center" style="width:3%;">#</th>
                    <th style="width:18%;">NO PK / NO BA</th>
                    <th style="width:11%;">TGL PK</th>
                    <th style="width:16%;">IDPEL / NO REF</th>
                    <th style="width:13%;">SIM ID</th>
                    <th style="width:13%;">IMEI MODEM</th>
                    <th style="width:10%;">INDIKATOR</th>
                    <th style="width:8%;">STATUS</th>
                    <th class="pe-2" style="width:12%;">WAKTU DATA</th>
                </tr>
            </thead>
            <tbody class="fw-medium text-dark">
                <?php if (empty($data_mon_order)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-5"><?= $q !== '' ? 'Tidak ada data yang cocok dengan pencarian.' : 'Belum ada data monitoring pemeliharaan. Silakan buat order atau PK terlebih dahulu.' ?></td></tr>
                <?php else: ?>
                    <?php foreach ($data_mon_order as $idx => $row): ?>
                        <tr class="border-bottom">
                            <td class="ps-2 text-center text-muted small"><?= ($hal - 1) * PH_PER_HALAMAN + $idx + 1 ?></td>
                            <td>
                                <div class="fw-bold text-dark font-monospace"><?= ph_e($row['NoFpk']) ?></div>
                                <div class="small text-primary font-monospace">BA: <?= ph_e($row['NoFba'] ?? '-') ?></div>
                            </td>
                            <td class="text-secondary"><?= ph_e($row['TglFpk']) ?></td>
                            <td><div class="fw-bold text-dark">IDPEL: <?= ph_e($row['IdPel']) ?></div><div class="small text-muted">REF: <?= ph_e($row['NoRef'] ?? '-') ?></div></td>
                            <td>
                                <?php if (!empty($row['SimId'])): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info-emphasis border border-info-subtle font-monospace"><?= ph_e($row['SimId']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted fst-italic small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="font-monospace text-muted"><?= ph_e($row['ImeiModem'] ?? '-') ?></td>
                            <td>
                                <span class="badge bg-<?= ($row['KondisiRelay'] ?? '') === 'MENYALA' ? 'success' : 'danger' ?>-subtle text-<?= ($row['KondisiRelay'] ?? '') === 'MENYALA' ? 'success' : 'danger' ?>">
                                    <?= ph_e($row['KondisiRelay'] ?? '-') ?>
                                </span>
                            </td>
                            <td><?= ph_badge_status($row['StatusData']) ?></td>
                            <td class="pe-2 font-monospace text-secondary small"><?= ph_e($row['WaktuData'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= ph_pager($sub_ini, $total_hasil, $hal, $total_hal, $q) ?>
</div>