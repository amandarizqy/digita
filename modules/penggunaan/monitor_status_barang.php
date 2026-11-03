<?php
// modules/penggunaan/monitor_status_barang.php  -  Monitoring Status Barang (hanya baca)
// Menjawab per barang (tabel master_barang): apakah TERPASANG (IdPel terisi) atau BELUM, apakah AKTIF atau NONAKTIF (StatusData,
// diubah trigger saat tiket terbit), status relay ON/OFF, apakah punya TIKET AKTIF (trx_order_putus / trx_order_nyala), dan apakah sedang
// MENGIRIM PESAN (antrean sms_outbox) beserta balasan terakhir (sms_inbox).
// Wilayah : kode unit session (pg_scope()); akun tanpa AP / UP memilih Unit AP dahulu (pg_filter_unit()). Barang tanpa kode unit tidak tampil.
// Penautan: barang -> tiket lewat IdPel + NomorBarang (dari view vw_terpasang); barang -> SMS lewat 9 digit terakhir NomorBarang.
//           Barang yang belum ada di view (data SIM/IMEI/relay/unit belum lengkap) tidak punya NomorBarang, jadi tiket & SMS-nya tidak terlacak.
// SMS     : hanya $batas_sms SMS terakhir dari sms_outbox / sms_inbox yang diperiksa (bentuk tabel smsd standar; kolom opsional dideteksi).

$pesan_error = '';
$sumber_barang = 'vw_terpasang';
$tabel_outbox  = 'sms_outbox';
$tabel_inbox   = 'sms_inbox';
$batas_sms     = 2000;
if (!preg_match('/^\w+$/', $sumber_barang)) throw new RuntimeException('Nama view tidak valid.');
foreach ([$tabel_outbox, $tabel_inbox] as $id) if (!preg_match('/^\w+(\.\w+)?$/', $id)) throw new RuntimeException('Nama tabel SMS tidak valid.');
$q = fn(string $n) => '`' . str_replace('.', '`.`', $n) . '`';
$last9 = fn($n) => substr(preg_replace('/\D/', '', (string) $n), -9);

$u         = pg_unit_akun($conn);
$unit_siap = $u['upi'] !== '';
$f = $unit_siap ? pg_filter_unit($conn, $u) : ['perlu_filter' => false, 'error' => '', 'label' => '', 'par_unit' => [], 'u_eff' => $u, 'sel_ap' => '', 'sel_up' => '', 'opsi_ap' => [], 'opsi_up' => []];
if ($f['error']) $pesan_error = $f['error'];

// ---- filter daftar ----
$keyword = trim($_GET['keyword'] ?? '');
$pasang  = in_array($_GET['pasang'] ?? '', ['ya', 'tidak'], true) ? $_GET['pasang'] : '';
$status  = in_array($_GET['status'] ?? '', ['AKTIF', 'TIDAK'], true) ? $_GET['status'] : '';
$relay   = in_array($_GET['relay'] ?? '', ['ON', 'OFF'], true) ? $_GET['relay'] : '';
$proses  = ($_GET['proses'] ?? '') === 'ya' ? 'ya' : '';
$limit   = (int) ($_GET['limit'] ?? 25);
if (!in_array($limit, [10, 25, 50, 100], true)) $limit = 25;
$hal     = max(1, (int) ($_GET['hal'] ?? 1));

$status_sms = ['Reserved' => ['MENGIRIM', 'warning'], 'SendingOK' => ['TERKIRIM', 'success'], 'SendingError' => ['GAGAL', 'danger']];
$data = []; $total_data = 0;
$ringkas = ['total' => 0, 'pasang' => 0, 'aktif' => 0, 'tiket' => 0, 'kirim' => 0];
$tiket_putus = []; $tiket_nyala = []; $out_by = []; $in_by = []; $ada_status = false; $pesan_sms = '';

