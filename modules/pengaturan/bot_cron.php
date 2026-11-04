<?php
/**
 * modules/pengaturan/bot_cron.php - Pelaksana cron untuk bot unggah file DIGITA.
 *
 * Membaca daftar bot dari tabel pengaturan_bot (diatur di Pengaturan > Daftar Bot), lalu untuk SETIAP bot berstatus AKTIF:
 *   1. memindai FolderMasuk,
 *   2. mengunggah tiap file sesuai JenisBot (PELUNASAN | DIL | TUL | SURVEY | KEPENTINGAN) langsung ke tabel tujuannya,
 *   3. memindahkan file ke FolderBerhasil / FolderGagal (+ file .txt berisi alasan/baris yang ditolak),
 *   4. mencatat hasilnya ke TerakhirJalan, StatusTerakhir, PesanTerakhir (tampil di halaman Daftar Bot).
 *
 * Perintah (jalankan dari command line / cron, TIDAK bisa dibuka lewat browser):
 *     php bot_cron.php siapkan                 buat folder bot + subfolder AP/UP sesuai tingkat bot
 *     php bot_cron.php status                  tampilkan daftar bot dan jumlah file yang menunggu
 *     php bot_cron.php jalan --kering          hanya tampilkan file yang akan diunggah
 *     php bot_cron.php jalan                   unggah sungguhan (inilah yang dipasang di cron)
 *   Opsi: --bot=kode_bot (hanya bot itu)   --stabil=detik (file harus tidak berubah selama N detik, bawaan 60)
 *
 * Susunan folder (relatif terhadap folder bot, lihat bot_root()) menurut tingkat wilayah bot:
 *   bot tingkat UP  (UnitUpi+UnitAp+UnitUp terisi) : <FolderMasuk>/file.xlsx
 *   bot tingkat AP  (UnitUp kosong)                 : <FolderMasuk>/<KodeUP>/file.xlsx
 *   bot tingkat UPI (UnitAp & UnitUp kosong)        : <FolderMasuk>/<KodeAP>/<KodeUP>/file.xlsx
 *   (khusus jenis DIL boleh langsung <FolderMasuk>/file.xlsx bila kolom UNITAP/UNITUP ada di file)
 *
 * Kode keluar: 0 = semua beres / tidak ada pekerjaan, 1 = ada file gagal atau tertunda, 2 = kesalahan fatal.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip ini hanya dapat dijalankan dari command line (cron).');
}

if (!ini_get('date.timezone')) date_default_timezone_set('Asia/Jakarta');
@ini_set('memory_limit', '512M');

require_once __DIR__ . '/../../config/database.php';   // $conn (PDO)
require_once __DIR__ . '/bot_helper.php';
require_once __DIR__ . '/bot_handlers.php';

// ============================================================
// Log (ke layar + <folder bot>/log/bot_cron.log, diputar otomatis)
// ============================================================
function bc_log(string $level, string $pesan): void
{
    $baris = date('Y-m-d H:i:s') . " [$level] $pesan";
    echo $baris . PHP_EOL;
    $berkas = $GLOBALS['BC_LOG'] ?? '';
    if ($berkas === '') return;
    if (@filesize($berkas) > 2000000) {                       // > 2 MB: putar, simpan 5 cadangan
        for ($i = 4; $i >= 1; $i--) if (is_file("$berkas.$i")) @rename("$berkas.$i", "$berkas." . ($i + 1));
        @rename($berkas, "$berkas.1");
    }
    @file_put_contents($berkas, $baris . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// ============================================================
// Pemindaian folder masuk
// ============================================================
/** Subfolder langsung di $dir (nama => path), urut nama. */
function bc_subfolder(string $dir): array
{
    $hasil = [];
    foreach (@scandir($dir) ?: [] as $n) {
        if ($n === '.' || $n === '..' || !is_dir("$dir/$n")) continue;
        $hasil[$n] = "$dir/$n";
    }
    return $hasil;
}

/**
 * File .xlsx di satu folder.
 * @return array [siap => path[], belum_stabil => nama[]]
 */
