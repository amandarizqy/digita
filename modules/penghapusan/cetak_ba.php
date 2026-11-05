<?php
// Cetak Formulir Perintah Kerja + Berita Acara Penghapusan (halaman cetak / simpan PDF)
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/penghapusan_helper.php';

if (!isset($_SESSION['NamaAkun']) || !pgh_akses_monitoring()) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$no_fba = trim((string) ($_GET['no_fba'] ?? ''));
if ($no_fba === '') {
    http_response_code(400);
    exit('Nomor Berita Acara tidak ditemukan.');
}

function cba_cb($aktif) { return $aktif ? '&#9745;' : '&#9744;'; }
function cba_v($v) { $v = trim((string) $v); return $v === '' ? '-' : pgh_e($v); }

[$sc, $sp] = pgh_scope('b');
try {
    $nm = pgh_sql_nama_modem($conn, 'mk');
    $al = pgh_sql_alasan($conn, 'p');
    $st = $conn->prepare("SELECT b.NoFba, b.NoFpk, b.TglFba, b.KdPetugas, b.IdPel, b.StatusData, b.UnitUp,
                                 u.NamaUnit, p.LongDil, p.LatDil, $al AS Alasan, o.NoFormOrder,
                                 bd.NoRef AS K_NoRef, bd.SimId AS K_Sim, bd.ImeiModem AS K_Imei, $nm AS K_NamaModem,
                                 bd.NomorBarang AS K_KontakS41, bd.NomorModem AS K_KontakModem,
                                 bd.`Long` AS K_Long, bd.Lat AS K_Lat, bd.PosisiPasang AS K_Posisi, bd.LampuIndikator AS K_Lampu,
                                 (bd.FotoBarang IS NOT NULL) AS K_FotoBarang, (bd.FotoTerpasang IS NOT NULL) AS K_FotoPasang
                          FROM frm_ba b
                          JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.StatusPekerjaan = 'BONGKAR'
                          LEFT JOIN frm_pk p ON p.NoFpk = b.NoFpk
                          LEFT JOIN frm_order_detil od ON od.NoFpk = b.NoFpk
                          LEFT JOIN frm_order o ON o.NoFormOrder = od.NoFormOrder
                          LEFT JOIN master_up u ON u.UnitUp = b.UnitUp
                          LEFT JOIN master_modem mk ON mk.ImeiModem = bd.ImeiModem
                          WHERE b.NoFba = ? AND b.JenisPekerjaan = 'BONGKAR'" . $sc . ' LIMIT 1');
    $st->execute(array_merge([$no_fba], $sp));
    $d = $st->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('penghapusan cetak_ba: ' . $e->getMessage());
    http_response_code(500);
    exit('Gagal memuat Berita Acara.');
}

if (!$d) {
    http_response_code(404);
    exit('Data Berita Acara tidak ditemukan atau di luar unit Anda.');
}

$tgl  = $d['TglFba'] ? date('d-m-Y', strtotime($d['TglFba'])) : '-';
$unit = trim((string) ($d['NamaUnit'] ?? '')) !== '' ? $d['NamaUnit'] : ($d['UnitUp'] ?? '-');

$imei = trim((string) $d['K_Imei']);
if ($imei !== '' && trim((string) $d['K_NamaModem']) !== '') { $imei .= ' (' . $d['K_NamaModem'] . ')'; }
$kontak_s41 = trim((string) $d['K_KontakS41']) !== '' ? $d['K_KontakS41'] : pgh_nomor_sim($conn, $d['K_Sim']);
$kontak_mdm = trim((string) $d['K_KontakModem']) !== '' ? $d['K_KontakModem'] : $kontak_s41;
$nama_pel = pgh_nama_pelanggan($conn, $d['IdPel']);
$f_barang = !empty($d['K_FotoBarang']);
$f_pasang = !empty($d['K_FotoPasang']);
$selesai  = (($d['StatusData'] ?? '') === 'AKTIF');
$url_foto = 'foto.php?no=' . urlencode($d['NoFba']);
$pil = function (string $label, array $opsi, string $nilai) {
    echo '<tr><td class="lbl">' . $label . '</td><td class="pilihan">';
    foreach ($opsi as $k => $t) { echo '<span>' . cba_cb($nilai === $k) . ' ' . $t . '</span> '; }
    echo '</td></tr>';
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Berita Acara - <?= pgh_e($d['NoFba']) ?></title>
    <style>
        @page { size: A4; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 12px; color: #000; }
        .kertas { width: 100%; max-width: 780px; margin: auto; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #000; padding: 4px 6px; vertical-align: middle; }
        .judul td { text-align: center; }
        .lbl { width: 21%; white-space: nowrap; }
        .sub th { background: #fff; text-align: center; font-weight: 700; letter-spacing: .5px; }
        .grp td { text-align: center; font-weight: 700; }
        .pilihan span { white-space: nowrap; margin-right: 6px; }
        .ttd td { height: 95px; text-align: center; vertical-align: top; width: 33.33%; }
        .ttd .nama { display: block; margin-top: 62px; }
        .catatan td { height: 70px; vertical-align: top; }
        .foto-box { margin-top: 14px; text-align: center; page-break-inside: avoid; }
        .foto-box figure { display: inline-block; margin: 4px; }
        .foto-box img { max-width: 230px; max-height: 230px; border: 1px solid #999; }
        .foto-box figcaption { font-size: 10px; color: #444; }
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
            <td style="width:25%;text-align:left;">PT PLN (PERSERO)<br>UID BANTEN<br><?= pgh_e($unit) ?></td>
            <td style="width:50%;">FORMULIR<br>PERINTAH KERJA</td>
            <td style="width:25%;">NOMOR DOKUMEN<br>FM-2026-Z</td>
        </tr>
    </table>
    <table>
        <tr><td class="lbl">Unit</td><td colspan="2">: <?= pgh_e($unit) ?></td></tr>
        <tr><td class="lbl">Tanggal</td><td colspan="2">: <?= pgh_e($tgl) ?></td></tr>
        <tr>
            <td class="lbl">Jenis PK</td>
            <td colspan="2" class="pilihan">
                <span><?= cba_cb(false) ?> PASANG</span> <span><?= cba_cb(false) ?> BONGKAR</span> <span><?= cba_cb(true) ?> <strong>PENGHAPUSAN</strong></span>
            </td>
        </tr>
        <tr><td class="lbl">Nomor Form</td><td colspan="2">: <?= pgh_e($d['NoFpk']) ?> &nbsp;|&nbsp; BA: <?= pgh_e($d['NoFba']) ?><?= !empty($d['NoFormOrder']) ? ' &nbsp;|&nbsp; Order: ' . pgh_e($d['NoFormOrder']) : '' ?></td></tr>
        <tr><td class="lbl">Id Pelanggan</td><td colspan="2">: <?= pgh_e($d['IdPel']) ?></td></tr>
        <tr><td class="lbl">Nama</td><td colspan="2">: <?= cba_v($nama_pel) ?></td></tr>
        <tr>
            <td class="lbl">Tikor DIL</td>
            <td>Long: <?= cba_v($d['LongDil']) ?></td>
            <td>Lat: <?= cba_v($d['LatDil']) ?></td>
        </tr>

        <tr><td class="lbl">Alasan</td><td colspan="2">: <?= cba_v($d['Alasan'] ?? '') ?></td></tr>

        <tr class="sub"><th></th><th colspan="2">BONGKAR</th></tr>

        <tr class="grp"><td colspan="3">MODEM</td></tr>
        <tr><td class="lbl">IMEI</td><td colspan="2"><?= cba_v($imei) ?></td></tr>
        <tr><td class="lbl">Nomor SIM</td><td colspan="2"><?= cba_v($d['K_Sim']) ?></td></tr>
        <tr><td class="lbl">Nomor Kontak</td><td colspan="2"><?= cba_v($kontak_mdm) ?></td></tr>

        <tr class="grp"><td colspan="3">S41</td></tr>
        <tr><td class="lbl">NoRef IMEI</td><td colspan="2"><?= cba_v($d['K_NoRef']) ?></td></tr>
        <tr><td class="lbl">Nomor SIM</td><td colspan="2"><?= cba_v($d['K_Sim']) ?></td></tr>
        <tr><td class="lbl">Nomor Kontak</td><td colspan="2"><?= cba_v($kontak_s41) ?></td></tr>

        <tr><td class="lbl">Tikor</td><td colspan="2">Long: <?= cba_v($d['K_Long']) ?>&nbsp;&nbsp;&nbsp; Lat: <?= cba_v($d['K_Lat']) ?></td></tr>
        <?php $pil('Posisi', ['PANGKAL' => 'Pangkal', 'TENGAH' => 'Tengah', 'UJUNG' => 'Ujung'], (string) $d['K_Posisi']); ?>
        <?php $pil('Lampu indikator', ['MENYALA' => 'Menyala', 'TIDAK' => 'Tidak Menyala'], (string) $d['K_Lampu']); ?>
        <tr><td class="lbl">Foto Barang</td><td colspan="2" class="pilihan"><span><?= cba_cb($f_barang) ?> Ada Dilampirkan</span> <span><?= cba_cb(!$f_barang) ?> Tidak Dilampirkan</span></td></tr>
        <tr><td class="lbl">Foto Terpasang</td><td colspan="2" class="pilihan"><span><?= cba_cb($f_pasang) ?> Ada Dilampirkan</span> <span><?= cba_cb(!$f_pasang) ?> Tidak Dilampirkan</span></td></tr>
    </table>

    <table class="ttd">
        <tr>
            <td>Membongkar,<br>Petugas<span class="nama">( <?= pgh_e($d['KdPetugas']) ?> )</span></td>
            <td>Memeriksa,<br>Staf Pengendalian Piutang<span class="nama">( ____________________ )</span></td>
            <td>Menyetujui,<br>Team Leader Pengendalian Piutang<span class="nama">( ____________________ )</span></td>
        </tr>
    </table>
    <table class="catatan">
        <tr><td><strong><u>Catatan Penghapusan:</u></strong><?= $selesai ? '<br>Perangkat dibongkar dan dilepas dari pelanggan (dihapus dari master barang aktif).' : '' ?></td></tr>
    </table>

    <?php if ($f_pasang || $f_barang): ?>
    <div class="foto-box">
        <h4 style="margin:0 0 4px;">Dokumentasi Pekerjaan Lapangan</h4>
        <?php if ($f_pasang): ?><figure><img src="<?= pgh_e($url_foto . '&jenis=pasang') ?>" alt="Foto Terpasang"><figcaption>Foto terpasang (sebelum dibongkar)</figcaption></figure><?php endif; ?>
        <?php if ($f_barang): ?><figure><img src="<?= pgh_e($url_foto . '&jenis=barang') ?>" alt="Foto Barang"><figcaption>Foto barang</figcaption></figure><?php endif; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
