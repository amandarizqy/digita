<?php
// File: modules/penghapusan/index.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/penghapusan_ui.php';
require_once __DIR__ . '/akses.php';

$tab    = $_GET['tab']    ?? '';
$sub    = $_GET['sub']    ?? '';
$action = $_GET['action'] ?? '';

// Tentukan tab & sub default berdasarkan role user jika parameter URL kosong
if (empty($tab)) {
    if (pga_boleh_hapus('order_penghapusan')) {
        $tab = 'eksekusi';
        $sub = 'order_penghapusan';
    } else {
        $tab = 'monitoring';
        $sub = 'monitor_order';
    }
}

// Cek Proteksi Hak Akses untuk Form Khusus (Buat PK, Order, BA Mutasi)
if (!empty($action)) {
    if (!pga_boleh_hapus($action)) {
        echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script><script>
            Swal.fire({
                icon: "error", 
                title: "Akses Ditolak", 
                text: "Menu Eksekusi ini hanya dapat diakses oleh SF.AP dan SF.UP.", 
                confirmButtonText: "OK",
                confirmButtonColor: "#0d6efd"
            }).then(() => { 
                location.href = "?page=penghapusan&tab=monitoring&sub=monitor_order"; 
            });
        </script>';
        return;
    }
    
    if ($action === 'form_pk') { include __DIR__ . '/form_pk.php'; return; }
    if ($action === 'form_order') { include __DIR__ . '/form_order.php'; return; }
    if ($action === 'form_ba') { include __DIR__ . '/form_ba.php'; return; }
}

