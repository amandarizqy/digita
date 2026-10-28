<?php
// modules/penggunaan/monitor_perintah.php  -  Monitoring Perintah (hanya baca)
// Tab: Tiket Putus (trx_order_putus) | Tiket Nyala (trx_order_nyala) | SMS Keluar (sms_outbox) | SMS Masuk (sms_inbox)
// Wilayah: selalu mengikuti kode unit session akun (pg_scope()). Tiket: kolom Unit* pada tabel tiket.
//          SMS: dicocokkan ke perangkat lewat 9 digit terakhir nomor (NomorBarang pada view) lalu difilter unit perangkat;
//          SMS yang tidak cocok dengan perangkat di wilayah Anda tidak ditampilkan. Hanya $batas_sms SMS terakhir yang diperiksa.
// sms_outbox / sms_inbox memakai bentuk tabel smsd standar (ID, DestinationNumber/SenderNumber, TextDecoded, CreatorID, Processed, ...);
// kolom opsional (waktu, Status, Processed) dideteksi otomatis.

$pesan_error = '';
$sumber_barang = 'vw_terpasang';
$tabel_outbox  = 'sms_outbox';
$tabel_inbox   = 'sms_inbox';
$batas_sms     = 1000;
foreach ([$sumber_barang] as $id) if (!preg_match('/^\w+$/', $id)) throw new RuntimeException('Nama view tidak valid.');
foreach ([$tabel_outbox, $tabel_inbox] as $id) if (!preg_match('/^\w+(\.\w+)?$/', $id)) throw new RuntimeException('Nama tabel SMS tidak valid.');
$q = fn(string $n) => '`' . str_replace('.', '`.`', $n) . '`';

$u         = pg_unit_akun($conn);
$unit_siap = $u['upi'] !== '';

$tab     = (string) ($_GET['tab'] ?? 'putus');
if (!in_array($tab, ['putus', 'nyala', 'keluar', 'masuk'], true)) $tab = 'putus';
$keyword = trim($_GET['keyword'] ?? '');
$status  = (string) ($_GET['status'] ?? '');
$limit   = (int) ($_GET['limit'] ?? 25);
if (!in_array($limit, [10, 25, 50, 100], true)) $limit = 25;
$hal     = max(1, (int) ($_GET['hal'] ?? 1));

$status_sms = ['Reserved' => ['ANTREAN', 'warning'], 'SendingOK' => ['TERKIRIM', 'success'], 'SendingError' => ['GAGAL', 'danger']];
$ada_status = $ada_processed = false;
$data = []; $total_data = 0; $kartu = [];

