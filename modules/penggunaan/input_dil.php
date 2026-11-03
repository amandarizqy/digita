<?php
// modules/penggunaan/input_dil.php  -  Input DIL (tabel `dil`)
// Kolom dil: IdPel (PK) | Nama | Alamat | JenisMk | NomorTlp | NomorHp | GolTar | Daya | TegUkur | KdPembMeter | NomorKwh
//            | Aktifasi | ProdukPrabayar | IndexPrioritas | NamaAkun | UnitUp | UnitAp | UnitUpi | StatusData | WaktuData
// Fitur: tambah manual (status otomatis AKTIF) dan import Excel "Monitoring DIL Detail" (.xlsx).
// Import: baris Excel dimasukkan ke tabel penampung `transit_dil`, dipindahkan ke `dil`, lalu transit_dil dikosongkan lagi.
// Daftar, pencarian, edit, dan aktif/nonaktif ada di Monitoring > DIL (monitor_dil.php).
require_once __DIR__ . '/../../functions/xlsx_reader.php';

$pesan_sukses  = '';
$pesan_error   = '';
$detail_import = [];   // rincian baris yang dilewati saat import

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

/** Kolom tabel dil yang diisi dari Excel (urutan = urutan INSERT). */
const DIL_KOLOM = ['IdPel', 'Nama', 'Alamat', 'JenisMk', 'NomorTlp', 'NomorHp', 'GolTar', 'Daya', 'TegUkur', 'KdPembMeter',
                   'NomorKwh', 'Aktifasi', 'ProdukPrabayar', 'NamaAkun', 'UnitUp', 'UnitAp', 'UnitUpi', 'StatusData', 'WaktuData'];

/** transit_dil tidak punya StatusData: kolomnya sama dengan dil tanpa StatusData. StatusData (AKTIF) diberikan saat pindah ke dil. */
const TRANSIT_KOLOM = ['IdPel', 'Nama', 'Alamat', 'JenisMk', 'NomorTlp', 'NomorHp', 'GolTar', 'Daya', 'TegUkur', 'KdPembMeter',
                       'NomorKwh', 'Aktifasi', 'ProdukPrabayar', 'NamaAkun', 'UnitUp', 'UnitAp', 'UnitUpi', 'WaktuData'];

/** Judul kolom Excel (huruf besar, tanpa spasi/simbol) => kolom dil. */
const DIL_HEADER = [
    'IDPEL' => 'IdPel', 'NAMA' => 'Nama', 'ALAMAT' => 'Alamat', 'JENISMK' => 'JenisMk', 'NOTLP' => 'NomorTlp', 'NOHP' => 'NomorHp',
    'TARIF' => 'GolTar', 'DAYA' => 'Daya', 'TEGUKUR' => 'TegUkur', 'KDPEMBMETER' => 'KdPembMeter', 'NOMORKWH' => 'NomorKwh',
    'AKTIFASI' => 'Aktifasi', 'PRODUKPRABAYAR' => 'ProdukPrabayar', 'UNITUP' => 'UnitUp', 'UNITAP' => 'UnitAp', 'UNITUPI' => 'UnitUpi',
];

/** Rapikan teks sel: buang spasi ganda/tepi (Excel PLN sering berisi ' ') lalu potong sesuai panjang kolom. */
if (!function_exists('pg_dil_teks')) {
    function pg_dil_teks($v, int $maks): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $v)), 0, $maks);
    }
}

