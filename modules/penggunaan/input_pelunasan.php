<?php
// modules/penggunaan/input_pelunasan.php  -  Input Pelunasan (tabel `pelunasan_ap2t`)
// Kolom: IdPel CHAR(12) PK | ThBlRek CHAR(7) PK | TglBayar DATE | RpBK INT | RpTag BIGINT | UnitUp | UnitAp | UnitUpi | WaktuData
// Halaman langsung menampilkan SALDO (dpp) yang BELUM lunas pada wilayah akun, siap diproses:
//   - per baris: isi Tanggal Bayar & RpBK lalu klik "Lunasi", atau
//   - banyak sekaligus: centang baris lalu "Lunasi Terpilih".
// RpTag diambil dari dpp. Unit: UPI dari session; AP / UP dari session bila ada, bila tidak dari baris dpp-nya.
// Data besar -> daftar selalu dibatasi satu tahun (awalan ThBlRek), wilayah mengikuti kode unit session (pg_scope()).
// Pelunasan yang sudah tercatat TIDAK ditimpa (INSERT IGNORE).

$pesan_sukses = '';
$pesan_error  = '';

if (empty($_SESSION['pg_csrf'])) $_SESSION['pg_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['pg_csrf'];

$u         = pg_unit_akun($conn);
$unit_siap = $u['upi'] !== '';

// ---- filter daftar ----
$keyword = trim($_GET['keyword'] ?? '');
$bulan_f = preg_match('/^(0[1-9]|1[0-2])$/', $_GET['bulan'] ?? '') ? $_GET['bulan'] : '';
$limit   = (int) ($_GET['limit'] ?? 25);
if (!in_array($limit, [10, 25, 50, 100], true)) $limit = 25;
$hal     = max(1, (int) ($_GET['hal'] ?? 1));

if (!empty($_SESSION['pg_flash'])) {
    $fl = $_SESSION['pg_flash']; unset($_SESSION['pg_flash']);
    $pesan_sukses = $fl['sukses'] ?? ''; $pesan_error = $fl['error'] ?? '';
}

// ---------------------------------------------------------
// PROSES PELUNASAN (POST)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targets = isset($_POST['satu']) && $_POST['satu'] !== '' ? [(string) $_POST['satu']] : array_map('strval', (array) ($_POST['pilih'] ?? []));
    $tgl_in  = (array) ($_POST['tgl'] ?? []);
    $bk_in   = (array) ($_POST['rpbk'] ?? []);

    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $pesan_error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (!$unit_siap) {
        $pesan_error = 'Akun Anda belum memiliki Unit UPI, sehingga pelunasan tidak dapat disimpan.';
    } elseif (!$targets) {
        $pesan_error = 'Pilih minimal satu tagihan untuk dilunasi.';
    } elseif (count($targets) > 200) {
        $pesan_error = 'Maksimal 200 tagihan per proses.';
    } else {
        $ok = $sudah = 0; $gagal = [];
        try {
            $st_dpp = null;
            $conn->beginTransaction();
            $ins = $conn->prepare("INSERT IGNORE INTO pelunasan_ap2t (IdPel, ThBlRek, TglBayar, RpBK, RpTag, UnitUp, UnitAp, UnitUpi, WaktuData)
                                   VALUES (?,?,?,?,?,?,?,?,NOW())");
            foreach (array_unique($targets) as $key) {
                if (!preg_match('/^(\d{12})_([0-9A-Za-z]{1,8})$/', $key, $m)) { $gagal[] = 'Data tidak valid'; continue; }
                [$idpel, $bulan] = [$m[1], $m[2]];
                $tgl = trim((string) ($tgl_in[$key] ?? ''));
                $bk  = trim((string) ($bk_in[$key] ?? '0'));
                $lbl = $idpel . ' / ' . $bulan;

                if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tgl, $d) || !checkdate((int) $d[2], (int) $d[3], (int) $d[1])) { $gagal[] = "$lbl: tanggal bayar tidak valid"; continue; }
                if ($tgl > date('Y-m-d'))                                       { $gagal[] = "$lbl: tanggal bayar melewati hari ini"; continue; }
                if ($bk === '' || !ctype_digit($bk) || (float) $bk > 4294967295) { $gagal[] = "$lbl: RpBK harus angka bulat tidak negatif"; continue; }

                // tagihan harus ada di dpp pada wilayah akun
                $p = [':id' => $idpel, ':bl' => $bulan];
                $scope = pg_scope($u, 'd', 'Unit', $p);
                $st = $conn->prepare("SELECT d.ThBlRek, d.RpTag, d.UnitUp, d.UnitAp FROM dpp d WHERE d.IdPel = :id AND d.ThBlRek = :bl AND $scope LIMIT 1");
                $st->execute($p);
                $tg = $st->fetch(PDO::FETCH_ASSOC);
                if (!$tg)                                       { $gagal[] = "$lbl: tagihan tidak ditemukan di wilayah Anda"; continue; }
                if (strlen(trim((string) $tg['ThBlRek'])) > 7)  { $gagal[] = "$lbl: ThBlRek lebih dari 7 karakter"; continue; }

                $ins->execute([$idpel, $tg['ThBlRek'], $tgl, (int) $bk, (int) $tg['RpTag'],
                               $u['up'] !== '' ? $u['up'] : $tg['UnitUp'], $u['ap'] !== '' ? $u['ap'] : $tg['UnitAp'], $u['upi']]);
                if ($ins->rowCount() > 0) $ok++; else $sudah++;
            }
            $conn->commit();

            if ($ok > 0) $pesan_sukses = '<strong>' . pg_n($ok) . '</strong> tagihan berhasil dicatat lunas.';
            if ($sudah > 0) $pesan_sukses .= ($pesan_sukses ? ' ' : '') . pg_n($sudah) . ' dilewati karena pelunasannya sudah tercatat.';
            if ($gagal) $pesan_error = pg_n(count($gagal)) . ' tagihan tidak diproses: ' . pg_e(implode('; ', array_slice($gagal, 0, 5))) . (count($gagal) > 5 ? '; …' : '') . '.';
        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $pesan_error = 'Gagal menyimpan, tidak ada data yang tersimpan: ' . pg_e($e->getMessage());
        }
    }

    if (!headers_sent()) {
        $_SESSION['pg_flash'] = ['sukses' => $pesan_sukses, 'error' => $pesan_error];
        header('Location: ' . pg_url('input_pelunasan', [
            'tahun' => $_POST['f_tahun'] ?? '', 'bulan' => $_POST['f_bulan'] ?? '', 'keyword' => $_POST['f_keyword'] ?? '',
            'limit' => ($_POST['f_limit'] ?? '') == 25 ? '' : ($_POST['f_limit'] ?? ''), 'hal' => ($_POST['f_hal'] ?? 1) > 1 ? $_POST['f_hal'] : '',
        ]));
        exit;
    }
}

