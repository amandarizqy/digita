<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';

// Logika backend: Ambil data unit dari database
$stmt = $conn->query("SELECT * FROM master_up"); // atau tabel terkait
$daftar_unit = $stmt->fetchAll();

// Panggil tampilan frontend (HTML) di folder templates
require_once '../../templates/master/unit.html';
?>