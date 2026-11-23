<?php
// modules/penggunaan/input_dil.php  -  Input DIL (tabel `dil`)
// Kolom dil: IdPel (PK) | Nama | Alamat | JenisMk | NomorTlp | NomorHp | GolTar | Daya | TegUkur | KdPembMeter | NomorMeter | MerekMeter
//            | ProdukPrabayar | Kogol | Aktifasi | JenisShuntrip | Kuadran | SkalaPrioritas | NamaAkun | UnitUp | UnitAp | UnitUpi | StatusData | WaktuData
//            (Kuadran & SkalaPrioritas diisi modul Perencanaan; NamaAkun, StatusData, WaktuData otomatis.)
// Fitur: tambah manual lewat pop-up form bertab (status otomatis AKTIF) dan import Excel "Monitoring DIL Detail" (.xlsx).
//        UPDATE DIL: memperbarui kolom NomorMeter / SkalaPrioritas / Peringkat pada IdPel yang SUDAH ADA di dil (wilayah akun saja),
//        lewat pop-up satu IdPel (dengan saran IdPel otomatis saat mengetik) atau upload Excel. Tipe & batas nilai tiap kolom dibaca dari struktur tabel dil (SHOW COLUMNS).
// Import: baris Excel dimasukkan ke tabel penampung `transit_dil`. Trigger database yang upsert ke `dil` (StatusData = AKTIF).
//         Setelah itu aplikasi menghitung baris yang masuk, lalu menghapus transit_dil berdasarkan akun + unit yang diproses.
// Daftar, pencarian, edit, dan aktif/nonaktif ada di Monitoring > DIL (monitor_dil.php).
require_once __DIR__ . '/../../functions/xlsx_reader.php';

$pesan_sukses  = '';
$pesan_error   = '';
$detail_import = [];   // rincian baris yang dilewati saat import
$form_lama     = [];   // isian form tambah yang gagal disimpan (agar pop-up terbuka lagi dengan isian terakhir)
$pesan_info    = '';   // informasi netral (mis. tidak ada perubahan)
$form_update   = [];   // isian pop-up Update DIL yang perlu dibuka kembali
$buka_modal    = '';   // 'update' bila pop-up Update DIL harus dibuka lagi

if (empty($_SESSION['pg_csrf'])) $_SESSION['pg_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['pg_csrf'];

/** IdPel numerik kurang dari 12 digit dilengkapi nol di depan (Excel sering membuang nol awal). */
if (!function_exists('pg_norm_idpel')) {
    function pg_norm_idpel($v): string
    {
        $v = trim((string) $v);
        if (preg_match('/^\d{1,12}$/', $v)) $v = str_pad($v, 12, '0', STR_PAD_LEFT);
        return $v;
    }
}

/** Kolom transit_dil = kolom dil tanpa StatusData. StatusData (AKTIF) diberikan trigger saat upsert ke dil. JenisShuntrip, Kogol & MerekMeter hanya dipakai bila Excel memuat kolomnya. */
const TRANSIT_KOLOM = ['IdPel', 'Nama', 'Alamat', 'JenisMk', 'NomorTlp', 'NomorHp', 'GolTar', 'Daya', 'TegUkur', 'KdPembMeter',
                       'NomorMeter', 'MerekMeter', 'Aktifasi', 'ProdukPrabayar', 'Kogol', 'JenisShuntrip', 'NamaAkun', 'UnitUp', 'UnitAp', 'UnitUpi', 'WaktuData'];

/** Kolom dil yang boleh diperbarui lewat menu Update DIL: kolom => label. */
const UPDATE_KOLOM = ['NomorMeter' => 'Nomor Meter', 'SkalaPrioritas' => 'Skala Prioritas', 'Peringkat' => 'Peringkat', 'JenisShuntrip' => 'Jenis Shuntrip'];

/** Judul kolom Excel (huruf besar, tanpa spasi/simbol) untuk Update DIL => kolom dil. Excel PLN masih menulis NOMORKWH. */
const UPDATE_HEADER = ['NOMORMETER' => 'NomorMeter', 'NOMORKWH' => 'NomorMeter', 'SKALAPRIORITAS' => 'SkalaPrioritas', 'SKALA' => 'SkalaPrioritas', 'PERINGKAT' => 'Peringkat', 'JENISSHUNTRIP' => 'JenisShuntrip'];

/** Nilai ENUM dil.JenisShuntrip. */
const JENIS_SHUNTRIP = ['MCCB', 'RELAY', 'ALARM', 'S41', 'AMI', 'SDU', '-'];

/** Nilai ENUM dil.Kogol. */
const KOGOL = ['0', '1', '2', '3', '4'];

/** Judul kolom Excel (huruf besar, tanpa spasi/simbol) => kolom dil. */
const DIL_HEADER = [
    'IDPEL' => 'IdPel', 'NAMA' => 'Nama', 'ALAMAT' => 'Alamat', 'JENISMK' => 'JenisMk', 'NOTLP' => 'NomorTlp', 'NOHP' => 'NomorHp',
    'TARIF' => 'GolTar', 'DAYA' => 'Daya', 'TEGUKUR' => 'TegUkur', 'KDPEMBMETER' => 'KdPembMeter', 'NOMORKWH' => 'NomorMeter', 'NOMORMETER' => 'NomorMeter',   // judul Excel tetap NOMORKWH
    'MEREKKWH' => 'MerekMeter', 'MEREKMETER' => 'MerekMeter',   // judul Excel: MEREKKWH (disimpan ke kolom MerekMeter)
    'AKTIFASI' => 'Aktifasi', 'KOGOL' => 'Kogol', 'JENISSHUNTRIP' => 'JenisShuntrip', 'PRODUKPRABAYAR' => 'ProdukPrabayar',
    'UNITUP' => 'UnitUp', 'UNITAP' => 'UnitAp', 'UNITUPI' => 'UnitUpi',
];

/** Rapikan teks sel: buang spasi ganda/tepi (Excel PLN sering berisi ' ') lalu potong sesuai panjang kolom. */
if (!function_exists('pg_dil_teks')) {
    function pg_dil_teks($v, int $maks): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $v)), 0, $maks);
    }
}

