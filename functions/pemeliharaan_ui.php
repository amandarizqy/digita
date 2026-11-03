<?php
// functions/pemeliharaan_ui.php
// Komponen tampilan modul Pemeliharaan, disamakan dengan modul Master (functions/master_ui.php):
// judul + tombol aksi di kanan atas, tab level 2, pill level 3, kartu tabel dengan header/footer yang sama.

require_once __DIR__ . '/pemeliharaan_helper.php';
require_once __DIR__ . '/master_ui.php';   // gembok, pop-up "Akses ditolak" (SweetAlert2) dan konfirmasi, sama dengan Master

function ph_ui_styles(): string
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
function ph_header(array $o, array $struktur, string $menu, string $sub): string
{
    $h = ms_styles() . ph_ui_styles() . ms_skrip_akses();

    // JUDUL + AKSI (kanan)
    $h .= '<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3"><div>'
        . '<h4 class="fw-bold text-dark mb-1">' . ph_e($o['title'] ?? 'Modul Pemeliharaan S41') . '</h4>';
    if (!empty($o['subtitle'])) {
        $h .= '<p class="text-muted small mb-0">' . ph_e($o['subtitle']) . '</p>';
    }
    $h .= '</div><div class="d-flex flex-wrap gap-2 align-items-center">';
    foreach ($o['tombol'] ?? [] as $t) {
        $kelas = 'btn ' . ($t['class'] ?? 'btn-primary') . ' btn-sm px-3 shadow-sm rounded-2';
        $ikon  = '<i class="bi ' . ph_e($t['icon'] ?? 'bi-plus-lg') . ' me-1"></i> ' . ph_e($t['label']);
        if (($t['type'] ?? 'modal') === 'link') {
            $h .= '<a href="' . ph_e($t['href']) . '" target="_blank" rel="noopener" class="' . $kelas . '">' . $ikon . '</a>';
        } else {
            $h .= '<button type="button" class="' . $kelas . '" data-bs-toggle="modal" data-bs-target="#' . ph_e($t['target']) . '">' . $ikon . '</button>';
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
        $url   = 'index.php?page=pemeliharaan&menu=' . ph_e($kunci) . '&sub=' . ph_e($awal);
        $aktif = ($kunci === $menu);
        if ($terkunci) {
            $h .= '<li class="nav-item"><a class="nav-link py-2 px-3 fw-medium text-secondary" aria-disabled="true" style="opacity:.6;" href="' . $url . '"'
                . ' onclick="event.preventDefault();tampilkanAksesDitolak();return false;">'
                . '<i class="bi ' . ph_e($m['icon']) . ' me-2"></i>' . ph_e($m['label']) . ' <i class="bi bi-lock-fill ms-1" style="font-size:.75em"></i></a></li>';
        } else {
            $h .= '<li class="nav-item"><a class="nav-link py-2 px-3 fw-medium ' . ($aktif ? 'active text-white' : 'text-secondary') . '"'
                . ($aktif ? ' style="background-color:#0d6efd;"' : '') . ' href="' . $url . '">'
                . '<i class="bi ' . ph_e($m['icon']) . ' me-2"></i>' . ph_e($m['label']) . '</a></li>';
        }
    }
    $h .= '</ul>';

    // LEVEL 3: PILL SUB ENTITAS (yang tidak boleh diakses terkunci)
    $h .= '<div class="d-flex align-items-center flex-wrap gap-2 mb-4">'
        . '<span class="text-secondary fw-bold text-uppercase me-2 ph-label">SUB ENTITAS:</span>';
    foreach ($struktur[$menu]['sub'] as $kunci_sub => $info) {
        $url = 'index.php?page=pemeliharaan&menu=' . ph_e($menu) . '&sub=' . ph_e($kunci_sub);
        if (!empty($info[3])) {
            $h .= '<a href="' . $url . '" class="btn btn-sm rounded-pill px-3 py-1 fw-medium btn-outline-secondary text-secondary bg-white"'
                . ' aria-disabled="true" style="opacity:.6;" onclick="event.preventDefault();tampilkanAksesDitolak();return false;">'
                . ph_e($info[0]) . ' <i class="bi bi-lock-fill ms-1" style="font-size:.75em"></i></a>';
            continue;
        }
        $on = ($kunci_sub === $sub);
        $h .= '<a href="' . $url . '" '
            . 'class="btn btn-sm rounded-pill px-3 py-1 fw-medium ' . ($on ? 'btn-outline-primary active' : 'btn-outline-secondary text-secondary bg-white') . '"'
            . ($on ? ' style="border-color:#0d6efd;background-color:#e7f1ff;color:#0d6efd;"' : '') . '>'
            . ($on ? '<i class="bi bi-check2 me-1"></i>' : '') . ph_e($info[0]) . '</a>';
    }
    return $h . '</div>';
}

/** Header kartu tabel: judul di kiri, kotak cari di kanan (sama seperti kartu Master). */
function ph_card_header(string $judul, string $sub, string $q): string
{
    return '<div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">'
        . '<div class="d-flex align-items-center gap-2"><span class="fw-bold text-primary fs-6">' . ph_e($judul) . '</span></div>'
        . ph_kotak_cari($sub, $q) . '</div>';
}