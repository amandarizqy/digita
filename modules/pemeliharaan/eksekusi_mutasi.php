<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    require_once __DIR__ . '/../../config/database.php';
}
require_once __DIR__ . '/../../functions/pemeliharaan_helper.php';

if (!ph_akses_mutasi()) {
    echo '<div class="alert alert-warning">Anda tidak memiliki akses ke Mutasi Pemeliharaan.</div>';
    return;
}

$sub_ini   = 'mutasi_pemeliharaan';
$nama_akun = substr(trim((string) ($_SESSION['NamaAkun'] ?? '')), 0, 20);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'eksekusi_ba') {
    csrf_verify();
    try {
        $no_fpk    = trim((string) ($_POST['no_fpk'] ?? ''));
        $lampu     = array_key_exists($_POST['lampu'] ?? '', PH_LAMPU) ? $_POST['lampu'] : '';
        $posisi    = in_array($_POST['posisi_pasang'] ?? '', PH_POSISI, true) ? $_POST['posisi_pasang'] : '';
        $status    = in_array($_POST['status_data'] ?? '', PH_STATUS, true) ? $_POST['status_data'] : '';
        $sim_baru  = trim((string) ($_POST['sim_id'] ?? ''));
        $imei_baru = trim((string) ($_POST['imei_modem'] ?? ''));
        [$lat, $long, $err_k] = ph_koordinat($_POST['latitude'] ?? '', $_POST['longitude'] ?? '');
        [$foto_pasang, $err_f1] = ph_foto_baca('foto_pasang');
        [$foto_barang, $err_f2] = ph_foto_baca('foto_barang');

        if ($no_fpk === '' || $lampu === '' || $posisi === '' || $status === '') {
            throw new RuntimeException('Data eksekusi tidak lengkap.');
        }
        foreach ([$err_k, $err_f1, $err_f2] as $e_) {
            if ($e_ !== null) throw new RuntimeException($e_);
        }

        [$sc, $sp] = ph_scope('p');
        $st = $conn->prepare("SELECT p.NoFpk, p.IdPel, p.UnitUp, p.UnitAp, p.UnitUpi, o.KdPetugas, pd.NoRef, pd.NomorBarang, pd.NomorModem
                              FROM frm_pk p
                              JOIN frm_order_detil od ON od.NoFpk = p.NoFpk
                              JOIN frm_order o ON o.NoFormOrder = od.NoFormOrder
                              LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk AND pd.StatusBarang = 'DIL'
                              WHERE p.NoFpk = ? AND p.JenisPk = 'PEMELIHARAAN'" . $sc . ' LIMIT 1');
        $st->execute(array_merge([$no_fpk], $sp));
        $pk = $st->fetch(PDO::FETCH_ASSOC);
        if (!$pk) throw new RuntimeException('FPK tidak ditemukan atau di luar unit Anda.');

        $st = $conn->prepare('SELECT SimId, ImeiModem FROM master_barang WHERE NoRef = ?');
        $st->execute([$pk['NoRef']]);
        $cur = $st->fetch(PDO::FETCH_ASSOC);
        if (!$cur) throw new RuntimeException('Perangkat tidak ditemukan di master.');

        $st = $conn->prepare("SELECT NoFba FROM frm_ba WHERE NoFpk = ? AND JenisPekerjaan = 'PEMELIHARAAN' LIMIT 1");
        $st->execute([$no_fpk]);
        $no_fba = $st->fetchColumn();

        $mau_ganti = ($sim_baru !== '' || $imei_baru !== '');
        if ($mau_ganti) {
            if ($sim_baru !== '') {
                $c = $conn->prepare("SELECT COUNT(*) FROM master_nomor WHERE SimId = ? AND StatusData = 'AKTIF'");
                $c->execute([$sim_baru]);
                if (!(int) $c->fetchColumn()) throw new RuntimeException('SIM baru tidak aktif.');
            }
            if ($imei_baru !== '') {
                $c = $conn->prepare("SELECT COUNT(*) FROM master_modem WHERE ImeiModem = ? AND StatusData = 'AKTIF'");
                $c->execute([$imei_baru]);
                if (!(int) $c->fetchColumn()) throw new RuntimeException('Modem baru tidak aktif.');
            }
        }
        $sim_akhir  = $sim_baru !== '' ? $sim_baru : $cur['SimId'];
        $imei_akhir = $imei_baru !== '' ? $imei_baru : $cur['ImeiModem'];

        $kontak_s41   = trim((string) $pk['NomorBarang']) !== '' ? $pk['NomorBarang'] : ph_nomor_sim($conn, $cur['SimId']);
        $kontak_modem = trim((string) $pk['NomorModem']) !== '' ? $pk['NomorModem'] : $kontak_s41;

        $conn->beginTransaction();

        if ($no_fba === false) {
            $no_fba = ph_no_form($conn, 'frm_ba', 'NoFba', $pk['UnitUp'], date('Y-m-d'), '-F.G');
            $conn->prepare("INSERT INTO frm_ba (NoFba, TglFba, JenisPekerjaan, KdPetugas, NoFpk, IdPel, NamaAkun, UnitUp, UnitAp, UnitUpi, StatusData, WaktuData)
                            VALUES (?, ?, 'PEMELIHARAAN', ?, ?, ?, ?, ?, ?, ?, 'TIDAK', NOW())")
                 ->execute([$no_fba, date('Y-m-d'), $pk['KdPetugas'], $no_fpk, $pk['IdPel'], $nama_akun, $pk['UnitUp'], $pk['UnitAp'], $pk['UnitUpi']]);
            $conn->prepare("INSERT INTO frm_ba_detil (NoFba, StatusPekerjaan, NoRef, SimId, ImeiModem, NomorBarang, NomorModem) VALUES (?, 'PASANG', ?, ?, ?, ?, ?)")
                 ->execute([$no_fba, $pk['NoRef'], $cur['SimId'], $cur['ImeiModem'], $kontak_s41, $kontak_modem]);
        }

        $set = 'LampuIndikator = :lampu, PosisiPasang = :posisi, `Long` = :long, Lat = :lat';
        if ($foto_pasang !== null) $set .= ', FotoTerpasang = :fp';
        if ($foto_barang !== null) $set .= ', FotoBarang = :fb';

        $u = $conn->prepare("UPDATE frm_ba_detil SET $set WHERE NoFba = :no AND StatusPekerjaan = 'PASANG'");
        $u->bindValue(':lampu', $lampu);
        $u->bindValue(':posisi', $posisi);
        $u->bindValue(':long', $long, $long === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $u->bindValue(':lat', $lat, $lat === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        if ($foto_pasang !== null) $u->bindValue(':fp', $foto_pasang, PDO::PARAM_LOB);
        if ($foto_barang !== null) $u->bindValue(':fb', $foto_barang, PDO::PARAM_LOB);
        $u->bindValue(':no', $no_fba);
        $u->execute();

        if ($mau_ganti) {
            $conn->prepare("INSERT INTO frm_ba_detil (NoFba, StatusPekerjaan, NoRef, SimId, ImeiModem, NomorBarang, NomorModem) VALUES (?, 'BONGKAR', ?, ?, ?, ?, ?)")
                 ->execute([$no_fba, $pk['NoRef'], $cur['SimId'], $cur['ImeiModem'], $kontak_s41, $kontak_modem]);

            $kontak_baru = ($sim_baru !== '') ? ph_nomor_sim($conn, $sim_baru) : null;
            $conn->prepare("UPDATE frm_ba_detil SET SimId = ?, ImeiModem = ?, NomorBarang = ?, NomorModem = ? WHERE NoFba = ? AND StatusPekerjaan = 'PASANG'")
                 ->execute([$sim_akhir, $imei_akhir, $kontak_baru ?? $kontak_s41, $kontak_baru ?? $kontak_modem, $no_fba]);
            $conn->prepare('UPDATE master_barang SET SimId = ?, ImeiModem = ? WHERE NoRef = ?')
                 ->execute([$sim_akhir, $imei_akhir, $pk['NoRef']]);
        }

        $conn->prepare('UPDATE frm_ba SET StatusData = ?, NamaAkun = ?, WaktuData = NOW() WHERE NoFba = ?')
             ->execute([$status, $nama_akun, $no_fba]);
        $conn->prepare('UPDATE frm_pk SET StatusData = ?, WaktuData = NOW() WHERE NoFpk = ?')
             ->execute([$status, $no_fpk]);

        $conn->commit();
        ph_redirect($sub_ini, 'sukses', "Berita Acara $no_fba berhasil disimpan.");
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        error_log('pemeliharaan eksekusi: ' . $e->getMessage());
        $pesan = ph_duplikat($e) ? 'BA ini sudah pernah dimutasi; perangkat tidak dapat diganti lagi.' : ph_pesan_error($e);
        ph_redirect($sub_ini, 'gagal', 'Gagal menyimpan eksekusi: ' . $pesan);
    }
}

$pesan_flash = ph_flash_html();
$pesan_error = '';
$data_eks = [];
$total_hasil = 0; $hal = 1; $total_hal = 1; $q = '';
$list_sim = $list_modem = [];

try {
    $nama_sql = ph_sql_nama_modem($conn);
    [$data_eks, $total_hasil, $hal, $total_hal, $q] = ph_daftar(
        $conn,
        "o.NoFormOrder, o.KdPetugas AS PetugasOrder, p.NoFpk, p.TglFpk, p.IdPel, p.LongDil, p.LatDil, pd.NoRef,
         COALESCE(bd.SimId, mb.SimId) AS SimId, COALESCE(bd.ImeiModem, mb.ImeiModem) AS ImeiModem, $nama_sql AS NamaModem,
         b.NoFba, b.StatusData AS StatusBa, bd.LampuIndikator, bd.PosisiPasang, bd.`Long` AS Longitude, bd.Lat AS Latitude,
         (bk.NoFba IS NOT NULL) AS SudahMutasi, bk.SimId AS SimLama, bk.ImeiModem AS ImeiLama,
         (bd.FotoTerpasang IS NOT NULL) AS AdaFotoPasang, (bd.FotoBarang IS NOT NULL) AS AdaFotoBarang",
        "frm_order o
         JOIN frm_order_detil od ON od.NoFormOrder = o.NoFormOrder
         JOIN frm_pk p ON p.NoFpk = od.NoFpk
         LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk AND pd.StatusBarang = 'DIL'
         LEFT JOIN master_barang mb ON mb.NoRef = pd.NoRef
         LEFT JOIN frm_ba b ON b.NoFpk = p.NoFpk AND b.JenisPekerjaan = 'PEMELIHARAAN'
         LEFT JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.StatusPekerjaan = 'PASANG'
         LEFT JOIN frm_ba_detil bk ON bk.NoFba = b.NoFba AND bk.StatusPekerjaan = 'BONGKAR'
         LEFT JOIN master_modem mm ON mm.ImeiModem = COALESCE(bd.ImeiModem, mb.ImeiModem)",
        "AND p.JenisPk = 'PEMELIHARAAN'",
        [],
        ['alias' => 'p', 'urut' => 'o.TglOrder DESC, o.NoFormOrder DESC, p.NoFpk DESC',
         'cari'  => ph_kolom_cari($conn, true, true, ['pd.NoRef', 'COALESCE(bd.SimId, mb.SimId)', 'COALESCE(bd.ImeiModem, mb.ImeiModem)'])]
    );

    $list_sim = $conn->query("SELECT n.SimId, n.NomorAkun FROM master_nomor n WHERE n.StatusData = 'AKTIF' AND n.SimId NOT IN (SELECT SimId FROM master_barang WHERE SimId IS NOT NULL AND StatusData = 'AKTIF') ORDER BY n.SimId ASC")->fetchAll(PDO::FETCH_ASSOC);
    $nm = ph_sql_nama_modem($conn, 'm');
    $list_modem = $conn->query("SELECT m.ImeiModem, $nm AS NamaModem FROM master_modem m WHERE m.StatusData = 'AKTIF' ORDER BY m.ImeiModem ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pesan_error = 'Gagal memuat data eksekusi.';
}
?>

<style>
    .table-compact thead th { font-size: 0.75rem !important; font-weight: 700 !important; color: #6c757d !important; padding: 0.6rem 0.35rem !important; }
    .table-compact tbody td { padding: 0.5rem 0.35rem !important; font-size: 0.81rem !important; }
</style>

<?= $pesan_flash ?>
<?php if ($pesan_error): ?>
    <div class="alert alert-danger rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= ph_e($pesan_error) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 bg-white p-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 px-2 pt-2">
        <div>
            <h5 class="fw-bold text-primary mb-0">Eksekusi Mutasi Pemeliharaan</h5>
            <p class="text-muted small mb-0">Langkah 3: Kerjakan order lapangan, rekam kondisi lampu indikator, foto, dan mutasi perangkat.</p>
        </div>
        <?= ph_kotak_cari($sub_ini, $q) ?>
    </div>

    <div class="w-100 table-responsive">
        <table class="table table-hover align-middle mb-0 table-compact w-100">
            <thead class="table-light border-top border-bottom">
                <tr>
                    <th class="ps-2 text-center" style="width:3%;">#</th>
                    <th>NO ORDER / PETUGAS</th>
                    <th>NO FPK / NO BA</th>
                    <th>IDPEL / NO REF</th>
                    <th>SIM ID</th>
                    <th>MODEM</th>
                    <th>INDIKATOR</th>
                    <th>STATUS</th>
                    <th class="text-center" style="width:120px;">AKSI</th>
                </tr>
            </thead>
            <tbody class="fw-medium text-dark">
                <?php if (empty($data_eks)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-5">Belum ada FPK diorderkan.</td></tr>
                <?php else: ?>
                    <?php foreach ($data_eks as $idx => $row): ?>
                        <?php $sudah = !empty($row['SudahMutasi']); ?>
                        <tr class="border-bottom">
                            <td class="ps-2 text-center text-muted small"><?= ($hal - 1) * PH_PER_HALAMAN + $idx + 1 ?></td>
                            <td class="font-monospace"><?= ph_e($row['NoFormOrder']) ?><br><small class="text-muted"><?= ph_e($row['PetugasOrder'] ?? '-') ?></small></td>
                            <td>
                                <div class="fw-bold text-dark font-monospace"><?= ph_e($row['NoFpk']) ?></div>
                                <div class="small text-primary font-monospace">BA: <?= ph_e($row['NoFba'] ?? '-') ?></div>
                            </td>
                            <td>IDPEL: <?= ph_e($row['IdPel']) ?><br><small class="text-muted">REF: <?= ph_e($row['NoRef'] ?? '-') ?></small></td>
                            <td class="font-monospace"><span class="badge bg-info bg-opacity-10 text-info"><?= ph_e($row['SimId'] ?? '-') ?></span></td>
                            <td class="font-monospace"><?= ph_e($row['ImeiModem'] ?? '-') ?></td>
                            <td><?= ph_badge_relay($row['LampuIndikator'] ?? '') ?></td>
                            <td><?= empty($row['NoFba']) ? '<span class="badge bg-primary-subtle text-primary">BELUM</span>' : ph_badge_status($row['StatusBa']) ?></td>
                            <td class="pe-2 text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-3 px-3 py-1 fw-bold btn-eksekusi"
                                        data-nofpk="<?= ph_e($row['NoFpk']) ?>" data-nofba="<?= ph_e($row['NoFba'] ?? '') ?>"
                                        data-order="<?= ph_e($row['NoFormOrder']) ?>" data-idpel="<?= ph_e($row['IdPel']) ?>"
                                        data-noref="<?= ph_e($row['NoRef'] ?? '-') ?>" data-sim="<?= ph_e($row['SimId'] ?? '-') ?>"
                                        data-imei="<?= ph_e($row['ImeiModem'] ?? '-') ?>" data-lampu="<?= ph_e($row['LampuIndikator'] ?? '') ?>"
                                        data-posisi="<?= ph_e($row['PosisiPasang'] ?? '') ?>" data-long="<?= ph_e($row['Longitude'] ?? ($row['LongDil'] ?? '')) ?>"
                                        data-lat="<?= ph_e($row['Latitude'] ?? ($row['LatDil'] ?? '')) ?>" data-status="<?= ph_e($row['StatusBa'] ?? 'TIDAK') ?>"
                                        data-mutasi="<?= $sudah ? '1' : '0' ?>" data-fotop="<?= !empty($row['AdaFotoPasang']) ? '1' : '0' ?>"
                                        data-fotob="<?= !empty($row['AdaFotoBarang']) ? '1' : '0' ?>">
                                    <i class="bi bi-tools me-1"></i> <?= empty($row['NoFba']) ? 'Eksekusi' : 'Ubah BA' ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= ph_pager($sub_ini, $total_hasil, $hal, $total_hal, $q) ?>
</div>

<!-- MODAL EKSEKUSI -->
<div class="modal fade" id="modalEksekusi" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-tools me-2 text-primary"></i>Eksekusi Pemeliharaan &amp; Berita Acara</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="eksekusi_ba">
                <input type="hidden" name="no_fpk" id="eks_nofpk">
                <div class="modal-body p-4 text-start">
                    <div class="alert alert-light border rounded-3 mb-3 p-3">
                        <div class="row g-2 small">
                            <div class="col-md-6"><strong>NO ORDER:</strong> <span id="eks_t_order" class="font-monospace"></span></div>
                            <div class="col-md-6"><strong>NO FPK:</strong> <span id="eks_t_nofpk" class="font-monospace"></span></div>
                            <div class="col-md-6"><strong>ID PELANGGAN:</strong> <span id="eks_t_idpel"></span></div>
                            <div class="col-md-6"><strong>NO BA:</strong> <span id="eks_t_nofba" class="font-monospace"></span></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">LAMPU INDIKATOR *</label>
                            <select name="lampu" id="eks_lampu" class="form-select rounded-3" required>
                                <?php foreach (PH_LAMPU as $nilai => $label): ?>
                                    <option value="<?= ph_e($nilai) ?>"><?= ph_e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">POSISI PASANG *</label>
                            <select name="posisi_pasang" id="eks_posisi" class="form-select rounded-3" required>
                                <?php foreach (PH_POSISI as $pos): ?>
                                    <option value="<?= ph_e($pos) ?>"><?= ph_e($pos) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">LONGITUDE</label>
                            <input type="text" name="longitude" id="eks_long" class="form-control rounded-3 font-monospace" autocomplete="off" inputmode="decimal" placeholder="contoh: 106.62319 (-180 s/d 180)">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">LATITUDE</label>
                            <input type="text" name="latitude" id="eks_lat" class="form-control rounded-3 font-monospace" autocomplete="off" inputmode="decimal" placeholder="contoh: -6.17698 (-90 s/d 90)">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">FOTO PASANG</label>
                            <input type="file" name="foto_pasang" class="form-control rounded-3" accept="image/*">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">FOTO BARANG</label>
                            <input type="file" name="foto_barang" class="form-control rounded-3" accept="image/*">
                        </div>
                    </div>

                    <div class="border rounded-3 p-3 mb-3 bg-light">
                        <div class="fw-bold small text-uppercase text-muted mb-2"><i class="bi bi-arrow-left-right me-1"></i>Mutasi Perangkat (Opsional)</div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small mb-1">SIM card baru</label>
                                <select name="sim_id" id="mutSim" class="form-select rounded-3" style="width:100%;">
                                    <option value="">-- Tidak diganti --</option>
                                    <?php foreach ($list_sim as $s): ?>
                                        <option value="<?= ph_e($s['SimId']) ?>"><?= ph_e($s['SimId']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">Modem baru</label>
                                <select name="imei_modem" id="mutImei" class="form-select rounded-3" style="width:100%;">
                                    <option value="">-- Tidak diganti --</option>
                                    <?php foreach ($list_modem as $m): ?>
                                        <option value="<?= ph_e($m['ImeiModem']) ?>"><?= ph_e($m['ImeiModem']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-1">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">STATUS PEMELIHARAAN *</label>
                        <select name="status_data" id="eks_status" class="form-select rounded-3" required>
                            <option value="AKTIF">AKTIF (SELESAI)</option>
                            <option value="TIDAK">TIDAK / PENDING</option>
                        </select>
                    </div>
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
    var teks = function (id, v) { document.getElementById(id).textContent = v || "-"; };
    var nilai = function (id, v) { document.getElementById(id).value = v || ""; };

    document.querySelectorAll(".btn-eksekusi").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var d = this.dataset;
            nilai("eks_nofpk", d.nofpk);
            teks("eks_t_order", d.order); teks("eks_t_nofpk", d.nofpk); teks("eks_t_idpel", d.idpel);
            teks("eks_t_nofba", d.nofba || "(otomatis)");
            document.getElementById("eks_lampu").value = d.lampu === "TIDAK" ? "TIDAK" : "MENYALA";
            document.getElementById("eks_posisi").value = d.posisi || "PANGKAL";
            nilai("eks_long", d.long); nilai("eks_lat", d.lat);
            document.getElementById("eks_status").value = d.status === "AKTIF" ? "AKTIF" : "TIDAK";
            if (modal) modal.show();
        });
    });
});
</script>