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
    $nama_sql = ph_sql_nama_modem($conn);
    if ($type === 'mutasi') {
        [$sc, $sp]   = ph_scope('b');
        [$cari, $cp] = ph_cari_sql(ph_kolom_cari($conn, false));
        $sql = "SELECT b.NoFba, b.NoFpk, b.TglFba, b.IdPel, bd.NoRef, bd.SimId, bd.ImeiModem, $nama_sql AS NamaModem, b.WaktuData,
                       bk.SimId AS SimAwal, bk.ImeiModem AS ImeiAwal, (bk.NoFba IS NOT NULL) AS Termutasi
                FROM frm_ba b
                JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.StatusPekerjaan = 'PASANG'
                LEFT JOIN frm_ba_detil bk ON bk.NoFba = b.NoFba AND bk.StatusPekerjaan = 'BONGKAR'
                LEFT JOIN master_modem mm ON mm.ImeiModem = bd.ImeiModem
                WHERE b.JenisPekerjaan = 'PEMELIHARAAN' $sc $cari
                ORDER BY b.WaktuData DESC, b.NoFba DESC";
    } else {
        [$sc, $sp]   = ph_scope('p');
        [$cari, $cp] = ph_cari_sql(ph_kolom_cari($conn, true, true, ['pd.NoRef', 'COALESCE(bd.SimId, mb.SimId)', 'COALESCE(bd.ImeiModem, mb.ImeiModem)']));
        $sql = "SELECT o.NoFormOrder, p.NoFpk, b.NoFba, p.TglFpk, COALESCE(b.KdPetugas, o.KdPetugas) AS KdPetugas, p.IdPel, pd.NoRef, COALESCE(bd.SimId, mb.SimId) AS SimId, COALESCE(bd.ImeiModem, mb.ImeiModem) AS ImeiModem, $nama_sql AS NamaModem,
                       COALESCE(bd.NomorBarang, pd.NomorBarang) AS KontakS41, COALESCE(bd.NomorModem, pd.NomorModem) AS KontakModem,
                       bd.PosisiPasang, bd.LampuIndikator AS KondisiRelay, bd.`Long` AS Longitude, bd.Lat AS Latitude,
                       COALESCE(b.StatusData, p.StatusData) AS StatusData, p.WaktuData
                FROM frm_pk p
                LEFT JOIN frm_order_detil od ON od.NoFpk = p.NoFpk
                LEFT JOIN frm_order o ON o.NoFormOrder = od.NoFormOrder
                LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk AND pd.StatusBarang = 'DIL'
                LEFT JOIN master_barang mb ON mb.NoRef = pd.NoRef
                LEFT JOIN frm_ba b ON b.NoFpk = p.NoFpk AND b.JenisPekerjaan = 'PEMELIHARAAN'
                LEFT JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.StatusPekerjaan = 'PASANG'
                LEFT JOIN master_modem mm ON mm.ImeiModem = COALESCE(bd.ImeiModem, mb.ImeiModem)
                WHERE p.JenisPk = 'PEMELIHARAAN' $sc $cari
                ORDER BY p.WaktuData DESC, p.NoFpk DESC";
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute(array_merge($sp, $cp));
} catch (PDOException $e) {
    error_log('pemeliharaan export: ' . $e->getMessage());
    http_response_code(500);
    exit('Gagal membuat laporan.');
}

$filename = 'Laporan_Pemeliharaan_' . ucfirst($type) . '_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$teks = "style=\"mso-number-format:'\\@';\"";
echo "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head><body>\n";
echo '<h2>REKAPITULASI LAPORAN ' . strtoupper($type) . " PEMELIHARAAN S41 - DIGITA</h2>\n";
echo "<table border=\"1\"><thead><tr style=\"background-color:#ffc107;font-weight:bold;\">";