function bc_file_di(string $dir, int $stabil): array
{
    $siap = $tunggu = [];
    $sekarang = time();
    foreach (@scandir($dir) ?: [] as $n) {
        $p = "$dir/$n";
        if (!is_file($p) || $n[0] === '.' || strncmp($n, '~$', 2) === 0) continue;   // file kunci sementara Excel / tersembunyi
        if (strtolower(pathinfo($n, PATHINFO_EXTENSION)) !== 'xlsx') continue;
        if ($sekarang - (int) @filemtime($p) < $stabil) { $tunggu[] = $n; continue; }  // mungkin masih disalin
        $siap[] = $p;
    }
    return ['siap' => $siap, 'belum_stabil' => $tunggu];
}

/**
 * Kumpulkan pekerjaan untuk satu bot menurut tingkat wilayahnya.
 * @return array [tugas[], salah_tempat[], belum_stabil[]]  tugas = ['file','ap','up']
 */
function bc_cari_tugas(PDO $conn, array $bot, string $dir_masuk, int $stabil): array
{
    $upi = trim((string) $bot['UnitUpi']); $ap_b = trim((string) $bot['UnitAp']); $up_b = trim((string) $bot['UnitUp']);
    $jenis = $bot['JenisBot'];
    $peta = bh_peta_unit($conn, $upi, $ap_b, $up_b);

    $tugas = $salah = $tunggu = [];
    $ambil = function (string $dir, string $ap, string $up) use (&$tugas, &$salah, &$tunggu, $stabil, $jenis) {
        $r = bc_file_di($dir, $stabil);
        foreach ($r['belum_stabil'] as $n) $tunggu[] = $n;
        foreach ($r['siap'] as $f) {
            if ($jenis !== 'DIL' && ($ap === '' || $up === '')) { $salah[] = $f; continue; }   // wilayah tidak lengkap
            $tugas[] = ['file' => $f, 'ap' => $ap, 'up' => $up];
        }
    };
    $up_valid = fn(string $kode, string $ap) => ($e = $peta['up'][strtoupper($kode)] ?? null) && ($ap === '' || strcasecmp($e['ap'], $ap) === 0) ? $e : null;

    if ($up_b !== '' && $ap_b !== '') {                                           // tingkat UP
        $ambil($dir_masuk, $ap_b, $up_b);
        foreach (bc_subfolder($dir_masuk) as $n => $p) bc_log('WARN', "Subfolder diabaikan (bot tingkat UP: taruh file langsung di folder masuk): $p");
    } elseif ($ap_b !== '') {                                                      // tingkat AP
        $ap_kanon = $peta['ap'][strtoupper($ap_b)] ?? $ap_b;
        $ambil($dir_masuk, $ap_kanon, '');
        foreach (bc_subfolder($dir_masuk) as $n => $p) {
            if ($e = $up_valid($n, $ap_kanon)) $ambil($p, $ap_kanon, $e['kode']);
            else bc_log('WARN', "Subfolder diabaikan (bukan kode UP aktif di bawah AP $ap_kanon): $p");
        }
    } else {                                                                       // tingkat UPI
        $ambil($dir_masuk, '', '');
        foreach (bc_subfolder($dir_masuk) as $n => $p) {
            if (!isset($peta['ap'][strtoupper($n)])) { bc_log('WARN', "Subfolder diabaikan (bukan kode AP aktif di bawah UPI $upi): $p"); continue; }
            $ap = $peta['ap'][strtoupper($n)];
            $ambil($p, $ap, '');
            foreach (bc_subfolder($p) as $n2 => $p2) {
                if ($e = $up_valid($n2, $ap)) $ambil($p2, $ap, $e['kode']);
                else bc_log('WARN', "Subfolder diabaikan (bukan kode UP aktif di bawah AP $ap): $p2");
            }
        }
    }
    return [$tugas, $salah, $tunggu];
}

