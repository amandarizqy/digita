<?php
// modules/penggunaan/input_saldo.php  -  Input Saldo (tabel `dpp`)
// Kolom: IdPel CHAR(12) PK | ThBlRek CHAR(8) PK | RpTag BIGINT | UnitUp CHAR(5) | UnitAp CHAR(5) | UnitUpi CHAR(2) | WaktuData TIMESTAMP
// UnitUpi / UnitAp / UnitUp diambil dari session akun yang login (atau dari unit tujuan yang dipilih di langkah 1).
// Akun tingkat UPI/AP tidak punya AP/UP di session -> unit yang kurang dipilih dari master (dibatasi ke bawah unit akun).
// Urutan kerja halaman: (1) pilih unit tujuan -> (2) cari & pilih IdPel dari aset barang (VIEW $sumber_barang, terhubung ke dil) -> (3) isi periode & saldo.
// IdPel manual HANYA lewat pilihan dari view aset barang pada unit terpilih dan WAJIB ada di dil; tidak pernah menambah data dil.
// Fitur: input manual dan import Excel (.xlsx). Daftar & ringkasan saldo ada di Monitoring > Saldo (monitor_saldo.php).
require_once __DIR__ . '/../../functions/xlsx_reader.php';

$pesan_sukses  = '';
$pesan_error   = '';
$detail_import = [];
$chunk         = 500;

// Sumber aset barang untuk pencarian IdPel = VIEW (bukan tabel master_barang). Ganti nama view sesuai di database.
// View ini membawa kolom: NoRef, SimIdBarang, ImeiModem, StatusRelay, IdPel, UnitUp, UnitAp, UnitUpi, SimIdModem, NomorBarang, NomorModem.
$sumber_barang = 'vw_terpasang';   // default: vw_terpasang (unit UPi/UPi/UP diambil dari session akun yang login)
if (!preg_match('/^\w+$/', $sumber_barang)) throw new RuntimeException('Nama view aset barang tidak valid.');

