<?php
// Sub-menu: Order Pemeliharaan (Eksekusi PK & BA)
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

$sub_ini  = 'order_pemeliharaan';
$is_ulp   = (substr(ph_role(), -3) === '.UP');
$pesan_error = '';

/* ======================================================================
 * 1. SIMPAN (POST) -> SIMPAN KE frm_pk (Header) & frm_ba (Detail/BA)
 * ==================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();

    [$petugas, $e1] = ph_pilih($_POST['kd_petugas'] ?? '', PH_PETUGAS, 'Kode petugas');
    [$relay,   $e2] = ph_pilih($_POST['kondisi_relay'] ?? '', PH_RELAY, 'Kondisi relay');
    [$posisi,  $e3] = ph_pilih($_POST['posisi_pasang'] ?? '', PH_POSISI, 'Posisi pasang');
    [$status,  $e4] = ph_pilih($_POST['status_data'] ?? '', PH_STATUS, 'Status data');
    $tgl = ph_tanggal($_POST['tgl_pemeliharaan'] ?? '');
    [$lat, $long, $e5] = ph_koordinat($_POST['latitude'] ?? '', $_POST['longitude'] ?? '');
    [$foto_pasang, $e6] = ph_foto_baca('foto_pasang');
    [$foto_barang, $e7] = ph_foto_baca('foto_barang');
    $galat = $e1 ?: ($e2 ?: ($e3 ?: ($e4 ?: ($e5 ?: ($e6 ?: $e7)))));
    if (!$galat && !$tgl) {
        $galat = 'Tanggal pemeliharaan tidak valid.';
    }
    $nama_akun = substr(trim((string) ($_SESSION['NamaAkun'] ?? '')), 0, 20);

    // ---- A. Tambah Order (PK & BA) ----
    if (!$galat && $_POST['action'] === 'tambah_pemeliharaan') {
        try {
            $up_pilih = $is_ulp ? trim((string) ($_SESSION['UnitUp'] ?? '')) : trim((string) ($_POST['unit_up'] ?? ''));
            $st = $conn->prepare("SELECT UnitUp, UnitAp, UnitUpi FROM master_up WHERE UnitUp = ? AND StatusData = 'AKTIF'");
            $st->execute([$up_pilih]);
            $unit = $st->fetch(PDO::FETCH_ASSOC);
            if (!$unit) {
                $galat = 'Unit ULP tidak valid atau tidak aktif.';
            } elseif (!$is_ulp && trim((string) ($_POST['unit_ap'] ?? '')) !== $unit['UnitAp']) {
                $galat = 'Unit ULP tidak sesuai dengan UP3 yang dipilih.';
            }

            $id_pel = trim((string) ($_POST['id_pel'] ?? ''));
            if (!$galat) {
                $st = $conn->prepare("SELECT 1 FROM master_barang WHERE IdPel = ? AND UnitUp = ? AND StatusData = 'AKTIF' LIMIT 1");
                $st->execute([$id_pel, $unit['UnitUp']]);
                if ($id_pel === '' || !$st->fetchColumn()) {
                    $galat = 'ID pelanggan tidak ditemukan di master barang untuk unit ini.';
                }
            }

            $items = [];
            if (!$galat) {
                $cari = $conn->prepare("SELECT NoRef, SimId, ImeiModem, IdPel, UnitUp FROM master_barang WHERE NoRef = ? AND StatusData = 'AKTIF'");
                foreach (array_unique(array_filter(array_map('trim', (array) ($_POST['no_ref'] ?? [])), 'strlen')) as $ref) {
                    $cari->execute([$ref]);
                    $b = $cari->fetch(PDO::FETCH_ASSOC);
                    if (!$b) {
                        $galat = 'No Ref "' . $ref . '" tidak ditemukan atau tidak aktif di master barang.';
                        break;
                    }
                    if (($b['UnitUp'] ?? '') !== $unit['UnitUp']) {
                        $galat = 'No Ref "' . $ref . '" terdaftar pada unit lain (' . ($b['UnitUp'] ?: 'tanpa unit') . '), bukan unit ' . $unit['UnitUp'] . '.';
                        break;
                    }
                    if (($b['IdPel'] ?? '') !== '' && $b['IdPel'] !== $id_pel) {
                        $galat = 'No Ref "' . $ref . '" milik pelanggan ' . $b['IdPel'] . ', bukan ' . $id_pel . '.';
                        break;
                    }
                    $items[] = $b;
                }
                if (!$galat && !$items) {
                    $galat = 'Pilih minimal satu barang.';
                }
            }

            if (!$galat) {
                $upi = $unit['UnitUpi'] ?: '56';

                for ($percobaan = 1; ; $percobaan++) {
                    try {
                        $bases  = ph_nomor_dasar($conn, $unit['UnitUp'], count($items));
                        $no_fpk = $bases[0] . '-F.G';

                        $conn->beginTransaction();

                        // 1. SIMPAN KE TABEL HEADER: frm_pk
                        $conn->prepare("INSERT INTO frm_pk 
                            (NoFpk, TglFpk, IdPel, SimId, ImeiModem, LongDil, LatDil, NamaAkun, UnitUp, UnitAp, UnitUpi, StatusData, WaktuData) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())")
                            ->execute([
                                $no_fpk, $tgl, substr($id_pel, 0, 12), 
                                $items[0]['SimId'] ?? null, $items[0]['ImeiModem'] ?? null, 
                                $long, $lat, $nama_akun, $unit['UnitUp'], $unit['UnitAp'], $upi, $status
                            ]);

                        // 2. SIMPAN KE TABEL DETAIL / EKSEKUSI: frm_ba
                        $ba_stmt = $conn->prepare("INSERT INTO frm_ba 
                            (NoFormPemeliharaan, NoFormOrder, TglPemeliharaan, KdPetugas, NamaAkun, NoRef, SimId, ImeiModem, IdPel, Longitude, Latitude, PosisiPasang, FotoPasang, FotoBarang, KondisiRelay, UnitUp, UnitAp, UnitUpi, StatusData, WaktuData) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

                        foreach ($items as $i => $b) {
                            $sim  = ($b['SimId'] ?? '') !== '' ? substr($b['SimId'], 0, 25) : null;
                            $imei = ($b['ImeiModem'] ?? '') !== '' ? substr($b['ImeiModem'], 0, 30) : null;
                            $no_ba = $bases[$i] . '-F.H';

                            $ba_stmt->execute([
                                $no_ba, $no_fpk, $tgl, $petugas, $nama_akun, substr($b['NoRef'], 0, 30), $sim, $imei,
                                substr($id_pel, 0, 12), $long, $lat, $posisi, $foto_pasang, $foto_barang, $relay,
                                $unit['UnitUp'], $unit['UnitAp'], $upi, $status
                            ]);
                        }

                        $conn->commit();
                        break;
                    } catch (Throwable $e) {
                        if ($conn->inTransaction()) {
                            $conn->rollBack();
                        }
                        if (ph_duplikat($e) && $percobaan < 3) {
                            continue;
                        }
                        throw $e;
                    }
                }
                ph_redirect($sub_ini, 'sukses', "Order FPK $no_fpk berhasil disimpan dengan " . count($items) . ' Formulir Perintah Kerja (BA).');
            }
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log('pemeliharaan tambah: ' . $e->getMessage());
            $m = $e->getMessage();
            if ($e instanceof RuntimeException) {
                $galat = $m;
            } elseif (stripos($m, 'foreign key') !== false) {
                $galat = 'Data referensi belum terdaftar di master.';
            } elseif (stripos($m, 'duplicate') !== false) {
                $galat = 'Nomor form bentrok. Silakan simpan ulang.';
            } else {
                $galat = 'Gagal menyimpan data. Hubungi administrator.';
            }
        }
    }

    // ---- B. Edit Data (frm_ba) ----
    if (!$galat && $_POST['action'] === 'edit_pemeliharaan') {
        try {
            $no_form = trim((string) ($_POST['no_form_pemeliharaan'] ?? ''));
            
            $sql = "UPDATE frm_ba SET TglPemeliharaan = ?, KdPetugas = ?, Longitude = ?, Latitude = ?, PosisiPasang = ?, KondisiRelay = ?, StatusData = ?, WaktuData = NOW()";
            $par = [$tgl, $petugas, $long, $lat, $posisi, $relay, $status];
            if ($foto_pasang !== null) {
                $sql .= ", FotoPasang = ?";
                $par[] = $foto_pasang;
            }
            if ($foto_barang !== null) {
                $sql .= ", FotoBarang = ?";
                $par[] = $foto_barang;
            }
            $sql .= " WHERE NoFormPemeliharaan = ?";
            $par[] = $no_form;
            $conn->prepare($sql)->execute($par);
            
            ph_redirect($sub_ini, 'sukses', "Data BA Pemeliharaan $no_form berhasil diperbarui.");
        } catch (PDOException $e) {
            error_log('pemeliharaan edit: ' . $e->getMessage());
            $galat = 'Gagal memperbarui data. Hubungi administrator.';
        }
    }

    if ($galat) {
        ph_redirect($sub_ini, 'gagal', $galat);
    }
}

/* ======================================================================
 * 2. READ DATA & DROPDOWN DARI TABEL frm_ba & master_barang
 * ==================================================================== */
