<?php
// tests/koneksi_test.php
// Koneksi KHUSUS pengujian. Nama database dikunci ke "digita_test" supaya
// test (yang menjalankan TRUNCATE/DELETE/UPSERT) tidak bisa menyentuh data produksi.
$host = getenv('DB_TEST_HOST') ?: 'localhost';
$user = getenv('DB_TEST_USER') ?: 'root';
$pass = getenv('DB_TEST_PASS') ?: '';
$name = 'digita_db';

return new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