if (empty($_SESSION['pg_csrf'])) $_SESSION['pg_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['pg_csrf'];

// ---- normalisasi nilai ----
if (!function_exists('sd_idpel')) {
    /** IdPel dinormalkan jadi string angka (angka ilmiah/".0" dari Excel dibereskan). TIDAK ada pelengkap nol: wajib persis 12 digit. */
    function sd_idpel($v): ?string
    {
        $v = trim((string) $v);
        if (preg_match('/^\d+(\.0+)?$/', $v))                    return preg_replace('/\.0+$/', '', $v);
        if (preg_match('/^\d+(\.\d+)?[eE]\+?\d+$/', $v))       return sprintf('%.0f', (float) $v);   // angka ilmiah dari Excel
        return null;
    }
    /** Alasan penolakan IdPel (null bila valid: persis 12 digit). */
    function sd_idpel_alasan(?string $v): ?string
    {
        if ($v === null)        return 'IdPel harus berupa angka';
        if (strlen($v) < 12)    return 'IdPel kurang dari 12 karakter, tidak disimpan';
        if (strlen($v) > 12)    return 'IdPel lebih dari 12 karakter, tidak disimpan';
        return null;
    }
    /** Periode dari <input type="month"> (YYYY-MM) -> YYYYMM. */
    function sd_bulan_kalender($v): ?string
    {
        return preg_match('/^((?:19|20)\d{2})-(0[1-9]|1[0-2])$/', trim((string) $v), $m) ? $m[1] . $m[2] : null;
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

// ---- LANGKAH 1: unit tujuan = session + pilihan (GET/POST). AP & UP harus lengkap sebelum langkah berikutnya. ----
$sel_ap = $sAp !== '' ? $sAp : trim((string) ($_POST['unit_ap'] ?? $_GET['unit_ap'] ?? ''));
$sel_up = $sUp !== '' ? $sUp : trim((string) ($_POST['unit_up'] ?? $_GET['unit_up'] ?? ''));
if ($perlu_pilih) {
    if (!isset($opsi_ap[$sel_ap]))                  { $sel_ap = ''; $sel_up = ''; }
    elseif (!isset($opsi_up[$sel_ap][$sel_up]))     { $sel_up = ''; }
}
$unit_lengkap = $unit_siap && $sel_ap !== '' && $sel_up !== '';
$unit_tujuan  = fn() => $unit_lengkap ? [$sUpi, $sel_ap, $sel_up] : null;     // [Upi, Ap, Up]
$ut           = ['upi' => $sUpi, 'ap' => $sel_ap, 'up' => $sel_up];           // untuk pg_scope() pada view aset barang

if (!empty($_SESSION['pg_flash'])) {
    $fl = $_SESSION['pg_flash']; unset($_SESSION['pg_flash']);
    $pesan_sukses = $fl['sukses'] ?? ''; $pesan_error = $fl['error'] ?? ''; $detail_import = $fl['detail'] ?? [];
}

// ---------------------------------------------------------
// PROSES FORM (POST)
// ---------------------------------------------------------
$sql_simpan = fn(int $n) => "INSERT INTO dpp (IdPel, ThBlRek, RpTag, UnitUp, UnitAp, UnitUpi, WaktuData) VALUES "
    . implode(',', array_fill(0, $n, '(?,?,?,?,?,?,NOW())'))
    . " ON DUPLICATE KEY UPDATE RpTag = VALUES(RpTag), UnitUp = VALUES(UnitUp), UnitAp = VALUES(UnitAp), UnitUpi = VALUES(UnitUpi), WaktuData = NOW()";

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
        $bulan = sd_bulan_kalender($_POST['thblrek'] ?? '');
        $rp    = sd_rp($_POST['rptag'] ?? '');
        if ($e_id = sd_idpel_alasan($idpel))  $pesan_error = $e_id . '.';
        elseif ($bulan === null)              $pesan_error = 'Pilih periode (ThBlRek) dari kalender.';
        elseif ($rp === null)                 $pesan_error = 'Saldo (RpTag) harus angka, tidak boleh negatif.';
        else {
            try {
                // IdPel WAJIB sudah terdaftar di DIL; halaman ini tidak pernah menambah data ke DIL
                $st = $conn->prepare("SELECT NamaPelanggan FROM dil WHERE IdPel = ?");
                $st->execute([$idpel]);
                $dil = $st->fetch(PDO::FETCH_ASSOC);
                $punya_barang = false;
                if ($dil) {                                          // IdPel juga harus punya aset barang di unit terpilih
                    $pb = []; $sc_b = pg_scope($ut, 'b', 'Unit', $pb); $pb[':idp'] = $idpel;
                    $st = $conn->prepare("SELECT 1 FROM `$sumber_barang` b WHERE b.IdPel = :idp AND $sc_b LIMIT 1");
                    $st->execute($pb);
                    $punya_barang = (bool) $st->fetchColumn();
                }
                if (!$dil) {
                    $pesan_error = 'IdPel <strong>' . pg_e($idpel) . '</strong> tidak terdaftar di DIL, saldo tidak disimpan. Tambahkan dulu lewat Input DIL.';
                } elseif (!$punya_barang) {
                    $pesan_error = 'IdPel <strong>' . pg_e($idpel) . '</strong> tidak memiliki aset barang pada unit yang dipilih. Pilih IdPel lewat pencarian.';
                } else {
                    $conn->prepare($sql_simpan(1))->execute([$idpel, $bulan, $rp, $tuj[2], $tuj[1], $tuj[0]]);
                    $nm = trim((string) $dil['NamaPelanggan']);
                    $pesan_sukses = 'Saldo IdPel <strong>' . pg_e($idpel) . '</strong>' . ($nm !== '' ? ' (' . pg_e($nm) . ')' : '')
                                  . ' periode <strong>' . pg_e($bulan) . '</strong> sebesar <strong>Rp ' . pg_n($rp) . '</strong> berhasil disimpan.';
                }
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
            $peta = null; $baris = []; $dibaca = $salah = $dobel = $tidak_dil = 0;
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
                    $alasan = sd_idpel_alasan($id) ?? ($bl === null ? 'ThBlRek harus YYYYMM/YYYYMMDD' : ($rp === null ? 'RpTag kosong/bukan angka/negatif' : null));
                    if ($alasan !== null) {
                        $salah++;
                        if (count($detail_import) < 20) $detail_import[] = "Baris $no: $alasan (" . trim((string) ($sel[$peta['idpel']] ?? '')) . ')';
                        continue;
                    }
                    if (isset($baris[$id . '|' . $bl])) $dobel++;           // baris terakhir yang dipakai
                    $baris[$id . '|' . $bl] = [$id, $bl, $rp, $no];
                }
                if ($peta === null) throw new RuntimeException('Baris judul kolom (IdPel, ThBlRek, RpTag) tidak ditemukan.');

                // IdPel WAJIB sudah terdaftar di DIL: yang tidak ada dilewati (tidak pernah ditambahkan ke DIL)
                if ($baris) {
                    $ada = [];
                    foreach (array_chunk(array_values(array_unique(array_column($baris, 0))), 1000) as $paket) {
                        $st = $conn->prepare("SELECT IdPel FROM dil WHERE IdPel IN (" . implode(',', array_fill(0, count($paket), '?')) . ")");
                        $st->execute($paket);
                        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $x) $ada[(string) $x] = true;
                    }
                    foreach ($baris as $k => $b) {
                        if (isset($ada[$b[0]])) continue;
                        $tidak_dil++;
                        if (count($detail_import) < 20) $detail_import[] = 'Baris ' . $b[3] . ': IdPel ' . $b[0] . ' tidak terdaftar di DIL, tidak disimpan';
                        unset($baris[$k]);
                    }
                }
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
                        . ($salah ? ', ' . pg_n($salah) . ' baris tidak valid (termasuk IdPel kurang dari 12 karakter)' : '')
                        . ($tidak_dil ? ', ' . pg_n($tidak_dil) . ' baris dilewati karena IdPel tidak terdaftar di DIL' : '') . '.';
                }
            } catch (Throwable $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                $pesan_error = 'Import gagal, tidak ada data yang tersimpan: ' . pg_e($e->getMessage());
            }
        }
    }

    if (!headers_sent()) {
        $_SESSION['pg_flash'] = ['sukses' => $pesan_sukses, 'error' => $pesan_error, 'detail' => $detail_import];
        header('Location: ' . pg_url('input_saldo', ['unit_ap' => $sAp === '' ? $sel_ap : '', 'unit_up' => $sUp === '' ? $sel_up : '']));
        exit;
    }
}

