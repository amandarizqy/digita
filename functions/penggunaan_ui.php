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
                'input_saldo'     => ['Saldo Tunggakan', 'bi-wallet2'],
                'input_pelunasan' => ['Pelunasan', 'bi-cash-coin'],
                'isi_pulsa'       => ['Isi Pulsa', 'bi-phone'],
                'input_perintah'  => ['Perintah', 'bi-terminal'],
            ],
        ],
        'monitoring' => [
            'label' => 'Monitoring', 'icon' => 'bi-display', 'items' => [
                'monitor_dil'       => ['DIL', 'bi-journal-text'],
                'monitor_pelunasan' => ['Pelunasan', 'bi-cash-coin'],
                'monitor_isi_pulsa' => ['Isi Pulsa', 'bi-phone'],
                'monitor_perintah'  => ['Perintah', 'bi-terminal'],
                'monitor_status_barang' => ['Status Barang', 'bi-box-seam'],
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

/** Format angka bulat gaya Indonesia (4.800). */
function pg_n($v): string
{
    return number_format((float) $v, 0, ',', '.');
}

/** Footer kartu tabel: info jumlah + paginasi. $url_fn: fn(int $hal): string */
function pg_footer_tabel(int $hal, int $limit, int $total, callable $url_fn): string
{
    $dari    = $total > 0 ? ($hal - 1) * $limit + 1 : 0;
    $sampai  = min($hal * $limit, $total);
    $halaman = max(1, (int) ceil($total / max(1, $limit)));

    $h  = '<div class="card-footer bg-white border-top-0 py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">';
    $h .= '<span class="text-muted small">Menampilkan ' . pg_n($dari) . ' - ' . pg_n($sampai) . ' dari ' . pg_n($total) . ' total data</span>';
    if ($halaman > 1) {
        $btn = function ($label, $target, $disabled = false, $active = false) use ($url_fn) {
            return '<li class="page-item' . ($disabled ? ' disabled' : '') . ($active ? ' active' : '') . '">'
                . '<a class="page-link" href="' . ($disabled ? '#' : pg_e($url_fn($target))) . '">' . $label . '</a></li>';
        };
        $h .= '<nav aria-label="Navigasi halaman"><ul class="pagination pagination-sm mb-0">';
        $h .= $btn('Sebelumnya', $hal - 1, $hal <= 1);
        $awal = max(1, $hal - 2);
        $akhir = min($halaman, $hal + 2);
        if ($awal > 1) {
            $h .= $btn('1', 1);
            if ($awal > 2) $h .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        for ($i = $awal; $i <= $akhir; $i++) $h .= $btn((string) $i, $i, false, $i === $hal);
        if ($akhir < $halaman) {
            if ($akhir < $halaman - 1) $h .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            $h .= $btn((string) $halaman, $halaman);
        }
        $h .= $btn('Berikutnya', $hal + 1, $hal >= $halaman);
        $h .= '</ul></nav>';
    }
    return $h . '</div>';
}

// ------------------------------------------------------------------
// UNIT AKUN YANG LOGIN
// ------------------------------------------------------------------
/** Unit akun dari session (cadangan: master_pengguna) + label "UPI › AP › UP". */
function pg_unit_akun(PDO $conn): array
{
    $u = ['upi' => (string) ($_SESSION['UnitUpi'] ?? ''), 'ap' => (string) ($_SESSION['UnitAp'] ?? ''), 'up' => (string) ($_SESSION['UnitUp'] ?? ''), 'label' => ''];
    try {
        if ($u['upi'] === '' && !empty($_SESSION['NamaAkun'])) {
            $st = $conn->prepare("SELECT UnitUp, UnitAp, UnitUpi FROM master_pengguna WHERE NamaAkun = ?");
            $st->execute([$_SESSION['NamaAkun']]);
            if ($r = $st->fetch(PDO::FETCH_ASSOC)) { $u['upi'] = (string) $r['UnitUpi']; $u['ap'] = (string) $r['UnitAp']; $u['up'] = (string) $r['UnitUp']; }
        }
        $nama = function (string $sql, string $kode) use ($conn) { $st = $conn->prepare($sql); $st->execute([$kode]); return $st->fetchColumn() ?: $kode; };
        if ($u['upi'] !== '') $u['label'] = $nama("SELECT COALESCE(NULLIF(SingkatanNama,''), UnitUpi) FROM master_upi WHERE UnitUpi = ?", $u['upi']);
        if ($u['ap']  !== '') $u['label'] .= ' › ' . $nama("SELECT COALESCE(NULLIF(SingkatanNama,''), UnitAp) FROM master_ap WHERE UnitAp = ?", $u['ap']);
        if ($u['up']  !== '') $u['label'] .= ' › ' . $nama("SELECT COALESCE(NULLIF(SingkatanNama,''), UnitUp) FROM master_up WHERE UnitUp = ?", $u['up']);
    } catch (PDOException $e) { /* label tetap kode */ }
    if ($u['label'] === '') $u['label'] = $u['upi'];
    return $u;
}

/**
 * Kondisi SQL wilayah akun: UPI, + AP / UP bila akun terikat. $awalan = 'Unit' (kolom UnitUpi/UnitAp/UnitUp: dpp, pelunasan_ap2t, master_barang).
 * Parameter ditambahkan ke $p (:s_upi, :s_ap, :s_up).
 */
function pg_scope(array $u, string $alias, string $awalan, array &$p): string
{
    $w = ["$alias.{$awalan}Upi = :s_upi"]; $p[':s_upi'] = $u['upi'];
    if ($u['ap'] !== '') { $w[] = "$alias.{$awalan}Ap = :s_ap"; $p[':s_ap'] = $u['ap']; }
    if ($u['up'] !== '') { $w[] = "$alias.{$awalan}Up = :s_up"; $p[':s_up'] = $u['up']; }
    return implode(' AND ', $w);
}