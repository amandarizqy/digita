<?php
// includes/menu_pengaturan.php
// Satu-satunya sumber data menu Pengaturan (dipakai sidebar.php dan modules/pengaturan/index.php).
$semua  = ['MB.UI','AM.UI','AM.AP','ML.UP','TL.AP','TL.UP','SF.UI','SF.AP','SF.UP','SF.VD','SA.KP'];
$sf_bot = ['SF.UI','SF.AP','SF.UP','SA.KP']; // SF.VD tidak punya akses Bot

return [
    'Akun' => [
        ['label' => 'Update Password', 'sub' => 'update_password', 'file' => 'update_password.php', 'roles' => $semua],
    ],
    'Bot' => [
        // Satu halaman untuk menambah, mengubah, dan menjalankan bot
        ['label' => 'Daftar Bot', 'sub' => 'bot', 'file' => 'bot.php', 'roles' => $sf_bot],
    ],
    'Pesan' => [
        ['label' => 'Kotak Masuk',  'sub' => 'kotak_masuk',  'file' => 'kotak_masuk.php',  'roles' => $semua],
        ['label' => 'Kotak Keluar', 'sub' => 'kotak_keluar', 'file' => 'kotak_keluar.php', 'roles' => $semua],
        ['label' => 'Terkirim',     'sub' => 'terkirim',     'file' => 'terkirim.php',     'roles' => $semua],
    ],
    'Panduan' => [
        ['label' => 'Buku Manual Aplikasi', 'sub' => 'manual',      'file' => 'manual.php',      'roles' => $semua],
        ['label' => 'Buku Tata Kelola S41', 'sub' => 'tata_kelola', 'file' => 'tata_kelola.php', 'roles' => $semua],
    ],
];