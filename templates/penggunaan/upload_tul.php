<?php
// templates/penggunaan/upload_tul.php  -  Input: Unggah TUL VI-01 (XLSX)
// Variabel dari modules/penggunaan/upload_tul.php:
//   $pesan_sukses, $pesan_error, $hasil, $ditolak, $csrf, $unit_siap, $nama_upi, $opsi_ap, $peta_unit, $sel_ap, $sel_up

echo pg_header([
    'title'    => 'Upload TUL VI-01',
    'subtitle' => 'Unggah file Excel (.xlsx) daftar pelanggan yang sudah dicetak TUL VI-01 tetapi belum lunas.',
    'action'   => 'upload_tul',
    'buttons'  => [['label' => 'Lihat Data TUL', 'icon' => 'bi-display', 'href' => pg_url('monitor_tul'), 'class' => 'btn btn-outline-primary btn-sm px-3 rounded-2']],
]);

if ($pesan_sukses) echo pg_alert('success', pg_e($pesan_sukses) . ' <a href="' . pg_e(pg_url('monitor_tul')) . '" class="alert-link">Lihat datanya &rarr;</a>');
if ($pesan_error)  echo pg_alert('danger', pg_e($pesan_error));
if (!$unit_siap)   echo pg_alert('warning', 'Unit UPI akun Anda belum tersedia, unggah dinonaktifkan. Lengkapi UnitUpi di Master Pengguna.');

if ($hasil):
    $kpi_unggah = [
        ['Baris Dibaca', pg_n($hasil['dibaca']), pg_e($hasil['nama']) . ' · ' . $hasil['durasi'] . ' detik', 'bi-file-earmark-spreadsheet', 'primary'],
        ['Tersimpan', pg_n($hasil['tersimpan']), 'Baru atau diperbarui', 'bi-check2-circle', 'success'],
        ['Duplikat di File', pg_n($hasil['duplikat']), 'Nomer Tul sama, dipakai baris terakhir', 'bi-files', $hasil['duplikat'] > 0 ? 'warning' : 'secondary'],
        ['Ditolak', pg_n($hasil['ditolak']), $hasil['bentrok'] > 0 ? pg_n($hasil['bentrok']) . ' bentrok dengan wilayah lain' : 'Format data tidak valid', 'bi-x-octagon', $hasil['ditolak'] > 0 ? 'danger' : 'secondary'],
    ]; ?>