// ============================================================
// Memindahkan file setelah diproses
// ============================================================
function bc_pindah(string $file, string $root, string $folder_rel, string $ap, string $up, string $catatan = ''): string
{
    $dir = $root . '/' . $folder_rel . '/' . date('Y-m-d') . ($ap !== '' ? "/$ap" : '') . ($up !== '' ? "/$up" : '');
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException("Tidak bisa membuat folder $dir");
    $tujuan = $dir . '/' . date('His') . '_' . basename($file);                // cap waktu: tidak menimpa file lama
    if (!@rename($file, $tujuan)) {                                            // beda drive -> salin lalu hapus
        if (!@copy($file, $tujuan)) throw new RuntimeException("Tidak bisa memindahkan file ke $tujuan");
        @unlink($file);
    }
    if ($catatan !== '') @file_put_contents($tujuan . '.txt', $catatan);
    return $tujuan;
}

/** Isi file .txt pendamping: alasan + contoh baris ditolak. */
function bc_catatan(string $nama, array $hasil): string
{
    $t = "File    : $nama\r\nWaktu   : " . date('Y-m-d H:i:s') . "\r\nHasil   : " . $hasil['pesan'] . "\r\n";
    if ($hasil['detail']) $t .= "\r\nBaris ditolak (maksimal 50 contoh):\r\n- " . implode("\r\n- ", $hasil['detail']) . "\r\n";
    return $t;
}

