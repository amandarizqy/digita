<?php
// modules/pemasangan/cetak_ba.php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['NamaAkun'])) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$no_order = trim((string) ($_GET['no_order'] ?? ''));
$no_fba   = trim((string) ($_GET['no_fba'] ?? ''));

if ($no_order === '' && $no_fba === '') {
    http_response_code(400);
    exit('Nomor Order atau Nomor BA tidak ditemukan.');
}

const BA_BARIS_MIN = 25;

try {
    $order = null;
    $rows  = [];

    if ($no_order !== '') {
        $st = $conn->prepare('SELECT o.NoFormOrder, o.TglOrder, o.KdPetugas, o.UnitUp, u.NamaUnit
                              FROM frm_order o
                              LEFT JOIN master_up u ON u.UnitUp = o.UnitUp
                              WHERE o.NoFormOrder = ? LIMIT 1');
        $st->execute([$no_order]);
        $order = $st->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            http_response_code(404);
            exit('Order tidak ditemukan.');
        }

        $st = $conn->prepare("SELECT p.NoFpk, p.TglFpk, p.JenisPk, p.IdPel, p.LongDil, p.LatDil,
                                     pd.NoRef, pd.SimId, pd.ImeiModem, pd.NomorBarang,
                                     d.Nama,
                                     b.NoFba, b.TglFba, b.NamaAkun AS PetugasBa,
                                     bd.PosisiPasang, bd.LampuIndikator, bd.`Long` AS LongPasang, bd.`Lat` AS LatPasang,
                                     bd.FotoBarang, bd.FotoTerpasang
                              FROM frm_order_detil od
                              JOIN frm_pk p ON p.NoFpk = od.NoFpk
                              LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk
                              LEFT JOIN dil d ON d.IdPel = p.IdPel
                              LEFT JOIN frm_ba b ON b.NoFpk = p.NoFpk AND b.JenisPekerjaan = 'PASANG'
                              LEFT JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.NoRef = pd.NoRef AND bd.StatusPekerjaan = 'PASANG'
                              WHERE od.NoFormOrder = ?
                              ORDER BY p.NoFpk ASC");
        $st->execute([$no_order]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $st = $conn->prepare("SELECT p.NoFpk, p.TglFpk, p.JenisPk, p.IdPel, p.LongDil, p.LatDil, p.UnitUp,
                                     u.NamaUnit,
                                     pd.NoRef, pd.SimId, pd.ImeiModem, pd.NomorBarang,
                                     d.Nama,
                                     b.NoFba, b.TglFba, b.KdPetugas, b.NamaAkun AS PetugasBa,
                                     bd.PosisiPasang, bd.LampuIndikator, bd.`Long` AS LongPasang, bd.`Lat` AS LatPasang,
                                     bd.FotoBarang, bd.FotoTerpasang
                              FROM frm_ba b
                              JOIN frm_pk p ON p.NoFpk = b.NoFpk
                              LEFT JOIN master_up u ON u.UnitUp = p.UnitUp
                              LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk
                              LEFT JOIN dil d ON d.IdPel = p.IdPel
                              LEFT JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.StatusPekerjaan = 'PASANG'
                              WHERE b.NoFba = ?
                              ORDER BY p.NoFpk ASC");
        $st->execute([$no_fba]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            http_response_code(404);
            exit('Berita Acara tidak ditemukan.');
        }
    }
} catch (PDOException $e) {
    http_response_code(500);
    exit('Gagal memuat Formulir Mutasi Pemasangan: ' . $e->getMessage());
}

if ($order) {
    $tgl  = $order['TglOrder'] ? date('d-m-Y', strtotime($order['TglOrder'])) : '-';
    $unit = trim((string) ($order['NamaUnit'] ?? '')) !== '' ? $order['NamaUnit'] : ($order['UnitUp'] ?? '-');
} else {
    $tgl  = !empty($rows[0]['TglFba']) ? date('d-m-Y', strtotime($rows[0]['TglFba'])) : '-';
    $unit = trim((string) ($rows[0]['NamaUnit'] ?? '')) !== '' ? $rows[0]['NamaUnit'] : ($rows[0]['UnitUp'] ?? '-');
}

$total_baris = max(BA_BARIS_MIN, count($rows));

function v($val) { return htmlspecialchars(trim((string)$val) === '' ? '' : $val); }
function cb($cond) { return $cond ? '&#9745;' : '&#9744;'; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Mutasi & Berita Acara Pasang - <?= htmlspecialchars($order['NoFormOrder'] ?? $no_fba) ?></title>
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
        .foto-wrap { text-align: center; padding: 6px; }
        .foto-wrap img { max-width: 100%; max-height: 155px; object-fit: contain; border: 1px solid #ccc; }
        
        .page-break { page-break-before: always; margin-top: 30px; }
        .no-print { text-align: right; margin-bottom: 15px; }
        .bg-light { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; }
        @media print { .no-print { display: none; } body { padding: 0; } }
    </style>
</head>
<body onload="window.print()">
    <!-- TOMBOL PRINT -->
    <div class="no-print">
        <button onclick="window.print()" style="padding:8px 15px;background:#198754;color:#fff;border:none;cursor:pointer;font-weight:bold;border-radius:4px;">Cetak / Simpan PDF</button>
    </div>

    <?php $first_page = true; ?>

    <!-- ========================================================================= -->
    <!-- HALAMAN 1: REKAP FORMULIR ORDER / MUTASI PEMASANGAN (SAMA DGN CETAK ORDER)-->
    <!-- ========================================================================= -->
    <?php if ($order): $first_page = false; ?>
    <table>
        <tr class="judul">
            <td style="width:25%;text-align:left;">PT PLN (PERSERO)<br>UID BANTEN<br><?= htmlspecialchars($unit) ?></td>
            <td style="width:50%; font-size:13px;">FORMULIR<br>HASIL MUTASI PEMASANGAN (SPK)</td>
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
    <?php endif; ?>

    <!-- ========================================================================= -->
    <!-- HALAMAN SELANJUTNYA: DETAIL FPK & BERITA ACARA TERISI LENGKAP + FOTO      -->
    <!-- ========================================================================= -->
    <?php foreach ($rows as $d): ?>
        <?php
        if (!$first_page) {
            echo '<div class="page-break"></div>';
        }
        $first_page = false;

        $cb_pasang   = ($d['JenisPk'] === 'PASANG') ? '&#9745;' : '&#9744;';
        $cb_bongkar  = ($d['JenisPk'] === 'BONGKAR') ? '&#9745;' : '&#9744;';
        $cb_pelihara = ($d['JenisPk'] === 'PEMELIHARAAN') ? '&#9745;' : '&#9744;';

        $posisi      = trim((string) ($d['PosisiPasang'] ?? ''));
        $lampu       = trim((string) ($d['LampuIndikator'] ?? ''));
        $ada_foto_brg = !empty($d['FotoBarang']);
        $ada_foto_psg = !empty($d['FotoTerpasang']);
        $tgl_fpk_fmt  = !empty($d['TglFpk']) ? date('d-m-Y', strtotime($d['TglFpk'])) : '-';
        $tgl_pasang_ba = !empty($d['TglFba']) ? date('d-m-Y', strtotime($d['TglFba'])) : '-';
        $long_pasang = trim((string) ($d['LongPasang'] ?? '')) !== '' ? $d['LongPasang'] : ($d['LongDil'] ?? '-');
        $lat_pasang  = trim((string) ($d['LatPasang'] ?? '')) !== '' ? $d['LatPasang'] : ($d['LatDil'] ?? '-');
        ?>
        <table>
            <tr class="judul">
                <td style="width:25%; text-align:left;">PT PLN (PERSERO)<br>UID BANTEN<br><?= v($unit) ?></td>
                <td style="width:50%; font-size:14px;">BERITA ACARA<br>PERINTAH KERJA PEMASANGAN</td>
                <td style="width:25%; font-weight:normal;">NOMOR BA<br><?= v($d['NoFba'] ?: 'FM-2026-Z') ?></td>
            </tr>
        </table>
        <table>
            <tr><td class="lbl-fpk">Unit</td><td colspan="2">: <?= v($unit) ?></td></tr>
            <tr><td class="lbl-fpk">Tanggal</td><td colspan="2">: <?= $tgl_fpk_fmt ?></td></tr>
            <tr><td class="lbl-fpk">Jenis PK</td><td colspan="2">: <?= $cb_pasang ?> PASANG &nbsp;&nbsp;&nbsp;&nbsp; <?= $cb_bongkar ?> BONGKAR &nbsp;&nbsp;&nbsp;&nbsp; <?= $cb_pelihara ?> PEMELIHARAAN</td></tr>
            <tr><td class="lbl-fpk">Nomor Form FPK</td><td colspan="2">: <?= v($d['NoFpk']) ?></td></tr>
            <tr><td class="lbl-fpk">Id Pelanggan</td><td colspan="2">: <?= v($d['IdPel']) ?></td></tr>
            <tr><td class="lbl-fpk">Nama</td><td colspan="2">: <?= v($d['Nama']) ?></td></tr>
            <tr><td class="lbl-fpk">Tikor DIL</td><td colspan="2">: Long: <?= v($d['LongDil'] ?: '-') ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Lat: <?= v($d['LatDil'] ?: '-') ?></td></tr>
            
            <tr class="judul bg-light"><td class="lbl-fpk"></td><td style="width:39%;">BONGKAR</td><td style="width:39%;">PASANG</td></tr>
            
            <tr><td class="judul" style="text-align:center;" colspan="3">MODEM</td></tr>
            <tr><td class="lbl-fpk">IMEI</td><td>: </td><td>: <?= v($d['ImeiModem']) ?></td></tr>
            <tr><td class="lbl-fpk">Nomor SIM</td><td>: </td><td>: <?= v($d['SimId']) ?></td></tr>
            <tr><td class="lbl-fpk">Nomor Kontak</td><td>: </td><td>: <?= v($d['NomorBarang']) ?></td></tr>
            
            <tr><td class="judul" style="text-align:center;" colspan="3">S41</td></tr>
            <tr><td class="lbl-fpk">NoRef IMEI</td><td>: </td><td>: <?= v($d['NoRef']) ?></td></tr>
            <tr><td class="lbl-fpk">Nomor SIM</td><td>: </td><td>: <?= v($d['SimId']) ?></td></tr>
            <tr><td class="lbl-fpk">Nomor Kontak</td><td>: </td><td>: <?= v($d['NomorBarang']) ?></td></tr>
            <tr><td class="lbl-fpk">Tanggal Pasang</td>
                <td>: .......................</td>
                <td>: <strong><?= $tgl_pasang_ba ?></strong></td>
            </tr>
            <tr><td class="lbl-fpk">Tikor</td>
                <td>: Long: ....................... Lat: .......................</td>
                <td>: Long: <strong><?= v($long_pasang) ?></strong> &nbsp; Lat: <strong><?= v($lat_pasang) ?></strong></td>
            </tr>
            <tr><td class="lbl-fpk">Posisi</td>
                <td>&#9744; Pangkal &nbsp; &#9744; Tengah &nbsp; &#9744; Ujung</td>
                <td><?= cb(strcasecmp($posisi, 'Pangkal') === 0) ?> Pangkal &nbsp; <?= cb(strcasecmp($posisi, 'Tengah') === 0) ?> Tengah &nbsp; <?= cb(strcasecmp($posisi, 'Ujung') === 0) ?> Ujung</td>
            </tr>
            <tr><td class="lbl-fpk">Lampu indikator</td>
                <td>&#9744; Menyala &nbsp;&nbsp;&nbsp;&nbsp; &#9744; Tidak Menyala</td>
                <td><?= cb($lampu === 'MENYALA') ?> Menyala &nbsp;&nbsp;&nbsp;&nbsp; <?= cb($lampu === 'TIDAK') ?> Tidak Menyala</td>
            </tr>
            <tr><td class="lbl-fpk">Foto Barang</td>
                <td>&#9744; Ada Dilampirkan &nbsp; &#9744; Tidak Dilampirkan</td>
                <td><?= cb($ada_foto_brg) ?> Ada Dilampirkan &nbsp; <?= cb(!$ada_foto_brg) ?> Tidak Dilampirkan</td>
            </tr>
            <tr><td class="lbl-fpk">Foto Terpasang</td>
                <td>&#9744; Ada Dilampirkan &nbsp; &#9744; Tidak Dilampirkan</td>
                <td><?= cb($ada_foto_psg) ?> Ada Dilampirkan &nbsp; <?= cb(!$ada_foto_psg) ?> Tidak Dilampirkan</td>
            </tr>
        </table>

        <!-- DOKUMENTASI FOTO ALAT & PEMASANGAN DI ATAS TTD -->
        <table>
            <tr class="judul bg-light">
                <td style="width:50%;">FOTO BARANG / PERANGKAT S41</td>
                <td style="width:50%;">FOTO TERPASANG DI LAPANGAN</td>
            </tr>
            <tr>
                <td class="foto-wrap">
                    <?php if ($ada_foto_brg): ?>
                        <img src="data:image/jpeg;base64,<?= base64_encode($d['FotoBarang']) ?>" alt="Foto Barang">
                    <?php else: ?>
                        <span style="color:#777; font-style:italic;">(Tidak ada foto barang)</span>
                    <?php endif; ?>
                </td>
                <td class="foto-wrap">
                    <?php if ($ada_foto_psg): ?>
                        <img src="data:image/jpeg;base64,<?= base64_encode($d['FotoTerpasang']) ?>" alt="Foto Terpasang">
                    <?php else: ?>
                        <span style="color:#777; font-style:italic;">(Tidak ada foto terpasang)</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <!-- TANDA TANGAN -->
        <table style="text-align:center;">
            <tr>
                <td style="width:33%; vertical-align:top; height:100px; padding-top:10px;">
                    Memasang,<br>Petugas<br><br><br><br><br>( <?= v($d['PetugasBa'] ?: '____________________') ?> )
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
                <td style="height:50px; vertical-align:top; font-weight:bold;">Catatan Pemasangan:<br></td>
            </tr>
        </table>
    <?php endforeach; ?>
</body>
</html>