<?php
// Tampilkan error jika ada masalah file
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load Komponen Tampilan Atas (Header & CSS)
if (file_exists('includes/header.php')) {
    include_once 'includes/header.php';
}

// Load Navigasi Atas (Navbar)
if (file_exists('includes/navbar.php')) {
    include_once 'includes/navbar.php';
}

// Load Menu Samping (Sidebar)
if (file_exists('includes/sidebar.php')) {
    include_once 'includes/sidebar.php';
}
?>

<!-- Area Konten Utama -->
<div class="content-wrapper p-4">
    <div class="container-fluid">
        <h2>Selamat Datang di Sistem DIGITA</h2>
        <p>Silakan pilih menu di samping untuk mengelola modul.</p>
        
        <?php
        // Muat isi modul master jika ada
        if (file_exists('modules/master/index.php')) {
            include_once 'modules/master/index.php';
        }
        ?>
    </div>
</div>

<?php
// Load Komponen Tampilan Bawah (Footer & JS)
if (file_exists('includes/footer.php')) {
    include_once 'includes/footer.php';
}
?>