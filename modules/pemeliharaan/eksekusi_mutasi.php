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

/** Membaca isian satu sisi formulir ($s = 'bongkar' | 'pasang'). */
function ek_baca_sisi(string $s): array
{
    $lampu  = array_key_exists($_POST[$s . '_lampu'] ?? '', PH_LAMPU) ? $_POST[$s . '_lampu'] : '';
    $posisi = in_array($_POST[$s . '_posisi'] ?? '', PH_POSISI, true) ? $_POST[$s . '_posisi'] : '';
    [$lat, $long, $err_k] = ph_koordinat($_POST[$s . '_latitude'] ?? '', $_POST[$s . '_longitude'] ?? '');
    [$f_terpasang, $err_f1] = ph_foto_baca($s . '_foto_terpasang');
    [$f_barang, $err_f2]    = ph_foto_baca($s . '_foto_barang');
    return [
        'lampu' => $lampu, 'posisi' => $posisi, 'lat' => $lat, 'long' => $long,
        'f_terpasang' => $f_terpasang, 'f_barang' => $f_barang,
        'err' => array_values(array_filter([$err_k, $err_f1, $err_f2], function ($e) { return $e !== null; })),
    ];
}

/** Menyimpan (insert / update) satu baris frm_ba_detil untuk sisi BONGKAR atau PASANG. */
function ek_simpan_detil(PDO $conn, string $no_fba, string $status, array $dev, ?string $kontak, array $x): void
{
    $st = $conn->prepare('SELECT COUNT(*) FROM frm_ba_detil WHERE NoFba = ? AND StatusPekerjaan = ?');
    $st->execute([$no_fba, $status]);
    if ((int) $st->fetchColumn() === 0) {
        $conn->prepare('INSERT INTO frm_ba_detil (NoFba, StatusPekerjaan, NoRef, SimId, ImeiModem, NomorBarang, NomorModem) VALUES (?, ?, ?, ?, ?, ?, ?)')
             ->execute([$no_fba, $status, $dev['NoRef'], $dev['SimId'], $dev['ImeiModem'], $kontak, $kontak]);
    } else {
        $conn->prepare('UPDATE frm_ba_detil SET NoRef = ?, SimId = ?, ImeiModem = ?, NomorBarang = ?, NomorModem = ? WHERE NoFba = ? AND StatusPekerjaan = ?')
             ->execute([$dev['NoRef'], $dev['SimId'], $dev['ImeiModem'], $kontak, $kontak, $no_fba, $status]);
    }

    $set = 'LampuIndikator = :lampu, PosisiPasang = :posisi, `Long` = :long, Lat = :lat';
    if ($x['f_terpasang'] !== null) $set .= ', FotoTerpasang = :fp';
    if ($x['f_barang'] !== null)    $set .= ', FotoBarang = :fb';
    $u = $conn->prepare("UPDATE frm_ba_detil SET $set WHERE NoFba = :no AND StatusPekerjaan = :st");
    $u->bindValue(':lampu', $x['lampu']);
    $u->bindValue(':posisi', $x['posisi']);
    $u->bindValue(':long', $x['long'], $x['long'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $u->bindValue(':lat', $x['lat'], $x['lat'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    if ($x['f_terpasang'] !== null) $u->bindValue(':fp', $x['f_terpasang'], PDO::PARAM_LOB);
    if ($x['f_barang'] !== null)    $u->bindValue(':fb', $x['f_barang'], PDO::PARAM_LOB);
    $u->bindValue(':no', $no_fba);
    $u->bindValue(':st', $status);
    $u->execute();
}

/**
 * BONGKAR = alat lama, datanya dari FPK dan TIDAK bisa diubah dari formulir.
 * Baris dibuat sekali; saat Ubah BA hanya identitas alat yang disamakan lagi dengan FPK,
 * sedangkan lampu/posisi/tikor/foto yang sudah tercatat tidak ditimpa.
 */
function ek_simpan_bongkar(PDO $conn, string $no_fba, array $dev, ?string $kontak, array $pk, array $ps, bool $diganti): void
{
    $st = $conn->prepare("SELECT COUNT(*) FROM frm_ba_detil WHERE NoFba = ? AND StatusPekerjaan = 'BONGKAR'");
    $st->execute([$no_fba]);
    if ((int) $st->fetchColumn() === 0) {
        $conn->prepare("INSERT INTO frm_ba_detil (NoFba, StatusPekerjaan, NoRef, SimId, ImeiModem, NomorBarang, NomorModem, LampuIndikator, PosisiPasang, `Long`, Lat)
                        VALUES (?, 'BONGKAR', ?, ?, ?, ?, ?, ?, ?, ?, ?)")
             ->execute([$no_fba, $dev['NoRef'], $dev['SimId'], $dev['ImeiModem'], $kontak, $kontak,
                        $diganti ? 'TIDAK' : $ps['lampu'],      // alat yang diganti dianggap perlu mutasi
                        $ps['posisi'],                           // posisi tiang sama dengan alat yang dipasang
                        ($pk['LongDil'] ?? '') !== '' ? $pk['LongDil'] : null,
                        ($pk['LatDil'] ?? '') !== '' ? $pk['LatDil'] : null]);
    } else {
        $conn->prepare("UPDATE frm_ba_detil SET NoRef = ?, SimId = ?, ImeiModem = ?, NomorBarang = ?, NomorModem = ? WHERE NoFba = ? AND StatusPekerjaan = 'BONGKAR'")
             ->execute([$dev['NoRef'], $dev['SimId'], $dev['ImeiModem'], $kontak, $kontak, $no_fba]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'eksekusi_ba') {
    csrf_verify();
    try {
        $no_fpk        = trim((string) ($_POST['no_fpk'] ?? ''));
        $status        = in_array($_POST['status_data'] ?? '', PH_STATUS, true) ? $_POST['status_data'] : '';
        $no_ref_pasang = trim((string) ($_POST['no_ref_pasang'] ?? ''));   // kosong = tidak ada penggantian
        $ps = ek_baca_sisi('pasang');          // BONGKAR (alat lama) tidak diterima dari formulir

        if ($no_fpk === '' || $status === '') {
            throw new RuntimeException('Data eksekusi tidak lengkap.');
        }
        if ($ps['lampu'] === '' || $ps['posisi'] === '') {
            throw new RuntimeException('Lampu indikator dan posisi pada bagian Pasang wajib diisi.');
        }
        if ($ps['err']) throw new RuntimeException('Bagian Pasang: ' . $ps['err'][0]);

        [$sc, $sp] = ph_scope('p');
        $st = $conn->prepare("SELECT p.NoFpk, p.IdPel, p.LongDil, p.LatDil, p.UnitUp, p.UnitAp, p.UnitUpi, o.KdPetugas,
                                     pd.NoRef AS B_NoRef, pp.NoRef AS P_NoRef
                              FROM frm_pk p
                              JOIN frm_order_detil od ON od.NoFpk = p.NoFpk
                              JOIN frm_order o ON o.NoFormOrder = od.NoFormOrder
                              LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk AND pd.StatusBarang = 'DIL'
                              LEFT JOIN frm_pk_detil pp ON pp.NoFpk = p.NoFpk AND pp.StatusBarang = 'STOK'
                              WHERE p.NoFpk = ? AND p.JenisPk = 'PEMELIHARAAN'" . $sc . ' LIMIT 1');
        $st->execute(array_merge([$no_fpk], $sp));
        $pk = $st->fetch(PDO::FETCH_ASSOC);
        if (!$pk) throw new RuntimeException('FPK tidak ditemukan atau di luar unit Anda.');
        if (empty($pk['B_NoRef'])) throw new RuntimeException('FPK ini belum memiliki data perangkat bongkar.');

        $ref_bongkar = (string) $pk['B_NoRef'];
        $ref_rencana = (string) ($pk['P_NoRef'] ?: $ref_bongkar);       // alat pasang yang direncanakan di FPK
        $ref_final   = $no_ref_pasang === '' ? $ref_bongkar : $no_ref_pasang;

        $st = $conn->prepare('SELECT NoRef, SimId, ImeiModem, IdPel, StatusData FROM master_barang WHERE NoRef = ?');
        $st->execute([$ref_bongkar]);
        $dev_bk = $st->fetch(PDO::FETCH_ASSOC);
        if (!$dev_bk) throw new RuntimeException('Perangkat bongkar tidak ditemukan di master.');

        if ($ref_final === $ref_bongkar) {
            $dev_ps = $dev_bk;                                          // tidak ada penggantian: data pasang = data bongkar
        } else {
            $boleh = ($ref_final === $ref_rencana);
            if (!$boleh) {
                foreach (ph_alat_pasang_tersedia($conn) as $a) {
                    if ((string) $a['NoRef'] === $ref_final) { $boleh = true; break; }
                }
            }
            if (!$boleh) throw new RuntimeException("No Ref pasang $ref_final tidak tersedia (sudah terpakai, belum lulus uji, atau di luar unit Anda).");
            $st->execute([$ref_final]);
            $dev_ps = $st->fetch(PDO::FETCH_ASSOC);
            if (!$dev_ps || $dev_ps['StatusData'] !== 'AKTIF') throw new RuntimeException('Alat pasang tidak ditemukan atau tidak aktif.');
            $melekat = trim((string) $dev_ps['IdPel']);
            if ($melekat !== '' && $melekat !== (string) $pk['IdPel']) throw new RuntimeException("Alat pasang $ref_final sudah terpasang di pelanggan lain.");
            if (trim((string) $dev_ps['SimId']) === '' || trim((string) $dev_ps['ImeiModem']) === '') {
                throw new RuntimeException('Alat pasang belum lulus pengujian (SIM / modem kosong).');
            }
        }

        $kontak_bk = ph_nomor_sim($conn, $dev_bk['SimId']);
        $kontak_ps = ph_nomor_sim($conn, $dev_ps['SimId']);

        $conn->beginTransaction();

        // Berita Acara (header)
        $st = $conn->prepare("SELECT NoFba FROM frm_ba WHERE NoFpk = ? AND JenisPekerjaan = 'PEMELIHARAAN' LIMIT 1");
        $st->execute([$no_fpk]);
        $no_fba = $st->fetchColumn();
        if ($no_fba === false) {
            $no_fba = ph_no_form($conn, 'frm_ba', 'NoFba', $pk['UnitUp'], date('Y-m-d'), '-F.G');
            $conn->prepare("INSERT INTO frm_ba (NoFba, TglFba, JenisPekerjaan, KdPetugas, NoFpk, IdPel, NamaAkun, UnitUp, UnitAp, UnitUpi, StatusData, WaktuData)
                            VALUES (?, ?, 'PEMELIHARAAN', ?, ?, ?, ?, ?, ?, ?, 'TIDAK', NOW())")
                 ->execute([$no_fba, date('Y-m-d'), $pk['KdPetugas'], $no_fpk, $pk['IdPel'], $nama_akun, $pk['UnitUp'], $pk['UnitAp'], $pk['UnitUpi']]);
        }

        // Dua sisi formulir: BONGKAR (otomatis dari FPK) dan PASANG (diisi petugas)
        ek_simpan_bongkar($conn, $no_fba, $dev_bk, $kontak_bk, $pk, $ps, $ref_final !== $ref_bongkar);
        ek_simpan_detil($conn, $no_fba, 'PASANG', $dev_ps, $kontak_ps, $ps);

        // Alat pasang final dicatat kembali di FPK. Tanpa penggantian = tidak ada baris PASANG di FPK.
        if ($ref_final === $ref_bongkar) {
            $conn->prepare("DELETE FROM frm_pk_detil WHERE NoFpk = ? AND StatusBarang = 'STOK'")->execute([$no_fpk]);
        } else {
            // Bersihkan baris rusak (StatusBarang kosong) sisa simpan lama sebelum menyimpan alat baru
            $conn->prepare("DELETE FROM frm_pk_detil WHERE NoFpk = ? AND (StatusBarang = '' OR StatusBarang IS NULL)")->execute([$no_fpk]);
            $st = $conn->prepare("SELECT COUNT(*) FROM frm_pk_detil WHERE NoFpk = ? AND StatusBarang = 'STOK'");
            $st->execute([$no_fpk]);
            if ((int) $st->fetchColumn() === 0) {
                $conn->prepare("INSERT INTO frm_pk_detil (NoFpk, StatusBarang, NoRef, SimId, ImeiModem, NomorBarang, NomorModem) VALUES (?, 'STOK', ?, ?, ?, ?, ?)")
                     ->execute([$no_fpk, $dev_ps['NoRef'], $dev_ps['SimId'], $dev_ps['ImeiModem'], $kontak_ps, $kontak_ps]);
            } else {
                $conn->prepare("UPDATE frm_pk_detil SET NoRef = ?, SimId = ?, ImeiModem = ?, NomorBarang = ?, NomorModem = ? WHERE NoFpk = ? AND StatusBarang = 'STOK'")
                     ->execute([$dev_ps['NoRef'], $dev_ps['SimId'], $dev_ps['ImeiModem'], $kontak_ps, $kontak_ps, $no_fpk]);
            }
        }

        // Master barang: alat yang dibongkar dilepas dari pelanggan, alat pasang dilekatkan ke pelanggan.
        // Aman diulang (Ubah BA): alat lama/rencana yang bukan alat final dilepas, alat final dilekatkan.
        $lepas = array_diff(array_unique([$ref_bongkar, $ref_rencana]), [$ref_final]);
        foreach ($lepas as $ref) {
            $conn->prepare('UPDATE master_barang SET IdPel = NULL WHERE NoRef = ? AND IdPel = ?')->execute([$ref, $pk['IdPel']]);
        }
        $conn->prepare("UPDATE master_barang SET IdPel = ? WHERE NoRef = ? AND (IdPel IS NULL OR IdPel = '' OR IdPel = ?)")
             ->execute([$pk['IdPel'], $ref_final, $pk['IdPel']]);

        $conn->prepare('UPDATE frm_ba SET StatusData = ?, NamaAkun = ?, WaktuData = NOW() WHERE NoFba = ?')
             ->execute([$status, $nama_akun, $no_fba]);
        $conn->prepare('UPDATE frm_pk SET StatusData = ?, WaktuData = NOW() WHERE NoFpk = ?')
             ->execute([$status, $no_fpk]);

        $conn->commit();
        ph_redirect($sub_ini, 'sukses', "Berita Acara $no_fba berhasil disimpan.");
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        error_log('pemeliharaan eksekusi: ' . $e->getMessage());
        $pesan = ph_duplikat($e)
            ? 'Data Berita Acara ini bentrok dengan data yang sudah ada.' . (ph_role() === 'SA.KP' ? ' Detail: ' . $e->getMessage() : '')
            : ph_pesan_error($e);
        ph_redirect($sub_ini, 'gagal', 'Gagal menyimpan eksekusi: ' . $pesan);
    }
}

$pesan_flash = ph_flash_html();
$pesan_error = '';
$data_eks = [];
$total_hasil = 0; $hal = 1; $total_hal = 1; $q = '';
$list_alat_baru = [];

try {
    [$data_eks, $total_hasil, $hal, $total_hal, $q] = ph_daftar(
        $conn,
        "o.NoFormOrder, o.KdPetugas AS PetugasOrder, p.NoFpk, p.TglFpk, p.IdPel, p.LongDil, p.LatDil,
         pd.NoRef AS BNoRef, pd.SimId AS BSim, pd.ImeiModem AS BImei, pd.NomorBarang AS BKontak,
         COALESCE(pp.NoRef, pd.NoRef) AS PNoRef, COALESCE(pp.SimId, pd.SimId) AS PSim,
         COALESCE(pp.ImeiModem, pd.ImeiModem) AS PImei, COALESCE(pp.NomorBarang, pd.NomorBarang) AS PKontak,
         b.NoFba, b.StatusData AS StatusBa,
         bd.LampuIndikator AS PLampu, bd.PosisiPasang AS PPosisi, bd.`Long` AS PLong, bd.Lat AS PLat,
         (bd.FotoTerpasang IS NOT NULL) AS PFotoP, (bd.FotoBarang IS NOT NULL) AS PFotoB,
         bk.LampuIndikator AS BLampu, bk.PosisiPasang AS BPosisi, bk.`Long` AS BLong, bk.Lat AS BLat,
         (bk.FotoTerpasang IS NOT NULL) AS BFotoP, (bk.FotoBarang IS NOT NULL) AS BFotoB",
        "frm_order o
         JOIN frm_order_detil od ON od.NoFormOrder = o.NoFormOrder
         JOIN frm_pk p ON p.NoFpk = od.NoFpk
         LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk AND pd.StatusBarang = 'DIL'
         LEFT JOIN frm_pk_detil pp ON pp.NoFpk = p.NoFpk AND pp.StatusBarang = 'STOK'
         LEFT JOIN frm_ba b ON b.NoFpk = p.NoFpk AND b.JenisPekerjaan = 'PEMELIHARAAN'
         LEFT JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.StatusPekerjaan = 'PASANG'
         LEFT JOIN frm_ba_detil bk ON bk.NoFba = b.NoFba AND bk.StatusPekerjaan = 'BONGKAR'
         LEFT JOIN master_modem mm ON mm.ImeiModem = pd.ImeiModem",
        "AND p.JenisPk = 'PEMELIHARAAN'",
        [],
        ['alias' => 'p', 'urut' => 'o.TglOrder DESC, o.NoFormOrder DESC, p.NoFpk DESC',
         'cari'  => ph_kolom_cari($conn, true, true, ['pd.NoRef', 'pp.NoRef', 'pd.SimId', 'pd.ImeiModem'])]
    );

    // Alat baru yang belum terpakai (untuk memilih alat pasang di lapangan)
    $list_alat_baru = ph_alat_pasang_tersedia($conn);
} catch (PDOException $e) {
    error_log('pemeliharaan eksekusi_mutasi: ' . $e->getMessage());
    $pesan_error = 'Gagal memuat data eksekusi.' . (ph_role() === 'SA.KP' ? ' Detail: ' . $e->getMessage() : '');
}

/** Radio Posisi / Lampu untuk satu sisi, tampilan seperti kotak centang di formulir. */
function ek_radio(string $nama, array $opsi): string
{
    $h = '';
    foreach ($opsi as $nilai => $label) {
        $id = $nama . '_' . strtolower($nilai);
        $h .= '<div class="form-check form-check-inline mb-0">'
            . '<input class="form-check-input" type="radio" name="' . ph_e($nama) . '" id="' . ph_e($id) . '" value="' . ph_e($nilai) . '" required>'
            . '<label class="form-check-label small" for="' . ph_e($id) . '">' . ph_e($label) . '</label></div>';
    }
    return $h;
}
$opsi_posisi = ['PANGKAL' => 'Pangkal', 'TENGAH' => 'Tengah', 'UJUNG' => 'Ujung'];
$opsi_lampu  = ['MENYALA' => 'Menyala', 'TIDAK' => 'Tidak Menyala'];
?>

<style>
    .pln-form-box { border: 1.5px solid #212529; font-size: 0.85rem; }
    .pln-form-box th, .pln-form-box td { border: 1px solid #212529 !important; padding: 6px 10px; vertical-align: middle; }
    #modalEksekusi .select2-container .select2-selection { font-weight: 700; font-size: 0.85rem; }
</style>

<?= $pesan_flash ?>
<?php if ($pesan_error): ?>
    <div class="alert alert-danger rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= ph_e($pesan_error) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 bg-white">
    <?= ph_card_header('Daftar Order Pemeliharaan untuk Dieksekusi', $sub_ini, $q) ?>
    <div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 ph-table">
            <thead class="table-light text-secondary text-uppercase ph-thead">
                <tr>
                    <th class="ps-4" style="width:40px;">#</th>
                    <th>NO ORDER / PETUGAS</th>
                    <th>NO FPK / NO BA</th>
                    <th>IDPEL / NO REF</th>
                    <th>SIM ID (PASANG)</th>
                    <th>MODEM (PASANG)</th>
                    <th>INDIKATOR</th>
                    <th>STATUS</th>
                    <th class="text-end pe-4" style="width:130px;">AKSI</th>
                </tr>
            </thead>
            <tbody class="fw-medium text-dark">
                <?php if (empty($data_eks)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-5"><?= $q !== '' ? 'Tidak ada data yang cocok dengan pencarian.' : 'Belum ada FPK diorderkan.' ?></td></tr>
                <?php else: ?>
                    <?php foreach ($data_eks as $idx => $row): ?>
                        <?php
                        $ada_ba = !empty($row['NoFba']);
                        $js = [
                            'nofpk' => $row['NoFpk'], 'nofba' => $row['NoFba'] ?? '', 'order' => $row['NoFormOrder'], 'idpel' => $row['IdPel'],
                            'status' => $row['StatusBa'] ?? 'TIDAK',
                            'bk' => [
                                'noref' => $row['BNoRef'] ?? '', 'sim' => $row['BSim'] ?? '', 'imei' => $row['BImei'] ?? '', 'kontak' => $row['BKontak'] ?? '',
                                'lampu' => $row['BLampu'] ?? '', 'posisi' => $row['BPosisi'] ?? '',
                                'long' => $row['BLong'] ?? ($row['LongDil'] ?? ''), 'lat' => $row['BLat'] ?? ($row['LatDil'] ?? ''),
                                'fotop' => !empty($row['BFotoP']) ? 1 : 0, 'fotob' => !empty($row['BFotoB']) ? 1 : 0,
                            ],
                            'ps' => [
                                'noref' => $row['PNoRef'] ?? '', 'sim' => $row['PSim'] ?? '', 'imei' => $row['PImei'] ?? '', 'kontak' => $row['PKontak'] ?? '',
                                'lampu' => $row['PLampu'] ?? '', 'posisi' => $row['PPosisi'] ?? '',
                                'long' => $row['PLong'] ?? ($row['LongDil'] ?? ''), 'lat' => $row['PLat'] ?? ($row['LatDil'] ?? ''),
                                'fotop' => !empty($row['PFotoP']) ? 1 : 0, 'fotob' => !empty($row['PFotoB']) ? 1 : 0,
                            ],
                        ];
                        $sama = (string) ($row['BNoRef'] ?? '') === (string) ($row['PNoRef'] ?? '');
                        ?>
                        <tr class="border-bottom">
                            <td class="ps-4 text-muted small"><?= ($hal - 1) * PH_PER_HALAMAN + $idx + 1 ?></td>
                            <td class="font-monospace"><?= ph_e($row['NoFormOrder']) ?><br><small class="text-muted"><?= ph_e($row['PetugasOrder'] ?? '-') ?></small></td>
                            <td>
                                <div class="fw-bold text-dark font-monospace"><?= ph_e($row['NoFpk']) ?></div>
                                <div class="small text-primary font-monospace">BA: <?= ph_e($row['NoFba'] ?? '-') ?></div>
                            </td>
                            <td>IDPEL: <?= ph_e($row['IdPel']) ?>
                                <br><small class="text-muted">BONGKAR: <?= ph_e($row['BNoRef'] ?? '-') ?></small>
                                <br><small class="text-muted">PASANG: <?= ph_e($row['PNoRef'] ?? '-') ?><?= $sama ? ' <em>(tidak diganti)</em>' : '' ?></small></td>
                            <td class="font-monospace"><span class="badge bg-info bg-opacity-10 text-info"><?= ph_e($row['PSim'] ?? '-') ?></span></td>
                            <td class="font-monospace"><?= ph_e($row['PImei'] ?? '-') ?></td>
                            <td><?= ph_badge_relay($row['PLampu'] ?? '') ?></td>
                            <td><?= $ada_ba ? ph_badge_status($row['StatusBa']) : '<span class="badge bg-primary-subtle text-primary">BELUM</span>' ?></td>
                            <td class="text-end pe-4">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-3 px-3 py-1 fw-bold btn-eksekusi"
                                        data-row="<?= ph_e(json_encode($js, JSON_UNESCAPED_UNICODE)) ?>"
                                        data-status="<?= ph_e($row['StatusBa'] ?? 'TIDAK') ?>">
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
    <?= ph_pager($sub_ini, $total_hasil, $hal, $total_hal, $q) ?>
</div>

<!-- MODAL EKSEKUSI (sesuai formulir: kolom BONGKAR dan PASANG) -->
<div class="modal fade" id="modalEksekusi" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-bottom bg-white py-3 px-4">
                <div class="row w-100 align-items-center">
                    <div class="col-md-5">
                        <h6 class="fw-bold mb-0 text-dark small">PT PLN (PERSERO) UID BANTEN</h6>
                        <span class="fw-bold text-danger">EKSEKUSI PEMELIHARAAN &amp; BERITA ACARA</span>
                    </div>
                    <div class="col-md-5 text-center">
                        <h5 class="fw-bold text-dark mb-0">FORMULIR PERINTAH KERJA</h5>
                    </div>
                    <div class="col-md-2 text-end"><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                </div>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="eksekusi_ba">
                <input type="hidden" name="no_fpk" id="eks_nofpk">
                <div class="modal-body p-4 text-start bg-white">
                    <table class="table pln-form-box mb-3 w-100">
                        <tr>
                            <td class="bg-light fw-bold" style="width:18%;">Jenis PK</td>
                            <td style="width:32%;"><span class="badge bg-warning text-dark px-2 py-1 fw-bold">PEMELIHARAAN</span></td>
                            <td class="bg-light fw-bold" style="width:18%;">Nomor Form (FPK)</td>
                            <td style="width:32%;" class="font-monospace"><span id="eks_t_nofpk"></span></td>
                        </tr>
                        <tr>
                            <td class="bg-light fw-bold">Id Pelanggan</td>
                            <td class="fw-bold"><span id="eks_t_idpel"></span></td>
                            <td class="bg-light fw-bold">No Order / No BA</td>
                            <td class="font-monospace small"><span id="eks_t_order"></span> / <span id="eks_t_nofba"></span></td>
                        </tr>
                    </table>

                    <table class="table pln-form-box text-center mb-3 w-100">
                        <thead>
                            <tr class="bg-light">
                                <th style="width:16%;"></th>
                                <th style="width:42%;">BONGKAR <span class="small fw-normal text-muted">(alat lama)</span></th>
                                <th style="width:42%;">PASANG <span class="small fw-normal text-muted">(alat baru)</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="bg-light text-start fw-bold small">No Ref S41</td>
                                <td class="bg-light"><span id="bgNoRef" class="font-monospace fw-bold text-danger">-</span></td>
                                <td>
                                    <select name="no_ref_pasang" id="eks_noref_pasang" class="form-select form-select-sm font-monospace fw-bold">
                                        <option value="">Tidak ada penggantian (sama dengan bongkar)</option>
                                        <?php foreach ($list_alat_baru as $al): ?>
                                            <option value="<?= ph_e($al['NoRef']) ?>"
                                                    data-sim="<?= ph_e($al['SimId'] ?? '') ?>"
                                                    data-imei="<?= ph_e($al['ImeiModem'] ?? '') ?>"
                                                    data-kontak="<?= ph_e($al['Kontak'] ?? '') ?>"><?= ph_e($al['NoRef']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="psInfo" class="small text-muted fst-italic mt-1"></div>
                                </td>
                            </tr>
                            <tr>
                                <td class="bg-light text-start fw-bold small">Nomor SIM</td>
                                <td class="bg-light"><span id="bgSim" class="font-monospace small">-</span></td>
                                <td><span id="psSim" class="font-monospace small">-</span></td>
                            </tr>
                            <tr>
                                <td class="bg-light text-start fw-bold small">IMEI Modem</td>
                                <td class="bg-light"><span id="bgImei" class="font-monospace small">-</span></td>
                                <td><span id="psImei" class="font-monospace small">-</span></td>
                            </tr>
                            <tr>
                                <td class="bg-light text-start fw-bold small">Nomor Kontak</td>
                                <td class="bg-light"><span id="bgKontak" class="font-monospace small">-</span></td>
                                <td><span id="psKontak" class="font-monospace small">-</span></td>
                            </tr>
                            <tr>
                                <td class="bg-light text-start fw-bold small">Tikor</td>
                                <td class="bg-light">
                                    <span class="small">Long:</span> <span id="bgLong" class="font-monospace small">-</span>
                                    <span class="small ms-2">Lat:</span> <span id="bgLat" class="font-monospace small">-</span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <span class="small">Long:</span>
                                        <input type="text" name="pasang_longitude" id="pasang_long" class="form-control form-control-sm font-monospace" style="max-width:130px;" autocomplete="off" inputmode="decimal" placeholder="106.62319">
                                        <span class="small">Lat:</span>
                                        <input type="text" name="pasang_latitude" id="pasang_lat" class="form-control form-control-sm font-monospace" style="max-width:130px;" autocomplete="off" inputmode="decimal" placeholder="-6.17698">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="bg-light text-start fw-bold small">Posisi</td>
                                <td class="bg-light"><span id="bgPosisi" class="small">-</span></td>
                                <td><?= ek_radio('pasang_posisi', $opsi_posisi) ?></td>
                            </tr>
                            <tr>
                                <td class="bg-light text-start fw-bold small">Lampu indikator</td>
                                <td class="bg-light"><span id="bgLampu" class="small">-</span></td>
                                <td><?= ek_radio('pasang_lampu', $opsi_lampu) ?></td>
                            </tr>
                            <tr>
                                <td class="bg-light text-start fw-bold small">Foto Barang</td>
                                <td class="bg-light"><span class="small text-muted fst-italic">Tidak diunggah (alat lama)</span></td>
                                <td>
                                    <input type="file" name="pasang_foto_barang" class="form-control form-control-sm" accept="image/*">
                                    <div class="small text-success fst-italic mt-1 d-none" id="pasang_ada_fotob"><i class="bi bi-check-circle me-1"></i>Foto sudah dilampirkan (kosongkan jika tidak diganti)</div>
                                </td>
                            </tr>
                            <tr>
                                <td class="bg-light text-start fw-bold small">Foto Terpasang</td>
                                <td class="bg-light"><span class="small text-muted fst-italic">Tidak diunggah (alat lama)</span></td>
                                <td>
                                    <input type="file" name="pasang_foto_terpasang" class="form-control form-control-sm" accept="image/*">
                                    <div class="small text-success fst-italic mt-1 d-none" id="pasang_ada_fotop"><i class="bi bi-check-circle me-1"></i>Foto sudah dilampirkan (kosongkan jika tidak diganti)</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="alert alert-light border small py-2 mb-3">
                        <i class="bi bi-lock-fill me-1 text-secondary"></i>
                        Kolom <b>BONGKAR</b> adalah data alat lama dari FPK dan tidak dapat diubah. Untuk mengganti alat, pilih No Ref baru di kolom <b>PASANG</b>.
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
    var el = function (id) { return document.getElementById(id); };
    var teks = function (id, v) { el(id).textContent = (v === undefined || v === null || v === "") ? "-" : v; };
    var gabung = function (imei) { return imei ? imei : "-"; };
    var selPs = el("eks_noref_pasang");
    var cur = null;   // data baris yang sedang dibuka

    var onUbah = function (elm, fn) {
        if (window.jQuery) jQuery(elm).on("change", fn); else elm.addEventListener("change", fn);
    };
    var setRadio = function (nama, nilai) {
        document.querySelectorAll('input[name="' + nama + '"]').forEach(function (r) { r.checked = (r.value === nilai); });
    };
    var setVal = function (sel, v) {
        sel.value = v;
        if (window.jQuery) jQuery(sel).trigger("change"); else sel.dispatchEvent(new Event("change"));
    };

    // Kolom PASANG: alat dari dropdown, atau sama dengan BONGKAR bila tidak ada penggantian
    var isiPasang = function () {
        if (!cur) return;
        var ganti = selPs.value !== "";
        var opt = selPs.options[selPs.selectedIndex];
        var sim = ganti ? opt.getAttribute("data-sim") : cur.bk.sim;
        var imei = ganti ? opt.getAttribute("data-imei") : cur.bk.imei;
        var kontak = ganti ? opt.getAttribute("data-kontak") : cur.bk.kontak;
        teks("psSim", sim); teks("psImei", gabung(imei)); teks("psKontak", kontak);
        el("psInfo").textContent = ganti ? "Alat baru menggantikan " + cur.bk.noref : "Tidak ada penggantian: data pasang sama dengan bongkar";
    };
    onUbah(selPs, isiPasang);

    if (window.jQuery && jQuery.fn.select2) {
        jQuery(selPs).select2({
            theme: "bootstrap-5", width: "100%", dropdownParent: jQuery("#modalEksekusi"),
            language: { noResults: function () { return "No Ref tidak ditemukan"; } }
        });
        jQuery(document).on("select2:open", function () {
            var f = document.querySelector(".select2-container--open .select2-search__field");
            if (f) f.focus();
        });
    }

    var isiSisi = function (s, d) {
        setRadio(s + "_posisi", d.posisi || "PANGKAL");
        setRadio(s + "_lampu", d.lampu === "TIDAK" ? "TIDAK" : "MENYALA");
        el(s + "_long").value = d.long || ""; el(s + "_lat").value = d.lat || "";
        el(s + "_ada_fotob").classList.toggle("d-none", !d.fotob);
        el(s + "_ada_fotop").classList.toggle("d-none", !d.fotop);
        modalEl.querySelectorAll('input[type="file"][name^="' + s + '_"]').forEach(function (f) { f.value = ""; });
    };

    document.querySelectorAll(".btn-eksekusi").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var d = JSON.parse(this.dataset.row);
            cur = d;
            el("eks_nofpk").value = d.nofpk;
            teks("eks_t_order", d.order); teks("eks_t_nofpk", d.nofpk); teks("eks_t_idpel", d.idpel);
            teks("eks_t_nofba", d.nofba || "(otomatis)");

            // BONGKAR (alat lama dari FPK)
            teks("bgNoRef", d.bk.noref); teks("bgSim", d.bk.sim); teks("bgImei", gabung(d.bk.imei)); teks("bgKontak", d.bk.kontak);

            // Hapus opsi sementara dari pembukaan sebelumnya, lalu pastikan alat pasang rencana FPK ada di daftar
            Array.prototype.slice.call(selPs.querySelectorAll("option.dyn")).forEach(function (o) { o.remove(); });
            var rencana = (d.ps.noref && d.ps.noref !== d.bk.noref) ? d.ps.noref : "";
            if (rencana && !selPs.querySelector('option[value="' + rencana + '"]')) {
                var o = document.createElement("option");
                o.value = rencana; o.textContent = rencana; o.className = "dyn";
                o.setAttribute("data-sim", d.ps.sim || ""); o.setAttribute("data-imei", d.ps.imei || ""); o.setAttribute("data-kontak", d.ps.kontak || "");
                selPs.appendChild(o);
            }
            setVal(selPs, rencana);

            // BONGKAR: hanya tampilan (alat lama)
            teks("bgLong", d.bk.long); teks("bgLat", d.bk.lat);
            teks("bgPosisi", d.bk.posisi ? d.bk.posisi.charAt(0) + d.bk.posisi.slice(1).toLowerCase() : "");
            teks("bgLampu", d.bk.lampu === "TIDAK" ? "Tidak Menyala" : (d.bk.lampu === "MENYALA" ? "Menyala" : ""));
            // Belum ada BA: isian pasang diawali dari tikor FPK
            isiSisi("pasang", d.nofba ? d.ps : { lampu: d.bk.lampu, posisi: d.bk.posisi, long: d.bk.long, lat: d.bk.lat, fotob: 0, fotop: 0 });

            el("eks_status").value = this.dataset.status === "AKTIF" ? "AKTIF" : "TIDAK";
            if (modal) modal.show();
        });
    });
});
</script>