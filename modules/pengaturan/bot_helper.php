<?php
// modules/pengaturan/bot_helper.php
// Fungsi bersama: halaman Daftar Bot (bot.php, web) dan pelaksana cron (bot_cron.php, CLI).

if (!defined('BOT_ROOT') && is_file(__DIR__ . '/../../config/bot.php')) {
    require_once __DIR__ . '/../../config/bot.php';
}

if (!function_exists('bot_jenis_daftar')) {

    /**
     * Jenis bot = nilai ENUM kolom bot_akses.JenisBot.
     */
    function bot_jenis_daftar(): array
    {
        return [
            'PELUNASAN' => [
                'label'  => 'Upload Pelunasan (Riwayat)',
                'tabel'  => 'pelunasan_ap2t (+ dil, kategorisasi_risiko)',
                'format' => 'Kolom: Idpel, Bulan (YYYYMM), TglTransaksi, RpBK, RpTagihan.',
                'sumber' => 'perencanaan/upload_riwayat.php',
            ],
            'DIL' => [
                'label'  => 'Upload DIL',
                'tabel'  => 'dil',
                'format' => 'Excel "Monitoring DIL Detail": IDPEL, NAMA, ALAMAT, TARIF, DAYA, UNITUP, UNITAP, UNITUPI, dst.',
                'sumber' => 'penggunaan/input_dil.php',
            ],
            'TUL' => [
                'label'  => 'Upload TUL VI-01',
                'tabel'  => 'tul_601',
                'format' => 'Laporan AP2T "TUL VI-01 belum lunas": Nomer Tul VI-01, Tgl Pencetakan, Id Pelanggan, Bulan Rekening Awal/Akhir, Jumlah Lembar, Rp. Tagihan.',
                'sumber' => 'penggunaan/upload_tul.php',
            ],
            'SURVEY' => [
                'label'  => 'Upload Survey SR',
                'tabel'  => 'kategorisasi_risiko (PosisiSR)',
                'format' => 'Kolom: Idpel, PosisiSR (0/1), Periode (tahun; opsional).',
                'sumber' => 'perencanaan/input_survey.php',
            ],
            'KEPENTINGAN' => [
                'label'  => 'Upload Tingkat Kepentingan',
                'tabel'  => 'kategorisasi_risiko (LevelKepentingan)',
                'format' => 'Kolom: Idpel, LevelKepentingan (RENDAH/MODERAT/TINGGI), Periode (tahun; opsional).',
                'sumber' => 'perencanaan/input_kepentingan.php',
            ],
        ];
    }

    /** Folder bot di server: htdocs/ftp/digitas (atau isi BOT_ROOT bila ingin lokasi lain). */
    function bot_root(): array
    {
        clearstatcache(true);
        $dasar = defined('BOT_ROOT') ? BOT_ROOT : __DIR__ . '/../../../../ftp/digitas';
        $nyata = realpath($dasar);
        return [$nyata !== false ? str_replace('\\', '/', $nyata) : '', $dasar];
    }

    /** Rapikan folder relatif. null = tidak valid. */
    function bot_norm_folder(string $s): ?string
    {
        $s = trim(str_replace('\\', '/', $s), " \t\n\r\0\x0B/");
        if ($s === '' || strlen($s) > 255) return null;
        return preg_match('~^[A-Za-z0-9_\-]+(?:/[A-Za-z0-9_\-]+)*$~', $s) ? $s : null;
    }

    /** true bila $anak sama dengan atau berada di dalam $induk. */
    function bot_di_dalam(string $anak, string $induk): bool
    {
        $a = strtolower($anak);
        $i = strtolower($induk);
        return $a === $i || strpos($a, $i . '/') === 0;
    }

    /** Buat folder bila belum ada (hanya di dalam folder bot). */
    function bot_buat_folder(string $root, array $relatif): array
    {
        if (!is_dir($root)) return $relatif;
        $gagal = [];
        foreach ($relatif as $r) {
            $abs = $root . '/' . $r;
            if (!is_dir($abs) && !@mkdir($abs, 0775, true) && !is_dir($abs)) { $gagal[] = $r; continue; }
            $nyata = realpath($abs);
            if ($nyata === false || strpos(str_replace('\\', '/', $nyata) . '/', $root . '/') !== 0) $gagal[] = $r;
        }
        return $gagal;
    }
}