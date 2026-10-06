<?php
// templates/perencanaan/data_riwayat.php  -  Monitoring: Data Riwayat Pelunasan
// Variabel dari modules/perencanaan/data_riwayat.php

$url_riwayat = function (array $ubah = []) use ($keyword, $limit) {
    return pr_url('data_riwayat', array_merge(['keyword' => $keyword, 'limit' => $limit == 10 ? '' : $limit], $ubah));
};

echo pr_header([
    'title'    => 'Data Riwayat Pelunasan',
    'subtitle' => 'Riwayat tagihan dan tanggal bayar pelanggan (pelunasan AP2T) yang menjadi dasar hitung keterlambatan dan dampak.',
    'action'   => 'data_riwayat',
    'buttons'  => [['label' => 'Input Riwayat Baru', 'icon' => 'bi-plus-lg', 'href' => pr_url('upload_riwayat')]],
]);

if (!empty($pesan_sukses)) echo pr_alert('success', pr_e($pesan_sukses));
if (!empty($pesan_error))  echo pr_alert('danger', pr_e($pesan_error));
?>

<!-- KARTU METRIK KPI -->
<div class="row g-3 mb-4">
    <?= pr_kpi('Total Riwayat', pr_n($total_baris), 'Baris tagihan · ' . pr_n($total_pelanggan) . ' pelanggan', 'bi-clock-history', 'primary') ?>
    <?= pr_kpi('Total Nominal', pr_rp_ringkas($total_nominal), 'Rp ' . pr_n($total_nominal) . ' · RpTag + RpBK', 'bi-cash-stack', 'success', true) ?>
    <?= pr_kpi('Belum Dibayar', pr_n($belum_bayar), 'Tagihan tanpa Tanggal Bayar', 'bi-exclamation-circle', 'danger', $belum_bayar > 0) ?>
    <?= pr_kpi('Rekening Terakhir', pr_e($bulan_terakhir), 'ThBlRek terbaru pada data', 'bi-calendar3', 'warning') ?>
</div>

<!-- TABEL -->
<div class="card border-0 shadow-sm rounded-4 bg-white">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="fw-bold text-primary fs-6">Riwayat Pelunasan Terbaru</span>
            <span class="badge bg-light text-secondary border font-monospace py-1 px-2">Tabel: pelunasan_ap2t</span>
        </div>
        <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-2">
            <?= pr_hidden('data_riwayat') ?>
            <div class="input-group input-group-sm" style="width:260px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="keyword" class="form-control bg-light border-start-0" value="<?= pr_e($keyword) ?>" placeholder="Cari IDPel / ThBlRek / unit...">
            </div>
            <select name="limit" class="form-select form-select-sm bg-light" style="width:auto;" onchange="this.form.submit()" aria-label="Jumlah baris">
                <?php foreach ([10, 25, 50, 100] as $n): ?>
                    <option value="<?= $n ?>" <?= $limit === $n ? 'selected' : '' ?>><?= $n ?> baris</option>
                <?php endforeach; ?>
            </select>
            <?php if ($keyword !== ''): ?>
                <a href="<?= pr_e(pr_url('data_riwayat')) ?>" class="btn btn-outline-secondary btn-sm" title="Reset pencarian"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary text-uppercase pr-thead">
                    <tr>
                        <th class="ps-4">ID Pelanggan</th>
                        <th>Rekening</th>
                        <th>Tanggal Bayar</th>
                        <th class="text-end">RpBK</th>
                        <th class="text-end">RpTag</th>
                        <th>Unit (UP / AP / UPI)</th>
                        <th>Waktu Input</th>
                        <th class="text-end pe-4" style="width:80px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($data_riwayat)): foreach ($data_riwayat as $row):
                    $th = (string) $row['ThBlRek'];
                    $rek_label = strlen($th) === 6 ? substr($th, 4, 2) . '/' . substr($th, 0, 4) : $th;
                ?>
                    <tr>
                        <td class="ps-4 fw-bold text-dark font-monospace"><?= pr_e($row['IdPel']) ?></td>
                        <td><span class="badge bg-light text-dark border font-monospace" title="ThBlRek <?= pr_e($th) ?>"><?= pr_e($rek_label) ?></span></td>
                        <td>
                            <?php if (empty($row['TglBayar'])): ?>
                                <?= pr_badge('BELUM BAYAR', 'danger', 'bi-x-circle') ?>
                            <?php else: ?>
                                <span class="font-monospace small"><?= pr_e(date('d/m/Y', strtotime($row['TglBayar']))) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end font-monospace small text-nowrap"><?= pr_rp($row['RpBK'] ?? 0) ?></td>
                        <td class="text-end font-monospace small text-nowrap"><?= pr_rp($row['RpTag'] ?? 0) ?></td>
                        <td>
                            <span class="badge bg-light text-dark border font-monospace"><?= pr_e($row['UnitUp'] ?? '-') ?></span>
                            <span class="badge bg-light text-dark border font-monospace"><?= pr_e($row['UnitAp'] ?? '-') ?></span>
                            <span class="badge bg-light text-secondary border font-monospace"><?= pr_e($row['UnitUpi'] ?? '-') ?></span>
                        </td>
                        <td class="text-muted font-monospace small"><?= pr_e(date('d/m/Y H:i', strtotime($row['WaktuData']))) ?></td>
                        <td class="text-end pe-4">
                            <a href="<?= pr_e($url_riwayat(['act' => 'delete', 'idpel' => $row['IdPel'], 'thblrek' => $row['ThBlRek'], 'hal' => $page])) ?>"
                               class="btn btn-outline-danger btn-sm py-1 px-2" title="Hapus data"
                               onclick="return confirm(<?= pr_e(json_encode('Hapus riwayat IdPel ' . $row['IdPel'] . ' (rekening ' . $row['ThBlRek'] . ')?')) ?>);">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; else: echo pr_empty_row(8, 'Belum ada data riwayat pelunasan.', 'bi-clock-history'); endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?= pr_footer_tabel($page, $limit, $total_data, fn($h) => $url_riwayat(['hal' => $h])) ?>
</div>
