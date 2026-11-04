<?php
// modules/pemasangan/cetak_order.php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/pemasangan_helper.php';

if (!isset($_SESSION['NamaAkun']) || !p_akses_order()) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$no_order = trim((string) ($_GET['no_order'] ?? ''));
if ($no_order === '') {
    http_response_code(400);
    exit('Nomor Order tidak ditemukan.');
}

const CO_BARIS_MIN = 25;

try {
    $st = $conn->prepare('SELECT o.NoFormOrder, o.TglOrder, o.KdPetugas, o.UnitUp, u.NamaUnit
                          FROM frm_order o
                          LEFT JOIN master_up u ON u.UnitUp = o.UnitUp
                          WHERE o.NoFormOrder = ? LIMIT 1');
    $st->execute([$no_order]);
    $order = $st->fetch(PDO::FETCH_ASSOC);

    $rows = [];
    if ($order) {
        $st = $conn->prepare("SELECT p.NoFpk, p.IdPel, pd.NoRef, pd.SimId, pd.NomorBarang, b.TglFba
                              FROM frm_order_detil od
                              JOIN frm_pk p ON p.NoFpk = od.NoFpk
                              LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk AND pd.StatusBarang = 'STOK'
                              LEFT JOIN frm_ba b ON b.NoFpk = p.NoFpk AND b.JenisPekerjaan = 'PEMASANGAN'
                              WHERE od.NoFormOrder = ?
                              ORDER BY p.NoFpk ASC");
        $st->execute([$no_order]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    http_response_code(500);
    exit('Gagal memuat Formulir Order Pemasangan.');
}

if (!$order) {
    http_response_code(404);
    exit('Order tidak ditemukan.');
}

$tgl  = $order['TglOrder'] ? date('d-m-Y', strtotime($order['TglOrder'])) : '-';
$unit = trim((string) ($order['NamaUnit'] ?? '')) !== '' ? $order['NamaUnit'] : ($order['UnitUp'] ?? '-');
$total_baris = max(CO_BARIS_MIN, count($rows));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Order Pemasangan - <?= htmlspecialchars($order['NoFormOrder']) ?></title>
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
            <td style="width:25%;text-align:left;">PT PLN (PERSERO)<br>UID BANTEN<br><?= htmlspecialchars($unit) ?></td>
            <td style="width:50%;">FORMULIR<br>ORDER PERINTAH KERJA PEMASANGAN</td>
            <td style="width:25%;">NOMOR<br>DOKUMEN<br>FM-2026-Z</td>
        </tr>
    </table>
    <table>
        <tr><td class="lbl">Nomor Order</td><td>: <span class="mono"><?= htmlspecialchars($order['NoFormOrder']) ?></span></td></tr>
        <tr><td class="lbl">Unit</td><td>: <?= htmlspecialchars($unit) ?></td></tr>
        <tr><td class="lbl">Nama Petugas</td><td>: <?= htmlspecialchars($order['KdPetugas'] ?? '-') ?></td></tr>
        <tr><td class="lbl">Tanggal</td><td>: <?= htmlspecialchars($tgl) ?></td></tr>
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
                    $sim_kontak = ($sim === '' && $knt === '') ? '' : ($sim !== '' ? $sim : '-') . ' &ndash; ' . ($knt !== '' ? $knt : '-');
                    ?>
                    <td class="mono"><?= htmlspecialchars($r['NoFpk']) ?></td>
                    <td><?= htmlspecialchars($r['IdPel']) ?></td>
                    <td class="mono"><?= htmlspecialchars($r['NoRef'] ?? '') ?></td>
                    <td class="mono"><?= $sim_kontak ?></td>
                    <td><?= !empty($r['TglFba']) ? htmlspecialchars(date('d-m-Y', strtotime($r['TglFba']))) : '' ?></td>
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
            <td>Menerima,<br>Petugas<span class="nama">( <?= htmlspecialchars($order['KdPetugas'] ?? '____________________') ?> )</span></td>
        </tr>
    </table>
</div>
</body>
</html>