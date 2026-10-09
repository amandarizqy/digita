<?php
// functions/perencanaan_ui.php
// Komponen tampilan bersama modul Perencanaan, mengikuti pola modul Master Data & Pengadaan:
//   Judul + aksi utama  ->  Tab Level 2  ->  Pill "SUB ENTITAS" Level 3  ->  Kartu KPI  ->  Kartu tabel

require_once __DIR__ . '/prioritas_helper.php';

/** Escape singkat. */
function pr_e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * URL halaman Perencanaan lewat front controller (?page=perencanaan&action=...).
 * Nilai null / '' dibuang; nilai '0' tetap dipertahankan (mis. filter posisi_sr=0).
 */
function pr_url(string $action = '', array $params = []): string
{
    $q = ['page' => 'perencanaan'];
    if ($action !== '') {
        $q['action'] = $action;
    }
    $q['periode'] = $params['periode'] ?? pr_periode();   // pilihan periode ikut ke semua link
    unset($params['periode']);
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') {
            continue;
        }
        $q[$k] = $v;
    }
    return '?' . http_build_query($q);
}

/** Input hidden penjaga rute untuk form GET. */
function pr_hidden(string $action, array $extra = []): string
{
    $html = '<input type="hidden" name="page" value="perencanaan">'
          . '<input type="hidden" name="action" value="' . pr_e($action) . '">'
          . '<input type="hidden" name="periode" value="' . pr_e(pr_periode()) . '">';
    foreach ($extra as $k => $v) {
        if ($v === null || $v === '') {
            continue;
        }
        $html .= '<input type="hidden" name="' . pr_e($k) . '" value="' . pr_e($v) . '">';
    }
    return $html;
}

/** Jalankan query skalar; kembalikan $default bila tabel belum ada / error. */
function pr_scalar(PDO $conn, string $sql, array $params = [], $default = 0)
{
    try {
        $st = $conn->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false || $v === null ? $default : $v;
    } catch (PDOException $e) {
        return $default;
    }
}

/** Daftar tahun yang sudah ada di data (di-cache di session 2 menit). */
function pr_periode_data(): array
{
    $c = $_SESSION['pr_periode_data'] ?? null;
    if (is_array($c) && ($c['t'] ?? 0) > time() - 120) return $c['v'];
    $v = [];
    if (!empty($GLOBALS['conn'])) {
        try { $v = array_map('strval', $GLOBALS['conn']->query("SELECT DISTINCT Periode FROM kategorisasi_risiko ORDER BY Periode DESC")->fetchAll(PDO::FETCH_COLUMN)); }
        catch (Throwable $e) { $v = []; }
    }
    $_SESSION['pr_periode_data'] = ['t' => time(), 'v' => $v];
    return $v;
}

/**
 * Periode (tahun) yang sedang dipilih untuk seluruh modul Perencanaan.
 * Urutan: ?periode= di URL -> pilihan terakhir di session -> tahun terbaru di data -> tahun berjalan.
 */
function pr_periode(): string
{
    static $p = null;
    if ($p !== null) return $p;
    $sah = fn($v) => is_string($v) && preg_match('/^\d{4}$/', $v) && $v >= 1901 && $v <= 2155;

    if ($sah($_GET['periode'] ?? null))          $p = $_GET['periode'];
    elseif ($sah($_SESSION['pr_periode'] ?? null)) $p = $_SESSION['pr_periode'];
    else                                          $p = pr_periode_data()[0] ?? date('Y');
    $_SESSION['pr_periode'] = $p;
    return $p;
}

