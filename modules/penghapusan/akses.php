<?php
// modules/penghapusan/akses.php

function pg_menu_penghapusan() {
    return [
        'input' => [
            'label' => 'Input & Proses',
            'items' => ['form_pk' => 'Buat PK', 'form_order' => 'Order Hapus', 'form_ba' => 'BA Mutasi']
        ],
        'monitoring' => [
            'label' => 'Monitoring',
            'items' => ['monitor_pk' => 'Monitoring PK & Order']
        ]
    ];
}

function pga_boleh_hapus($action) {
    return true; // Atur hak akses sesuai kebutuhan sistem login Anda
}

function pga_pertama_hapus($menu_urutan) {
    return 'monitor_pk';
}