<div class="row g-3 mb-4">
    <?php foreach ($kpi_unggah as [$lbl, $val, $ket, $ikon, $warna]): ?>
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 py-3 px-4 bg-white h-100">
            <div class="d-flex justify-content-between align-items-start">
                <span class="text-muted fw-bold text-uppercase" style="font-size:.75rem;letter-spacing:.5px;"><?= pg_e($lbl) ?></span>
                <i class="bi <?= pg_e($ikon) ?> text-<?= $warna ?>"></i>
            </div>
            <h3 class="fw-bold mt-1 mb-0 fs-4 text-<?= $warna === 'secondary' ? 'dark' : $warna ?>"><?= pg_e($val) ?></h3>
            <span class="text-muted small"><?= $ket ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="mb-4">
    <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
        <div class="card-header bg-white py-3 px-4 border-bottom">
            <span class="fw-bold text-primary fs-6">Unggah File Excel</span>
        </div>
        <form method="POST" action="<?= pg_e(pg_url('upload_tul')) ?>" enctype="multipart/form-data" id="formUnggah">
            <input type="hidden" name="csrf" value="<?= pg_e($csrf) ?>">
            <div class="card-body px-4 py-4">
                <!-- WILAYAH TUJUAN: UPI (akun) -> AP -> UP -->
                <div class="fw-bold small text-uppercase text-secondary mb-2" style="letter-spacing:.5px;"><i class="bi bi-geo-alt me-1"></i> Wilayah Tujuan Data</div>
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <label class="form-label small text-muted mb-1">Unit UPI</label>
                        <input type="text" class="form-control bg-light" value="<?= pg_e($nama_upi ?: '-') ?>" readonly>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="unit_ap" class="form-label small text-muted mb-1">Unit AP <span class="text-danger">*</span></label>
                        <select name="unit_ap" id="unit_ap" class="form-select" required <?= $unit_siap ? '' : 'disabled' ?>>
                            <option value="">— Pilih Unit AP —</option>
                            <?php foreach ($opsi_ap as $kode_ap => $nama_ap_opt): ?>
                                <option value="<?= pg_e((string) $kode_ap) ?>" <?= (string) $kode_ap === (string) $sel_ap ? 'selected' : '' ?>><?= pg_e($nama_ap_opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="unit_up" class="form-label small text-muted mb-1">Unit UP <span class="text-danger">*</span></label>
                        <select name="unit_up" id="unit_up" class="form-select" required disabled>
                            <option value="">— Pilih AP dahulu —</option>
                        </select>
                    </div>
                </div>
                <div class="form-text mb-4 mt-n2"><i class="bi bi-info-circle me-1"></i>Seluruh baris pada file akan disimpan dengan unit AP &amp; UP yang dipilih di atas.</div>

                <label for="file_xlsx" class="form-label fw-bold small text-uppercase text-secondary" style="letter-spacing:.5px;">File .xlsx</label>
                <input type="file" name="file_xlsx" id="file_xlsx" class="form-control form-control-lg" accept=".xlsx" required <?= $unit_siap ? '' : 'disabled' ?>>
                <div class="form-text">Sheet pertama dibaca. Batas server: <?= pg_e(ini_get('upload_max_filesize')) ?> per file.</div>
            </div>
            <div class="card-footer bg-white border-top py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="text-muted small"><i class="bi bi-arrow-repeat me-1"></i> Nomer Tul yang sudah ada akan diperbarui, bukan digandakan.</span>
                <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm rounded-2" id="btnUnggah" <?= $unit_siap ? '' : 'disabled' ?>>
                    <i class="bi bi-cloud-arrow-up me-1"></i> Unggah &amp; Proses
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($ditolak)): ?>
<div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
        <span class="fw-bold text-primary fs-6">Baris Ditolak</span>
        <span class="text-muted small">Menampilkan <?= count($ditolak) ?> dari <?= pg_n($hasil['ditolak'] ?? count($ditolak)) ?> baris</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-secondary text-uppercase" style="font-size:.75rem;letter-spacing:.3px;white-space:nowrap;">
                <tr><th class="ps-4" style="width:110px;">Baris Excel</th><th>Nomer Tul</th><th>Nilai Id Pelanggan</th><th class="pe-4">Alasan</th></tr>
            </thead>
            <tbody>
                <?php foreach ($ditolak as [$no, $idp, $notul, $alasan]): ?>
                <tr>
                    <td class="ps-4 font-monospace"><?= (int) $no ?></td>
                    <td class="font-monospace small"><?= pg_e($notul ?: '(kosong)') ?></td>
                    <td class="font-monospace small"><?= pg_e($idp ?: '(kosong)') ?></td>
                    <td class="pe-4"><span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-50"><i class="bi bi-x-circle me-1"></i><?= pg_e($alasan) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- CONTOH FORMAT EXCEL -->