$kepala = ($type === 'mutasi')
    ? ['No', 'No BA', 'No FPK', 'Tgl BA', 'ID Pelanggan', 'No Ref', 'SIM Awal', 'SIM Sekarang', 'IMEI Awal', 'IMEI Sekarang', 'Nama Modem', 'Status Mutasi', 'Waktu Data']
    : ['No', 'No Order', 'No FPK', 'No BA', 'Tgl FPK', 'Kode Petugas', 'ID Pelanggan', 'No Ref', 'SIM ID', 'IMEI Modem', 'Nama Modem', 'Kontak S41', 'Kontak Modem', 'Posisi Pasang', 'Indikator Relay', 'Koordinat GPS', 'Status', 'Waktu Data'];
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
        echo "<td $teks>" . ph_xl($r['NoFba']) . '</td>'
           . "<td $teks>" . ph_xl($r['NoFpk'] ?? '-') . '</td>'
           . '<td>' . ph_xl($r['TglFba']) . '</td>'
           . "<td $teks>" . ph_xl($r['IdPel']) . '</td>'
           . "<td $teks>" . ph_xl($r['NoRef'] ?? '-') . '</td>'
           . "<td $teks>" . ph_xl($mut ? ($r['SimAwal'] ?? '-') : '-') . '</td>'
           . "<td $teks>" . ph_xl($r['SimId'] ?? '-') . '</td>'
           . "<td $teks>" . ph_xl($mut ? ($r['ImeiAwal'] ?? '-') : '-') . '</td>'
           . "<td $teks>" . ph_xl($r['ImeiModem'] ?? '-') . '</td>'
           . '<td>' . ph_xl($r['NamaModem'] ?? '-') . '</td>'
           . '<td>' . ($mut ? 'TERMUTASI' : 'BELUM DIMUTASI') . '</td>'
           . '<td>' . ph_xl($r['WaktuData']) . '</td>';
    } else {
        $kontak_s41   = trim((string) ($r['KontakS41'] ?? '')) !== '' ? $r['KontakS41'] : ph_nomor_sim($conn, $r['SimId'] ?? '');
        $kontak_modem = trim((string) ($r['KontakModem'] ?? '')) !== '' ? $r['KontakModem'] : $kontak_s41;
        $gps = (($r['Latitude'] ?? '') !== '' && ($r['Longitude'] ?? '') !== '') ? $r['Latitude'] . ', ' . $r['Longitude'] : '-';
        echo "<td $teks>" . ph_xl($r['NoFormOrder'] ?? '-') . '</td>'
           . "<td $teks>" . ph_xl($r['NoFpk']) . '</td>'
           . "<td $teks>" . ph_xl($r['NoFba'] ?? '-') . '</td>'
           . '<td>' . ph_xl($r['TglFpk']) . '</td>'
           . '<td>' . ph_xl($r['KdPetugas'] ?? '-') . '</td>'
           . "<td $teks>" . ph_xl($r['IdPel']) . '</td>'
           . "<td $teks>" . ph_xl($r['NoRef'] ?? '-') . '</td>'
           . "<td $teks>" . ph_xl($r['SimId'] ?? '-') . '</td>'
           . "<td $teks>" . ph_xl($r['ImeiModem'] ?? '-') . '</td>'
           . '<td>' . ph_xl($r['NamaModem'] ?? '-') . '</td>'
           . "<td $teks>" . ph_xl($kontak_s41 ?? '-') . '</td>'
           . "<td $teks>" . ph_xl($kontak_modem ?? '-') . '</td>'
           . '<td>' . ph_xl($r['PosisiPasang'] ?? '-') . '</td>'
           . '<td>' . ph_xl($r['KondisiRelay'] ?? '-') . '</td>'
           . "<td $teks>" . (preg_match('/^-?\d+(\.\d+)?, -?\d+(\.\d+)?$/', $gps) ? htmlspecialchars($gps, ENT_QUOTES, 'UTF-8') : ph_xl($gps)) . '</td>'
           . '<td>' . (($r['StatusData'] ?? '') === 'AKTIF' ? 'SELESAI' : 'PENDING') . '</td>'
           . '<td>' . ph_xl($r['WaktuData']) . '</td>';
    }
    echo "</tr>\n";
}
echo '</tbody></table></body></html>';