<?php
/**
 * config/bot.php
 * Lokasi folder utama (root) bot unggah DIGITA. Dipakai bersama oleh web (bot.php) dan cron (bot_cron.php).
 *
 * Semua Direktori Utama / Arsip di tabel bot_akses ditulis RELATIF terhadap folder ini.
 * BASE_URL tidak didefinisikan di sini (sudah didefinisikan index.php).
 *
 * Urutan pencarian (yang pertama sudah ada di disk dipakai):
 *   1. variabel lingkungan DIGITA_BOT_ROOT   (paling pasti; cocok untuk cron / server produksi)
 *   2. <htdocs>/ftp/digitas                  (dua tingkat di atas config/)
 *   3. <satu tingkat di atas htdocs>/ftp/digitas
 * Setelah lokasi fisiknya pasti, cukup sisakan satu baris dan hapus kandidat lainnya.
 */
if (!defined('BOT_ROOT')) {
    $bot_kandidat = array_values(array_filter([
        (string) getenv('DIGITA_BOT_ROOT'),
        __DIR__ . '/../../ftp/digitas',
        __DIR__ . '/../../../ftp/digitas',
    ], fn($p) => $p !== ''));

    $bot_pilih = $bot_kandidat[0];
    foreach ($bot_kandidat as $p) {
        if (is_dir($p)) { $bot_pilih = $p; break; }
    }
    define('BOT_ROOT', realpath($bot_pilih) ?: $bot_pilih);
    unset($bot_kandidat, $bot_pilih, $p);
}