/** Dropdown pemilih periode untuk header halaman. */
function pr_periode_pilih(string $action): string
{
    $p     = pr_periode();
    $tahun = array_unique(array_merge(pr_periode_data(), [$p, date('Y')]));
    rsort($tahun);
    $h  = '<form method="GET" action="" class="d-flex align-items-center gap-2 me-1">'
        . '<input type="hidden" name="page" value="perencanaan"><input type="hidden" name="action" value="' . pr_e($action) . '">'
        . '<label for="pr_periode" class="text-secondary fw-bold text-uppercase pr-label mb-0">Periode</label>'
        . '<select name="periode" id="pr_periode" class="form-select form-select-sm bg-white fw-semibold" style="width:auto;" onchange="this.form.submit()">';
    foreach ($tahun as $y) {
        $h .= '<option value="' . pr_e($y) . '"' . ((string) $y === $p ? ' selected' : '') . '>' . pr_e($y) . '</option>';
    }
    return $h . '</select></form>';
}

/** Format angka bulat gaya Indonesia (4.800). */
function pr_n($v): string
{
    return number_format((float) $v, 0, ',', '.');
}

/** Format rupiah. */
function pr_rp($v): string
{
    return 'Rp ' . number_format((float) $v, 0, ',', '.');
}

/** Rupiah ringkas untuk KPI: Rp 3,51 M / Rp 850 jt / Rp 12.500. */
function pr_rp_ringkas($v): string
{
    $v = (float) $v;
    if ($v >= 1e12) return 'Rp ' . number_format($v / 1e12, 2, ',', '.') . ' T';
    if ($v >= 1e9)  return 'Rp ' . number_format($v / 1e9, 2, ',', '.') . ' M';
    if ($v >= 1e6)  return 'Rp ' . number_format($v / 1e6, 1, ',', '.') . ' jt';
    return pr_rp($v);
}

/** Persentase aman (hindari bagi nol). */
function pr_pct($bagian, $total, int $desimal = 0): string
{
    return $total > 0 ? number_format($bagian / $total * 100, $desimal, ',', '.') : '0';
}

// ------------------------------------------------------------------
// STRUKTUR MENU
// ------------------------------------------------------------------
function pr_menu(): array
{
    return [
        'input' => [
            'label' => 'Input Data', 'icon' => 'bi-box-arrow-in-right', 'items' => [
                'upload_riwayat'    => ['Riwayat Pelunasan', 'bi-upload'],
                'input_kepentingan' => ['Tingkat Kepentingan', 'bi-pencil-square'],
                'input_survey'      => ['Survey SR', 'bi-ui-checks'],
            ],
        ],
        'proses' => [
            'label' => 'Proses', 'icon' => 'bi-gear', 'items' => [
                'proses_risiko'    => ['Klasifikasi Level Risiko', 'bi-diagram-3'],
                'proses_prioritas' => ['Skala Prioritas', 'bi-sort-numeric-down'],
            ],
        ],
        'monitoring' => [
            'label' => 'Monitoring', 'icon' => 'bi-display', 'items' => [
                // 'data_riwayat'      => ['Riwayat Pelunasan', 'bi-clock-history'],
                'hasil_kepentingan' => ['Tingkat Kepentingan', 'bi-bar-chart-line'],
                'hasil_survey'      => ['Survey SR', 'bi-clipboard-data'],
                'hasil_risiko'      => ['Level Risiko', 'bi-shield-check'],
                'hasil_prioritas'   => ['Skala Prioritas', 'bi-list-stars'],
            ],
        ],
    ];
}

/** Kunci grup menu untuk sebuah action (default: input). */
function pr_group_of(string $action): string
{
    foreach (pr_menu() as $key => $grp) {
        if (isset($grp['items'][$action])) {
            return $key;
        }
    }
    return 'input';
}

