<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/pemeliharaan_helper.php';

if (!isset($_SESSION['NamaAkun']) || !ph_akses_monitoring()) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$type = (($_GET['type'] ?? 'order') === 'mutasi') ? 'mutasi' : 'order';
$unit_up = $_SESSION['UnitUp'] ?? '-';

try {
    $nama_sql = ph_sql_nama_modem($conn);
    if ($type === 'mutasi') {
        [$sc, $sp] = ph_scope('b');
        $sql = "SELECT b.NoFba, b.NoFpk, b.TglFba, b.IdPel, bd.NoRef, bd.SimId, bd.ImeiModem, $nama_sql AS NamaModem, b.WaktuData,
                       bk.SimId AS SimAwal, bk.ImeiModem AS ImeiAwal, (bk.NoFba IS NOT NULL AND NOT (bk.NoRef <=> bd.NoRef AND bk.SimId <=> bd.SimId AND bk.ImeiModem <=> bd.ImeiModem)) AS Termutasi
                FROM frm_ba b
                JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.StatusPekerjaan = 'PASANG'
                LEFT JOIN frm_ba_detil bk ON bk.NoFba = b.NoFba AND bk.StatusPekerjaan = 'BONGKAR'
                LEFT JOIN master_modem mm ON mm.ImeiModem = bd.ImeiModem
                WHERE b.JenisPekerjaan = 'PEMELIHARAAN' $sc
                ORDER BY b.WaktuData DESC, b.NoFba DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute($sp);
    } else {
        [$sc, $sp] = ph_scope('p');
        $sql = "SELECT o.NoFormOrder, p.NoFpk, b.NoFba, p.TglFpk, COALESCE(b.KdPetugas, o.KdPetugas) AS KdPetugas, p.IdPel, pd.NoRef, COALESCE(bd.SimId, mb.SimId) AS SimId, COALESCE(bd.ImeiModem, mb.ImeiModem) AS ImeiModem, $nama_sql AS NamaModem,
                       COALESCE(bd.NomorBarang, pd.NomorBarang) AS KontakS41, COALESCE(bd.NomorModem, pd.NomorModem) AS KontakModem,
                       bd.PosisiPasang, bd.LampuIndikator AS KondisiRelay, bd.`Long` AS Longitude, bd.Lat AS Latitude,
                       COALESCE(b.StatusData, p.StatusData) AS StatusData, p.WaktuData
                FROM frm_pk p
                LEFT JOIN frm_order_detil od ON od.NoFpk = p.NoFpk
                LEFT JOIN frm_order o ON o.NoFormOrder = od.NoFormOrder
                LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk AND pd.StatusBarang = 'DIL'
                LEFT JOIN master_barang mb ON mb.NoRef = pd.NoRef
                LEFT JOIN frm_ba b ON b.NoFpk = p.NoFpk AND b.JenisPekerjaan = 'PEMELIHARAAN'
                LEFT JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.StatusPekerjaan = 'PASANG'
                LEFT JOIN master_modem mm ON mm.ImeiModem = COALESCE(bd.ImeiModem, mb.ImeiModem)
                WHERE p.JenisPk = 'PEMELIHARAAN' $sc
                ORDER BY p.WaktuData DESC, p.NoFpk DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute($sp);
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    exit('Gagal memuat data laporan.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Laporan Pemeliharaan - <?= strtoupper($type) ?></title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #000; margin: 0; padding: 10px; }
        .kertas { width: 100%; max-width: 1100px; margin: auto; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #000; padding-bottom: 8px; }
        .header h3 { margin: 0 0 5px 0; font-size: 14px; text-transform: uppercase; }
        .header p { margin: 0; font-size: 11px; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 5px 6px; vertical-align: middle; text-align: left; }
        th { background-color: #f2f2f2; text-align: center; font-weight: bold; font-size: 10.5px; }
        .text-center { text-align: center; }
        .mono { font-family: "Courier New", monospace; }
        .no-print { text-align: right; margin-bottom: 15px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">
<div class="kertas">
    <div class="no-print">
        <button onclick="window.print()" style="padding: 8px 16px; background: #0d6efd; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Cetak / Simpan PDF</button>
    </div>

    <div class="header">
        <h3>REKAPITULASI LAPORAN <?= strtoupper($type) ?> PEMELIHARAAN S41</h3>
        <p>PT PLN (PERSERO) UID BANTEN &mdash; Unit: <?= ph_e($unit_up) ?> &mdash; Tanggal Cetak: <?= date('d-m-Y H:i') ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 3%;">No</th>
                <?php if ($type === 'mutasi'): ?>
                    <th>No BA</th><th>No FPK</th><th>Tgl BA</th><th>ID Pelanggan</th><th>No Ref</th><th>SIM Awal</th><th>SIM Sekarang</th><th>IMEI Awal</th><th>IMEI Sekarang</th><th>Status</th>
                <?php else: ?>
                    <th>No Order</th><th>No FPK</th><th>No BA</th><th>Tgl FPK</th><th>Petugas</th><th>ID Pel</th><th>No Ref</th><th>SIM ID</th><th>Modem</th><th>Indikator</th><th>Status</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="<?= $type === 'mutasi' ? 11 : 12 ?>" class="text-center py-4">Tidak ada data untuk dicetak.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td class="text-center"><?= $i + 1 ?></td>
                        <?php if ($type === 'mutasi'): ?>
                            <td class="mono"><?= ph_e($r['NoFba']) ?></td>
                            <td class="mono"><?= ph_e($r['NoFpk'] ?? '-') ?></td>
                            <td class="text-center"><?= ph_e($r['TglFba']) ?></td>
                            <td class="mono"><?= ph_e($r['IdPel']) ?></td>
                            <td class="mono"><?= ph_e($r['NoRef'] ?? '-') ?></td>
                            <td class="mono"><?= ph_e(!empty($r['Termutasi']) ? ($r['SimAwal'] ?? '-') : '-') ?></td>
                            <td class="mono"><?= ph_e($r['SimId'] ?? '-') ?></td>
                            <td class="mono"><?= ph_e(!empty($r['Termutasi']) ? ($r['ImeiAwal'] ?? '-') : '-') ?></td>
                            <td class="mono"><?= ph_e($r['ImeiModem'] ?? '-') ?></td>
                            <td class="text-center"><?= !empty($r['Termutasi']) ? 'TERMUTASI' : 'TETAP' ?></td>
                        <?php else: ?>
                            <td class="mono"><?= ph_e($r['NoFormOrder'] ?? '-') ?></td>
                            <td class="mono"><?= ph_e($r['NoFpk']) ?></td>
                            <td class="mono"><?= ph_e($r['NoFba'] ?? '-') ?></td>
                            <td class="text-center"><?= ph_e($r['TglFpk']) ?></td>
                            <td><?= ph_e($r['KdPetugas'] ?? '-') ?></td>
                            <td class="mono"><?= ph_e($r['IdPel']) ?></td>
                            <td class="mono"><?= ph_e($r['NoRef'] ?? '-') ?></td>
                            <td class="mono"><?= ph_e($r['SimId'] ?? '-') ?></td>
                            <td class="mono"><?= ph_e($r['ImeiModem'] ?? '-') ?></td>
                            <td class="text-center"><?= ph_e($r['KondisiRelay'] ?? '-') ?></td>
                            <td class="text-center"><?= (($r['StatusData'] ?? '') === 'AKTIF') ? 'SELESAI' : 'PENDING' ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>