<?php
// 1. Panggil middleware pelindung URL (RBAC)
require_once '../../includes/auth_check.php';

// 2. Atur judul halaman
$page_title = "Formulir Pembelian S41 - Digita";

// 3. Tangkap output tampilan (view) ke dalam variabel $content
ob_start();
require_once '../../templates/pengadaan/pembelian.php'; 
$content = ob_get_clean();

// 4. Render ke dalam layout utama
require_once '../../templates/layouts/base.php';
?>