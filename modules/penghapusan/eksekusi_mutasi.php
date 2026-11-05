<?php
// modules/penghapusan/eksekusi_mutasi.php
// Langkah 3: petugas membongkar alat di lapangan -> Berita Acara (BA) -> alat dilepas dari pelanggan.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    require_once __DIR__ . '/../../config/database.php';
}
require_once __DIR__ . '/../../functions/penghapusan_helper.php';

if (!pgh_akses_mutasi()) {
    echo '<div class="alert alert-warning">Anda tidak memiliki akses ke Mutasi Penghapusan.</div>';
    return;
}

$sub_ini   = 'mutasi_penghapusan';
$nama_akun = substr(trim((string) ($_SESSION['NamaAkun'] ?? '')), 0, 20);

/** Membaca isian formulir sisi BONGKAR (satu-satunya sisi pada penghapusan). */
function ek_baca_sisi(string $s): array
{
    $lampu  = array_key_exists($_POST[$s . '_lampu'] ?? '', PGH_LAMPU) ? $_POST[$s . '_lampu'] : '';
    $posisi = in_array($_POST[$s . '_posisi'] ?? '', PGH_POSISI, true) ? $_POST[$s . '_posisi'] : '';
    [$lat, $long, $err_k] = pgh_koordinat($_POST[$s . '_latitude'] ?? '', $_POST[$s . '_longitude'] ?? '');
    [$f_terpasang, $err_f1] = pgh_foto_baca($s . '_foto_terpasang');
    [$f_barang, $err_f2]    = pgh_foto_baca($s . '_foto_barang');
    return [
        'lampu' => $lampu, 'posisi' => $posisi, 'lat' => $lat, 'long' => $long,
        'f_terpasang' => $f_terpasang, 'f_barang' => $f_barang,
        'err' => array_values(array_filter([$err_k, $err_f1, $err_f2], function ($e) { return $e !== null; })),
    ];
}

