<?php
// modules/penggunaan/input_perintah.php  -  Input Perintah
// Sumber perangkat : tabel `master_barang` (NoRef, SimId, ImeiModem, IdPel, UnitUp, UnitAp, UnitUpi, StatusData, WaktuData)
//                    -> hanya perangkat di wilayah akun (kode unit session, pg_scope()).
// Sumber perintah  : tabel `baku_outbox` (Id, IsiPesan, Keterangan, JenisPerintah, DatabaseTujuan, StatusData) -> hanya AKTIF & KIRIM.
// Pengiriman       : pesan diantrekan ke tabel `outbox` pada database `DatabaseTujuan` (mis. `smsd` = antrean SMS gateway
//                    bergaya Gammu: DestinationNumber, TextDecoded, CreatorID). Nomor tujuan = SimId perangkat.
//                    Ubah HANYA fungsi pr_antrekan_sms() di bawah bila gateway Anda memakai struktur lain.
// Perintah berparameter (IsiPesan berakhiran ":+62", mis. SETADMIN:+62 / DELADMIN:+62) meminta nomor admin dan ditambahkan ke teks.

$pesan_sukses = '';
$pesan_error  = '';
$maks_kirim   = 50;      // batas perangkat per sekali kirim

if (empty($_SESSION['pg_csrf'])) $_SESSION['pg_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['pg_csrf'];

$u         = pg_unit_akun($conn);
$unit_siap = $u['upi'] !== '';

/** Nomor telepon -> format +62xxxxxxxxxx; null bila tidak valid. */
if (!function_exists('pr_nomor')) {
    function pr_nomor($v): ?string
    {
        $v = preg_replace('/[\s\-().]/', '', trim((string) $v));
        if ($v === '') return null;
        if ($v[0] === '+')               $n = substr($v, 1);
        elseif (strpos($v, '62') === 0)  $n = $v;
        elseif ($v[0] === '0')           $n = '62' . substr($v, 1);
        else                             $n = '62' . $v;
        return preg_match('/^62[1-9]\d{7,12}$/', $n) ? '+' . $n : null;
    }
    /** Perintah butuh nomor admin bila IsiPesan berakhiran ":+62". */
    function pr_butuh_param(string $isi): bool { return (bool) preg_match('/:\+62$/', trim($isi)); }

    /** Antrekan satu SMS ke outbox gateway. $db sudah divalidasi (/^\w+$/). Sesuaikan di sini bila struktur gateway berbeda. */
    function pr_antrekan_sms(PDO $conn, string $db, string $tujuan, string $teks, string $pembuat): void
    {
        $st = $conn->prepare("INSERT INTO `$db`.`outbox` (DestinationNumber, TextDecoded, CreatorID, Coding) VALUES (?, ?, ?, 'Default_No_Compression')");
        $st->execute([$tujuan, $teks, $pembuat]);
    }
}

// ---- filter daftar perangkat ----
$keyword = trim($_GET['keyword'] ?? '');
$status  = in_array($_GET['status'] ?? '', ['AKTIF', 'TIDAK'], true) ? $_GET['status'] : '';
$limit   = (int) ($_GET['limit'] ?? 25);
if (!in_array($limit, [10, 25, 50, 100], true)) $limit = 25;
$hal     = max(1, (int) ($_GET['hal'] ?? 1));

if (!empty($_SESSION['pg_flash'])) {
    $fl = $_SESSION['pg_flash']; unset($_SESSION['pg_flash']);
    $pesan_sukses = $fl['sukses'] ?? ''; $pesan_error = $fl['error'] ?? '';
}

// ---------------------------------------------------------
// KIRIM PERINTAH (POST)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cmd_id  = (int) ($_POST['cmd'] ?? 0);
    $targets = array_values(array_unique(array_map('strval', (array) ($_POST['pilih'] ?? []))));

    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $pesan_error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (!$unit_siap) {
        $pesan_error = 'Akun Anda belum memiliki Unit UPI, sehingga perintah tidak dapat dikirim.';
    } elseif ($cmd_id <= 0) {
        $pesan_error = 'Pilih perintah yang akan dikirim.';
    } elseif (!$targets) {
        $pesan_error = 'Pilih minimal satu perangkat.';
    } elseif (count($targets) > $maks_kirim) {
        $pesan_error = "Maksimal $maks_kirim perangkat per pengiriman.";
    } else {
        try {
            // perintah harus AKTIF dan berjenis KIRIM (dibaca ulang dari database, bukan dari form)
            $st = $conn->prepare("SELECT Id, IsiPesan, Keterangan, DatabaseTujuan FROM baku_outbox WHERE Id = ? AND StatusData = 'AKTIF' AND JenisPerintah = 'KIRIM'");
            $st->execute([$cmd_id]);
            $cmd = $st->fetch(PDO::FETCH_ASSOC);

            $teks = null;
            if (!$cmd) {
                $pesan_error = 'Perintah tidak ditemukan atau tidak aktif.';
            } elseif (!preg_match('/^\w+$/', (string) $cmd['DatabaseTujuan'])) {
                $pesan_error = 'Database tujuan perintah tidak valid.';
            } else {
                $teks = trim((string) $cmd['IsiPesan']);
                if (pr_butuh_param($teks)) {
                    $adm = preg_replace('/\D/', '', (string) ($_POST['param'] ?? ''));
                    $adm = preg_replace('/^(62|0)/', '', $adm);
                    if (!preg_match('/^[1-9]\d{7,12}$/', $adm)) { $pesan_error = 'Nomor admin tidak valid (contoh: 81234567890).'; $teks = null; }
                    else $teks .= $adm;
                }
                if ($teks !== null && mb_strlen($teks) > 160) { $pesan_error = 'Teks perintah melebihi 160 karakter.'; $teks = null; }
            }

            if ($teks !== null) {
                // perangkat harus berada di wilayah akun
                $p = []; $ph = [];
                foreach ($targets as $i => $no) { $ph[] = ":n$i"; $p[":n$i"] = $no; }
                $scope = pg_scope($u, 'b', 'Unit', $p);
                $st = $conn->prepare("SELECT b.NoRef, b.SimId, b.StatusData FROM master_barang b WHERE b.NoRef IN (" . implode(',', $ph) . ") AND $scope");
                $st->execute($p);
                $perangkat = $st->fetchAll(PDO::FETCH_ASSOC);

                $ok = 0; $lewat = [];
                $ditemukan = array_column($perangkat, 'NoRef');
                $hilang = array_diff($targets, array_map('strval', $ditemukan));
                if ($hilang) $lewat[] = pg_n(count($hilang)) . ' perangkat di luar wilayah Anda';

                $pembuat = mb_substr('digita:' . ($_SESSION['NamaAkun'] ?? ''), 0, 50);
                $conn->beginTransaction();
                foreach ($perangkat as $b) {
                    $no = (string) $b['NoRef'];
                    if ($b['StatusData'] === 'TIDAK')  { $lewat[] = "$no nonaktif"; continue; }
                    $hp = pr_nomor($b['SimId'] ?? '');
                    if ($hp === null)                  { $lewat[] = "$no tanpa nomor SIM valid"; continue; }
                    pr_antrekan_sms($conn, $cmd['DatabaseTujuan'], $hp, $teks, $pembuat);
                    $ok++;
                }
                $conn->commit();

                if ($ok > 0) $pesan_sukses = 'Perintah <strong>' . pg_e($teks) . '</strong> (' . pg_e($cmd['Keterangan']) . ') diantrekan ke <strong>' . pg_n($ok) . '</strong> perangkat.';
                if ($lewat)  $pesan_error  = 'Dilewati: ' . pg_e(implode('; ', array_slice($lewat, 0, 5))) . (count($lewat) > 5 ? '; …' : '') . '.';
                if ($ok === 0 && !$pesan_error) $pesan_error = 'Tidak ada perintah yang diantrekan.';
            }
        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $pesan_error = 'Gagal mengantrekan perintah, tidak ada yang terkirim: ' . pg_e($e->getMessage());
        }
    }

    if (!headers_sent()) {
        $_SESSION['pg_flash'] = ['sukses' => $pesan_sukses, 'error' => $pesan_error];
        header('Location: ' . pg_url('input_perintah', [
            'keyword' => $_POST['f_keyword'] ?? '', 'status' => $_POST['f_status'] ?? '',
            'limit' => ($_POST['f_limit'] ?? '') == 25 ? '' : ($_POST['f_limit'] ?? ''), 'hal' => ($_POST['f_hal'] ?? 1) > 1 ? $_POST['f_hal'] : '',
        ]));
        exit;
    }
}

