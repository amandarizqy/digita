<?php
// modules/penggunaan/monitor_perintah.php  -  Monitoring Perintah (hanya baca)
// Sumber  : `outbox` di database gateway (perintah dari Input Perintah, CreatorID 'digita:<akun>:<NoRef>[:<Tiket>]')
//           dan `inbox` (balasan perangkat). Status outbox: Reserved = antrean, SendingOK = terkirim, SendingError = gagal.
// Wilayah : perintah ditelusuri ke perangkat lewat NoRef di CreatorID -> view `vw_terpasang` -> unit perangkat dicocokkan
//           dengan kode unit session akun (pg_scope()). Balasan dicocokkan lewat 9 digit terakhir nomor perangkat.
// Catatan : belum terhubung ke tiket (trx_order_putus / trx_order_nyala).

$pesan_error = ''; $pesan_balasan = '';
$sumber_barang = 'vw_terpasang';
$db_gateway    = 'smsd';
foreach ([$sumber_barang, $db_gateway] as $id) if (!preg_match('/^\w+$/', $id)) throw new RuntimeException('Nama view/database tidak valid.');

$u         = pg_unit_akun($conn);
$unit_siap = $u['upi'] !== '';

$status_map = ['Reserved' => ['ANTREAN', 'warning'], 'SendingOK' => ['TERKIRIM', 'success'], 'SendingError' => ['GAGAL', 'danger']];
$daftar_perintah = ['PLNOFF', 'PLNON', 'PLNSTATUS'];

$keyword = trim($_GET['keyword'] ?? '');
$status  = isset($status_map[$_GET['status'] ?? '']) ? $_GET['status'] : '';
$cmd     = in_array($_GET['cmd'] ?? '', $daftar_perintah, true) ? $_GET['cmd'] : '';
$limit   = (int) ($_GET['limit'] ?? 25);
if (!in_array($limit, [10, 25, 50, 100], true)) $limit = 25;
$hal     = max(1, (int) ($_GET['hal'] ?? 1));

$data = []; $total_data = 0; $balasan = [];
$ringkas = ['Reserved' => 0, 'SendingOK' => 0, 'SendingError' => 0, 'total' => 0];

if (!$unit_siap) {
    $pesan_error = 'Akun Anda belum memiliki Unit UPI, sehingga data perintah tidak dapat ditampilkan.';
} else {
    try {
        // dasar: perintah dari aplikasi ini, ditelusuri ke perangkat di wilayah akun
        $dasar = "FROM `$db_gateway`.`outbox` o
                  INNER JOIN `$sumber_barang` b ON b.NoRef = SUBSTRING_INDEX(SUBSTRING_INDEX(o.CreatorID, ':', 3), ':', -1)
                  LEFT JOIN dil l ON l.IdPel = b.IdPel";
        $p = []; $scope = pg_scope($u, 'b', 'Unit', $p);
        $dasar_where = "o.CreatorID LIKE 'digita:%' AND $scope";

        // ringkasan per status
        $st = $conn->prepare("SELECT o.Status, COUNT(DISTINCT o.ID) $dasar WHERE $dasar_where GROUP BY o.Status");
        $st->execute($p);
        foreach ($st->fetchAll(PDO::FETCH_NUM) as [$s, $n]) { if (isset($ringkas[$s])) $ringkas[$s] = (int) $n; $ringkas['total'] += (int) $n; }

        // daftar
        $w = [$dasar_where]; $p2 = $p;
        if ($status !== '') { $w[] = 'o.Status = :st'; $p2[':st'] = $status; }
        if ($cmd !== '')    { $w[] = 'o.TextDecoded = :cm'; $p2[':cm'] = $cmd; }
        if ($keyword !== '') {
            $w[] = '(b.NoRef LIKE :k1 OR b.IdPel LIKE :k2 OR o.DestinationNumber LIKE :k3 OR l.NamaPelanggan LIKE :k4 OR o.CreatorID LIKE :k5)';
            foreach (['k1', 'k2', 'k3', 'k4', 'k5'] as $k) $p2[":$k"] = '%' . $keyword . '%';
        }
        $where = 'WHERE ' . implode(' AND ', $w);

        $st = $conn->prepare("SELECT COUNT(DISTINCT o.ID) $dasar $where");
        $st->execute($p2);
        $total_data = (int) $st->fetchColumn();
        $hal = min($hal, max(1, (int) ceil($total_data / $limit)));

        $st = $conn->prepare("SELECT o.ID, o.DestinationNumber, o.TextDecoded, o.CreatorID, o.Status,
                                     SUBSTRING_INDEX(SUBSTRING_INDEX(o.CreatorID, ':', 2), ':', -1) AS Pengirim,
                                     IF(LENGTH(o.CreatorID) - LENGTH(REPLACE(o.CreatorID, ':', '')) >= 3, SUBSTRING_INDEX(o.CreatorID, ':', -1), NULL) AS Tiket,
                                     MIN(b.NoRef) AS NoRef, MIN(b.IdPel) AS IdPel, MIN(NULLIF(l.NamaPelanggan,'')) AS NamaPelanggan
                              $dasar $where GROUP BY o.ID, o.DestinationNumber, o.TextDecoded, o.CreatorID, o.Status
                              ORDER BY o.ID DESC LIMIT :lim OFFSET :off");
        foreach ($p2 as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->bindValue(':off', ($hal - 1) * $limit, PDO::PARAM_INT);
        $st->execute();
        $data = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $pesan_error = 'Gagal memuat perintah: ' . pg_e($e->getMessage())
                     . ' (pastikan akun database aplikasi punya hak SELECT ke ' . pg_e($db_gateway) . '.outbox dan kolom/collation-nya kompatibel).';
    }

    // balasan perangkat: 500 SMS masuk terakhir yang cocok dengan perangkat di wilayah akun (gagal tidak mengganggu daftar utama)
    if (!$pesan_error) {
        try {
            $pb = []; $scope_b = pg_scope($u, 'b', 'Unit', $pb);
            $st = $conn->prepare("SELECT i.ReceivingDateTime, i.SenderNumber, i.TextDecoded, i.Processed,
                                         MIN(b.NoRef) AS NoRef, MIN(b.IdPel) AS IdPel, MIN(NULLIF(l.NamaPelanggan,'')) AS NamaPelanggan
                                  FROM (SELECT ReceivingDateTime, SenderNumber, TextDecoded, Processed FROM `$db_gateway`.`inbox` ORDER BY ReceivingDateTime DESC LIMIT 500) i
                                  INNER JOIN `$sumber_barang` b ON RIGHT(REPLACE(i.SenderNumber, '+', ''), 9) = RIGHT(REPLACE(b.NomorBarang, '+', ''), 9)
                                  LEFT JOIN dil l ON l.IdPel = b.IdPel
                                  WHERE $scope_b
                                  GROUP BY i.ReceivingDateTime, i.SenderNumber, i.TextDecoded, i.Processed
                                  ORDER BY i.ReceivingDateTime DESC LIMIT 15");
            $st->execute($pb);
            $balasan = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $pesan_balasan = 'Balasan perangkat belum dapat dimuat: ' . pg_e($e->getMessage());
        }
    }
}

$boleh_input = pga_boleh('input_perintah');

include __DIR__ . '/../../templates/penggunaan/monitor_perintah.php';