if (!function_exists('ud_meta')) {
    /**
     * Tipe kolom dil yang boleh diupdate, dibaca dari struktur tabel: kolom => [ada, tipe (teks|angka|desimal|pilihan), maks (panjang teks),
     * min/maksNilai (angka), pilihan (enum)]. SkalaPrioritas dibatasi 1-9 (skala matriks risiko).
     */
    function ud_meta(PDO $conn): array
    {
        $meta = [];
        foreach (array_keys(UPDATE_KOLOM) as $k) $meta[$k] = ['ada' => false, 'tipe' => 'teks', 'maks' => 50, 'min' => null, 'maksNilai' => null, 'pilihan' => []];
        try { $rows = $conn->query("SHOW COLUMNS FROM dil")->fetchAll(PDO::FETCH_ASSOC); } catch (PDOException $e) { return $meta; }
        $batas = ['tinyint' => [127, 255], 'smallint' => [32767, 65535], 'mediumint' => [8388607, 16777215], 'int' => [2147483647, 4294967295], 'bigint' => [PHP_INT_MAX, PHP_INT_MAX]];
        foreach ($rows as $r) {
            $k = (string) $r['Field'];
            if (!isset($meta[$k])) continue;
            $tipe = (string) $r['Type']; $t = strtolower($tipe);
            $meta[$k]['ada'] = true;
            if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)/', $t, $x)) {
                $unsigned = strpos($t, 'unsigned') !== false;
                $meta[$k]['tipe'] = 'angka';
                $meta[$k]['min'] = $unsigned ? 0 : -$batas[$x[1]][0] - 1;
                $meta[$k]['maksNilai'] = $batas[$x[1]][$unsigned ? 1 : 0];
            } elseif (preg_match('/^(?:var)?char\((\d+)\)/', $t, $x)) {
                $meta[$k]['maks'] = (int) $x[1];
            } elseif (preg_match("/^enum\((.*)\)$/i", $tipe, $x)) {
                $meta[$k]['tipe'] = 'pilihan'; $meta[$k]['pilihan'] = str_getcsv($x[1], ',', "'");
            } elseif (preg_match('/^(decimal|numeric|float|double)/', $t)) {
                $meta[$k]['tipe'] = 'desimal';
            }
        }
        if ($meta['SkalaPrioritas']['tipe'] === 'angka') { $meta['SkalaPrioritas']['min'] = 1; $meta['SkalaPrioritas']['maksNilai'] = 9; }
        return $meta;
    }

    /** Validasi & rapikan satu nilai sesuai tipe kolom. Mengembalikan [true, nilai] atau [false, alasan]. */
    function ud_nilai(string $kolom, array $m, $mentah): array
    {
        $v = trim(preg_replace('/\s+/u', ' ', (string) $mentah));
        if ($v === '') return [false, 'nilai kosong'];
        if (($m['tipe'] === 'angka' || $kolom === 'NomorMeter') && preg_match('/^-?\d+\.0+$/', $v)) $v = preg_replace('/\.0+$/', '', $v);   // "5.0" dari Excel
        elseif ($kolom === 'NomorMeter' && preg_match('/^\d+(\.\d+)?[eE]\+?\d+$/', $v)) $v = sprintf('%.0f', (float) $v);                // angka ilmiah dari Excel
        switch ($m['tipe']) {
            case 'angka':
                if (!preg_match('/^-?\d{1,18}$/', $v)) return [false, 'harus berupa angka bulat'];
                $n = (int) $v;
                if ($m['min'] !== null && $n < $m['min'])             return [false, 'minimal ' . $m['min']];
                if ($m['maksNilai'] !== null && $n > $m['maksNilai']) return [false, 'maksimal ' . $m['maksNilai']];
                return [true, (string) $n];
            case 'desimal':
                return is_numeric($v) ? [true, $v] : [false, 'harus berupa angka'];
            case 'pilihan':
                foreach ($m['pilihan'] as $o) if (strcasecmp($o, $v) === 0) return [true, $o];
                return [false, 'harus salah satu dari: ' . implode(', ', $m['pilihan'])];
            default:
                return mb_strlen($v) > $m['maks'] ? [false, 'maksimal ' . $m['maks'] . ' karakter'] : [true, $v];
        }
    }

    /** Apakah nilai di database sama dengan nilai baru (angka dibandingkan secara numerik). */
    function ud_sama(array $m, $lama, string $baru): bool
    {
        if ($lama === null) return false;
        $lama = trim((string) $lama);
        return $m['tipe'] === 'angka' || $m['tipe'] === 'desimal' ? (is_numeric($lama) && (float) $lama === (float) $baru) : $lama === $baru;
    }
}

// wilayah akun + struktur kolom untuk menu Update DIL
$u         = pg_unit_akun($conn);
$unit_siap = $u['upi'] !== '';
$ud_meta   = ud_meta($conn);

