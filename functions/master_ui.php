<?php
// functions/master_ui.php
// Komponen tampilan bersama modul Master Data, disamakan dengan modul Perencanaan.
// Fungsi berawalan ms_ supaya tidak bentrok dengan pr_ milik Perencanaan.

function ms_e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function ms_n($v): string
{
    return number_format((float) $v, 0, ',', '.');
}

function ms_styles(): string
{
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;
    return <<<'CSS'
<style>
  .ms-label    { font-size:.75rem; letter-spacing:.5px; }
  .ms-thead th { font-size:.75rem; letter-spacing:.3px; white-space:nowrap; }
  .ms-icon     { width:52px; height:52px; flex:0 0 52px; }
  .modal .form-label { font-size:.75rem; font-weight:700; letter-spacing:.5px; text-transform:uppercase; color:#6c757d; }

  /* Gembok & tab/pill terkunci: disamakan dengan Perencanaan */
  .bi-lock-fill { font-size:.75em !important; color:inherit !important; margin-left:.25rem; }
  .nav-pills .nav-link:has(.bi-lock-fill) { background-color:transparent !important; color:#6c757d !important; opacity:.6; }
  .btn:has(.bi-lock-fill) { background-color:#fff !important; border-color:#6c757d !important; color:#6c757d !important; opacity:.6; }
</style>
CSS;
}

function ms_tone(string $tone): array
{
    $map = [
        'primary'   => ['#0d6efd', '#e7f1ff'],
        'success'   => ['#198754', '#d1e7dd'],
        'warning'   => ['#997404', '#fff3cd'],
        'danger'    => ['#dc3545', '#f8d7da'],
        'secondary' => ['#6c757d', '#e9ecef'],
        'info'      => ['#087990', '#cff4fc'],
    ];
    return $map[$tone] ?? $map['primary'];
}

/** Skrip "Akses ditolak" (SweetAlert2), dimuat khusus di halaman Master. */
function ms_skrip_akses(): string
{
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;
    return <<<'JS'
<script>
(function () {
    if (!window.Swal) {
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
        document.head.appendChild(s);
    }
})();
function tampilkanAksesDitolak() {
    if (window.Swal) {
        Swal.fire({icon: 'error', title: 'Akses ditolak', text: 'Anda tidak memiliki wewenang untuk membuka menu ini.', confirmButtonText: 'OK', confirmButtonColor: '#0d6efd'});
    } else {
        alert('Akses ditolak: Anda tidak memiliki wewenang untuk membuka menu ini.');
    }
}
</script>
JS;
}

/**
 * Header halaman + Tab Level 2 + Pill Level 3.
 * @param array  $o     title, subtitle, tombol => ['label','target','icon']
 * @param array  $akses ['unit','pengguna','provider','modem','pesan' => bool]
 * @param string $tab   'unit' | 'pengguna' | 'pesan'
 * @param array  $pills tiap item: ['label','href','on','locked']
 */
function ms_header(array $o, array $akses, string $tab, array $pills = []): string
{
    $h = ms_styles() . ms_skrip_akses();

    // JUDUL + AKSI
    $h .= '<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3"><div>'
        . '<h4 class="fw-bold text-dark mb-1">' . ms_e($o['title'] ?? 'Modul Master Data') . '</h4>';
    if (!empty($o['subtitle'])) {
        $h .= '<p class="text-muted small mb-0">' . ms_e($o['subtitle']) . '</p>';
    }
    $h .= '</div><div class="d-flex flex-wrap gap-2 align-items-center">';
    if (!empty($o['tombol'])) {
        $t  = $o['tombol'];
        $h .= '<button type="button" class="btn btn-primary btn-sm px-3 shadow-sm rounded-2" data-bs-toggle="modal" data-bs-target="#' . ms_e($t['target']) . '">'
            . '<i class="bi ' . ms_e($t['icon'] ?? 'bi-plus-lg') . ' me-1"></i> ' . ms_e($t['label']) . '</button>';
    }
    $h .= '</div></div>';

    // LEVEL 2: TAB
    $pusat_pesan_ok = !empty($akses['provider']) || !empty($akses['pesan']) || !empty($akses['modem']);
    $tabs = [
        'unit'     => ['Unit (UI / UP3 / ULP)', 'bi-diagram-3',      !empty($akses['unit']),     'index.php?page=master&sub=unit'],
        'pengguna' => ['Pengguna',              'bi-people',         !empty($akses['pengguna']), 'index.php?page=master&sub=pengguna'],
        'pesan'    => ['Pusat Pesan',           'bi-chat-left-dots', $pusat_pesan_ok,
                       'index.php?page=master&sub=' . (!empty($akses['provider']) ? 'provider' : 'pesan')],
    ];
    $h .= '<ul class="nav nav-pills bg-white p-2 rounded-3 shadow-sm mb-3 border border-light-subtle flex-wrap">';
    foreach ($tabs as $kunci => [$label, $icon, $boleh, $url]) {
        $aktif = $kunci === $tab;
        if ($boleh) {
            $h .= '<li class="nav-item"><a class="nav-link py-2 px-3 fw-medium ' . ($aktif ? 'active text-white' : 'text-secondary') . '"'
                . ($aktif ? ' style="background-color:#0d6efd;"' : '') . ' href="' . ms_e($url) . '">'
                . '<i class="bi ' . $icon . ' me-2"></i>' . ms_e($label) . '</a></li>';
        } else {
            $h .= '<li class="nav-item"><a class="nav-link py-2 px-3 fw-medium text-secondary" aria-disabled="true" style="opacity:.6;" href="' . ms_e($url) . '"'
                . ' onclick="event.preventDefault();tampilkanAksesDitolak();return false;">'
                . '<i class="bi ' . $icon . ' me-2"></i>' . ms_e($label) . ' <i class="bi bi-lock-fill ms-1" style="font-size:.75em"></i></a></li>';
        }
    }
    $h .= '</ul>';

    // LEVEL 3: PILL SUB ENTITAS
    if ($pills) {
        $h .= '<div class="d-flex align-items-center flex-wrap gap-2 mb-4">'
            . '<span class="text-secondary fw-bold text-uppercase me-2 ms-label">SUB ENTITAS:</span>';
        foreach ($pills as $p) {
            if (!empty($p['locked'])) {
                $h .= '<a href="' . ms_e($p['href']) . '" class="btn btn-sm rounded-pill px-3 py-1 fw-medium btn-outline-secondary text-secondary bg-white"'
                    . ' aria-disabled="true" style="opacity:.6;" onclick="event.preventDefault();tampilkanAksesDitolak();return false;">'
                    . ms_e($p['label']) . ' <i class="bi bi-lock-fill ms-1" style="font-size:.75em"></i></a>';
                continue;
            }
            $on = !empty($p['on']);
            $h .= '<a href="' . ms_e($p['href']) . '" class="btn btn-sm rounded-pill px-3 py-1 fw-medium '
                . ($on ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white') . '"'
                . ($on ? ' style="border-color:#0d6efd;background-color:#e7f1ff;color:#0d6efd;"' : '') . '>'
                . ($on ? '<i class="bi bi-check2 me-1"></i>' : '') . ms_e($p['label']) . '</a>';
        }
        $h .= '</div>';
    } else {
        $h .= '<div class="mb-4"></div>';
    }
    return $h;
}

/** Kartu KPI. */
function ms_kpi(string $label, string $value_html, string $foot_html, string $icon, string $tone = 'primary',
                bool $accent = false, string $col = 'col-md-6'): string
{
    [$fg, $bg] = ms_tone($tone);
    $border = $accent ? ' border-start border-4 border-' . $tone : '';
    return '<div class="' . $col . '"><div class="card border-0 shadow-sm rounded-4 py-4 px-4 bg-white h-100' . $border . '">'
        . '<div class="d-flex justify-content-between align-items-center gap-2"><div>'
        . '<span class="text-muted fw-bold text-uppercase ms-label">' . ms_e($label) . '</span>'
        . '<h3 class="fw-bold text-dark mt-2 mb-1 text-nowrap"' . ($accent && $tone !== 'primary' ? ' style="color:' . $fg . '!important"' : '') . '>' . $value_html . '</h3>'
        . ($foot_html !== '' ? '<span class="text-muted small">' . $foot_html . '</span>' : '') . '</div>'
        . '<div class="rounded-circle d-flex align-items-center justify-content-center ms-icon" style="background-color:' . $bg . ';color:' . $fg . ';">'
        . '<i class="bi ' . ms_e($icon) . ' fs-4"></i></div></div></div></div>';
}

function ms_kpi_nilai($angka, string $satuan): string
{
    return ms_n($angka) . ' <span class="fs-6 fw-semibold text-muted">' . ms_e($satuan) . '</span>';
}

function ms_empty_row(int $colspan, string $pesan, string $icon = 'bi-inbox'): string
{
    return '<tr><td colspan="' . $colspan . '" class="text-center text-muted py-5">'
        . '<i class="bi ' . $icon . ' fs-1 d-block mb-2 text-secondary"></i>' . ms_e($pesan) . '</td></tr>';
}

function ms_badge_status($status): string
{
    return ($status ?? '') === 'AKTIF'
        ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-50 px-2 py-1 font-monospace" style="font-size:.72rem;"><i class="bi bi-check-circle me-1"></i>AKTIF</span>'
        : '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-50 px-2 py-1 font-monospace" style="font-size:.72rem;"><i class="bi bi-x-circle me-1"></i>NONAKTIF</span>';
}

function ms_badge_tabel(string $nama): string
{
    return '<span class="badge bg-light text-secondary border font-monospace py-1 px-2">Tabel: ' . ms_e($nama) . '</span>';
}

function ms_footer_tabel(int $total): string
{
    return '<div class="card-footer bg-white border-top-0 py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">'
        . '<span class="text-muted small">Menampilkan ' . ms_n($total > 0 ? 1 : 0) . ' - ' . ms_n($total)
        . ' dari ' . ms_n($total) . ' total data</span></div>';
}