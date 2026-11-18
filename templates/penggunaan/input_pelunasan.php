<?php
// templates/penggunaan/input_pelunasan.php  -  Input Pelunasan: daftar saldo belum lunas siap diproses
// Variabel dari modules/penggunaan/input_pelunasan.php:
//   $data, $total_data, $ringkas, $keyword, $tahun, $daftar_tahun, $bulan_f, $limit, $hal, $csrf, $u, $unit_siap,
//   $boleh_monitor, $pesan_sukses, $pesan_error, $upload_hasil

$url_pl = fn(array $ubah = []) => pg_url('input_pelunasan', array_merge(
    ['tahun' => $tahun, 'bulan' => $bulan_f, 'keyword' => $keyword, 'limit' => $limit == 25 ? '' : $limit], $ubah));

$nama_bulan = ['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni',
               '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'];

echo pg_header([
    'title'    => 'Input Pelunasan',
    'subtitle' => 'Saldo tunggakan yang belum lunas. Isi tanggal bayar dan RpBK, lalu proses. Wilayah: ' . $u['label'] . '.',
    'action'   => 'input_pelunasan',
    'buttons'  => $boleh_monitor ? [['label' => 'Monitoring Pelunasan', 'icon' => 'bi-display', 'href' => pg_url('monitor_pelunasan'),
                                     'class' => 'btn btn-outline-primary btn-sm px-3 shadow-sm rounded-2 bg-white']] : [],
]);

if (!empty($pesan_sukses)) echo pg_alert('success', $pesan_sukses);
if (!empty($pesan_error))  echo pg_alert('danger', $pesan_error);

if (!$unit_siap) {
    echo pg_alert('danger', 'Akun Anda belum memiliki <strong>Unit UPI</strong>, sehingga halaman ini tidak dapat dipakai. Hubungi administrator.');
    return;
}
if (!$ada_saldo) {
    echo pg_alert('info', 'Belum ada data saldo tunggakan di wilayah Anda, jadi daftar saldo kosong. Anda tetap dapat mencatat pelunasan lewat <strong>Input Form</strong> atau <strong>Upload Excel</strong>.');
}
?>

<!-- CARA INPUT: kartu pilihan (pop card input form / upload Excel) -->
<style>
.pl-opsi { display:flex; align-items:center; gap:1rem; width:100%; text-align:left; background:#fff; border:0; border-radius:1rem;
           padding:1.25rem 1.5rem; box-shadow:0 .25rem .75rem rgba(0,0,0,.06); transition:transform .15s, box-shadow .15s; }
