<?php 
require_once __DIR__ . '/favicon.php';   // ikon tab untuk semua halaman
require_once __DIR__ . '/../config/database.php';

// Pengaman: kalau BASE_URL belum didefinisikan di tempat lain, pakai kosong (root server)
if (!defined('BASE_URL')) {
    define('BASE_URL', '');
}

$kode_hak = $_SESSION['KodeHak'] ?? $_SESSION['Role'] ?? '';
$menus = [];

// Ambil page aktif dari URL saat ini (default: dashboard)
$current_page = $_GET['page'] ?? 'dashboard';

// Ambil jalur URI saat ini untuk deteksi halaman di luar query parameter ?page=
$request_uri = $_SERVER['REQUEST_URI'] ?? '';

if (!empty($kode_hak)) {
    // ===== Menu Pengaturan (SA.KP sengaja tidak dimasukkan) =====
$semua  = ['MB.UI','AM.UI','AM.AP','ML.UP','TL.AP','TL.UP','SF.UI','SF.AP','SF.UP','SF.VD'];
$sf_bot = ['SF.UI','SF.AP','SF.UP']; // SF.VD tidak punya akses Bot

$menu_pengaturan = [
    'Akun' => [
        ['label' => 'Update Password', 'sub' => 'update_password', 'roles' => $semua],
    ],
    'Bot' => [
        ['label' => 'DIL',             'sub' => 'dil',             'roles' => $sf_bot],
        ['label' => 'Saldo Tunggakan', 'sub' => 'saldo_tunggakan', 'roles' => $sf_bot],
        ['label' => 'Pelunasan',       'sub' => 'pelunasan',       'roles' => $sf_bot],
        ['label' => 'SMS Gateway',     'sub' => 'sms_gateway',     'roles' => $sf_bot],
    ],
    'Pesan' => [
        ['label' => 'Kotak Masuk',  'sub' => 'kotak_masuk',  'roles' => $semua],
        ['label' => 'Kotak Keluar', 'sub' => 'kotak_keluar', 'roles' => $semua],
        ['label' => 'Terkirim',     'sub' => 'terkirim',     'roles' => $semua],
    ],
    'Panduan' => [
        ['label' => 'Buku Manual Aplikasi', 'sub' => 'manual',      'roles' => $semua],
        ['label' => 'Buku Tata Kelola S41', 'sub' => 'tata_kelola', 'roles' => $semua],
    ],
];

// Filter sesuai role yang sedang login; grup kosong tidak ditampilkan
$pengaturan_tampil = [];
foreach ($menu_pengaturan as $grup => $items) {
    $boleh = array_filter($items, fn($i) => in_array($kode_hak, $i['roles'], true));
    if ($boleh) $pengaturan_tampil[$grup] = $boleh;
}
$sub_aktif = $_GET['sub'] ?? '';

    try {
        $query_menu = "SELECT m.NamaMenu, m.UrlRoute, m.Icon 
                       FROM pengaturan_menu m 
                       JOIN pengaturan_hak_akses h ON m.IdMenu = h.IdMenu 
                       WHERE h.KodeHak = :kode_hak AND m.StatusData = 'AKTIF' 
                       ORDER BY m.Urutan ASC";
        $stmt_menu = $conn->prepare($query_menu);
        $stmt_menu->bindParam(':kode_hak', $kode_hak);
        $stmt_menu->execute();
        $menus = $stmt_menu->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Abaikan error di UI
    }
}
?>
<div class="sidebar d-flex flex-column flex-shrink-0 p-3 bg-dark text-white min-vh-100" style="width: 250px;">
    <a href="<?= BASE_URL ?>/index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none ps-2">
    <span class="bg-white rounded-3 d-inline-flex align-items-center justify-content-center p-2 me-2 shadow-sm">
        <img src="<?= BASE_URL ?>/assets/img/logo1.png" alt="Logo DIGITA" height="48">
    </span>
    <span class="fs-4 fw-bold">DIGITAS</span>
    </a>
    <hr class="text-secondary">
    <div class="small text-uppercase text-muted fw-bold ps-2 mb-2" style="letter-spacing: 0.5px; font-size: 0.75rem;">Menu Utama</div>
    
    <ul class="nav nav-pills flex-column mb-auto">
        <?php foreach ($menus as $menu): ?>
            <?php 
                // 1. Ekstrak nama modul dari UrlRoute database (misal: "/modules/master/index.php" -> "master")
                $path_parts = explode('/', trim($menu['UrlRoute'], '/'));
                $module_name = $path_parts[1] ?? 'dashboard'; 
                
                // Jika URL tidak mengandung kata 'modules' (misal cuma '/index.php'), anggap sebagai dashboard
                if (strpos($menu['UrlRoute'], 'modules') === false) {
                    $module_name = 'dashboard';
                }

                // 2. Tentukan apakah menu ini sedang aktif
                $is_active = false;
                if ($current_page === $module_name) {
                    $is_active = true;
                } elseif ($module_name === 'master' && strpos($request_uri, '/modules/master/') !== false) {
                    $is_active = true;
                }

                $active_class = $is_active ? 'active bg-primary' : '';

                // 3. Tautan URL (diawali "/" supaya aman dengan BASE_URL),
                //    disesuaikan dengan role agar SF.UI dan AM.UI masuk ke sub-modul yang berhak
                if ($module_name === 'master') {
                    if ($kode_hak === 'AM.UI') {
                        $target_url = "/index.php?page=master&sub=pesan";
                    } else {
                        $target_url = "/index.php?page=master&sub=unit";
                    }
                } else {
                    $target_url = "/index.php?page=" . urlencode($module_name);
                }
            ?>
            <li class="nav-item mb-1">
                <a href="<?= BASE_URL . $target_url; ?>" class="nav-link text-white <?= $active_class ?>">
                    <i class="bi <?= htmlspecialchars($menu['Icon']) ?> me-2"></i> <?= htmlspecialchars($menu['NamaMenu']) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    
         <hr class="text-secondary">
    <div class="dropdown dropup">
        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle p-2 rounded hover-bg"
           data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">
            <i class="bi bi-person-circle fs-5 me-2"></i>
            <strong><?= htmlspecialchars($_SESSION['NamaPengguna'] ?? 'Administrator'); ?></strong>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark text-small shadow" style="min-width: 230px; max-height: 70vh; overflow-y: auto;">
            <?php if (!empty($pengaturan_tampil)): ?>
                <li><h6 class="dropdown-header text-white"><i class="bi bi-gear-fill me-1"></i> Pengaturan</h6></li>
                <?php foreach ($pengaturan_tampil as $grup => $items): ?>
                    <li><span class="dropdown-header text-uppercase small text-white-50"><?= htmlspecialchars($grup) ?></span></li>
                    <?php foreach ($items as $item): ?>
                        <?php $aktif = ($current_page === 'pengaturan' && $sub_aktif === $item['sub']); ?>
                        <li>
                            <a class="dropdown-item ps-4 <?= $aktif ? 'active' : '' ?>"
                               href="<?= BASE_URL ?>/index.php?page=pengaturan&sub=<?= urlencode($item['sub']) ?>">
                                <?= htmlspecialchars($item['label']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <li><hr class="dropdown-divider"></li>
            <?php endif; ?>
            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/modules/auth/logout.php">Keluar (Logout)</a></li>
        </ul>
    </div>
</div>