$nama_sel_ap = $sAp !== '' ? $nama_ap : ($opsi_ap[$sel_ap] ?? '');
$nama_sel_up = $sUp !== '' ? $nama_up : ($opsi_up[$sel_ap][$sel_up] ?? '');
$label_wilayah = $nama_upi . ($nama_sel_ap !== '' ? ' › ' . $nama_sel_ap : '') . ($nama_sel_up !== '' ? ' › ' . $nama_sel_up : '');

// ---------------------------------------------------------
// LANGKAH 2: cari & pilih IdPel dari aset barang (view) di unit terpilih, terhubung ke dil (INNER JOIN).
// ---------------------------------------------------------
$keyword = trim($_GET['keyword'] ?? '');
$limit   = 5;      // 5 baris per halaman agar pemilihan IdPel ringkas
$hal     = max(1, (int) ($_GET['hal'] ?? 1));
$cari = []; $total_cari = 0; $idpel_pilih = null; $info_pilih = null;
if ($unit_lengkap) {
    try {
        $p = []; $scope = pg_scope($ut, 'b', 'Unit', $p);
        $w = ['b.IdPel IS NOT NULL', $scope];
        if ($keyword !== '') {
            $w[] = '(b.IdPel LIKE :k1 OR b.NoRef LIKE :k2 OR b.SimIdBarang LIKE :k3 OR b.ImeiModem LIKE :k4 OR b.NomorBarang LIKE :k6 OR l.NamaPelanggan LIKE :k5)';
            foreach (['k1', 'k2', 'k3', 'k4', 'k5', 'k6'] as $k) $p[":$k"] = '%' . $keyword . '%';
        }
        $from = "FROM `$sumber_barang` b INNER JOIN dil l ON l.IdPel = b.IdPel WHERE " . implode(' AND ', $w);

        $st = $conn->prepare("SELECT COUNT(DISTINCT b.IdPel) $from");
        $st->execute($p);
        $total_cari = (int) $st->fetchColumn();
        $hal = min($hal, max(1, (int) ceil($total_cari / $limit)));

        $st = $conn->prepare("SELECT b.IdPel, NULLIF(l.NamaPelanggan,'') AS NamaPelanggan, COUNT(*) AS jml, MIN(b.NoRef) AS NoRef, MIN(b.SimIdBarang) AS SimIdBarang, MIN(b.ImeiModem) AS ImeiModem, MIN(b.NomorBarang) AS NomorBarang
                              $from GROUP BY b.IdPel, l.NamaPelanggan ORDER BY b.IdPel LIMIT :lim OFFSET :off");
        foreach ($p as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->bindValue(':off', ($hal - 1) * $limit, PDO::PARAM_INT);
        $st->execute();
        $cari = $st->fetchAll(PDO::FETCH_ASSOC);

        // IdPel yang dipilih (?idpel=) harus valid pada unit ini: ada di view aset barang DAN di dil
        $pil = (string) ($_GET['idpel'] ?? '');
        if (preg_match('/^\d{12}$/', $pil)) {
            $pp = []; $sc2 = pg_scope($ut, 'b', 'Unit', $pp); $pp[':pil'] = $pil;
            $st = $conn->prepare("SELECT NULLIF(l.NamaPelanggan,'') AS nama, COUNT(*) AS jml FROM `$sumber_barang` b INNER JOIN dil l ON l.IdPel = b.IdPel WHERE b.IdPel = :pil AND $sc2 GROUP BY l.NamaPelanggan");
            $st->execute($pp);
            if ($r = $st->fetch(PDO::FETCH_ASSOC)) { $idpel_pilih = $pil; $info_pilih = $r; }
        }
    } catch (PDOException $e) {
        $pesan_error = trim($pesan_error . ' Gagal memuat aset barang: ' . pg_e($e->getMessage()));
    }
}

// tombol ke monitoring hanya bila akun berhak membukanya
$boleh_monitor = pga_boleh('monitor_saldo');

include __DIR__ . '/../../templates/penggunaan/input_saldo.php';