.pl-opsi:hover { transform:translateY(-2px); box-shadow:0 .5rem 1.25rem rgba(0,0,0,.10); }
.pl-opsi:focus-visible { outline:2px solid #0d6efd; outline-offset:2px; }
.pl-opsi-form  { border-left:4px solid #0d6efd; }
.pl-opsi-excel { border-left:4px solid #198754; }
.pl-opsi-ikon  { flex:0 0 auto; width:64px; height:64px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.6rem; }
.pl-opsi-form  .pl-opsi-ikon { background:#e7f0ff; color:#0d6efd; }
.pl-opsi-excel .pl-opsi-ikon { background:#e6f3ec; color:#198754; }
.pl-opsi-judul { font-weight:700; font-size:1.05rem; color:#212529; }
.pl-opsi-ket   { font-size:.85rem; color:#6c757d; }
</style>
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6">
        <button type="button" class="pl-opsi pl-opsi-form" data-bs-toggle="modal" data-bs-target="#modalManual">
            <span class="pl-opsi-ikon"><i class="bi bi-pencil-square"></i></span>
            <span>
                <span class="pl-opsi-judul d-block">Input Form</span>
                <span class="pl-opsi-ket d-block">Isi IdPel, periode, tanggal bayar, RpTag &amp; RpBK satu per satu.</span>
            </span>
        </button>
    </div>
    <div class="col-12 col-md-6">
        <button type="button" class="pl-opsi pl-opsi-excel" data-bs-toggle="modal" data-bs-target="#modalUpload">
            <span class="pl-opsi-ikon"><i class="bi bi-file-earmark-excel"></i></span>
            <span>
                <span class="pl-opsi-judul d-block">Upload Excel</span>
                <span class="pl-opsi-ket d-block">Muat banyak pelunasan sekaligus dari file .xlsx (Idpel, Bulan, TglTransaksi, RpBK, RpTagihan).</span>
            </span>
        </button>
    </div>
</div>

<?php if (!empty($upload_hasil)): $uh = $upload_hasil; ?>
<!-- RINGKASAN UPLOAD EXCEL -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <i class="bi bi-file-earmark-excel text-success fs-5"></i>
            <span class="fw-bold"><?= pg_e($uh['nama']) ?></span>
            <span class="small text-muted ms-auto"><?= pg_e($uh['durasi']) ?> detik</span>
        </div>
        <div class="row g-2 text-center">
            <?php foreach ([
                ['Baris dibaca',          $uh['dibaca'],    'text-dark'],
                ['Dicatat lunas',         $uh['ok'],        'text-success'],
                ['Sudah tercatat',        $uh['sudah'],     'text-secondary'],
                ['Ditolak (format)',      $uh['ditolak'],   'text-danger'],
            ] as [$lb, $nl, $cl]): ?>
            <div class="col-6 col-md">
                <div class="border rounded-3 py-2 px-1 h-100">
                    <div class="fw-bold fs-5 <?= $cl ?>"><?= pg_n($nl) ?></div>
                    <div class="text-muted" style="font-size:.72rem;"><?= $lb ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($uh['duplikat'] > 0): ?><div class="small text-muted mt-2"><?= pg_n($uh['duplikat']) ?> baris ganda (IdPel + Bulan sama) dalam file; yang terakhir dipakai.</div><?php endif; ?>

        <?php if (!empty($uh['contoh_tolak'])): ?>
        <details class="mt-2">
            <summary class="small fw-semibold">Baris ditolak (<?= pg_n($uh['ditolak']) ?><?= $uh['ditolak'] > count($uh['contoh_tolak']) ? ', ' . count($uh['contoh_tolak']) . ' pertama' : '' ?>)</summary>
            <div class="table-responsive mt-2" style="max-height:200px;overflow:auto;">
                <table class="table table-sm small mb-0">
                    <thead><tr><th>Baris Excel</th><th>Idpel</th><th>Alasan</th></tr></thead>
                    <tbody>
                    <?php foreach ($uh['contoh_tolak'] as $t): ?>
                        <tr><td><?= (int) $t['no'] ?></td><td class="font-monospace"><?= pg_e($t['idpel']) ?></td><td><?= pg_e($t['alasan']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($ada_saldo): ?>
<!-- RINGKASAN -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 py-3 px-4 bg-white h-100">
            <span class="text-muted fw-bold text-uppercase" style="font-size:.75rem;letter-spacing:.5px;">Tagihan Belum Lunas</span>
            <h3 class="fw-bold mt-1 mb-0 fs-4 text-danger"><?= pg_n($ringkas['jumlah']) ?></h3>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 py-3 px-4 bg-white h-100">
            <span class="text-muted fw-bold text-uppercase" style="font-size:.75rem;letter-spacing:.5px;">Total Saldo Belum Lunas</span>
            <h3 class="fw-bold mt-1 mb-0 fs-4 text-dark">Rp <?= pg_n($ringkas['rp']) ?></h3>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4 pb-2">
        <!-- FILTER -->
        <form method="GET" action="" class="row g-2 align-items-center mb-3">
            <?= pg_hidden('input_pelunasan') ?>
            <div class="col-md-2">
                <select name="tahun" class="form-select form-select-sm fw-semibold" onchange="this.form.submit()" aria-label="Tahun">
                    <?php foreach ($daftar_tahun as $y): ?>
                    <option value="<?= pg_e($y) ?>" <?= (string) $y === $tahun ? 'selected' : '' ?>>Tahun <?= pg_e($y) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="bulan" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Bulan">
                    <option value="">Semua bulan</option>
                    <?php foreach ($nama_bulan as $k => $n): ?>
                    <option value="<?= $k ?>" <?= $bulan_f === $k ? 'selected' : '' ?>><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="keyword" value="<?= pg_e($keyword) ?>" class="form-control form-control-sm" placeholder="Cari IdPel atau nama...">
            </div>
            <div class="col-md-2">
                <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ([10, 25, 50, 100] as $l): ?>
                    <option value="<?= $l ?>" <?= $limit === $l ? 'selected' : '' ?>><?= $l ?> / halaman</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary px-3"><i class="bi bi-search me-1"></i>Cari</button>
                <?php if ($keyword !== '' || $bulan_f !== ''): ?><a href="<?= pg_e(pg_url('input_pelunasan', ['tahun' => $tahun])) ?>" class="btn btn-sm btn-outline-secondary px-3">Reset</a><?php endif; ?>
            </div>
        </form>
    </div>

    <!-- DAFTAR + PROSES -->
    <form method="POST" action="<?= pg_e($url_pl(['hal' => $hal > 1 ? $hal : ''])) ?>" id="formPelunasan">
        <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
        <input type="hidden" name="f_tahun" value="<?= pg_e($tahun) ?>">
        <input type="hidden" name="f_bulan" value="<?= pg_e($bulan_f) ?>">
        <input type="hidden" name="f_keyword" value="<?= pg_e($keyword) ?>">
        <input type="hidden" name="f_limit" value="<?= (int) $limit ?>">
        <input type="hidden" name="f_hal" value="<?= (int) $hal ?>">

        <?php if ($data): ?>
        <div class="px-4 pb-3 d-flex flex-wrap align-items-center gap-2">
            <label for="tgl_semua" class="small fw-bold text-secondary text-uppercase mb-0">Tanggal bayar semua baris</label>
            <input type="date" id="tgl_semua" class="form-control form-control-sm" style="width:auto" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnTerapkan">Terapkan</button>
            <span class="ms-auto small text-muted" id="infoPilih">0 dipilih</span>
            <button type="submit" class="btn btn-sm btn-primary px-3" id="btnBanyak" disabled
                    data-konfirmasi="Lunasi {jumlah} tagihan terpilih?" data-jumlah=".cek-baris:checked" data-judul="Lunasi tagihan?" data-ya="Ya, Lunasi">
                <i class="bi bi-check2-all me-1"></i>Lunasi Terpilih
            </button>
        </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="text-secondary text-uppercase" style="font-size:.75rem;letter-spacing:.3px;white-space:nowrap;">
                        <th class="ps-4" style="width:40px"><input type="checkbox" class="form-check-input" id="cekSemua" aria-label="Pilih semua"></th>
                        <th>IdPel</th><th>Nama Pelanggan</th><th>ThBlRek</th><th class="text-end">Saldo (RpTag)</th>
                        <th style="min-width:150px">Tanggal Bayar</th><th style="min-width:150px">RpBK</th><th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$data): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>
                        <?= ($keyword !== '' || $bulan_f !== '') ? 'Tidak ada tagihan belum lunas yang cocok dengan filter.' : 'Semua tagihan pada periode ini sudah lunas.' ?></td></tr>
                <?php else: foreach ($data as $r): $key = $r['IdPel'] . '_' . trim($r['ThBlRek']); ?>
                    <tr>
                        <td class="ps-4"><input type="checkbox" class="form-check-input cek-baris" name="pilih[]" value="<?= pg_e($key) ?>" aria-label="Pilih <?= pg_e($r['IdPel']) ?>"></td>
                        <td class="font-monospace fw-semibold"><?= pg_e($r['IdPel']) ?></td>
                        <td><?= $r['NamaPelanggan'] === null ? '<span class="text-muted">-</span>' : pg_e($r['NamaPelanggan']) ?></td>
                        <td class="font-monospace"><?= pg_e($r['ThBlRek']) ?></td>
                        <td class="text-end fw-semibold">Rp <?= pg_n($r['RpTag']) ?></td>
                        <td><input type="date" class="form-control form-control-sm tgl-baris" name="tgl[<?= pg_e($key) ?>]" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required></td>
                        <td>
                            <input type="hidden" name="rptag[<?= pg_e($key) ?>]" value="<?= (int) $r['RpTag'] ?>">
                            <input type="number" class="form-control form-control-sm" name="rpbk[<?= pg_e($key) ?>]" min="0" step="1" value="0" required>
                        </td>
                        <td class="text-end pe-4">
                            <button type="submit" name="satu" value="<?= pg_e($key) ?>" class="btn btn-sm btn-outline-success"
                                    data-konfirmasi="Catat IdPel <?= pg_e($r['IdPel']) ?> periode <?= pg_e(trim($r['ThBlRek'])) ?> sebagai lunas?" data-judul="Catat pelunasan?" data-ya="Ya, Catat">
                                <i class="bi bi-check-circle me-1"></i>Lunasi
                            </button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </form>
    <?= pg_footer_tabel($hal, $limit, $total_data, fn($h) => $url_pl(['hal' => $h > 1 ? $h : ''])) ?>
</div>

<?php endif; ?>

<!-- POP CARD: INPUT MANUAL -->
<div class="modal fade" id="modalManual" tabindex="-1" aria-labelledby="modalManualLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= pg_e($url_pl(['hal' => $hal > 1 ? $hal : ''])) ?>" class="modal-content border-0 shadow rounded-4" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
            <input type="hidden" name="aksi" value="manual">
            <input type="hidden" name="f_tahun" value="<?= pg_e($tahun) ?>">
            <input type="hidden" name="f_bulan" value="<?= pg_e($bulan_f) ?>">
            <input type="hidden" name="f_keyword" value="<?= pg_e($keyword) ?>">
            <input type="hidden" name="f_limit" value="<?= (int) $limit ?>">
            <input type="hidden" name="f_hal" value="<?= (int) $hal ?>">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="modalManualLabel"><i class="bi bi-pencil-square text-primary me-2"></i>Input Pelunasan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body px-4">
                <p class="small text-muted">Pelunasan dicatat langsung dari data yang Anda isi di bawah (tidak dicocokkan dengan saldo). Pelunasan yang sudah tercatat tidak ditimpa.</p>
                <div class="row g-3">
                    <div class="col-12 col-sm-7">
                        <label for="m_idpel" class="form-label small fw-bold text-secondary text-uppercase">IdPel</label>
                        <input type="text" class="form-control font-monospace" id="m_idpel" name="m_idpel" maxlength="12" inputmode="numeric" pattern="\d{11,12}" placeholder="12 digit" required>
                    </div>
                    <div class="col-12 col-sm-5">
                        <label for="m_bulan" class="form-label small fw-bold text-secondary text-uppercase">Periode (ThBlRek)</label>
                        <input type="text" class="form-control font-monospace" id="m_bulan" name="m_bulan" maxlength="6" inputmode="numeric" pattern="(19|20)\d{2}(0[1-9]|1[0-2])" placeholder="YYYYMM" value="<?= pg_e($tahun . ($bulan_f !== '' ? $bulan_f : '')) ?>" required>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label for="m_tgl" class="form-label small fw-bold text-secondary text-uppercase">Tanggal Bayar</label>
                        <input type="date" class="form-control" id="m_tgl" name="m_tgl" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label for="m_rptag" class="form-label small fw-bold text-secondary text-uppercase">RpTag</label>
                        <input type="number" class="form-control" id="m_rptag" name="m_rptag" min="0" step="1" required>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label for="m_rpbk" class="form-label small fw-bold text-secondary text-uppercase">RpBK</label>
                        <input type="number" class="form-control" id="m_rpbk" name="m_rpbk" min="0" step="1" value="0" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-light px-3" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check2-circle me-1"></i>Catat Lunas</button>
            </div>
        </form>
    </div>
</div>

<!-- POP CARD: UPLOAD EXCEL -->
<div class="modal fade" id="modalUpload" tabindex="-1" aria-labelledby="modalUploadLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= pg_e($url_pl(['hal' => $hal > 1 ? $hal : ''])) ?>" enctype="multipart/form-data" class="modal-content border-0 shadow rounded-4" id="formUpload">
            <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
            <input type="hidden" name="aksi" value="upload">
            <input type="hidden" name="f_tahun" value="<?= pg_e($tahun) ?>">
            <input type="hidden" name="f_bulan" value="<?= pg_e($bulan_f) ?>">
            <input type="hidden" name="f_keyword" value="<?= pg_e($keyword) ?>">
            <input type="hidden" name="f_limit" value="<?= (int) $limit ?>">
            <input type="hidden" name="f_hal" value="<?= (int) $hal ?>">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="modalUploadLabel"><i class="bi bi-file-earmark-excel text-success me-2"></i>Upload Excel Pelunasan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body px-4">
                <label for="file_xlsx" class="form-label small fw-bold text-secondary text-uppercase">File .xlsx</label>
                <input type="file" class="form-control" id="file_xlsx" name="file_xlsx" accept=".xlsx" required>
                <div class="small text-muted mt-3">
                    <div class="fw-semibold text-dark mb-1">Kolom yang dibaca (dicari berdasarkan nama, urutan bebas):</div>
                    <ul class="mb-2 ps-3">
                        <li><code>Idpel</code> → IdPel</li>
                        <li><code>Bulan</code> (YYYYMM) → ThBlRek</li>
                        <li><code>TglTransaksi</code> → Tanggal Bayar</li>
                        <li><code>RpBK</code> → RpBK</li>
                        <li><code>RpTagihan</code> → RpTag</li>
                    </ul>
                    Kolom lain diabaikan. Setiap baris valid langsung dicatat (tidak dicocokkan dengan saldo);
                    yang sudah tercatat lunas tidak ditimpa. Seluruh file diproses dalam satu transaksi.
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-light px-3" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success px-4" id="btnUpload"><i class="bi bi-upload me-1"></i>Upload &amp; Proses</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var fu = document.getElementById('formUpload'), bu = document.getElementById('btnUpload');
    if (fu) fu.addEventListener('submit', function () {
        if (bu) { bu.disabled = true; bu.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...'; }
    });
    var mm = document.getElementById('modalManual');
    if (mm) mm.addEventListener('shown.bs.modal', function () { var i = document.getElementById('m_idpel'); if (i) i.focus(); });
})();
</script>

<script>
(function () {
    var semua = document.getElementById('cekSemua'), info = document.getElementById('infoPilih'),
        btn = document.getElementById('btnBanyak'), tglSemua = document.getElementById('tgl_semua');
    function hitung() {
        var n = document.querySelectorAll('.cek-baris:checked').length;
        if (info) info.textContent = n + ' dipilih';
        if (btn) btn.disabled = n === 0;
    }
    if (semua) semua.addEventListener('change', function () {
        document.querySelectorAll('.cek-baris').forEach(function (c) { c.checked = semua.checked; });
        hitung();
    });
    document.querySelectorAll('.cek-baris').forEach(function (c) { c.addEventListener('change', hitung); });
    var t = document.getElementById('btnTerapkan');
    if (t) t.addEventListener('click', function () {
        if (!tglSemua.value) return;
        document.querySelectorAll('.tgl-baris').forEach(function (i) { i.value = tglSemua.value; });
    });
    hitung();
})();
</script>

<?php include __DIR__ . '/../_konfirmasi.php'; ?>