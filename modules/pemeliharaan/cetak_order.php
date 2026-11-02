<?php
// Cetak Formulir Order Perintah Kerja (satu order memuat banyak FPK)
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/pemeliharaan_helper.php';

if (!isset($_SESSION['NamaAkun']) || !ph_akses_monitoring()) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$no_order = trim((string) ($_GET['no_order'] ?? ''));
if ($no_order === '') {
    http_response_code(400);
    exit('Nomor Order tidak ditemukan.');
}

const CO_BARIS_MIN = 25; 

[$sc, $sp] = ph_scope('o');
try {
    $st = $conn->prepare('SELECT o.NoFormOrder, o.TglOrder, o.KdPetugas, o.UnitUp, u.NamaUnit
                          FROM frm_order o
                          LEFT JOIN master_up u ON u.UnitUp = o.UnitUp
                          WHERE o.NoFormOrder = ?' . $sc . ' LIMIT 1');
    $st->execute(array_merge([$no_order], $sp));
    $order = $st->fetch(PDO::FETCH_ASSOC);

    $rows = [];
    if ($order) {
        $st = $conn->prepare("SELECT p.NoFpk, p.IdPel, pd.NoRef, pd.SimId, pd.NomorBarang, b.TglFba
                              FROM frm_order_detil od
                              JOIN frm_pk p ON p.NoFpk = od.NoFpk
                              LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk AND pd.StatusBarang = 'DIL'
                              LEFT JOIN frm_ba b ON b.NoFpk = p.NoFpk AND b.JenisPekerjaan = 'PEMELIHARAAN'
                              WHERE od.NoFormOrder = ?
                              ORDER BY p.NoFpk ASC");
        $st->execute([$no_order]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('pemeliharaan cetak_order: ' . $e->getMessage());
    http_response_code(500);
    exit('Gagal memuat Formulir Order.');
}

if (!$order) {
    http_response_code(404);
    exit('Order tidak ditemukan atau di luar unit Anda.');
}

$tgl  = $order['TglOrder'] ? date('d-m-Y', strtotime($order['TglOrder'])) : '-';
$unit = trim((string) ($order['NamaUnit'] ?? '')) !== '' ? $order['NamaUnit'] : ($order['UnitUp'] ?? '-');
$jml  = count($rows);
$total_baris = max(CO_BARIS_MIN, $jml); 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Order - <?= ph_e($order['NoFormOrder']) ?></title>
    <style>
        @page { size: A4; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 12px; color: #000; }
        .kertas { width: 100%; max-width: 780px; margin: auto; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #000; padding: 3px 6px; vertical-align: middle; }
        .judul td { text-align: center; }
        .lbl { width: 18%; white-space: nowrap; }
        .daftar th { font-weight: 700; text-align: center; }
        .daftar td { height: 19px; font-size: 10.5px; }
        .daftar .no { width: 4%; text-align: left; }
        .mono { font-family: "Courier New", monospace; }
        .ttd td { height: 100px; text-align: center; vertical-align: top; width: 33.33%; }
        .ttd .nama { display: block; margin-top: 66px; }
        tr { page-break-inside: avoid; }
        .no-print { text-align: right; margin-bottom: 10px; }
        @media print { .no-print { display: none; } body { padding: 0; } }
    </style>
</head>
<body onload="window.print()">
<div class="kertas">
    <div class="no-print">
        <button onclick="window.print()" style="padding:8px 15px;background:#0d6efd;color:#fff;border:none;cursor:pointer;font-weight:bold;">Cetak / Simpan PDF</button>
    </div>

    <table>
        <tr class="judul">
            <td style="width:25%;text-align:left;">PT PLN (PERSERO)<br>UID BANTEN<br><?= ph_e($unit) ?></td>
            <td style="width:50%;">FORMULIR<br>ORDER PERINTAH KERJA</td>
            <td style="width:25%;">NOMOR<br>DOKUMEN<br>FM-2026-Z</td>
        </tr>
    </table>
    <table>
        <tr><td class="lbl">Nomor Order</td><td>: <span class="mono"><?= ph_e($order['NoFormOrder']) ?></span></td></tr>
        <tr><td class="lbl">Unit</td><td>: <?= ph_e($unit) ?></td></tr>
        <tr><td class="lbl">Nama Petugas</td><td>: <?= ph_e($order['KdPetugas'] ?? '-') ?></td></tr>
        <tr><td class="lbl">Tanggal</td><td>: <?= ph_e($tgl) ?></td></tr>
    </table>

    <table class="daftar">
        <thead>
            <tr>
                <th class="no">No</th>
                <th style="width:27%;">No Perintah Kerja</th>
                <th style="width:15%;">IdPel</th>
                <th style="width:19%;">No Ref IMEI</th>
                <th style="width:24%;">SIM Id &ndash; Nomor Kontak</th>
                <th style="width:11%;">Tgl Pasang</th>
            </tr>
        </thead>
        <tbody>
        <?php for ($i = 0; $i < $total_baris; $i++): ?>
            <?php $r = $rows[$i] ?? null; ?>
            <tr>
                <td class="no"><?= $i + 1 ?></td>
                <?php if ($r): ?>
                    <?php
                    $sim = trim((string) ($r['SimId'] ?? ''));
                    $knt = trim((string) ($r['NomorBarang'] ?? ''));
                    if ($knt === '') {
                        $knt = (string) ph_nomor_sim($conn, $sim);
                    }
                    $sim_kontak = ($sim === '' && $knt === '')
                        ? ''
                        : ($sim !== '' ? ph_e($sim) : '-') . ' &ndash; ' . ($knt !== '' ? ph_e($knt) : '-');
                    ?>
                    <td class="mono"><?= ph_e($r['NoFpk']) ?></td>
                    <td><?= ph_e($r['IdPel']) ?></td>
                    <td class="mono"><?= ph_e($r['NoRef'] ?? '') ?></td>
                    <td class="mono"><?= $sim_kontak ?></td>
                    <td><?= !empty($r['TglFba']) ? ph_e(date('d-m-Y', strtotime($r['TglFba']))) : '' ?></td>
                <?php else: ?>
                    <td></td><td></td><td></td><td></td><td></td>
                <?php endif; ?>
            </tr>
        <?php endfor; ?>
        </tbody>
    </table>

    <table class="ttd">
        <tr>
            <td>Memeriksa,<br>Team Leader Pengendalian Piutang<span class="nama">( ____________________ )</span></td>
            <td>Memeriksa,<br>Team Leader Pengendalian Piutang<span class="nama">( ____________________ )</span></td>
            <td>Menerima,<br>Petugas<span class="nama">( <?= ph_e($order['KdPetugas'] ?? '____________________') ?> )</span></td>
        </tr>
    </table>
</div>
</body>
</html>