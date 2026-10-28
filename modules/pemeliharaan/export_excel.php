<?php
// Ekspor Excel laporan pemeliharaan: ?type=order|mutasi[&q=pencarian]
// Wajib login, hanya role monitoring, dan hanya data unit milik pengguna (aturan sama dengan halaman monitoring).
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/pemeliharaan_helper.php';

if (!isset($_SESSION['NamaAkun'])) {
    http_response_code(401);
    exit('Silakan login terlebih dahulu.');
}
if (!ph_akses_monitoring()) {
    http_response_code(403);
    exit('Anda tidak memiliki akses ekspor laporan pemeliharaan.');
}

// type hanya boleh 'order' atau 'mutasi' (nilai lain -> 'order'); tidak pernah dipakai mentah di header/nama file
$type = (($_GET['type'] ?? 'order') === 'mutasi') ? 'mutasi' : 'order';

[$sc, $sp]   = ph_scope('p');             // batas unit (gagal-tertutup bila akun tanpa unit)
[$cari, $cp] = ph_cari_sql();             // ikut pencarian yang sedang aktif di layar

try {
    if ($type === 'mutasi') {
        $sql = "SELECT p.NoFormPemeliharaan, p.TglPemeliharaan, p.IdPel, p.NoRef, p.SimId, p.ImeiModem, p.WaktuData,
                       d.SimId AS SimAwal, d.ImeiModem AS ImeiAwal,
                       (d.NoRef IS NOT NULL AND (NOT (p.SimId <=> d.SimId) OR NOT (p.ImeiModem <=> d.ImeiModem))) AS Termutasi
                FROM frm_pemeliharaan p
                LEFT JOIN frm_order_har_detil d ON d.NoFormOrder = p.NoFormOrder AND d.NoRef = p.NoRef
                WHERE 1=1 $sc $cari ORDER BY p.WaktuData DESC, p.NoFormPemeliharaan DESC";
    } else {
        $sql = "SELECT p.NoFormPemeliharaan, p.NoFormOrder, p.TglPemeliharaan, p.KdPetugas, p.IdPel, p.NoRef, p.SimId, p.ImeiModem,
                       p.KondisiRelay, p.PosisiPasang, p.Latitude, p.Longitude, p.StatusData, p.WaktuData
                FROM frm_pemeliharaan p
                WHERE 1=1 $sc $cari ORDER BY p.WaktuData DESC, p.NoFormPemeliharaan DESC";
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute(array_merge($sp, $cp));
} catch (PDOException $e) {
    error_log('pemeliharaan export: ' . $e->getMessage());
    http_response_code(500);
    exit('Gagal membuat laporan. Hubungi administrator.');
}

$filename = 'Laporan_Pemeliharaan_' . ucfirst($type) . '_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

$teks = "style=\"mso-number-format:'\\@';\"";     // paksa kolom kode tampil sebagai teks (tidak jadi 1.2E+15 dsb.)
echo "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head><body>\n";
echo '<h2>REKAPITULASI LAPORAN ' . ($type === 'mutasi' ? 'MUTASI ' : '') . "PEMELIHARAAN S41 - DIGITA</h2>\n";
echo "<table border=\"1\" cellpadding=\"5\" cellspacing=\"0\"><thead><tr style=\"background-color:#e9ecef;font-weight:bold;\">";

if ($type === 'mutasi') {
    $kepala = ['No', 'No Form Pemeliharaan', 'Tgl Pemeliharaan', 'ID Pelanggan', 'No Ref', 'SIM Awal', 'SIM Sekarang', 'IMEI Awal', 'IMEI Sekarang', 'Status Mutasi', 'Waktu Data'];
} else {
    $kepala = ['No', 'No Form Pemeliharaan', 'No Form Order', 'Tgl Pemeliharaan', 'Kode Petugas', 'ID Pelanggan', 'No Ref', 'SIM ID', 'IMEI Modem', 'Kondisi Relay', 'Posisi Pasang', 'Koordinat GPS', 'Status', 'Waktu Data'];
}
foreach ($kepala as $k) {
    echo '<th>' . ph_xl($k) . '</th>';
}
echo "</tr></thead><tbody>\n";

$no = 0;
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $no++;
    echo '<tr><td>' . $no . '</td>';
    if ($type === 'mutasi') {
        $mut = !empty($r['Termutasi']);
        echo "<td $teks>" . ph_xl($r['NoFormPemeliharaan']) . '</td>'
           . '<td>' . ph_xl($r['TglPemeliharaan']) . '</td>'
           . "<td $teks>" . ph_xl($r['IdPel']) . '</td>'
           . "<td $teks>" . ph_xl($r['NoRef']) . '</td>'
           . "<td $teks>" . ph_xl($r['SimAwal'] ?? '-') . '</td>'
           . "<td $teks>" . ph_xl($r['SimId'] ?? '-') . '</td>'
           . "<td $teks>" . ph_xl($r['ImeiAwal'] ?? '-') . '</td>'
           . "<td $teks>" . ph_xl($r['ImeiModem'] ?? '-') . '</td>'
           . '<td>' . ($mut ? 'TERMUTASI' : 'BELUM DIMUTASI') . '</td>'
           . '<td>' . ph_xl($r['WaktuData']) . '</td>';
    } else {
        $gps = (($r['Latitude'] ?? '') !== '' && ($r['Longitude'] ?? '') !== '') ? $r['Latitude'] . ', ' . $r['Longitude'] : '-';
        echo "<td $teks>" . ph_xl($r['NoFormPemeliharaan']) . '</td>'
           . "<td $teks>" . ph_xl($r['NoFormOrder'] ?? '-') . '</td>'
           . '<td>' . ph_xl($r['TglPemeliharaan']) . '</td>'
           . '<td>' . ph_xl($r['KdPetugas']) . '</td>'
           . "<td $teks>" . ph_xl($r['IdPel']) . '</td>'
           . "<td $teks>" . ph_xl($r['NoRef']) . '</td>'
           . "<td $teks>" . ph_xl($r['SimId'] ?? '-') . '</td>'
           . "<td $teks>" . ph_xl($r['ImeiModem'] ?? '-') . '</td>'
           . '<td>' . ph_xl($r['KondisiRelay'] ?? '-') . '</td>'
           . '<td>' . ph_xl($r['PosisiPasang'] ?? '-') . '</td>'
           . '<td>' . ph_xl($gps) . '</td>'
           . '<td>' . (($r['StatusData'] ?? '') === 'AKTIF' ? 'SELESAI' : 'PENDING') . '</td>'
           . '<td>' . ph_xl($r['WaktuData']) . '</td>';
    }
    echo "</tr>\n";
}
echo "</tbody></table></body></html>";