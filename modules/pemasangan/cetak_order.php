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
        $st = $conn->prepare("SELECT p.NoFpk, p.TglFpk, p.JenisPk, p.IdPel, p.LongDil, p.LatDil,
                                     pd.NoRef, pd.SimId, pd.ImeiModem, pd.NomorBarang,
                                     d.Nama, b.TglFba
                              FROM frm_order_detil od
                              JOIN frm_pk p ON p.NoFpk = od.NoFpk
                              LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk
                              LEFT JOIN dil d ON d.IdPel = p.IdPel
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

function v($val) { return htmlspecialchars(trim((string)$val) === '' ? '' : $val); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Order SPK & FPK - <?= htmlspecialchars($order['NoFormOrder']) ?></title>
    <style>
        @page { size: A4; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: auto; max-width: 780px; color: #000; padding: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        td, th { border: 1px solid #000; padding: 4px 6px; vertical-align: middle; }
        .judul { text-align: center; font-weight: bold; }
        .lbl { width: 18%; font-weight: normal; }
        .lbl-fpk { width: 22%; font-weight: normal; }
        .daftar th { font-weight: 700; text-align: center; }
        .daftar td { height: 21px; font-size: 10.5px; }
        .daftar .no { width: 4%; text-align: center; }
        .mono { font-family: "Courier New", monospace; }
        
        .page-break { page-break-before: always; margin-top: 30px; }
        .no-print { text-align: right; margin-bottom: 15px; }
        .bg-light { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; }
        @media print { .no-print { display: none; } body { padding: 0; } }
    </style>
</head>
<body onload="window.print()">
    <!-- TOMBOL PRINT -->
    <div class="no-print">
        <button onclick="window.print()" style="padding:8px 15px;background:#0d6efd;color:#fff;border:none;cursor:pointer;font-weight:bold;border-radius:4px;">Cetak / Simpan PDF</button>
    </div>

    <!-- ========================================================================= -->
    <!-- HALAMAN 1: REKAP FORMULIR ORDER PERINTAH KERJA (SPK)                      -->
    <!-- ========================================================================= -->
    <table>
        <tr class="judul">
            <td style="width:25%;text-align:left;">PT PLN (PERSERO)<br>UID BANTEN<br><?= htmlspecialchars($unit) ?></td>
            <td style="width:50%; font-size:13px;">FORMULIR<br>ORDER PERINTAH KERJA</td>
            <td style="width:25%; font-weight:normal;">NOMOR DOKUMEN<br>FM-2026-Z</td>
        </tr>
    </table>
    <table>
        <tr><td class="lbl">Nomor Order</td><td>: <?= htmlspecialchars($order['NoFormOrder']) ?></td></tr>
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
                    <td class="mono" style="text-align:center;"><?= htmlspecialchars($r['NoFpk']) ?></td>
                    <td style="text-align:center;"><?= htmlspecialchars($r['IdPel']) ?></td>
                    <td class="mono" style="text-align:center;"><?= htmlspecialchars($r['NoRef'] ?? '') ?></td>
                    <td class="mono" style="text-align:center;"><?= $sim_kontak ?></td>
                    <td style="text-align:center;"><?= !empty($r['TglFba']) ? date('d-m-Y', strtotime($r['TglFba'])) : '' ?></td>
                <?php else: ?>
                    <td></td><td></td><td></td><td></td><td></td>
                <?php endif; ?>
            </tr>
        <?php endfor; ?>
        </tbody>
    </table>

    <table style="text-align:center;">
        <tr>
            <td style="width:33%; vertical-align:top; height:100px; padding-top:10px;">
                Memeriksa,<br>Team Leader Pengendalian Piutang<br><br><br><br><br>(____________________)
            </td>
            <td style="width:34%; vertical-align:top; height:100px; padding-top:10px;">
                Memeriksa,<br>Team Leader Pengendalian Piutang<br><br><br><br><br>(____________________)
            </td>
            <td style="width:33%; vertical-align:top; height:100px; padding-top:10px;">
                Menerima,<br>Petugas<br><br><br><br><br>( <?= htmlspecialchars($order['KdPetugas'] ?? '____________________') ?> )
            </td>
        </tr>
    </table>

    <!-- ========================================================================= -->
    <!-- HALAMAN SELANJUTNYA: LOOPING FORMULIR PERINTAH KERJA (FPK) SATU PERSATU   -->
    <!-- ========================================================================= -->
    <?php foreach ($rows as $d): ?>
        <?php
        $cb_pasang   = ($d['JenisPk'] === 'PASANG') ? '&#9745;' : '&#9744;';
        $cb_bongkar  = ($d['JenisPk'] === 'BONGKAR') ? '&#9745;' : '&#9744;';
        $cb_pelihara = ($d['JenisPk'] === 'PEMELIHARAAN') ? '&#9745;' : '&#9744;';
        ?>
        <div class="page-break"></div>
        <table>
            <tr class="judul">
                <td style="width:25%; text-align:left;">PT PLN (PERSERO)<br>UID BANTEN<br><?= v($unit) ?></td>
                <td style="width:50%; font-size:14px;">FORMULIR<br>PERINTAH KERJA</td>
                <td style="width:25%; font-weight:normal;">NOMOR DOKUMEN<br>FM-2026-Z</td>
            </tr>
        </table>
        <table>
            <tr><td class="lbl-fpk">Unit</td><td colspan="2">: <?= v($unit) ?></td></tr>
            <tr><td class="lbl-fpk">Tanggal</td><td colspan="2">: <?= date('d-m-Y', strtotime($d['TglFpk'])) ?></td></tr>
            <tr><td class="lbl-fpk">Jenis PK</td><td colspan="2">: <?= $cb_pasang ?> PASANG &nbsp;&nbsp;&nbsp;&nbsp; <?= $cb_bongkar ?> BONGKAR &nbsp;&nbsp;&nbsp;&nbsp; <?= $cb_pelihara ?> PEMELIHARAAN</td></tr>
            <tr><td class="lbl-fpk">Nomor Form</td><td colspan="2">: <?= v($d['NoFpk']) ?></td></tr>
            <tr><td class="lbl-fpk">Id Pelanggan</td><td colspan="2">: <?= v($d['IdPel']) ?></td></tr>
            <tr><td class="lbl-fpk">Nama</td><td colspan="2">: <?= v($d['Nama']) ?></td></tr>
            <tr><td class="lbl-fpk">Tikor DIL</td><td colspan="2">: Long: <?= v($d['LongDil']) ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Lat: <?= v($d['LatDil']) ?></td></tr>
            
            <tr class="judul bg-light"><td class="lbl-fpk"></td><td style="width:39%;">BONGKAR</td><td style="width:39%;">PASANG</td></tr>
            
            <tr><td class="judul" style="text-align:center;" colspan="3">MODEM</td></tr>
            <tr><td class="lbl-fpk">IMEI</td><td>: </td><td>: <?= v($d['ImeiModem']) ?></td></tr>
            <tr><td class="lbl-fpk">Nomor SIM</td><td>: </td><td>: <?= v($d['SimId']) ?></td></tr>
            <tr><td class="lbl-fpk">Nomor Kontak</td><td>: </td><td>: <?= v($d['NomorBarang']) ?></td></tr>
            
            <tr><td class="judul" style="text-align:center;" colspan="3">S41</td></tr>
            <tr><td class="lbl-fpk">NoRef IMEI</td><td>: </td><td>: <?= v($d['NoRef']) ?></td></tr>
            <tr><td class="lbl-fpk">Nomor SIM</td><td>: </td><td>: <?= v($d['SimId']) ?></td></tr>
            <tr><td class="lbl-fpk">Nomor Kontak</td><td>: </td><td>: <?= v($d['NomorBarang']) ?></td></tr>
            <tr><td class="lbl-fpk">Tikor</td><td>: Long: ....................... Lat: .......................</td><td>: Long: ....................... Lat: .......................</td></tr>
            <tr><td class="lbl-fpk">Posisi</td>
                <td>&#9744; Pangkal &nbsp; &#9744; Tengah &nbsp; &#9744; Ujung</td>
                <td>&#9744; Pangkal &nbsp; &#9744; Tengah &nbsp; &#9744; Ujung</td>
            </tr>
            <tr><td class="lbl-fpk">Lampu indikator</td>
                <td>&#9744; Menyala &nbsp;&nbsp;&nbsp;&nbsp; &#9744; Tidak Menyala</td>
                <td>&#9744; Menyala &nbsp;&nbsp;&nbsp;&nbsp; &#9744; Tidak Menyala</td>
            </tr>
            <tr><td class="lbl-fpk">Foto Barang</td>
                <td>&#9744; Ada Dilampirkan &nbsp; &#9744; Tidak Dilampirkan</td>
                <td>&#9744; Ada Dilampirkan &nbsp; &#9744; Tidak Dilampirkan</td>
            </tr>
            <tr><td class="lbl-fpk">Foto Terpasang</td>
                <td>&#9744; Ada Dilampirkan &nbsp; &#9744; Tidak Dilampirkan</td>
                <td>&#9744; Ada Dilampirkan &nbsp; &#9744; Tidak Dilampirkan</td>
            </tr>
        </table>

        <table style="text-align:center;">
            <tr>
                <td style="width:33%; vertical-align:top; height:100px; padding-top:10px;">
                    Memasang,<br>Petugas<br><br><br><br><br>(____________________)
                </td>
                <td style="width:34%; vertical-align:top; height:100px; padding-top:10px;">
                    Memeriksa,<br>Staf Pengendalian Piutang<br><br><br><br><br>(____________________)
                </td>
                <td style="width:33%; vertical-align:top; height:100px; padding-top:10px;">
                    Menyetujui,<br>Team Leader Pengendalian Piutang<br><br><br><br><br>(____________________)
                </td>
            </tr>
        </table>
        <table>
            <tr>
                <td style="height:70px; vertical-align:top; font-weight:bold;">Catatan Pemasangan:<br></td>
            </tr>
        </table>
    <?php endforeach; ?>
</body>
</html>