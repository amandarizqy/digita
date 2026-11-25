<?php
// functions/penghapusan_cetak.php
// Formulir Perintah Kerja (FPK) Penghapusan untuk dicetak. Dipakai bersama oleh:
//   - modules/penghapusan/cetak_pk.php     (cetak satu FPK)
//   - modules/penghapusan/cetak_order.php  (cetak Order + semua FPK di dalamnya)
// Penghapusan hanya punya sisi BONGKAR (tidak ada alat pengganti).

function pgc_cb($aktif) { return $aktif ? '&#9745;' : '&#9744;'; }
function pgc_v($v) { $v = trim((string) $v); return $v === '' ? '&nbsp;' : pgh_e($v); }

/** Data lengkap satu FPK penghapusan. $sc/$sp = filter unit dari pgh_scope() (kosongkan bila sudah lolos filter lewat Order-nya). */
function pgc_ambil_fpk(PDO $conn, string $no_fpk, string $sc = '', array $sp = []): ?array
{
    $nm = pgh_sql_nama_modem($conn, 'mk');
    $st = $conn->prepare("SELECT p.NoFpk, p.TglFpk, p.IdPel, p.LongDil, p.LatDil, p.UnitUp, u.NamaUnit,
                                 o.NoFormOrder, o.KdPetugas,
                                 pd.NoRef AS K_NoRef, pd.SimId AS K_Sim, pd.ImeiModem AS K_Imei, pd.NomorBarang AS K_Kontak, $nm AS K_NamaModem,
                                 b.NoFba,
                                 bk.PosisiPasang AS K_Posisi, bk.LampuIndikator AS K_Lampu, bk.`Long` AS K_Long, bk.Lat AS K_Lat,
                                 (bk.FotoBarang IS NOT NULL) AS K_FotoBarang, (bk.FotoTerpasang IS NOT NULL) AS K_FotoPasang
                          FROM frm_pk p
                          LEFT JOIN frm_order_detil od ON od.NoFpk = p.NoFpk
                          LEFT JOIN frm_order o ON o.NoFormOrder = od.NoFormOrder
                          LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk AND pd.StatusBarang = 'DIL'
                          LEFT JOIN frm_ba b ON b.NoFpk = p.NoFpk AND b.JenisPekerjaan = 'BONGKAR'
                          LEFT JOIN frm_ba_detil bk ON bk.NoFba = b.NoFba AND bk.StatusPekerjaan = 'BONGKAR'
                          LEFT JOIN master_modem mk ON mk.ImeiModem = pd.ImeiModem
                          LEFT JOIN master_up u ON u.UnitUp = p.UnitUp
                          WHERE p.NoFpk = ? AND p.JenisPk = 'BERHENTI'" . $sc . ' LIMIT 1');
    $st->execute(array_merge([$no_fpk], $sp));
    $d = $st->fetch(PDO::FETCH_ASSOC);
    return $d ?: null;
}

/** HTML satu halaman Formulir Perintah Kerja. $halaman_baru = true => paksa mulai di halaman baru saat dicetak. */
function pgc_halaman_pk(PDO $conn, array $d, bool $halaman_baru = false): string
{
    $ada_ba   = !empty($d['NoFba']);
    $tgl      = $d['TglFpk'] ? date('d-m-Y', strtotime($d['TglFpk'])) : '-';
    $unit     = trim((string) ($d['NamaUnit'] ?? '')) !== '' ? $d['NamaUnit'] : ($d['UnitUp'] ?? '-');
    $nama_pel = pgh_nama_pelanggan($conn, $d['IdPel']);

    // Kolom lapangan (tikor/posisi/lampu/foto) hanya terisi bila Berita Acara sudah ada.
    $K = [
        'imei'   => trim((string) $d['K_Imei']) . (trim((string) $d['K_NamaModem']) !== '' ? ' (' . $d['K_NamaModem'] . ')' : ''),
        'sim'    => $d['K_Sim'], 'noref' => $d['K_NoRef'],
        'kontak' => trim((string) $d['K_Kontak']) !== '' ? $d['K_Kontak'] : pgh_nomor_sim($conn, $d['K_Sim']),
        'long'   => $ada_ba ? $d['K_Long'] : '', 'lat' => $ada_ba ? $d['K_Lat'] : '',
        'pos'    => $ada_ba ? (string) $d['K_Posisi'] : '', 'lampu' => $ada_ba ? (string) $d['K_Lampu'] : '',
        'f_barang' => $ada_ba && !empty($d['K_FotoBarang']), 'f_pasang' => $ada_ba && !empty($d['K_FotoPasang']),
    ];

    $baris_teks = function (string $label, string $kunci) use ($K) {
        echo '<tr><td class="lbl">' . $label . '</td><td>' . pgc_v($K[$kunci]) . '</td></tr>';
    };
    $baris_pilihan = function (string $label, array $opsi, string $kunci) use ($K) {
        echo '<tr><td class="lbl">' . $label . '</td><td class="pilihan">';
        foreach ($opsi as $nilai => $teks) { echo '<span>' . pgc_cb($K[$kunci] === $nilai) . ' ' . $teks . '</span> '; }
        echo '</td></tr>';
    };

    ob_start();
    ?>
    <div class="pk"<?= $halaman_baru ? ' style="page-break-before:always;"' : '' ?>>
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
                    <span><?= pgc_cb(false) ?> PASANG</span> <span><?= pgc_cb(true) ?> <strong>BONGKAR (PENGHAPUSAN)</strong></span> <span><?= pgc_cb(false) ?> PEMELIHARAAN</span>
                </td>
            </tr>
            <tr><td class="lbl">Nomor Form</td><td colspan="2">: <?= pgh_e($d['NoFpk']) ?><?= !empty($d['NoFormOrder']) ? ' &nbsp;|&nbsp; Order: ' . pgh_e($d['NoFormOrder']) : '' ?><?= $ada_ba ? ' &nbsp;|&nbsp; BA: ' . pgh_e($d['NoFba']) : '' ?></td></tr>
            <tr><td class="lbl">Id Pelanggan</td><td colspan="2">: <?= pgh_e($d['IdPel']) ?></td></tr>
            <tr><td class="lbl">Nama</td><td colspan="2">: <?= pgc_v($nama_pel) ?></td></tr>
            <tr>
                <td class="lbl">Tikor DIL</td>
                <td>Long: <?= pgc_v($d['LongDil']) ?></td>
                <td>Lat: <?= pgc_v($d['LatDil']) ?></td>
            </tr>
        </table>
        <table>
            <tr class="sub"><th style="width:21%;"></th><th>BONGKAR <span style="font-weight:400;">(alat dilepas dari pelanggan)</span></th></tr>

            <tr class="grp"><td colspan="2">MODEM</td></tr>
            <?php $baris_teks('IMEI', 'imei'); ?>
            <?php $baris_teks('Nomor SIM', 'sim'); ?>
            <?php $baris_teks('Nomor Kontak', 'kontak'); ?>

            <tr class="grp"><td colspan="2">S41</td></tr>
            <?php $baris_teks('NoRef IMEI', 'noref'); ?>
            <?php $baris_teks('Nomor SIM', 'sim'); ?>
            <?php $baris_teks('Nomor Kontak', 'kontak'); ?>

            <tr><td class="lbl">Tikor</td><td>Long: <?= pgc_v($K['long']) ?>&nbsp;&nbsp;&nbsp; Lat: <?= pgc_v($K['lat']) ?></td></tr>
            <?php $baris_pilihan('Posisi', ['PANGKAL' => 'Pangkal', 'TENGAH' => 'Tengah', 'UJUNG' => 'Ujung'], 'pos'); ?>
            <?php $baris_pilihan('Lampu indikator', ['MENYALA' => 'Menyala', 'TIDAK' => 'Tidak Menyala'], 'lampu'); ?>
            <tr>
                <td class="lbl">Foto Barang</td>
                <td class="pilihan"><span><?= pgc_cb($K['f_barang']) ?> Ada Dilampirkan</span> <span><?= pgc_cb($ada_ba && !$K['f_barang']) ?> Tidak Dilampirkan</span></td>
            </tr>
            <tr>
                <td class="lbl">Foto Terpasang</td>
                <td class="pilihan"><span><?= pgc_cb($K['f_pasang']) ?> Ada Dilampirkan</span> <span><?= pgc_cb($ada_ba && !$K['f_pasang']) ?> Tidak Dilampirkan</span></td>
            </tr>
        </table>

        <table class="ttd">
            <tr>
                <td>Membongkar,<br>Petugas<span class="nama">( <?= pgh_e($d['KdPetugas'] ?? '____________________') ?> )</span></td>
                <td>Memeriksa,<br>Staf Pengendalian Piutang<span class="nama">( ____________________ )</span></td>
                <td>Menyetujui,<br>Team Leader Pengendalian Piutang<span class="nama">( ____________________ )</span></td>
            </tr>
        </table>
        <table class="catatan">
            <tr><td><strong><u>Alasan / Catatan Penghapusan:</u></strong></td></tr>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

/** CSS cetak untuk halaman Rekap Order (.rekap) dan halaman FPK (.pk). */
function pgc_css(): string
{
    return <<<'CSS'
@page { size: A4; margin: 10mm; }
* { box-sizing: border-box; }
body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 12px; color: #000; }
.kertas { width: 100%; max-width: 780px; margin: auto; }
table { width: 100%; border-collapse: collapse; }
td, th { border: 1px solid #000; vertical-align: middle; }
tr { page-break-inside: avoid; }
.judul td { text-align: center; }
.mono { font-family: "Courier New", monospace; }
.no-print { text-align: right; margin-bottom: 10px; }
.pk td, .pk th { padding: 5px 6px; }
.pk .lbl { width: 21%; white-space: nowrap; }
.pk .sub th { background: #fff; text-align: center; font-weight: 700; letter-spacing: .5px; }
.pk .grp td { text-align: center; font-weight: 700; }
.pk .pilihan span { white-space: nowrap; margin-right: 6px; }
.pk .ttd td { height: 95px; text-align: center; vertical-align: top; width: 33.33%; }
.pk .ttd .nama { display: block; margin-top: 62px; }
.pk .catatan td { height: 70px; vertical-align: top; }
.rekap td, .rekap th { padding: 3px 6px; }
.rekap .lbl { width: 18%; white-space: nowrap; }
.rekap .daftar th { font-weight: 700; text-align: center; }
.rekap .daftar td { height: 19px; font-size: 10.5px; }
.rekap .daftar .no { width: 4%; text-align: left; }
.rekap .ttd td { height: 100px; text-align: center; vertical-align: top; width: 33.33%; }
.rekap .ttd .nama { display: block; margin-top: 66px; }
@media print { .no-print { display: none; } body { padding: 0; } }
CSS;
}