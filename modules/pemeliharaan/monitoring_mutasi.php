<?php
// Sub-menu: Monitoring Mutasi Pemeliharaan (hanya baca).
// Tombol "Cetak Laporan PDF" hanya ada di kanan atas halaman (index.php), tidak diulang di dalam kartu.
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
    $nama_sql = ph_sql_nama_modem($conn);
    [$data_mon_mutasi, $total_hasil, $hal, $total_hal, $q] = ph_daftar(
        $conn,
        "b.NoFba, b.NoFpk, b.TglFba, b.IdPel, bd.NoRef, bd.SimId, bd.ImeiModem, $nama_sql AS NamaModem, b.WaktuData,
         bk.SimId AS SimAwal, bk.ImeiModem AS ImeiAwal, (bk.NoFba IS NOT NULL AND NOT (bk.NoRef <=> bd.NoRef AND bk.SimId <=> bd.SimId AND bk.ImeiModem <=> bd.ImeiModem)) AS Termutasi",
        "frm_ba b
         JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.StatusPekerjaan = 'PASANG'
         LEFT JOIN frm_ba_detil bk ON bk.NoFba = b.NoFba AND bk.StatusPekerjaan = 'BONGKAR'
         LEFT JOIN master_modem mm ON mm.ImeiModem = bd.ImeiModem",
        "AND b.JenisPekerjaan = 'PEMELIHARAAN'",
        [],
        ['alias' => 'b', 'urut' => 'b.WaktuData DESC, b.NoFba DESC', 'cari' => ph_kolom_cari($conn, false)]
    );
} catch (PDOException $e) {
    error_log('pemeliharaan monitoring_mutasi: ' . $e->getMessage());
    $pesan_error = 'Gagal memuat data monitoring mutasi.' . (ph_role() === 'SA.KP' ? ' Detail: ' . $e->getMessage() : '');
}
?>

<?php if ($pesan_error): ?>
    <div class="alert alert-danger rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= ph_e($pesan_error) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 bg-white">
    <?= ph_card_header('Daftar Monitoring Mutasi Pemeliharaan', $sub_ini, $q) ?>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 ph-table">
                <thead class="table-light text-secondary text-uppercase ph-thead">
                    <tr>
                        <th class="ps-4" style="width:40px;">#</th>
                        <th>NO BA / FPK</th>
                        <th>TGL BA</th>
                        <th>IDPEL / NO REF</th>
                        <th>SIM ID</th>
                        <th>MODEM</th>
                        <th>STATUS MUTASI</th>
                        <th class="text-end pe-4">WAKTU DATA</th>
                    </tr>
                </thead>
                <tbody class="fw-medium text-dark">
                    <?php if (empty($data_mon_mutasi)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-5"><?= $q !== '' ? 'Tidak ada data yang cocok dengan pencarian.' : 'Belum ada data mutasi.' ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($data_mon_mutasi as $idx => $row): ?>
                            <?php $mut = !empty($row['Termutasi']); ?>
                            <tr class="border-bottom">
                                <td class="ps-4 text-muted small"><?= ($hal - 1) * PH_PER_HALAMAN + $idx + 1 ?></td>
                                <td>
                                    <div class="fw-bold text-dark font-monospace"><?= ph_e($row['NoFba']) ?></div>
                                    <div class="small text-primary font-monospace">FPK: <?= ph_e($row['NoFpk'] ?? '-') ?></div>
                                </td>
                                <td><?= ph_e($row['TglFba']) ?></td>
                                <td>IDPEL: <?= ph_e($row['IdPel']) ?><br><small class="text-muted">REF: <?= ph_e($row['NoRef'] ?? '-') ?></small></td>
                                <td><span class="badge bg-info bg-opacity-10 text-info font-monospace"><?= ph_e($row['SimId'] ?: '-') ?></span></td>
                                <td class="font-monospace text-muted">
                                    <?= ph_e($row['ImeiModem'] ?: '-') ?>
                                    <?php if (!empty($row['NamaModem'])): ?><br><small class="text-dark"><?= ph_e($row['NamaModem']) ?></small><?php endif; ?>
                                </td>
                                <td><?= $mut ? '<span class="badge bg-warning-subtle text-warning-emphasis">TERMUTASI</span>' : '<span class="badge bg-secondary-subtle text-secondary">TETAP</span>' ?></td>
                                <td class="text-end pe-4 font-monospace text-secondary small"><?= ph_e($row['WaktuData'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?= ph_pager($sub_ini, $total_hasil, $hal, $total_hal, $q) ?>
</div>