// ---------------------------------------------------------
// AJAX (GET, hanya baca): saran IdPel & detail satu IdPel untuk pop-up Update DIL.
// Diletakkan SEBELUM pembacaan flash supaya pesan flash tidak ikut terhapus oleh request AJAX.
// ---------------------------------------------------------
if (isset($_GET['ajax']) && in_array($_GET['ajax'], ['saran_idpel', 'cek_idpel'], true)) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    try {
        if (!$unit_siap) { echo json_encode(['found' => false, 'items' => []]); exit; }

        if ($_GET['ajax'] === 'saran_idpel') {
            // saran: IdPel yang DIAWALI karakter yang diketik (memakai index primary key), maksimal 10, hanya wilayah akun
            $q = preg_replace('/[^A-Za-z0-9]/', '', (string) ($_GET['q'] ?? ''));
            if (strlen($q) < 3 || strlen($q) > 12) { echo json_encode(['items' => []]); exit; }
            $p = [':q' => $q . '%'];
            $scope = pg_scope($u, 'd', 'Unit', $p);
            $st = $conn->prepare("SELECT d.IdPel, d.Nama FROM dil d WHERE d.IdPel LIKE :q AND $scope ORDER BY d.IdPel LIMIT 10");
            $st->execute($p);
            $items = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $items[] = ['idpel' => trim((string) $r['IdPel']), 'nama' => trim((string) ($r['Nama'] ?? ''))];
            echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // cek_idpel: detail satu IdPel (hanya kolom yang benar-benar ada di tabel dil)
        $idpel = pg_norm_idpel($_GET['idpel'] ?? '');
        if (!preg_match('/^[A-Za-z0-9]{12}$/', $idpel)) { echo json_encode(['found' => false]); exit; }
        $kolom_ada = array_keys(array_filter($ud_meta, fn($x) => !empty($x['ada'])));
        $sel = $kolom_ada ? ', ' . implode(', ', array_map(fn($k) => "d.`$k`", $kolom_ada)) : '';
        $p = [':id' => $idpel];
        $scope = pg_scope($u, 'd', 'Unit', $p);
        $st = $conn->prepare("SELECT d.Nama $sel FROM dil d WHERE d.IdPel = :id AND $scope LIMIT 1");
        $st->execute($p);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['found' => false]); exit; }
        $nilai = fn(string $k) => isset($row[$k]) ? trim((string) $row[$k]) : '';
        echo json_encode([
            'found'          => true,
            'nama'           => trim((string) ($row['Nama'] ?? '')) ?: '-',
            'jenisshuntrip'  => $nilai('JenisShuntrip'),
            'nomormeter'     => $nilai('NomorMeter'),
            'skalaprioritas' => $nilai('SkalaPrioritas'),
            'peringkat'      => $nilai('Peringkat'),
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['found' => false, 'items' => [], 'error' => 'Query gagal']);
    }
    exit;
}

// ---- pesan hasil proses sebelumnya (post-redirect-get) ----
if (!empty($_SESSION['pg_flash'])) {
    $fl = $_SESSION['pg_flash']; unset($_SESSION['pg_flash']);
    $pesan_sukses  = $fl['sukses'] ?? '';
    $pesan_error   = $fl['error'] ?? '';
    $detail_import = $fl['detail'] ?? [];
    $form_lama     = $fl['form_lama'] ?? [];
    $pesan_info    = $fl['info'] ?? '';
    $form_update   = $fl['form_update'] ?? [];
    $buka_modal    = $fl['buka'] ?? '';
}

