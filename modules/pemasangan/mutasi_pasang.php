<?php
$unit_up = $_SESSION['UnitUp'] ?? '';
$is_sa   = ($_SESSION['KodeHak'] ?? '') === 'SA.KP';
$view = $_GET['view'] ?? 'daftar';
$q = trim($_GET['q'] ?? '');

if ($view === 'daftar') {
    // Setup Paginasi
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $page  = isset($_GET['p']) ? (int)$_GET['p'] : 1;
    if ($limit < 1) $limit = 10;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    $where_clause = "WHERE 1=1";
    $params = [];
    
    if (!$is_sa && !empty($unit_up)) {
        $where_clause .= " AND o.UnitUp = ?";
        $params[] = $unit_up;
    }
    if (!empty($q)) {
        $where_clause .= " AND o.NoFormOrder LIKE ?";
        $params[] = "%$q%";
    }

    $sql_count = "SELECT COUNT(*) FROM frm_order o $where_clause";
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->execute($params);
    $total_data = $stmt_count->fetchColumn();
    $total_pages = ceil($total_data / $limit);

    $sql = "SELECT o.NoFormOrder, o.TglOrder, o.NamaAkun, o.StatusData, o.WaktuData,
                   (SELECT COUNT(NoFpk) FROM frm_order_detil d WHERE d.NoFormOrder = o.NoFormOrder) as TotalTarget,
                   (SELECT COUNT(d.NoFpk) FROM frm_order_detil d 
                    JOIN frm_ba b ON d.NoFpk = b.NoFpk AND b.JenisPekerjaan = 'PASANG'
                    WHERE d.NoFormOrder = o.NoFormOrder AND b.StatusData = 'AKTIF') as TotalSelesai,
                   (SELECT b.NoFba FROM frm_order_detil d 
                    JOIN frm_ba b ON d.NoFpk = b.NoFpk AND b.JenisPekerjaan = 'PASANG'
                    WHERE d.NoFormOrder = o.NoFormOrder LIMIT 1) as NoFba
            FROM frm_order o
            $where_clause
            ORDER BY o.WaktuData DESC LIMIT $limit OFFSET $offset";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $list_mutasi = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card border-0 shadow-sm rounded-4 bg-white p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="fw-bold text-primary mb-1">Mutasi & Eksekusi Pemasangan Lapangan</h5>
            <p class="text-muted small mb-0">Pencatatan Berita Acara (BA) pemasangan perangkat S41 berdasarkan Surat Perintah Kerja (SPK).</p>
        </div>
        <div>
            <form action="index.php" method="GET" class="input-group input-group-sm">
                <input type="hidden" name="page" value="pemasangan">
                <input type="hidden" name="menu" value="eksekusi">
                <input type="hidden" name="sub" value="mutasi_pasang">
                <?php if(isset($_GET['limit'])): ?><input type="hidden" name="limit" value="<?= htmlspecialchars($_GET['limit']) ?>"><?php endif; ?>
                <input type="text" class="form-control" name="q" placeholder="Cari No Order..." value="<?= htmlspecialchars($q) ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-dark border-top border-bottom">
                <tr>
                    <th class="ps-3" style="width:5%;">#</th>
                    <th>NO FORM ORDER</th>
                    <th>TGL ORDER</th>
                    <th>PEMBUAT</th>
                    <th class="text-center">PROGRESS PEMASANGAN</th>
                    <th class="text-center pe-3" style="width:18%;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($list_mutasi)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data Order untuk mutasi pasang.</td></tr>
                <?php else: ?>
                    <?php foreach ($list_mutasi as $idx => $row): ?>
                        <?php $is_final = ($row['StatusData'] === 'AKTIF'); ?>
                        <tr>
                            <td class="ps-3 text-muted"><?= $offset + $idx + 1 ?></td>
                            <td class="fw-bold font-monospace text-dark"><?= htmlspecialchars($row['NoFormOrder']) ?></td>
                            <td><?= htmlspecialchars($row['TglOrder']) ?></td>
                            <td><?= htmlspecialchars($row['NamaAkun']) ?></td>
                            <td class="text-center">
                                <span class="badge <?= $is_final ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' : 'bg-info bg-opacity-10 text-info' ?> font-monospace px-3 py-2">
                                    <?= $row['TotalSelesai'] ?> / <?= $row['TotalTarget'] ?> Selesai <?= $is_final ? '(Final)' : '' ?>
                                </span>
                            </td>
                            <td class="text-center pe-3">
                                <div class="d-flex justify-content-center gap-1 align-items-center">
                                    <?php if ($is_final): ?>
                                        <a href="index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=detail&no_form=<?= urlencode($row['NoFormOrder']) ?>" class="btn btn-sm btn-outline-secondary px-3 fw-semibold">
                                            <i class="bi bi-eye me-1"></i> Detail Order
                                        </a>
                                    <?php else: ?>
                                        <a href="index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=detail&no_form=<?= urlencode($row['NoFormOrder']) ?>" class="btn btn-sm btn-outline-primary px-3">
                                            <i class="bi bi-tools me-1"></i> Eksekusi
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($row['TotalSelesai'] > 0): ?>
                                        <a href="modules/pemasangan/cetak_ba.php?no_order=<?= urlencode($row['NoFormOrder']) ?>" target="_blank" class="btn btn-sm btn-outline-success px-2 py-1" title="Cetak Berita Acara Pemasangan PDF">
                                            <i class="bi bi-printer-fill"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div class="d-flex justify-content-between align-items-center mt-4">
        <div class="d-flex align-items-center gap-2">
            <span class="small text-muted">Tampilkan</span>
            <select class="form-select form-select-sm w-auto" onchange="window.location.href=this.value">
                <option value="?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=daftar&limit=5&q=<?= urlencode($q) ?>" <?= $limit == 5 ? 'selected' : '' ?>>5</option>
                <option value="?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=daftar&limit=10&q=<?= urlencode($q) ?>" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                <option value="?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=daftar&limit=20&q=<?= urlencode($q) ?>" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
                <option value="?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=daftar&limit=30&q=<?= urlencode($q) ?>" <?= $limit == 30 ? 'selected' : '' ?>>30</option>
            </select>
            <span class="small text-muted">dari <?= $total_data ?> data</span>
        </div>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=daftar&limit=<?= $limit ?>&p=<?= $page - 1 ?>&q=<?= urlencode($q) ?>">Prev</a></li>
                <?php 
                $start_page = max(1, $page - 2); $end_page = min($total_pages, $page + 2);
                for($i = $start_page; $i <= $end_page; $i++): 
                ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=daftar&limit=<?= $limit ?>&p=<?= $i ?>&q=<?= urlencode($q) ?>"><?= $i ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>"><a class="page-link" href="?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=daftar&limit=<?= $limit ?>&p=<?= $page + 1 ?>&q=<?= urlencode($q) ?>">Next</a></li>
            </ul>
        </nav>
    </div>
</div>

<?php 
} elseif ($view === 'detail') {
    $no_form = $_GET['no_form'] ?? '';
    
    // Cek akses Super Admin atau UnitUp
    if ($is_sa) {
        $stmt_head = $conn->prepare("SELECT * FROM frm_order WHERE NoFormOrder = ?");
        $stmt_head->execute([$no_form]);
    } else {
        $stmt_head = $conn->prepare("SELECT * FROM frm_order WHERE NoFormOrder = ? AND UnitUp = ?");
        $stmt_head->execute([$no_form, $unit_up]);
    }
    $formulir = $stmt_head->fetch(PDO::FETCH_ASSOC);
    
    if (!$formulir) {
        echo '<div class="alert alert-danger">Surat Perintah Kerja (Order) tidak ditemukan.</div>';
        return;
    }

    // StatusData === 'AKTIF' berarti sudah finalisasi, 'TIDAK' berarti masih draft/belum final
    $is_order_final = ($formulir['StatusData'] === 'AKTIF');

    $stmt_detil = $conn->prepare("
        SELECT od.NoFpk, pk.IdPel, pkd.NoRef, pkd.SimId, pkd.ImeiModem,
               b.NoFba as NoFormPasang, b.TglFba as TglPasang, bd.PosisiPasang, b.StatusData as StatusPasang
        FROM frm_order_detil od
        JOIN frm_pk pk ON od.NoFpk = pk.NoFpk
        JOIN frm_pk_detil pkd ON pk.NoFpk = pkd.NoFpk
        LEFT JOIN frm_ba b ON od.NoFpk = b.NoFpk AND b.JenisPekerjaan = 'PASANG'
        LEFT JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.NoRef = pkd.NoRef AND bd.StatusPekerjaan = 'PASANG'
        WHERE od.NoFormOrder = ?
    ");
    $stmt_detil->execute([$no_form]);
    $items = $stmt_detil->fetchAll(PDO::FETCH_ASSOC);

    // Hitung progress selesai untuk menentukan munculnya tombol Finalisasi
    $total_target  = count($items);
    $total_selesai = 0;
    foreach ($items as $it) {
        if ($it['StatusPasang'] === 'AKTIF') $total_selesai++;
    }
    $semua_selesai = ($total_target > 0 && $total_selesai === $total_target);
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="card border-0 shadow-sm rounded-4 bg-white p-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold text-primary mb-0"><?= $is_order_final ? 'Detail Order Mutasi Pasang' : 'Eksekusi Mutasi Pasang' ?>: <?= htmlspecialchars($no_form) ?></h5>
                <?php if ($is_order_final): ?>
                    <span class="badge bg-success"><i class="bi bi-lock-fill me-1"></i> Difinalisasi</span>
                <?php endif; ?>
            </div>
            <p class="text-muted small mb-0 mt-1">
                <?= $is_order_final ? 'Seluruh Berita Acara pada SPK ini telah difinalisasi dan terkunci (hanya baca).' : 'Daftar perangkat dan pelanggan dalam SPK yang siap dipasang di lapangan.' ?>
            </p>
        </div>
        <a href="index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=daftar" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-center" style="width:5%;">#</th>
                    <th>NO FPK</th>
                    <th>IDPEL</th>
                    <th>NO REF / IMEI</th>
                    <th class="text-center">STATUS PASANG</th>
                    <th class="text-center" style="width:15%;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="6" class="text-center text-muted">Tidak ada target FPK dalam order ini.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $idx => $it): ?>
                        <?php $sudah_pasang = ($it['StatusPasang'] === 'AKTIF'); ?>
                        <tr>
                            <td class="text-center"><?= $idx + 1 ?></td>
                            <td class="fw-bold font-monospace text-primary"><?= htmlspecialchars($it['NoFpk']) ?></td>
                            <td class="font-monospace"><?= htmlspecialchars($it['IdPel']) ?></td>
                            <td class="font-monospace">
                                <div>Ref: <?= htmlspecialchars($it['NoRef']) ?></div>
                                <small class="text-muted">IMEI: <?= htmlspecialchars($it['ImeiModem'] ?? '-') ?></small>
                            </td>
                            <td class="text-center">
                                <?php if ($sudah_pasang): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle me-1"></i> Terpasang</span>
                                <?php else: ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning">Belum Dipasang</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($is_order_final): ?>
                                    <!-- Jika StatusData = AKTIF: Tombol menjadi Detail BA (Read-Only) -->
                                    <a href="index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=form_ba&no_form=<?= urlencode($no_form) ?>&noref=<?= urlencode($it['NoRef']) ?>&no_fpk=<?= urlencode($it['NoFpk']) ?>" class="btn btn-sm btn-outline-secondary px-3 fw-semibold">
                                        <i class="bi bi-eye me-1"></i> Detail BA
                                    </a>
                                <?php elseif ($sudah_pasang): ?>
                                    <!-- Jika StatusData = TIDAK tapi BA sudah diinput: Tombol menjadi Edit BA -->
                                    <a href="index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=form_ba&no_form=<?= urlencode($no_form) ?>&noref=<?= urlencode($it['NoRef']) ?>&no_fpk=<?= urlencode($it['NoFpk']) ?>" class="btn btn-sm btn-outline-warning text-dark fw-semibold px-3">
                                        <i class="bi bi-pencil-square me-1"></i> Edit BA
                                    </a>
                                <?php else: ?>
                                    <!-- Jika BA belum diinput sama sekali: Tombol Input BA -->
                                    <a href="index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=form_ba&no_form=<?= urlencode($no_form) ?>&noref=<?= urlencode($it['NoRef']) ?>&no_fpk=<?= urlencode($it['NoFpk']) ?>" class="btn btn-sm btn-primary px-3">
                                        Input BA
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- TOMBOL FINALISASI MUTASI (Muncul jika StatusData masih 'TIDAK' & semua FPK sudah terpasang) -->
    <?php if (!$is_order_final && $semua_selesai): ?>
        <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-success small fw-semibold">
                <i class="bi bi-check-circle-fill me-1"></i> Seluruh target FPK (<?= $total_selesai ?>/<?= $total_target ?>) telah selesai diinput Berita Acaranya.
            </div>
            <form id="formFinalisasiMutasi" action="modules/pemasangan/proses_mutasi.php" method="POST" class="m-0">
                <input type="hidden" name="action" value="finalisasi_order">
                <input type="hidden" name="no_form_order" value="<?= htmlspecialchars($no_form) ?>">
                <button type="button" class="btn btn-success fw-bold px-4 shadow-sm" onclick="konfirmasiFinalisasiMutasi()">
                    <i class="bi bi-cloud-check-fill me-1"></i> Finalisasi Mutasi Pasang
                </button>
            </form>
        </div>

        <script>
        function konfirmasiFinalisasiMutasi() {
            Swal.fire({
                title: 'Finalisasi Mutasi Pasang?',
                text: 'Setelah difinalisasi, seluruh Berita Acara pada SPK ini akan dikunci permanen dan tidak dapat diedit lagi. Lanjutkan?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Finalisasi!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('formFinalisasiMutasi').submit();
                }
            });
        }
        </script>
    <?php endif; ?>
</div>

<?php 
} elseif ($view === 'form_ba') {
    $no_form = $_GET['no_form'] ?? '';
    $noref   = $_GET['noref'] ?? '';
    $no_fpk  = $_GET['no_fpk'] ?? '';

    // 1. Cek apakah Order induknya sudah difinalisasi ('AKTIF')
    $stmt_ord = $conn->prepare("SELECT StatusData FROM frm_order WHERE NoFormOrder = ?");
    $stmt_ord->execute([$no_form]);
    $status_order = $stmt_ord->fetchColumn();
    $is_locked = ($status_order === 'AKTIF');

    // 2. Ambil info perangkat & pelanggan
    $stmt_info = $conn->prepare("
        SELECT pk.IdPel, pkd.NoRef, pkd.SimId, pkd.ImeiModem 
        FROM frm_pk pk 
        JOIN frm_pk_detil pkd ON pk.NoFpk = pkd.NoFpk 
        WHERE pk.NoFpk = ? AND pkd.NoRef = ?
    ");
    $stmt_info->execute([$no_fpk, $noref]);
    $data_alat = $stmt_info->fetch(PDO::FETCH_ASSOC);

    // 3. Ambil data BA yang sudah pernah disimpan (menggunakan kolom Long, Lat, FotoTerpasang sesuai frm_ba_detil)
    $stmt_ba = $conn->prepare("
        SELECT b.NoFba, b.TglFba, bd.*
        FROM frm_ba b
        JOIN frm_ba_detil bd ON b.NoFba = bd.NoFba
        WHERE b.NoFpk = ? AND bd.NoRef = ? AND b.JenisPekerjaan = 'PASANG'
        LIMIT 1
    ");
    $stmt_ba->execute([$no_fpk, $noref]);
    $data_ba = $stmt_ba->fetch(PDO::FETCH_ASSOC);

    $tgl_pasang_val     = $data_ba['TglFba'] ?? date('Y-m-d');
    $posisi_pasang_val  = $data_ba['PosisiPasang'] ?? 'Pangkal';
    $kondisi_relay_val  = $data_ba['LampuIndikator'] ?? 'MENYALA';
    $longitude_val      = $data_ba['Long'] ?? '';
    $latitude_val       = $data_ba['Lat'] ?? '';
    $catatan_val        = $data_ba['Catatan'] ?? $data_ba['Keterangan'] ?? '';
    $foto_barang_blob   = $data_ba['FotoBarang'] ?? null;
    $foto_pasang_blob   = $data_ba['FotoTerpasang'] ?? null;
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="card border-0 shadow-sm rounded-4 bg-white p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold text-primary mb-0">
                    <?= $is_locked ? 'Detail Berita Acara (BA) Pemasangan' : (!empty($data_ba) ? 'Edit Berita Acara (BA) Pemasangan' : 'Formulir Berita Acara (BA) Pemasangan') ?>
                </h5>
                <?php if ($is_locked): ?>
                    <span class="badge bg-secondary"><i class="bi bi-lock-fill me-1"></i> Terkunci</span>
                <?php endif; ?>
            </div>
            <p class="text-muted small mb-0 mt-1">Sesuai Dokumen FPK: <strong><?= htmlspecialchars($no_fpk) ?></strong></p>
        </div>
        <a href="index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=detail&no_form=<?= urlencode($no_form) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <form action="modules/pemasangan/proses_mutasi.php" method="POST" enctype="multipart/form-data" onsubmit="btnSubmit.disabled = true; btnSubmit.innerHTML = '<i class=\'bi bi-hourglass-split\'></i> Memproses...';">
        <input type="hidden" name="no_form_order" value="<?= htmlspecialchars($no_form) ?>">
        <input type="hidden" name="no_fpk" value="<?= htmlspecialchars($no_fpk) ?>">
        <input type="hidden" name="no_ref" value="<?= htmlspecialchars($data_alat['NoRef'] ?? $noref) ?>">
        <input type="hidden" name="id_pel" value="<?= htmlspecialchars($data_alat['IdPel'] ?? '') ?>">
        <input type="hidden" name="sim_id" value="<?= htmlspecialchars($data_alat['SimId'] ?? '') ?>">
        <input type="hidden" name="imei_modem" value="<?= htmlspecialchars($data_alat['ImeiModem'] ?? '') ?>">

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold small">ID Pelanggan</label>
                <input type="text" class="form-control bg-light font-monospace" value="<?= htmlspecialchars($data_alat['IdPel'] ?? '-') ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">NoRef / IMEI S41</label>
                <input type="text" class="form-control bg-light font-monospace" value="<?= htmlspecialchars($data_alat['NoRef'] ?? $noref) ?> (IMEI: <?= htmlspecialchars($data_alat['ImeiModem'] ?? '-') ?>)" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Tanggal Pasang</label>
                <input type="date" name="tgl_pasang" class="form-control <?= $is_locked ? 'bg-light' : '' ?>" value="<?= htmlspecialchars($tgl_pasang_val) ?>" <?= $is_locked ? 'readonly' : 'required' ?>>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Posisi Pasang</label>
                <select name="posisi_pasang" class="form-select <?= $is_locked ? 'bg-light' : '' ?>" <?= $is_locked ? 'disabled' : 'required' ?>>
                    <option value="Pangkal" <?= $posisi_pasang_val === 'Pangkal' ? 'selected' : '' ?>>Pangkal</option>
                    <option value="Tengah" <?= $posisi_pasang_val === 'Tengah' ? 'selected' : '' ?>>Tengah</option>
                    <option value="Ujung" <?= $posisi_pasang_val === 'Ujung' ? 'selected' : '' ?>>Ujung</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Lampu Indikator</label>
                <select name="kondisi_relay" class="form-select <?= $is_locked ? 'bg-light' : '' ?>" <?= $is_locked ? 'disabled' : 'required' ?>>
                    <option value="MENYALA" <?= $kondisi_relay_val === 'MENYALA' ? 'selected' : '' ?>>Menyala (Normal)</option>
                    <option value="TIDAK" <?= $kondisi_relay_val === 'TIDAK' ? 'selected' : '' ?>>Tidak Menyala</option>
                </select>
            </div>
            
            <!-- PENGAMBILAN KOORDINAT GPS & PETA INTERAKTIF -->
            <div class="col-12">
                <div class="card border bg-light p-3 rounded-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-bold text-dark mb-0"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Koordinat Lokasi Pemasangan (Tikor)</label>
                        <?php if (!$is_locked): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="ambilLokasiGPS()">
                                <i class="bi bi-crosshair me-1"></i> Ambil Lokasi Saat Ini
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Longitude</label>
                            <input type="text" id="longitude" name="longitude" class="form-control font-monospace <?= $is_locked ? 'bg-light' : 'bg-white' ?>" value="<?= htmlspecialchars($longitude_val) ?>" placeholder="106.xxxx" <?= $is_locked ? 'readonly' : 'required' ?>>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Latitude</label>
                            <input type="text" id="latitude" name="latitude" class="form-control font-monospace <?= $is_locked ? 'bg-light' : 'bg-white' ?>" value="<?= htmlspecialchars($latitude_val) ?>" placeholder="-6.xxxx" <?= $is_locked ? 'readonly' : 'required' ?>>
                        </div>
                    </div>
                    <?php if (!$is_locked): ?>
                        <small class="text-muted d-block mb-2">Geser pin pada peta di bawah ini atau klik untuk mengoreksi posisi lokasi pemasangan:</small>
                    <?php endif; ?>
                    <div id="map" style="height: 300px; width: 100%; border-radius: 8px; border: 1px solid #ced4da;"></div>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold small">Foto Barang</label>
                <?php if (!$is_locked): ?>
                    <input type="file" name="foto_barang" class="form-control" accept="image/*">
                <?php endif; ?>
                <?php if (!empty($foto_barang_blob)): ?>
                    <div class="mt-2">
                        <img src="data:image/jpeg;base64,<?= base64_encode($foto_barang_blob) ?>" alt="Foto Barang" class="img-thumbnail" style="max-height: 180px;">
                    </div>
                <?php elseif ($is_locked): ?>
                    <div class="form-control bg-light text-muted small">Tidak ada foto barang</div>
                <?php endif; ?>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold small">Foto Terpasang di Lapangan</label>
                <?php if (!$is_locked): ?>
                    <input type="file" name="foto_pasang" class="form-control" accept="image/*">
                <?php endif; ?>
                <?php if (!empty($foto_pasang_blob)): ?>
                    <div class="mt-2">
                        <img src="data:image/jpeg;base64,<?= base64_encode($foto_pasang_blob) ?>" alt="Foto Terpasang" class="img-thumbnail" style="max-height: 180px;">
                    </div>
                <?php elseif ($is_locked): ?>
                    <div class="form-control bg-light text-muted small">Tidak ada foto terpasang</div>
                <?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold small">Catatan Pemasangan</label>
                <textarea name="catatan" class="form-control <?= $is_locked ? 'bg-light' : '' ?>" rows="2" placeholder="<?= $is_locked ? '-' : 'Catatan tambahan lapangan...' ?>" <?= $is_locked ? 'readonly' : '' ?>><?= htmlspecialchars($catatan_val) ?></textarea>
            </div>
        </div>

        <!-- TOMBOL SIMPAN: Hanya tampil jika StatusData masih 'TIDAK' -->
        <?php if (!$is_locked): ?>
            <div class="mt-4 text-end">
                <button type="submit" id="btnSubmit" class="btn btn-primary px-5 fw-bold shadow-sm">
                    <i class="bi bi-save me-1"></i> <?= !empty($data_ba) ? 'Simpan Perubahan Berita Acara' : 'Simpan Berita Acara Pemasangan' ?>
                </button>
            </div>
        <?php endif; ?>
    </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var savedLat = <?= !empty($latitude_val) && is_numeric($latitude_val) ? floatval($latitude_val) : 'null' ?>;
    var savedLng = <?= !empty($longitude_val) && is_numeric($longitude_val) ? floatval($longitude_val) : 'null' ?>;
    var isLocked = <?= $is_locked ? 'true' : 'false' ?>;

    var defaultLat = savedLat !== null ? savedLat : -6.1574;
    var defaultLng = savedLng !== null ? savedLng : 106.7285;
    var zoomLevel  = savedLat !== null ? 17 : 15;

    var map = L.map('map').setView([defaultLat, defaultLng], zoomLevel);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var marker = L.marker([defaultLat, defaultLng], {draggable: !isLocked}).addTo(map);

    if (!isLocked) {
        function updateInputs(lat, lng) {
            document.getElementById('latitude').value = lat.toFixed(6);
            document.getElementById('longitude').value = lng.toFixed(6);
        }

        marker.on('dragend', function (e) {
            var coord = marker.getLatLng();
            updateInputs(coord.lat, coord.lng);
        });

        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            updateInputs(e.latlng.lat, e.latlng.lng);
        });

        window.ambilLokasiGPS = function() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    var lat = position.coords.latitude;
                    var lng = position.coords.longitude;
                    map.setView([lat, lng], 17);
                    marker.setLatLng([lat, lng]);
                    updateInputs(lat, lng);
                }, function(error) {
                    alert('Gagal mendeteksi lokasi GPS: ' + error.message);
                }, { enableHighAccuracy: true });
            } else {
                alert('Geolocation tidak didukung oleh browser Anda.');
            }
        }
    }
});
</script>
<?php } ?>  