// Proteksi Tab Eksekusi jika diakses langsung lewat URL
if ($tab === 'eksekusi' && !pga_boleh_hapus('order_penghapusan')) {
    echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script><script>
        Swal.fire({
            icon: "error", 
            title: "Akses Ditolak", 
            text: "Menu Eksekusi hanya dapat diakses oleh staf unit (SF.AP dan SF.UP).", 
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
    } catch (Exception $e) {
        // Fallback jika query gagal
    }
}

// Data Dummy jika DB belum berisi data
if (empty($data_pk)) {
    $data_pk = [
        [
            'no_pk' => 'PK-2026-DEL-001',
            'tgl_pk' => '2026-11-02',
            'id_pelanggan' => '546100000119',
            'alasan' => 'Modem Maestro 100 Ext - Rusak Total',
            'no_order' => 'ORD-DEL-001',
            'petugas' => 'IPM.001',
            'status' => 'BA SELESAI'
        ]
    ];
}
?>

<!-- Script Cetak Dokumen Order -->
<script>
function cetakOrder(noOrder, noPk, idpel, alasan) {
    var printWindow = window.open('', '', 'height=600,width=800');
    printWindow.document.write('<html><head><title>Cetak Order - ' + noOrder + '</title>');
    printWindow.document.write('<style>body{font-family:Arial,sans-serif;padding:30px;} .header{border-bottom:2px solid #000;padding-bottom:10px;margin-bottom:20px;} .table{width:100%;border-collapse:collapse;} .table td, .table th{border:1px solid #ccc;padding:8px;}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<div class="header"><h2>PT PLN (PERSERO) UID BANTEN</h2><h3>SURAT ORDER PENGHAPUSAN ASET</h3></div>');
    printWindow.document.write('<p><strong>No Order:</strong> ' + noOrder + '</p>');
    printWindow.document.write('<p><strong>No PK Referensi:</strong> ' + noPk + '</p>');
    printWindow.document.write('<p><strong>ID Pelanggan:</strong> ' + idpel + '</p>');
    printWindow.document.write('<p><strong>Keterangan Aset / Alasan:</strong> ' + alasan + '</p>');
    printWindow.document.write('<br><br><div style="float:right;text-align:center;"><p>Petugas Pelaksana</p><br><br><p>( ........................ )</p></div>');
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    setTimeout(function() { printWindow.print(); }, 500);
}
</script>

<div class="max-w-7xl mx-auto w-full font-sans p-6">
    
    <?php render_header_penghapusan($tab, $sub); ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-4">
        
        <?php if ($tab === 'eksekusi' && $sub === 'order_penghapusan'): ?>
            <!-- EKSEKUSI: ORDER PENGHAPUSAN (SF.AP & SF.UP) -->
            <h2 class="text-lg font-bold text-blue-600 mb-1">PK & Order Penghapusan</h2>
            <p class="text-gray-500 text-xs mb-6">Langkah 1: Buat PK Penghapusan &rarr; Langkah 2: Gabungkan PK ke dalam Surat Order Penghapusan.</p>

            <div class="flex justify-between items-center mb-6">
                <div class="flex w-96 shadow-sm rounded-md">
                    <input type="text" placeholder="Cari no form, idpel, no ref..." class="w-full border-y border-l border-gray-300 rounded-l-md py-2 px-3 text-xs focus:outline-none">
                    <button class="bg-gray-50 border-y border-r border-gray-300 rounded-r-md px-3 text-gray-400">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                </div>
                
                <div class="flex gap-2 text-xs font-bold">
                    <a href="?page=penghapusan&action=form_pk" class="bg-yellow-400 hover:bg-yellow-500 text-gray-900 px-4 py-2 rounded-md flex items-center gap-2 shadow-sm no-underline">
                        <i class="fa-solid fa-wrench"></i>  Buat PK
                    </a>
                    <a href="?page=penghapusan&action=form_order" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md flex items-center gap-2 shadow-sm no-underline">
                        <i class="fa-solid fa-file-circle-plus"></i>  Buat Order
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-gray-700 border-collapse">
                    <thead class="border-y bg-gray-50 text-gray-500 uppercase font-bold">
                        <tr>
                            <th class="py-3 px-3 w-10">#</th>
                            <th class="py-3 px-3">NO PK</th>
                            <th class="py-3 px-3">TGL PK</th>
                            <th class="py-3 px-3">IDPEL / NO REF</th>
                            <th class="py-3 px-3">ALASAN PENGHAPUSAN</th>
                            <th class="py-3 px-3">NO ORDER / PETUGAS</th>
                            <th class="py-3 px-3 text-center">TAHAP / STATUS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($data_pk as $idx => $row): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3.5 px-3 text-gray-400"><?php echo $idx + 1; ?></td>
                            <td class="py-3.5 px-3 font-bold text-gray-900"><?php echo htmlspecialchars($row['no_pk']); ?></td>
                            <td class="py-3.5 px-3"><?php echo htmlspecialchars($row['tgl_pk']); ?></td>
                            <td class="py-3.5 px-3">
                                <div class="font-bold text-gray-800">IDPEL: <?php echo htmlspecialchars($row['id_pelanggan']); ?></div>
                            </td>
                            <td class="py-3.5 px-3 font-semibold text-gray-700"><?php echo htmlspecialchars($row['alasan']); ?></td>
                            <td class="py-3.5 px-3">
                                <div class="text-blue-600 font-bold flex items-center gap-1 cursor-pointer hover:underline" 
                                     onclick="cetakOrder('<?php echo $row['no_order']; ?>', '<?php echo $row['no_pk']; ?>', '<?php echo $row['id_pelanggan']; ?>', '<?php echo addslashes($row['alasan']); ?>')">
                                    <?php echo htmlspecialchars($row['no_order']); ?> 
                                    <i class="fa-solid fa-print text-gray-400 hover:text-blue-600 ml-1" title="Cetak Order"></i>
                                </div>
                                <div class="text-[11px] text-gray-400"><?php echo htmlspecialchars($row['petugas']); ?></div>
                            </td>
                            <td class="py-3.5 px-3 text-center">
                                <span class="bg-green-100 text-green-700 px-2.5 py-1 rounded-full text-[10px] font-bold border border-green-200">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($tab === 'eksekusi' && $sub === 'mutasi_penghapusan'): ?>
            <!-- EKSEKUSI: MUTASI PENGHAPUSAN (SF.AP & SF.UP) -->
            <h2 class="text-lg font-bold text-green-600 mb-1">Mutasi Penghapusan Aset</h2>
            <p class="text-gray-500 text-xs mb-6">Langkah 3: Eksekusi pelepasan aset di lapangan dan terbitkan Berita Acara Mutasi (BAM).</p>

            <div class="flex justify-between items-center mb-6">
                <div class="flex w-96 shadow-sm rounded-md">
                    <input type="text" placeholder="Cari no BA, no order..." class="w-full border-y border-l border-gray-300 rounded-l-md py-2 px-3 text-xs focus:outline-none">
                    <button class="bg-gray-50 border-y border-r border-gray-300 rounded-r-md px-3 text-gray-400">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                </div>
                
                <a href="?page=penghapusan&action=form_ba" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md flex items-center gap-2 text-xs font-bold shadow-sm no-underline">
                    <i class="fa-solid fa-file-signature"></i> Buat BA Mutasi
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-gray-700 border-collapse">
                    <thead class="border-y bg-gray-50 text-gray-500 uppercase font-bold">
                        <tr>
                            <th class="py-3 px-3 w-10">#</th>
                            <th class="py-3 px-3">NO BERITA ACARA</th>
                            <th class="py-3 px-3">TGL MUTASI</th>
                            <th class="py-3 px-3">NO ORDER / ASET</th>
                            <th class="py-3 px-3">CATATAN HASIL MUTASI</th>
                            <th class="py-3 px-3 text-center">STATUS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr class="hover:bg-gray-50">
                            <td class="py-3.5 px-3 text-gray-400">1</td>
                            <td class="py-3.5 px-3 font-bold text-gray-900">BAM-DEL-001</td>
                            <td class="py-3.5 px-3">2026-11-03</td>
                            <td class="py-3.5 px-3">
                                <div class="font-bold text-blue-600">ORD-DEL-001</div>
                                <div class="text-[11px] text-gray-500">Modem Maestro 100 Ext</div>
                            </td>
                            <td class="py-3.5 px-3 text-gray-500 italic">Perangkat berhasil ditarik dari lokasi pelanggan.</td>
                            <td class="py-3.5 px-3 text-center">
                                <span class="bg-blue-100 text-blue-700 px-2.5 py-1 rounded-full text-[10px] font-bold border border-blue-200">TERPROSES</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <!-- MONITORING: TERBUKA UNTUK SEMUA ROLE -->
            <h2 class="text-lg font-bold text-gray-800 mb-1">Monitoring <?php echo ($sub === 'monitor_order') ? 'Order Penghapusan' : 'Mutasi Penghapusan'; ?></h2>
            <p class="text-gray-500 text-xs mb-6">Pantau status rekapitulasi data penghapusan aset secara real-time.</p>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-gray-700 border-collapse">
                    <thead class="border-y bg-gray-50 text-gray-500 uppercase font-bold">
                        <tr>
                            <th class="py-3 px-3">#</th>
                            <th class="py-3 px-3">NO REF / ID</th>
                            <th class="py-3 px-3">KETERANGAN ASET</th>
                            <th class="py-3 px-3 text-center">STATUS MONITORING</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($data_pk as $idx => $row): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3.5 px-3 text-gray-400"><?php echo $idx + 1; ?></td>
                            <td class="py-3.5 px-3 font-bold text-gray-900"><?php echo htmlspecialchars($row['no_order'] !== '-' ? $row['no_order'] : $row['no_pk']); ?></td>
                            <td class="py-3.5 px-3 text-gray-700"><?php echo htmlspecialchars($row['alasan']); ?></td>
                            <td class="py-3.5 px-3 text-center">
                                <span class="bg-green-100 text-green-700 px-2.5 py-1 rounded-full text-[10px] font-bold border border-green-200">
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
        <div class="mt-5 text-xs text-gray-500 border-t pt-4 flex justify-between items-center">
            <div>Menampilkan <span class="font-bold text-gray-800">1 - <?php echo count($data_pk); ?></span> dari <span class="font-bold text-gray-800"><?php echo count($data_pk); ?></span> data</div>
        </div>

    </div>
</div>