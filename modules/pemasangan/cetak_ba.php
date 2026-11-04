<?php
// modules/pemasangan/cetak_ba.php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/pemasangan_helper.php';

if (!isset($_SESSION['NamaAkun']) || !p_akses_mutasi()) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$no_fba = trim((string) ($_GET['no_fba'] ?? ''));

// Jika parameter berakhiran -F.E, konversi otomatis atau cari NoFba terkait di database
if (str_ends_with($no_fba, '-F.E')) {
    $no_fba_g = str_replace('-F.E', '-F.G', $no_fba);
    // Cek apakah -F.G ada, atau cari via relasi FPK
    $st_check = $conn->prepare("SELECT NoFba FROM frm_ba WHERE NoFba = ? OR NoFpk IN (SELECT NoFpk FROM frm_order_detil WHERE NoFormOrder = ?) LIMIT 1");
    $st_check->execute([$no_fba_g, $no_fba]);
    $found_ba = $st_check->fetchColumn();
    if ($found_ba) {
        $no_fba = $found_ba;
    }
}

function cba_v($v) { $v = trim((string) $v); return $v === '' ? '-' : htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

try {
    // Query disesuaikan dengan struktur tabel frm_ba dan frm_ba_detil
    $st = $conn->prepare("SELECT b.NoFba, b.NoFpk, b.TglFba, b.KdPetugas, b.IdPel, b.StatusData, b.UnitUp,
                                 u.NamaUnit, p.LongDil, p.LatDil,
                                 bd.NoRef AS P_NoRef, bd.SimId AS P_Sim, bd.ImeiModem AS P_Imei,
                                 bd.NomorBarang AS P_KontakS41, bd.NomorModem AS P_KontakModem,
                                 bd.`Long` AS P_Long, bd.Lat AS P_Lat, bd.PosisiPasang AS P_Posisi, bd.LampuIndikator AS P_Lampu
                          FROM frm_ba b
                          LEFT JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba
                          LEFT JOIN frm_pk p ON p.NoFpk = b.NoFpk
                          LEFT JOIN master_up u ON u.UnitUp = b.UnitUp
                          WHERE b.NoFba = ? LIMIT 1");
    $st->execute([$no_fba]);
    $d = $st->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Gagal memuat Berita Acara Pemasangan: ' . $e->getMessage());
}

if (!$d) {
    http_response_code(404);
    exit('Data Berita Acara tidak ditemukan di database untuk No FBA: ' . htmlspecialchars($no_fba));
}

$tgl  = $d['TglFba'] ? date('d-m-Y', strtotime($d['TglFba'])) : '-';
$unit = trim((string) ($d['NamaUnit'] ?? '')) !== '' ? $d['NamaUnit'] : ($d['UnitUp'] ?? '-');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak BA Pemasangan - <?= htmlspecialchars($d['NoFba']) ?></title>
    <style>
        @page { size: A4; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 12px; color: #000; }
        .kertas { width: 100%; max-width: 780px; margin: auto; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #000; padding: 4px 6px; vertical-align: middle; }
        .judul td { text-align: center; }
        .lbl { width: 21%; white-space: nowrap; }
        .ttd td { height: 95px; text-align: center; vertical-align: top; width: 33.33%; }
        .ttd .nama { display: block; margin-top: 62px; }
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
            <td style="width:50%;">BERITA ACARA<br>PEMASANGAN PERANGKAT S41</td>
            <td style="width:25%;">NOMOR DOKUMEN<br>FM-2026-Z</td>
        </tr>
    </table>
    <table>
        <tr><td class="lbl">Unit</td><td colspan="2">: <?= htmlspecialchars($unit) ?></td></tr>
        <tr><td class="lbl">Tanggal</td><td colspan="2">: <?= htmlspecialchars($tgl) ?></td></tr>
        <tr><td class="lbl">Nomor Form FPK / BA</td><td colspan="2">: <?= htmlspecialchars($d['NoFpk']) ?> &nbsp;|&nbsp; BA: <?= htmlspecialchars($d['NoFba']) ?></td></tr>
        <tr><td class="lbl">Id Pelanggan</td><td colspan="2">: <?= htmlspecialchars($d['IdPel']) ?></td></tr>
        <tr><td class="lbl">NoRef S41</td><td colspan="2">: <?= cba_v($d['P_NoRef']) ?></td></tr>
        <tr><td class="lbl">SIM ID / IMEI</td><td colspan="2">: <?= cba_v($d['P_Sim']) ?> / <?= cba_v($d['P_Imei']) ?></td></tr>
        <tr><td class="lbl">Posisi Pasang</td><td colspan="2">: <?= cba_v($d['P_Posisi']) ?></td></tr>
        <tr><td class="lbl">Lampu Indikator</td><td colspan="2">: <?= cba_v($d['P_Lampu']) ?></td></tr>
        <tr><td class="lbl">Koordinat (Tikor)</td><td colspan="2">: Long: <?= cba_v($d['P_Long']) ?>, Lat: <?= cba_v($d['P_Lat']) ?></td></tr>
    </table>

    <table class="ttd">
        <tr>
            <td>Memasang,<br>Petugas<span class="nama">( <?= htmlspecialchars($d['KdPetugas']) ?> )</span></td>
            <td>Memeriksa,<br>Staf Pengendalian Piutang<span class="nama">( ____________________ )</span></td>
            <td>Menyetujui,<br>Team Leader Pengendalian Piutang<span class="nama">( ____________________ )</span></td>
        </tr>
    </table>
</div>
</body>
</html>