// ============================================================
// Satu bot
// ============================================================
/** @return array ['kerja' => bool (ada yang dikerjakan/error), 'status' => BERHASIL|SEBAGIAN|GAGAL, 'pesan' => string] */
function bc_proses_bot(PDO $conn, array $bot, string $root, bool $kering, int $stabil): array
{
    $gagal = fn(string $p) => ['kerja' => true, 'status' => 'GAGAL', 'pesan' => $p];
    $jenis_daftar = bot_jenis_daftar();
    $jenis = (string) $bot['JenisBot'];

    if (!isset($jenis_daftar[$jenis]))                                  return $gagal("Jenis bot '$jenis' tidak dikenal.");
    if (strtolower(trim((string) $bot['PolaFile'])) !== '*.xlsx')       return $gagal("Pola file '{$bot['PolaFile']}' belum didukung bot unggah (hanya *.xlsx). Ubah di Daftar Bot.");
    if (trim((string) $bot['UnitUpi']) === '')                          return $gagal('Bot belum punya Unit UPI. Buat ulang bot dari akun yang memiliki unit.');
    if (trim((string) $bot['UnitUp']) !== '' && trim((string) $bot['UnitAp']) === '') return $gagal('Data wilayah bot tidak konsisten (UnitUp terisi tetapi UnitAp kosong).');

    $fm = bot_norm_folder((string) $bot['FolderMasuk']);
    $fb = bot_norm_folder((string) $bot['FolderBerhasil']);
    $fg = bot_norm_folder((string) $bot['FolderGagal']);
    if ($fm === null || $fb === null || $fg === null)                   return $gagal('Folder masuk/berhasil/gagal tidak valid. Perbaiki di Daftar Bot.');
    if (strtolower($fb) === strtolower($fg) || bot_di_dalam($fb, $fm) || bot_di_dalam($fg, $fm) || bot_di_dalam($fm, $fb) || bot_di_dalam($fm, $fg)) {
        return $gagal('Folder berhasil/gagal tidak boleh sama dengan atau berada di dalam folder masuk (dan sebaliknya).');
    }
    if (!$kering && ($sulit = bot_buat_folder($root, [$fm, $fb, $fg]))) return $gagal('Folder tidak bisa dibuat: ' . implode(', ', $sulit));
    $dir_masuk = $root . '/' . $fm;
    if (!is_dir($dir_masuk)) return $kering ? ['kerja' => false, 'status' => 'BERHASIL', 'pesan' => ''] : $gagal("Folder masuk tidak ada: $fm");

    [$tugas, $salah, $tunggu] = bc_cari_tugas($conn, $bot, $dir_masuk, $stabil);
    foreach ($tunggu as $n) bc_log('INFO', "[{$bot['KodeBot']}] Dilewati dulu (baru diubah, mungkin belum selesai disalin): $n");
    foreach ($salah as $f) bc_log('WARN', "[{$bot['KodeBot']}] File salah tempat (wilayah AP/UP tidak lengkap; taruh di subfolder kode unit): $f");

    if (!$tugas && !$salah) return ['kerja' => false, 'status' => 'BERHASIL', 'pesan' => ''];   // tidak ada pekerjaan
    bc_log('INFO', "[{$bot['KodeBot']}] {$jenis} - ditemukan " . count($tugas) . ' file' . ($salah ? ', ' . count($salah) . ' salah tempat' : '') . '.');

    if ($kering) {
        foreach ($tugas as $t) bc_log('INFO', "[KERING] [{$bot['KodeBot']}] AP " . ($t['ap'] ?: '-') . ' / UP ' . ($t['up'] ?: '-') . ' : ' . basename($t['file']));
        return ['kerja' => false, 'status' => 'BERHASIL', 'pesan' => ''];
    }

    $penuh = $sebagian = $ditolak_file = $tunda = $beruntun = 0;
    $baris = 0; $terakhir = '';
    foreach ($tugas as $t) {
        $nama = basename($t['file']);
        bc_log('INFO', "[{$bot['KodeBot']}] Mengunggah AP " . ($t['ap'] ?: '-') . ' / UP ' . ($t['up'] ?: '-') . " : $nama");
        $h = bh_jalankan($conn, $bot, $jenis, $t['file'], $t['ap'], $t['up']);
        $terakhir = "$nama: " . $h['pesan'];

        if ($h['status'] === 'sukses') {
            $beruntun = 0;
            try {
                $tujuan = bc_pindah($t['file'], $root, $fb, $t['ap'], $t['up'], $h['ditolak'] > 0 ? bc_catatan($nama, $h) : '');
                bc_log('INFO', "  BERHASIL -> $tujuan | {$h['pesan']}");
            } catch (Throwable $e) {
                bc_log('ERROR', "  Data sudah tersimpan tetapi file gagal dipindah ({$e->getMessage()}); file akan diunggah ulang (aman, data tidak dobel).");
                $tunda++; continue;
            }
            $baris += $h['tersimpan'];
            $h['ditolak'] > 0 ? $sebagian++ : $penuh++;
        } elseif ($h['status'] === 'gagal') {
            $beruntun = 0;
            try {
                $tujuan = bc_pindah($t['file'], $root, $fg, $t['ap'], $t['up'], bc_catatan($nama, $h));
                bc_log('ERROR', "  DITOLAK -> $tujuan | {$h['pesan']}");
            } catch (Throwable $e) {
                bc_log('ERROR', "  Ditolak ({$h['pesan']}) tetapi file gagal dipindah: {$e->getMessage()}");
                $tunda++; continue;
            }
            $ditolak_file++;
        } else {                                              // tidak_pasti: biarkan, coba lagi putaran berikutnya
            bc_log('WARN', "  TIDAK PASTI, file dibiarkan untuk dicoba lagi | {$h['pesan']}");
            $tunda++;
            if (++$beruntun >= 3) { bc_log('ERROR', "[{$bot['KodeBot']}] 3 kegagalan sementara beruntun, bot dihentikan untuk putaran ini."); break; }
        }
    }

    $ok = $penuh + $sebagian;
    $status = ($ditolak_file + $tunda + count($salah) + $sebagian) === 0 ? 'BERHASIL' : ($ok === 0 ? 'GAGAL' : 'SEBAGIAN');
    $ringkas = count($tugas) . ' file: ' . $penuh . ' berhasil' . ($sebagian ? ", $sebagian sebagian (ada baris ditolak)" : '')
             . ($ditolak_file ? ", $ditolak_file ditolak" : '') . ($tunda ? ", $tunda tertunda" : '') . '.'
             . ($terakhir !== '' ? ' ' . $terakhir : '')
             . ($salah ? ' | Salah tempat: ' . basename($salah[0]) . (count($salah) > 1 ? ' (+' . (count($salah) - 1) . ' lagi)' : '') . ' - taruh di subfolder kode AP/UP.' : '');
    return ['kerja' => true, 'status' => $status, 'pesan' => $ringkas];
}

