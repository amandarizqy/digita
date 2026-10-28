<?php
// Sub-menu: Monitoring Mutasi Pemeliharaan (hanya baca)
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

$sub_ini = 'monitoring_mutasi';
$data_mon_mutasi = [];
$total_hasil = 0; $hal = 1; $total_hal = 1; $q = '';
$pesan_error = '';

try {
    // Kueri disesuaikan dengan kolom yang valid pada frm_ba dan frm_ba_detil
    [$data_mon_mutasi, $total_hasil, $hal, $total_hal, $q] = ph_daftar(
        $conn,
        "b.NoFba, b.NoFpk, b.TglFba, b.IdPel, bd.NoRef, bd.SimId, bd.ImeiModem, bd.StatusPekerjaan, b.WaktuData",
        "frm_ba b 
         JOIN frm_ba_detil bd ON b.NoFba = bd.NoFba",
        "AND b.JenisPekerjaan = 'PEMELIHARAAN'"
    );
} catch (PDOException $e) {
    // Menangkap pesan error agar tidak langsung Fatal Error di layar
    $pesan_error = 'Gagal memuat data monitoring mutasi: ' . $e->getMessage();
}
?>

<style>
    .table-compact thead th { font-size: 0.75rem !important; font-weight: 700 !important; color: #6c757d !important; padding: 0.6rem 0.35rem !important; white-space: normal !important; }
    .table-compact tbody td { padding: 0.5rem 0.35rem !important; font-size: 0.81rem !important; white-space: normal !important; word-break: break-word !important; }
    .table-compact .badge { font-size: 0.7rem !important; padding: 0.3em 0.45em !important; }
</style>

<?php if ($pesan_error): ?>
    <div class="alert alert-danger rounded-3 mx-3 mt-3 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= ph_e($pesan_error) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 bg-white p-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 px-2 pt-2">
        <div>
            <h5 class="fw-bold text-primary mb-0">Monitoring Laporan Mutasi Pemeliharaan</h5>
            <p class="text-muted small mb-0">Rekapitulasi riwayat bongkar dan pasang perangkat pada pemeliharaan S41.</p>
        </div>
        <div class="d-flex gap-2">
            <?= ph_kotak_cari($sub_ini, $q) ?>
            <?= ph_tombol_export('mutasi', $q) ?>
        </div>
    </div>

    <div class="w-100 table-responsive">
        <table class="table table-hover align-middle mb-0 table-compact w-100" id="tableMonMutasi">
            <thead class="table-light text-dark border-top border-bottom">
                <tr>
                    <th class="ps-2 text-center" style="width:3%;">#</th>
                    <th style="width:18%;">NO FBA / PK</th>
                    <th style="width:11%;">TGL BA</th>
                    <th style="width:16%;">IDPEL / NO REF</th>
                    <th style="width:15%;">SIM ID</th>
                    <th style="width:15%;">IMEI MODEM</th>
                    <th style="width:12%;">STATUS PEKERJAAN</th>
                    <th class="pe-2" style="width:10%;">WAKTU DATA</th>
                </tr>
            </thead>
            <tbody class="fw-medium text-dark">
                <?php if (empty($data_mon_mutasi)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5"><?= $q !== '' ? 'Tidak ada data yang cocok dengan pencarian.' : 'Belum ada laporan mutasi pemeliharaan.' ?></td></tr>
                <?php else: ?>
                    <?php foreach ($data_mon_mutasi as $idx => $row): ?>
                        <tr class="border-bottom">
                            <td class="ps-2 text-center text-muted small"><?= ($hal - 1) * PH_PER_HALAMAN + $idx + 1 ?></td>
                            <td>
                                <div class="fw-bold text-dark font-monospace"><?= ph_e($row['NoFba']) ?></div>
                                <div class="small text-primary font-monospace">PK: <?= ph_e($row['NoFpk'] ?? '-') ?></div>
                            </td>
                            <td class="text-secondary"><?= ph_e($row['TglFba']) ?></td>
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
                                <span class="badge bg-<?= ($row['StatusPekerjaan'] ?? '') === 'PASANG' ? 'success' : 'danger' ?>-subtle text-<?= ($row['StatusPekerjaan'] ?? '') === 'PASANG' ? 'success' : 'danger' ?>">
                                    <?= ph_e($row['StatusPekerjaan'] ?? '-') ?>
                                </span>
                            </td>
                            <td class="pe-2 font-monospace text-secondary small"><?= ph_e($row['WaktuData'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= ph_pager($sub_ini, $total_hasil, $hal, $total_hal, $q) ?>
</div>