if (!$unit_siap) {
    $pesan_error = 'Akun Anda belum memiliki Unit UPI, sehingga data tidak dapat ditampilkan.';
} else {
    try {
        if ($tab === 'putus' || $tab === 'nyala') {
            // ------------------------- TIKET -------------------------
            if (!in_array($status, ['AKTIF', 'TIDAK', ''], true)) $status = '';
            [$tb, $pk] = $tab === 'putus' ? ['trx_order_putus', 'TiketPutus'] : ['trx_order_nyala', 'TiketNyala'];
            $p = []; $scope = pg_scope($u, 't', 'Unit', $p);

            $st = $conn->prepare("SELECT COUNT(*), SUM(t.StatusData = 'AKTIF'), SUM(t.StatusData = 'TIDAK') FROM `$tb` t WHERE $scope");
            $st->execute($p);
            [$n_all, $n_aktif, $n_tidak] = array_map('intval', $st->fetch(PDO::FETCH_NUM));
            $kartu = [['Total Tiket', $n_all, 'text-dark', ''], ['Aktif', $n_aktif, 'text-success', 'AKTIF'], ['Nonaktif', $n_tidak, 'text-secondary', 'TIDAK']];

            $w = [$scope];
            if ($status !== '') { $w[] = 't.StatusData = :st'; $p[':st'] = $status; }
            if ($keyword !== '') {
                $w[] = "(t.$pk LIKE :k1 OR t.IdPel LIKE :k2 OR t.NomorBarang LIKE :k3 OR l.NamaPelanggan LIKE :k4)";
                foreach (['k1', 'k2', 'k3', 'k4'] as $k) $p[":$k"] = '%' . $keyword . '%';
            }
            $join_n = $tab === 'putus' ? " LEFT JOIN trx_order_nyala n ON n.TiketPutus = t.TiketPutus" : '';
            $from = "FROM `$tb` t LEFT JOIN dil l ON l.IdPel = t.IdPel$join_n WHERE " . implode(' AND ', $w);

            $st = $conn->prepare("SELECT COUNT(DISTINCT t.$pk) $from");
            $st->execute($p);
            $total_data = (int) $st->fetchColumn();
            $hal = min($hal, max(1, (int) ceil($total_data / $limit)));

            $extra = $tab === 'putus' ? ", MIN(n.TiketNyala) AS Rujukan" : ", t.TiketPutus AS Rujukan";
            $st = $conn->prepare("SELECT t.$pk AS Kode, t.JenisTiket, t.IdPel, NULLIF(l.NamaPelanggan,'') AS NamaPelanggan, t.ThBlRek, t.NomorBarang, t.UnitUp, t.NamaAkun, t.StatusData, t.WaktuData $extra
                                  $from GROUP BY t.$pk, t.JenisTiket, t.IdPel, l.NamaPelanggan, t.ThBlRek, t.NomorBarang, t.UnitUp, t.NamaAkun, t.StatusData, t.WaktuData" . ($tab === 'nyala' ? ", t.TiketPutus" : '') . "
                                  ORDER BY t.WaktuData DESC, t.$pk DESC LIMIT :lim OFFSET :off");
        } else {
            // ------------------------- SMS (smsd) -------------------------
            $keluar = $tab === 'keluar';
            $tabel  = $keluar ? $tabel_outbox : $tabel_inbox;
            $kol    = array_column($conn->query("SHOW COLUMNS FROM " . $q($tabel))->fetchAll(PDO::FETCH_ASSOC), 'Field');
            $wajib  = $keluar ? ['DestinationNumber', 'TextDecoded', 'CreatorID'] : ['SenderNumber', 'TextDecoded'];
            foreach ($wajib as $k) if (!in_array($k, $kol, true)) throw new RuntimeException("Tabel $tabel tidak punya kolom $k (bentuk smsd standar).");

            if ($keluar) {
                $waktu = in_array('InsertIntoDB', $kol, true) ? 'InsertIntoDB' : (in_array('SendingDateTime', $kol, true) ? 'SendingDateTime' : null);
                $ada_status = in_array('Status', $kol, true);
                $urut  = in_array('ID', $kol, true) ? 'ID' : ($waktu ?? 'DestinationNumber');
                $sub   = "SELECT " . (in_array('ID', $kol, true) ? 'ID' : 'NULL AS ID') . ", DestinationNumber AS Nomor, TextDecoded, CreatorID, "
                       . ($waktu ? "$waktu AS Waktu" : "NULL AS Waktu") . ", " . ($ada_status ? "Status" : "NULL AS Status") . " FROM " . $q($tabel) . " ORDER BY $urut DESC LIMIT " . (int) $batas_sms;
            } else {
                $waktu = in_array('ReceivingDateTime', $kol, true) ? 'ReceivingDateTime' : null;
                $ada_processed = in_array('Processed', $kol, true);
                $urut  = $waktu ?? (in_array('ID', $kol, true) ? 'ID' : 'SenderNumber');
                $sub   = "SELECT " . (in_array('ID', $kol, true) ? 'ID' : 'NULL AS ID') . ", SenderNumber AS Nomor, TextDecoded, NULL AS CreatorID, "
                       . ($waktu ? "$waktu AS Waktu" : "NULL AS Waktu") . ", " . ($ada_processed ? "Processed AS Status" : "NULL AS Status") . " FROM " . $q($tabel) . " ORDER BY $urut DESC LIMIT " . (int) $batas_sms;
            }

            $p = []; $scope = pg_scope($u, 'b', 'Unit', $p);
            $dasar = "FROM ($sub) o
                      INNER JOIN `$sumber_barang` b ON RIGHT(REPLACE(o.Nomor, '+', ''), 9) = RIGHT(REPLACE(b.NomorBarang, '+', ''), 9)
                      LEFT JOIN dil l ON l.IdPel = b.IdPel";
            $gk = "o.ID, o.Nomor, o.TextDecoded, o.CreatorID, o.Waktu, o.Status";   // kunci kelompok per SMS (ID bisa NULL -> pakai semua kolom)

            // ringkasan per status dalam cakupan
            $st = $conn->prepare("SELECT x.Status, COUNT(*) FROM (SELECT $gk $dasar WHERE $scope GROUP BY $gk) x GROUP BY x.Status");
            $st->execute($p);
            $per = []; $n_all = 0;
            foreach ($st->fetchAll(PDO::FETCH_NUM) as [$s, $n]) { $per[(string) $s] = (int) $n; $n_all += (int) $n; }
            $label_total = 'SMS (' . pg_n($batas_sms) . ' terakhir, di wilayah Anda)';
            if ($keluar) {
                $kartu = [[$label_total, $n_all, 'text-dark', '']];
                if ($ada_status) foreach ($status_sms as $k => [$lb, $wr]) $kartu[] = [$lb, $per[$k] ?? 0, 'text-' . $wr, $k];
            } else {
                $kartu = [[$label_total, $n_all, 'text-dark', '']];
                if ($ada_processed) { $kartu[] = ['Sudah diproses', $per['true'] ?? 0, 'text-success', 'true']; $kartu[] = ['Belum diproses', $per['false'] ?? 0, 'text-warning', 'false']; }
            }

            $w = [$scope];
            if ($status !== '' && ($ada_status || $ada_processed)) { $w[] = 'o.Status = :st'; $p[':st'] = $status; } else $status = '';
            if ($keyword !== '') {
                $w[] = '(o.Nomor LIKE :k1 OR o.TextDecoded LIKE :k2 OR o.CreatorID LIKE :k3 OR b.IdPel LIKE :k4 OR l.NamaPelanggan LIKE :k5)';
                foreach (['k1', 'k2', 'k3', 'k4', 'k5'] as $k) $p[":$k"] = '%' . $keyword . '%';
            }
            $where = 'WHERE ' . implode(' AND ', $w);

            $st = $conn->prepare("SELECT COUNT(*) FROM (SELECT $gk $dasar $where GROUP BY $gk) x");
            $st->execute($p);
            $total_data = (int) $st->fetchColumn();
            $hal = min($hal, max(1, (int) ceil($total_data / $limit)));

            $st = $conn->prepare("SELECT o.ID, o.Nomor, o.TextDecoded, o.CreatorID, o.Waktu, o.Status,
                                         IF(o.CreatorID LIKE 'digita:%' AND LENGTH(o.CreatorID) - LENGTH(REPLACE(o.CreatorID, ':', '')) >= 3, SUBSTRING_INDEX(o.CreatorID, ':', -1), NULL) AS Tiket,
                                         MIN(b.IdPel) AS IdPel, MIN(b.NoRef) AS NoRef, MIN(NULLIF(l.NamaPelanggan,'')) AS NamaPelanggan
                                  $dasar $where GROUP BY $gk ORDER BY o.Waktu DESC, o.ID DESC LIMIT :lim OFFSET :off");
        }
        foreach ($p as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->bindValue(':off', ($hal - 1) * $limit, PDO::PARAM_INT);
        $st->execute();
        $data = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $pesan_error = 'Gagal memuat data: ' . pg_e($e->getMessage())
                     . ' (periksa hak SELECT akun database pada tabel terkait, dan kesamaan collation bila error "Illegal mix of collations").';
    }
}

$boleh_input = pga_boleh('input_perintah');

include __DIR__ . '/../../templates/penggunaan/monitor_perintah.php';