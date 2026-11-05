<?php
// functions/penghapusan_ui.php
// Komponen tampilan modul Penghapusan, disamakan dengan modul Master (functions/master_ui.php):
// judul + tombol aksi di kanan atas, tab level 2, pill level 3, kartu tabel dengan header/footer yang sama.

require_once __DIR__ . '/penghapusan_helper.php';
require_once __DIR__ . '/master_ui.php';   // gembok, pop-up "Akses ditolak" (SweetAlert2) dan konfirmasi, sama dengan Master

function pgh_ui_styles(): string
{
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;
    return <<<'CSS'
<style>
  .ph-label    { font-size:.75rem; letter-spacing:.5px; }
  .ph-thead th { font-size:.75rem; letter-spacing:.3px; white-space:nowrap; }
  .ph-table td { font-size:.875rem; }
  .modal .form-label { font-size:.75rem; font-weight:700; letter-spacing:.5px; text-transform:uppercase; color:#6c757d; }
</style>
CSS;
}

/**
 * Header halaman + tab (Eksekusi / Monitoring) + pill sub entitas.
 * @param array  $o         title, subtitle, tombol => daftar tombol di kanan atas:
 *                          ['type'=>'modal','label','icon','target','class'] atau ['type'=>'link','label','icon','href','class']
 * @param array  $struktur  menu => ['label','icon','sub'=>[kunci => [label, icon, file, terkunci(bool)]]]
 * @param string $menu      menu aktif
 * @param string $sub       sub aktif
 */
function pgh_header(array $o, array $struktur, string $menu, string $sub): string
{
    $h = ms_styles() . pgh_ui_styles() . ms_skrip_akses();

    // JUDUL + AKSI (kanan)
    $h .= '<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3"><div>'
        . '<h4 class="fw-bold text-dark mb-1">' . pgh_e($o['title'] ?? 'Modul Penghapusan S41') . '</h4>';
    if (!empty($o['subtitle'])) {
        $h .= '<p class="text-muted small mb-0">' . pgh_e($o['subtitle']) . '</p>';
    }
    $h .= '</div><div class="d-flex flex-wrap gap-2 align-items-center">';
    foreach ($o['tombol'] ?? [] as $t) {
        $kelas = 'btn ' . ($t['class'] ?? 'btn-primary') . ' btn-sm px-3 shadow-sm rounded-2';
        $ikon  = '<i class="bi ' . pgh_e($t['icon'] ?? 'bi-plus-lg') . ' me-1"></i> ' . pgh_e($t['label']);
        if (($t['type'] ?? 'modal') === 'link') {
            $h .= '<a href="' . pgh_e($t['href']) . '" target="_blank" rel="noopener" class="' . $kelas . '">' . $ikon . '</a>';
        } else {
            $h .= '<button type="button" class="' . $kelas . '" data-bs-toggle="modal" data-bs-target="#' . pgh_e($t['target']) . '">' . $ikon . '</button>';
        }
    }
    $h .= '</div></div>';

    // LEVEL 2: TAB (tab yang seluruh sub-menunya tidak boleh diakses tampil terkunci)
    $h .= '<ul class="nav nav-pills bg-white p-2 rounded-3 shadow-sm mb-3 border border-light-subtle flex-wrap">';
    foreach ($struktur as $kunci => $m) {
        $awal = '';
        foreach ($m['sub'] as $ks => $info) {
            if (empty($info[3])) { $awal = (string) $ks; break; }
        }
        $terkunci = ($awal === '');
        if ($terkunci) {
            $awal = (string) array_key_first($m['sub']);
        }
        $url   = 'index.php?page=penghapusan&menu=' . pgh_e($kunci) . '&sub=' . pgh_e($awal);
        $aktif = ($kunci === $menu);
        if ($terkunci) {
            $h .= '<li class="nav-item"><a class="nav-link py-2 px-3 fw-medium text-secondary" aria-disabled="true" style="opacity:.6;" href="' . $url . '"'
                . ' onclick="event.preventDefault();tampilkanAksesDitolak();return false;">'
                . '<i class="bi ' . pgh_e($m['icon']) . ' me-2"></i>' . pgh_e($m['label']) . ' <i class="bi bi-lock-fill ms-1" style="font-size:.75em"></i></a></li>';
        } else {
            $h .= '<li class="nav-item"><a class="nav-link py-2 px-3 fw-medium ' . ($aktif ? 'active text-white' : 'text-secondary') . '"'
                . ($aktif ? ' style="background-color:#0d6efd;"' : '') . ' href="' . $url . '">'
                . '<i class="bi ' . pgh_e($m['icon']) . ' me-2"></i>' . pgh_e($m['label']) . '</a></li>';
        }
    }
    $h .= '</ul>';

    // LEVEL 3: PILL SUB ENTITAS (yang tidak boleh diakses terkunci)
    $h .= '<div class="d-flex align-items-center flex-wrap gap-2 mb-4">'
        . '<span class="text-secondary fw-bold text-uppercase me-2 ph-label">SUB ENTITAS:</span>';
    foreach ($struktur[$menu]['sub'] as $kunci_sub => $info) {
        $url = 'index.php?page=penghapusan&menu=' . pgh_e($menu) . '&sub=' . pgh_e($kunci_sub);
        if (!empty($info[3])) {
            $h .= '<a href="' . $url . '" class="btn btn-sm rounded-pill px-3 py-1 fw-medium btn-outline-secondary text-secondary bg-white"'
                . ' aria-disabled="true" style="opacity:.6;" onclick="event.preventDefault();tampilkanAksesDitolak();return false;">'
                . pgh_e($info[0]) . ' <i class="bi bi-lock-fill ms-1" style="font-size:.75em"></i></a>';
            continue;
        }
        $on = ($kunci_sub === $sub);
        $h .= '<a href="' . $url . '" '
            . 'class="btn btn-sm rounded-pill px-3 py-1 fw-medium ' . ($on ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white') . '"'
            . ($on ? ' style="border-color:#0d6efd;background-color:#e7f1ff;color:#0d6efd;"' : '') . '>'
            . ($on ? '<i class="bi bi-check2 me-1"></i>' : '') . pgh_e($info[0]) . '</a>';
    }
    return $h . '</div>';
}

/** Header kartu tabel: judul di kiri, kotak cari di kanan (sama seperti kartu Master). */
function pgh_card_header(string $judul, string $sub, string $q): string
{
    return '<div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">'
        . '<div class="d-flex align-items-center gap-2"><span class="fw-bold text-primary fs-6">' . pgh_e($judul) . '</span></div>'
        . pgh_kotak_cari($sub, $q) . '</div>';
}