/** Catat hasil putaran ke pengaturan_bot (tampil di halaman Daftar Bot). */
function bc_catat(PDO $conn, int $id, string $status, string $pesan): void
{
    $conn->prepare('UPDATE pengaturan_bot SET TerakhirJalan = NOW(), StatusTerakhir = ?, PesanTerakhir = ? WHERE IdBot = ?')
         ->execute([$status, mb_substr($pesan, 0, 255), $id]);
}

// ============================================================
// Perintah
// ============================================================
function bc_ambil_bot(PDO $conn, bool $hanya_aktif, ?string $kode): array
{
    $w = []; $p = [];
    if ($hanya_aktif) $w[] = "StatusData = 'AKTIF'";
    if ($kode !== null) { $w[] = 'KodeBot = ?'; $p[] = $kode; }
    $st = $conn->prepare('SELECT * FROM pengaturan_bot' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY IdBot');
    $st->execute($p);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function bc_siapkan(PDO $conn, string $root, ?string $kode): int
{
    $n = 0;
    foreach (bc_ambil_bot($conn, false, $kode) as $bot) {
        $fm = bot_norm_folder((string) $bot['FolderMasuk']); $fb = bot_norm_folder((string) $bot['FolderBerhasil']); $fg = bot_norm_folder((string) $bot['FolderGagal']);
        if ($fm === null || $fb === null || $fg === null) { bc_log('WARN', "[{$bot['KodeBot']}] folder tidak valid, dilewati."); continue; }
        $buat = [$fm, $fb, $fg];
        $upi = trim((string) $bot['UnitUpi']); $ap = trim((string) $bot['UnitAp']); $up = trim((string) $bot['UnitUp']);
        if ($upi !== '' && $up === '') {                                     // tingkat AP / UPI: subfolder kode unit
            $peta = bh_peta_unit($conn, $upi, $ap, '');
            foreach ($peta['up'] as $u) $buat[] = $ap !== '' ? "$fm/{$u['kode']}" : "$fm/{$u['ap']}/{$u['kode']}";
            if ($ap === '') foreach ($peta['ap'] as $k) $buat[] = "$fm/$k";
        }
        $sulit = bot_buat_folder($root, array_values(array_unique($buat)));
        $n += count($buat) - count($sulit);
        bc_log($sulit ? 'WARN' : 'INFO', "[{$bot['KodeBot']}] " . (count($buat) - count($sulit)) . ' folder siap' . ($sulit ? ', gagal: ' . implode(', ', $sulit) : '.'));
    }
    bc_log('INFO', "Selesai menyiapkan folder ($n folder).");
    return 0;
}

function bc_status(PDO $conn, string $root, ?string $kode, int $stabil): int
{
    $bots = bc_ambil_bot($conn, false, $kode);
    if (!$bots) { echo "Belum ada bot di pengaturan_bot.\n"; return 0; }
    printf("%-22s %-12s %-7s %-10s %-19s %s\n", 'KodeBot', 'Jenis', 'Status', 'Terakhir', 'TerakhirJalan', 'Pesan');
    foreach ($bots as $b) {
        printf("%-22s %-12s %-7s %-10s %-19s %s\n", $b['KodeBot'], $b['JenisBot'], $b['StatusData'], $b['StatusTerakhir'], (string) $b['TerakhirJalan'], (string) $b['PesanTerakhir']);
    }
    return 0;
}

function bc_jalan(PDO $conn, string $root, bool $kering, ?string $kode, int $stabil): int
{
    $bots = bc_ambil_bot($conn, true, $kode);
    if (!$bots) { bc_log('INFO', 'Tidak ada bot berstatus AKTIF' . ($kode !== null ? " dengan kode '$kode'" : '') . '.'); return 0; }

    $kode_keluar = 0;
    foreach ($bots as $bot) {
        try {
            $r = bc_proses_bot($conn, $bot, $root, $kering, $stabil);
        } catch (Throwable $e) {
            $r = ['kerja' => true, 'status' => 'GAGAL', 'pesan' => 'Kesalahan tak terduga: ' . $e->getMessage()];
            bc_log('ERROR', "[{$bot['KodeBot']}] " . $r['pesan']);
        }
        if (!$r['kerja']) continue;                                            // tidak ada file: biarkan status lama apa adanya
        if ($r['status'] !== 'BERHASIL') $kode_keluar = 1;
        bc_log($r['status'] === 'BERHASIL' ? 'INFO' : 'WARN', "[{$bot['KodeBot']}] {$r['status']} - {$r['pesan']}");
        if (!$kering) {
            try { bc_catat($conn, (int) $bot['IdBot'], $r['status'], $r['pesan']); }
            catch (PDOException $e) { bc_log('ERROR', "[{$bot['KodeBot']}] Gagal mencatat status: {$e->getMessage()}"); }
        }
    }
    return $kode_keluar;
}

function bc_bantuan(): int
{
    echo "Pemakaian: php bot_cron.php <perintah> [opsi]\n"
       . "  siapkan                buat folder bot (+ subfolder AP/UP) untuk semua bot\n"
       . "  status                 daftar bot dan hasil putaran terakhir\n"
       . "  jalan [--kering]       unggah file di folder masuk semua bot AKTIF (--kering = hanya tampilkan)\n"
       . "Opsi: --bot=kode_bot  --stabil=detik (bawaan 60)\n";
    return 0;
}

// ============================================================
// Titik masuk
// ============================================================
$perintah = $argv[1] ?? '';
$opsi = [];
foreach (array_slice($argv, 2) as $a) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) $opsi[$m[1]] = $m[2] ?? true;
}
$kode   = isset($opsi['bot']) && is_string($opsi['bot']) && $opsi['bot'] !== '' ? $opsi['bot'] : null;
$stabil = isset($opsi['stabil']) && ctype_digit((string) $opsi['stabil']) ? (int) $opsi['stabil'] : (defined('BOT_STABIL_DETIK') ? (int) BOT_STABIL_DETIK : 60);
$kering = !empty($opsi['kering']);