// ---------------------------------------------------------
// DAFTAR SALDO BELUM LUNAS
// ---------------------------------------------------------
$daftar_tahun = []; $tahun = '';
$data = []; $total_data = 0; $ringkas = ['jumlah' => 0, 'rp' => 0];
if ($unit_siap) {
    try {
        // tahun yang punya tagihan di wilayah akun (cache session 5 menit per wilayah)
        $ck = 'pg_tahun_dpp_' . md5($u['upi'] . '|' . $u['ap'] . '|' . $u['up']);
        if (!empty($_SESSION[$ck]) && $_SESSION[$ck]['t'] > time() - 300) {
            $daftar_tahun = $_SESSION[$ck]['v'];
        } else {
            $p0 = []; $sc0 = pg_scope($u, 'd', 'Unit', $p0);
            $st = $conn->prepare("SELECT DISTINCT LEFT(d.ThBlRek, 4) FROM dpp d WHERE $sc0");
            $st->execute($p0);
            $daftar_tahun = array_values(array_filter(array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN)), fn($y) => preg_match('/^\d{4}$/', $y)));
            rsort($daftar_tahun);
            $_SESSION[$ck] = ['t' => time(), 'v' => $daftar_tahun];
        }
        $tahun = (string) ($_GET['tahun'] ?? '');
        if (!in_array($tahun, $daftar_tahun, true)) $tahun = $daftar_tahun[0] ?? '';

        if ($tahun !== '') {
            $p = [':per' => $tahun . $bulan_f . '%'];
            $scope = pg_scope($u, 'd', 'Unit', $p);
            $w = ['d.ThBlRek LIKE :per', 'p.IdPel IS NULL', $scope];
            if ($keyword !== '') { $w[] = '(d.IdPel LIKE :k1 OR l.NamaPelanggan LIKE :k2)'; $p[':k1'] = $p[':k2'] = '%' . $keyword . '%'; }
            $from = "FROM dpp d
                     LEFT JOIN pelunasan_ap2t p ON p.IdPel = d.IdPel AND p.ThBlRek = d.ThBlRek
                     LEFT JOIN dil l ON l.IdPel = d.IdPel
                     WHERE " . implode(' AND ', $w);

            $st = $conn->prepare("SELECT COUNT(*), COALESCE(SUM(d.RpTag),0) $from");
            $st->execute($p);
            [$total_data, $rp] = array_map('intval', $st->fetch(PDO::FETCH_NUM));
            $ringkas = ['jumlah' => $total_data, 'rp' => $rp];
            $hal = min($hal, max(1, (int) ceil($total_data / $limit)));

            $st = $conn->prepare("SELECT d.IdPel, NULLIF(l.NamaPelanggan,'') AS NamaPelanggan, d.ThBlRek, d.RpTag $from
                                  ORDER BY d.ThBlRek DESC, d.IdPel LIMIT :lim OFFSET :off");
            foreach ($p as $k => $v) $st->bindValue($k, $v);
            $st->bindValue(':lim', $limit, PDO::PARAM_INT);
            $st->bindValue(':off', ($hal - 1) * $limit, PDO::PARAM_INT);
            $st->execute();
            $data = $st->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $pesan_error = trim($pesan_error . ' Gagal memuat saldo: ' . pg_e($e->getMessage()));
    }
}

$boleh_monitor = pga_boleh('monitor_pelunasan');

include __DIR__ . '/../../templates/penggunaan/input_pelunasan.php';