/** CSS kecil khusus modul (dicetak sekali per halaman). */
function pr_styles(): string
{
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;
    return <<<'CSS'
<style>
  .pr-label   { font-size:.75rem; letter-spacing:.5px; }
  .pr-thead th{ font-size:.75rem; letter-spacing:.3px; white-space:nowrap; }
  .pr-icon    { width:52px; height:52px; flex:0 0 52px; }
  .pr-tile    { display:block; height:100%; padding:.85rem 1rem; background:#fff; text-decoration:none; color:inherit;
                border:1px solid #dee2e6; border-left:4px solid var(--c); border-radius:.75rem; transition:all .15s ease; cursor:pointer; }
  .pr-tile:hover { box-shadow:0 .25rem .75rem rgba(0,0,0,.08); transform:translateY(-1px); color:inherit; }
  .btn-check:checked + .pr-tile, .pr-tile.active { background:var(--bg); border-color:var(--c); box-shadow:0 0 0 1px var(--c); }
  .btn-check:disabled + .pr-tile { opacity:.45; cursor:not-allowed; transform:none; box-shadow:none; }
  .btn-check:focus-visible + .pr-tile { outline:2px solid #0d6efd; outline-offset:2px; }
  .pr-step    { width:34px; height:34px; flex:0 0 34px; }
</style>
CSS;
}

// ------------------------------------------------------------------
// HEADER + TAB LEVEL 2 + PILL LEVEL 3
// ------------------------------------------------------------------
/**
 * @param array $o  title, subtitle, action (kunci action aktif), badge (opsional, teks di sebelah judul),
 *                  buttons: array of ['label','href','icon','class','attrs']
 * @param bool  $tampil_pill  tampilkan baris "SUB ENTITAS"
 */
function pr_header(array $o, bool $tampil_pill = true): string
{
    $action = $o['action'] ?? '';
    $grup   = pr_group_of($action);
    $menu   = pr_menu();

    $h  = pr_styles();
    $nav_saja = !empty($o['nav_saja']);   // hanya tab + pill, tanpa judul (untuk halaman input lama)
    if (!$nav_saja) {
    $h .= '<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">';
    $h .= '<div><h4 class="fw-bold text-dark mb-1">' . pr_e($o['title'] ?? 'Modul Perencanaan');
    $h .= '</h4>';
    if (!empty($o['subtitle'])) {
        $h .= '<p class="text-muted small mb-0">' . pr_e($o['subtitle']) . '</p>';
    }
    $h .= '</div><div class="d-flex flex-wrap gap-2 align-items-center">';
    if (!empty($o['periode'])) {
        $h .= pr_periode_pilih($action);
    }
    foreach (($o['buttons'] ?? []) as $b) {
        $cls   = $b['class'] ?? 'btn btn-primary btn-sm px-3 shadow-sm rounded-2';
        $icon  = !empty($b['icon']) ? '<i class="bi ' . pr_e($b['icon']) . ' me-1"></i> ' : '';
        $tag   = isset($b['href']) ? 'a' : 'button';
        $href  = isset($b['href']) ? ' href="' . pr_e($b['href']) . '"' : '';
        $h    .= "<$tag$href class=\"" . pr_e($cls) . '" ' . ($b['attrs'] ?? '') . '>' . $icon . pr_e($b['label']) . "</$tag>";
    }
    $h .= '</div></div>';
    }

    // LEVEL 2
    $h .= '<ul class="nav nav-pills bg-white p-2 rounded-3 shadow-sm mb-3 border border-light-subtle flex-wrap">';
    foreach ($menu as $key => $g) {
        $target = (string) array_key_first($g['items']);
        $aktif  = $key === $grup;
        $h .= '<li class="nav-item"><a class="nav-link py-2 px-3 fw-medium ' . ($aktif ? 'active text-white' : 'text-secondary') . '"'
            . ($aktif ? ' style="background-color:#0d6efd;"' : '') . ' href="' . pr_e(pr_url($target)) . '">'
            . '<i class="bi ' . pr_e($g['icon']) . ' me-2"></i>' . pr_e($g['label']) . '</a></li>';
    }
    $h .= '</ul>';

    // LEVEL 3
    if ($tampil_pill && !empty($menu[$grup]['items'])) {
        $h .= '<div class="d-flex align-items-center flex-wrap gap-2 mb-4">'
            . '<span class="text-secondary fw-bold text-uppercase me-2 pr-label">SUB ENTITAS:</span>';
        foreach ($menu[$grup]['items'] as $k => [$label, $icon]) {
            $on = $k === $action;
            $h .= '<a href="' . pr_e(pr_url($k)) . '" class="btn btn-sm rounded-pill px-3 py-1 fw-medium '
                . ($on ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white') . '"'
                . ($on ? ' style="border-color:#0d6efd;background-color:#e7f1ff;color:#0d6efd;"' : '') . '>'
                . ($on ? '<i class="bi bi-check2 me-1"></i>' : '') . pr_e($label) . '</a>';
        }
        $h .= '</div>';
    } else {
        $h .= '<div class="mb-4"></div>';
    }
    return $h;
}

// ------------------------------------------------------------------
// KOMPONEN
// ------------------------------------------------------------------
/** Peta nada -> [warna teks/ikon, latar tint]. */
function pr_tone(string $tone): array
{
    $map = [
        'primary'   => ['#0d6efd', '#e7f1ff'],
        'success'   => ['#198754', '#d1e7dd'],
        'warning'   => ['#997404', '#fff3cd'],
        'danger'    => ['#dc3545', '#f8d7da'],
        'secondary' => ['#6c757d', '#e9ecef'],
        'info'      => ['#087990', '#cff4fc'],
        'dark'      => ['#212529', '#dee2e6'],
    ];
    return $map[$tone] ?? $map['primary'];
}

/**
 * Kartu KPI gaya master. $value_html boleh berisi markup (sudah aman dari pemanggil).
 */
function pr_kpi(string $label, string $value_html, string $foot_html, string $icon, string $tone = 'primary',
                bool $accent = false, string $col = 'col-md-6 col-xl-3'): string
{
    [$fg, $bg] = pr_tone($tone);
    $border = $accent ? ' border-start border-4 border-' . ($tone === 'warning' ? 'warning' : $tone) : '';
    return '<div class="' . $col . '"><div class="card border-0 shadow-sm rounded-4 py-4 px-4 bg-white h-100' . $border . '">'
        . '<div class="d-flex justify-content-between align-items-center gap-2"><div>'
        . '<span class="text-muted fw-bold text-uppercase pr-label">' . pr_e($label) . '</span>'
        . '<h3 class="fw-bold text-dark mt-2 mb-1 text-nowrap' . (mb_strlen(strip_tags($value_html)) > 9 ? ' fs-4' : '') . '"' . ($accent && $tone !== 'primary' ? ' style="color:' . $fg . '!important"' : '') . '>' . $value_html . '</h3>'
        . '<span class="text-muted small">' . $foot_html . '</span></div>'
        . '<div class="rounded-circle d-flex align-items-center justify-content-center pr-icon" style="background-color:' . $bg . ';color:' . $fg . ';">'
        . '<i class="bi ' . pr_e($icon) . ' fs-4"></i></div></div></div></div>';
}

/** Badge lembut umum. */
function pr_badge(string $text, string $tone = 'secondary', string $icon = ''): string
{
    $txt = $tone === 'warning' ? 'text-warning-emphasis' : 'text-' . $tone;
    return '<span class="badge bg-' . $tone . ' bg-opacity-10 ' . $txt . ' border border-' . $tone . ' border-opacity-50 px-2 py-1 font-monospace" style="font-size:.72rem;">'
        . ($icon ? '<i class="bi ' . pr_e($icon) . ' me-1"></i>' : '') . pr_e($text) . '</span>';
}

/** Badge Skala Prioritas (1-9). */
function pr_badge_prioritas($n, bool $panjang = true): string
{
    $n = (int) $n;
    if ($n < 1) {
        return pr_badge('-', 'secondary');
    }
    return pr_badge(($panjang ? 'PRIORITAS ' : 'P') . $n, warnaSkalaPrioritas($n));
}

/** Badge level (RENDAH / MODERAT / TINGGI / SANGAT ... / kemungkinan). */
function pr_badge_level($v): string
{
    $v = strtoupper(trim((string) $v));
    if ($v === '') {
        return '<span class="text-muted">-</span>';
    }
    if (in_array($v, ['RENDAH', 'SANGAT RENDAH', 'SANGAT JARANG TERJADI'], true)) {
        return pr_badge($v, 'success');
    }
    if (in_array($v, ['MODERAT', 'BISA TERJADI'], true)) {
        return pr_badge($v, 'warning');
    }
    if (in_array($v, ['TINGGI', 'SANGAT TINGGI', 'SANGAT MUNGKIN TERJADI'], true)) {
        return pr_badge($v, 'danger');
    }
    return pr_badge($v, 'secondary');
}

/** Badge Posisi SR: 0 mandiri, 1 tergantung, kosong belum diisi. */
function pr_badge_sr($v): string
{
    if ($v === null || trim((string) $v) === '') {
        return pr_badge('BELUM DIISI', 'warning', 'bi-exclamation-triangle');
    }
    return (string) $v === '1'
        ? pr_badge('TERGANTUNG', 'info', 'bi-diagram-2')
        : pr_badge('MANDIRI', 'secondary', 'bi-dash-circle');
}

/** Alert konsisten. */
function pr_alert(string $type, string $html, string $icon = ''): string
{
    $icon = $icon ?: ['success' => 'bi-check-circle-fill', 'danger' => 'bi-exclamation-triangle-fill',
                      'warning' => 'bi-database-exclamation'][$type] ?? 'bi-info-circle-fill';
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show border-0 shadow-sm rounded-3" role="alert">'
        . '<i class="bi ' . $icon . ' me-2"></i>' . $html
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>';
}

/** Baris kosong tabel. */
function pr_empty_row(int $colspan, string $pesan_html, string $icon = 'bi-inbox'): string
{
    return '<tr><td colspan="' . $colspan . '" class="text-center text-muted py-5">'
        . '<i class="bi ' . $icon . ' fs-1 d-block mb-2 text-secondary"></i>' . $pesan_html . '</td></tr>';
}

/**
 * Footer kartu tabel: info jumlah + paginasi.
 * @param callable $url_fn  fn(int $hal): string
 */
function pr_footer_tabel(int $hal, int $limit, int $total, callable $url_fn): string
{
    $dari    = $total > 0 ? ($hal - 1) * $limit + 1 : 0;
    $sampai  = min($hal * $limit, $total);
    $halaman = max(1, (int) ceil($total / max(1, $limit)));

    $h  = '<div class="card-footer bg-white border-top-0 py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">';
    $h .= '<span class="text-muted small">Menampilkan ' . pr_n($dari) . ' - ' . pr_n($sampai)
        . ' dari ' . pr_n($total) . ' total data</span>';

    if ($halaman > 1) {
        $btn = function ($label, $target, $disabled = false, $active = false) use ($url_fn) {
            return '<li class="page-item' . ($disabled ? ' disabled' : '') . ($active ? ' active' : '') . '">'
                . '<a class="page-link" href="' . ($disabled ? '#' : pr_e($url_fn($target))) . '">' . $label . '</a></li>';
        };
        $h .= '<nav aria-label="Navigasi halaman"><ul class="pagination pagination-sm mb-0">';
        $h .= $btn('Sebelumnya', $hal - 1, $hal <= 1);
        $awal  = max(1, $hal - 2);
        $akhir = min($halaman, $hal + 2);
        if ($awal > 1) {
            $h .= $btn('1', 1);
            if ($awal > 2) {
                $h .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
        }
        for ($i = $awal; $i <= $akhir; $i++) {
            $h .= $btn((string) $i, $i, false, $i === $hal);
        }
        if ($akhir < $halaman) {
            if ($akhir < $halaman - 1) {
                $h .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
            $h .= $btn((string) $halaman, $halaman);
        }
        $h .= $btn('Selanjutnya', $hal + 1, $hal >= $halaman);
        $h .= '</ul></nav>';
    }
    return $h . '</div>';
}

/** Pill filter (baris di bawah KPI, gaya SUB ENTITAS). $items: [ [label, href, active, count|null], ... ] */
function pr_pills(string $judul, array $items): string
{
    $h = '<div class="d-flex align-items-center flex-wrap gap-2 mb-3">'
       . '<span class="text-secondary fw-bold text-uppercase me-2 pr-label">' . pr_e($judul) . '</span>';
    foreach ($items as [$label, $href, $on, $count]) {
        $h .= '<a href="' . pr_e($href) . '" class="btn btn-sm rounded-pill px-3 py-1 fw-medium '
            . ($on ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white') . '"'
            . ($on ? ' style="border-color:#0d6efd;background-color:#e7f1ff;color:#0d6efd;"' : '') . '>'
            . ($on ? '<i class="bi bi-check2 me-1"></i>' : '') . pr_e($label)
            . ($count !== null ? ' <span class="opacity-75">(' . pr_n((int) $count) . ')</span>' : '') . '</a>';
    }
    return $h . '</div>';
}

// ------------------------------------------------------------------
// GRID SKALA PRIORITAS 3 x 3 (matriks Kemungkinan x Dampak)
// ------------------------------------------------------------------
/**
 * @param array  $data      [n => ['count'=>int, 'note'=>html, 'href'=>string, 'disabled'=>bool]]
 * @param string $mode      'radio' (form) | 'link' (filter)
 * @param int    $terpilih  skala aktif
 */
function pr_skala_grid(array $data, string $mode = 'link', int $terpilih = 0): string
{
    $baris = [
        'SANGAT MUNGKIN TERJADI' => [7, 8, 9],
        'BISA TERJADI'           => [4, 5, 6],
        'SANGAT JARANG TERJADI'  => [1, 2, 3],
    ];
    $tint = ['danger' => ['#dc3545', '#fdf0f1'], 'warning' => ['#e0a800', '#fff9e6'], 'success' => ['#198754', '#eef7f2']];

    $h = '<div class="row g-2 d-none d-md-flex mb-1"><div class="col-md-3"></div>';
    foreach (['DAMPAK SANGAT RENDAH', 'DAMPAK MODERAT', 'DAMPAK SANGAT TINGGI'] as $d) {
        $h .= '<div class="col-md-3"><span class="text-secondary fw-bold pr-label">' . $d . '</span></div>';
    }
    $h .= '</div>';

    foreach ($baris as $label => $skalaList) {
        $h .= '<div class="row g-2 align-items-stretch mb-2"><div class="col-12 col-md-3 d-flex align-items-center">'
            . '<span class="text-secondary fw-bold pr-label">' . $label . '</span></div>';
        foreach ($skalaList as $n) {
            $d      = $data[$n] ?? ['count' => 0, 'note' => '', 'href' => '#', 'disabled' => true];
            $tone   = warnaSkalaPrioritas($n);
            [$c, $bg] = $tint[$tone];
            $style  = '--c:' . $c . ';--bg:' . $bg . ';';
            $isi    = '<div class="d-flex justify-content-between align-items-baseline">'
                    . '<span class="fw-bold text-dark fs-5">Prioritas ' . $n . '</span>'
                    . '<span class="fw-semibold text-dark">' . pr_n((int) $d['count']) . ' <span class="text-muted small fw-normal">pelanggan</span></span></div>'
                    . '<div class="small mt-1">' . ($d['note'] ?? '') . '</div>';
            $h .= '<div class="col-12 col-md-3">';
            if ($mode === 'radio') {
                $dis = !empty($d['disabled']) ? ' disabled' : '';
                $chk = $terpilih === $n ? ' checked' : '';
                $h .= '<input type="radio" class="btn-check" name="skala_prioritas" id="skala_' . $n . '" value="' . $n . '" autocomplete="off"' . $chk . $dis . '>'
                    . '<label class="pr-tile" for="skala_' . $n . '" style="' . $style . '">' . $isi . '</label>';
            } else {
                $h .= '<a class="pr-tile' . ($terpilih === $n ? ' active' : '') . '" href="' . pr_e($d['href'] ?? '#') . '" style="' . $style . '">' . $isi . '</a>';
            }
            $h .= '</div>';
        }
        $h .= '</div>';
    }
    return $h;
}
