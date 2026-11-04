<?php
// File: modules/penghapusan/index.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/penghapusan_ui.php';
require_once __DIR__ . '/akses.php';

$tab = $_GET['tab'] ?? '';
$sub = $_GET['sub'] ?? '';

// Default Fallback
if (empty($tab)) {
    if (pga_boleh_hapus('order_penghapusan')) {
        $tab = 'eksekusi';
        $sub = 'order_penghapusan';
    } else {
        $tab = 'monitoring';
        $sub = 'monitor_order';
    }
}

// Semakan Akses Eksekusi
if ($tab === 'eksekusi' && !pga_boleh_hapus('order_penghapusan')) {
    echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script><script>
        Swal.fire({
            icon: "error", 
            title: "Akses Ditolak", 
            text: "Menu Eksekusi hanya dapat diakses oleh SF.AP dan SF.UP.", 
            confirmButtonText: "OK",
            confirmButtonColor: "#0d6efd"
        }).then(() => { 
            location.href = "?page=penghapusan&tab=monitoring&sub=monitor_order"; 
        });
    </script>';
    return;
}

// Ambil Data dari Database
$db = $pdo ?? $conn ?? $db ?? null;
$data_pk = [];

if ($db) {
    try {
        $stmt = $db->query("SELECT * FROM perintah_kerja_hapus ORDER BY id DESC");
        if ($stmt) {
            $data_pk = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {}
}

// Fallback Data Contoh
if (empty($data_pk)) {
    $data_pk = [
        [
            'id' => 1,
            'no_pk' => '5661000120261104-F.F',
            'tgl_pk' => '2026-11-04',
            'id_pelanggan' => '546100000119',
            'no_ref_bongkar' => '866782046622433',
            'no_ref_pasang'  => '-',
            'sim_id' => '621007864233621700',
            'modem' => '867749010776270',
            'no_order' => '5661000120261104-F.E',
            'petugas' => 'IPM.001',
            'status' => 'BA SELESAI'
        ],
        [
            'id' => 2,
            'no_pk' => '5661000120261103-F.F',
            'tgl_pk' => '2026-11-03',
            'id_pelanggan' => '546100000119',
            'no_ref_bongkar' => '860389053626848',
            'no_ref_pasang'  => '866782046622433',
            'sim_id' => '621000539421600900',
            'modem' => '355872010067224',
            'no_order' => '5661000120261103-F.E',
            'petugas' => 'IPM.001',
            'status' => 'BA SELESAI'
        ]
    ];
}
?>

<!-- Pustaka Leaflet untuk Peta Modal -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
    .modal-grid { display: grid; grid-template-columns: 160px 1fr 130px 1fr; border-top: 1px solid #e5e7eb; border-left: 1px solid #e5e7eb; }
    .modal-cell { padding: 8px 12px; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; }
    .modal-label { font-weight: 600; color: #374151; background-color: #f9fafb; font-size: 12px; }
    #modalMap { height: 260px; width: 100%; z-index: 1; }
</style>

<div class="max-w-7xl mx-auto w-full font-sans p-6">
    
    <!-- Header Navigasi -->
    <?php render_header_penghapusan($tab, $sub); ?>

    <!-- JADUAL / KANDUNGAN UTAMA -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200/80 p-6 mt-4">
        
        <?php if ($tab === 'eksekusi' && $sub === 'order_penghapusan'): ?>
            
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-base font-bold text-blue-600 tracking-tight">Daftar FPK & Order Penghapusan</h3>
                
                <div class="relative w-80">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </span>
                    <input type="text" placeholder="Cari no form, idpel, no ref..." class="w-full pl-9 pr-4 py-2 bg-gray-50/60 border border-gray-200 rounded-lg text-xs text-gray-700 focus:bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 transition-all placeholder:text-gray-400">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-gray-700 border-collapse">
                    <thead class="border-y border-gray-200 bg-gray-50/80 text-gray-800 uppercase font-bold text-[11px] tracking-wider">
                        <tr>
                            <th class="py-3 px-3 w-10 text-center">#</th>
                            <th class="py-3 px-3">NO FPK</th>
                            <th class="py-3 px-3">TGL FPK</th>
                            <th class="py-3 px-3">IDPEL / NO REF</th>
                            <th class="py-3 px-3">SIM ID</th>
                            <th class="py-3 px-3">MODEM</th>
                            <th class="py-3 px-3">NO ORDER / PETUGAS</th>
                            <th class="py-3 px-3 text-center">TAHAP / STATUS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($data_pk as $idx => $row): ?>
                        <tr class="hover:bg-gray-50/80 transition-colors">
                            <td class="py-3.5 px-3 text-center text-gray-400 font-medium"><?php echo $idx + 1; ?></td>
                            <td class="py-3.5 px-3 font-bold text-gray-900"><?php echo htmlspecialchars($row['no_pk']); ?></td>
                            <td class="py-3.5 px-3 font-medium text-gray-600"><?php echo htmlspecialchars($row['tgl_pk']); ?></td>
                            <td class="py-3.5 px-3">
                                <div class="font-bold text-gray-900">IDPEL: <?php echo htmlspecialchars($row['id_pelanggan']); ?></div>
                                <div class="text-[11px] text-gray-500 mt-0.5">BONGKAR: <?php echo htmlspecialchars($row['no_ref_bongkar'] ?? '-'); ?></div>
                                <div class="text-[11px] text-gray-500">PASANG: <?php echo htmlspecialchars($row['no_ref_pasang'] ?? '-'); ?></div>
                            </td>
                            <td class="py-3.5 px-3">
                                <span class="bg-cyan-100 text-cyan-700 font-mono text-[11px] px-2 py-0.5 rounded font-semibold border border-cyan-200/60 inline-block">
                                    <?php echo htmlspecialchars($row['sim_id'] ?? '621007864233621700'); ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-3 font-mono text-gray-700 font-semibold text-[11px]">
                                <?php echo htmlspecialchars($row['modem'] ?? '867749010776270'); ?>
                            </td>
                            <td class="py-3.5 px-3">
                                <div class="text-blue-600 font-bold flex items-center gap-1.5 cursor-pointer hover:underline" 
                                     onclick="cetakOrder('<?php echo $row['no_order']; ?>', '<?php echo $row['no_pk']; ?>', '<?php echo $row['id_pelanggan']; ?>', '<?php echo $row['no_ref_bongkar'] ?? ''; ?>')">
                                    <?php echo htmlspecialchars($row['no_order']); ?> 
                                    <i class="fa-solid fa-print text-blue-500 text-[11px]" title="Cetak Order"></i>
                                </div>
                                <div class="text-[11px] text-gray-500 font-medium mt-0.5"><?php echo htmlspecialchars($row['petugas']); ?></div>
                            </td>
                            <td class="py-3.5 px-3 text-center">
                                <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-[10px] font-bold tracking-wider inline-block">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($tab === 'eksekusi' && $sub === 'mutasi_penghapusan'): ?>
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-base font-bold text-blue-600 tracking-tight">Daftar Mutasi Penghapusan Aset</h3>
                <div class="relative w-80">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </span>
                    <input type="text" placeholder="Cari no BA, no order..." class="w-full pl-9 pr-4 py-2 bg-gray-50/60 border border-gray-200 rounded-lg text-xs focus:bg-white focus:outline-none">
                </div>
            </div>
            <!-- Tabel Mutasi -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-gray-700 border-collapse">
                    <thead class="border-y border-gray-200 bg-gray-50/80 text-gray-800 uppercase font-bold text-[11px]">
                        <tr>
                            <th class="py-3 px-3 w-10 text-center">#</th>
                            <th class="py-3 px-3">NO BERITA ACARA</th>
                            <th class="py-3 px-3">TGL MUTASI</th>
                            <th class="py-3 px-3">NO ORDER / ASET</th>
                            <th class="py-3 px-3">CATATAN HASIL MUTASI</th>
                            <th class="py-3 px-3 text-center">STATUS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr class="hover:bg-gray-50">
                            <td class="py-3.5 px-3 text-center text-gray-400">1</td>
                            <td class="py-3.5 px-3 font-bold text-gray-900">BAM-DEL-001</td>
                            <td class="py-3.5 px-3">2026-11-03</td>
                            <td class="py-3.5 px-3">
                                <div class="font-bold text-blue-600">5661000120261104-F.E</div>
                                <div class="text-[11px] text-gray-500">Modem Maestro 100 Ext</div>
                            </td>
                            <td class="py-3.5 px-3 text-gray-500 italic">Perangkat berhasil ditarik dari lokasi pelanggan.</td>
                            <td class="py-3.5 px-3 text-center">
                                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-[10px] font-bold">TERPROSES</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <!-- Monitoring View -->
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-base font-bold text-gray-800 tracking-tight">Rekap Monitoring <?php echo ($sub === 'monitor_order') ? 'Order Penghapusan' : 'Mutasi Penghapusan'; ?></h3>
                <div class="relative w-80">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </span>
                    <input type="text" placeholder="Cari data monitoring..." class="w-full pl-9 pr-4 py-2 bg-gray-50/60 border border-gray-200 rounded-lg text-xs focus:bg-white focus:outline-none">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-gray-700 border-collapse">
                    <thead class="border-y border-gray-200 bg-gray-50/80 text-gray-800 uppercase font-bold text-[11px]">
                        <tr>
                            <th class="py-3 px-3 w-10 text-center">#</th>
                            <th class="py-3 px-3">NO REF / FPK</th>
                            <th class="py-3 px-3">IDPELANGGAN</th>
                            <th class="py-3 px-3">ALASAN PENGHAPUSAN</th>
                            <th class="py-3 px-3 text-center">STATUS MONITORING</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($data_pk as $idx => $row): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3.5 px-3 text-center text-gray-400"><?php echo $idx + 1; ?></td>
                            <td class="py-3.5 px-3 font-bold text-gray-900"><?php echo htmlspecialchars($row['no_pk']); ?></td>
                            <td class="py-3.5 px-3 font-bold text-gray-800"><?php echo htmlspecialchars($row['id_pelanggan']); ?></td>
                            <td class="py-3.5 px-3 text-gray-600">Pelepasan perangkat modem rusak total</td>
                            <td class="py-3.5 px-3 text-center">
                                <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-[10px] font-bold">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Footer Pagination -->
        <div class="mt-5 text-xs text-gray-500 border-t border-gray-100 pt-4 flex justify-between items-center">
            <div>Menampilkan <span class="font-bold text-gray-800">1 - <?php echo count($data_pk); ?></span> dari <span class="font-bold text-gray-800"><?php echo count($data_pk); ?></span> data</div>
        </div>

    </div>
</div>


<!-- ==========================================
     MODAL 1: FORMULIR FPK (PERINTAH KERJA) 
     (Rekabentuk mengikut gambar image_40cb84.jpg)
============================================== -->
<div id="modalFpk" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-lg shadow-2xl border border-gray-300 w-full max-w-5xl overflow-hidden max-h-[90vh] flex flex-col">
        
        <!-- Modal Header -->
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-300 bg-white">
            <div>
                <h2 class="font-bold text-gray-900 text-sm leading-tight">PT PLN (PERSERO) UID BANTEN</h2>
                <h3 class="font-bold text-red-600 text-xs leading-tight">PILIH ID PELANGGAN</h3>
            </div>
            <div class="text-center">
                <h2 class="text-base font-bold text-gray-800 uppercase tracking-wide">FORMULIR PERINTAH KERJA</h2>
            </div>
            <div>
                <span class="border border-gray-400 px-3 py-1 font-mono text-xs font-bold bg-gray-50 text-gray-700 rounded">FM-2026-Z</span>
            </div>
        </div>

        <!-- Modal Body Scrollable -->
        <form action="functions/proses_penghapusan.php?action=buat_pk" method="POST" class="overflow-y-auto p-6 flex-1 text-xs">
            
            <div class="modal-grid mb-4">
                <div class="modal-cell modal-label">Jenis PK</div>
                <div class="modal-cell bg-white">
                    <span class="bg-yellow-400 text-yellow-950 font-bold px-2.5 py-0.5 rounded text-[11px]">PENGHAPUSAN</span>
                </div>
                
                <div class="modal-cell modal-label">Tanggal</div>
                <div class="modal-cell bg-white">
                    <input type="date" name="tanggal" value="<?php echo date('Y-m-d'); ?>" class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:outline-none" required>
                </div>

                <div class="modal-cell modal-label">Id Pelanggan</div>
                <div class="modal-cell bg-white">
                    <select name="id_pelanggan" class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:outline-none" required>
                        <option value="">Ketik untuk mencari ID Pelanggan...</option>
                        <option value="546100000119">546100000119 (S41 - Poris Tangerang)</option>
                        <option value="546100000120">546100000120 (S41 - Karawaci Tangerang)</option>
                    </select>
                </div>
                
                <div class="modal-cell modal-label">Nomor Form</div>
                <div class="modal-cell bg-white text-gray-400 italic">Otomatis sistem</div>

                <div class="modal-cell modal-label">Tikor DIL</div>
                <div class="modal-cell col-span-3 flex justify-between items-center bg-white gap-2">
                    <div class="flex gap-4 items-center flex-1">
                        <div class="flex items-center gap-1.5">
                            <span class="text-gray-500">Long:</span>
                            <input type="text" id="modalLng" name="longitude" class="border border-gray-300 rounded px-2 py-1 w-28 bg-gray-50 focus:outline-none" readonly placeholder="106.631889">
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-gray-500">Lat:</span>
                            <input type="text" id="modalLat" name="latitude" class="border border-gray-300 rounded px-2 py-1 w-28 bg-gray-50 focus:outline-none" readonly placeholder="-6.178306">
                        </div>
                    </div>
                    <button type="button" onclick="getModalGps()" class="bg-teal-600 hover:bg-teal-700 text-white px-3 py-1.5 rounded flex items-center gap-1.5 font-bold transition-colors border-none cursor-pointer">
                        <i class="fa-solid fa-location-dot"></i> Ambil GPS Saya
                    </button>
                </div>
            </div>

            <!-- Peta Modal -->
            <div class="mb-4 rounded border border-gray-300 overflow-hidden">
                <div id="modalMap"></div>
            </div>

            <!-- Tabel Perangkat Eksisting -->
            <div class="border border-gray-300 text-xs rounded mb-4 overflow-hidden">
                <div class="grid grid-cols-2 bg-gray-50 font-bold border-b border-gray-300 text-gray-700 text-center">
                    <div class="p-2 border-r border-gray-300">PERANGKAT EKSISTING (DIL)</div>
                    <div class="p-2">ALASAN PENGHAPUSAN ASET</div>
                </div>
                <div class="grid grid-cols-2 bg-white">
                    <div class="p-3 font-medium border-r border-gray-300 text-gray-600 flex items-center bg-gray-50">
                        Keterangan Kondisi Perangkat
                    </div>
                    <div class="p-2">
                         <textarea name="alasan_penghapusan" rows="2" class="w-full border border-gray-300 p-2 rounded focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Ketik alasan penghapusan aset..." required></textarea>
                    </div>
                </div>
            </div>

            <!-- Modal Action Buttons -->
            <div class="flex justify-end gap-3 pt-3 border-t border-gray-200">
                <button type="button" onclick="closeModalFpk()" class="px-5 py-2 border border-gray-300 rounded-md text-gray-700 font-medium hover:bg-gray-100 transition-colors">Batal</button>
                <button type="submit" class="px-6 py-2 bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-bold rounded-md shadow-sm border-none cursor-pointer">Simpan FPK</button>
            </div>
            
        </form>
    </div>
</div>


<!-- ==========================================
     MODAL 2: FORMULIR ORDER PENGHAPUSAN 
     (Rekabentuk mengikut gambar image_40cc21.png)
============================================== -->
<div id="modalOrder" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-xl shadow-2xl border border-gray-200 w-full max-w-xl overflow-hidden">
        
        <!-- Header Modal Order -->
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-file-lines text-blue-600 text-lg"></i>
                <h3 class="font-bold text-blue-600 text-base">Formulir Order Penghapusan</h3>
            </div>
            <button onclick="closeModalOrder()" class="text-gray-400 hover:text-gray-600 border-none bg-transparent cursor-pointer text-base">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Body Form Modal Order -->
        <form action="functions/proses_penghapusan.php?action=buat_order" method="POST" class="p-6 text-xs space-y-4">
            
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block font-bold text-gray-700 mb-1 uppercase text-[10px] tracking-wider">NO ORDER</label>
                    <input type="text" name="no_order" value="Otomatis sistem" readonly class="w-full border border-gray-200 rounded-lg p-2.5 bg-gray-50 text-gray-500 italic focus:outline-none">
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1 uppercase text-[10px] tracking-wider">TANGGAL ORDER *</label>
                    <input type="date" name="tanggal_order" value="<?php echo date('Y-m-d'); ?>" class="w-full border border-gray-300 rounded-lg p-2.5 focus:outline-none focus:ring-1 focus:ring-blue-500" required>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1 uppercase text-[10px] tracking-wider">PETUGAS *</label>
                    <select name="petugas" class="w-full border border-gray-300 rounded-lg p-2.5 bg-white focus:outline-none focus:ring-1 focus:ring-blue-500" required>
                        <option value="IPM.001">IPM.001</option>
                        <option value="IPM.002">IPM.002</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block font-bold text-gray-700 mb-1.5 uppercase text-[10px] tracking-wider">PILIH FPK YANG DIMASUKKAN KE ORDER *</label>
                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 text-center text-gray-400 font-medium">
                    <?php if (!empty($data_pk)): ?>
                        <select name="id_pk" class="w-full border border-gray-300 rounded-lg p-2 bg-white text-gray-700 text-xs focus:outline-none" required>
                            <option value="">-- Pilih FPK Penghapusan --</option>
                            <?php foreach($data_pk as $pkItem): ?>
                                <option value="<?php echo $pkItem['no_pk']; ?>"><?php echo $pkItem['no_pk']; ?> (IDPEL: <?php echo $pkItem['id_pelanggan']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        Tidak ada FPK bebas yang tersedia. Buat FPK terlebih dahulu melalui tombol "Buat FPK".
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal Action Buttons -->
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeModalOrder()" class="px-5 py-2 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors">Batal</button>
                <button type="submit" class="px-5 py-2 bg-blue-500 hover:bg-blue-600 text-white font-bold rounded-lg shadow-sm border-none cursor-pointer transition-colors">Terbitkan Order</button>
            </div>
            
        </form>
    </div>
</div>


<!-- ==========================================
     SCRIPT JAVASCRIPT KAWALAN MODAL & MAP
============================================== -->
<script>
    var leafletModalMap = null;
    var modalMarker = null;

    // --- LOGIK MODAL FPK ---
    function openModalFpk() {
        document.getElementById('modalFpk').classList.remove('hidden');
        
        // Inisialisasi Peta Leaflet jika belum ada
        setTimeout(function() {
            if (!leafletModalMap) {
                var initialLat = -6.178306;
                var initialLng = 106.631889;

                leafletModalMap = L.map('modalMap').setView([initialLat, initialLng], 13);
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(leafletModalMap);

                modalMarker = L.marker([initialLat, initialLng], {draggable: true}).addTo(leafletModalMap);

                modalMarker.on('dragend', function(e) {
                    var pos = modalMarker.getLatLng();
                    document.getElementById('modalLat').value = pos.lat.toFixed(6);
                    document.getElementById('modalLng').value = pos.lng.toFixed(6);
                    leafletModalMap.panTo(new L.LatLng(pos.lat, pos.lng));
                });
            }
            // Betulkan saiz render peta Leaflet dalam modal
            leafletModalMap.invalidateSize();
        }, 200);
    }

    function closeModalFpk() {
        document.getElementById('modalFpk').classList.add('hidden');
    }

    function getModalGps() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(pos) {
                let uLat = pos.coords.latitude;
                let uLng = pos.coords.longitude;
                document.getElementById('modalLat').value = uLat.toFixed(6);
                document.getElementById('modalLng').value = uLng.toFixed(6);
                let newLatLng = new L.LatLng(uLat, uLng);
                modalMarker.setLatLng(newLatLng);
                leafletModalMap.setView(newLatLng, 15);
            });
        } else {
            alert("Geolocation tidak didukung oleh pelayar ini.");
        }
    }

    // --- LOGIK MODAL ORDER ---
    function openModalOrder() {
        document.getElementById('modalOrder').classList.remove('hidden');
    }

    function closeModalOrder() {
        document.getElementById('modalOrder').classList.add('hidden');
    }

    // --- LOGIK CETAK DOKUMEN ---
    function cetakOrder(noOrder, noPk, idpel, rBongkar) {
        var printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Cetak Order - ' + noOrder + '</title>');
        printWindow.document.write('<style>body{font-family:Arial,sans-serif;padding:30px;} .header{border-bottom:2px solid #000;padding-bottom:10px;margin-bottom:20px;}</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write('<div class="header"><h2>PT PLN (PERSERO) UID BANTEN</h2><h3>SURAT ORDER PENGHAPUSAN ASET</h3></div>');
        printWindow.document.write('<p><strong>No Order:</strong> ' + noOrder + '</p>');
        printWindow.document.write('<p><strong>No FPK Referensi:</strong> ' + noPk + '</p>');
        printWindow.document.write('<p><strong>ID Pelanggan:</strong> ' + idpel + '</p>');
        printWindow.document.write('<p><strong>No Ref Bongkar:</strong> ' + rBongkar + '</p>');
        printWindow.document.write('<br><br><div style="float:right;text-align:center;"><p>Petugas Pelaksana</p><br><br><p>( ........................ )</p></div>');
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(function() { printWindow.print(); }, 500);
    }
</script>