$pesan_flash = ph_flash_html();

[$sc_sql, $sc_par] = ph_scope('p');

// Ringkasan Data dari frm_ba
$st = $conn->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(COALESCE(p.KondisiRelay, b.StatusRelay) LIKE '%BERFUNGSI%' OR COALESCE(p.KondisiRelay, b.StatusRelay) LIKE '%MENYALA%' OR COALESCE(p.KondisiRelay, b.StatusRelay) LIKE '%NC%'), 0) AS baik FROM frm_ba p LEFT JOIN master_barang b ON p.NoRef = b.NoRef WHERE 1=1" . $sc_sql);
$st->execute($sc_par);
$ringkas = $st->fetch(PDO::FETCH_ASSOC);
$total_pemeliharaan = (int) $ringkas['total'];
$total_relay_baik   = (int) $ringkas['baik'];

// Membaca Daftar Pemeliharaan dari frm_ba
[$data_pemeliharaan, $total_hasil, $hal, $total_hal, $q] = ph_daftar(
    $conn,
    "p.NoFormPemeliharaan, p.NoFormOrder, p.TglPemeliharaan, p.KdPetugas, p.IdPel, p.NoRef, p.SimId, p.ImeiModem,
     p.Longitude, p.Latitude, p.PosisiPasang, COALESCE(p.KondisiRelay, b.StatusRelay) AS KondisiRelay, p.StatusData,
     (p.FotoPasang IS NOT NULL) AS AdaFotoPasang, (p.FotoBarang IS NOT NULL) AS AdaFotoBarang",
    "frm_ba p LEFT JOIN master_barang b ON p.NoRef = b.NoRef"
);