<?php
$contoh_kolom = ['No.', 'Tgl Pencetakan Tul VI-01', 'Nomer Tul VI-01', 'Id Pelanggan', 'No Kontrak', 'No RBM', 'Nama Pelanggan', 'Alamat', 'Bulan Rekening Awal', 'Bulan Rekening Akhir', 'Jumlah Lembar', 'Rp. Tagihan', 'Tgl Pelaksanaan Putus', 'Stand Pemutusan', 'Nama Petugas Putus'];
$contoh_wajib = ['Tgl Pencetakan Tul VI-01', 'Nomer Tul VI-01', 'Id Pelanggan', 'Bulan Rekening Awal', 'Bulan Rekening Akhir', 'Jumlah Lembar', 'Rp. Tagihan'];
$contoh_opsi  = ['Nama Pelanggan', 'Tgl Pelaksanaan Putus', 'Stand Pemutusan'];
$contoh_baris = [
    [1, '2026-10-21 08:13:40', '2026-10-21/TULVI-01/926', '546104009013', 'FF4009015', 'AAAAABJ01995', 'HJ MAESAROH A', 'JL KH HASYIM ASHARI RT1/12', '202610', '202610', 1, 53132, '', '', ''],
    [2, '2026-10-21 08:13:40', '2026-10-21/TULVI-01/954', '566100607577', '060757', 'AAAAABJ02364', 'ASPOL BANDARA SOETTA', 'JL IRIGASI SIPON BENTENG BETAWI', '202610', '202610', 1, 83129, '', '', ''],
    [3, '2026-10-24 13:05:33', '2026-10-24/TULVI-01/1', '546100001689', 'FF0001684', 'AAAGGAK06890', 'KES DIM 0506', 'JL DAAN MOGOT 33 SUKARASA', '202607', '202610', 4, 44300340, '', '', ''],
];
?>
<div class="card border-0 shadow-sm rounded-4 bg-white">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-bold text-primary fs-6"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Contoh Format Excel</span>
        <span class="text-muted small">Laporan AP2T apa adanya (kop surat boleh ada) · sheet pertama · .xlsx · <i class="bi bi-arrow-left-right"></i> geser tabel untuk melihat semua kolom</span>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle mb-0 small text-nowrap">
            <thead>
                <tr>
                    <?php foreach ($contoh_kolom as $k):
                        $wajib = in_array($k, $contoh_wajib, true); $opsi = in_array($k, $contoh_opsi, true); ?>
                        <th class="px-2 py-2 <?= $wajib ? 'bg-primary bg-opacity-10 text-primary' : ($opsi ? 'bg-info bg-opacity-10 text-info-emphasis' : 'table-light text-secondary') ?>"><?= pg_e($k) ?><?= $wajib ? ' *' : '' ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contoh_baris as $b): ?>
                <tr>
                    <?php foreach ($b as $i => $v):
                        $wajib = in_array($contoh_kolom[$i], $contoh_wajib, true); $opsi = in_array($contoh_kolom[$i], $contoh_opsi, true); ?>
                        <td class="px-2 font-monospace <?= is_int($v) ? 'text-end' : '' ?> <?= $wajib ? 'bg-primary bg-opacity-10 fw-semibold' : ($opsi ? 'bg-info bg-opacity-10' : 'text-muted') ?>"><?= pg_e((string) $v) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-body px-4 py-3 border-top">
        <div class="row g-3 small">
            <div class="col-md-6">
                <div class="fw-bold text-primary mb-1">* Kolom wajib</div>
                <ul class="mb-0 ps-3 text-muted">
                    <li><strong>Nomer Tul VI-01</strong>: kunci data, maksimal 30 karakter</li>
                    <li><strong>Tgl Pencetakan Tul VI-01</strong>: contoh <span class="font-monospace">2026-10-21 08:13:40</span></li>
                    <li><strong>Id Pelanggan</strong>: 12 digit angka (format ilmiah seperti 5,46E+11 tetap terbaca)</li>
                    <li><strong>Bulan Rekening Awal / Akhir</strong>: YYYYMM, awal tidak boleh lebih besar dari akhir</li>
                    <li><strong>Jumlah Lembar</strong> (minimal 1) dan <strong>Rp. Tagihan</strong>: angka</li>
                </ul>
            </div>
            <div class="col-md-6">
                <div class="fw-bold text-info-emphasis mb-1">Kolom opsional &amp; catatan</div>
                <ul class="mb-0 ps-3 text-muted">
                    <li><strong>Nama Pelanggan</strong> melengkapi DIL hanya bila nama di DIL masih kosong.</li>
                    <li><strong>Stand Pemutusan</strong> &rarr; Stand Baca dan <strong>Tgl Pelaksanaan Putus</strong> &rarr; Tgl Baca; bila kosong, nilai lama tidak ditimpa.</li>
                    <li>Baris <em>TOTAL</em> di akhir laporan dilewati otomatis; kolom lain diabaikan; huruf besar/kecil judul tidak berpengaruh.</li>
                    <li>Id Pelanggan tidak dicek ke master barang; seluruh baris valid langsung disimpan ke data TUL. Status TUL lama tidak berubah saat diunggah ulang.</li>
                    <li>UnitUpi dari akun yang login; UnitAp dan UnitUp sesuai pilihan Wilayah Tujuan.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var peta  = <?= json_encode($peta_unit, JSON_UNESCAPED_UNICODE) ?>;   // [{kode, nama, ups:[{kode, nama}]}]
    var upAwal = <?= json_encode((string) $sel_up) ?>;
    var ap = document.getElementById('unit_ap'), up = document.getElementById('unit_up');

    function isiUp(pilih) {
        up.innerHTML = '';
        var o = document.createElement('option');
        o.value = '';
        o.textContent = ap.value ? '— Pilih Unit UP —' : '— Pilih AP dahulu —';
        up.appendChild(o);
        var item = peta.filter(function (x) { return x.kode === ap.value; })[0];
        (item ? item.ups : []).forEach(function (u) {
            var op = document.createElement('option');
            op.value = u.kode; op.textContent = u.nama;
            if (pilih && pilih === u.kode) op.selected = true;
            up.appendChild(op);
        });
        up.disabled = !ap.value || !item || item.ups.length === 0;
    }
    ap.addEventListener('change', function () { isiUp(''); });
    isiUp(upAwal);   // pertahankan pilihan setelah submit yang gagal validasi

    document.getElementById('formUnggah').addEventListener('submit', function () {
        var b = document.getElementById('btnUnggah');
        b.disabled = true;
        b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';
    });
})();
</script>