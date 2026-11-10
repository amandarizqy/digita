<?php
/**
 * modules/pengaturan/bot_cron.php - Pelaksana cron bot_akses.
 * Membaca daftar bot dari tabel bot_akses.
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

function bc_log(string $level, string $pesan): void
{
    $baris = date('Y-m-d H:i:s') . " [$level] $pesan";
    echo $baris . PHP_EOL;
    $berkas = $GLOBALS['BC_LOG'] ?? '';
    if ($berkas === '') return;
    if (@filesize($berkas) > 2000000) {
        for ($i = 4; $i >= 1; $i--) if (is_file("$berkas.$i")) @rename("$berkas.$i", "$berkas." . ($i + 1));
        @rename($berkas, "$berkas.1");
    }
    @file_put_contents($berkas, $baris . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function bc_subfolder(string $dir): array
{
    $hasil = [];
    foreach (@scandir($dir) ?: [] as $n) {
        if ($n === '.' || $n === '..' || !is_dir("$dir/$n")) continue;
        $hasil[$n] = "$dir/$n";
    }
    return $hasil;
}

function bc_file_di(string $dir, int $stabil): array
{
    $siap = $tunggu = [];
    $sekarang = time();
    foreach (@scandir($dir) ?: [] as $n) {
        $p = "$dir/$n";
        if (!is_file($p) || $n[0] === '.' || strncmp($n, '~$', 2) === 0) continue;
        if (strtolower(pathinfo($n, PATHINFO_EXTENSION)) !== 'xlsx') continue;
        if ($sekarang - (int) @filemtime($p) < $stabil) { $tunggu[] = $n; continue; }
        $siap[] = $p;
    }
    return ['siap' => $siap, 'belum_stabil' => $tunggu];
}

function bc_cari_tugas(PDO $conn, array $bot, string $dir_masuk, int $stabil): array
{
    $upi = trim((string) $bot['UnitUpi']); 
    $ap_b = trim((string) $bot['UnitAp']); 
    $up_b = trim((string) $bot['UnitUp']);
    $jenis = $bot['JenisBot'];
    $peta = bh_peta_unit($conn, $upi, $ap_b, $up_b);

    $tugas = $salah = $tunggu = [];
    $ambil = function (string $dir, string $ap, string $up) use (&$tugas, &$salah, &$tunggu, $stabil, $jenis) {
        $r = bc_file_di($dir, $stabil);
        foreach ($r['belum_stabil'] as $n) $tunggu[] = $n;
        foreach ($r['siap'] as $f) {
            if ($jenis !== 'DIL' && ($ap === '' || $up === '')) { $salah[] = $f; continue; }
            $tugas[] = ['file' => $f, 'ap' => $ap, 'up' => $up];
        }
    };
    $up_valid = fn(string $kode, string $ap) => ($e = $peta['up'][strtoupper($kode)] ?? null) && ($ap === '' || strcasecmp($e['ap'], $ap) === 0) ? $e : null;

    if ($up_b !== '' && $ap_b !== '') {
        $ambil($dir_masuk, $ap_b, $up_b);
    } elseif ($ap_b !== '') {
        $ap_kanon = $peta['ap'][strtoupper($ap_b)] ?? $ap_b;
        $ambil($dir_masuk, $ap_kanon, '');
        foreach (bc_subfolder($dir_masuk) as $n => $p) {
            if ($e = $up_valid($n, $ap_kanon)) $ambil($p, $ap_kanon, $e['kode']);
        }
    } else {
        $ambil($dir_masuk, '', '');
        foreach (bc_subfolder($dir_masuk) as $n => $p) {
            if (!isset($peta['ap'][strtoupper($n)])) continue;
            $ap = $peta['ap'][strtoupper($n)];
            $ambil($p, $ap, '');
            foreach (bc_subfolder($p) as $n2 => $p2) {
                if ($e = $up_valid($n2, $ap)) $ambil($p2, $ap, $e['kode']);
            }
        }
    }
    return [$tugas, $salah, $tunggu];
}

function bc_pindah(string $file, string $root, string $folder_rel, string $ap, string $up, string $catatan = ''): string
{
    $dir = $root . '/' . $folder_rel . '/' . date('Y-m-d') . ($ap !== '' ? "/$ap" : '') . ($up !== '' ? "/$up" : '');
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException("Tidak bisa membuat folder $dir");
    $tujuan = $dir . '/' . date('His') . '_' . basename($file);
    if (!@rename($file, $tujuan)) {
        if (!@copy($file, $tujuan)) throw new RuntimeException("Tidak bisa memindahkan file ke $tujuan");
        @unlink($file);
    }
    if ($catatan !== '') @file_put_contents($tujuan . '.txt', $catatan);
    return $tujuan;
}

function bc_catatan(string $nama, array $hasil): string
{
    $t = "File    : $nama\r\nWaktu   : " . date('Y-m-d H:i:s') . "\r\nHasil   : " . $hasil['pesan'] . "\r\n";
    if ($hasil['detail']) $t .= "\r\nBaris ditolak (maksimal 50 contoh):\r\n- " . implode("\r\n- ", $hasil['detail']) . "\r\n";
    return $t;
}

function bc_proses_bot(PDO $conn, array $bot, string $root, bool $kering, int $stabil): array
{
    $gagal = fn(string $p) => ['kerja' => true, 'status' => 'GAGAL', 'pesan' => $p];
    $jenis_daftar = bot_jenis_daftar();
    $jenis = (string) $bot['JenisBot'];

    if (!isset($jenis_daftar[$jenis])) return $gagal("Jenis bot '$jenis' tidak dikenal.");
    if (trim((string) $bot['UnitUpi']) === '') return $gagal('Bot belum punya Unit UPI.');

    $fm = bot_norm_folder((string) $bot['DirektoriUtama']);
    $fa = bot_norm_folder((string) $bot['DirektoriArsip']);
    if ($fm === null || $fa === null) return $gagal('DirektoriUtama / DirektoriArsip tidak valid.');
    
    if (!$kering && ($sulit = bot_buat_folder($root, [$fm, $fa]))) return $gagal('Folder tidak bisa dibuat: ' . implode(', ', $sulit));
    $dir_masuk = $root . '/' . $fm;
    if (!is_dir($dir_masuk)) return $kering ? ['kerja' => false, 'status' => 'BERHASIL', 'pesan' => ''] : $gagal("Direktori utama tidak ada: $fm");

    [$tugas, $salah, $tunggu] = bc_cari_tugas($conn, $bot, $dir_masuk, $stabil);
    if (!$tugas && !$salah) return ['kerja' => false, 'status' => 'BERHASIL', 'pesan' => ''];

    if ($kering) {
        foreach ($tugas as $t) bc_log('INFO', "[KERING] [Bot #{$bot['IdBot']}] " . basename($t['file']));
        return ['kerja' => false, 'status' => 'BERHASIL', 'pesan' => ''];
    }

    foreach ($tugas as $t) {
        $nama = basename($t['file']);
        bc_log('INFO', "[Bot #{$bot['IdBot']}] Mengunggah: $nama");
        $h = bh_jalankan($conn, $bot, $jenis, $t['file'], $t['ap'], $t['up']);

        if ($h['status'] === 'sukses' || $h['status'] === 'gagal') {
            try {
                $sub_arsip = $fa . ($h['status'] === 'sukses' ? '/berhasil' : '/gagal');
                $tujuan = bc_pindah($t['file'], $root, $sub_arsip, $t['ap'], $t['up'], bc_catatan($nama, $h));
                bc_log('INFO', "  SELESAI -> $tujuan | {$h['pesan']}");
            } catch (Throwable $e) {
                bc_log('ERROR', "  Gagal memindahkan file: {$e->getMessage()}");
            }
        }
    }

    return ['kerja' => true, 'status' => 'BERHASIL', 'pesan' => 'Proses selesai'];
}

function bc_ambil_bot(PDO $conn, bool $hanya_aktif, ?int $id_bot): array
{
    $w = []; $p = [];
    if ($hanya_aktif) $w[] = "StatusData = 'AKTIF'";
    if ($id_bot !== null) { $w[] = 'IdBot = ?'; $p[] = $id_bot; }
    $st = $conn->prepare('SELECT * FROM bot_akses' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY IdBot');
    $st->execute($p);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function bc_jalan(PDO $conn, string $root, bool $kering, ?int $id_bot, int $stabil): int
{
    $bots = bc_ambil_bot($conn, true, $id_bot);
    if (!$bots) { bc_log('INFO', 'Tidak ada bot berstatus AKTIF.'); return 0; }

    foreach ($bots as $bot) {
        try {
            bc_proses_bot($conn, $bot, $root, $kering, $stabil);
            $conn->prepare("UPDATE bot_akses SET WaktuData = NOW() WHERE IdBot = ?")->execute([$bot['IdBot']]);
        } catch (Throwable $e) {
            bc_log('ERROR', "[Bot #{$bot['IdBot']}] " . $e->getMessage());
        }
    }
    return 0;
}

$perintah = $argv[1] ?? 'jalan';
[$root]   = bot_root();
bc_jalan($conn, $root, false, null, 60);