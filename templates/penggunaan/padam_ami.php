<?php
// templates/penggunaan/padam_ami.php  -  Daftar Padam AMI (hanya tampil)
// Header + menu (AP2T/S41/AMI/AMR/SDU, sub menu Pemutusan/Penyalaan/Padam) dari pg_header, sama seperti halaman pemutusan/penyalaan.
// Variabel dari modules/penggunaan/padam_ami.php:
//   $halaman, $judul, $monitor, $boleh_monitor, $unit_siap, $q, $hal, $jml_hal, $per_hal, $total, $daftar, $kolom_tampil, $pesan_error

$aksi_url = strtok(pg_url($halaman), '?');                 // form GET: bawa kembali parameter bawaan pg_url (mis. ?page=...) sebagai hidden
parse_str((string) (parse_url(pg_url($halaman), PHP_URL_QUERY) ?? ''), $param_dasar);
$tautan = function (int $h) use ($halaman, $q): string {
    return pg_url($halaman, array_filter(['q' => $q, 'hal' => $h > 1 ? $h : null], fn($v) => $v !== null && $v !== ''));
};
$awal  = $total > 0 ? ($hal - 1) * $per_hal + 1 : 0;
$akhir = min($total, $hal * $per_hal);
$badge       = ['PADAM' => 'success', 'GAGAL' => 'danger', 'LUNAS' => 'warning'];
$badge_jenis = ['PREVENTIF' => 'info', 'KOREKTIF' => 'danger'];   // warna badge Jenis tiket
$ukuran_font = '.8rem';                                           // ukuran tulisan isi tabel (ubah di sini)
?>
<?= pg_header([
    'title'    => $judul,
    'subtitle' => 'Daftar tiket pemutusan yang sudah ditindak. Halaman ini hanya menampilkan data.',
    'action'   => $halaman,
    'buttons'  => !empty($boleh_monitor) ? [['label' => 'Monitoring AMI', 'icon' => 'bi-display', 'href' => pg_url($monitor),
                                             'class' => 'btn btn-outline-primary btn-sm px-3 shadow-sm rounded-2 bg-white']] : [],
]) ?>

<?php if (!empty($pesan_error)) echo pg_alert('danger', $pesan_error); ?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-3 p-md-4">
        <!-- FILTER -->
        <form method="GET" action="<?= pg_e($aksi_url) ?>" class="row g-2 align-items-end mb-3" autocomplete="off">
            <?php foreach ($param_dasar as $nk => $nv): if (is_array($nv)) continue; ?>
            <input type="hidden" name="<?= pg_e((string) $nk) ?>" value="<?= pg_e((string) $nv) ?>">
            <?php endforeach; ?>
            <div class="col-12 col-md-6">
                <label for="f_q" class="form-label small text-secondary mb-1">Cari</label>
                <input type="search" class="form-control" id="f_q" name="q" value="<?= pg_e($q) ?>" placeholder="IDPEL, nama, no. meter, atau tiket" maxlength="50">
            </div>
            <div class="col-12 col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search me-1"></i> Tampilkan</button>
                <?php if ($q !== ''): ?><a href="<?= pg_e(pg_url($halaman)) ?>" class="btn btn-outline-secondary">Reset</a><?php endif; ?>
            </div>
        </form>

        <div class="small text-muted mb-2">
            <?= $total > 0 ? 'Menampilkan <strong>' . pg_n($awal) . '&ndash;' . pg_n($akhir) . '</strong> dari <strong>' . pg_n($total) . '</strong> data' : 'Tidak ada data.' ?>
        </div>

        <!-- TABEL -->
        <style>
            .tbl-padam { font-size: <?= pg_e($ukuran_font) ?>; }
            .tbl-padam thead th { font-size: .72rem; text-transform: uppercase; letter-spacing: .3px; font-weight: 700; }
        </style>
        <div class="table-responsive" style="max-height:70vh;">
            <table class="table table-sm table-hover align-middle mb-0 text-nowrap tbl-padam">
                <thead class="table-light" style="position:sticky;top:0;z-index:1;">
                    <tr>
                        <th class="text-muted">#</th>
                        <?php foreach ($kolom_tampil as $k): ?>
                        <th class="<?= $k['tipe'] === 'angka' ? 'text-end' : '' ?>"><?= pg_e($k['label']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$daftar): ?>
                    <tr><td colspan="<?= count($kolom_tampil) + 1 ?>" class="text-center text-muted py-4">Belum ada data padam<?= $q !== '' ? ' untuk pencarian ini' : '' ?>.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($daftar as $i => $r): ?>
                    <tr>
                        <td class="text-muted"><?= pg_n($awal + $i) ?></td>
                        <?php foreach ($kolom_tampil as $k):
                            $v = $r[$k['key']] ?? null; $s = trim((string) $v);
                            if ($v === null || $s === '') { echo '<td class="text-muted">-</td>'; continue; }
                            switch ($k['tipe']) {
                                case 'badge':
                                    $w = $badge[strtoupper($s)] ?? 'secondary';
                                    echo '<td><span class="badge bg-' . $w . ' bg-opacity-10 text-' . $w . ' border border-' . $w . ' border-opacity-50">' . pg_e($s) . '</span></td>';
                                    break;
                                case 'jenis':
                                    $w = $badge_jenis[strtoupper($s)] ?? 'secondary';
                                    echo '<td><span class="badge bg-' . $w . ' bg-opacity-10 text-' . $w . ' border border-' . $w . ' border-opacity-50">' . pg_e($s) . '</span></td>';
                                    break;
                                case 'angka':
                                    echo '<td class="text-end font-monospace">' . (is_numeric($s) ? pg_n((int) $s) : pg_e($s)) . '</td>';
                                    break;
                                case 'tiket':
                                    echo '<td class="font-monospace fw-bold">' . pg_e($s) . '</td>';
                                    break;
                                case 'mono':
                                    echo '<td class="font-monospace">' . pg_e($s) . '</td>';
                                    break;
                                case 'nama':
                                    echo '<td style="white-space:normal;min-width:150px;">' . pg_e($s) . '</td>';
                                    break;
                                default:
                                    echo '<td>' . pg_e($s) . '</td>';
                            }
                        endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- HALAMAN -->
        <?php if ($jml_hal > 1): ?>
        <nav class="d-flex justify-content-between align-items-center mt-3" aria-label="Halaman">
            <span class="small text-muted">Halaman <?= pg_n($hal) ?> dari <?= pg_n($jml_hal) ?></span>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $hal <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= pg_e($tautan(1)) ?>">&laquo;</a></li>
                <li class="page-item <?= $hal <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= pg_e($tautan(max(1, $hal - 1))) ?>">Sebelumnya</a></li>
                <li class="page-item <?= $hal >= $jml_hal ? 'disabled' : '' ?>"><a class="page-link" href="<?= pg_e($tautan(min($jml_hal, $hal + 1))) ?>">Berikutnya</a></li>
                <li class="page-item <?= $hal >= $jml_hal ? 'disabled' : '' ?>"><a class="page-link" href="<?= pg_e($tautan($jml_hal)) ?>">&raquo;</a></li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>