<?php
// modules/penggunaan/input_dil.php  -  Input DIL (tabel `dil`)
// Kolom: IdPel CHAR(12) PK | NamaPelanggan VARCHAR(50) | IndexPrioritas INT | StatusData ENUM('AKTIF','TIDAK') | WaktuData TIMESTAMP
// Fitur: tambah manual (status otomatis AKTIF) dan import Excel (.xlsx).
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

    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $pesan_error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';

    } elseif ($aksi === 'tambah') {
        // ---- Tambah manual: status otomatis AKTIF ----
        $idpel = pg_norm_idpel($_POST['idpel'] ?? '');
        $nama  = trim(preg_replace('/\s+/', ' ', (string) ($_POST['nama'] ?? '')));
        if (!preg_match('/^[A-Za-z0-9]{12}$/', $idpel)) {
            $pesan_error = 'IdPel harus 12 karakter (huruf/angka).';
        } elseif ($nama === '' || mb_strlen($nama) > 50) {
            $pesan_error = 'Nama pelanggan wajib diisi (maksimal 50 karakter).';
        } else {
            try {
                $st = $conn->prepare("INSERT INTO dil (IdPel, NamaPelanggan, StatusData, WaktuData) VALUES (?, ?, 'AKTIF', NOW())");
                $st->execute([$idpel, $nama]);
                $pesan_sukses = 'Pelanggan <strong>' . pg_e($idpel) . '</strong> berhasil ditambahkan (status AKTIF).';
            } catch (PDOException $e) {
                $pesan_error = $e->getCode() === '23000'
                    ? 'IdPel <strong>' . pg_e($idpel) . '</strong> sudah terdaftar di DIL.'
                    : 'Gagal menyimpan: ' . pg_e($e->getMessage());
            }
        }

    } elseif ($aksi === 'import') {
        // ---- Import Excel: kolom A = IdPel, kolom B = Nama Pelanggan; semua berstatus AKTIF ----
        $f = $_FILES['file_xlsx'] ?? null;
        if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
            $pesan_error = 'Pilih file Excel (.xlsx) terlebih dahulu.';
        } elseif ($f['error'] !== UPLOAD_ERR_OK) {
            $pesan_error = 'Upload gagal (kode ' . (int) $f['error'] . '). Periksa batas ukuran upload server.';
        } elseif (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
            $pesan_error = 'Format file harus .xlsx.';
        } elseif ($f['size'] > 10 * 1024 * 1024) {
            $pesan_error = 'Ukuran file maksimal 10 MB.';
        } else {
            @set_time_limit(300);
            $total = $ok = $dobel = $salah = 0;
            $seen  = [];
            try {
                $ins = $conn->prepare("INSERT IGNORE INTO dil (IdPel, NamaPelanggan, StatusData, WaktuData) VALUES (?, ?, 'AKTIF', NOW())");
                $conn->beginTransaction();
                $pertama = true;
                foreach (xlsx_baris($f['tmp_name']) as $no => $sel) {
                    $a = trim((string) ($sel['A'] ?? ''));
                    $b = trim(preg_replace('/\s+/', ' ', (string) ($sel['B'] ?? '')));
                    if ($a === '' && $b === '') continue;                       // baris kosong
                    if ($pertama) {                                              // lewati baris judul
                        $pertama = false;
                        if (preg_match('/id\s*pel/i', $a)) continue;
                    }
                    $total++;
                    $idpel = pg_norm_idpel($a);
                    if (!preg_match('/^[A-Za-z0-9]{12}$/', $idpel) || $b === '') {
                        $salah++;
                        if (count($detail_import) < 20) $detail_import[] = "Baris $no: IdPel/Nama tidak valid (" . ($a === '' ? 'kosong' : $a) . ')';
                        continue;
                    }
                    if (isset($seen[$idpel])) {
                        $dobel++;
                        if (count($detail_import) < 20) $detail_import[] = "Baris $no: IdPel $idpel dobel di dalam file";
                        continue;
                    }
                    $seen[$idpel] = true;
                    $ins->execute([$idpel, mb_substr($b, 0, 50)]);
                    if ($ins->rowCount() > 0) {
                        $ok++;
                    } else {
                        $dobel++;
                        if (count($detail_import) < 20) $detail_import[] = "Baris $no: IdPel $idpel sudah terdaftar";
                    }
                }
                $conn->commit();
                if ($total === 0) {
                    $pesan_error = 'File tidak berisi data. Pastikan kolom A = IdPel dan kolom B = Nama Pelanggan.';
                } else {
                    $pesan_sukses = "Import selesai: <strong>" . pg_n($ok) . "</strong> pelanggan baru ditambahkan"
                                  . ($dobel ? ', ' . pg_n($dobel) . ' dilewati (sudah ada/dobel)' : '')
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

// tombol ke monitoring hanya bila akun berhak membukanya
$boleh_monitor = pga_boleh('monitor_dil');

include __DIR__ . '/../../templates/penggunaan/input_dil.php';