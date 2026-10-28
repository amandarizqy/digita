<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/pemeliharaan_helper.php';

if (!isset($_SESSION['NamaAkun']) || !ph_akses_monitoring()) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$type = (($_GET['type'] ?? 'order') === 'mutasi') ? 'mutasi' : 'order';

try {
    if ($type === 'mutasi') {
        $sql = "SELECT b.NoFba, b.NoFpk, b.TglFba, b.IdPel, bd.NoRef, bd.SimId, bd.ImeiModem, bd.StatusPekerjaan, b.WaktuData
                FROM frm_ba b
                JOIN frm_ba_detil bd ON b.NoFba = bd.NoFba
                WHERE b.JenisPekerjaan = 'PEMELIHARAAN' ORDER BY b.WaktuData DESC";
    } else {
        $sql = "SELECT p.NoFpk, b.NoFba, p.TglFpk, b.KdPetugas, p.IdPel, p.NomorBarang AS NoRef, p.SimId, p.ImeiModem,
                       bd.PosisiPasang, bd.LampuIndikator AS KondisiRelay, p.StatusData, p.WaktuData
                FROM frm_pk p
                LEFT JOIN frm_ba b ON p.NoFpk = b.NoFpk
                LEFT JOIN frm_ba_detil bd ON b.NoFba = bd.NoFba
                WHERE p.JenisPK = 'PEMELIHARAAN' ORDER BY p.WaktuData DESC";
    }
    $stmt = $conn->query($sql);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Gagal membuat laporan.');
}

$filename = 'Laporan_Pemeliharaan_' . ucfirst($type) . '_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$teks = "style=\"mso-number-format:'\\@';\"";
echo "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head><body>\n";
echo "<h2>REKAPITULASI LAPORAN " . strtoupper($type) . " PEMELIHARAAN S41 - DIGITA</h2>\n";
echo "<table border=\"1\"><thead><tr style=\"background-color:#ffc107;font-weight:bold;\">";

$kepala = ($type === 'mutasi') 
    ? ['No', 'No BA', 'No PK', 'Tgl BA', 'ID Pelanggan', 'No Ref', 'SIM ID', 'IMEI Modem', 'Status Pekerjaan', 'Waktu Data']
    : ['No', 'No PK', 'No BA', 'Tgl PK', 'Kode Petugas', 'ID Pelanggan', 'No Ref', 'SIM ID', 'IMEI Modem', 'Posisi Pasang', 'Indikator Relay', 'Status Data', 'Waktu Data'];

foreach ($kepala as $k) {
    echo '<th>' . ph_xl($k) . '</th>';
}
echo "</tr></thead><tbody>\n";

$no = 0;
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $no++;
    echo '<tr><td>' . $no . '</td>';
    if ($type === 'mutasi') {
        echo "<td $teks>" . ph_xl($r['NoFba']) . '</td>'
           . "<td $teks>" . ph_xl($r['NoFpk']) . '</td>'
           . '<td>' . ph_xl($r['TglFba']) . '</td>'
           . "<td $teks>" . ph_xl($r['IdPel']) . '</td>'
           . "<td $teks>" . ph_xl($r['NoRef']) . '</td>'
           . "<td $teks>" . ph_xl($r['SimId'] ?? '-') . '</td>'
           . "<td $teks>" . ph_xl($r['ImeiModem'] ?? '-') . '</td>'
           . '<td>' . ph_xl($r['StatusPekerjaan']) . '</td>'
           . '<td>' . ph_xl($r['WaktuData']) . '</td>';
    } else {
        echo "<td $teks>" . ph_xl($r['NoFpk']) . '</td>'
           . "<td $teks>" . ph_xl($r['NoFba'] ?? '-') . '</td>'
           . '<td>' . ph_xl($r['TglFpk']) . '</td>'
           . '<td>' . ph_xl($r['KdPetugas'] ?? '-') . '</td>'
           . "<td $teks>" . ph_xl($r['IdPel']) . '</td>'
           . "<td $teks>" . ph_xl($r['NoRef']) . '</td>'
           . "<td $teks>" . ph_xl($r['SimId'] ?? '-') . '</td>'
           . "<td $teks>" . ph_xl($r['ImeiModem'] ?? '-') . '</td>'
           . '<td>' . ph_xl($r['PosisiPasang'] ?? '-') . '</td>'
           . '<td>' . ph_xl($r['KondisiRelay'] ?? '-') . '</td>'
           . '<td>' . ph_xl($r['StatusData']) . '</td>'
           . '<td>' . ph_xl($r['WaktuData']) . '</td>';
    }
    echo "</tr>\n";
}
echo "</tbody></table></body></html>";