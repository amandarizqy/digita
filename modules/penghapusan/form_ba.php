<?php
// File: modules/penghapusan/form_ba.php
$current_action = $_GET['action'] ?? 'form_ba';
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
            <h2 class="font-bold text-green-600 text-sm leading-tight">BERITA ACARA MUTASI PENGHAPUSAN</h2>
        </div>
        <div>
            <span class="border border-gray-400 px-3 py-1 font-mono text-sm font-bold bg-gray-50 text-gray-700 rounded shadow-sm">BAM-DEL-FM</span>
        </div>
    </div>

    <!-- Form Pengisian -->
    <form action="functions/proses_penghapusan.php?action=buat_ba" method="POST">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 text-sm mb-6">
            <div>
                <label class="block font-semibold text-gray-700 mb-1.5">Nomor Berita Acara</label>
                <input type="text" name="no_ba" placeholder="Contoh: BAM-DEL-001" class="w-full border border-gray-300 rounded-md p-2.5 focus:outline-none focus:ring-1 focus:ring-blue-500" required>
            </div>

            <div>
                <label class="block font-semibold text-gray-700 mb-1.5">Pilih Order Penghapusan</label>
                <select name="id_order" class="w-full border border-gray-300 rounded-md p-2.5 bg-white focus:outline-none focus:ring-1 focus:ring-blue-500" required>
                    <option value="">-- Pilih Order --</option>
                    <option value="1">ORD-DEL-001 (Modem Maestro)</option>
                </select>
            </div>

            <div>
                <label class="block font-semibold text-gray-700 mb-1.5">Tanggal Pelaksanaan Mutasi</label>
                <input type="date" name="tanggal_mutasi" value="<?php echo date('Y-m-d'); ?>" class="w-full border border-gray-300 rounded-md p-2.5 focus:outline-none focus:ring-1 focus:ring-blue-500" required>
            </div>

            <div class="md:col-span-2">
                <label class="block font-semibold text-gray-700 mb-1.5">Catatan Hasil Mutasi Aset</label>
                <textarea name="catatan_mutasi" rows="3" class="w-full border border-gray-300 rounded-md p-2.5 focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Ketik catatan hasil pelepasan/pengembalian aset..."></textarea>
            </div>
        </div>

        <!-- Tombol Aksi -->
        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
            <a href="?page=penghapusan&action=monitor_pk" class="px-5 py-2 border border-gray-300 rounded-md text-gray-700 font-medium hover:bg-gray-50 text-sm no-underline transition-colors">Batal</a>
            <button type="submit" class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white font-bold rounded-md shadow-sm text-sm border-none cursor-pointer transition-colors">Simpan BA Mutasi</button>
        </div>
    </form>
</div>