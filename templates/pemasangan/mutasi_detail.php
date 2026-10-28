<?php
$is_draft = ($formulir['StatusData'] === 'TIDAK');
$edit_noref = $_GET['edit_noref'] ?? '';

$selected_item = null;
$nomor_kontak = '-';

if (!empty($edit_noref)) {
    foreach ($items as $it) {
        if ($it['NoRef'] === $edit_noref) {
            $selected_item = $it;
            
            // Ambil Nomor Kontak (NomorAkun) dari master_nomor berdasarkan SimId
            if (!empty($it['SimId'])) {
                $stmt_nk = $conn->prepare("SELECT NomorAkun FROM master_nomor WHERE SimId = ? LIMIT 1");
                $stmt_nk->execute([$it['SimId']]);
                $res_nk = $stmt_nk->fetchColumn();
                if ($res_nk) {
                    $nomor_kontak = $res_nk;
                }
            }
            break;
        }
    }
}
?>

<!-- PUSTAKA LEAFLET CSS UNTUK PETA -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
    .form-kertas { width: 100%; border-collapse: collapse; color: #000; font-family: Arial, sans-serif; font-size: 13px; }
    .form-kertas th, .form-kertas td { border: 1px solid #000; padding: 6px 10px; vertical-align: middle; }
    .form-kertas .judul-tengah { text-align: center; font-weight: bold; }
    .form-kertas .bg-abu { background-color: #f2f2f2; font-weight: bold; text-align: center; }
    .form-kertas .input-garis { border: none; border-bottom: 1px dotted #000; background: transparent; outline: none; }
    .form-kertas .input-tgl { border: none; background: transparent; outline: none; font-family: inherit; }
    .form-kertas .label-radio { margin-right: 15px; cursor: pointer; }
    .img-preview { max-height: 80px; max-width: 120px; object-fit: cover; border: 1px solid #ddd; border-radius: 4px; padding: 2px; display: none; }
    #map_lokasi { height: 300px; width: 100%; border: 1px solid #ccc; border-radius: 4px; margin-top: 10px; z-index: 1; }
</style>

<div class="container-fluid px-0">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold text-success mb-0"><i class="bi bi-arrow-left-right me-2"></i> Eksekusi Mutasi Pasang (Lapangan)</h5>
                <p class="text-muted small mb-0 mt-1">Laksanakan FPK dari Surat Perintah Kerja (SPK) dan buat Berita Acara (BA).</p>
            </div>
            <div class="text-end">
                <span class="badge bg-light text-dark border p-2 d-block mb-1">NO SPK: <span class="fw-bold text-primary"><?= htmlspecialchars($formulir['NoFormOrder']) ?></span></span>
            </div>
        </div>

        <div class="card-body p-4 bg-light">
            
            <!-- FORMULIR INPUT BERITA ACARA -->
            <?php if ($selected_item): ?>
            <div class="card border-dark shadow-sm mb-4">
                <div class="card-body bg-white p-4 overflow-auto">
                    <form action="modules/pemasangan/proses_mutasi.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="no_form_order" value="<?= htmlspecialchars($formulir['NoFormOrder']) ?>">
                        <input type="hidden" name="no_ref" value="<?= htmlspecialchars($selected_item['NoRef']) ?>">
                        <input type="hidden" name="id_pel" value="<?= htmlspecialchars($selected_item['IdPel']) ?>">
                        <input type="hidden" name="sim_id" value="<?= htmlspecialchars($selected_item['SimId'] ?? '') ?>">
                        <input type="hidden" name="imei_modem" value="<?= htmlspecialchars($selected_item['ImeiModem'] ?? '') ?>">
                        
                        <table class="form-kertas mb-3">
                            <!-- HEADER FORMULIR -->
                            <tr>
                                <td width="30%">
                                    <strong>PT PLN (PERSERO)<br>
                                    UPI: <?= htmlspecialchars($_SESSION['UnitUpi'] ?? '-') ?><br>
                                    AP: <?= htmlspecialchars($_SESSION['UnitAp'] ?? '-') ?><br>
                                    UP: <?= htmlspecialchars($_SESSION['UnitUp'] ?? '-') ?>
                                    </strong>
                                </td>
                                <td width="40%" class="judul-tengah fs-5">FORMULIR<br>PERINTAH KERJA</td>
                                <td width="30%">NOMOR DOKUMEN<br><span class="fw-bold text-primary"><?= htmlspecialchars($formulir['NoFormOrder']) ?></span></td>
                            </tr>
                            
                            <!-- DATA PELANGGAN & SPK -->
                            <tr>
                                <td>Unit</td>
                                <td colspan="2">: <?= htmlspecialchars($formulir['UnitUp']) ?></td>
                            </tr>
                            <tr>
                                <td>Tanggal</td>
                                <td colspan="2">: <input type="date" name="tgl_pasang" class="input-tgl fw-bold text-primary" value="<?= $selected_item['TglPasang'] ?? date('Y-m-d') ?>" required></td>
                            </tr>
                            <tr>
                                <td>Jenis PK</td>
                                <td colspan="2">
                                    : &nbsp; 
                                    <label class="label-radio"><input type="radio" checked> PASANG</label>
                                    <label class="label-radio text-muted"><input type="radio" disabled> BONGKAR</label>
                                    <label class="label-radio text-muted"><input type="radio" disabled> PEMELIHARAAN</label>
                                </td>
                            </tr>
                            <tr>
                                <td>Nomor Form FPK</td>
                                <td colspan="2">: <strong><?= htmlspecialchars($selected_item['NoFpk']) ?></strong></td>
                            </tr>
                            <tr>
                                <td>Id Pelanggan</td>
                                <td colspan="2">: <span class="fw-bold text-primary"><?= htmlspecialchars($selected_item['IdPel']) ?></span></td>
                            </tr>
                            <tr>
                                <td>Tikor DIL</td>
                                <td colspan="2">
                                    : Long: <input type="text" class="input-garis" style="width: 150px;" placeholder="............."> 
                                    Lat: <input type="text" class="input-garis" style="width: 150px;" placeholder=".............">
                                </td>
                            </tr>

                            <!-- HEADER PASANG -->
                            <tr>
                                <td colspan="3" class="bg-abu">DATA PEMASANGAN S41</td>
                            </tr>

                            <!-- MODEM & S41 -->
                            <tr>
                                <td><strong>MODEM & S41</strong></td>
                                <td colspan="2"></td>
                            </tr>
                            <tr>
                                <td>NoRef IMEI / Alat</td>
                                <td colspan="2">: <strong><?= htmlspecialchars($selected_item['NoRef']) ?></strong> (Modem: <?= htmlspecialchars($selected_item['ImeiModem'] ?? '-') ?>)</td>
                            </tr>
                            <tr>
                                <td>Nomor SIM</td>
                                <td colspan="2">: <?= htmlspecialchars($selected_item['SimId'] ?? '-') ?></td>
                            </tr>
                            <tr>
                                <td>Nomor Kontak (Akun)</td>
                                <td colspan="2">: <strong><?= htmlspecialchars($nomor_kontak) ?></strong></td>
                            </tr>
                            
                            <!-- DETAIL INSTALASI (DENGAN PETA) -->
                            <tr>
                                <td>Tikor GPS Terpasang</td>
                                <td colspan="2">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span>: Longitude:</span> 
                                        <input type="text" name="longitude" id="input_long" class="input-garis fw-bold text-primary" style="width: 120px;" value="<?= htmlspecialchars($selected_item['Longitude'] ?? '') ?>" placeholder="106.635..."> 
                                        <span>Latitude:</span>
                                        <input type="text" name="latitude" id="input_lat" class="input-garis fw-bold text-primary" style="width: 120px;" value="<?= htmlspecialchars($selected_item['Latitude'] ?? '') ?>" placeholder="-6.178...">
                                        
                                        <button type="button" class="btn btn-sm btn-outline-danger fw-bold ms-auto shadow-sm" onclick="getLocation()"><i class="bi bi-geo-alt-fill me-1"></i> Ambil Lokasi Saat Ini</button>
                                    </div>
                                    <div class="text-muted small mb-1"><i class="bi bi-info-circle me-1"></i> Geser penanda (marker) merah pada peta di bawah untuk menyesuaikan koordinat secara manual.</div>
                                    
                                    <!-- WADAH PETA LEAFLET -->
                                    <div id="map_lokasi"></div>
                                </td>
                            </tr>
                            <tr>
                                <td>Posisi Pasang</td>
                                <td colspan="2">
                                    : &nbsp;
                                    <label class="label-radio fw-bold text-primary"><input type="radio" name="posisi_pasang" value="PANGKAL" <?= (($selected_item['PosisiPasang'] ?? '') === 'PANGKAL') ? 'checked' : '' ?> required> Pangkal</label>
                                    <label class="label-radio fw-bold text-primary"><input type="radio" name="posisi_pasang" value="TENGAH" <?= (($selected_item['PosisiPasang'] ?? '') === 'TENGAH') ? 'checked' : '' ?>> Tengah</label>
                                    <label class="label-radio fw-bold text-primary"><input type="radio" name="posisi_pasang" value="UJUNG" <?= (($selected_item['PosisiPasang'] ?? '') === 'UJUNG') ? 'checked' : '' ?>> Ujung</label>
                                </td>
                            </tr>
                            <tr>
                                <td>Lampu Indikator Relay</td>
                                <td colspan="2">
                                    : &nbsp;
                                    <label class="label-radio fw-bold text-success"><input type="radio" name="kondisi_relay" value="BERFUNGSI" <?= (($selected_item['KondisiRelay'] ?? '') === 'BERFUNGSI') ? 'checked' : '' ?> required> Menyala</label>
                                    <label class="label-radio fw-bold text-danger"><input type="radio" name="kondisi_relay" value="TIDAK BERFUNGSI" <?= (($selected_item['KondisiRelay'] ?? '') === 'TIDAK BERFUNGSI') ? 'checked' : '' ?>> Tidak Menyala</label>
                                </td>
                            </tr>
                            
                            <!-- FOTO DENGAN LIVE PREVIEW -->
                            <tr>
                                <td>Foto Barang Fisik</td>
                                <td colspan="2">
                                    <div class="d-flex align-items-center gap-3">
                                        <span>:</span>
                                        <input type="file" name="foto_barang" accept="image/*" class="form-control form-control-sm" style="width: 250px;" onchange="previewImage(this, 'preview_barang')">
                                        <img id="preview_barang" class="img-preview" src="" alt="Pratinjau Foto Barang">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>Foto Hasil Terpasang</td>
                                <td colspan="2">
                                    <div class="d-flex align-items-center gap-3">
                                        <span>:</span>
                                        <input type="file" name="foto_pasang" accept="image/*" class="form-control form-control-sm" style="width: 250px;" onchange="previewImage(this, 'preview_pasang')">
                                        <img id="preview_pasang" class="img-preview" src="" alt="Pratinjau Foto Pasang">
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- CATATAN -->
                            <tr>
                                <td colspan="3">
                                    <strong>Catatan Tambahan (Opsional):</strong><br>
                                    <textarea class="form-control mt-2 border-0" style="resize: none; background: transparent;" rows="2" placeholder="Tulis catatan kendala atau kondisi di sini..."></textarea>
                                </td>
                            </tr>
                        </table>

                        <div class="mt-4 pt-3 d-flex justify-content-end gap-2">
                            <a href="index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=detail&no_form=<?= urlencode($formulir['NoFormOrder']) ?>" class="btn btn-light border text-secondary px-4 fw-semibold">Batal</a>
                            <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="bi bi-check-circle me-1"></i> Simpan Berita Acara (BA)</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- TABEL DAFTAR TARGET INSTALASI -->
            <h6 class="fw-bold text-dark mb-3">Daftar FPK dalam SPK Ini (Unit: <?= htmlspecialchars($formulir['UnitUp']) ?>)</h6>
            <div class="table-responsive bg-white rounded border">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3">NO</th>
                            <th class="py-3">NO FPK</th>
                            <th class="py-3">ID PELANGGAN</th>
                            <th class="py-3">ALAT (NO REF)</th>
                            <th class="text-center py-3">STATUS PASANG</th>
                            <th class="text-center py-3 pe-4">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">Tidak ada item dalam order ini.</td></tr>
                        <?php else: ?>
                            <?php foreach($items as $idx => $it): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-muted"><?= $idx + 1 ?></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($it['NoFpk']) ?></td>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($it['IdPel']) ?></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($it['NoRef']) ?></td>
                                <td class="text-center">
                                    <?php if($it['StatusPasang'] === 'AKTIF'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success px-3 py-2"><i class="bi bi-check-circle me-1"></i> Terpasang</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning px-3 py-2"><i class="bi bi-clock me-1"></i> Belum Dipasang</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center pe-4">
                                    <a href="index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=detail&no_form=<?= urlencode($formulir['NoFormOrder']) ?>&edit_noref=<?= urlencode($it['NoRef']) ?>" 
                                       class="btn btn-sm btn-outline-primary fw-bold px-3">
                                        <i class="bi bi-pencil-square me-1"></i> <?= ($it['StatusPasang'] === 'AKTIF') ? 'Edit BA' : 'Input BA Pasang' ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php
            // Hitung jumlah total dan jumlah yang sudah terpasang
            $total_fpk = count($items);
            $total_terpasang = 0;
            foreach($items as $it) {
                if($it['StatusPasang'] === 'AKTIF') $total_terpasang++;
            }
            $semua_selesai = ($total_fpk > 0 && $total_fpk === $total_terpasang);
            ?>

            <!-- KOTAK INDIKATOR & TOMBOL FINALISASI SPK -->
            <div class="alert <?= $semua_selesai ? 'alert-success border-success' : 'alert-warning border-warning' ?> d-flex justify-content-between align-items-center mb-4 shadow-sm">
                <div>
                    <h6 class="fw-bold mb-1">
                        <i class="bi <?= $semua_selesai ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-warning' ?> me-2"></i>
                        Status Eksekusi SPK: <?= $total_terpasang ?> dari <?= $total_fpk ?> FPK Telah Terpasang
                    </h6>
                    <p class="small mb-0 text-muted">
                        <?= $semua_selesai ? 'Semua perangkat S41 dalam SPK ini telah selesai dipasang di lapangan. Silakan lakukan finalisasi.' : 'Masih ada FPK yang belum dieksekusi atau belum di-input Berita Acara pasangnya.' ?>
                    </p>
                </div>
                <div>
                    <?php if($semua_selesai): ?>
                        <a href="modules/pemasangan/proses_mutasi.php?action=finalisasi_spk&no_form=<?= urlencode($formulir['NoFormOrder']) ?>" 
                        class="btn btn-success fw-bold px-4 shadow-sm" 
                        onclick="return confirm('Finalisasi SPK ini? Status order akan ditandai selesai secara penuh.')">
                        <i class="bi bi-patch-check-fill me-1"></i> Finalisasi SPK Selesai
                        </a>
                    <?php else: ?>
                        <button class="btn btn-secondary fw-bold px-4" disabled><i class="bi bi-lock-fill me-1"></i> Belum Bisa Finalisasi</button>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="card-footer bg-white p-4">
            <a href="index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=daftar" class="btn btn-light border text-secondary px-4 fw-semibold"><i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar SPK</a>
        </div>
    </div>
</div>

<!-- PUSTAKA LEAFLET JS UNTUK PETA -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// Fungsi JavaScript untuk Live Preview Foto
function previewImage(input, previewId) {
    const previewImg = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            previewImg.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    } else {
        previewImg.src = "";
        previewImg.style.display = 'none';
    }
}

// === IMPLEMENTASI PETA INTERAKTIF ===
if (document.getElementById('map_lokasi')) {
    var latInput = document.getElementById('input_lat');
    var longInput = document.getElementById('input_long');

    // Default ke Koordinat Banten/Tangerang (-6.1783, 106.6350) jika data kosong
    var startLat = latInput.value ? parseFloat(latInput.value) : -6.1783;
    var startLng = longInput.value ? parseFloat(longInput.value) : 106.6350;

    // Inisialisasi Peta
    var map = L.map('map_lokasi').setView([startLat, startLng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Tambahkan Penanda (Marker) yang bisa digeser
    var marker = L.marker([startLat, startLng], {draggable: true}).addTo(map);

    // Saat marker selesai digeser, perbarui input Teks
    marker.on('dragend', function() {
        var position = marker.getLatLng();
        latInput.value = position.lat.toFixed(6);
        longInput.value = position.lng.toFixed(6);
    });

    // Saat input teks diubah manual, pindahkan marker
    function updateMarker() {
        var lat = parseFloat(latInput.value);
        var lng = parseFloat(longInput.value);
        if(!isNaN(lat) && !isNaN(lng)) {
            var newLatLng = new L.LatLng(lat, lng);
            marker.setLatLng(newLatLng);
            map.flyTo(newLatLng, 15);
        }
    }
    latInput.addEventListener('input', updateMarker);
    longInput.addEventListener('input', updateMarker);

    // Fungsi Lacak Lokasi GPS
    function getLocation() {
        if (navigator.geolocation) {
            Swal.fire({
                title: 'Mencari Lokasi...',
                text: 'Mohon izinkan akses lokasi pada browser Anda.',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    var lat = position.coords.latitude;
                    var lng = position.coords.longitude;
                    latInput.value = lat.toFixed(6);
                    longInput.value = lng.toFixed(6);
                    
                    var newLatLng = new L.LatLng(lat, lng);
                    marker.setLatLng(newLatLng);
                    map.flyTo(newLatLng, 18);
                    
                    Swal.close();
                }, 
                function(error) {
                    Swal.fire('Gagal', 'Gagal mendapatkan lokasi GPS. Pastikan GPS aktif dan izin browser diberikan. Pesan error: ' + error.message, 'error');
                }, 
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        } else {
            Swal.fire('Tidak Didukung', 'Browser atau perangkat Anda tidak mendukung fitur Geolocation GPS.', 'error');
        }
    }
}
</script>