if (!in_array($perintah, ['siapkan', 'status', 'jalan'], true)) exit(bc_bantuan());

[$root, $root_konfig] = bot_root();
if ($root === '') {
    $pesan = 'Folder bot tidak ditemukan: ' . str_replace('\\', '/', (string) $root_konfig) . ' (buat folder itu atau atur BOT_ROOT di config/bot.php).';
    bc_log('ERROR', $pesan);
    if ($perintah === 'jalan' && !$kering) {                                   // supaya terlihat di halaman Daftar Bot
        try { foreach (bc_ambil_bot($conn, true, $kode) as $b) bc_catat($conn, (int) $b['IdBot'], 'GAGAL', $pesan); } catch (Throwable $e) { /* abaikan */ }
    }
    exit(2);
}
@mkdir($root . '/log', 0775, true);
$GLOBALS['BC_LOG'] = is_dir($root . '/log') ? $root . '/log/bot_cron.log' : '';

try {
    if ($perintah === 'siapkan') exit(bc_siapkan($conn, $root, $kode));
    if ($perintah === 'status')  exit(bc_status($conn, $root, $kode, $stabil));

    // jalan: satu putaran pada satu waktu (kunci otomatis lepas bila proses mati mendadak)
    $kunci = null;
    if (!$kering) {
        $kunci = fopen($root . '/bot_cron.lock', 'c');
        if ($kunci === false || !flock($kunci, LOCK_EX | LOCK_NB)) {
            bc_log('INFO', 'Putaran sebelumnya masih berjalan (bot_cron.lock terkunci). Keluar.');
            exit(0);
        }
    }
    $kode_keluar = bc_jalan($conn, $root, $kering, $kode, $stabil);
    if ($kunci) { flock($kunci, LOCK_UN); fclose($kunci); }
    exit($kode_keluar);
} catch (Throwable $e) {
    bc_log('ERROR', 'Kesalahan fatal: ' . $e->getMessage());
    exit(2);
}