// ---- pesan hasil proses sebelumnya (post-redirect-get) ----
if (!empty($_SESSION['pg_flash'])) {
    $fl = $_SESSION['pg_flash']; unset($_SESSION['pg_flash']);
    $pesan_sukses  = $fl['sukses'] ?? '';
    $pesan_error   = $fl['error'] ?? '';
    $detail_import = $fl['detail'] ?? [];
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
        if (!preg_match('/^[A-Za-z0-9]{12}$/', $idpel)) {
            $pesan_error = 'IdPel harus 12 karakter (huruf/angka).';
        } elseif ($nama === '' || mb_strlen(trim((string) ($_POST['nama'] ?? ''))) > 50) {
            $pesan_error = 'Nama pelanggan wajib diisi (maksimal 50 karakter).';
        } elseif ($daya_mentah !== '' && !preg_match('/^\d{1,9}$/', $daya_mentah)) {
            $pesan_error = 'Daya harus berupa angka bulat (VA).';
        } elseif ($teg !== '' && !in_array($teg, ['TR', 'TM', 'TT'], true)) {
            $pesan_error = 'Tegangan ukur harus TR, TM, atau TT.';
        } else {
            try {
                $st = $conn->prepare("INSERT INTO dil (IdPel, Nama, Alamat, JenisMk, NomorTlp, NomorHp, GolTar, Daya, TegUkur, KdPembMeter, NomorKwh,
                                                       Aktifasi, ProdukPrabayar, NamaAkun, UnitUp, UnitAp, UnitUpi, StatusData, WaktuData)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'AKTIF', NOW())");
                $st->execute([
                    $idpel, $nama, $t('alamat', 100), $t('jenismk', 10), $t('nomortlp', 20), $t('nomorhp', 20),
                    strtoupper($t('goltar', 5)), $daya_mentah === '' ? 0 : (int) $daya_mentah, $teg === '' ? null : $teg, $kdp,
                    $t('nomorkwh', 15), $t('aktifasi', 15), $t('produkprabayar', 50),
                    mb_substr((string) ($_SESSION['NamaAkun'] ?? ''), 0, 20),
                    $t('unitup', 5), $t('unitap', 5), $t('unitupi', 2),
                ]);
                $pesan_sukses = 'Pelanggan <strong>' . pg_e($idpel) . '</strong> berhasil ditambahkan (status AKTIF).';
            } catch (PDOException $e) {
                $pesan_error = $e->getCode() === '23000'
                    ? 'IdPel <strong>' . pg_e($idpel) . '</strong> sudah terdaftar di DIL.'
                    : 'Gagal menyimpan: ' . pg_e($e->getMessage());
            }
        }

    } elseif ($aksi === 'import') {
        // ---- Import Excel "Monitoring DIL Detail" ----
        // Judul kolom dicari otomatis (baris yang memuat IDPEL), jadi baris kop surat di atas dan urutan kolom bebas.
        // Alur: kosongkan sisa transit_dil milik akun ini -> isi transit_dil -> salin ke dil (StatusData = AKTIF) -> kosongkan transit_dil.
        $f = $_FILES['file_xlsx'] ?? null;
        if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
            $pesan_error = 'Pilih file Excel (.xlsx) terlebih dahulu.';
        } elseif ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
            $pesan_error = 'File melebihi batas upload server (upload_max_filesize = ' . ini_get('upload_max_filesize') . ', post_max_size = ' . ini_get('post_max_size') . '). Naikkan di php.ini lalu restart web server.';
        } elseif ($f['error'] !== UPLOAD_ERR_OK) {
            $pesan_error = 'Upload gagal (kode ' . (int) $f['error'] . '). Periksa folder temp dan batas ukuran upload server.';
        } elseif (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
            $pesan_error = 'Format file harus .xlsx.';
        } elseif ($f['size'] > 10 * 1024 * 1024) {
            $pesan_error = 'Ukuran file maksimal 10 MB.';
        } else {
            @set_time_limit(600);
            @ini_set('memory_limit', '256M');
            $akun  = mb_substr((string) ($_SESSION['NamaAkun'] ?? ''), 0, 20);
            $total = $ok = $dobel = $salah = 0;
            $seen  = [];
            $peta  = [];            // kolom dil => huruf kolom Excel
            $batch = [];            // baris yang menunggu dikirim ke transit_dil
            $kol_dil     = implode(', ', DIL_KOLOM);
            $kol_transit = implode(', ', TRANSIT_KOLOM);
            // SELECT dari transit_dil mengikuti urutan DIL_KOLOM; StatusData diisi konstanta AKTIF
            $kol_pilih   = implode(', ', array_map(fn($k) => $k === 'StatusData' ? "'AKTIF'" : $k, DIL_KOLOM));
            $per_baris   = '(' . implode(', ', array_fill(0, count(TRANSIT_KOLOM) - 1, '?')) . ', NOW())';   // WaktuData = NOW()
            $kirim = function (array $rows) use ($conn, $kol_transit, $per_baris) {
                if (!$rows) return;
                $conn->prepare("INSERT INTO transit_dil ($kol_transit) VALUES " . implode(', ', array_fill(0, count($rows), $per_baris)))
                     ->execute(array_merge(...$rows));
            };
            try {
                $conn->beginTransaction();
                $conn->prepare("DELETE FROM transit_dil WHERE NamaAkun = ?")->execute([$akun]);   // sisa import yang gagal sebelumnya

                foreach (xlsx_baris($f['tmp_name']) as $no => $sel) {
                    if (!$peta) {                                           // cari baris judul
                        if ($no > 30) throw new RuntimeException('Baris judul kolom (IDPEL, NAMA, ...) tidak ditemukan di 30 baris pertama.');
                        $tmp = [];
                        foreach ($sel as $huruf => $isi) {
                            $kunci = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $isi));
                            if (isset(DIL_HEADER[$kunci]) && !isset($tmp[DIL_HEADER[$kunci]])) $tmp[DIL_HEADER[$kunci]] = $huruf;
                        }
                        if (isset($tmp['IdPel'], $tmp['Nama'])) $peta = $tmp;
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

                    $teg = strtoupper($g('TegUkur', 2));
                    $batch[] = [
                        $idpel, $nama, $g('Alamat', 100), $g('JenisMk', 10), $g('NomorTlp', 20), $g('NomorHp', 20),
                        $g('GolTar', 5), (int) round((float) str_replace(',', '.', $g('Daya', 15))),
                        in_array($teg, ['TR', 'TM', 'TT'], true) ? $teg : null,
                        $g('KdPembMeter', 1), $g('NomorKwh', 15), $g('Aktifasi', 15), $g('ProdukPrabayar', 50),
                        $akun, $g('UnitUp', 5), $g('UnitAp', 5), $g('UnitUpi', 2),
                    ];
                    if (count($batch) >= 500) { $kirim($batch); $batch = []; }
                }
                if (!$peta) throw new RuntimeException('Baris judul kolom (IDPEL, NAMA, ...) tidak ditemukan. Pastikan file adalah "Monitoring DIL Detail".');
                $kirim($batch);

                // transit_dil -> dil (IdPel yang sudah ada dilewati), lalu transit_dil dikosongkan
                $in = $conn->prepare("INSERT IGNORE INTO dil ($kol_dil) SELECT $kol_pilih FROM transit_dil WHERE NamaAkun = ?");
                $in->execute([$akun]);
                $ok = $in->rowCount();
                $conn->prepare("DELETE FROM transit_dil WHERE NamaAkun = ?")->execute([$akun]);
                $conn->commit();

                $sudah_ada = max(0, count($seen) - $ok);
                if ($total === 0) {
                    $pesan_error = 'File tidak berisi data pelanggan di bawah baris judul.';
                } else {
                    $pesan_sukses = "Import selesai: <strong>" . pg_n($ok) . "</strong> pelanggan baru ditambahkan"
                                  . ($sudah_ada ? ', ' . pg_n($sudah_ada) . ' dilewati (IdPel sudah ada di DIL)' : '')
                                  . ($dobel ? ', ' . pg_n($dobel) . ' dobel di dalam file' : '')
                                  . ($salah ? ', ' . pg_n($salah) . ' baris tidak valid' : '') . '.';
                }
            } catch (Throwable $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                $pesan_error = 'Import gagal, tidak ada data yang tersimpan: ' . pg_e($e->getMessage());
            }
        }
    }

    // Post-Redirect-Get supaya refresh tidak mengirim ulang form
    if (!headers_sent()) {
        $_SESSION['pg_flash'] = ['sukses' => $pesan_sukses, 'error' => $pesan_error, 'detail' => $detail_import];
        header('Location: ' . pg_url('input_dil'));
        exit;
    }
}

// ---------------------------------------------------------
// RINGKASAN (daftar lengkap ada di Monitoring > DIL)
// ---------------------------------------------------------
$kpi = ['total' => 0, 'AKTIF' => 0, 'TIDAK' => 0];
try {
    foreach ($conn->query("SELECT StatusData, COUNT(*) c FROM dil GROUP BY StatusData")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (isset($kpi[$r['StatusData']])) $kpi[$r['StatusData']] = (int) $r['c'];
        $kpi['total'] += (int) $r['c'];
    }
} catch (PDOException $e) {
    $pesan_error = trim($pesan_error . ' Gagal memuat ringkasan DIL: ' . pg_e($e->getMessage()));
}

// nilai awal unit pada form tambah = unit akun yang login
$unit_awal = ['UnitUpi' => (string) ($_SESSION['UnitUpi'] ?? ''), 'UnitAp' => (string) ($_SESSION['UnitAp'] ?? ''), 'UnitUp' => (string) ($_SESSION['UnitUp'] ?? '')];

// tombol ke monitoring hanya bila akun berhak membukanya
$boleh_monitor = pga_boleh('monitor_dil');

include __DIR__ . '/../../templates/penggunaan/input_dil.php';