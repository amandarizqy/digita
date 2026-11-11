<?php
/**
 * config/bot.php
 * Konfigurasi direktori utama (root) untuk sistem bot unggah DIGITA.
 */

// Mengarah ke folder htdocs/ftp/digita berdasarkan posisi config/bot.php
define('BOT_ROOT', realpath(__DIR__ . '/../../../ftp/digitas') ?: __DIR__ . '/../../../ftp/digita');