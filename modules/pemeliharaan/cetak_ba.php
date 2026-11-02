<?php
// Cetak Formulir Perintah Kerja + Berita Acara Pemeliharaan (halaman cetak / simpan PDF)
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/pemeliharaan_helper.php';

if (!isset($_SESSION['NamaAkun']) || !ph_akses_monitoring()) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$no_fba = trim((string) ($_GET['no_fba'] ?? ''));
if ($no_fba === '') {
    http_response_code(400);
    exit('Nomor Berita Acara tidak ditemukan.');
}

function cba_cb($aktif) { return $aktif ? '&#9745;' : '&#9744;'; }
function cba_v($v) { $v = trim((string) $v); return $v === '' ? '-' : ph_e($v); }

[$sc, $sp] = ph_scope('b');
try {
    $nm_p = ph_sql_nama_modem($conn, 'mp');
    $nm_k = ph_sql_nama_modem($conn, 'mk');
    $st = $conn->prepare("SELECT b.NoFba, b.NoFpk, b.TglFba, b.KdPetugas, b.IdPel, b.StatusData, b.UnitUp,
                                 u.NamaUnit, p.LongDil, p.LatDil, o.NoFormOrder,
                                 bd.NoRef AS P_NoRef, bd.SimId AS P_Sim, bd.ImeiModem AS P_Imei, $nm_p AS P_NamaModem,
                                 bd.NomorBarang AS P_KontakS41, bd.NomorModem AS P_KontakModem,
                                 bd.`Long` AS P_Long, bd.Lat AS P_Lat, bd.PosisiPasang AS P_Posisi, bd.LampuIndikator AS P_Lampu,
                                 (bd.FotoBarang IS NOT NULL) AS P_FotoBarang, (bd.FotoTerpasang IS NOT NULL) AS P_FotoPasang,
                                 (bk.NoFba IS NOT NULL) AS AdaBongkar,
                                 bk.NoRef AS K_NoRef, bk.SimId AS K_Sim, bk.ImeiModem AS K_Imei, $nm_k AS K_NamaModem,
                                 bk.NomorBarang AS K_KontakS41, bk.NomorModem AS K_KontakModem,
                                 bk.`Long` AS K_Long, bk.Lat AS K_Lat, bk.PosisiPasang AS K_Posisi, bk.LampuIndikator AS K_Lampu,
                                 (bk.FotoBarang IS NOT NULL) AS K_FotoBarang, (bk.FotoTerpasang IS NOT NULL) AS K_FotoPasang
                          FROM frm_ba b
                          JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.StatusPekerjaan = 'PASANG'
                          LEFT JOIN frm_ba_detil bk ON bk.NoFba = b.NoFba AND bk.StatusPekerjaan = 'BONGKAR'
                          LEFT JOIN frm_pk p ON p.NoFpk = b.NoFpk
                          LEFT JOIN frm_order_detil od ON od.NoFpk = b.NoFpk
                          LEFT JOIN frm_order o ON o.NoFormOrder = od.NoFormOrder
                          LEFT JOIN master_up u ON u.UnitUp = b.UnitUp
                          LEFT JOIN master_modem mp ON mp.ImeiModem = bd.ImeiModem
                          LEFT JOIN master_modem mk ON mk.ImeiModem = bk.ImeiModem
                          WHERE b.NoFba = ? AND b.JenisPekerjaan = 'PEMELIHARAAN'" . $sc . ' LIMIT 1');
    $st->execute(array_merge([$no_fba], $sp));
    $d = $st->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('pemeliharaan cetak_ba: ' . $e->getMessage());
    http_response_code(500);
    exit('Gagal memuat Berita Acara.');
}

if (!$d) {
    http_response_code(404);
    exit('Data Berita Acara tidak ditemukan atau di luar unit Anda.');
}

$tgl   = $d['TglFba'] ? date('d-m-Y', strtotime($d['TglFba'])) : '-';
$unit  = trim((string) ($d['NamaUnit'] ?? '')) !== '' ? $d['NamaUnit'] : ($d['UnitUp'] ?? '-');
$bongkar = !empty($d['AdaBongkar']);

function cba_sisi(array $d, string $s, bool $ada, PDO $conn): array {
    $g = function ($k) use ($d, $s) { return $d[$s . '_' . $k] ?? null; };
    $imei = trim((string) $g('Imei'));
    if ($imei !== '' && trim((string) $g('NamaModem')) !== '') { $imei .= ' (' . $g('NamaModem') . ')'; }
    $lat = trim((string) $g('Lat')); $lng = trim((string) $g('Long'));
    $pos = (string) $g('Posisi'); $lampu = (string) $g('Lampu');
    $kontak_s41 = trim((string) $g('KontakS41')) !== '' ? $g('KontakS41') : ($ada ? ph_nomor_sim($conn, $g('Sim')) : null);
    $kontak_mdm = trim((string) $g('KontakModem')) !== '' ? $g('KontakModem') : $kontak_s41;
    return [
        'ada' => $ada, 'imei' => $imei, 'sim' => $g('Sim'), 'kontak_mdm' => $kontak_mdm, 'noref' => $g('NoRef'),
        'kontak_s41' => $kontak_s41, 'long' => $lng, 'lat' => $lat, 'pos' => $pos, 'lampu' => $lampu,
        'f_barang' => !empty($g('FotoBarang')), 'f_pasang' => !empty($g('FotoPasang')),
    ];
}
$K = cba_sisi($d, 'K', $bongkar, $conn);
$P = cba_sisi($d, 'P', true, $conn);
$nama_pel = ph_nama_pelanggan($conn, $d['IdPel']);

$fmt_tikor = function (array $x) { return 'Long: ' . cba_v($x['ada'] ? $x['long'] : '') . '&nbsp;&nbsp;&nbsp; Lat: ' . cba_v($x['ada'] ? $x['lat'] : ''); };
$baris_teks = function (string $label, string $kunci, array $K, array $P) {
    echo '<tr><td class="lbl">' . $label . '</td><td>' . ($K['ada'] ? cba_v($K[$kunci]) : '-') . '</td><td>' . cba_v($P[$kunci]) . '</td></tr>';
};
$baris_pilihan = function (string $label, array $opsi, string $kunci, array $K, array $P) {
    echo '<tr><td class="lbl">' . $label . '</td>';
    foreach ([$K, $P] as $x) {
        echo '<td class="pilihan">';
        foreach ($opsi as $nilai => $teks) { echo '<span>' . cba_cb($x['ada'] && $x[$kunci] === $nilai) . ' ' . $teks . '</span> '; }
        echo '</td>';
    }
    echo '</tr>';
};
$punya_foto = $P['f_pasang'] || $P['f_barang'] || $K['f_pasang'] || $K['f_barang'];
$url_foto = 'foto.php?no=' . urlencode($d['NoFba']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Berita Acara - <?= ph_e($d['NoFba']) ?></title>
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
            <td style="width:25%;text-align:left;">PT PLN (PERSERO)<br>UID BANTEN<br><?= ph_e($unit) ?></td>
            <td style="width:50%;">FORMULIR<br>PERINTAH KERJA</td>
            <td style="width:25%;">NOMOR DOKUMEN<br>FM-2026-Z</td>
        </tr>
    </table>
    <table>
        <tr><td class="lbl">Unit</td><td colspan="2">: <?= ph_e($unit) ?></td></tr>
        <tr><td class="lbl">Tanggal</td><td colspan="2">: <?= ph_e($tgl) ?></td></tr>
        <tr>
            <td class="lbl">Jenis PK</td>
            <td colspan="2" class="pilihan">
                <span><?= cba_cb(false) ?> PASANG</span> <span><?= cba_cb(false) ?> BONGKAR</span> <span><?= cba_cb(true) ?> <strong>PEMELIHARAAN</strong></span>
            </td>
        </tr>
        <tr><td class="lbl">Nomor Form</td><td colspan="2">: <?= ph_e($d['NoFpk']) ?> &nbsp;|&nbsp; BA: <?= ph_e($d['NoFba']) ?><?= !empty($d['NoFormOrder']) ? ' &nbsp;|&nbsp; Order: ' . ph_e($d['NoFormOrder']) : '' ?></td></tr>
        <tr><td class="lbl">Id Pelanggan</td><td colspan="2">: <?= ph_e($d['IdPel']) ?></td></tr>
        <tr><td class="lbl">Nama</td><td colspan="2">: <?= cba_v($nama_pel) ?></td></tr>
        <tr>
            <td class="lbl">Tikor DIL</td>
            <td>Long: <?= cba_v($d['LongDil']) ?></td>
            <td>Lat: <?= cba_v($d['LatDil']) ?></td>
        </tr>

        <tr class="sub"><th></th><th>BONGKAR</th><th>PASANG</th></tr>

        <tr class="grp"><td colspan="3">MODEM</td></tr>
        <?php $baris_teks('IMEI', 'imei', $K, $P); ?>
        <?php $baris_teks('Nomor SIM', 'sim', $K, $P); ?>
        <?php $baris_teks('Nomor Kontak', 'kontak_mdm', $K, $P); ?>

        <tr class="grp"><td colspan="3">S41</td></tr>
        <?php $baris_teks('NoRef IMEI', 'noref', $K, $P); ?>
        <?php $baris_teks('Nomor SIM', 'sim', $K, $P); ?>
        <?php $baris_teks('Nomor Kontak', 'kontak_s41', $K, $P); ?>

        <tr><td class="lbl">Tikor</td><td><?= $fmt_tikor($K) ?></td><td><?= $fmt_tikor($P) ?></td></tr>
        <?php $baris_pilihan('Posisi', ['PANGKAL' => 'Pangkal', 'TENGAH' => 'Tengah', 'UJUNG' => 'Ujung'], 'pos', $K, $P); ?>
        <?php $baris_pilihan('Lampu indikator', ['MENYALA' => 'Menyala', 'TIDAK' => 'Tidak Menyala'], 'lampu', $K, $P); ?>
        <tr>
            <td class="lbl">Foto Barang</td>
            <?php foreach ([$K, $P] as $x): ?>
                <td class="pilihan"><span><?= cba_cb($x['f_barang']) ?> Ada Dilampirkan</span> <span><?= cba_cb($x['ada'] && !$x['f_barang']) ?> Tidak Dilampirkan</span></td>
            <?php endforeach; ?>
        </tr>
        <tr>
            <td class="lbl">Foto Terpasang</td>
            <?php foreach ([$K, $P] as $x): ?>
                <td class="pilihan"><span><?= cba_cb($x['f_pasang']) ?> Ada Dilampirkan</span> <span><?= cba_cb($x['ada'] && !$x['f_pasang']) ?> Tidak Dilampirkan</span></td>
            <?php endforeach; ?>
        </tr>
    </table>

    <table class="ttd">
        <tr>
            <td>Memasang,<br>Petugas<span class="nama">( <?= ph_e($d['KdPetugas']) ?> )</span></td>
            <td>Memeriksa,<br>Staf Pengendalian Piutang<span class="nama">( ____________________ )</span></td>
            <td>Menyetujui,<br>Team Leader Pengendalian Piutang<span class="nama">( ____________________ )</span></td>
        </tr>
    </table>
    <table class="catatan">
        <tr><td><strong><u>Catatan Pemasangan:</u></strong><?= $bongkar ? '<br>Perangkat diganti (mutasi): SIM / modem lama dibongkar dan diganti dengan perangkat baru.' : '' ?></td></tr>
    </table>

    <?php if ($punya_foto): ?>
    <div class="foto-box">
        <h4 style="margin:0 0 4px;">Dokumentasi Pekerjaan Lapangan</h4>
        <?php if ($P['f_pasang']): ?><figure><img src="<?= ph_e($url_foto . '&jenis=pasang&sisi=pasang') ?>" alt="Foto Terpasang"><figcaption>Foto terpasang (PASANG)</figcaption></figure><?php endif; ?>
        <?php if ($P['f_barang']): ?><figure><img src="<?= ph_e($url_foto . '&jenis=barang&sisi=pasang') ?>" alt="Foto Barang"><figcaption>Foto barang (PASANG)</figcaption></figure><?php endif; ?>
        <?php if ($K['f_pasang']): ?><figure><img src="<?= ph_e($url_foto . '&jenis=pasang&sisi=bongkar') ?>" alt="Foto Terpasang Lama"><figcaption>Foto terpasang (BONGKAR)</figcaption></figure><?php endif; ?>
        <?php if ($K['f_barang']): ?><figure><img src="<?= ph_e($url_foto . '&jenis=barang&sisi=bongkar') ?>" alt="Foto Barang Lama"><figcaption>Foto barang (BONGKAR)</figcaption></figure><?php endif; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>