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
    // Pembanding: SIM/IMEI saat order dibuat (frm_order_har_detil). Bila berbeda dengan yang terpasang sekarang -> TERMUTASI.
    [$data_mon_mutasi, $total_hasil, $hal, $total_hal, $q] = ph_daftar(
        $conn,
        "p.NoFormPemeliharaan, p.TglPemeliharaan, p.IdPel, p.NoRef, p.SimId, p.ImeiModem, p.WaktuData,
         d.SimId AS SimAwal, d.ImeiModem AS ImeiAwal,
         (d.NoRef IS NOT NULL AND (NOT (p.SimId <=> d.SimId) OR NOT (p.ImeiModem <=> d.ImeiModem))) AS Termutasi",
        "frm_pemeliharaan p
         LEFT JOIN frm_order_har_detil d ON d.NoFormOrder = p.NoFormOrder AND d.NoRef = p.NoRef"
    );
} catch (PDOException $e) {
    error_log('pemeliharaan monitoring_mutasi: ' . $e->getMessage());
    $pesan_error = 'Gagal memuat data monitoring mutasi. Hubungi administrator.';
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
            <h5 class="fw-bold text-primary mb-0">Monitoring Laporan Mutasi Pemeliharaan</h5>
            <p class="text-muted small mb-0">Perbandingan SIM &amp; modem saat order dibuat dengan yang terpasang sekarang.</p>
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
                    <th style="width:17%;">NO FORM PEMELIHARAAN</th>
                    <th style="width:10%;">TGL PEMELIHARAAN</th>
                    <th style="width:14%;">IDPEL / NO REF</th>
                    <th style="width:18%;">SIM ID (AWAL &rarr; SEKARANG)</th>
                    <th style="width:18%;">IMEI MODEM (AWAL &rarr; SEKARANG)</th>
                    <th style="width:10%;">STATUS MUTASI</th>
                    <th class="pe-2" style="width:10%;">WAKTU DATA</th>
                </tr>
            </thead>
            <tbody class="fw-medium text-dark">
                <?php if (empty($data_mon_mutasi)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4"><?= $q !== '' ? 'Tidak ada data yang cocok dengan pencarian.' : 'Belum ada laporan mutasi pemeliharaan.' ?></td></tr>
                <?php else: ?>
                    <?php foreach ($data_mon_mutasi as $idx => $row): ?>
                        <?php $mut = !empty($row['Termutasi']); ?>
                        <tr class="border-bottom">
                            <td class="ps-2 text-center text-muted small"><?= ($hal - 1) * PH_PER_HALAMAN + $idx + 1 ?></td>
                            <td class="fw-bold text-dark font-monospace"><?= ph_e($row['NoFormPemeliharaan']) ?></td>
                            <td class="text-secondary"><?= ph_e($row['TglPemeliharaan']) ?></td>
                            <td><div class="fw-bold text-dark">IDPEL: <?= ph_e($row['IdPel']) ?></div><div class="small text-muted">REF: <?= ph_e($row['NoRef']) ?></div></td>
                            <td class="font-monospace">
                                <?php if ($mut && ($row['SimAwal'] ?? '') !== ($row['SimId'] ?? '')): ?>
                                    <span class="text-muted text-decoration-line-through small"><?= ph_e($row['SimAwal'] ?: '-') ?></span><br>
                                    <span class="fw-bold"><?= ph_e($row['SimId'] ?: '-') ?></span>
                                <?php else: ?>
                                    <?= ph_e($row['SimId'] ?: '-') ?>
                                <?php endif; ?>
                            </td>
                            <td class="font-monospace">
                                <?php if ($mut && ($row['ImeiAwal'] ?? '') !== ($row['ImeiModem'] ?? '')): ?>
                                    <span class="text-muted text-decoration-line-through small"><?= ph_e($row['ImeiAwal'] ?: '-') ?></span><br>
                                    <span class="fw-bold"><?= ph_e($row['ImeiModem'] ?: '-') ?></span>
                                <?php else: ?>
                                    <?= ph_e($row['ImeiModem'] ?: '-') ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($mut): ?>
                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">TERMUTASI</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary">BELUM DIMUTASI</span>
                                <?php endif; ?>
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