<?php
// File: modules/penghapusan/form_pk.php
$current_action = $_GET['action'] ?? 'form_pk';
?>

<!-- PUSTAKA CSS & JS WAJIB UNTUK STYLING & MAP -->
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
    .form-grid { display: grid; grid-template-columns: 180px 1fr 140px 1fr; border-top: 1px solid #d1d5db; border-left: 1px solid #d1d5db; }
    .form-cell { padding: 10px 14px; border-right: 1px solid #d1d5db; border-bottom: 1px solid #d1d5db; display: flex; align-items: center; }
    .form-label { font-weight: 600; color: #374151; background-color: #f9fafb; }
    #map { height: 320px; width: 100%; z-index: 1; }
</style>

<div class="max-w-6xl mx-auto bg-white shadow-md border border-gray-200 rounded-lg p-6 my-4 font-sans">
    
    <!-- Render Navigasi Sub Entitas -->
    <?php 
    if (function_exists('render_sub_entitas_penghapusan')) {
        render_sub_entitas_penghapusan($current_action); 
    }
    ?>

    <!-- Kop Surat Formulir -->
    <div class="flex justify-between items-center pb-4 border-b border-gray-300 mb-6">
        <div>
            <h1 class="font-bold text-gray-900 text-lg leading-tight">PT PLN (PERSERO) UID BANTEN</h1>
            <h2 class="font-bold text-red-600 text-sm leading-tight">FORMULIR PERINTAH KERJA PENGHAPUSAN</h2>
        </div>
        <div>
            <span class="border border-gray-400 px-3 py-1 font-mono text-sm font-bold bg-gray-50 text-gray-700 rounded shadow-sm">FM-2026-DEL</span>
        </div>
    </div>

    <!-- Form Pengisian -->
    <form action="functions/proses_penghapusan.php?action=buat_pk" method="POST">
        
        <!-- Grid Input Utama -->
        <div class="form-grid text-sm mb-6 rounded overflow-hidden shadow-sm">
            
            <!-- Baris 1 -->
            <div class="form-cell form-label">Jenis PK</div>
            <div class="form-cell bg-white">
                <span class="bg-yellow-400 text-yellow-950 font-bold px-3 py-1 rounded text-xs tracking-wider shadow-sm">PENGHAPUSAN</span>
            </div>
            
            <div class="form-cell form-label">Tanggal</div>
            <div class="form-cell bg-white">
                <input type="date" name="tanggal" value="<?php echo date('Y-m-d'); ?>" class="w-full border border-gray-300 rounded px-3 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500" required>
            </div>

            <!-- Baris 2 -->
            <div class="form-cell form-label">Id Pelanggan</div>
            <div class="form-cell bg-white">
                <select name="id_pelanggan" class="w-full border border-gray-300 rounded px-3 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500" required>
                    <option value="">-- Pilih ID Pelanggan --</option>
                    <option value="546100000119">546100000119 (S41 - Poris Tangerang)</option>
                    <option value="546100000120">546100000120 (S41 - Karawaci Tangerang)</option>
                </select>
            </div>
            
            <div class="form-cell form-label">Nomor Form</div>
            <div class="form-cell bg-white text-gray-400 italic">Otomatis sistem</div>

            <!-- Baris 3 (Koordinat & GPS) -->
            <div class="form-cell form-label">Tikor DIL</div>
            <div class="form-cell col-span-3 flex justify-between items-center bg-white gap-4">
                <div class="flex gap-4 items-center flex-1">
                    <div class="flex items-center gap-2">
                        <span class="text-gray-500 text-xs font-semibold">Long:</span>
                        <input type="text" id="lng" name="longitude" class="border border-gray-300 rounded px-2.5 py-1 w-36 focus:outline-none text-sm bg-gray-50" readonly placeholder="106.631889">
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-gray-500 text-xs font-semibold">Lat:</span>
                        <input type="text" id="lat" name="latitude" class="border border-gray-300 rounded px-2.5 py-1 w-36 focus:outline-none text-sm bg-gray-50" readonly placeholder="-6.178306">
                    </div>
                </div>
                <button type="button" onclick="getLocation()" class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-1.5 rounded flex items-center gap-2 text-xs font-bold transition-colors shadow-sm cursor-pointer border-none">
                    <i class="fa-solid fa-location-dot"></i> Ambil GPS Saya
                </button>
            </div>
        </div>

        <!-- Container Peta Leaflet -->
        <div class="mb-6 rounded-lg overflow-hidden border border-gray-300 shadow-sm">
            <div id="map"></div>
        </div>

        <!-- Tabel Perangkat Eksisting -->
        <div class="border border-gray-300 text-sm rounded-lg mb-6 overflow-hidden shadow-sm">
            <div class="grid grid-cols-2 bg-gray-100 font-bold border-b border-gray-300 text-gray-700">
                <div class="p-3 border-r border-gray-300">PERANGKAT EKSISTING (DIL)</div>
                <div class="p-3">ALASAN PENGHAPUSAN ASET</div>
            </div>
            <div class="grid grid-cols-2 bg-white">
                <div class="p-3 font-medium border-r border-gray-300 text-gray-700 flex items-center bg-gray-50">
                    Keterangan Kondisi Perangkat
                </div>
                <div class="p-3">
                     <textarea name="alasan_penghapusan" rows="2" class="w-full border border-gray-300 p-2.5 text-sm rounded focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Ketik alasan penghapusan aset (misal: Mati Total / Rusak Parah)..." required></textarea>
                </div>
            </div>
        </div>

        <!-- Tombol Aksi Batal & Simpan -->
        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
            <a href="?page=penghapusan&action=monitor_pk" class="px-5 py-2 border border-gray-300 rounded-md text-gray-700 font-medium hover:bg-gray-50 text-sm no-underline transition-colors">Batal</a>
            <button type="submit" class="px-6 py-2 bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-bold rounded-md shadow-sm text-sm transition-colors border-none cursor-pointer">Simpan FPK</button>
        </div>
        
    </form>
</div>

<!-- Script Peta Leaflet -->
<script>
    var initialLat = -6.178306;
    var initialLng = 106.631889;
    var map = L.map('map').setView([initialLat, initialLng], 13);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var marker = L.marker([initialLat, initialLng], {draggable: true}).addTo(map);

    marker.on('dragend', function(e) {
        var pos = marker.getLatLng();
        document.getElementById('lat').value = pos.lat.toFixed(6);
        document.getElementById('lng').value = pos.lng.toFixed(6);
        map.panTo(new L.LatLng(pos.lat, pos.lng));
    });

    function getLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(pos) {
                let uLat = pos.coords.latitude;
                let uLng = pos.coords.longitude;
                document.getElementById('lat').value = uLat.toFixed(6);
                document.getElementById('lng').value = uLng.toFixed(6);
                let newLatLng = new L.LatLng(uLat, uLng);
                marker.setLatLng(newLatLng);
                map.setView(newLatLng, 15);
            }, function(err) {
                alert("Gagal mengambil posisi GPS. Pastikan izin lokasi aktif di browser.");
            });
        } else {
            alert("Fitur Geolocation tidak didukung browser ini.");
        }
    }
</script>