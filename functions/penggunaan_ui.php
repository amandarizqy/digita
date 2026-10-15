<?php
// functions/penggunaan_ui.php
// Komponen tampilan bersama modul Penggunaan (pola sama dengan perencanaan_ui.php):
//   Judul + tombol  ->  Tab Level 2 (Input / Monitoring)  ->  Pill Level 3 (DIL, Saldo, ...)

function pg_e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

/** URL halaman Penggunaan lewat front controller (?page=penggunaan&action=...). */
function pg_url(string $action = '', array $params = []): string
{
    $q = ['page' => 'penggunaan'];
    if ($action !== '') $q['action'] = $action;
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') continue;
        $q[$k] = $v;
    }
    return '?' . http_build_query($q);
}

/** Input hidden penjaga rute untuk form GET. */
function pg_hidden(string $action, array $extra = []): string
{
    $h = '<input type="hidden" name="page" value="penggunaan">'
       . '<input type="hidden" name="action" value="' . pg_e($action) . '">';
    foreach ($extra as $k => $v) {
        if ($v === null || $v === '') continue;
        $h .= '<input type="hidden" name="' . pg_e($k) . '" value="' . pg_e($v) . '">';
    }
    return $h;
}

/** Struktur menu: Level 2 = grup, Level 3 = items [action => [label, icon]]. */
function pg_menu(): array
{
    return [
        'input' => [
            'label' => 'Input', 'icon' => 'bi-box-arrow-in-right', 'items' => [
                'input_dil'       => ['DIL', 'bi-journal-text'],
                'input_saldo'     => ['Saldo', 'bi-wallet2'],
                'input_pelunasan' => ['Pelunasan', 'bi-cash-coin'],
                'input_perintah'  => ['Perintah', 'bi-terminal'],
            ],
        ],
        'monitoring' => [
            'label' => 'Monitoring', 'icon' => 'bi-display', 'items' => [
                'monitor_dil'       => ['DIL', 'bi-journal-text'],
                'monitor_pelunasan' => ['Pelunasan', 'bi-cash-coin'],
                'monitor_perintah'  => ['Perintah', 'bi-terminal'],
                'monitor_status'    => ['Status Eksekusi', 'bi-activity'],
            ],
        ],
    ];
}

/** Kunci grup menu untuk sebuah action (default: input). */
function pg_group_of(string $action): string
{
    foreach (pg_menu() as $key => $grp) {
        if (isset($grp['items'][$action])) return $key;
    }
    return 'input';
}

/** Alert konsisten. */
function pg_alert(string $type, string $html, string $icon = ''): string
{
    $icon = $icon ?: (['success' => 'bi-check-circle-fill', 'danger' => 'bi-exclamation-triangle-fill'][$type] ?? 'bi-info-circle-fill');
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show border-0 shadow-sm rounded-3" role="alert">'
        . '<i class="bi ' . $icon . ' me-2"></i>' . $html
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>';
}

/**
 * Header + tab level 2 + pill level 3.
 * @param array $o title, subtitle, action, buttons: [['label','href','icon','class','attrs'], ...]
 */
function pg_header(array $o, bool $tampil_pill = true): string
{
    $action = $o['action'] ?? '';
    $grup   = pg_group_of($action);
    $menu   = pg_menu();

    $h  = '<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">';
    $h .= '<div><h4 class="fw-bold text-dark mb-1">' . pg_e($o['title'] ?? 'Modul Penggunaan') . '</h4>';
    if (!empty($o['subtitle'])) $h .= '<p class="text-muted small mb-0">' . pg_e($o['subtitle']) . '</p>';
    $h .= '</div><div class="d-flex flex-wrap gap-2 align-items-center">';
    foreach (($o['buttons'] ?? []) as $b) {
        $cls  = $b['class'] ?? 'btn btn-primary btn-sm px-3 shadow-sm rounded-2';
        $icon = !empty($b['icon']) ? '<i class="bi ' . pg_e($b['icon']) . ' me-1"></i> ' : '';
        $tag  = isset($b['href']) ? 'a' : 'button';
        $href = isset($b['href']) ? ' href="' . pg_e($b['href']) . '"' : '';
        $h   .= "<$tag$href class=\"" . pg_e($cls) . '" ' . ($b['attrs'] ?? '') . '>' . $icon . pg_e($b['label']) . "</$tag>";
    }
    $h .= '</div></div>';

    // LEVEL 2
    $h .= '<ul class="nav nav-pills bg-white p-2 rounded-3 shadow-sm mb-3 border border-light-subtle flex-wrap">';
    foreach ($menu as $key => $g) {
        $target = (string) array_key_first($g['items']);
        $aktif  = $key === $grup;
        $h .= '<li class="nav-item"><a class="nav-link py-2 px-3 fw-medium ' . ($aktif ? 'active text-white' : 'text-secondary') . '"'
            . ($aktif ? ' style="background-color:#0d6efd;"' : '') . ' href="' . pg_e(pg_url($target)) . '">'
            . '<i class="bi ' . pg_e($g['icon']) . ' me-2"></i>' . pg_e($g['label']) . '</a></li>';
    }
    $h .= '</ul>';

    // LEVEL 3
    if ($tampil_pill && !empty($menu[$grup]['items'])) {
        $h .= '<div class="d-flex align-items-center flex-wrap gap-2 mb-4">'
            . '<span class="text-secondary fw-bold text-uppercase me-2" style="font-size:.75rem;letter-spacing:.5px;">SUB MENU:</span>';
        foreach ($menu[$grup]['items'] as $k => [$label, $icon]) {
            $on = $k === $action;
            $h .= '<a href="' . pg_e(pg_url($k)) . '" class="btn btn-sm rounded-pill px-3 py-1 fw-medium '
                . ($on ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white') . '"'
                . ($on ? ' style="border-color:#0d6efd;background-color:#e7f1ff;color:#0d6efd;"' : '') . '>'
                . ($on ? '<i class="bi bi-check2 me-1"></i>' : '') . pg_e($label) . '</a>';
        }
        $h .= '</div>';
    } else {
        $h .= '<div class="mb-4"></div>';
    }
    return $h;
}