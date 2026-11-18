<?php
// modules/penggunaan/input_pelunasan.php  -  Input Pelunasan (tabel `pelunasan_ap2t`)
// Kolom: IdPel CHAR(12) PK | ThBlRek CHAR(7) PK | TglBayar DATE | RpBK INT | RpTag BIGINT | UnitUp | UnitAp | UnitUpi | NamaAkun VARCHAR(20) | WaktuData
//
// Halaman menampilkan SALDO (dpp) yang BELUM lunas pada wilayah akun. Ada 3 cara mencatat pelunasan:
//   1. Per baris / "Lunasi Terpilih" pada daftar saldo (aksi = lunasi)
//   2. Input manual lewat pop card (modal)             (aksi = manual)
//   3. Upload Excel (.xlsx) daftar transaksi            (aksi = upload)
//
// Aturan yang sama untuk ketiganya (fungsi pl_simpan) - INSERT TIDAK LAGI MEMBACA TABEL dpp:
//   - RpTag datang dari input: kolom RpTagihan (Excel) / isian RpTag (manual) / nilai saldo pada baris (daftar)
//   - Unit: UPI / AP / UP dari session akun (AP / UP kosong bila akun tidak punya)
//   - pelunasan yang sudah tercatat TIDAK ditimpa (INSERT IGNORE)
//   (tabel dpp hanya dipakai untuk MENAMPILKAN daftar saldo belum lunas)
//
// Upload Excel: file dibaca streaming, hanya 5 kolom yang diambil (dicari berdasarkan NAMA kolom, urutan bebas):
//   Idpel -> IdPel | Bulan (YYYYMM) -> ThBlRek | TglTransaksi -> TglBayar | RpBK -> RpBK | RpTagihan -> RpTag
//   Kolom lain di file (Trf, Daya, Petugas, dst.) diabaikan. Baris footer cetakan dilewati otomatis.

require_once __DIR__ . '/../../functions/xlsx_reader.php';

// ---- PETA KOLOM EXCEL: field => nama kolom di Excel (huruf kecil, tanpa spasi/simbol). Ubah di sini bila perlu.
$pl_peta = [
    'idpel' => ['idpel'],
    'bulan' => ['bulan', 'thblrek'],            // -> ThBlRek
    'tgl'   => ['tgltransaksi', 'tglbayar'],    // -> TglBayar
    'rpbk'  => ['rpbk'],                        // -> RpBK
    'rptag' => ['rptagihan', 'rptag'],          // -> RpTag
];
$pl_nama      = ['idpel' => 'Idpel', 'bulan' => 'Bulan', 'tgl' => 'TglTransaksi', 'rpbk' => 'RpBK', 'rptag' => 'RpTagihan'];
$pl_chunk     = 500;      // baris per query insert
$pl_max_upload = 50000;   // batas baris valid per file

// ---- Fungsi bantu -----------------------------------------------------------------
if (!function_exists('pl_simpan')) {

function pl_header($v): string { return preg_replace('/[^a-z0-9]/', '', strtolower((string) $v)); }

function pl_idpel($v): ?string
{
    $v = trim((string) $v);
    if (preg_match('/^\d+(\.0+)?$/', $v))                  $v = preg_replace('/\.0+$/', '', $v);
    elseif (preg_match('/^\d+(\.\d+)?[eE]\+?\d+$/', $v))   $v = sprintf('%.0f', (float) $v);   // angka ilmiah dari Excel
    else return null;
    if (strlen($v) === 11) $v = '0' . $v;                  // nol di depan hilang oleh Excel
    return preg_match('/^\d{12}$/', $v) ? $v : null;
}

function pl_bulan($v): ?string
{
    $v = preg_replace('/\.0+$/', '', trim((string) $v));
    return preg_match('/^(19|20)\d{2}(0[1-9]|1[0-2])$/', $v) ? $v : null;
}

/** null = kosong, false = tidak valid, string = Y-m-d */
function pl_tanggal($v)
{
    $v = trim((string) $v);
    if ($v === '') return null;
    if (preg_match('/^(\d{4})(\d{2})(\d{2})(\.0+)?$/', $v, $m)) return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "$m[1]-$m[2]-$m[3]" : false;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m))       return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "$m[1]-$m[2]-$m[3]" : false;
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m))   return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : false;
    if (is_numeric($v) && $v > 20000 && $v < 80000)             return gmdate('Y-m-d', ((int) $v - 25569) * 86400);   // tanggal serial Excel
    return false;
}

