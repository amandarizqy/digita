<?php
// File: modules/penghapusan/form_order.php
$current_action = $_GET['action'] ?? 'form_order';
?>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<div class="max-w-4xl mx-auto bg-white shadow-md border border-gray-200 rounded-lg p-6 my-4 font-sans">
    
    <!-- Render Navigasi Sub Entitas -->
    <?php 
    if (function_exists('render_sub_entitas_penghapusan')) {
        render_sub_entitas_penghapusan($current_action); 
    }
    ?>

    <!-- Header Formulir -->
    <div class="flex justify-between items-center pb-4 border-b border-gray-300 mb-6">
        <div>
            <h1 class="font-bold text-gray-900 text-lg leading-tight">PT PLN (PERSERO) UID BANTEN</h1>
            <h2 class="font-bold text-red-600 text-sm leading-tight">SURAT ORDER PENGHAPUSAN ASET</h2>
        </div>
        <div>
            <span class="border border-gray-400 px-3 py-1 font-mono text-sm font-bold bg-gray-50 text-gray-700 rounded shadow-sm">ORD-DEL-FM</span>
        </div>
    </div>

    <!-- Form Pengisian -->
    <form action="functions/proses_penghapusan.php?action=buat_order" method="POST">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 text-sm mb-6">
            
            <div>
                <label class="block font-semibold text-gray-700 mb-1.5">Pilih Perintah Kerja (PK)</label>
                <select name="id_pk" class="w-full border border-gray-300 rounded-md p-2.5 bg-white focus:outline-none focus:ring-1 focus:ring-blue-500" required>
                    <option value="">-- Pilih Nomor PK --</option>
                    <option value="1">PK-2026-DEL-001 (Modem Maestro 100 Ext)</option>
                    <option value="2">PK-2026-DEL-002 (Router Mikrotik RB750)</option>
                </select>
            </div>

            <div>
                <label class="block font-semibold text-gray-700 mb-1.5">Nomor Order Penghapusan</label>
                <input type="text" name="no_order" placeholder="Contoh: ORD-DEL-001" class="w-full border border-gray-300 rounded-md p-2.5 focus:outline-none focus:ring-1 focus:ring-blue-500" required>
            </div>

            <div>
                <label class="block font-semibold text-gray-700 mb-1.5">Tanggal Order</label>
                <input type="date" name="tanggal_order" value="<?php echo date('Y-m-d'); ?>" class="w-full border border-gray-300 rounded-md p-2.5 focus:outline-none focus:ring-1 focus:ring-blue-500" required>
            </div>

            <div>
                <label class="block font-semibold text-gray-700 mb-1.5">Petugas / Pelaksana</label>
                <input type="text" name="petugas" placeholder="Contoh: IPM.001" class="w-full border border-gray-300 rounded-md p-2.5 focus:outline-none focus:ring-1 focus:ring-blue-500" required>
            </div>

            <div class="md:col-span-2">
                <label class="block font-semibold text-gray-700 mb-1.5">Nama Aset / Perangkat Dihapus</label>
                <input type="text" name="aset_dihapus" placeholder="Contoh: Modem Maestro 100 Ext" class="w-full border border-gray-300 rounded-md p-2.5 focus:outline-none focus:ring-1 focus:ring-blue-500" required>
            </div>

            <div class="md:col-span-2">
                <label class="block font-semibold text-gray-700 mb-1.5">Catatan / Detail Alasan Penghapusan</label>
                <textarea name="alasan" rows="3" class="w-full border border-gray-300 rounded-md p-2.5 focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Ketik rincian alasan penghapusan aset..." required></textarea>
            </div>
            
        </div>

        <!-- Tombol Aksi -->
        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
            <a href="?page=penghapusan&action=monitor_pk" class="px-5 py-2 border border-gray-300 rounded-md text-gray-700 font-medium hover:bg-gray-50 text-sm no-underline transition-colors">Batal</a>
            <button type="submit" class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white font-bold rounded-md shadow-sm text-sm border-none cursor-pointer transition-colors">Terbitkan Order</button>
        </div>
        
    </form>
</div>