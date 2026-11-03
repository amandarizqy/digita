<?php
// File: functions/penghapusan_ui.php

/**
 * Render Navigasi Tab Utama (Eksekusi vs Monitoring) & Sub Entitas
 */
function render_header_penghapusan($active_tab = 'eksekusi', $active_sub = 'order_penghapusan') {
    $can_eksekusi = pga_boleh_hapus('order_penghapusan');
    ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <div class="font-sans mb-6">
        <h1 class="text-2xl font-bold mb-1 text-gray-900">Modul Penghapusan Aset</h1>
        <p class="text-gray-500 mb-6 text-sm">Kelola PK Penghapusan, Order Penghapusan, Eksekusi Lapangan (Mutasi), dan Monitoring Aset.</p>

        <!-- Tab Utama (Eksekusi / Monitoring) Memanjang W-Full -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-2.5 flex gap-2 mb-6 w-full">
            
            <!-- Tab Eksekusi (Dengan Proteksi Visual) -->
            <?php if ($can_eksekusi): ?>
                <a href="?page=penghapusan&tab=eksekusi&sub=order_penghapusan" 
                   class="px-6 py-2.5 rounded-xl font-semibold text-sm transition-all no-underline flex items-center gap-2 <?php echo ($active_tab === 'eksekusi') ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50'; ?>">
                    <i class="fa-solid fa-screwdriver-wrench"></i> Eksekusi
                </a>
            <?php else: ?>
                <span title="Hanya dapat diakses oleh SF.AP dan SF.UP" 
                      class="px-6 py-2.5 rounded-xl font-semibold text-sm text-gray-400 bg-gray-100 opacity-60 cursor-not-allowed flex items-center gap-2">
                    <i class="fa-solid fa-lock text-xs"></i> Eksekusi
                </span>
            <?php endif; ?>

            <!-- Tab Monitoring (Semua Role Boleh) -->
            <a href="?page=penghapusan&tab=monitoring&sub=monitor_order" 
               class="px-6 py-2.5 rounded-xl font-semibold text-sm transition-all no-underline flex items-center gap-2 <?php echo ($active_tab === 'monitoring') ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50'; ?>">
                <i class="fa-solid fa-desktop"></i> Monitoring
            </a>
        </div>

        <!-- Sub Entitas Navigasi -->
        <div class="flex items-center gap-3 text-sm">
            <span class="font-bold text-gray-700 text-xs uppercase tracking-wider">SUB ENTITAS:</span>
            
            <?php if ($active_tab === 'eksekusi' && $can_eksekusi): ?>
                <a href="?page=penghapusan&tab=eksekusi&sub=order_penghapusan" 
                   class="border px-4 py-1.5 rounded-full flex items-center gap-2 transition-all no-underline text-xs <?php echo ($active_sub === 'order_penghapusan') ? 'border-blue-500 text-blue-600 bg-blue-50/80 font-bold shadow-sm' : 'border-gray-300 text-gray-600 bg-white hover:bg-gray-50 font-medium'; ?>">
                    <i class="fa-solid fa-check"></i> Order Penghapusan
                </a>
                <a href="?page=penghapusan&tab=eksekusi&sub=mutasi_penghapusan" 
                   class="border px-4 py-1.5 rounded-full flex items-center gap-2 transition-all no-underline text-xs <?php echo ($active_sub === 'mutasi_penghapusan') ? 'border-blue-500 text-blue-600 bg-blue-50/80 font-bold shadow-sm' : 'border-gray-300 text-gray-600 bg-white hover:bg-gray-50 font-medium'; ?>">
                    <i class="fa-solid fa-arrow-right-arrow-left"></i> Mutasi Penghapusan
                </a>
            <?php else: ?>
                <a href="?page=penghapusan&tab=monitoring&sub=monitor_order" 
                   class="border px-4 py-1.5 rounded-full flex items-center gap-2 transition-all no-underline text-xs <?php echo ($active_sub === 'monitor_order') ? 'border-blue-500 text-blue-600 bg-blue-50/80 font-bold shadow-sm' : 'border-gray-300 text-gray-600 bg-white hover:bg-gray-50 font-medium'; ?>">
                    <i class="fa-solid fa-list-check"></i> Monitoring Order Penghapusan
                </a>
                <a href="?page=penghapusan&tab=monitoring&sub=monitor_mutasi" 
                   class="border px-4 py-1.5 rounded-full flex items-center gap-2 transition-all no-underline text-xs <?php echo ($active_sub === 'monitor_mutasi') ? 'border-blue-500 text-blue-600 bg-blue-50/80 font-bold shadow-sm' : 'border-gray-300 text-gray-600 bg-white hover:bg-gray-50 font-medium'; ?>">
                    <i class="fa-solid fa-chart-line"></i> Monitoring Mutasi Penghapusan
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php
}