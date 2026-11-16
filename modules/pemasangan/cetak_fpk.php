<?php
// modules/pemasangan/cetak_fpk.php
session_start();
require_once __DIR__ . '/../../config/database.php';

$no_fpk = trim((string) ($_GET['no_fpk'] ?? ''));
if ($no_fpk === '') { 
    http_response_code(400); 
    exit('Nomor FPK tidak ditemukan.'); 
}

try {
    $st = $conn->prepare("SELECT p.NoFpk, p.TglFpk, p.JenisPk, p.IdPel, p.LongDil, p.LatDil, p.UnitUp,
                                 pd.NoRef, pd.SimId, pd.ImeiModem, pd.NomorBarang,
                                 d.Nama, u.NamaUnit
                          FROM frm_pk p
                          LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk
                          LEFT JOIN dil d ON d.IdPel = p.IdPel
                          LEFT JOIN master_up u ON u.UnitUp = p.UnitUp
                          WHERE p.NoFpk = ? LIMIT 1");
    $st->execute([$no_fpk]);
    $d = $st->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { 
    exit('Gagal memuat data Formulir Perintah Kerja (FPK).'); 
}

if (!$d) { 
    exit('Data FPK tidak ditemukan di sistem.'); 
}

$unit = trim((string) ($d['NamaUnit'] ?? '')) !== '' ? $d['NamaUnit'] : ($d['UnitUp'] ?? '-');
$cb_pasang   = ($d['JenisPk'] === 'PASANG') ? '&#9745;' : '&#9744;';
$cb_bongkar  = ($d['JenisPk'] === 'BONGKAR') ? '&#9745;' : '&#9744;';
$cb_pelihara = ($d['JenisPk'] === 'PEMELIHARAAN') ? '&#9745;' : '&#9744;';

function v($val) { return htmlspecialchars(trim((string)$val) === '' ? '' : $val); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak FPK - <?= v($no_fpk) ?></title>
    <style>
        @page { size: A4; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: auto; max-width: 780px; color: #000; padding: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        td, th { border: 1px solid #000; padding: 5px 7px; vertical-align: middle; }
        .judul td { text-align: center; font-weight: bold; }
        .lbl { width: 22%; font-weight: normal; }
        .no-print { text-align: right; margin-bottom: 15px; }
        .bg-light { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; }
        @media print { .no-print { display: none; } body { padding: 0; } }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print">
        <button onclick="window.print()" style="padding:8px 15px;background:#0d6efd;color:#fff;border:none;cursor:pointer;font-weight:bold;border-radius:4px;">Cetak / Simpan PDF</button>
    </div>

    <table>
        <tr class="judul">
            <td style="width:25%; text-align:left;">PT PLN (PERSERO)<br>UID BANTEN<br><?= v($unit) ?></td>
            <td style="width:50%; font-size:14px;">FORMULIR<br>PERINTAH KERJA</td>
            <td style="width:25%; font-weight:normal;">NOMOR DOKUMEN<br>FM-2026-Z</td>
        </tr>
    </table>
    <table>
        <tr><td class="lbl">Unit</td><td colspan="2">: <?= v($unit) ?></td></tr>
        <tr><td class="lbl">Tanggal</td><td colspan="2">: <?= date('d-m-Y', strtotime($d['TglFpk'])) ?></td></tr>
        <tr><td class="lbl">Jenis PK</td><td colspan="2">: <?= $cb_pasang ?> PASANG &nbsp;&nbsp;&nbsp;&nbsp; <?= $cb_bongkar ?> BONGKAR &nbsp;&nbsp;&nbsp;&nbsp; <?= $cb_pelihara ?> PEMELIHARAAN</td></tr>
        <tr><td class="lbl">Nomor Form</td><td colspan="2">: <?= v($d['NoFpk']) ?></td></tr>
        <tr><td class="lbl">Id Pelanggan</td><td colspan="2">: <?= v($d['IdPel']) ?></td></tr>
        <tr><td class="lbl">Nama</td><td colspan="2">: <?= v($d['Nama']) ?></td></tr>
        <tr><td class="lbl">Tikor DIL</td><td colspan="2">: Long: <?= v($d['LongDil']) ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Lat: <?= v($d['LatDil']) ?></td></tr>
        
        <tr class="judul bg-light"><td class="lbl"></td><td style="width:39%;">BONGKAR</td><td style="width:39%;">PASANG</td></tr>
        
        <tr><td class="judul" style="text-align:center;" colspan="3">MODEM</td></tr>
        <tr><td class="lbl">IMEI</td><td>: </td><td>: <?= v($d['ImeiModem']) ?></td></tr>
        <tr><td class="lbl">Nomor SIM</td><td>: </td><td>: <?= v($d['SimId']) ?></td></tr>
        <tr><td class="lbl">Nomor Kontak</td><td>: </td><td>: <?= v($d['NomorBarang']) ?></td></tr>
        
        <tr><td class="judul" style="text-align:center;" colspan="3">S41</td></tr>
        <tr><td class="lbl">NoRef IMEI</td><td>: </td><td>: <?= v($d['NoRef']) ?></td></tr>
        <tr><td class="lbl">Nomor SIM</td><td>: </td><td>: <?= v($d['SimId']) ?></td></tr>
        <tr><td class="lbl">Nomor Kontak</td><td>: </td><td>: <?= v($d['NomorBarang']) ?></td></tr>
        <tr><td class="lbl">Tikor</td><td>: Long: ....................... Lat: .......................</td><td>: Long: ....................... Lat: .......................</td></tr>
        <tr><td class="lbl">Posisi</td>
            <td>&#9744; Pangkal &nbsp; &#9744; Tengah &nbsp; &#9744; Ujung</td>
            <td>&#9744; Pangkal &nbsp; &#9744; Tengah &nbsp; &#9744; Ujung</td>
        </tr>
        <tr><td class="lbl">Lampu indikator</td>
            <td>&#9744; Menyala &nbsp;&nbsp;&nbsp;&nbsp; &#9744; Tidak Menyala</td>
            <td>&#9744; Menyala &nbsp;&nbsp;&nbsp;&nbsp; &#9744; Tidak Menyala</td>
        </tr>
        <tr><td class="lbl">Foto Barang</td>
            <td>&#9744; Ada Dilampirkan &nbsp; &#9744; Tidak Dilampirkan</td>
            <td>&#9744; Ada Dilampirkan &nbsp; &#9744; Tidak Dilampirkan</td>
        </tr>
        <tr><td class="lbl">Foto Terpasang</td>
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
</body>
</html>