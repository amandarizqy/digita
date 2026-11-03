<?php
// modules/pemeliharaan/frm_pemeliharaan.php
// Langkah 1: Buat FPK Pemeliharaan -> Langkah 2: Buat Surat Perintah Kerja (Order)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    require_once __DIR__ . '/../../config/database.php';
}
require_once __DIR__ . '/../../functions/pemeliharaan_helper.php';

if (!ph_akses_order()) {
    echo '<div class="alert alert-warning">Anda tidak memiliki akses ke Order Pemeliharaan.</div>';
    return;
}

$sub_ini   = 'order_pemeliharaan';
$url_cetak_order = BASE_URL . '/modules/pemeliharaan/cetak_order.php';
$nama_akun = substr(trim((string) ($_SESSION['NamaAkun'] ?? '')), 0, 20);

/* ======================================================================
 * PROSES FORM SUBMIT (POST)
 * ==================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();
    $aksi = (string) $_POST['action'];

    // 1. TAMBAH FPK PEMELIHARAAN
    if ($aksi === 'tambah_pk') {
        try {
            $tgl    = ph_tanggal($_POST['tgl_fpk'] ?? '');
            $id_pel = trim((string) ($_POST['id_pel'] ?? ''));
            [$lat, $long, $err_k] = ph_koordinat($_POST['latitude'] ?? '', $_POST['longitude'] ?? '');

            if ($tgl === null) throw new RuntimeException('Tanggal FPK tidak valid.');
            if ($id_pel === '') throw new RuntimeException('Pilih ID Pelanggan terlebih dahulu.');
            if ($err_k !== null) throw new RuntimeException($err_k);
            if ($lat === null || $long === null) throw new RuntimeException('Titik koordinat DIL wajib diisi melalui peta atau tombol GPS.');
            
            // Ambil data master barang milik pelanggan
            $b = ph_barang_pelanggan($conn, $id_pel);

            // Alat PASANG: pilih No Ref baru yang belum terpakai; kosong = tidak ada penggantian (sama dengan bongkar)
            $pasang = ph_tentukan_alat_pasang($conn, $b, (string) ($_POST['no_ref_pasang'] ?? ''));

            // Cek apakah pelanggan masih punya FPK pemeliharaan aktif yang belum selesai (status TIDAK)
            $cek = $conn->prepare("SELECT NoFpk FROM frm_pk WHERE IdPel = ? AND JenisPk = 'PEMELIHARAAN' AND StatusData = 'TIDAK' LIMIT 1");
            $cek->execute([$id_pel]);
            if ($cek->fetchColumn()) {
                throw new RuntimeException("Pelanggan $id_pel masih memiliki FPK pemeliharaan aktif yang belum selesai.");
            }

            $conn->beginTransaction();
            // Membuat FPK dengan status default 'TIDAK' dan jenis 'PEMELIHARAAN'
            $no_fpk = ph_buat_pk($conn, [
                'tgl' => $tgl, 'id_pel' => $id_pel, 'long' => $long, 'lat' => $lat, 'nama_akun' => $nama_akun,
            ], $b, $pasang);
            $conn->commit();

            ph_redirect($sub_ini, 'sukses', "FPK Pemeliharaan ($no_fpk) berhasil dibuat dan dimasukkan ke sistem.");
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log('pemeliharaan tambah_pk: ' . $e->getMessage());
            ph_redirect($sub_ini, 'gagal', 'Gagal membuat FPK: ' . ph_pesan_error($e));
        }
    }

    // 2. TERBITKAN FORMULIR ORDER (SPK)
    if ($aksi === 'tambah_order') {
        try {
            $tgl_order  = ph_tanggal($_POST['tgl_order'] ?? '');
            $kd_petugas = in_array($_POST['kd_petugas'] ?? '', PH_PETUGAS, true) ? $_POST['kd_petugas'] : '';
            $no_fpks    = array_values(array_unique(array_filter(array_map('trim', (array) ($_POST['no_fpk_list'] ?? [])), 'strlen')));

            if ($tgl_order === null) throw new RuntimeException('Tanggal order tidak valid.');
            if ($kd_petugas === '') throw new RuntimeException('Pilih petugas pelaksana.');
            if (!$no_fpks) throw new RuntimeException('Pilih minimal satu FPK untuk dimasukkan ke dalam order.');

            [$sc, $sp] = ph_scope('p');
            $st = $conn->prepare("SELECT p.NoFpk, p.UnitUp, p.UnitAp, p.UnitUpi FROM frm_pk p
                                  WHERE p.NoFpk = ? AND p.JenisPk = 'PEMELIHARAAN'" . $sc . "
                                    AND NOT EXISTS (SELECT 1 FROM frm_order_detil od WHERE od.NoFpk = p.NoFpk)");
            $pk0 = null;
            foreach ($no_fpks as $no) {
                $st->execute(array_merge([$no], $sp));
                $r = $st->fetch(PDO::FETCH_ASSOC);
                if (!$r) throw new RuntimeException("FPK $no tidak valid atau sudah masuk ke order lain.");
                $pk0 = $pk0 ?? $r;
            }

            $u_up  = trim((string) ($_SESSION['UnitUp']  ?? '')) ?: trim((string) $pk0['UnitUp']);
            $u_ap  = trim((string) ($_SESSION['UnitAp']  ?? '')) ?: trim((string) $pk0['UnitAp']);
            $u_upi = trim((string) ($_SESSION['UnitUpi'] ?? '')) ?: trim((string) $pk0['UnitUpi']);

            $conn->beginTransaction();
            $no_order = ph_no_form($conn, 'frm_order', 'NoFormOrder', $u_up, $tgl_order, '-F.E');
            $conn->prepare("INSERT INTO frm_order (NoFormOrder, TglOrder, KdPetugas, NamaAkun, UnitUp, UnitAp, UnitUpi, StatusData, WaktuData)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'AKTIF', NOW())")
                 ->execute([$no_order, $tgl_order, $kd_petugas, $nama_akun, $u_up, $u_ap, $u_upi]);
            
            $ins = $conn->prepare('INSERT INTO frm_order_detil (NoFormOrder, NoFpk) VALUES (?, ?)');
            foreach ($no_fpks as $no) {
                $ins->execute([$no_order, $no]);
            }
            $conn->commit();

            ph_redirect($sub_ini, 'sukses', "Formulir Order ($no_order) berisi " . count($no_fpks) . ' FPK berhasil diterbitkan.');
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log('pemeliharaan tambah_order: ' . $e->getMessage());
            ph_redirect($sub_ini, 'gagal', 'Gagal menerbitkan Order: ' . ph_pesan_error($e));
        }
    }
}

/* ======================================================================
 * AMBIL DATA UNTUK DITAMPILKAN KE TABEL
 * ==================================================================== */
