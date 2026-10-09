<?php
// includes/favicon.php
// Ikon tab untuk SEMUA halaman setelah login. Dipanggil dari includes/sidebar.php.
// Ikon dipasang lewat JavaScript ke <head>, jadi tidak perlu mengubah base.php.
// Alamat web proyek dihitung dari kedalaman skrip yang berjalan (aman untuk subfolder/alias/Windows).
$__root   = rtrim(dirname(str_replace('\\', '/', __DIR__)), '/');                       // folder proyek di disk
$__script = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
$__rel    = stripos($__script, $__root . '/') === 0 ? substr($__script, strlen($__root) + 1) : 'index.php';
$__parts  = array_values(array_filter(explode('/', str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php')), 'strlen'));
$__base   = implode('/', array_slice($__parts, 0, max(0, count($__parts) - (substr_count($__rel, '/') + 1))));
$__icon   = ($__base === '' ? '' : '/' . $__base) . '/assets/img/icon.png?v=2';
?>
<script>
(function () {
    var head = document.head;
    var link = head.querySelector('link[rel~="icon"]');
    if (!link) { link = document.createElement('link'); link.rel = 'icon'; head.appendChild(link); }
    link.type = 'image/png';
    link.href = <?= json_encode($__icon, JSON_UNESCAPED_SLASHES) ?>;
})();
</script>