// ---------------------------------------------------------
// DATA: perintah & perangkat
// ---------------------------------------------------------
$perintah = []; $data = []; $total_data = 0; $ringkas = ['total' => 0, 'siap' => 0];
if ($unit_siap) {
    try {
        $perintah = $conn->query("SELECT Id, IsiPesan, Keterangan, DatabaseTujuan FROM baku_outbox WHERE StatusData = 'AKTIF' AND JenisPerintah = 'KIRIM' ORDER BY Id")->fetchAll(PDO::FETCH_ASSOC);

        $p = []; $scope = pg_scope($u, 'b', 'Unit', $p);
        $st = $conn->prepare("SELECT COUNT(*), SUM(b.SimId IS NOT NULL AND b.SimId <> '' AND (b.StatusData IS NULL OR b.StatusData <> 'TIDAK')) FROM master_barang b WHERE $scope");
        $st->execute($p);
        [$ringkas['total'], $ringkas['siap']] = array_map('intval', $st->fetch(PDO::FETCH_NUM));

        $w = [$scope];
        if ($keyword !== '') { $w[] = '(b.NoRef LIKE :k1 OR b.IdPel LIKE :k2 OR b.SimId LIKE :k3 OR b.ImeiModem LIKE :k4 OR l.NamaPelanggan LIKE :k5)'; foreach (['k1','k2','k3','k4','k5'] as $k) $p[":$k"] = '%' . $keyword . '%'; }
        if ($status !== '')  { $w[] = 'b.StatusData = :st'; $p[':st'] = $status; }
        $from = "FROM master_barang b LEFT JOIN dil l ON l.IdPel = b.IdPel WHERE " . implode(' AND ', $w);

        $st = $conn->prepare("SELECT COUNT(*) $from");
        $st->execute($p);
        $total_data = (int) $st->fetchColumn();
        $hal = min($hal, max(1, (int) ceil($total_data / $limit)));

        $st = $conn->prepare("SELECT b.NoRef, b.SimId, b.ImeiModem, b.IdPel, NULLIF(l.NamaPelanggan,'') AS NamaPelanggan, b.UnitUp, b.StatusData $from ORDER BY b.NoRef LIMIT :lim OFFSET :off");
        foreach ($p as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->bindValue(':off', ($hal - 1) * $limit, PDO::PARAM_INT);
        $st->execute();
        $data = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $pesan_error = trim($pesan_error . ' Gagal memuat data: ' . pg_e($e->getMessage()));
    }
}

$boleh_monitor = pga_boleh('monitor_perintah');

include __DIR__ . '/../../templates/penggunaan/input_perintah.php';