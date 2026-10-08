<?php
// tests/bootstrap.php
// Dimuat otomatis oleh PHPUnit sebelum test dijalankan (lihat phpunit.xml).
// Sengaja TIDAK memuat config/database.php supaya tidak pernah konek ke DB produksi.
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../functions/perencanaan_ui.php';   // pr_periode(), pr_alert(), dll.
require_once __DIR__ . '/../functions/risiko_helper.php';    // dipakai sebagai "pembanding" hasil SQL
