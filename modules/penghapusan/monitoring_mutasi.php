<?php
// Sub-menu: Monitoring Mutasi Penghapusan (hanya baca).
// Tombol "Cetak Laporan PDF" hanya ada di kanan atas halaman (index.php), tidak diulang di dalam kartu.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    require_once __DIR__ . '/../../config/database.php';
}
require_once __DIR__ . '/../../functions/penghapusan_helper.php';

if (!pgh_akses_monitoring()) {
    echo '<div class="alert alert-warning">Anda tidak memiliki akses ke Monitoring.</div>';
    return;
}

$sub_ini = 'monitoring_mutasi';
$data_mon_mutasi = [];
$total_hasil = 0; $hal = 1; $total_hal = 1; $q = '';
$pesan_error = '';

try {
    $nama_sql = pgh_sql_nama_modem($conn);
    [$data_mon_mutasi, $total_hasil, $hal, $total_hal, $q] = pgh_daftar(
        $conn,
        "b.NoFba, b.NoFpk, b.TglFba, b.IdPel, bd.NoRef, bd.SimId, bd.ImeiModem, $nama_sql AS NamaModem, b.WaktuData,
         bd.LampuIndikator AS Lampu, b.StatusData AS StatusBa",
        "frm_ba b
         JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.StatusPekerjaan = 'BONGKAR'
         LEFT JOIN master_modem mm ON mm.ImeiModem = bd.ImeiModem",
        "AND b.JenisPekerjaan = 'BONGKAR'",
        [],
        ['alias' => 'b', 'urut' => 'b.WaktuData DESC, b.NoFba DESC', 'cari' => pgh_kolom_cari($conn, false)]
    );
} catch (PDOException $e) {
    error_log('penghapusan monitoring_mutasi: ' . $e->getMessage());
    $pesan_error = 'Gagal memuat data monitoring mutasi.' . (pgh_role() === 'SA.KP' ? ' Detail: ' . $e->getMessage() : '');
}
?>

<?php if ($pesan_error): ?>
    <div class="alert alert-danger rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= pgh_e($pesan_error) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 bg-white">
    <?= pgh_card_header('Daftar Monitoring Mutasi Penghapusan', $sub_ini, $q) ?>
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
                        <th>INDIKATOR</th>
                        <th>STATUS</th>
                        <th class="text-end pe-4">WAKTU DATA</th>
                    </tr>
                </thead>
                <tbody class="fw-medium text-dark">
                    <?php if (empty($data_mon_mutasi)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-5"><?= $q !== '' ? 'Tidak ada data yang cocok dengan pencarian.' : 'Belum ada data penghapusan.' ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($data_mon_mutasi as $idx => $row): ?>
                            <tr class="border-bottom">
                                <td class="ps-4 text-muted small"><?= ($hal - 1) * PGH_PER_HALAMAN + $idx + 1 ?></td>
                                <td>
                                    <div class="fw-bold text-dark font-monospace"><?= pgh_e($row['NoFba']) ?></div>
                                    <div class="small text-primary font-monospace">FPK: <?= pgh_e($row['NoFpk'] ?? '-') ?></div>
                                </td>
                                <td><?= pgh_e($row['TglFba']) ?></td>
                                <td>IDPEL: <?= pgh_e($row['IdPel']) ?><br><small class="text-muted">REF: <?= pgh_e($row['NoRef'] ?? '-') ?></small></td>
                                <td><span class="badge bg-info bg-opacity-10 text-info font-monospace"><?= pgh_e($row['SimId'] ?: '-') ?></span></td>
                                <td class="font-monospace text-muted">
                                    <?= pgh_e($row['ImeiModem'] ?: '-') ?>
                                    <?php if (!empty($row['NamaModem'])): ?><br><small class="text-dark"><?= pgh_e($row['NamaModem']) ?></small><?php endif; ?>
                                </td>
                                <td><?= pgh_badge_relay($row['Lampu'] ?? '') ?></td>
                                <td><?= pgh_badge_status($row['StatusBa'] ?? '') ?></td>
                                <td class="text-end pe-4 font-monospace text-secondary small"><?= pgh_e($row['WaktuData'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?= pgh_pager($sub_ini, $total_hasil, $hal, $total_hal, $q) ?>
</div>
