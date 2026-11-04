<?php
$unit_up = $_SESSION['UnitUp'] ?? '';
$view = $_GET['view'] ?? 'daftar';
$q = trim($_GET['q'] ?? '');

if ($view === 'daftar') {
    $sql = "SELECT o.NoFormOrder, o.TglOrder, o.NamaAkun, o.StatusData, o.WaktuData,
                   (SELECT COUNT(NoFpk) FROM frm_order_detil d WHERE d.NoFormOrder = o.NoFormOrder) as TotalTarget,
                   (SELECT COUNT(d.NoFpk) FROM frm_order_detil d 
                    JOIN frm_ba b ON d.NoFpk = b.NoFpk AND b.JenisPekerjaan = 'PASANG'
                    WHERE d.NoFormOrder = o.NoFormOrder AND b.StatusData = 'AKTIF') as TotalSelesai,
                   (SELECT b.NoFba FROM frm_order_detil d 
                    JOIN frm_ba b ON d.NoFpk = b.NoFpk AND b.JenisPekerjaan = 'PASANG'
                    WHERE d.NoFormOrder = o.NoFormOrder LIMIT 1) as NoFba
            FROM frm_order o
            WHERE o.UnitUp = :up AND (o.NoFormOrder LIKE :q)
            ORDER BY o.WaktuData DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':up' => $unit_up, ':q' => "%$q%"]);
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
                    <th class="text-center pe-3" style="width:15%;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($list_mutasi)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data Order untuk mutasi pasang.</td></tr>
                <?php else: ?>
                    <?php foreach ($list_mutasi as $idx => $row): ?>
                        <tr>
                            <td class="ps-3 text-muted"><?= $idx + 1 ?></td>
                            <td class="fw-bold font-monospace text-dark"><?= htmlspecialchars($row['NoFormOrder']) ?></td>
                            <td><?= htmlspecialchars($row['TglOrder']) ?></td>
                            <td><?= htmlspecialchars($row['NamaAkun']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-info bg-opacity-10 text-info font-monospace px-3 py-2">
                                    <?= $row['TotalSelesai'] ?> / <?= $row['TotalTarget'] ?> Selesai
                                </span>
                            </td>
                            <td class="text-center pe-3">
                                <div class="d-flex justify-content-center gap-1 align-items-center">
                                    <a href="index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=detail&no_form=<?= urlencode($row['NoFormOrder']) ?>" class="btn btn-sm btn-outline-primary px-3">
                                        <i class="bi bi-tools me-1"></i> Eksekusi
                                    </a>

                                    <?php if (!empty($row['NoFba'])): ?>
                                        <a href="modules/pemasangan/cetak_ba.php?no_fba=<?= urlencode($row['NoFba']) ?>" target="_blank" class="btn btn-sm btn-outline-success px-2 py-1" title="Cetak Berita Acara PDF">
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
</div>

<?php 
} elseif ($view === 'detail') {
    $no_form = $_GET['no_form'] ?? '';
    $stmt_head = $conn->prepare("SELECT * FROM frm_order WHERE NoFormOrder = ? AND UnitUp = ?");
    $stmt_head->execute([$no_form, $unit_up]);
    $formulir = $stmt_head->fetch(PDO::FETCH_ASSOC);
    
    if (!$formulir) {
        echo '<div class="alert alert-danger">Surat Perintah Kerja (Order) tidak ditemukan.</div>';
        return;
    }

    // Menggunakan JenisPekerjaan = 'PASANG' agar sinkron dengan tabel database
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
?>

<div class="card border-0 shadow-sm rounded-4 bg-white p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold text-primary mb-1">Eksekusi Mutasi Pasang: <?= htmlspecialchars($no_form) ?></h5>
            <p class="text-muted small mb-0">Daftar perangkat dan pelanggan dalam SPK yang siap dipasang di lapangan.</p>
        </div>
        <a href="index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=daftar" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle">
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
                                <a href="index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=form_ba&no_form=<?= urlencode($no_form) ?>&noref=<?= urlencode($it['NoRef']) ?>&no_fpk=<?= urlencode($it['NoFpk']) ?>" class="btn btn-sm <?= $sudah_pasang ? 'btn-outline-success' : 'btn-primary' ?> px-3">
                                    <?= $sudah_pasang ? 'Detail BA' : 'Input BA' ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php 
} elseif ($view === 'form_ba') {
    $no_form = $_GET['no_form'] ?? '';
    $noref   = $_GET['noref'] ?? '';
    $no_fpk  = $_GET['no_fpk'] ?? '';

    $stmt_info = $conn->prepare("
        SELECT pk.IdPel, pkd.NoRef, pkd.SimId, pkd.ImeiModem 
        FROM frm_pk pk 
        JOIN frm_pk_detil pkd ON pk.NoFpk = pkd.NoFpk 
        WHERE pk.NoFpk = ? AND pkd.NoRef = ?
    ");
    $stmt_info->execute([$no_fpk, $noref]);
    $data_alat = $stmt_info->fetch(PDO::FETCH_ASSOC);
?>

<!-- Panggil Leaflet CSS & JS untuk Peta Interaktif -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="card border-0 shadow-sm rounded-4 bg-white p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="fw-bold text-primary mb-1">Formulir Berita Acara (BA) Pemasangan Perangkat</h5>
            <p class="text-muted small mb-0">Sesuai Dokumen FPK: <strong><?= htmlspecialchars($no_fpk) ?></strong></p>
        </div>
        <a href="index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=detail&no_form=<?= urlencode($no_form) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <form action="modules/pemasangan/proses_mutasi.php" method="POST" enctype="multipart/form-data">
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
                <input type="date" name="tgl_pasang" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Posisi Pasang</label>
                <select name="posisi_pasang" class="form-select" required>
                    <option value="Pangkal">Pangkal</option>
                    <option value="Tengah">Tengah</option>
                    <option value="Ujung">Ujung</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Lampu Indikator</label>
                <select name="kondisi_relay" class="form-select" required>
                    <option value="MENYALA">Menyala (Normal)</option>
                    <option value="TIDAK">Tidak Menyala</option>
                </select>
            </div>
            
            <!-- PENGAMBILAN KORDINAT GPS & PETA INTERAKTIF -->
            <div class="col-12">
                <div class="card border bg-light p-3 rounded-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-bold text-dark mb-0"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Koordinat Lokasi Pemasangan (Tikor)</label>
                        <button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="ambilLokasiGPS()">
                            <i class="bi bi-crosshair me-1"></i> Ambil Lokasi Saat Ini
                        </button>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Longitude</label>
                            <input type="text" id="longitude" name="longitude" class="form-control font-monospace bg-white" placeholder="106.xxxx" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Latitude</label>
                            <input type="text" id="latitude" name="latitude" class="form-control font-monospace bg-white" placeholder="-6.xxxx" required>
                        </div>
                    </div>
                    <small class="text-muted d-block mb-2">Geser pin pada peta di bawah ini atau klik untuk mengoreksi posisi lokasi pemasangan:</small>
                    <div id="map" style="height: 300px; width: 100%; border-radius: 8px; border: 1px solid #ced4da;"></div>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold small">Foto Barang</label>
                <input type="file" name="foto_barang" class="form-control" accept="image/*">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Foto Terpasang di Lapangan</label>
                <input type="file" name="foto_pasang" class="form-control" accept="image/*">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold small">Catatan Pemasangan</label>
                <textarea name="catatan" class="form-control" rows="2" placeholder="Catatan tambahan lapangan..."></textarea>
            </div>
        </div>

        <div class="mt-4 text-end">
            <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm">
                <i class="bi bi-save me-1"></i> Simpan Berita Acara Pemasangan
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var defaultLat = -6.1574;
    var defaultLng = 106.7285;

    var map = L.map('map').setView([defaultLat, defaultLng], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var marker = L.marker([defaultLat, defaultLng], {draggable: true}).addTo(map);

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
});
</script>
<?php } ?>