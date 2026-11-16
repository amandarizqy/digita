<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$action = $_GET['action'] ?? 'full'; // 'full' atau 'detail'
$no_form_array = $_GET['no_form'] ?? [];
$noref_array   = $_GET['noref'] ?? [];

if ($action === 'full' && empty($no_form_array)) die("Pilih minimal 1 formulir.");
if ($action === 'detail' && empty($noref_array)) die("Pilih minimal 1 NoRef alat.");

// Kop Surat Dinamis Berdasarkan User Login
$kop_upi = $_SESSION['NamaUnitUpi'] ?? 'UID BANTEN';
$kop_ap  = $_SESSION['NamaUnitAp'] ?? 'UP3 CIKOKOL';
$kop_up  = $_SESSION['NamaUnitUp'] ?? '';

// Helper Checkbox
function cek($cond) {
    return $cond ? '☑' : '☐';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Formulir Pengujian S41</title>
    <style>
        @page { size: A4 portrait; margin: 15mm; }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px; /* Ukuran font disesuaikan agar muat dalam 1 halaman */
            color: #000;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            box-sizing: border-box;
        }
        
        /* CLASS TABEL UTAMA FULL BORDER */
        .table-full-border {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
            margin-bottom: 20px;
        }
        .table-full-border th, .table-full-border td {
            border: 1px solid #000;
            padding: 4px;
            vertical-align: middle;
        }
        
        /* Pengaturan khusus kolom tabel */
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .fw-bold { font-weight: bold; }
        
        /* Baris judul KONDISI */
        .row-title td {
            background-color: #fff; /* Sesuai gambar, putih tanpa abu-abu */
            font-weight: bold;
            text-align: center;
        }

        .page-break { page-break-before: always; }
        .no-print { display: none; }
        
        @media screen {
            body { background: #525659; padding: 20px; }
            .container { background: #fff; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.5); margin-bottom: 20px; }
            .no-print { display: block; position: fixed; top: 20px; right: 20px; z-index: 999; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" style="padding:10px 20px; cursor:pointer; font-weight:bold; background:#0d6efd; color:#fff; border:none; border-radius:5px; box-shadow:0 2px 5px rgba(0,0,0,0.3);">🖨️ Cetak PDF</button>
</div>

<?php
// ==============================================================
// BLOK 1: CETAK FULL (RINGKASAN + DETAIL)
// ==============================================================
if ($action === 'full') {
    $first_page = true;
    foreach ($no_form_array as $no_form) {
        $stmt_head = $conn->prepare("SELECT * FROM formulir_pengujian WHERE NoFormulir = ?");
        $stmt_head->execute([$no_form]);
        $head = $stmt_head->fetch(PDO::FETCH_ASSOC);
        if (!$head) continue;

        $stmt_det = $conn->prepare("SELECT d.*, m.NomorAkun 
                                    FROM formulir_pengujian_detil d 
                                    LEFT JOIN master_nomor m ON d.SimId = m.SimId 
                                    WHERE d.NoFormulir = ? ORDER BY d.NoRef ASC");
        $stmt_det->execute([$no_form]);
        $items = $stmt_det->fetchAll(PDO::FETCH_ASSOC);
        
        if (!$first_page) echo '<div class="page-break"></div>';
        $first_page = false;
?>
        <!-- HALAMAN RINGKASAN -->
        <div class="container">
            <table class="table-full-border">
                <tr>
                    <td style="width: 25%; font-size: 11px;">
                        PT PLN (PERSERO)<br><?= htmlspecialchars($kop_upi) ?><br><?= htmlspecialchars($kop_ap) ?><br><?= htmlspecialchars($kop_up) ?>
                    </td>
                    <td class="text-center" style="width: 50%; font-size: 12px;">
                        FORMULIR<br>RINGKASAN HASIL PENGUJIAN PEMASANGAN
                    </td>
                    <td class="text-center" style="width: 25%; font-size: 11px;">
                        NOMOR<br><?= htmlspecialchars($no_form) ?>
                    </td>
                </tr>
                <tr>
                    <td colspan="3" style="padding: 0; border: none;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="width: 80px; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 4px;">Unit</td>
                                <td style="width: 10px; border-bottom: 1px solid #000; text-align: center;">:</td>
                                <td style="border-bottom: 1px solid #000; padding: 4px;"><?= htmlspecialchars($head['UnitUp']) ?></td>
                            </tr>
                            <tr>
                                <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 4px;">Tanggal Uji</td>
                                <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                                <td style="border-bottom: 1px solid #000; padding: 4px;"><?= date('d-m-Y', strtotime($head['TglUji'])) ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="3" style="padding: 0; border: none;">
                        <table style="width: 100%; border-collapse: collapse; text-align: center;">
                            <tr class="fw-bold">
                                <td style="width: 4%; border-bottom: 1px solid #000; border-right: 1px solid #000;">No</td>
                                <td style="width: 26%; border-bottom: 1px solid #000; border-right: 1px solid #000;">IMEI</td>
                                <td style="width: 14%; border-bottom: 1px solid #000; border-right: 1px solid #000;">Id Cocok</td>
                                <td style="width: 14%; border-bottom: 1px solid #000; border-right: 1px solid #000;">Cacat Fisik</td>
                                <td style="width: 14%; border-bottom: 1px solid #000; border-right: 1px solid #000;">Stiker QC</td>
                                <td style="width: 28%; border-bottom: 1px solid #000;">Status</td>
                            </tr>
                            <?php 
                            for ($i = 0; $i < 25; $i++): 
                                $it = $items[$i] ?? null;
                            ?>
                            <tr>
                                <td style="border-bottom: 1px solid #000; border-right: 1px solid #000;"><?= $i + 1 ?></td>
                                <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; text-align: left; padding-left: 5px;"><?= $it ? htmlspecialchars($it['ImeiModem']) : '' ?></td>
                                <td style="border-bottom: 1px solid #000; border-right: 1px solid #000;"><?= $it ? 'Ya | Tidak' : 'Ya | Tidak' ?></td>
                                <td style="border-bottom: 1px solid #000; border-right: 1px solid #000;"><?= $it ? 'Ya | Tidak' : 'Ya | Tidak' ?></td>
                                <td style="border-bottom: 1px solid #000; border-right: 1px solid #000;"><?= $it ? 'Ya | Tidak' : 'Ya | Tidak' ?></td>
                                <td style="border-bottom: 1px solid #000;"><?= $it ? 'Lolos Uji | Tidak Lolos Uji' : 'Lolos Uji | Tidak Lolos Uji' ?></td>
                            </tr>
                            <?php endfor; ?>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="3" style="padding: 0; border: none;">
                        <table style="width: 100%; border-collapse: collapse; text-align: center;">
                            <tr>
                                <td style="width: 33.33%; padding: 10px; border-right: 1px solid #000; vertical-align: top;">
                                    Menguji,<br>Staf Pengendalian Piutang<br><br><br><br><br><br>( _______________________ )
                                </td>
                                <td style="width: 33.33%; padding: 10px; border-right: 1px solid #000; vertical-align: top;">
                                    Memeriksa,<br>Team Leader Pengendalian Piutang<br><br><br><br><br><br>( _______________________ )
                                </td>
                                <td style="width: 33.33%; padding: 10px; vertical-align: top;">
                                    Menyetujui,<br>Asman Niaga/Manajer Unit Layanan<br><br><br><br><br><br>( _______________________ )
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="3" style="padding: 10px; text-align: left; vertical-align: top; height: 80px;">
                        <span style="font-weight: bold; text-decoration: underline;">Catatan Penerima:</span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- HALAMAN DETAIL ALAT (LOOPING) -->
        <?php foreach ($items as $idx => $it): ?>
            <div class="page-break"></div>
            <?php renderDetailTable($it, $head, $no_form, $kop_upi, $kop_ap, $kop_up); ?>
        <?php endforeach; ?>

<?php
    }
} 
// ==============================================================
// BLOK 2: CETAK DETAIL SAJA (DARI MODAL NOREF)
// ==============================================================
elseif ($action === 'detail') {
    $first_page = true;
    foreach ($noref_array as $noref) {
        $stmt_det = $conn->prepare("SELECT d.*, f.UnitUp, f.TglUji, f.NoFormulir, m.NomorAkun 
                                    FROM formulir_pengujian_detil d 
                                    JOIN formulir_pengujian f ON d.NoFormulir = f.NoFormulir
                                    LEFT JOIN master_nomor m ON d.SimId = m.SimId 
                                    WHERE d.NoRef = ?");
        $stmt_det->execute([$noref]);
        $it = $stmt_det->fetch(PDO::FETCH_ASSOC);
        
        if ($it) {
            $head = ['UnitUp' => $it['UnitUp'], 'TglUji' => $it['TglUji']];
            $no_form = $it['NoFormulir'];
            
            if (!$first_page) echo '<div class="page-break"></div>';
            $first_page = false;
            renderDetailTable($it, $head, $no_form, $kop_upi, $kop_ap, $kop_up);
        }
    }
}

// ==============================================================
// FUNGSI RENDER TABEL DETAIL (MENGHINDARI KODE BERULANG)
// ==============================================================
function renderDetailTable($it, $head, $no_form, $kop_upi, $kop_ap, $kop_up) {
?>
<div class="container">
    <table class="table-full-border">
        <tr>
            <td style="width: 25%;">
                PT PLN (PERSERO)<br><?= htmlspecialchars($kop_upi) ?><br><?= htmlspecialchars($kop_ap) ?><br><?= htmlspecialchars($kop_up) ?>
            </td>
            <td class="text-center" style="width: 50%;">
                FORMULIR<br>PENGUJIAN PEMASANGAN
            </td>
            <td class="text-center" style="width: 25%;">
                NOMOR<br><?= htmlspecialchars($no_form) ?>
            </td>
        </tr>
        <tr>
            <td colspan="3" style="padding: 0; border: none;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 120px; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">Unit</td>
                        <td style="width: 10px; border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td style="border-bottom: 1px solid #000; padding: 3px 5px;"><?= htmlspecialchars($head['UnitUp']) ?></td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">Tanggal Uji</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td style="border-bottom: 1px solid #000; padding: 3px 5px;"><?= date('d-m-Y', strtotime($head['TglUji'])) ?></td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">IMEI</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td style="border-bottom: 1px solid #000; padding: 3px 5px;"><?= htmlspecialchars($it['ImeiModem']) ?></td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">Nomor SIM</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td style="border-bottom: 1px solid #000; padding: 3px 5px;"><?= htmlspecialchars($it['SimId'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">Nomor Kontak</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td style="border-bottom: 1px solid #000; padding: 3px 5px;"><?= !empty($it['NomorAkun']) ? '+62 ' . ltrim($it['NomorAkun'], '0+62') : '' ?></td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr class="row-title"><td colspan="3">KONDISI ADMIN</td></tr>
        <tr>
            <td colspan="3" style="padding: 0; border: none;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 120px; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">SET ADMIN</td>
                        <td style="width: 10px; border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td style="width: 150px; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;"><?= cek($it['SetAdmin']=='BERHASIL') ?> Berhasil</td>
                        <td style="width: 150px; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;"><?= cek($it['SetAdmin']=='GAGAL') ?> Gagal</td>
                        <td style="border-bottom: 1px solid #000; padding: 3px 5px;">+62 <?= !empty($it['NomorAkun']) ? ltrim($it['NomorAkun'], '0+62') : '_______________________' ?></td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">DEL ADMIN</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;"><?= cek($it['DelAdmin']=='BERHASIL') ?> Berhasil</td>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;"><?= cek($it['DelAdmin']=='GAGAL') ?> Gagal</td>
                        <td style="border-bottom: 1px solid #000;"></td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">RESET ADMIN</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;"><?= cek($it['ResetAdmin']=='BERHASIL') ?> Berhasil</td>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;"><?= cek($it['ResetAdmin']=='GAGAL') ?> Gagal</td>
                        <td style="border-bottom: 1px solid #000;"></td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">LIST ADMIN</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;"><?= cek($it['ListAdmin']=='BERHASIL') ?> Berhasil</td>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;"><?= cek($it['ListAdmin']=='GAGAL') ?> Gagal</td>
                        <td style="border-bottom: 1px solid #000;"></td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr class="row-title"><td colspan="3">KONDISI AWAL</td></tr>
        <tr>
            <td colspan="3" style="padding: 0; border: none;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 120px; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">Cek phasa</td>
                        <td style="width: 10px; border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td style="width: 200px; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;"><?= cek($it['TeganganPhasa']=='ADA') ?> Ada Tegangan</td>
                        <td style="border-bottom: 1px solid #000; padding: 3px 5px;"><?= cek($it['TeganganPhasa']=='TIDAK') ?> Tidak Ada Tegangan</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">Lampu indikator</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;"><?= cek($it['LampuIndikator']=='AKTIF') ?> Menyala</td>
                        <td style="border-bottom: 1px solid #000; padding: 3px 5px;"><?= cek($it['LampuIndikator']=='TIDAK') ?> Tidak Menyala</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">PLNSTATUS</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td colspan="2" style="border-bottom: 1px solid #000; padding: 3px 5px; text-align: right;"><?= htmlspecialchars($it['DetikStatusAwal'] ?? '') ?> detik</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">Dijawab</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;"><?= cek($it['JawabanStatusAwal']=='ADA') ?> Ya</td>
                        <td style="border-bottom: 1px solid #000; padding: 3px 5px;"><?= cek($it['JawabanStatusAwal']=='TIDAK') ?> Tidak</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">Isi status</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;"><?= cek($it['IsiStatusAwal']=='SESUAI') ?> Sesuai</td>
                        <td style="border-bottom: 1px solid #000; padding: 3px 5px;"><?= cek($it['IsiStatusAwal']=='TIDAK') ?> Tidak Sesuai</td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr class="row-title"><td colspan="3">KONDISI DIPUTUS SEMENTARA</td></tr>
        <tr>
            <td colspan="3" style="padding: 0; border: none;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 120px; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">PLNOFF</td>
                        <td style="width: 10px; border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td colspan="2" style="border-bottom: 1px solid #000; padding: 3px 5px; text-align: right;"><?= htmlspecialchars($it['DetikPutus'] ?? '') ?> detik</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">Dijawab</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td style="width: 200px; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;"><?= cek($it['JawabanPutus']=='ADA') ?> Ya</td>
                        <td style="border-bottom: 1px solid #000; padding: 3px 5px;"><?= cek($it['JawabanPutus']=='TIDAK') ?> Tidak</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">Durasi Jawab</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td colspan="2" style="border-bottom: 1px solid #000; padding: 3px 5px; text-align: right;"><?= htmlspecialchars($it['DetikJawabanPutus'] ?? '') ?> detik</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">Lampu beban</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;"><?= cek($it['LampuDiputus']=='MENYALA') ?> Menyala</td>
                        <td style="border-bottom: 1px solid #000; padding: 3px 5px;"><?= cek($it['LampuDiputus']=='PADAM') ?> Tidak Menyala</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">PLNSTATUS</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td colspan="2" style="border-bottom: 1px solid #000; padding: 3px 5px; text-align: right;"><?= htmlspecialchars($it['DetikStatusPutus'] ?? '') ?> detik</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">Dijawab</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;"><?= cek($it['JawabanStatusPutus']=='ADA') ?> Ya</td>
                        <td style="border-bottom: 1px solid #000; padding: 3px 5px;"><?= cek($it['JawabanStatusPutus']=='TIDAK') ?> Tidak</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">Isi status</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;"><?= cek($it['IsiStatusPutus']=='SESUAI') ?> Sesuai</td>
                        <td style="border-bottom: 1px solid #000; padding: 3px 5px;"><?= cek($it['IsiStatusPutus']=='TIDAK') ?> Tidak Sesuai</td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr class="row-title"><td colspan="3">KONDISI DINYALAKAN KEMBALI</td></tr>
        <tr>
            <td colspan="3" style="padding: 0; border: none;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 120px; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">PLNON</td>
                        <td style="width: 10px; border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td colspan="2" style="border-bottom: 1px solid #000; padding: 3px 5px; text-align: right;"><?= htmlspecialchars($it['DetikNyala'] ?? '') ?> detik</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">Dijawab</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td style="width: 200px; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;"><?= cek($it['JawabanNyala']=='ADA') ?> Ya</td>
                        <td style="border-bottom: 1px solid #000; padding: 3px 5px;"><?= cek($it['JawabanNyala']=='TIDAK') ?> Tidak</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">Durasi Jawab</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td colspan="2" style="border-bottom: 1px solid #000; padding: 3px 5px; text-align: right;"><?= htmlspecialchars($it['DetikJawabanNyala'] ?? '') ?> detik</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">Lampu beban</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;"><?= cek($it['LampuMenyala']=='MENYALA') ?> Menyala</td>
                        <td style="border-bottom: 1px solid #000; padding: 3px 5px;"><?= cek($it['LampuMenyala']=='PADAM') ?> Tidak Menyala</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">PLNSTATUS</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td colspan="2" style="border-bottom: 1px solid #000; padding: 3px 5px; text-align: right;"><?= htmlspecialchars($it['DetikStatusNyala'] ?? '') ?> detik</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">Dijawab</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;"><?= cek($it['JawabanStatusNyala']=='ADA') ?> Ya</td>
                        <td style="border-bottom: 1px solid #000; padding: 3px 5px;"><?= cek($it['JawabanStatusNyala']=='TIDAK') ?> Tidak</td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;">Isi status</td>
                        <td style="border-bottom: 1px solid #000; text-align: center;">:</td>
                        <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px 5px;"><?= cek($it['IsiStatusNyala']=='SESUAI') ?> Sesuai</td>
                        <td style="border-bottom: 1px solid #000; padding: 3px 5px;"><?= cek($it['IsiStatusNyala']=='TIDAK') ?> Tidak Sesuai</td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td colspan="3" style="padding: 0; border: none;">
                <table style="width: 100%; border-collapse: collapse; text-align: center;">
                    <tr>
                        <td style="width: 33.33%; padding: 10px; border-right: 1px solid #000; vertical-align: top;">
                            Menguji,<br>Staf Pengendalian Piutang<br><br><br><br><br><br>( _______________________ )
                        </td>
                        <td style="width: 33.33%; padding: 10px; border-right: 1px solid #000; vertical-align: top;">
                            Memeriksa,<br>Team Leader Pengendalian Piutang<br><br><br><br><br><br>( _______________________ )
                        </td>
                        <td style="width: 33.33%; padding: 10px; vertical-align: top;">
                            Menyetujui,<br>Asman Niaga/Manajer Unit Layanan<br><br><br><br><br><br>( _______________________ )
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="3" style="padding: 5px 10px; text-align: left; vertical-align: top; height: 50px;">
                <span style="font-weight: bold; text-decoration: underline;">Catatan Pengujian:</span><br>
                <?= htmlspecialchars($it['Catatan'] ?? '') ?>
            </td>
        </tr>
    </table>
</div>
<?php
}
?>