$list_barang_full = $list_ap = $list_idpel_pem = [];
$unit_saya = null;
try {
    $f_unit = $is_ulp ? ' AND UnitUp = ?' : '';
    $b_unit = $is_ulp ? [trim((string) ($_SESSION['UnitUp'] ?? ''))] : [];
    
    $st = $conn->prepare("SELECT NoRef, IdPel, SimId, ImeiModem FROM master_barang WHERE StatusData = 'AKTIF' AND NoRef IS NOT NULL AND NoRef != ''$f_unit ORDER BY NoRef ASC");
    $st->execute($b_unit);
    $list_barang_full = $st->fetchAll(PDO::FETCH_ASSOC);

    $st = $conn->prepare("SELECT DISTINCT IdPel FROM master_barang WHERE StatusData = 'AKTIF' AND IdPel IS NOT NULL AND IdPel != ''$f_unit ORDER BY IdPel ASC");
    $st->execute($b_unit);
    $list_idpel_pem = $st->fetchAll(PDO::FETCH_ASSOC);

    if ($is_ulp) {
        $st = $conn->prepare("SELECT u.UnitUp, u.SingkatanNama AS NamaUp, a.UnitAp, a.SingkatanNama AS NamaAp FROM master_up u LEFT JOIN master_ap a ON a.UnitAp = u.UnitAp WHERE u.UnitUp = ?");
        $st->execute([trim((string) ($_SESSION['UnitUp'] ?? ''))]);
        $unit_saya = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } else {
        $list_ap = $conn->query("SELECT UnitAp, SingkatanNama FROM master_ap WHERE StatusData = 'AKTIF' ORDER BY UnitAp ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('pemeliharaan dropdown: ' . $e->getMessage());
    $pesan_error = 'Sebagian data pilihan gagal dimuat.';
}
?>

<style>
    .table-compact thead th { font-size: 0.85rem !important; font-weight: 700 !important; color: #495057 !important; padding: 0.75rem 0.5rem !important; }
    .table-compact tbody td { padding: 0.75rem 0.5rem !important; font-size: 0.9rem !important; word-break: break-word !important; }
    .table-compact .badge { font-size: 0.8rem !important; padding: 0.4em 0.6em !important; }
</style>

<?= $pesan_flash ?>
<?php if ($pesan_error): ?>
    <div class="alert alert-danger rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= ph_e($pesan_error) ?></div>
<?php endif; ?>

<!-- KARTU RINGKASAN -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-uppercase text-muted fw-bold small mb-1">TOTAL PERINTH KERJA (BA)</div>
                    <div class="h2 fw-bold mb-0 text-dark"><?= $total_pemeliharaan ?> <span class="fs-6 fw-normal text-muted">Berkas</span></div>
                </div>
                <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-4 fs-2"><i class="bi bi-file-earmark-check"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-uppercase text-muted fw-bold small mb-1">KONDISI RELAY BAIK</div>
                    <div class="h2 fw-bold mb-0 text-dark"><?= $total_relay_baik ?> <span class="fs-6 text-muted">/ <?= $total_pemeliharaan ?> Normal</span></div>
                </div>
                <div class="p-3 bg-success bg-opacity-10 text-success rounded-4 fs-2"><i class="bi bi-shield-check"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- TABEL ORDER PEMELIHARAAN -->
<div class="card border-0 shadow-sm rounded-4 bg-white p-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 px-2 pt-2">
        <h5 class="fw-bold text-primary mb-0">Daftar Formulir Perintah Kerja (BA Pemeliharaan)</h5>
        <div class="d-flex gap-2">
            <?= ph_kotak_cari($sub_ini, $q) ?>
            <button type="button" class="btn btn-primary fw-medium px-3 rounded-3" id="btnBukaModalTambah">
                <i class="bi bi-plus-lg me-1"></i> Buat Order Perintah Kerja
            </button>
        </div>
    </div>

    <div class="w-100 table-responsive">
        <table class="table table-hover align-middle mb-0 table-compact w-100" id="tablePemeliharaan">
            <thead class="table-light border-top border-bottom">
                <tr>
                    <th class="ps-2 text-center" style="width:3%;">#</th>
                    <th style="width:18%;">NO FORM (BA) / NO ORDER (PK)</th>
                    <th style="width:12%;">TGL / PETUGAS</th>
                    <th style="width:14%;">IDPEL / NO REF</th>
                    <th style="width:12%;">SIM ID</th>
                    <th style="width:12%;">IMEI MODEM</th>
                    <th style="width:12%;">KOORDINAT GPS</th>
                    <th style="width:10%;">KONDISI RELAY</th>
                    <th style="width:5%;">STATUS</th>
                    <th class="pe-2 text-center" style="width:5%;">AKSI</th>
                </tr>
            </thead>
            <tbody class="fw-medium text-dark">
                <?php if (empty($data_pemeliharaan)): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4"><?= $q !== '' ? 'Tidak ada data yang cocok dengan pencarian.' : 'Belum ada data order perintah kerja.' ?></td></tr>
                <?php else: ?>
                    <?php foreach ($data_pemeliharaan as $idx => $row): ?>
                        <tr class="border-bottom">
                            <td class="ps-2 text-center text-muted small"><?= ($hal - 1) * PH_PER_HALAMAN + $idx + 1 ?></td>
                            <td>
                                <div class="fw-bold text-dark font-monospace"><?= ph_e($row['NoFormPemeliharaan']) ?></div>
                                <div class="small text-primary font-monospace">Order PK: <?= ph_e($row['NoFormOrder'] ?? '-') ?></div>
                            </td>
                            <td><?= ph_e($row['TglPemeliharaan']) ?><br><small class="text-muted"><?= ph_e($row['KdPetugas']) ?></small></td>
                            <td>IDPEL: <?= ph_e($row['IdPel']) ?><br><small class="text-muted">REF: <?= ph_e($row['NoRef']) ?></small></td>
                            <td><span class="badge bg-info bg-opacity-10 text-info font-monospace"><?= ph_e($row['SimId'] ?? '-') ?></span></td>
                            <td class="font-monospace"><?= ph_e($row['ImeiModem'] ?? '-') ?></td>
                            <td class="font-monospace small"><?= ($row['Latitude'] ?? '') !== '' ? ph_e($row['Latitude'] . ', ' . $row['Longitude']) : '<span class="text-muted">-</span>' ?></td>
                            <td><?= ph_badge_relay($row['KondisiRelay']) ?></td>
                            <td><?= ph_badge_status($row['StatusData']) ?></td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-info btn-detail-pemeliharaan" title="Lihat Detail & Foto"
                                            data-noform="<?= ph_e($row['NoFormPemeliharaan']) ?>"
                                            data-noorder="<?= ph_e($row['NoFormOrder'] ?? '') ?>"
                                            data-idpel="<?= ph_e($row['IdPel']) ?>"
                                            data-noref="<?= ph_e($row['NoRef']) ?>"
                                            data-sim="<?= ph_e($row['SimId'] ?? '-') ?>"
                                            data-imei="<?= ph_e($row['ImeiModem'] ?? '-') ?>"
                                            data-lat="<?= ph_e($row['Latitude'] ?? '-') ?>"
                                            data-long="<?= ph_e($row['Longitude'] ?? '-') ?>"
                                            data-posisi="<?= ph_e($row['PosisiPasang'] ?? '-') ?>"
                                            data-relay="<?= ph_e($row['KondisiRelay'] ?? '-') ?>">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-edit-pemeliharaan" title="Edit Data"
                                            data-noform="<?= ph_e($row['NoFormPemeliharaan']) ?>"
                                            data-tgl="<?= ph_e($row['TglPemeliharaan']) ?>"
                                            data-petugas="<?= ph_e($row['KdPetugas']) ?>"
                                            data-lat="<?= ph_e($row['Latitude'] ?? '') ?>"
                                            data-long="<?= ph_e($row['Longitude'] ?? '') ?>"
                                            data-posisi="<?= ph_e($row['PosisiPasang'] ?? '') ?>"
                                            data-relay="<?= ph_e($row['KondisiRelay'] ?? '') ?>"
                                            data-status="<?= ph_e($row['StatusData'] ?? '') ?>">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= ph_pager($sub_ini, $total_hasil, $hal, $total_hal, $q) ?>
</div>

<!-- ======================================================================
 * 3. DOKUMEN HTML MODAL (TAMBAH ORDER PK, EDIT, DETAIL)
 * ==================================================================== -->

<!-- MODAL TAMBAH ORDER PERINTAH KERJA -->
<div class="modal fade" id="modalTambahPemeliharaan" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark">Form Order Perintah Kerja (PK & BA)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=pemeliharaan&menu=eksekusi&sub=order_pemeliharaan" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-body p-4 text-start">
                    <input type="hidden" name="action" value="tambah_pemeliharaan">

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">UNIT UPI</label>
                            <input type="text" class="form-control rounded-3 bg-light text-muted fw-semibold" value="UID BANTEN (56)" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">UNIT AP *</label>
                            <?php if ($is_ulp): ?>
                                <input type="text" class="form-control rounded-3 bg-light text-muted" value="<?= ph_e($unit_saya['NamaAp'] ?? '') ?> (<?= ph_e($unit_saya['UnitAp'] ?? '') ?>)" readonly>
                                <input type="hidden" name="unit_ap" value="<?= ph_e($unit_saya['UnitAp'] ?? '') ?>">
                            <?php else: ?>
                                <select name="unit_ap" id="selectUnitAp" class="form-select rounded-3" required>
                                    <option value="">-- Pilih Unit AP --</option>
                                    <?php foreach ($list_ap as $ap): ?>
                                        <option value="<?= ph_e($ap['UnitAp']); ?>"><?= ph_e($ap['SingkatanNama']); ?> (<?= ph_e($ap['UnitAp']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">UNIT UP *</label>
                            <?php if ($is_ulp): ?>
                                <input type="text" class="form-control rounded-3 bg-light text-muted" value="<?= ph_e($unit_saya['NamaUp'] ?? '') ?> (<?= ph_e($unit_saya['UnitUp'] ?? '') ?>)" readonly>
                                <input type="hidden" name="unit_up" value="<?= ph_e($unit_saya['UnitUp'] ?? '') ?>">
                            <?php else: ?>
                                <select name="unit_up" id="selectUnitUp" class="form-select rounded-3" required disabled>
                                    <option value="">-- Pilih AP Dahulu --</option>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">TANGGAL PK *</label>
                            <input type="date" name="tgl_pemeliharaan" class="form-control rounded-3" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">KODE PETUGAS *</label>
                            <select name="kd_petugas" class="form-select rounded-3" required>
                                <?php foreach (PH_PETUGAS as $p): ?>
                                    <option value="<?= $p ?>"><?= $p ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">ID PELANGGAN *</label>
                            <select name="id_pel" id="selectIdPel" class="form-select" required style="width:100%;">
                                <option value="">-- Pilih ID Pelanggan --</option>
                                <?php foreach ($list_idpel_pem as $pel): ?>
                                    <option value="<?= ph_e($pel['IdPel']); ?>"><?= ph_e($pel['IdPel']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- PENGISIAN BARANG PERINTH KERJA -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-0">PERANGKAT PERINTAH KERJA (BA) *</label>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-3" id="btnTambahBarang">
                                <i class="bi bi-plus-lg me-1"></i> Tambah Barang
                            </button>
                        </div>

                        <div class="table-responsive border rounded-3 p-2 bg-light">
                            <table class="table table-bordered align-middle mb-0 bg-white" id="tableDetailBarang">
                                <thead class="table-light small text-uppercase fw-bold text-muted">
                                    <tr>
                                        <th style="width: 40%;">NO REF PERANGKAT *</th>
                                        <th style="width: 27%;">SIM ID (AUTO)</th>
                                        <th style="width: 27%;">IMEI MODEM (AUTO)</th>
                                        <th class="text-center" style="width: 6%;">#</th>
                                    </tr>
                                </thead>
                                <tbody id="containerBarang">
                                    <tr class="row-barang">
                                        <td>
                                            <select name="no_ref[]" class="form-select form-select-sm select-noref" required style="width: 100%;" disabled>
                                                <option value="">-- Pilih ID Pelanggan Dahulu --</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm bg-light font-monospace input-simid" placeholder="Auto SIM ID" readonly>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm bg-light font-monospace input-imei" placeholder="Auto IMEI Modem" readonly>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger p-1 border-0 btn-hapus-baris" disabled>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">LONGITUDE (GPS)</label>
                            <input type="text" name="longitude" class="form-control rounded-3 font-monospace" placeholder="Contoh: 106.84513">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">LATITUDE (GPS)</label>
                            <input type="text" name="latitude" class="form-control rounded-3 font-monospace" placeholder="Contoh: -6.2088">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">POSISI PASANG *</label>
                            <select name="posisi_pasang" class="form-select rounded-3">
                                <?php foreach (PH_POSISI as $pos): ?>
                                    <option value="<?= $pos ?>"><?= $pos ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">KONDISI RELAY *</label>
                            <select name="kondisi_relay" class="form-select rounded-3" required>
                                <?php foreach (PH_RELAY as $rel): ?>
                                    <option value="<?= $rel ?>"><?= $rel ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">FOTO TERPASANG</label>
                            <input type="file" name="foto_pasang" class="form-control rounded-3" accept="image/*">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">FOTO BARANG</label>
                            <input type="file" name="foto_barang" class="form-control rounded-3" accept="image/*">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">STATUS DATA *</label>
                        <select name="status_data" class="form-select rounded-3" required>
                            <?php foreach (PH_STATUS as $st_val): ?>
                                <option value="<?= $st_val ?>"><?= $st_val ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-footer border-top p-3 justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3">Simpan Order Perintah Kerja</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDIT -->
<div class="modal fade" id="modalEditPemeliharaan" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark">Edit BA Pemeliharaan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=pemeliharaan&menu=eksekusi&sub=order_pemeliharaan" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-body p-4 text-start">
                    <input type="hidden" name="action" value="edit_pemeliharaan">
                    <input type="hidden" name="no_form_pemeliharaan" id="editNoFormPemeliharaan">

                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">TANGGAL PEMELIHARAAN *</label>
                        <input type="date" name="tgl_pemeliharaan" id="editTglPemeliharaan" class="form-control rounded-3" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">KODE PETUGAS *</label>
                        <select name="kd_petugas" id="editKdPetugas" class="form-select rounded-3" required>
                            <?php foreach (PH_PETUGAS as $p): ?>
                                <option value="<?= $p ?>"><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">LONGITUDE</label>
                            <input type="text" name="longitude" id="editLongitude" class="form-control rounded-3 font-monospace">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">LATITUDE</label>
                            <input type="text" name="latitude" id="editLatitude" class="form-control rounded-3 font-monospace">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">POSISI PASANG *</label>
                            <select name="posisi_pasang" id="editPosisiPasang" class="form-select rounded-3">
                                <?php foreach (PH_POSISI as $pos): ?>
                                    <option value="<?= $pos ?>"><?= $pos ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">KONDISI RELAY *</label>
                            <select name="kondisi_relay" id="editKondisiRelay" class="form-select rounded-3" required>
                                <?php foreach (PH_RELAY as $rel): ?>
                                    <option value="<?= $rel ?>"><?= $rel ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">GANTI FOTO TERPASANG</label>
                            <input type="file" name="foto_pasang" class="form-control rounded-3" accept="image/*">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-uppercase text-muted fw-bold small mb-1">GANTI FOTO BARANG</label>
                            <input type="file" name="foto_barang" class="form-control rounded-3" accept="image/*">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-uppercase text-muted fw-bold small mb-1">STATUS DATA *</label>
                        <select name="status_data" id="editStatusData" class="form-select rounded-3" required>
                            <?php foreach (PH_STATUS as $st_val): ?>
                                <option value="<?= $st_val ?>"><?= $st_val ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-footer border-top p-3 justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DETAIL -->
<div class="modal fade" id="modalDetailPemeliharaan" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-info-circle text-primary me-2"></i>Detail Formulir Perintah Kerja (BA)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-4 bg-light p-3 rounded-3 border">
                    <div class="col-md-6">
                        <small class="text-muted d-block fw-bold">NO FORM BA (PERINTH KERJA)</small>
                        <span id="detNoForm" class="fw-bold font-monospace text-primary"></span>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block fw-bold">NO FORM ORDER PK</small>
                        <span id="detNoOrder" class="fw-bold font-monospace text-secondary"></span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block fw-bold">ID PELANGGAN</small>
                        <span id="detIdPel" class="fw-semibold"></span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block fw-bold">NO REF PERANGKAT</small>
                        <span id="detNoRef" class="fw-semibold"></span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block fw-bold">SIM ID / IMEI</small>
                        <span id="detSimImei" class="font-monospace small"></span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block fw-bold">KONDISI RELAY</small>
                        <span id="detRelay" class="badge bg-success"></span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block fw-bold">POSISI PASANG</small>
                        <span id="detPosisi" class="fw-semibold"></span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block fw-bold">KOORDINAT GPS</small>
                        <span id="detGps" class="font-monospace small"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top p-3">
                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
var masterBarang = <?= json_encode($list_barang_full) ?>;

document.addEventListener("DOMContentLoaded", function () {
    
    function updateNoRefOptions(selectEl, selectedIdPel) {
        selectEl.innerHTML = "";
        if (selectedIdPel !== "") {
            var filteredBarang = masterBarang.filter(function (b) {
                return b.IdPel === selectedIdPel;
            });

            if (filteredBarang.length > 0) {
                selectEl.innerHTML = '<option value="">-- Pilih No Ref Perangkat --</option>';
                filteredBarang.forEach(function (b) {
                    var opt = document.createElement("option");
                    opt.value = b.NoRef;
                    opt.textContent = b.NoRef;
                    opt.setAttribute("data-sim", b.SimId || "");
                    opt.setAttribute("data-imei", b.ImeiModem || "");
                    selectEl.appendChild(opt);
                });
                selectEl.disabled = false;
            } else {
                selectEl.innerHTML = '<option value="">-- Tidak ada No Ref untuk IdPel ini --</option>';
                selectEl.disabled = true;
            }
        } else {
            selectEl.innerHTML = '<option value="">-- Pilih ID Pelanggan Dahulu --</option>';
            selectEl.disabled = true;
        }
    }

    var selIdPel = document.getElementById("selectIdPel");
    if (selIdPel) {
        selIdPel.addEventListener("change", function () {
            var selectedIdPel = this.value;
            document.querySelectorAll(".select-noref").forEach(function (selectEl) {
                updateNoRefOptions(selectEl, selectedIdPel);
                var tr = selectEl.closest("tr");
                if (tr) {
                    tr.querySelector(".input-simid").value = "";
                    tr.querySelector(".input-imei").value = "";
                }
            });
        });
    }

    document.addEventListener("change", function (e) {
        if (e.target && e.target.classList.contains("select-noref")) {
            var opt = e.target.options[e.target.selectedIndex];
            var tr = e.target.closest("tr");
            if (tr) {
                tr.querySelector(".input-simid").value = opt.getAttribute("data-sim") || "";
                tr.querySelector(".input-imei").value = opt.getAttribute("data-imei") || "";
            }
        }
    });

    var btnTambahBarang = document.getElementById("btnTambahBarang");
    if (btnTambahBarang) {
        btnTambahBarang.addEventListener("click", function () {
            var container = document.getElementById("containerBarang");
            var firstRow = container.querySelector(".row-barang");
            if (!firstRow) return;

            var newRow = firstRow.cloneNode(true);
            var selectNoRef = newRow.querySelector(".select-noref");
            
            selectNoRef.value = "";
            newRow.querySelector(".input-simid").value = "";
            newRow.querySelector(".input-imei").value = "";

            var currentIdPel = selIdPel ? selIdPel.value : "";
            updateNoRefOptions(selectNoRef, currentIdPel);

            var btnHapus = newRow.querySelector(".btn-hapus-baris");
            btnHapus.disabled = false;
            btnHapus.addEventListener("click", function () {
                if (container.querySelectorAll(".row-barang").length > 1) {
                    newRow.remove();
                }
            });

            container.appendChild(newRow);
        });
    }

    var btnTambah = document.getElementById("btnBukaModalTambah");
    if (btnTambah) {
        btnTambah.addEventListener("click", function () {
            var modalTambah = bootstrap.Modal.getOrCreateInstance(document.getElementById("modalTambahPemeliharaan"));
            modalTambah.show();
        });
    }

    document.addEventListener("click", function (e) {
        var btnEdit = e.target.closest(".btn-edit-pemeliharaan");
        if (btnEdit) {
            e.preventDefault();
            document.getElementById("editNoFormPemeliharaan").value = btnEdit.getAttribute("data-noform") || "";
            document.getElementById("editTglPemeliharaan").value = btnEdit.getAttribute("data-tgl") || "";
            document.getElementById("editKdPetugas").value = btnEdit.getAttribute("data-petugas") || "";
            document.getElementById("editLongitude").value = btnEdit.getAttribute("data-long") || "";
            document.getElementById("editLatitude").value = btnEdit.getAttribute("data-lat") || "";
            document.getElementById("editPosisiPasang").value = btnEdit.getAttribute("data-posisi") || "";
            document.getElementById("editKondisiRelay").value = btnEdit.getAttribute("data-relay") || "";
            document.getElementById("editStatusData").value = btnEdit.getAttribute("data-status") || "";

            var modalEdit = bootstrap.Modal.getOrCreateInstance(document.getElementById("modalEditPemeliharaan"));
            modalEdit.show();
        }

        var btnDetail = e.target.closest(".btn-detail-pemeliharaan");
        if (btnDetail) {
            e.preventDefault();
            document.getElementById("detNoForm").textContent = btnDetail.getAttribute("data-noform") || "";
            document.getElementById("detNoOrder").textContent = btnDetail.getAttribute("data-noorder") || "";
            document.getElementById("detIdPel").textContent = btnDetail.getAttribute("data-idpel") || "";
            document.getElementById("detNoRef").textContent = btnDetail.getAttribute("data-noref") || "";
            document.getElementById("detSimImei").textContent = (btnDetail.getAttribute("data-sim") || "-") + " / " + (btnDetail.getAttribute("data-imei") || "-");
            document.getElementById("detRelay").textContent = btnDetail.getAttribute("data-relay") || "";
            document.getElementById("detPosisi").textContent = btnDetail.getAttribute("data-posisi") || "";
            document.getElementById("detGps").textContent = (btnDetail.getAttribute("data-lat") || "-") + ", " + (btnDetail.getAttribute("data-long") || "-");

            var modalDetail = bootstrap.Modal.getOrCreateInstance(document.getElementById("modalDetailPemeliharaan"));
            modalDetail.show();
        }
    });

    var selAp = document.getElementById("selectUnitAp");
    if (selAp) {
        selAp.addEventListener("change", function () {
            var valAp = this.value;
            var selUp = document.getElementById("selectUnitUp");
            if (!selUp) return;

            selUp.innerHTML = '<option value="">-- Memuat Data UP... --</option>';
            selUp.disabled = true;

            if (valAp !== "") {
                fetch('modules/pemeliharaan/get_unit_up.php?unit_ap=' + encodeURIComponent(valAp))
                    .then(response => response.json())
                    .then(data => {
                        selUp.innerHTML = '<option value="">-- Pilih Unit UP --</option>';
                        if (data && data.length > 0) {
                            data.forEach(item => {
                                var opt = document.createElement("option");
                                opt.value = item.UnitUp;
                                opt.textContent = item.SingkatanNama + ' (' + item.UnitUp + ')';
                                selUp.appendChild(opt);
                            });
                            selUp.disabled = false;
                        } else {
                            selUp.innerHTML = '<option value="">-- Tidak Ada Data UP --</option>';
                        }
                    })
                    .catch(() => {
                        selUp.innerHTML = '<option value="">-- Gagal Memuat Data --</option>';
                    });
            } else {
                selUp.innerHTML = '<option value="">-- Pilih AP Dahulu --</option>';
            }
        });
    }
});
</script>