/** kosong = 0, bukan angka = null */
function pl_angka($v): ?float
{
    $v = trim((string) $v);
    return $v === '' ? 0.0 : (is_numeric($v) ? (float) $v : null);
}

/**
 * Catat pelunasan langsung ke pelunasan_ap2t (tanpa membaca dpp).
 * $items: [['idpel','bulan','tgl'(Y-m-d),'bk'(int),'tag'(int)], ...] yang SUDAH divalidasi formatnya
 * dan unik per (idpel,bulan). Dipanggil di dalam transaksi milik pemanggil.
 * Return: ['ok' => dicatat baru, 'sudah' => sudah tercatat (dilewati)]
 */
function pl_simpan(PDO $conn, array $u, string $namaAkun, array $items, int $chunk = 500): array
{
    $hasil = ['ok' => 0, 'sudah' => 0];
    if (!$items) return $hasil;

    $baris = [];
    foreach ($items as $it) {
        $baris[] = [$it['idpel'], $it['bulan'], $it['tgl'], (int) $it['bk'], (int) $it['tag'], $namaAkun,
                    (string) ($u['up'] ?? ''), (string) ($u['ap'] ?? ''), $u['upi']];
    }

    // INSERT IGNORE: yang sudah tercatat tidak ditimpa
    $head = "INSERT IGNORE INTO pelunasan_ap2t (IdPel, ThBlRek, TglBayar, RpBK, RpTag, NamaAkun, UnitUp, UnitAp, UnitUpi, WaktuData) VALUES ";
    foreach (array_chunk($baris, $chunk) as $paket) {
        $st = $conn->prepare($head . implode(',', array_fill(0, count($paket), '(?,?,?,?,?,?,?,?,?,NOW())')));
        $st->execute(array_merge(...$paket));
        $hasil['ok'] += $st->rowCount();
    }
    $hasil['sudah'] = count($baris) - $hasil['ok'];
    return $hasil;
}
}

// ---------------------------------------------------------
$pesan_sukses = '';
$pesan_error  = '';
$upload_hasil = null;     // ringkasan upload Excel (ditampilkan setelah redirect)

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
    $upload_hasil = $fl['upload'] ?? null;
}