if (!$unit_siap) {
    $pesan_error = 'Akun Anda belum memiliki Unit UPI, sehingga data tidak dapat ditampilkan.';
} elseif (!$f['perlu_filter'] && !$pesan_error) {
    try {
        $p = []; $scope = pg_scope($f['u_eff'], 'b', 'Unit', $p);

        // ---- kolom tabel SMS (bentuk smsd standar; kolom opsional dideteksi) ----
        $kol_out = array_column($conn->query("SHOW COLUMNS FROM " . $q($tabel_outbox))->fetchAll(PDO::FETCH_ASSOC), 'Field');
        $kol_in  = array_column($conn->query("SHOW COLUMNS FROM " . $q($tabel_inbox))->fetchAll(PDO::FETCH_ASSOC), 'Field');
        foreach (['DestinationNumber', 'TextDecoded'] as $k) if (!in_array($k, $kol_out, true)) throw new RuntimeException("Tabel $tabel_outbox tidak punya kolom $k.");
        foreach (['SenderNumber', 'TextDecoded'] as $k) if (!in_array($k, $kol_in, true)) throw new RuntimeException("Tabel $tabel_inbox tidak punya kolom $k.");
        $ada_status = in_array('Status', $kol_out, true);
        $w_out = in_array('InsertIntoDB', $kol_out, true) ? 'InsertIntoDB' : (in_array('SendingDateTime', $kol_out, true) ? 'SendingDateTime' : null);
        $w_in  = in_array('ReceivingDateTime', $kol_in, true) ? 'ReceivingDateTime' : null;
        $urut_out = in_array('ID', $kol_out, true) ? 'ID' : ($w_out ?? 'DestinationNumber');
        $urut_in  = $w_in ?? (in_array('ID', $kol_in, true) ? 'ID' : 'SenderNumber');
        $sub_out = "SELECT DestinationNumber AS Nomor, TextDecoded, " . ($w_out ? "$w_out AS Waktu" : "NULL AS Waktu") . ", " . ($ada_status ? "Status" : "NULL AS Status")
                 . " FROM " . $q($tabel_outbox) . " ORDER BY $urut_out DESC LIMIT " . (int) $batas_sms;
        $sub_in  = "SELECT SenderNumber AS Nomor, TextDecoded, " . ($w_in ? "$w_in AS Waktu" : "NULL AS Waktu")
                 . " FROM " . $q($tabel_inbox) . " ORDER BY $urut_in DESC LIMIT " . (int) $batas_sms;

        // ---- ringkasan (seluruh barang di wilayah) ----
        $st = $conn->prepare("SELECT COUNT(*), COALESCE(SUM(b.IdPel IS NOT NULL),0), COALESCE(SUM(b.StatusData = 'AKTIF'),0) FROM master_barang b WHERE $scope");
        $st->execute($p);
        [$ringkas['total'], $ringkas['pasang'], $ringkas['aktif']] = array_map('intval', $st->fetch(PDO::FETCH_NUM));

        // barang punya tiket AKTIF (putus atau nyala) -> kondisi dipakai juga sebagai filter "proses"
        $ada_tiket = "(EXISTS (SELECT 1 FROM trx_order_putus tp WHERE tp.StatusData = 'AKTIF' AND tp.IdPel = b.IdPel AND tp.NomorBarang = v.NomorBarang)
                       OR EXISTS (SELECT 1 FROM trx_order_nyala tn WHERE tn.StatusData = 'AKTIF' AND tn.IdPel = b.IdPel AND tn.NomorBarang = v.NomorBarang))";
        $st = $conn->prepare("SELECT COUNT(DISTINCT b.NoRef) FROM master_barang b INNER JOIN `$sumber_barang` v ON v.NoRef = b.NoRef WHERE $scope AND $ada_tiket");
        $st->execute($p);
        $ringkas['tiket'] = (int) $st->fetchColumn();

        // barang yang sedang mengirim pesan: SMS keluar masih antre (Status 'Reserved'; bila tabel tanpa kolom Status, semua baris = antre)
        $pk = []; $scope_v = pg_scope($f['u_eff'], 'v', 'Unit', $pk);
        $st = $conn->prepare("SELECT COUNT(DISTINCT v.NoRef) FROM ($sub_out) o
                              INNER JOIN `$sumber_barang` v ON RIGHT(REPLACE(o.Nomor, '+', ''), 9) = RIGHT(REPLACE(v.NomorBarang, '+', ''), 9)
                              WHERE $scope_v" . ($ada_status ? " AND o.Status = 'Reserved'" : ''));
        $st->execute($pk);
        $ringkas['kirim'] = (int) $st->fetchColumn();

        // ---- daftar barang (satu halaman) ----
        $w = [$scope];
        if ($pasang === 'ya')    $w[] = 'b.IdPel IS NOT NULL';
        if ($pasang === 'tidak') $w[] = 'b.IdPel IS NULL';
        if ($status !== '') { $w[] = 'b.StatusData = :st'; $p[':st'] = $status; }
        if ($relay !== '')  { $w[] = 'b.StatusRelay = :rl'; $p[':rl'] = $relay; }
        if ($proses === 'ya') $w[] = $ada_tiket;
        if ($keyword !== '') {
            $w[] = '(b.NoRef LIKE :k1 OR b.IdPel LIKE :k2 OR b.SimId LIKE :k3 OR b.ImeiModem LIKE :k4 OR l.Nama LIKE :k5 OR v.NomorBarang LIKE :k6)';
            foreach (['k1', 'k2', 'k3', 'k4', 'k5', 'k6'] as $k) $p[":$k"] = '%' . $keyword . '%';
        }
        $from = "FROM master_barang b LEFT JOIN `$sumber_barang` v ON v.NoRef = b.NoRef LEFT JOIN dil l ON l.IdPel = b.IdPel WHERE " . implode(' AND ', $w);

        $st = $conn->prepare("SELECT COUNT(DISTINCT b.NoRef) $from");
        $st->execute($p);
        $total_data = (int) $st->fetchColumn();
        $hal = min($hal, max(1, (int) ceil($total_data / $limit)));

        $st = $conn->prepare("SELECT b.NoRef, MIN(b.SimId) AS SimId, MIN(b.ImeiModem) AS ImeiModem, MIN(b.StatusRelay) AS StatusRelay, MIN(b.IdPel) AS IdPel,
                                     MIN(b.UnitUp) AS UnitUp, MIN(b.StatusData) AS StatusData, MIN(b.WaktuData) AS WaktuData,
                                     MIN(NULLIF(l.Nama,'')) AS Nama, MIN(v.NomorBarang) AS NomorBarang, MAX(v.NoRef IS NOT NULL) AS Siap
                              $from GROUP BY b.NoRef ORDER BY b.NoRef LIMIT :lim OFFSET :off");
        foreach ($p as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->bindValue(':off', ($hal - 1) * $limit, PDO::PARAM_INT);
        $st->execute();
        $data = $st->fetchAll(PDO::FETCH_ASSOC);

        // ---- tiket aktif & SMS untuk barang pada halaman ini ----
        $nomor = array_values(array_unique(array_filter(array_map(fn($r) => (string) $r['NomorBarang'], $data), fn($x) => $x !== '')));
        if ($nomor) {
            $ph = implode(',', array_fill(0, count($nomor), '?'));
            $tk = ['putus' => [], 'nyala' => []];
            foreach (['putus' => ['trx_order_putus', 'TiketPutus'], 'nyala' => ['trx_order_nyala', 'TiketNyala']] as $jenis => [$tb, $pkt]) {
                $st = $conn->prepare("SELECT NomorBarang, $pkt AS Tiket, JenisTiket, WaktuData FROM $tb WHERE StatusData = 'AKTIF' AND NomorBarang IN ($ph) ORDER BY WaktuData DESC");
                $st->execute($nomor);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) if (!isset($tk[$jenis][$r['NomorBarang']])) $tk[$jenis][$r['NomorBarang']] = $r;   // terbaru per nomor
            }
            [$tiket_putus, $tiket_nyala] = [$tk['putus'], $tk['nyala']];
            $perlu = array_flip(array_map($last9, $nomor));
            try {
                foreach ($conn->query($sub_out)->fetchAll(PDO::FETCH_ASSOC) as $r) { $k = $last9($r['Nomor']); if (isset($perlu[$k]) && !isset($out_by[$k])) $out_by[$k] = $r; }
                foreach ($conn->query($sub_in)->fetchAll(PDO::FETCH_ASSOC)  as $r) { $k = $last9($r['Nomor']); if (isset($perlu[$k]) && !isset($in_by[$k]))  $in_by[$k]  = $r; }
            } catch (PDOException $e) {
                $pesan_sms = 'Data SMS belum dapat dimuat: ' . pg_e($e->getMessage());
            }
        }
    } catch (Throwable $e) {
        $pesan_error = 'Gagal memuat status barang: ' . pg_e($e->getMessage())
                     . ' (periksa hak SELECT akun database pada tabel/view terkait, dan kesamaan collation bila error "Illegal mix of collations").';
    }
}

include __DIR__ . '/../../templates/penggunaan/monitor_status_barang.php';