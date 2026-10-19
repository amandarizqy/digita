<?php
// modules/penggunaan/input_saldo.php  -  Input Saldo (tabel `dpp`)
// Kolom: IdPel CHAR(12) PK | ThBlRek CHAR(8) PK | RpTag BIGINT | KodeUp CHAR(5) | KodeAp CHAR(5) | KodeUpi CHAR(2) | WaktuData TIMESTAMP
// KodeUpi / KodeAp / KodeUp diambil dari session akun yang login (UnitUpi / UnitAp / UnitUp).
// Akun tingkat UPI/AP tidak punya AP/UP di session -> unit yang kurang dipilih dari master (dibatasi ke bawah unit akun).
// Fitur: input manual dan import Excel (.xlsx). Daftar & ringkasan saldo ada di Monitoring > Saldo (monitor_saldo.php).
require_once __DIR__ . '/../../functions/xlsx_reader.php';

$pesan_sukses  = '';
$pesan_error   = '';
$detail_import = [];
$chunk         = 500;

if (empty($_SESSION['pg_csrf'])) $_SESSION['pg_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['pg_csrf'];

// ---- normalisasi nilai ----
if (!function_exists('sd_idpel')) {
    function sd_idpel($v): ?string
    {
        $v = trim((string) $v);
        if (preg_match('/^\d+(\.0+)?$/', $v))                    $v = preg_replace('/\.0+$/', '', $v);
        elseif (preg_match('/^\d+(\.\d+)?[eE]\+?\d+$/', $v))     $v = sprintf('%.0f', (float) $v);   // angka ilmiah dari Excel
        else return null;
        if (strlen($v) < 12) $v = str_pad($v, 12, '0', STR_PAD_LEFT);                               // nol di depan hilang oleh Excel
        return preg_match('/^\d{12}$/', $v) ? $v : null;
    }
    /** ThBlRek: YYYYMM atau YYYYMMDD. */
    function sd_bulan($v): ?string
    {
        $v = preg_replace('/\.0+$/', '', trim((string) $v));
        return preg_match('/^(19|20)\d{2}(0[1-9]|1[0-2])((0[1-9]|[12]\d|3[01]))?$/', $v) ? $v : null;
    }
    function sd_rp($v): ?int
    {
        $v = trim((string) $v);
        if ($v === '' || !is_numeric($v) || (float) $v < 0) return null;
        return (int) round((float) $v);
    }
    function sd_header($v): string { return preg_replace('/[^a-z0-9]/', '', strtolower((string) $v)); }
}

// ---- 1. unit akun (session; cadangan master_pengguna) ----
$sUpi = (string) ($_SESSION['UnitUpi'] ?? '');
$sAp  = (string) ($_SESSION['UnitAp']  ?? '');
$sUp  = (string) ($_SESSION['UnitUp']  ?? '');
if ($sUpi === '' && !empty($_SESSION['NamaAkun'])) {
    try {
        $st = $conn->prepare("SELECT UnitUp, UnitAp, UnitUpi FROM master_pengguna WHERE NamaAkun = ?");
        $st->execute([$_SESSION['NamaAkun']]);
        if ($u = $st->fetch(PDO::FETCH_ASSOC)) { $sUpi = (string) $u['UnitUpi']; $sAp = (string) $u['UnitAp']; $sUp = (string) $u['UnitUp']; }
    } catch (PDOException $e) { /* ditangani di bawah */ }
}
$unit_siap = $sUpi !== '';
$perlu_pilih = $unit_siap && ($sAp === '' || $sUp === '');   // ada unit yang harus dipilih

$nama_upi = $sUpi; $nama_ap = $sAp; $nama_up = $sUp;
$opsi_ap = []; $opsi_up = []; $peta_unit = [];
if ($unit_siap) {
    try {
        $one = function (string $sql, array $p, string $def) use ($conn) { $st = $conn->prepare($sql); $st->execute($p); return $st->fetchColumn() ?: $def; };
        $nama_upi = $one("SELECT COALESCE(NULLIF(SingkatanNama,''), UnitUpi) FROM master_upi WHERE UnitUpi = ?", [$sUpi], $sUpi);
        if ($sAp !== '') $nama_ap = $one("SELECT COALESCE(NULLIF(SingkatanNama,''), UnitAp) FROM master_ap WHERE UnitAp = ?", [$sAp], $sAp);
        if ($sUp !== '') $nama_up = $one("SELECT COALESCE(NULLIF(SingkatanNama,''), UnitUp) FROM master_up WHERE UnitUp = ?", [$sUp], $sUp);

        if ($perlu_pilih) {
            $sql = "SELECT UnitAp, COALESCE(NULLIF(SingkatanNama,''), UnitAp) AS Nama FROM master_ap WHERE UnitUpi = :upi AND StatusData = 'AKTIF'";
            $prm = [':upi' => $sUpi];
            if ($sAp !== '') { $sql .= " AND UnitAp = :ap"; $prm[':ap'] = $sAp; }
            $st = $conn->prepare($sql . " ORDER BY UnitAp"); $st->execute($prm);
            $opsi_ap = $st->fetchAll(PDO::FETCH_KEY_PAIR);
            if ($opsi_ap) {
                $kode = array_map('strval', array_keys($opsi_ap));
                $sql = "SELECT UnitAp, UnitUp, COALESCE(NULLIF(SingkatanNama,''), UnitUp) AS Nama FROM master_up WHERE UnitAp IN (" . implode(',', array_fill(0, count($kode), '?')) . ") AND StatusData = 'AKTIF'";
                $prm = $kode;
                if ($sUp !== '') { $sql .= " AND UnitUp = ?"; $prm[] = $sUp; }
                $st = $conn->prepare($sql . " ORDER BY UnitUp"); $st->execute($prm);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $opsi_up[(string) $r['UnitAp']][(string) $r['UnitUp']] = $r['Nama'];
            }
            foreach ($opsi_ap as $k => $n) {
                $ups = [];
                foreach ($opsi_up[(string) $k] ?? [] as $ku => $nu) $ups[] = ['kode' => (string) $ku, 'nama' => $nu];
                $peta_unit[] = ['kode' => (string) $k, 'nama' => $n, 'ups' => $ups];
            }
        }
    } catch (PDOException $e) { $pesan_error = 'Gagal memuat daftar unit: ' . pg_e($e->getMessage()); $unit_siap = false; }
}

/** Tentukan [Upi, Ap, Up] tujuan dari session + pilihan form; null jika belum lengkap/valid. */
$unit_tujuan = function () use ($sUpi, $sAp, $sUp, $perlu_pilih, $opsi_ap, $opsi_up) {
    $ap = $sAp !== '' ? $sAp : trim((string) ($_POST['unit_ap'] ?? ''));
    $up = $sUp !== '' ? $sUp : trim((string) ($_POST['unit_up'] ?? ''));
    if ($perlu_pilih && (!isset($opsi_ap[$ap]) || !isset($opsi_up[$ap][$up]))) return null;
    return ($ap !== '' && $up !== '') ? [$sUpi, $ap, $up] : null;
};

if (!empty($_SESSION['pg_flash'])) {
    $fl = $_SESSION['pg_flash']; unset($_SESSION['pg_flash']);
    $pesan_sukses = $fl['sukses'] ?? ''; $pesan_error = $fl['error'] ?? ''; $detail_import = $fl['detail'] ?? [];
}

// ---------------------------------------------------------
// PROSES FORM (POST)
// ---------------------------------------------------------
$sql_simpan = fn(int $n) => "INSERT INTO dpp (IdPel, ThBlRek, RpTag, KodeUp, KodeAp, KodeUpi, WaktuData) VALUES "
    . implode(',', array_fill(0, $n, '(?,?,?,?,?,?,NOW())'))
    . " ON DUPLICATE KEY UPDATE RpTag = VALUES(RpTag), KodeUp = VALUES(KodeUp), KodeAp = VALUES(KodeAp), KodeUpi = VALUES(KodeUpi), WaktuData = NOW()";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $tuj  = $unit_siap ? $unit_tujuan() : null;

    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $pesan_error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (!$unit_siap) {
        $pesan_error = $pesan_error ?: 'Akun Anda belum memiliki Unit UPI, sehingga saldo tidak dapat disimpan.';
    } elseif ($tuj === null) {
        $pesan_error = 'Pilih Unit AP dan Unit UP tujuan terlebih dahulu.';

    } elseif ($aksi === 'tambah') {
        $idpel = sd_idpel($_POST['idpel'] ?? '');
        $bulan = sd_bulan($_POST['thblrek'] ?? '');
        $rp    = sd_rp($_POST['rptag'] ?? '');
        if ($idpel === null)      $pesan_error = 'IdPel harus 12 digit angka.';
        elseif ($bulan === null)  $pesan_error = 'ThBlRek harus format YYYYMM atau YYYYMMDD.';
        elseif ($rp === null)     $pesan_error = 'Saldo (RpTag) harus angka, tidak boleh negatif.';
        else {
            try {
                $conn->prepare($sql_simpan(1))->execute([$idpel, $bulan, $rp, $tuj[2], $tuj[1], $tuj[0]]);
                $pesan_sukses = 'Saldo IdPel <strong>' . pg_e($idpel) . '</strong> periode <strong>' . pg_e($bulan) . '</strong> sebesar <strong>Rp ' . pg_n($rp) . '</strong> berhasil disimpan.';
            } catch (PDOException $e) { $pesan_error = 'Gagal menyimpan: ' . pg_e($e->getMessage()); }
        }

    } elseif ($aksi === 'import') {
        $f = $_FILES['file_xlsx'] ?? null;
        if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
            $pesan_error = 'Pilih file Excel (.xlsx) terlebih dahulu.';
        } elseif ($f['error'] !== UPLOAD_ERR_OK) {
            $pesan_error = 'Upload gagal (kode ' . (int) $f['error'] . '). Periksa batas ukuran upload server.';
        } elseif (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'xlsx' || file_get_contents($f['tmp_name'], false, null, 0, 2) !== 'PK') {
            $pesan_error = 'Format file harus .xlsx (Excel).';
        } elseif ($f['size'] > 10 * 1024 * 1024) {
            $pesan_error = 'Ukuran file maksimal 10 MB.';
        } else {
            @set_time_limit(300);
            $alias = ['idpel' => ['idpel'], 'bulan' => ['thblrek', 'bulan'], 'rp' => ['rptag', 'rptagihan', 'saldo']];
            $nama_kol = ['idpel' => 'IdPel', 'bulan' => 'ThBlRek', 'rp' => 'RpTag'];
            $peta = null; $baris = []; $dibaca = $salah = $dobel = 0;
            try {
                foreach (xlsx_baris($f['tmp_name']) as $no => $sel) {
                    if ($peta === null) {                                   // cari baris judul (20 baris pertama)
                        if ($no > 20) throw new RuntimeException('Baris judul kolom tidak ditemukan pada 20 baris pertama.');
                        $kol = [];
                        foreach ($sel as $h => $n) $kol[sd_header($n)] = $h;
                        if (!isset($kol['idpel'])) continue;
                        $peta = [];
                        foreach ($alias as $fld => $al) foreach ($al as $a) if (isset($kol[$a])) { $peta[$fld] = $kol[$a]; break; }
                        $kurang = array_diff(array_keys($alias), array_keys($peta));
                        if ($kurang) throw new RuntimeException('Kolom wajib tidak ditemukan: ' . implode(', ', array_map(fn($k) => $nama_kol[$k], $kurang)) . '.');
                        continue;
                    }
                    if (trim(implode('', $sel)) === '') continue;
                    $dibaca++;
                    $id = sd_idpel($sel[$peta['idpel']] ?? ''); $bl = sd_bulan($sel[$peta['bulan']] ?? ''); $rp = sd_rp($sel[$peta['rp']] ?? '');
                    $alasan = $id === null ? 'IdPel harus 12 digit angka' : ($bl === null ? 'ThBlRek harus YYYYMM/YYYYMMDD' : ($rp === null ? 'RpTag kosong/bukan angka/negatif' : null));
                    if ($alasan !== null) {
                        $salah++;
                        if (count($detail_import) < 20) $detail_import[] = "Baris $no: $alasan (" . trim((string) ($sel[$peta['idpel']] ?? '')) . ')';
                        continue;
                    }
                    if (isset($baris[$id . '|' . $bl])) $dobel++;           // baris terakhir yang dipakai
                    $baris[$id . '|' . $bl] = [$id, $bl, $rp];
                }
                if ($peta === null) throw new RuntimeException('Baris judul kolom (IdPel, ThBlRek, RpTag) tidak ditemukan.');
                if (!$baris) {
                    $pesan_error = 'Tidak ada baris valid yang bisa disimpan.';
                } else {
                    $conn->beginTransaction();
                    foreach (array_chunk(array_values($baris), $chunk) as $paket) {
                        $prm = [];
                        foreach ($paket as $b) array_push($prm, $b[0], $b[1], $b[2], $tuj[2], $tuj[1], $tuj[0]);
                        $conn->prepare($sql_simpan(count($paket)))->execute($prm);
                    }
                    $conn->commit();
                    $pesan_sukses = 'Import selesai: <strong>' . pg_n(count($baris)) . '</strong> baris saldo disimpan'
                        . ($dobel ? ', ' . pg_n($dobel) . ' baris dobel dalam file (dipakai yang terakhir)' : '')
                        . ($salah ? ', ' . pg_n($salah) . ' baris tidak valid' : '') . '.';
                }
            } catch (Throwable $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                $pesan_error = 'Import gagal, tidak ada data yang tersimpan: ' . pg_e($e->getMessage());
            }
        }
    }

    if (!headers_sent()) {
        $_SESSION['pg_flash'] = ['sukses' => $pesan_sukses, 'error' => $pesan_error, 'detail' => $detail_import];
        header('Location: ' . pg_url('input_saldo'));
        exit;
    }
}

$label_wilayah = $nama_upi . ($sAp !== '' ? ' › ' . $nama_ap : '') . ($sUp !== '' ? ' › ' . $nama_up : '');

// tombol ke monitoring hanya bila akun berhak membukanya
$boleh_monitor = pga_boleh('monitor_saldo');

include __DIR__ . '/../../templates/penggunaan/input_saldo.php';