// ---------------------------------------------------------
// PROSES PELUNASAN (POST)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi          = (string) ($_POST['aksi'] ?? 'lunasi');
    $namaAkunInput = mb_substr((string) ($_SESSION['NamaAkun'] ?? ''), 0, 20);
    $flash_upload  = null;
    $hari_ini      = date('Y-m-d');

    if (!$_POST && !$_FILES && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $pesan_error = 'File melebihi batas server (upload_max_filesize ' . ini_get('upload_max_filesize') . ', post_max_size ' . ini_get('post_max_size') . ').';
    } elseif (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $pesan_error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (!$unit_siap) {
        $pesan_error = 'Akun Anda belum memiliki Unit UPI, sehingga pelunasan tidak dapat disimpan.';

    // ===== CARA 2: INPUT MANUAL (pop card) =====
    } elseif ($aksi === 'manual') {
        $id  = pl_idpel($_POST['m_idpel'] ?? '');
        $bl  = pl_bulan(str_replace('-', '', (string) ($_POST['m_bulan'] ?? '')));
        $tgl = pl_tanggal($_POST['m_tgl'] ?? '');
        $bk  = trim((string) ($_POST['m_rpbk'] ?? '0'));
        if ($bk === '') $bk = '0';
        $tag = trim((string) ($_POST['m_rptag'] ?? ''));

        if ($id === null)                                                 $pesan_error = 'IdPel harus 12 digit angka.';
        elseif ($bl === null)                                             $pesan_error = 'Periode harus format YYYYMM, contoh 202611.';
        elseif ($tgl === null || $tgl === false)                          $pesan_error = 'Tanggal bayar tidak valid.';
        elseif ($tgl > $hari_ini)                                         $pesan_error = 'Tanggal bayar melewati hari ini.';
        elseif (!ctype_digit($bk) || (float) $bk > 4294967295)            $pesan_error = 'RpBK harus angka bulat tidak negatif.';
        elseif ($tag === '' || !ctype_digit($tag) || (float) $tag > 9.2e18) $pesan_error = 'RpTag harus angka bulat tidak negatif.';
        else {
            try {
                $conn->beginTransaction();
                $r = pl_simpan($conn, $u, $namaAkunInput, [['idpel' => $id, 'bulan' => $bl, 'tgl' => $tgl, 'bk' => (int) $bk, 'tag' => (int) $tag]], $pl_chunk);
                $conn->commit();
                if ($r['ok'] > 0)             $pesan_sukses = "Pelunasan IdPel <strong>$id</strong> periode <strong>$bl</strong> berhasil dicatat.";
                else                          $pesan_sukses = "Pelunasan IdPel $id periode $bl sudah tercatat sebelumnya, tidak diubah.";
            } catch (PDOException $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                $pesan_error = 'Gagal menyimpan, tidak ada data yang tersimpan: ' . pg_e($e->getMessage());
            }
        }

    // ===== CARA 3: UPLOAD EXCEL =====
    } elseif ($aksi === 'upload') {
        $f = $_FILES['file_xlsx'] ?? null;
        if ($f === null || in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            $pesan_error = 'File melebihi batas server (upload_max_filesize ' . ini_get('upload_max_filesize') . ', post_max_size ' . ini_get('post_max_size') . ').';
        } elseif ($f['error'] !== UPLOAD_ERR_OK) {
            $pesan_error = 'Pilih file .xlsx terlebih dahulu.';
        } elseif (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'xlsx' || file_get_contents($f['tmp_name'], false, null, 0, 2) !== 'PK') {
            $pesan_error = 'Format file harus .xlsx (Excel).';
        } else {
            set_time_limit(300);
            $t0 = microtime(true);
            $baris = [];                 // dedup per IdPel|ThBlRek (baris terakhir dipakai)
            $dibaca = $tolak = $duplikat = 0;
            $ditolak = [];               // contoh baris ditolak (maks 50)
            $peta = null;

            try {
                foreach (xlsx_baris($f['tmp_name']) as $no => $sel) {
                    // a. cari baris judul kolom (20 baris pertama)
                    if ($peta === null) {
                        if ($no > 20) throw new RuntimeException('Baris judul kolom tidak ditemukan pada 20 baris pertama.');
                        $kol = [];
                        foreach ($sel as $huruf => $nilai) $kol[pl_header($nilai)] = $huruf;
                        if (!isset($kol['idpel'])) continue;

                        $peta = [];
                        foreach ($pl_peta as $field => $alias) {
                            foreach ($alias as $a) if (isset($kol[$a])) { $peta[$field] = $kol[$a]; break; }
                        }
                        $kurang = array_diff(array_keys($pl_peta), array_keys($peta));
                        if ($kurang) throw new RuntimeException('Kolom wajib tidak ditemukan: ' . implode(', ', array_map(fn($k) => $pl_nama[$k], $kurang)) . '.');
                        continue;
                    }

                    // b. baris data - hanya 4 kolom yang diambil
                    if (trim(implode('', $sel)) === '') continue;
                    $g = fn($field) => trim((string) ($sel[$peta[$field]] ?? ''));
                    // baris footer cetakan ("Petugas Cetak", "Page 1 of 1") tidak punya Idpel & Bulan -> dilewati
                    if ($g('idpel') === '' && $g('bulan') === '') continue;
                    $dibaca++;

                    $id  = pl_idpel($g('idpel'));
                    $bln = pl_bulan($g('bulan'));
                    $tgl = pl_tanggal($g('tgl'));
                    $bk  = pl_angka($g('rpbk'));
                    $tag = pl_angka($g('rptag'));

                    $alasan = $id === null ? 'Idpel harus 12 digit angka'
                            : ($bln === null ? 'Bulan harus format YYYYMM'
                            : ($tgl === null || $tgl === false ? 'TglTransaksi kosong / tidak valid'
                            : ($tgl > $hari_ini ? 'TglTransaksi melewati hari ini'
                            : ($bk === null || $bk < 0 || $bk > 4294967295 ? 'RpBK harus angka tidak negatif'
                            : ($tag === null || $tag < 0 || $tag > 9.2e18 ? 'RpTagihan harus angka tidak negatif' : null)))));
                    if ($alasan !== null) {
                        $tolak++;
                        if (count($ditolak) < 50) $ditolak[] = ['no' => $no, 'idpel' => $g('idpel'), 'alasan' => $alasan];
                        continue;
                    }

                    $kunci = $id . '|' . $bln;
                    if (isset($baris[$kunci])) $duplikat++;
                    $baris[$kunci] = ['idpel' => $id, 'bulan' => $bln, 'tgl' => $tgl, 'bk' => (int) round($bk), 'tag' => (int) round($tag)];
                    if (count($baris) > $pl_max_upload) throw new RuntimeException('Terlalu banyak baris (maks ' . pg_n($pl_max_upload) . ' per file). Pecah file lalu unggah bertahap.');
                }
                if ($peta === null) throw new RuntimeException('Baris judul kolom (Idpel, Bulan, TglTransaksi, RpBK, RpTagihan) tidak ditemukan.');

                // c. simpan dalam satu transaksi
                $r = ['ok' => 0, 'sudah' => 0];
                if ($baris) {
                    $conn->beginTransaction();
                    $r = pl_simpan($conn, $u, $namaAkunInput, array_values($baris), $pl_chunk);
                    $conn->commit();
                }

                $upload_hasil = [
                    'nama' => $f['name'], 'dibaca' => $dibaca, 'valid' => count($baris), 'ok' => $r['ok'], 'sudah' => $r['sudah'],
                    'duplikat' => $duplikat, 'ditolak' => $tolak,
                    'durasi' => round(microtime(true) - $t0, 2),
                    'contoh_tolak' => $ditolak,
                ];
                if ($r['ok'] > 0) {
                    $pesan_sukses = '<strong>' . pg_n($r['ok']) . '</strong> tagihan berhasil dicatat lunas dari file ' . pg_e($f['name']) . '.';
                } elseif (!$baris) {
                    $pesan_error = 'Tidak ada baris valid yang bisa diproses dari file ini.';
                } else {
                    $pesan_error = 'Tidak ada tagihan baru yang dicatat. Lihat ringkasan di bawah.';
                }
                $flash_upload = $upload_hasil;
            } catch (RuntimeException $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                $pesan_error = pg_e($e->getMessage());
            } catch (Throwable $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                $pesan_error = 'Gagal memproses file, tidak ada data yang tersimpan: ' . pg_e($e->getMessage());
            }
        }

    // ===== CARA 1: LUNASI DARI DAFTAR (per baris / terpilih) =====
    } else {
        $targets = isset($_POST['satu']) && $_POST['satu'] !== '' ? [(string) $_POST['satu']] : array_map('strval', (array) ($_POST['pilih'] ?? []));
        $tgl_in  = (array) ($_POST['tgl'] ?? []);
        $bk_in   = (array) ($_POST['rpbk'] ?? []);
        $tag_in  = (array) ($_POST['rptag'] ?? []);

        if (!$targets) {
            $pesan_error = 'Pilih minimal satu tagihan untuk dilunasi.';
        } elseif (count($targets) > 200) {
            $pesan_error = 'Maksimal 200 tagihan per proses.';
        } else {
            $gagal = []; $items = [];
            foreach (array_unique($targets) as $key) {
                if (!preg_match('/^(\d{12})_([0-9A-Za-z]{1,8})$/', $key, $m)) { $gagal[] = 'Data tidak valid'; continue; }
                [$idpel, $bulan] = [$m[1], $m[2]];
                $tgl = trim((string) ($tgl_in[$key] ?? ''));
                $bk  = trim((string) ($bk_in[$key] ?? '0'));
                $tag = trim((string) ($tag_in[$key] ?? ''));
                $lbl = $idpel . ' / ' . $bulan;

                if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tgl, $d) || !checkdate((int) $d[2], (int) $d[3], (int) $d[1])) { $gagal[] = "$lbl: tanggal bayar tidak valid"; continue; }
                if ($tgl > $hari_ini)                                           { $gagal[] = "$lbl: tanggal bayar melewati hari ini"; continue; }
                if ($bk === '' || !ctype_digit($bk) || (float) $bk > 4294967295) { $gagal[] = "$lbl: RpBK harus angka bulat tidak negatif"; continue; }
                if ($tag === '' || !ctype_digit($tag) || (float) $tag > 9.2e18)  { $gagal[] = "$lbl: RpTag tidak valid"; continue; }
                $items[] = ['idpel' => $idpel, 'bulan' => $bulan, 'tgl' => $tgl, 'bk' => (int) $bk, 'tag' => (int) $tag];
            }
            try {
                $conn->beginTransaction();
                $r = pl_simpan($conn, $u, $namaAkunInput, $items, $pl_chunk);
                $conn->commit();

                if ($r['ok'] > 0) $pesan_sukses = '<strong>' . pg_n($r['ok']) . '</strong> tagihan berhasil dicatat lunas.';
                if ($r['sudah'] > 0) $pesan_sukses .= ($pesan_sukses ? ' ' : '') . pg_n($r['sudah']) . ' dilewati karena pelunasannya sudah tercatat.';
                if ($gagal) $pesan_error = pg_n(count($gagal)) . ' tagihan tidak diproses: ' . pg_e(implode('; ', array_slice($gagal, 0, 5))) . (count($gagal) > 5 ? '; …' : '') . '.';
            } catch (PDOException $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                $pesan_error = 'Gagal menyimpan, tidak ada data yang tersimpan: ' . pg_e($e->getMessage());
            }
        }
    }

    if (!headers_sent()) {
        $_SESSION['pg_flash'] = ['sukses' => $pesan_sukses, 'error' => $pesan_error, 'upload' => $flash_upload];
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
            if ($keyword !== '') { $w[] = '(d.IdPel LIKE :k1 OR l.Nama LIKE :k2)'; $p[':k1'] = $p[':k2'] = '%' . $keyword . '%'; }
            $from = "FROM dpp d
                     LEFT JOIN pelunasan_ap2t p ON p.IdPel = d.IdPel AND p.ThBlRek = d.ThBlRek
                     LEFT JOIN dil l ON l.IdPel = d.IdPel
                     WHERE " . implode(' AND ', $w);

            $st = $conn->prepare("SELECT COUNT(*), COALESCE(SUM(d.RpTag),0) $from");
            $st->execute($p);
            [$total_data, $rp] = array_map('intval', $st->fetch(PDO::FETCH_NUM));
            $ringkas = ['jumlah' => $total_data, 'rp' => $rp];
            $hal = min($hal, max(1, (int) ceil($total_data / $limit)));

            $st = $conn->prepare("SELECT d.IdPel, NULLIF(l.Nama,'') AS NamaPelanggan, d.ThBlRek, d.RpTag $from
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

// Tanpa saldo di dpp, input manual / upload Excel tetap bisa dipakai (insert tidak bergantung pada dpp)
$ada_saldo = $tahun !== '';
if ($tahun === '') $tahun = date('Y');

$boleh_monitor = pga_boleh('monitor_pelunasan');

include __DIR__ . '/../../templates/penggunaan/input_pelunasan.php';