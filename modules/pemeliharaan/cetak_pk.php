<?php
// Cetak Formulir Perintah Kerja (PK) Pemeliharaan, dipanggil dari Eksekusi > Mutasi Pemeliharaan.
// Bisa dicetak sebelum eksekusi (kolom lapangan dikosongkan agar diisi tangan oleh petugas)
// maupun sesudah eksekusi (kolom lapangan terisi dari Berita Acara).
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/pemeliharaan_helper.php';

if (!isset($_SESSION['NamaAkun']) || !ph_akses_monitoring()) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$no_fpk = trim((string) ($_GET['no_fpk'] ?? ''));
if ($no_fpk === '') {
    http_response_code(400);
    exit('Nomor FPK tidak ditemukan.');
}

function cpk_cb($aktif) { return $aktif ? '&#9745;' : '&#9744;'; }
function cpk_v($v) { $v = trim((string) $v); return $v === '' ? '&nbsp;' : ph_e($v); }

[$sc, $sp] = ph_scope('p');
try {
    $nm_k = ph_sql_nama_modem($conn, 'mk');
    $nm_p = ph_sql_nama_modem($conn, 'mp');
    $st = $conn->prepare("SELECT p.NoFpk, p.TglFpk, p.IdPel, p.LongDil, p.LatDil, p.UnitUp, u.NamaUnit,
                                 o.NoFormOrder, o.KdPetugas,
                                 pd.NoRef AS K_NoRef, pd.SimId AS K_Sim, pd.ImeiModem AS K_Imei, pd.NomorBarang AS K_Kontak, $nm_k AS K_NamaModem,
                                 pp.NoRef AS P_NoRef, pp.SimId AS P_Sim, pp.ImeiModem AS P_Imei, pp.NomorBarang AS P_Kontak, $nm_p AS P_NamaModem,
                                 b.NoFba,
                                 bk.PosisiPasang AS K_Posisi, bk.LampuIndikator AS K_Lampu, bk.`Long` AS K_Long, bk.Lat AS K_Lat,
                                 (bk.FotoBarang IS NOT NULL) AS K_FotoBarang, (bk.FotoTerpasang IS NOT NULL) AS K_FotoPasang,
                                 bd.PosisiPasang AS P_Posisi, bd.LampuIndikator AS P_Lampu, bd.`Long` AS P_Long, bd.Lat AS P_Lat,
                                 (bd.FotoBarang IS NOT NULL) AS P_FotoBarang, (bd.FotoTerpasang IS NOT NULL) AS P_FotoPasang,
                                 bd.NoRef AS BA_P_NoRef, bd.SimId AS BA_P_Sim, bd.ImeiModem AS BA_P_Imei, bd.NomorBarang AS BA_P_Kontak
                          FROM frm_pk p
                          LEFT JOIN frm_order_detil od ON od.NoFpk = p.NoFpk
                          LEFT JOIN frm_order o ON o.NoFormOrder = od.NoFormOrder
                          LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk AND pd.StatusBarang = 'DIL'
                          LEFT JOIN frm_pk_detil pp ON pp.NoFpk = p.NoFpk AND pp.StatusBarang = 'STOK'
                          LEFT JOIN frm_ba b ON b.NoFpk = p.NoFpk AND b.JenisPekerjaan = 'PEMELIHARAAN'
                          LEFT JOIN frm_ba_detil bk ON bk.NoFba = b.NoFba AND bk.StatusPekerjaan = 'BONGKAR'
                          LEFT JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.StatusPekerjaan = 'PASANG'
                          LEFT JOIN master_modem mk ON mk.ImeiModem = pd.ImeiModem
                          LEFT JOIN master_modem mp ON mp.ImeiModem = COALESCE(bd.ImeiModem, pp.ImeiModem, pd.ImeiModem)
                          LEFT JOIN master_up u ON u.UnitUp = p.UnitUp
                          WHERE p.NoFpk = ? AND p.JenisPk = 'PEMELIHARAAN'" . $sc . ' LIMIT 1');
    $st->execute(array_merge([$no_fpk], $sp));
    $d = $st->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('pemeliharaan cetak_pk: ' . $e->getMessage());
    http_response_code(500);
    exit('Gagal memuat Perintah Kerja.');
}

if (!$d) {
    http_response_code(404);
    exit('FPK tidak ditemukan atau di luar unit Anda.');
}

$ada_ba = !empty($d['NoFba']);
$tgl    = $d['TglFpk'] ? date('d-m-Y', strtotime($d['TglFpk'])) : '-';
$unit   = trim((string) ($d['NamaUnit'] ?? '')) !== '' ? $d['NamaUnit'] : ($d['UnitUp'] ?? '-');
$nama_pel = ph_nama_pelanggan($conn, $d['IdPel']);

/** Sisi BONGKAR: alat lama dari FPK. Kolom lapangan (tikor/posisi/lampu/foto) hanya terisi bila BA sudah ada. */
$K = [
    'imei' => trim((string) $d['K_Imei']) . (trim((string) $d['K_NamaModem']) !== '' ? ' (' . $d['K_NamaModem'] . ')' : ''),
    'sim' => $d['K_Sim'], 'noref' => $d['K_NoRef'],
    'kontak' => trim((string) $d['K_Kontak']) !== '' ? $d['K_Kontak'] : ph_nomor_sim($conn, $d['K_Sim']),
    'long' => $ada_ba ? $d['K_Long'] : '', 'lat' => $ada_ba ? $d['K_Lat'] : '',
    'pos' => $ada_ba ? (string) $d['K_Posisi'] : '', 'lampu' => $ada_ba ? (string) $d['K_Lampu'] : '',
    'f_barang' => $ada_ba && !empty($d['K_FotoBarang']), 'f_pasang' => $ada_ba && !empty($d['K_FotoPasang']),
];

/** Sisi PASANG: dari BA bila sudah dieksekusi, kalau belum dari rencana FPK; tanpa penggantian = sama dengan bongkar. */
$p_ref  = $ada_ba && !empty($d['BA_P_NoRef']) ? $d['BA_P_NoRef']  : ($d['P_NoRef'] ?: $d['K_NoRef']);
$p_sim  = $ada_ba && !empty($d['BA_P_Sim'])   ? $d['BA_P_Sim']    : ($d['P_Sim']   ?: $d['K_Sim']);
$p_imei = $ada_ba && !empty($d['BA_P_Imei'])  ? $d['BA_P_Imei']   : ($d['P_Imei']  ?: $d['K_Imei']);
$p_nama = $d['P_NamaModem'];
$p_knt  = $ada_ba && !empty($d['BA_P_Kontak']) ? $d['BA_P_Kontak'] : ($d['P_Kontak'] ?: ph_nomor_sim($conn, $p_sim));
$P = [
    'imei' => trim((string) $p_imei) . (trim((string) $p_nama) !== '' ? ' (' . $p_nama . ')' : ''),
    'sim' => $p_sim, 'noref' => $p_ref, 'kontak' => $p_knt,
    'long' => $ada_ba ? $d['P_Long'] : '', 'lat' => $ada_ba ? $d['P_Lat'] : '',
    'pos' => $ada_ba ? (string) $d['P_Posisi'] : '', 'lampu' => $ada_ba ? (string) $d['P_Lampu'] : '',
    'f_barang' => $ada_ba && !empty($d['P_FotoBarang']), 'f_pasang' => $ada_ba && !empty($d['P_FotoPasang']),
];

$baris_teks = function (string $label, string $kunci) use ($K, $P) {
    echo '<tr><td class="lbl">' . $label . '</td><td>' . cpk_v($K[$kunci]) . '</td><td>' . cpk_v($P[$kunci]) . '</td></tr>';
};
$baris_pilihan = function (string $label, array $opsi, string $kunci) use ($K, $P) {
    echo '<tr><td class="lbl">' . $label . '</td>';
    foreach ([$K, $P] as $x) {
        echo '<td class="pilihan">';
        foreach ($opsi as $nilai => $teks) { echo '<span>' . cpk_cb($x[$kunci] === $nilai) . ' ' . $teks . '</span> '; }
        echo '</td>';
    }
    echo '</tr>';
};
$tikor = function (array $x) { return 'Long: ' . cpk_v($x['long']) . '&nbsp;&nbsp;&nbsp; Lat: ' . cpk_v($x['lat']); };
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Perintah Kerja - <?= ph_e($d['NoFpk']) ?></title>
    <style>
        @page { size: A4; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 12px; color: #000; }
        .kertas { width: 100%; max-width: 780px; margin: auto; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #000; padding: 5px 6px; vertical-align: middle; }
        .judul td { text-align: center; }
        .lbl { width: 21%; white-space: nowrap; }
        .sub th { background: #fff; text-align: center; font-weight: 700; letter-spacing: .5px; }
        .grp td { text-align: center; font-weight: 700; }
        .pilihan span { white-space: nowrap; margin-right: 6px; }
        .ttd td { height: 95px; text-align: center; vertical-align: top; width: 33.33%; }
        .ttd .nama { display: block; margin-top: 62px; }
        .catatan td { height: 70px; vertical-align: top; }
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
                <span><?= cpk_cb(false) ?> PASANG</span> <span><?= cpk_cb(false) ?> BONGKAR</span> <span><?= cpk_cb(true) ?> <strong>PEMELIHARAAN</strong></span>
            </td>
        </tr>
        <tr><td class="lbl">Nomor Form</td><td colspan="2">: <?= ph_e($d['NoFpk']) ?><?= !empty($d['NoFormOrder']) ? ' &nbsp;|&nbsp; Order: ' . ph_e($d['NoFormOrder']) : '' ?><?= $ada_ba ? ' &nbsp;|&nbsp; BA: ' . ph_e($d['NoFba']) : '' ?></td></tr>
        <tr><td class="lbl">Id Pelanggan</td><td colspan="2">: <?= ph_e($d['IdPel']) ?></td></tr>
        <tr><td class="lbl">Nama</td><td colspan="2">: <?= cpk_v($nama_pel) ?></td></tr>
        <tr>
            <td class="lbl">Tikor DIL</td>
            <td>Long: <?= cpk_v($d['LongDil']) ?></td>
            <td>Lat: <?= cpk_v($d['LatDil']) ?></td>
        </tr>

        <tr class="sub"><th></th><th>BONGKAR</th><th>PASANG</th></tr>

        <tr class="grp"><td colspan="3">MODEM</td></tr>
        <?php $baris_teks('IMEI', 'imei'); ?>
        <?php $baris_teks('Nomor SIM', 'sim'); ?>
        <?php $baris_teks('Nomor Kontak', 'kontak'); ?>

        <tr class="grp"><td colspan="3">S41</td></tr>
        <?php $baris_teks('NoRef IMEI', 'noref'); ?>
        <?php $baris_teks('Nomor SIM', 'sim'); ?>
        <?php $baris_teks('Nomor Kontak', 'kontak'); ?>

        <tr><td class="lbl">Tikor</td><td><?= $tikor($K) ?></td><td><?= $tikor($P) ?></td></tr>
        <?php $baris_pilihan('Posisi', ['PANGKAL' => 'Pangkal', 'TENGAH' => 'Tengah', 'UJUNG' => 'Ujung'], 'pos'); ?>
        <?php $baris_pilihan('Lampu indikator', ['MENYALA' => 'Menyala', 'TIDAK' => 'Tidak Menyala'], 'lampu'); ?>
        <tr>
            <td class="lbl">Foto Barang</td>
            <?php foreach ([$K, $P] as $x): ?>
                <td class="pilihan"><span><?= cpk_cb($x['f_barang']) ?> Ada Dilampirkan</span> <span><?= cpk_cb($ada_ba && !$x['f_barang']) ?> Tidak Dilampirkan</span></td>
            <?php endforeach; ?>
        </tr>
        <tr>
            <td class="lbl">Foto Terpasang</td>
            <?php foreach ([$K, $P] as $x): ?>
                <td class="pilihan"><span><?= cpk_cb($x['f_pasang']) ?> Ada Dilampirkan</span> <span><?= cpk_cb($ada_ba && !$x['f_pasang']) ?> Tidak Dilampirkan</span></td>
            <?php endforeach; ?>
        </tr>
    </table>

    <table class="ttd">
        <tr>
            <td>Memasang,<br>Petugas<span class="nama">( <?= ph_e($d['KdPetugas'] ?? '____________________') ?> )</span></td>
            <td>Memeriksa,<br>Staf Pengendalian Piutang<span class="nama">( ____________________ )</span></td>
            <td>Menyetujui,<br>Team Leader Pengendalian Piutang<span class="nama">( ____________________ )</span></td>
        </tr>
    </table>
    <table class="catatan">
        <tr><td><strong><u>Catatan Pemasangan:</u></strong></td></tr>
    </table>
</div>
</body>
</html>