$pesan_flash = ph_flash_html();
$pesan_error = '';
$data_pk = [];
$total_hasil = 0; $hal = 1; $total_hal = 1; $q = '';
$list_pelanggan = [];
$list_pk_bebas  = [];
$list_alat_baru = [];

try {
    $nama_sql = ph_sql_nama_modem($conn);
    // Menampilkan daftar FPK pemeliharaan yang ada di sistem
    [$data_pk, $total_hasil, $hal, $total_hal, $q] = ph_daftar(
        $conn,
        "p.NoFpk, p.TglFpk, p.IdPel, p.LongDil, p.LatDil, p.StatusData AS StatusPk,
         pd.NoRef, pd.SimId, pd.ImeiModem, $nama_sql AS NamaModem, pp.NoRef AS NoRefPasang,
         o.NoFormOrder, o.KdPetugas AS PetugasOrder, b.NoFba, b.StatusData AS StatusBa",
        "frm_pk p
         LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk AND pd.StatusBarang = 'DIL'
         LEFT JOIN frm_pk_detil pp ON pp.NoFpk = p.NoFpk AND pp.StatusBarang = 'STOK'
         LEFT JOIN frm_order_detil od ON od.NoFpk = p.NoFpk
         LEFT JOIN frm_order o ON o.NoFormOrder = od.NoFormOrder
         LEFT JOIN frm_ba b ON b.NoFpk = p.NoFpk AND b.JenisPekerjaan = 'PEMELIHARAAN'
         LEFT JOIN master_modem mm ON mm.ImeiModem = pd.ImeiModem",
        "AND p.JenisPk = 'PEMELIHARAAN'",
        [],
        ['alias' => 'p', 'urut' => 'p.WaktuData DESC, p.NoFpk DESC',
         'cari'  => ph_kolom_cari($conn, true, true, ['pd.NoRef', 'pd.SimId', 'pd.ImeiModem'])]
    );

    // Ambil daftar pelanggan yang memiliki perangkat aktif (master_barang)
    [$sc, $sp] = ph_scope('mb');
    $st = $conn->prepare("SELECT mb.IdPel, mb.NoRef, mb.SimId, mb.ImeiModem, mb.UnitUp, $nama_sql AS NamaModem
                          FROM master_barang mb
                          LEFT JOIN master_modem mm ON mm.ImeiModem = mb.ImeiModem
                          WHERE mb.StatusData = 'AKTIF' AND mb.IdPel IS NOT NULL AND mb.IdPel <> ''" . $sc . "
                          ORDER BY mb.IdPel ASC, mb.NoRef ASC");
    $st->execute($sp);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (!isset($list_pelanggan[$r['IdPel']])) {
            $r['Kontak'] = ph_nomor_sim($conn, $r['SimId'] ?? '');
            $r['NamaPel'] = ph_nama_pelanggan($conn, $r['IdPel']);
            $list_pelanggan[$r['IdPel']] = $r;
        }
    }
    $list_pelanggan = array_values($list_pelanggan);

    // Alat baru yang belum terpakai (sudah lulus pengujian) untuk bagian PASANG
    $list_alat_baru = ph_alat_pasang_tersedia($conn);

    // Ambil FPK yang belum masuk ke dalam order manapun untuk dicentang saat buat order
    [$sc, $sp] = ph_scope('p');
    $st = $conn->prepare("SELECT p.NoFpk, p.TglFpk, p.IdPel, pd.NoRef, pp.NoRef AS NoRefPasang, pd.SimId, pd.ImeiModem, $nama_sql AS NamaModem
                          FROM frm_pk p
                          LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk AND pd.StatusBarang = 'DIL'
                          LEFT JOIN frm_pk_detil pp ON pp.NoFpk = p.NoFpk AND pp.StatusBarang = 'STOK'
                          LEFT JOIN master_modem mm ON mm.ImeiModem = pd.ImeiModem
                          WHERE p.JenisPk = 'PEMELIHARAAN'" . $sc . "
                            AND NOT EXISTS (SELECT 1 FROM frm_order_detil od WHERE od.NoFpk = p.NoFpk)
                          ORDER BY p.TglFpk DESC, p.NoFpk DESC LIMIT 200");
    $st->execute($sp);
    $list_pk_bebas = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('pemeliharaan frm_pemeliharaan: ' . $e->getMessage());
    $pesan_error = 'Gagal memuat data pemeliharaan dari database.' . (ph_role() === 'SA.KP' ? ' Detail: ' . $e->getMessage() : '');
}
?>

<!-- Pustaka Peta Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
    .pln-form-box { border: 1.5px solid #212529; font-size: 0.85rem; }
    .pln-form-box th, .pln-form-box td { border: 1px solid #212529 !important; padding: 6px 10px; vertical-align: middle; }
    #modalBuatPK .select2-container .select2-selection { font-weight: 700; font-size: 0.85rem; }
    .pln-header-title { font-weight: 700; letter-spacing: 0.5px; }
    .table-compact thead th { font-size: 0.75rem !important; font-weight: 700 !important; color: #6c757d !important; padding: 0.6rem 0.35rem !important; }
    .table-compact tbody td { padding: 0.5rem 0.35rem !important; font-size: 0.81rem !important; }
</style>

<?= $pesan_flash ?>
<?php if ($pesan_error): ?>
    <div class="alert alert-danger rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= ph_e($pesan_error) ?></div>
<?php endif; ?>

<?php /* Tombol "Buat FPK" dan "Buat Order" hanya di header atas halaman; modal-nya ada di bawah file ini.
         Kotak cari ditaruh sejajar dengan baris SUB ENTITAS (layar lebar) agar kartu tidak turun jauh. */ ?>
<style>
    .ph-cari-atas { position: relative; z-index: 2; margin-bottom: .75rem; pointer-events: none; }
    .ph-cari-atas > * { pointer-events: auto; }
    @media (min-width: 992px) { .ph-cari-atas { margin-top: -3.4rem; } }
</style>
<div class="ph-cari-atas d-flex justify-content-end">
    <?= ph_kotak_cari($sub_ini, $q) ?>
</div>

<div class="card border-0 shadow-sm rounded-4 bg-white p-3">
    <div class="w-100 table-responsive">
        <table class="table table-hover align-middle mb-0 table-compact w-100">
            <thead class="table-light border-top border-bottom">
                <tr>
                    <th class="ps-2 text-center" style="width:3%;">#</th>
                    <th>NO FPK</th>
                    <th>TGL FPK</th>
                    <th>IDPEL / NO REF</th>
                    <th>SIM ID</th>
                    <th>MODEM</th>
                    <th>NO ORDER / PETUGAS</th>
                    <th>TAHAP / STATUS</th>
                </tr>
            </thead>
            <tbody class="fw-medium text-dark">
                <?php if (empty($data_pk)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada FPK pemeliharaan di sistem. Klik tombol "Buat FPK" di atas untuk mulai mencatat.</td></tr>
                <?php else: ?>
                    <?php foreach ($data_pk as $idx => $row): ?>
                        <tr class="border-bottom">
                            <td class="ps-2 text-center text-muted small"><?= ($hal - 1) * PH_PER_HALAMAN + $idx + 1 ?></td>
                            <td class="fw-bold text-dark font-monospace"><?= ph_e($row['NoFpk']) ?></td>
                            <td><?= ph_e($row['TglFpk']) ?></td>
                            <td>IDPEL: <?= ph_e($row['IdPel']) ?><br><small class="text-muted">BONGKAR: <?= ph_e($row['NoRef'] ?? '-') ?></small><br><small class="text-muted">PASANG: <?= empty($row['NoRefPasang']) ? '-' : (($row['NoRefPasang'] === ($row['NoRef'] ?? null)) ? ph_e($row['NoRefPasang']) . ' <em>(tidak diganti)</em>' : ph_e($row['NoRefPasang'])) ?></small></td>
                            <td><span class="badge bg-info bg-opacity-10 text-info font-monospace"><?= ph_e($row['SimId'] ?? '-') ?></span></td>
                            <td class="font-monospace">
                                <?= ph_e($row['ImeiModem'] ?? '-') ?>
                                <?php if (!empty($row['NamaModem'])): ?><br><small class="text-muted"><?= ph_e($row['NamaModem']) ?></small><?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['NoFormOrder'])): ?>
                                    <span class="font-monospace"><?= ph_e($row['NoFormOrder']) ?></span>
                                    <a href="<?= ph_e($url_cetak_order . '?no_order=' . urlencode($row['NoFormOrder'])) ?>" target="_blank" rel="noopener" class="ms-1 text-decoration-none" title="Cetak Formulir Order"><i class="bi bi-printer"></i></a>
                                    <br><small class="text-muted"><?= ph_e($row['PetugasOrder'] ?? '-') ?></small>
                                <?php else: ?>
                                    <span class="text-muted fst-italic small">Belum diorderkan</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['NoFba'])): ?>
                                    <?= (($row['StatusBa'] ?? '') === 'AKTIF') ? '<span class="badge bg-success-subtle text-success">BA SELESAI</span>' : '<span class="badge bg-warning-subtle text-warning-emphasis">BA PENDING</span>' ?>
                                <?php elseif (!empty($row['NoFormOrder'])): ?>
                                    <span class="badge bg-primary-subtle text-primary">MENUNGGU EKSEKUSI</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary">FPK BARU (TIDAK)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= ph_pager($sub_ini, $total_hasil, $hal, $total_hal, $q) ?>
</div>

<!-- MODAL 1: BUAT FPK -->
<div class="modal fade" id="modalBuatPK" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-bottom bg-white py-3 px-4">
                <div class="row w-100 align-items-center">
                    <div class="col-md-5">
                        <h6 class="fw-bold mb-0 text-dark small">PT PLN (PERSERO) UID BANTEN</h6>
                        <span id="kopUnitPLN" class="fw-bold text-danger">PILIH ID PELANGGAN</span>
                    </div>
                    <div class="col-md-4 text-center">
                        <h5 class="fw-bold text-dark mb-0 pln-header-title">FORMULIR PERINTAH KERJA</h5>
                    </div>
                    <div class="col-md-3 text-end">
                        <span class="border border-dark px-2 py-1 fw-bold small font-monospace">FM-2026-Z</span>
                    </div>
                </div>
            </div>

            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="tambah_pk">
                <div class="modal-body p-4 bg-white">
                    <table class="table pln-form-box mb-3 w-100">
                        <tr>
                            <td class="bg-light fw-bold" style="width: 18%;">Jenis PK</td>
                            <td style="width: 32%;"><span class="badge bg-warning text-dark px-2 py-1 fw-bold">PEMELIHARAAN</span></td>
                            <td class="bg-light fw-bold" style="width: 18%;">Tanggal</td>
                            <td style="width: 32%;"><input type="date" name="tgl_fpk" class="form-control form-control-sm border-0 bg-transparent fw-bold" value="<?= date('Y-m-d') ?>" required></td>
                        </tr>
                        <tr>
                            <td class="bg-light fw-bold">Id Pelanggan</td>
                            <td>
                                <select name="id_pel" id="selectIdPelPk" class="form-select form-select-sm border-0 bg-transparent fw-bold" required>
                                    <option value="">-- Pilih ID Pelanggan --</option>
                                    <?php foreach ($list_pelanggan as $pel): ?>
                                        <option value="<?= ph_e($pel['IdPel']) ?>"
                                                data-noref="<?= ph_e($pel['NoRef']) ?>"
                                                data-sim="<?= ph_e($pel['SimId'] ?? '') ?>"
                                                data-imei="<?= ph_e($pel['ImeiModem'] ?? '') ?>"
                                                data-nama="<?= ph_e($pel['NamaModem'] ?? '') ?>"
                                                data-kontak="<?= ph_e($pel['Kontak'] ?? '') ?>"
                                                data-namapel="<?= ph_e($pel['NamaPel'] ?? '') ?>"
                                                data-unit="<?= ph_e($pel['UnitUp'] ?? '') ?>">
                                            <?= ph_e($pel['IdPel']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="bg-light fw-bold">Nomor Form</td>
                            <td><span class="text-muted small fst-italic">Otomatis sistem</span></td>
                        </tr>
                        <tr>
                            <td class="bg-light fw-bold">Nama</td>
                            <td colspan="3"><span id="pkNamaPel" class="fw-bold">-</span></td>
                        </tr>
                        <tr>
                            <td class="bg-light fw-bold">Tikor DIL</td>
                            <td colspan="3">
                                <div class="d-flex align-items-center gap-3">
                                    <span>Long: <input type="text" name="longitude" id="lngInput" class="d-inline-block form-control form-control-sm font-monospace border-0 bg-transparent w-auto" readonly required></span>
                                    <span>Lat: <input type="text" name="latitude" id="latInput" class="d-inline-block form-control form-control-sm font-monospace border-0 bg-transparent w-auto" readonly required></span>
                                    <button type="button" class="btn btn-sm btn-success py-0 px-2 ms-auto" id="btnGetGPS"><i class="bi bi-geo-alt-fill me-1"></i> Ambil GPS Saya</button>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <div id="mapPemeliharaan" style="height: 160px; width: 100%;" class="rounded-3 border mb-3"></div>

                    <table class="table pln-form-box text-center mb-1 w-100">
                        <thead>
                            <tr class="bg-light">
                                <th style="width:16%;"></th>
                                <th style="width:42%;">BONGKAR <span class="small fw-normal text-muted">(alat terpasang saat ini)</span></th>
                                <th style="width:42%;">PASANG <span class="small fw-normal text-muted">(alat baru)</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="bg-light text-start fw-bold small">No Ref S41</td>
                                <td><span id="bgNoRef" class="font-monospace fw-bold text-danger">-</span></td>
                                <td>
                                    <select name="no_ref_pasang" id="selectNoRefPasang" class="form-select form-select-sm font-monospace fw-bold">
                                        <option value="">Tidak ada penggantian (sama dengan bongkar)</option>
                                        <?php foreach ($list_alat_baru as $al): ?>
                                            <option value="<?= ph_e($al['NoRef']) ?>"
                                                    data-sim="<?= ph_e($al['SimId'] ?? '') ?>"
                                                    data-imei="<?= ph_e($al['ImeiModem'] ?? '') ?>"
                                                    data-nama="<?= ph_e($al['NamaModem'] ?? '') ?>"
                                                    data-kontak="<?= ph_e($al['Kontak'] ?? '') ?>">
                                                <?= ph_e($al['NoRef']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="psInfo" class="small text-muted fst-italic mt-1"><?= $list_alat_baru ? count($list_alat_baru) . ' alat baru siap dipasang' : 'Belum ada alat baru yang lulus uji / belum terpakai' ?></div>
                                </td>
                            </tr>
                            <tr>
                                <td class="bg-light text-start fw-bold small">Nomor SIM</td>
                                <td><span id="bgSim" class="font-monospace small">-</span></td>
                                <td><span id="psSim" class="font-monospace small">-</span></td>
                            </tr>
                            <tr>
                                <td class="bg-light text-start fw-bold small">IMEI Modem</td>
                                <td><span id="bgImei" class="font-monospace small">-</span></td>
                                <td><span id="psImei" class="font-monospace small">-</span></td>
                            </tr>
                            <tr>
                                <td class="bg-light text-start fw-bold small">Nomor Kontak</td>
                                <td><span id="bgKontak" class="font-monospace small">-</span></td>
                                <td><span id="psKontak" class="font-monospace small">-</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer border-top p-3 justify-content-end gap-2 bg-light">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning fw-bold rounded-3 px-4">Simpan FPK</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL 2: BUAT ORDER -->
<div class="modal fade" id="modalBuatOrder" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <h5 class="modal-title fw-bold text-primary"><i class="bi bi-file-earmark-text me-2"></i>Formulir Order Pemeliharaan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="tambah_order">
                <div class="modal-body p-4 text-start">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">NO ORDER</label>
                            <input type="text" class="form-control rounded-3 font-monospace bg-light" value="Otomatis sistem" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">TANGGAL ORDER *</label>
                            <input type="date" name="tgl_order" class="form-control rounded-3 bg-light" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">PETUGAS *</label>
                            <select name="kd_petugas" class="form-select rounded-3 bg-light" required>
                                <?php foreach (PH_PETUGAS as $p): ?>
                                    <option value="<?= ph_e($p) ?>"><?= ph_e($p) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">PILIH FPK YANG DIMASUKKAN KE ORDER *</label>
                        <div class="border rounded-3 p-3 bg-light" style="max-height: 260px; overflow-y: auto;">
                            <?php if (!$list_pk_bebas): ?>
                                <span class="text-muted small">Tidak ada FPK bebas yang tersedia. Buat FPK terlebih dahulu melalui tombol "Buat FPK".</span>
                            <?php endif; ?>
                            <?php foreach ($list_pk_bebas as $i => $pk): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="no_fpk_list[]" value="<?= ph_e($pk['NoFpk']) ?>" id="fpk_<?= (int) $i ?>">
                                    <label class="form-check-label font-monospace small text-dark" for="fpk_<?= (int) $i ?>">
                                        <strong><?= ph_e($pk['NoFpk']) ?></strong> | IDPEL: <?= ph_e($pk['IdPel']) ?> | Bongkar: <?= ph_e($pk['NoRef'] ?? '-') ?> &rarr; Pasang: <?= ph_e($pk['NoRefPasang'] ?? '-') ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top p-3 justify-content-end gap-2 bg-light">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary fw-bold rounded-3 px-4" <?= $list_pk_bebas ? '' : 'disabled' ?>>Terbitkan Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Hilangkan penomoran "1." / "2." pada tombol Buat FPK & Buat Order (termasuk tombol di header halaman)
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("button, a").forEach(function (b) {
        b.childNodes.forEach(function (n) {
            if (n.nodeType === 3) n.nodeValue = n.nodeValue.replace(/^(\s*)[12]\.\s*(?=Buat (FPK|Order))/, "$1");
        });
    });
});
document.addEventListener("DOMContentLoaded", function () {
    var selIdPel = document.getElementById("selectIdPelPk");
    var selPasang = document.getElementById("selectNoRefPasang");
    var kop = document.getElementById("kopUnitPLN");
    // jQuery.on('change') menangkap event bawaan maupun event dari Select2
    var onUbah = function (elm, fn) {
        if (window.jQuery) jQuery(elm).on("change", fn); else elm.addEventListener("change", fn);
    };
    var el = function (id) { return document.getElementById(id); };
    var gabung = function (imei, nama) { imei = imei || "-"; return nama ? nama + " \u2014 " + imei : imei; };

    // Kolom PASANG: alat baru bila dipilih, kalau kosong = tidak ada penggantian -> samakan dengan BONGKAR
    var isiPasang = function () {
        var optBg = selIdPel.options[selIdPel.selectedIndex];
        var optPs = selPasang.options[selPasang.selectedIndex];
        var ganti = selPasang.value !== "";
        var src = ganti ? optPs : optBg;
        el("psSim").textContent = src.getAttribute("data-sim") || "-";
        el("psImei").textContent = gabung(src.getAttribute("data-imei"), src.getAttribute("data-nama"));
        el("psKontak").textContent = src.getAttribute("data-kontak") || "-";
        if (selIdPel.value === "") {
            el("psInfo").textContent = "Pilih ID Pelanggan terlebih dahulu";
        } else if (!ganti) {
            el("psInfo").textContent = "Tidak ada penggantian: data pasang sama dengan bongkar";
        } else {
            el("psInfo").textContent = "Alat baru menggantikan " + (el("bgNoRef").textContent || "-");
        }
    };

    if (selIdPel) {
        onUbah(selIdPel, function () {
            var opt = this.options[this.selectedIndex];
            el("pkNamaPel").textContent = opt.getAttribute("data-namapel") || "-";
            el("bgNoRef").textContent = opt.getAttribute("data-noref") || "-";
            el("bgSim").textContent = opt.getAttribute("data-sim") || "-";
            el("bgImei").textContent = gabung(opt.getAttribute("data-imei"), opt.getAttribute("data-nama"));
            el("bgKontak").textContent = opt.getAttribute("data-kontak") || "-";
            var unit = opt.getAttribute("data-unit") || "";
            kop.textContent = unit ? "ULP " + unit : "PILIH ID PELANGGAN";
            isiPasang();
            validasiIdPel();
        });
    }
    if (selPasang) onUbah(selPasang, isiPasang);

    // ID Pelanggan wajib dipilih (select2 menyembunyikan validasi bawaan 'required')
    function validasiIdPel() {
        selIdPel.setCustomValidity(selIdPel.value === "" ? "Pilih ID Pelanggan terlebih dahulu." : "");
    }
    var formPK = selIdPel ? selIdPel.closest("form") : null;
    if (formPK) {
        formPK.addEventListener("submit", function (e) {
            validasiIdPel();
            if (selIdPel.value === "") {
                e.preventDefault();
                alert("Pilih ID Pelanggan terlebih dahulu.");
            }
        });
    }

    // Kotak pencarian pada ID Pelanggan dan No Ref pasang (Select2, sudah dimuat di layout)
    if (window.jQuery && jQuery.fn.select2) {
        var parentModal = jQuery("#modalBuatPK");
        jQuery(selIdPel).select2({
            theme: "bootstrap-5", width: "100%", dropdownParent: parentModal,
            placeholder: "Ketik untuk mencari ID Pelanggan...",
            language: { noResults: function () { return "ID Pelanggan tidak ditemukan"; } }
        });
        jQuery(selPasang).select2({
            theme: "bootstrap-5", width: "100%", dropdownParent: parentModal,
            language: { noResults: function () { return "No Ref tidak ditemukan"; } }
        });
        // fokus langsung ke kotak cari saat dropdown dibuka
        jQuery(document).on("select2:open", function () {
            var f = document.querySelector(".select2-container--open .select2-search__field");
            if (f) f.focus();
        });
    }

    var defaultLat = -6.1783, defaultLng = 106.6321, map = null, marker = null;
    var modalPK = document.getElementById("modalBuatPK");
    var isiKoordinat = function (lat, lng) {
        document.getElementById("latInput").value = lat.toFixed(6);
        document.getElementById("lngInput").value = lng.toFixed(6);
    };
    if (modalPK) {
        modalPK.addEventListener("shown.bs.modal", function () {
            if (!window.L) return;
            if (!map) {
                map = L.map("mapPemeliharaan").setView([defaultLat, defaultLng], 13);
                L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { attribution: "&copy; OpenStreetMap" }).addTo(map);
                marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);
                map.on("click", function (e) { marker.setLatLng(e.latlng); isiKoordinat(e.latlng.lat, e.latlng.lng); });
                marker.on("dragend", function () { var p = marker.getLatLng(); isiKoordinat(p.lat, p.lng); });
            }
            setTimeout(function () { map.invalidateSize(); }, 200);
        });
    }

    var btnGPS = document.getElementById("btnGetGPS");
    if (btnGPS) {
        btnGPS.addEventListener("click", function () {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function (pos) {
                    var lat = pos.coords.latitude, lng = pos.coords.longitude;
                    isiKoordinat(lat, lng);
                    if (map && marker) {
                        var coord = [lat, lng];
                        marker.setLatLng(coord);
                        map.setView(coord, 16);
                    }
                }, function (err) {
                    alert("Gagal mendeteksi lokasi GPS Anda: " + err.message);
                });
            } else {
                alert("Browser Anda tidak mendukug Geolocation.");
            }
        });
    }
});
</script>