// ---------------------------------------------------------
// PROSES FORM (POST)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $pesan_error = 'File melebihi batas upload server (post_max_size = ' . ini_get('post_max_size') . ', upload_max_filesize = ' . ini_get('upload_max_filesize') . '). Naikkan nilai tersebut di php.ini lalu restart web server.';
    } elseif (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $pesan_error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';

    } elseif ($aksi === 'tambah') {
        // ---- Tambah manual: semua kolom dil, status otomatis AKTIF ----
        $t = fn(string $k, int $m) => pg_dil_teks($_POST[$k] ?? '', $m);
        $idpel = pg_norm_idpel($_POST['idpel'] ?? '');
        $nama  = $t('nama', 50);
        $daya_mentah = trim((string) ($_POST['daya'] ?? ''));
        $teg   = strtoupper($t('tegukur', 2));
        $kdp   = strtoupper($t('kdpembmeter', 1));
        $shuntrip = strtoupper($t('jenisshuntrip', 10));
        $kogol = $t('kogol', 5);
        $upi_akun = mb_substr(trim((string) ($_SESSION['UnitUpi'] ?? '')), 0, 2);   // Unit UPI terkunci = UPI akun yang login
        if (!preg_match('/^[A-Za-z0-9]{12}$/', $idpel)) {
            $pesan_error = 'IdPel harus 12 karakter (huruf/angka).';
        } elseif ($nama === '' || mb_strlen(trim((string) ($_POST['nama'] ?? ''))) > 50) {
            $pesan_error = 'Nama pelanggan wajib diisi (maksimal 50 karakter).';
        } elseif ($daya_mentah !== '' && !preg_match('/^\d{1,9}$/', $daya_mentah)) {
            $pesan_error = 'Daya harus berupa angka bulat (VA).';
        } elseif ($teg !== '' && !in_array($teg, ['TR', 'TM', 'TT'], true)) {
            $pesan_error = 'Tegangan ukur harus TR, TM, atau TT.';
        } elseif ($upi_akun === '') {
            $pesan_error = 'Akun Anda belum memiliki Unit UPI, sehingga pelanggan tidak dapat ditambahkan. Hubungi administrator.';
        } elseif ($shuntrip !== '' && !in_array($shuntrip, JENIS_SHUNTRIP, true)) {
            $pesan_error = 'Jenis shuntrip harus salah satu dari: ' . implode(', ', JENIS_SHUNTRIP) . '.';
        } elseif ($kogol !== '' && !in_array($kogol, KOGOL, true)) {
            $pesan_error = 'Kogol harus salah satu dari: ' . implode(', ', KOGOL) . '.';
        } else {
            try {
                // JenisShuntrip & Kogol hanya disertakan bila diisi (kosong = nilai bawaan database)
                $kolom = ['IdPel', 'Nama', 'Alamat', 'JenisMk', 'NomorTlp', 'NomorHp', 'GolTar', 'Daya', 'TegUkur', 'KdPembMeter', 'NomorMeter', 'MerekMeter',
                          'Aktifasi', 'ProdukPrabayar', 'NamaAkun', 'UnitUp', 'UnitAp', 'UnitUpi'];
                $nilai = [
                    $idpel, $nama, $t('alamat', 100), $t('jenismk', 10), $t('nomortlp', 20), $t('nomorhp', 20),
                    strtoupper($t('goltar', 5)), $daya_mentah === '' ? 0 : (int) $daya_mentah, $teg === '' ? null : $teg, $kdp,
                    $t('nomormeter', 15), $t('merekmeter', 50), $t('aktifasi', 15), $t('produkprabayar', 50),
                    mb_substr((string) ($_SESSION['NamaAkun'] ?? ''), 0, 20),
                    $t('unitup', 5), $t('unitap', 5), $upi_akun,
                ];
                if ($shuntrip !== '') { $kolom[] = 'JenisShuntrip'; $nilai[] = $shuntrip; }
                if ($kogol !== '')    { $kolom[] = 'Kogol';         $nilai[] = $kogol; }
                $st = $conn->prepare("INSERT INTO dil (" . implode(', ', $kolom) . ", StatusData, WaktuData) VALUES ("
                                   . implode(', ', array_fill(0, count($kolom), '?')) . ", 'AKTIF', NOW())");
                $st->execute($nilai);
                $pesan_sukses = 'Pelanggan <strong>' . pg_e($idpel) . '</strong> berhasil ditambahkan (status AKTIF).';
            } catch (PDOException $e) {
                $pesan_error = $e->getCode() === '23000'
                    ? 'IdPel <strong>' . pg_e($idpel) . '</strong> sudah terdaftar di DIL.'
                    : 'Gagal menyimpan: ' . pg_e($e->getMessage());
            }
        }
        if ($pesan_error !== '') {   // simpan isian terakhir supaya pop-up terbuka lagi dengan data yang sama
            foreach (['idpel', 'nama', 'alamat', 'jenismk', 'nomortlp', 'nomorhp', 'goltar', 'daya', 'tegukur', 'kdpembmeter', 'nomormeter', 'merekmeter',
                      'aktifasi', 'produkprabayar', 'kogol', 'jenisshuntrip', 'unitupi', 'unitap', 'unitup'] as $k) {
                $form_lama[$k] = mb_substr(trim((string) ($_POST[$k] ?? '')), 0, 100);
            }
        }

    } elseif ($aksi === 'import') {
        // ---- Import Excel "Monitoring DIL Detail" ----
        // Judul kolom dicari otomatis (baris yang memuat IDPEL), jadi baris kop surat di atas dan urutan kolom bebas.
        // Alur: kosongkan sisa transit_dil milik akun+UPI ini -> isi transit_dil (trigger upsert ke dil) -> hitung -> hapus transit_dil per unit.
        $f = $_FILES['file_xlsx'] ?? null;
        if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
            $pesan_error = 'Pilih file Excel (.xlsx) terlebih dahulu.';
        } elseif ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
            $pesan_error = 'File melebihi batas upload server (upload_max_filesize = ' . ini_get('upload_max_filesize') . ', post_max_size = ' . ini_get('post_max_size') . '). Naikkan di php.ini lalu restart web server.';
        } elseif ($f['error'] !== UPLOAD_ERR_OK) {
            $pesan_error = 'Upload gagal (kode ' . (int) $f['error'] . '). Periksa folder temp dan batas ukuran upload server.';
        } elseif (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
            $pesan_error = 'Format file harus .xlsx.';
        } elseif ($f['size'] > 64 * 1024 * 1024) {
            $pesan_error = 'Ukuran file maksimal 64 MB.';
        } else {
            @set_time_limit(600);
            @ini_set('memory_limit', '512M');
            $akun     = mb_substr((string) ($_SESSION['NamaAkun'] ?? ''), 0, 20);
            $upi_akun = mb_substr(trim((string) ($_SESSION['UnitUpi'] ?? '')), 0, 2);
            $total = $dobel = $salah = 0;
            $masuk = 0;             // baris yang berhasil masuk ke transit_dil (trigger yang upsert ke dil)
            $seen  = [];
            $peta  = [];            // kolom dil => huruf kolom Excel
            $batch = [];            // baris yang menunggu dikirim ke transit_dil
            $cols  = [];            // kolom yang diisi (tanpa WaktuData): ditentukan setelah baris judul ditemukan
            $units = [];            // kombinasi UnitUpi|UnitAp|UnitUp yang dipakai import ini

            $kirim = function (array $rows) use ($conn, &$cols, &$masuk) {
                if (!$rows) return;
                $per_baris = '(' . implode(', ', array_fill(0, count($cols), '?')) . ', NOW())';   // WaktuData = NOW()
                $st = $conn->prepare('INSERT INTO transit_dil (' . implode(', ', $cols) . ', WaktuData) VALUES ' . implode(', ', array_fill(0, count($rows), $per_baris)));
                $st->execute(array_merge(...$rows));
                $masuk += $st->rowCount();      // baris yang benar-benar tersimpan, tanpa membedakan baru/sudah ada
            };

            // Kondisi WHERE: akun + kombinasi unit yang diproses. Dipakai untuk hitung dan hapus.
            $filter_unit = function (array $kombinasi) use ($akun): array {
                $sql = 'NamaAkun = ?'; $params = [$akun];
                if ($kombinasi) {
                    $sql .= ' AND (' . implode(' OR ', array_fill(0, count($kombinasi), '(UnitUpi = ? AND UnitAp = ? AND UnitUp = ?)')) . ')';
                    foreach ($kombinasi as [$i, $a, $p]) { $params[] = $i; $params[] = $a; $params[] = $p; }
                }
                return [$sql, $params];
            };

            try {
                $conn->beginTransaction();
                // Sisa import gagal sebelumnya: hanya milik akun ini dan UnitUpi akun ini
                if ($upi_akun !== '') $conn->prepare("DELETE FROM transit_dil WHERE NamaAkun = ? AND UnitUpi = ?")->execute([$akun, $upi_akun]);
                else                  $conn->prepare("DELETE FROM transit_dil WHERE NamaAkun = ?")->execute([$akun]);

                foreach (xlsx_baris($f['tmp_name']) as $no => $sel) {
                    if (!$peta) {                                           // cari baris judul
                        if ($no > 30) throw new RuntimeException('Baris judul kolom (IDPEL, NAMA, ...) tidak ditemukan di 30 baris pertama.');
                        $tmp = [];
                        foreach ($sel as $huruf => $isi) {
                            $kunci = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $isi));
                            if (isset(DIL_HEADER[$kunci]) && !isset($tmp[DIL_HEADER[$kunci]])) $tmp[DIL_HEADER[$kunci]] = $huruf;
                        }
                        if (isset($tmp['IdPel'], $tmp['Nama'])) {
                            $peta = $tmp;
                            // JenisShuntrip, Kogol & MerekMeter hanya disertakan bila Excel memuat kolomnya (selain itu memakai nilai bawaan database)
                            $cols = array_values(array_filter(TRANSIT_KOLOM, fn($k) => $k !== 'WaktuData' && (!in_array($k, ['JenisShuntrip', 'Kogol', 'MerekMeter'], true) || isset($peta[$k]))));
                        }
                        continue;
                    }
                    $g = fn(string $k, int $m) => isset($peta[$k]) ? pg_dil_teks($sel[$peta[$k]] ?? '', $m) : '';

                    $idpel_mentah = trim((string) ($sel[$peta['IdPel']] ?? ''));
                    $nama         = $g('Nama', 50);
                    if ($idpel_mentah === '' && $nama === '') continue;     // baris kosong
                    $total++;
                    $idpel = pg_norm_idpel($idpel_mentah);
                    if (!preg_match('/^[A-Za-z0-9]{12}$/', $idpel) || $nama === '') {
                        $salah++;
                        if (count($detail_import) < 20) $detail_import[] = "Baris $no: IdPel/Nama tidak valid (" . ($idpel_mentah === '' ? 'kosong' : $idpel_mentah) . ')';
                        continue;
                    }
                    if (isset($seen[$idpel])) {
                        $dobel++;
                        if (count($detail_import) < 20) $detail_import[] = "Baris $no: IdPel $idpel dobel di dalam file";
                        continue;
                    }
                    $seen[$idpel] = true;

                    $r_upi = $g('UnitUpi', 2); $r_ap = $g('UnitAp', 5); $r_up = $g('UnitUp', 5);
                    $units[$r_upi . '|' . $r_ap . '|' . $r_up] = [$r_upi, $r_ap, $r_up];   // catat unit untuk filter hapus

                    $teg = strtoupper($g('TegUkur', 2));
                    $sh  = strtoupper($g('JenisShuntrip', 10));
                    $kg  = $g('Kogol', 5);
                    if (preg_match('/^\d+\.0+$/', $kg)) $kg = preg_replace('/\.0+$/', '', $kg);   // "1.0" dari Excel -> "1"
                    $baris = [
                        'IdPel' => $idpel, 'Nama' => $nama, 'Alamat' => $g('Alamat', 100), 'JenisMk' => $g('JenisMk', 10),
                        'NomorTlp' => $g('NomorTlp', 20), 'NomorHp' => $g('NomorHp', 20), 'GolTar' => $g('GolTar', 5),
                        'Daya' => (int) round((float) str_replace(',', '.', $g('Daya', 15))),
                        'TegUkur' => in_array($teg, ['TR', 'TM', 'TT'], true) ? $teg : null,
                        'KdPembMeter' => $g('KdPembMeter', 1), 'NomorMeter' => $g('NomorMeter', 15), 'MerekMeter' => $g('MerekMeter', 50), 'Aktifasi' => $g('Aktifasi', 15),
                        'ProdukPrabayar' => $g('ProdukPrabayar', 50),
                        'Kogol' => in_array($kg, KOGOL, true) ? $kg : null,
                        'JenisShuntrip' => in_array($sh, JENIS_SHUNTRIP, true) ? $sh : null,
                        'NamaAkun' => $akun, 'UnitUp' => $r_up, 'UnitAp' => $r_ap, 'UnitUpi' => $r_upi,
                    ];
                    $batch[] = array_map(fn($c) => $baris[$c], $cols);      // urutan nilai selalu sama dengan $cols
                    if (count($batch) >= 500) { $kirim($batch); $batch = []; }
                }
                if (!$peta) throw new RuntimeException('Baris judul kolom (IDPEL, NAMA, ...) tidak ditemukan. Pastikan file adalah "Monitoring DIL Detail".');
                $kirim($batch);

                // Trigger di database sudah upsert ke dil pada tiap insert. Hapus HANYA milik akun + unit yang diproses.
                [$where, $prm] = $filter_unit(array_values($units));
                $conn->prepare("DELETE FROM transit_dil WHERE $where")->execute($prm);
                $conn->commit();

                if ($total === 0) {
                    $pesan_error = 'File tidak berisi data pelanggan di bawah baris judul.';
                } elseif ($masuk === 0) {
                    $pesan_error = 'Tidak ada baris yang berhasil masuk'
                                 . ($salah ? ': ' . pg_n($salah) . ' baris tidak valid' : '')
                                 . ($dobel ? ($salah ? ', ' : ': ') . pg_n($dobel) . ' dobel di dalam file' : '') . '.';
                } else {
                    $pesan_sukses = 'Import selesai: <strong>' . pg_n($masuk) . '</strong> pelanggan berhasil masuk ke DIL'
                                  . ($dobel ? ', ' . pg_n($dobel) . ' dobel di dalam file (tidak masuk)' : '')
                                  . ($salah ? ', ' . pg_n($salah) . ' baris tidak valid (tidak masuk)' : '') . '.';
                }
            } catch (Throwable $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                $pesan_error = 'Import gagal, tidak ada data yang tersimpan: ' . pg_e($e->getMessage());
            }
        }

    } elseif ($aksi === 'update_satu') {
        // ---- UPDATE DIL: satu IdPel, satu atau beberapa kolom sekaligus (kolom yang dikosongkan = tidak diubah) ----
        $idpel  = pg_norm_idpel($_POST['idpel'] ?? '');
        $lanjut = !empty($_POST['lanjut']);
        $isian  = [   // kolom dil (daftar putih UPDATE_KOLOM) => isian mentah dari form
            'JenisShuntrip'  => (string) ($_POST['jenisshuntrip'] ?? ''),
            'NomorMeter'     => (string) ($_POST['nomormeter'] ?? ''),
            'SkalaPrioritas' => (string) ($_POST['skalaprioritas'] ?? ''),
            'Peringkat'      => (string) ($_POST['peringkat'] ?? ''),
        ];
        $baru = []; $galat = [];
        foreach ($isian as $kol => $mentah) {
            if (trim($mentah) === '') continue;
            if (!$ud_meta[$kol]['ada']) { $galat[] = 'Kolom ' . UPDATE_KOLOM[$kol] . ' belum ada di tabel dil'; continue; }
            [$ok_n, $nilai] = ud_nilai($kol, $ud_meta[$kol], $mentah);
            if ($ok_n) $baru[$kol] = $nilai; else $galat[] = UPDATE_KOLOM[$kol] . ' tidak valid: ' . $nilai;
        }
        if (!$unit_siap) {
            $pesan_error = 'Akun Anda belum memiliki Unit UPI, sehingga data tidak dapat diperbarui. Hubungi administrator.';
        } elseif (!preg_match('/^[A-Za-z0-9]{12}$/', $idpel)) {
            $pesan_error = 'IdPel harus 12 karakter (huruf/angka).';
        } elseif ($galat) {
            $pesan_error = implode('; ', array_map('pg_e', $galat)) . '.';
        } elseif (!$baru) {
            $pesan_error = 'Isi minimal satu kolom yang akan diperbarui.';
        } else {
            try {
                $kols = array_keys($baru);                                             // nama kolom berasal dari daftar putih
                $sel  = implode(', ', array_map(fn($k) => "d.`$k`", $kols));
                $p = [':id' => $idpel]; $scope = pg_scope($u, 'd', 'Unit', $p);
                $st = $conn->prepare("SELECT d.Nama, $sel FROM dil d WHERE d.IdPel = :id AND $scope LIMIT 1");
                $st->execute($p);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    $pesan_error = 'IdPel <strong>' . pg_e($idpel) . '</strong> tidak ditemukan di DIL pada wilayah Anda.';
                } else {
                    $beda = [];
                    foreach ($baru as $kol => $nilai) if (!ud_sama($ud_meta[$kol], $row[$kol] ?? null, $nilai)) $beda[$kol] = $nilai;
                    if (!$beda) {
                        $pesan_info = 'IdPel <strong>' . pg_e($idpel) . '</strong> (' . pg_e($row['Nama']) . ') sudah memiliki nilai yang sama, tidak ada perubahan.';
                    } else {
                        $set = []; $pu = [':id' => $idpel]; $rinci = [];
                        foreach (array_keys($beda) as $i => $kol) {
                            $set[] = "d.`$kol` = :v$i"; $pu[":v$i"] = $beda[$kol];
                            $lama  = trim((string) ($row[$kol] ?? ''));
                            $rinci[] = pg_e(UPDATE_KOLOM[$kol]) . ' ' . ($lama === '' ? '<em>kosong</em>' : pg_e($lama)) . ' &rarr; <strong>' . pg_e($beda[$kol]) . '</strong>';
                        }
                        $scope_u = pg_scope($u, 'd', 'Unit', $pu);
                        $conn->prepare("UPDATE dil d SET " . implode(', ', $set) . ", d.WaktuData = NOW() WHERE d.IdPel = :id AND $scope_u")->execute($pu);
                        $pesan_sukses = 'IdPel <strong>' . pg_e($idpel) . '</strong> (' . pg_e($row['Nama']) . '): ' . implode('; ', $rinci) . '.';
                    }
                }
            } catch (PDOException $e) {
                $pesan_error = 'Gagal memperbarui: ' . pg_e($e->getMessage());
            }
        }
        if ($pesan_error !== '') {   // buka lagi pop-up dengan isian terakhir
            $form_update = ['idpel' => $idpel, 'jenisshuntrip' => mb_substr($isian['JenisShuntrip'], 0, 20), 'nomormeter' => mb_substr($isian['NomorMeter'], 0, 15),
                            'skalaprioritas' => mb_substr($isian['SkalaPrioritas'], 0, 5), 'peringkat' => mb_substr($isian['Peringkat'], 0, 12), 'lanjut' => $lanjut ? '1' : ''];
            $buka_modal = 'update';
        } elseif ($lanjut) {         // siap IdPel berikutnya
            $form_update = ['lanjut' => '1']; $buka_modal = 'update';
        }

    } elseif ($aksi === 'update_excel') {
        // ---- UPDATE DIL: upload Excel (IdPel + satu/lebih kolom: NomorMeter, SkalaPrioritas, Peringkat) ----
        $f = $_FILES['file_update'] ?? null;
        $dipilih = array_values(array_filter(array_intersect(array_keys(UPDATE_KOLOM), array_map('strval', (array) ($_POST['kolom'] ?? []))), fn($k) => $ud_meta[$k]['ada']));
        if (!$unit_siap) {
            $pesan_error = 'Akun Anda belum memiliki Unit UPI, sehingga data tidak dapat diperbarui. Hubungi administrator.';
        } elseif (!$dipilih) {
            $pesan_error = 'Pilih minimal satu kolom yang akan diperbarui.';
        } elseif (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
            $pesan_error = 'Pilih file Excel (.xlsx) terlebih dahulu.';
        } elseif ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
            $pesan_error = 'File melebihi batas upload server (upload_max_filesize = ' . ini_get('upload_max_filesize') . ', post_max_size = ' . ini_get('post_max_size') . ').';
        } elseif ($f['error'] !== UPLOAD_ERR_OK) {
            $pesan_error = 'Upload gagal (kode ' . (int) $f['error'] . ').';
        } elseif (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
            $pesan_error = 'Format file harus .xlsx.';
        } elseif ($f['size'] > 64 * 1024 * 1024) {
            $pesan_error = 'Ukuran file maksimal 64 MB.';
        } else {
            @set_time_limit(600);
            @ini_set('memory_limit', '512M');
            $kolom_id = null; $peta_ud = [];            // IdPel & kolom dil => huruf kolom Excel
            $baris = []; $terlihat = [];                // IdPel => [kolom => nilai]
            $total = $salah = $dobel = $tidak_ada = $sama = $tanpa_nilai = 0;
            $tambah_detail = function (string $t) use (&$detail_import) { if (count($detail_import) < 20) $detail_import[] = $t; };
            try {
                foreach (xlsx_baris($f['tmp_name']) as $no => $sel) {
                    if ($kolom_id === null) {           // cari baris judul (30 baris pertama)
                        if ($no > 30) throw new RuntimeException('Baris judul kolom (IDPEL dan kolom yang diperbarui) tidak ditemukan di 30 baris pertama.');
                        $ti = null; $tmp = [];
                        foreach ($sel as $huruf => $isi) {
                            $k = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $isi));
                            if ($k === 'IDPEL' && $ti === null) $ti = $huruf;
                            elseif (isset(UPDATE_HEADER[$k]) && in_array(UPDATE_HEADER[$k], $dipilih, true) && !isset($tmp[UPDATE_HEADER[$k]])) $tmp[UPDATE_HEADER[$k]] = $huruf;
                        }
                        if ($ti !== null && $tmp) { $kolom_id = $ti; $peta_ud = $tmp; }
                        continue;
                    }
                    $id_mentah = trim((string) ($sel[$kolom_id] ?? ''));
                    $isi_baris = array_filter(array_map(fn($h) => trim((string) ($sel[$h] ?? '')), $peta_ud), fn($x) => $x !== '');
                    if ($id_mentah === '' && !$isi_baris) continue;                 // baris kosong
                    $total++;
                    $id = pg_norm_idpel($id_mentah);
                    if (!preg_match('/^[A-Za-z0-9]{12}$/', $id)) { $salah++; $tambah_detail("Baris $no: IdPel tidak valid (" . ($id_mentah === '' ? 'kosong' : $id_mentah) . ')'); continue; }
                    if (!$isi_baris)                             { $tanpa_nilai++; continue; }   // IdPel ada, semua sel nilai kosong: tidak ada yang diubah
                    $baru = [];
                    foreach ($peta_ud as $kol => $huruf) {
                        $mentah = trim((string) ($sel[$huruf] ?? ''));
                        if ($mentah === '') continue;                                               // sel kosong = kolom itu tidak diubah
                        [$ok_n, $nilai] = ud_nilai($kol, $ud_meta[$kol], $mentah);
                        if ($ok_n) $baru[$kol] = $nilai; else { $salah++; $tambah_detail("Baris $no: " . UPDATE_KOLOM[$kol] . ' tidak valid (' . $mentah . ': ' . $nilai . ") untuk IdPel $id"); }
                    }
                    if (!$baru) continue;
                    if (isset($terlihat[$id])) { $dobel++; $tambah_detail("Baris $no: IdPel $id dobel di dalam file (nilai terakhir dipakai)"); }
                    $terlihat[$id] = true;
                    $baris[$id] = $baru + ($baris[$id] ?? []);                                       // nilai baris terakhir menimpa
                }
                if ($kolom_id === null) throw new RuntimeException('Baris judul kolom tidak ditemukan. File harus punya kolom IDPEL dan minimal satu dari: ' . implode(', ', array_map(fn($k) => UPDATE_KOLOM[$k], $dipilih)) . '.');
                if (!$baris) throw new RuntimeException($total === 0 ? 'File tidak berisi data di bawah baris judul.' : 'Tidak ada nilai valid yang bisa diproses.');

                // cek ke dil: hanya IdPel di wilayah akun; nilai yang sama dilewati
                $kolom_pakai = array_keys($peta_ud);
                $sel_kol = implode(', ', array_map(fn($k) => "d.`$k`", $kolom_pakai));          // nama kolom dari daftar putih UPDATE_KOLOM
                $ubah = [];
                foreach (array_chunk(array_map('strval', array_keys($baris)), 500) as $paket) {   // strval: IdPel angka murni jadi int bila jadi kunci array
                    $ph = []; $p = [];
                    foreach ($paket as $i => $id) { $ph[] = ":w$i"; $p[":w$i"] = $id; }
                    $scope = pg_scope($u, 'd', 'Unit', $p);
                    $st = $conn->prepare("SELECT d.IdPel, $sel_kol FROM dil d WHERE d.IdPel IN (" . implode(',', $ph) . ") AND $scope");
                    $st->execute($p);
                    $ada = [];
                    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $ada[(string) trim((string) $r['IdPel'])] = $r;
                    foreach ($paket as $id) {
                        if (!isset($ada[$id])) { $tidak_ada++; $tambah_detail("IdPel $id tidak ada di DIL pada wilayah Anda"); continue; }
                        $beda = [];
                        foreach ($baris[$id] as $kol => $nilai) if (!ud_sama($ud_meta[$kol], $ada[$id][$kol] ?? null, $nilai)) $beda[$kol] = $nilai;
                        if ($beda) $ubah[$id] = $beda; else $sama++;
                    }
                }

                // perbarui per 500 IdPel dalam satu transaksi
                $per_kolom = array_fill_keys($kolom_pakai, 0);
                if ($ubah) {
                    $conn->beginTransaction();
                    foreach (array_chunk($ubah, 500, true) as $paket) {
                        $case = array_fill_keys($kolom_pakai, []); $in = []; $p = []; $i = 0;
                        foreach ($paket as $id => $perkol) {
                            $id = (string) $id; $in[] = ":w$i"; $p[":w$i"] = $id;
                            foreach ($perkol as $kol => $nilai) {
                                $ci = array_search($kol, $kolom_pakai, true);
                                $case[$kol][] = "WHEN :i{$ci}_$i THEN :v{$ci}_$i"; $p[":i{$ci}_$i"] = $id; $p[":v{$ci}_$i"] = $nilai; $per_kolom[$kol]++;
                            }
                            $i++;
                        }
                        $set = [];
                        foreach ($case as $kol => $when) if ($when) $set[] = "d.`$kol` = CASE d.IdPel " . implode(' ', $when) . " ELSE d.`$kol` END";
                        $scope = pg_scope($u, 'd', 'Unit', $p);
                        $conn->prepare("UPDATE dil d SET " . implode(', ', $set) . ", d.WaktuData = NOW() WHERE d.IdPel IN (" . implode(',', $in) . ") AND $scope")->execute($p);
                    }
                    $conn->commit();
                }

                $rinci = [];
                foreach ($per_kolom as $kol => $n) if ($n) $rinci[] = UPDATE_KOLOM[$kol] . ': ' . pg_n($n);
                $pesan_sukses = 'Proses selesai: <strong>' . pg_n(count($ubah)) . '</strong> IdPel diperbarui' . ($rinci ? ' (' . implode(', ', $rinci) . ')' : '')
                              . ($sama ? ', ' . pg_n($sama) . ' sudah bernilai sama (dilewati)' : '')
                              . ($tidak_ada ? ', ' . pg_n($tidak_ada) . ' tidak ada di DIL wilayah Anda' : '')
                              . ($tanpa_nilai ? ', ' . pg_n($tanpa_nilai) . ' tanpa nilai (sel kosong)' : '')
                              . ($salah ? ', ' . pg_n($salah) . ' isian tidak valid' : '')
                              . ($dobel ? ', ' . pg_n($dobel) . ' dobel di dalam file' : '') . '.';
                if (!$ubah) { $pesan_info = $pesan_sukses; $pesan_sukses = ''; }
            } catch (Throwable $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                $pesan_error = 'Proses gagal, tidak ada data yang diubah: ' . pg_e($e->getMessage());
            }
        }
    }

    // Post-Redirect-Get supaya refresh tidak mengirim ulang form
    if (!headers_sent()) {
        $_SESSION['pg_flash'] = ['sukses' => $pesan_sukses, 'error' => $pesan_error, 'detail' => $detail_import, 'form_lama' => $form_lama, 'info' => $pesan_info, 'form_update' => $form_update, 'buka' => $buka_modal];
        header('Location: ' . pg_url('input_dil'));
        exit;
    }
}

// nilai awal unit pada form tambah = unit akun yang login
$unit_awal = ['UnitUpi' => (string) ($_SESSION['UnitUpi'] ?? ''), 'UnitAp' => (string) ($_SESSION['UnitAp'] ?? ''), 'UnitUp' => (string) ($_SESSION['UnitUp'] ?? '')];

// tombol ke monitoring hanya bila akun berhak membukanya
$boleh_monitor = pga_boleh('monitor_dil');

include __DIR__ . '/../../templates/penggunaan/input_dil.php';