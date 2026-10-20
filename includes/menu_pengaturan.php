<?php
// includes/menu_pengaturan.php
// Satu-satunya sumber data menu Pengaturan (dipakai sidebar.php dan modules/pengaturan/index.php).
// SA.KP sengaja tidak dimasukkan.
$semua  = ['MB.UI','AM.UI','AM.AP','ML.UP','TL.AP','TL.UP','SF.UI','SF.AP','SF.UP','SF.VD'];
$sf_bot = ['SF.UI','SF.AP','SF.UP']; // SF.VD tidak punya akses Bot

return [
    'Akun' => [
        ['label' => 'Update Password', 'sub' => 'update_password', 'file' => 'update_password.php', 'roles' => $semua],
    ],
    'Bot' => [
        ['label' => 'DIL',             'sub' => 'dil',             'file' => 'dil.php',             'roles' => $sf_bot],
        ['label' => 'Saldo Tunggakan', 'sub' => 'saldo_tunggakan', 'file' => 'saldo_tunggakan.php', 'roles' => $sf_bot],
        ['label' => 'Pelunasan',       'sub' => 'pelunasan',       'file' => 'pelunasan.php',       'roles' => $sf_bot],
        ['label' => 'SMS Gateway',     'sub' => 'sms_gateway',     'file' => 'sms_gateway.php',     'roles' => $sf_bot],
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