/** Menyimpan (insert / update) baris BONGKAR di frm_ba_detil. Foto lama dipertahankan bila tidak diunggah ulang. */
function ek_simpan_bongkar(PDO $conn, string $no_fba, array $dev, ?string $kontak, array $x): void
{
    $st = $conn->prepare("SELECT COUNT(*) FROM frm_ba_detil WHERE NoFba = ? AND StatusPekerjaan = 'BONGKAR'");
    $st->execute([$no_fba]);
    if ((int) $st->fetchColumn() === 0) {
        $conn->prepare("INSERT INTO frm_ba_detil (NoFba, StatusPekerjaan, NoRef, SimId, ImeiModem, NomorBarang, NomorModem) VALUES (?, 'BONGKAR', ?, ?, ?, ?, ?)")
             ->execute([$no_fba, $dev['NoRef'], $dev['SimId'], $dev['ImeiModem'], $kontak, $kontak]);
    }

    $set = 'LampuIndikator = :lampu, PosisiPasang = :posisi, `Long` = :long, Lat = :lat';
    if ($x['f_terpasang'] !== null) $set .= ', FotoTerpasang = :fp';
    if ($x['f_barang'] !== null)    $set .= ', FotoBarang = :fb';
    $u = $conn->prepare("UPDATE frm_ba_detil SET $set WHERE NoFba = :no AND StatusPekerjaan = 'BONGKAR'");
    $u->bindValue(':lampu', $x['lampu']);
    $u->bindValue(':posisi', $x['posisi']);
    $u->bindValue(':long', $x['long'], $x['long'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $u->bindValue(':lat', $x['lat'], $x['lat'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    if ($x['f_terpasang'] !== null) $u->bindValue(':fp', $x['f_terpasang'], PDO::PARAM_LOB);
    if ($x['f_barang'] !== null)    $u->bindValue(':fb', $x['f_barang'], PDO::PARAM_LOB);
    $u->bindValue(':no', $no_fba);
    $u->execute();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'eksekusi_ba') {
    csrf_verify();
    try {
        $no_fpk = trim((string) ($_POST['no_fpk'] ?? ''));
        $status = in_array($_POST['status_data'] ?? '', PGH_STATUS, true) ? $_POST['status_data'] : '';
        $bk     = ek_baca_sisi('bongkar');

        if ($no_fpk === '' || $status === '') {
            throw new RuntimeException('Data eksekusi tidak lengkap.');
        }
        if ($bk['lampu'] === '' || $bk['posisi'] === '') {
            throw new RuntimeException('Lampu indikator dan posisi wajib diisi.');
        }
        if ($bk['err']) throw new RuntimeException($bk['err'][0]);

        [$sc, $sp] = pgh_scope('p');
        $st = $conn->prepare("SELECT p.NoFpk, p.IdPel, p.UnitUp, p.UnitAp, p.UnitUpi, o.KdPetugas, pd.NoRef AS B_NoRef
                              FROM frm_pk p
                              JOIN frm_order_detil od ON od.NoFpk = p.NoFpk
                              JOIN frm_order o ON o.NoFormOrder = od.NoFormOrder
                              LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk AND pd.StatusBarang = 'DIL'
                              WHERE p.NoFpk = ? AND p.JenisPk = 'BERHENTI'" . $sc . ' LIMIT 1');
        $st->execute(array_merge([$no_fpk], $sp));
        $pk = $st->fetch(PDO::FETCH_ASSOC);
        if (!$pk) throw new RuntimeException('FPK tidak ditemukan, belum diorderkan, atau di luar unit Anda.');
        if (empty($pk['B_NoRef'])) throw new RuntimeException('FPK ini belum memiliki data perangkat yang akan dibongkar.');

        $st = $conn->prepare('SELECT NoRef, SimId, ImeiModem, IdPel, StatusData FROM master_barang WHERE NoRef = ?');
        $st->execute([$pk['B_NoRef']]);
        $dev = $st->fetch(PDO::FETCH_ASSOC);
        if (!$dev) throw new RuntimeException('Perangkat tidak ditemukan di master barang.');
        $melekat = trim((string) $dev['IdPel']);
        if ($melekat !== '' && $melekat !== (string) $pk['IdPel']) {
            throw new RuntimeException('Perangkat tercatat terpasang di pelanggan lain.');
        }
        $kontak = pgh_nomor_sim($conn, $dev['SimId']);

        $conn->beginTransaction();

        // Berita Acara (header)
        $st = $conn->prepare("SELECT NoFba, StatusData FROM frm_ba WHERE NoFpk = ? AND JenisPekerjaan = 'BONGKAR' LIMIT 1");
        $st->execute([$no_fpk]);
        $ba_lama     = $st->fetch(PDO::FETCH_ASSOC);
        $no_fba      = $ba_lama ? $ba_lama['NoFba'] : false;
        $status_lama = $ba_lama ? (string) $ba_lama['StatusData'] : '';
        if ($no_fba === false) {
            $no_fba = pgh_no_form($conn, 'frm_ba', 'NoFba', $pk['UnitUp'], date('Y-m-d'), '-F.G');
            $conn->prepare("INSERT INTO frm_ba (NoFba, TglFba, JenisPekerjaan, KdPetugas, NoFpk, IdPel, NamaAkun, UnitUp, UnitAp, UnitUpi, StatusData, WaktuData)
                            VALUES (?, ?, 'BONGKAR', ?, ?, ?, ?, ?, ?, ?, 'TIDAK', NOW())")
                 ->execute([$no_fba, date('Y-m-d'), $pk['KdPetugas'], $no_fpk, $pk['IdPel'], $nama_akun, $pk['UnitUp'], $pk['UnitAp'], $pk['UnitUpi']]);
        }

        // Detil BA: hanya sisi BONGKAR
        ek_simpan_bongkar($conn, $no_fba, $dev, $kontak, $bk);

        // Master barang mengikuti status BA (aman diulang saat Ubah BA).
        // Pasang-kembali HANYA bila BA sebelumnya sudah SELESAI (alat sudah dilepas). BA yang baru disimpan sebagai
        // PENDING tidak boleh menyentuh master_barang.StatusData, karena kolom itu juga diubah trigger tiket (putus/nyala).
        if ($status === 'AKTIF') {
            pgh_lepas_barang($conn, (string) $dev['NoRef'], (string) $pk['IdPel']);
        } elseif ($status_lama === 'AKTIF') {
            pgh_pasang_kembali_barang($conn, (string) $dev['NoRef'], (string) $pk['IdPel']);
        }

        $conn->prepare('UPDATE frm_ba SET StatusData = ?, NamaAkun = ?, WaktuData = NOW() WHERE NoFba = ?')
             ->execute([$status, $nama_akun, $no_fba]);
        $conn->prepare('UPDATE frm_pk SET StatusData = ?, WaktuData = NOW() WHERE NoFpk = ?')
             ->execute([$status, $no_fpk]);

        $conn->commit();
        pgh_redirect($sub_ini, 'sukses', "Berita Acara $no_fba berhasil disimpan" . ($status === 'AKTIF' ? ' dan alat telah dilepas dari pelanggan.' : ' (status pending).'));
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        error_log('penghapusan eksekusi: ' . $e->getMessage());
        $pesan = pgh_duplikat($e)
            ? 'Data Berita Acara ini bentrok dengan data yang sudah ada.' . (pgh_role() === 'SA.KP' ? ' Detail: ' . $e->getMessage() : '')
            : pgh_pesan_error($e);
        pgh_redirect($sub_ini, 'gagal', 'Gagal menyimpan eksekusi: ' . $pesan);
    }
}

$pesan_flash = pgh_flash_html();
$pesan_error = '';
$data_eks = [];
$total_hasil = 0; $hal = 1; $total_hal = 1; $q = '';

try {
    $alasan_sql = pgh_sql_alasan($conn, 'p');
    [$data_eks, $total_hasil, $hal, $total_hal, $q] = pgh_daftar(
        $conn,
        "o.NoFormOrder, o.KdPetugas AS PetugasOrder, p.NoFpk, p.TglFpk, p.IdPel, p.LongDil, p.LatDil, $alasan_sql AS Alasan,
         pd.NoRef AS BNoRef, pd.SimId AS BSim, pd.ImeiModem AS BImei, pd.NomorBarang AS BKontak,
         b.NoFba, b.StatusData AS StatusBa,
         bd.LampuIndikator AS BLampu, bd.PosisiPasang AS BPosisi, bd.`Long` AS BLong, bd.Lat AS BLat,
         (bd.FotoTerpasang IS NOT NULL) AS BFotoP, (bd.FotoBarang IS NOT NULL) AS BFotoB",
        "frm_order o
         JOIN frm_order_detil od ON od.NoFormOrder = o.NoFormOrder
         JOIN frm_pk p ON p.NoFpk = od.NoFpk
         LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk AND pd.StatusBarang = 'DIL'
         LEFT JOIN frm_ba b ON b.NoFpk = p.NoFpk AND b.JenisPekerjaan = 'BONGKAR'
         LEFT JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.StatusPekerjaan = 'BONGKAR'
         LEFT JOIN master_modem mm ON mm.ImeiModem = pd.ImeiModem",
        "AND p.JenisPk = 'BERHENTI'",
        [],
        ['alias' => 'p', 'urut' => 'o.TglOrder DESC, o.NoFormOrder DESC, p.NoFpk DESC',
         'cari'  => pgh_kolom_cari($conn, true, true, ['pd.NoRef', 'pd.SimId', 'pd.ImeiModem'])]
    );
} catch (PDOException $e) {
    error_log('penghapusan eksekusi_mutasi: ' . $e->getMessage());
    $pesan_error = 'Gagal memuat data eksekusi.' . (pgh_role() === 'SA.KP' ? ' Detail: ' . $e->getMessage() : '');
}

function ek_radio(string $nama, array $opsi): string
{
    $h = '';
    foreach ($opsi as $nilai => $label) {
        $id = $nama . '_' . strtolower($nilai);
        $h .= '<div class="form-check form-check-inline mb-0">'
            . '<input class="form-check-input" type="radio" name="' . pgh_e($nama) . '" id="' . pgh_e($id) . '" value="' . pgh_e($nilai) . '" required>'
            . '<label class="form-check-label small" for="' . pgh_e($id) . '">' . pgh_e($label) . '</label></div>';
    }
    return $h;
}
$opsi_posisi = ['PANGKAL' => 'Pangkal', 'TENGAH' => 'Tengah', 'UJUNG' => 'Ujung'];
$opsi_lampu  = ['MENYALA' => 'Menyala', 'TIDAK' => 'Tidak Menyala'];
?>

<style>
    .pln-form-box { border: 1.5px solid #212529; font-size: 0.85rem; }
    .pln-form-box th, .pln-form-box td { border: 1px solid #212529 !important; padding: 6px 10px; vertical-align: middle; }
</style>

<?= $pesan_flash ?>
<?php if ($pesan_error): ?>
    <div class="alert alert-danger rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= pgh_e($pesan_error) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 bg-white">
    <?= pgh_card_header('Daftar Order Penghapusan untuk Dieksekusi', $sub_ini, $q) ?>
    <div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 ph-table">
            <thead class="table-light text-secondary text-uppercase ph-thead">
                <tr>
                    <th class="ps-4" style="width:40px;">#</th>
                    <th>NO ORDER / PETUGAS</th>
                    <th>NO FPK / NO BA</th>
                    <th>IDPEL / NO REF</th>
                    <th>SIM ID</th>
                    <th>MODEM</th>
                    <th>INDIKATOR</th>
                    <th>STATUS</th>
                    <th class="text-end pe-4" style="width:130px;">AKSI</th>
                </tr>
            </thead>
            <tbody class="fw-medium text-dark">
                <?php if (empty($data_eks)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-5"><?= $q !== '' ? 'Tidak ada data yang cocok dengan pencarian.' : 'Belum ada FPK penghapusan yang diorderkan.' ?></td></tr>
                <?php else: ?>
                    <?php foreach ($data_eks as $idx => $row): ?>
                        <?php
                        $ada_ba = !empty($row['NoFba']);
                        $js = [
                            'nofpk' => $row['NoFpk'], 'nofba' => $row['NoFba'] ?? '', 'order' => $row['NoFormOrder'], 'idpel' => $row['IdPel'],
                            'alasan' => $row['Alasan'] ?? '',
                            'bk' => [
                                'noref' => $row['BNoRef'] ?? '', 'sim' => $row['BSim'] ?? '', 'imei' => $row['BImei'] ?? '', 'kontak' => $row['BKontak'] ?? '',
                                'lampu' => $row['BLampu'] ?? '', 'posisi' => $row['BPosisi'] ?? '',
                                'long' => $row['BLong'] ?? ($row['LongDil'] ?? ''), 'lat' => $row['BLat'] ?? ($row['LatDil'] ?? ''),
                                'fotop' => !empty($row['BFotoP']) ? 1 : 0, 'fotob' => !empty($row['BFotoB']) ? 1 : 0,
                            ],
                        ];
                        ?>
                        <tr class="border-bottom">
                            <td class="ps-4 text-muted small"><?= ($hal - 1) * PGH_PER_HALAMAN + $idx + 1 ?></td>
                            <td class="font-monospace"><?= pgh_e($row['NoFormOrder']) ?><br><small class="text-muted"><?= pgh_e($row['PetugasOrder'] ?? '-') ?></small></td>
                            <td>
                                <div class="fw-bold text-dark font-monospace"><?= pgh_e($row['NoFpk']) ?></div>
                                <div class="small text-primary font-monospace">BA: <?= pgh_e($row['NoFba'] ?? '-') ?></div>
                            </td>
                            <td>IDPEL: <?= pgh_e($row['IdPel']) ?><br><small class="text-muted">BONGKAR: <?= pgh_e($row['BNoRef'] ?? '-') ?></small></td>
                            <td class="font-monospace"><span class="badge bg-info bg-opacity-10 text-info"><?= pgh_e($row['BSim'] ?? '-') ?></span></td>
                            <td class="font-monospace"><?= pgh_e($row['BImei'] ?? '-') ?></td>
                            <td><?= pgh_badge_relay($row['BLampu'] ?? '') ?></td>
                            <td><?= $ada_ba ? pgh_badge_status($row['StatusBa']) : '<span class="badge bg-primary-subtle text-primary">BELUM</span>' ?></td>
                            <td class="text-end pe-4">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-3 px-3 py-1 fw-bold btn-eksekusi"
                                        data-row="<?= pgh_e(json_encode($js, JSON_UNESCAPED_UNICODE)) ?>"
                                        data-status="<?= pgh_e($row['StatusBa'] ?? 'TIDAK') ?>">
                                    <i class="bi bi-tools me-1"></i> <?= $ada_ba ? 'Ubah BA' : 'Eksekusi' ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </div>
    <?= pgh_pager($sub_ini, $total_hasil, $hal, $total_hal, $q) ?>
</div>

<!-- MODAL EKSEKUSI: hanya sisi BONGKAR -->
<div class="modal fade" id="modalEksekusi" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-bottom bg-white py-3 px-4">
                <div class="row w-100 align-items-center">
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-0 text-dark small">PT PLN (PERSERO) UID BANTEN</h6>
                        <span class="fw-bold text-danger">EKSEKUSI PENGHAPUSAN &amp; BERITA ACARA</span>
                    </div>
                    <div class="col-md-5 text-center"><h5 class="fw-bold text-dark mb-0">FORMULIR PERINTAH KERJA</h5></div>
                    <div class="col-md-1 text-end"><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                </div>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="eksekusi_ba">
                <input type="hidden" name="no_fpk" id="eks_nofpk">
                <div class="modal-body p-4 text-start bg-white">
                    <table class="table pln-form-box mb-3 w-100">
                        <tr>
                            <td class="bg-light fw-bold" style="width:22%;">Jenis PK</td>
                            <td style="width:28%;"><span class="badge bg-danger px-2 py-1 fw-bold">PENGHAPUSAN</span></td>
                            <td class="bg-light fw-bold" style="width:22%;">Nomor Form (FPK)</td>
                            <td style="width:28%;" class="font-monospace"><span id="eks_t_nofpk"></span></td>
                        </tr>
                        <tr>
                            <td class="bg-light fw-bold">Id Pelanggan</td>
                            <td class="fw-bold"><span id="eks_t_idpel"></span></td>
                            <td class="bg-light fw-bold">No Order / No BA</td>
                            <td class="font-monospace small"><span id="eks_t_order"></span> / <span id="eks_t_nofba"></span></td>
                        </tr>
                        <tr>
                            <td class="bg-light fw-bold">Alasan</td>
                            <td colspan="3"><span id="eks_t_alasan" class="small">-</span></td>
                        </tr>
                    </table>

                    <table class="table pln-form-box text-center mb-3 w-100">
                        <thead><tr class="bg-light"><th style="width:22%;"></th><th>BONGKAR <span class="small fw-normal text-muted">(alat dihapus dari pelanggan)</span></th></tr></thead>
                        <tbody>
                            <tr><td class="bg-light text-start fw-bold small">No Ref S41</td><td><span id="bgNoRef" class="font-monospace fw-bold text-danger">-</span></td></tr>
                            <tr><td class="bg-light text-start fw-bold small">Nomor SIM</td><td><span id="bgSim" class="font-monospace small">-</span></td></tr>
                            <tr><td class="bg-light text-start fw-bold small">IMEI Modem</td><td><span id="bgImei" class="font-monospace small">-</span></td></tr>
                            <tr><td class="bg-light text-start fw-bold small">Nomor Kontak</td><td><span id="bgKontak" class="font-monospace small">-</span></td></tr>
                            <tr>
                                <td class="bg-light text-start fw-bold small">Tikor</td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <span class="small">Long:</span>
                                        <input type="text" name="bongkar_longitude" id="bongkar_long" class="form-control form-control-sm font-monospace" style="max-width:150px;" autocomplete="off" inputmode="decimal" placeholder="106.62319">
                                        <span class="small">Lat:</span>
                                        <input type="text" name="bongkar_latitude" id="bongkar_lat" class="form-control form-control-sm font-monospace" style="max-width:150px;" autocomplete="off" inputmode="decimal" placeholder="-6.17698">
                                    </div>
                                </td>
                            </tr>
                            <tr><td class="bg-light text-start fw-bold small">Posisi *</td><td><?= ek_radio('bongkar_posisi', $opsi_posisi) ?></td></tr>
                            <tr><td class="bg-light text-start fw-bold small">Lampu indikator *</td><td><?= ek_radio('bongkar_lampu', $opsi_lampu) ?></td></tr>
                            <tr>
                                <td class="bg-light text-start fw-bold small">Foto Barang</td>
                                <td>
                                    <input type="file" name="bongkar_foto_barang" class="form-control form-control-sm" accept="image/*">
                                    <div class="small text-success fst-italic mt-1 d-none" id="bongkar_ada_fotob"><i class="bi bi-check-circle me-1"></i>Foto sudah dilampirkan (kosongkan jika tidak diganti)</div>
                                </td>
                            </tr>
                            <tr>
                                <td class="bg-light text-start fw-bold small">Foto Terpasang</td>
                                <td>
                                    <input type="file" name="bongkar_foto_terpasang" class="form-control form-control-sm" accept="image/*">
                                    <div class="small text-success fst-italic mt-1 d-none" id="bongkar_ada_fotop"><i class="bi bi-check-circle me-1"></i>Foto sudah dilampirkan (kosongkan jika tidak diganti)</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="alert alert-light border small py-2 mb-3">
                        <i class="bi bi-info-circle me-1 text-primary"></i>
                        Status <b>AKTIF (SELESAI)</b>: alat dilepas dari pelanggan dan dinonaktifkan di master barang. Status <b>TIDAK / PENDING</b>: alat tetap terpasang di pelanggan.
                    </div>

                    <label class="form-label text-uppercase text-muted fw-bold small mb-1">STATUS PENGHAPUSAN *</label>
                    <select name="status_data" id="eks_status" class="form-select rounded-3" required>
                        <option value="AKTIF">AKTIF (SELESAI)</option>
                        <option value="TIDAK">TIDAK / PENDING</option>
                    </select>
                </div>
                <div class="modal-footer border-top p-3 justify-content-end gap-2 bg-light">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary fw-bold rounded-3 px-4">Simpan Berita Acara</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    var modalEl = document.getElementById("modalEksekusi");
    var modal = (modalEl && window.bootstrap) ? new bootstrap.Modal(modalEl) : null;
    var el = function (id) { return document.getElementById(id); };
    var teks = function (id, v) { el(id).textContent = (v === undefined || v === null || v === "") ? "-" : v; };
    var setRadio = function (nama, nilai) {
        document.querySelectorAll('input[name="' + nama + '"]').forEach(function (r) { r.checked = (r.value === nilai); });
    };

    document.querySelectorAll(".btn-eksekusi").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var d = JSON.parse(this.dataset.row);
            el("eks_nofpk").value = d.nofpk;
            teks("eks_t_order", d.order); teks("eks_t_nofpk", d.nofpk); teks("eks_t_idpel", d.idpel);
            teks("eks_t_nofba", d.nofba || "(otomatis)"); teks("eks_t_alasan", d.alasan);
            teks("bgNoRef", d.bk.noref); teks("bgSim", d.bk.sim); teks("bgImei", d.bk.imei); teks("bgKontak", d.bk.kontak);

            setRadio("bongkar_posisi", d.bk.posisi || "PANGKAL");
            setRadio("bongkar_lampu", d.bk.lampu === "TIDAK" ? "TIDAK" : "MENYALA");
            el("bongkar_long").value = d.bk.long || ""; el("bongkar_lat").value = d.bk.lat || "";
            el("bongkar_ada_fotob").classList.toggle("d-none", !d.bk.fotob);
            el("bongkar_ada_fotop").classList.toggle("d-none", !d.bk.fotop);
            modalEl.querySelectorAll('input[type="file"]').forEach(function (f) { f.value = ""; });

            el("eks_status").value = this.dataset.status === "AKTIF" ? "AKTIF" : "TIDAK";
            if (modal) modal.show();
        });
    });
});
</script>
