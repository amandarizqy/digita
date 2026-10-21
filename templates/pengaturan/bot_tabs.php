<?php
// templates/pengaturan/_bot_tabs.php - tab perpindahan antar halaman Bot (DIL, Saldo Tunggakan, dst.)
// Pakai di atas isi tiap halaman Bot:  <?php include __DIR__ . '/_bot_tabs.php'; ?>
$menu_bot  = require __DIR__ . '/../../includes/menu_pengaturan.php';
$kode_hak  = $_SESSION['KodeHak'] ?? '';
$sub_aktif = $_GET['sub'] ?? '';
$tab_bot   = array_filter($menu_bot['Bot'] ?? [], fn($i) => in_array($kode_hak, $i['roles'], true));
?>
<ul class="nav nav-tabs mb-3">
    <?php foreach ($tab_bot as $t): ?>
        <li class="nav-item">
            <a class="nav-link <?= $sub_aktif === $t['sub'] ? 'active' : '' ?>"
               href="<?= BASE_URL ?>/index.php?page=pengaturan&sub=<?= urlencode($t['sub']) ?>">
                <?= htmlspecialchars($t['label']) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>