<?php
// modules/penggunaan/input_dil.php  -  Input DIL
// AREA BACKEND: query SELECT/INSERT/UPDATE & proses form (POST) ditulis di sini.
// Variabel hasil dikirim ke templates/penggunaan/input_dil.php

$pesan_sukses = '';
$pesan_error  = '';
$data         = [];   // TODO: isi dari query

// if ($_SERVER['REQUEST_METHOD'] === 'POST') { ... }

include __DIR__ . '/../../templates/penggunaan/input_dil.php';