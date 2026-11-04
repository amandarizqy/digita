<?php
// File: functions/penghapusan_ui.php

/**
 * Render Header Utama, Tab Utama (Eksekusi vs Monitoring), & Sub Entitas
 */
function render_header_penghapusan($active_tab = 'eksekusi', $active_sub = 'order_penghapusan') {$can_eksekusi = pga_boleh_hapus('order_penghapusan');
    ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <div class="font-sans mb-6">
        
        <!-- TOP HEADER: Tajuk Kiri & Butang Modal Kanan -->
        <div class="flex justify-between items-start mb-6">
            <div>
                <?php if ($active_tab === 'eksekusi' &&$active_sub === 'order_penghapusan'): ?>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">FPK & Order Penghapusan</h1>
                    <p class="text-gray-500 text-xs mt-1">Langkah 1: Buat FPK Penghapusan &rarr; Langkah 2: Gabungkan FPK ke dalam Surat Perintah Kerja (Order).</p>
                <?php elseif ($active_tab === 'eksekusi' &&$active_sub === 'mutasi_penghapusan'): ?>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Mutasi Penghapusan Aset</h1>
                    <p class="text-gray-500 text-xs mt-1">Langkah 3: Eksekusi pelepasan aset di lapangan dan terbitkan Berita Acara Mutasi (BAM).</p>
                <?php else: ?>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Monitoring Penghapusan Aset</h1>
                    <p class="text-gray-500 text-xs mt-1">Pantau status rekapitulasi data penghapusan aset secara real-time.</p>
                <?php endif; ?>
            </div>

            <!-- BUTANG AKSI DENGAN MODAL TRIGGER -->
            <?php if ($active_tab === 'eksekusi' &&$can_eksekusi): ?>
                <div class="flex gap-2.5">
                    <?php if ($active_sub === 'order_penghapusan'): ?>
                        <button onclick="openModalFpk()" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-xs font-semibold flex items-center gap-2 shadow-sm transition-all border-none cursor-pointer">
                            <i class="fa-solid fa-screwdriver-wrench text-xs"></i> Buat FPK
                        </button>
                        <button onclick="openModalOrder()" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-xs font-semibold flex items-center gap-2 shadow-sm transition-all border-none cursor-pointer">
                            <i class="fa-solid fa-square-plus text-xs"></i> Buat Order
                        </button>
                    <?php elseif ($active_sub === 'mutasi_penghapusan'): ?>
                        <button onclick="openModalBa()" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-xs font-semibold flex items-center gap-2 shadow-sm transition-all border-none cursor-pointer">
                            <i class="fa-solid fa-file-signature text-xs"></i> Buat BA Mutasi
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB UTAMA -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-1.5 flex gap-2 mb-6 w-full">
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

            <a href="?page=penghapusan&tab=monitoring&sub=monitor_order" 
               class="px-6 py-2.5 rounded-xl font-semibold text-sm transition-all no-underline flex items-center gap-2 <?php echo ($active_tab === 'monitoring') ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50'; ?>">
                <i class="fa-solid fa-desktop"></i> Monitoring
            </a>
        </div>

        <!-- SUB ENTITAS -->
        <div class="flex items-center gap-3 text-sm">
            <span class="font-bold text-gray-700 text-xs uppercase tracking-wider">SUB ENTITAS:</span>
            
            <?php if ($active_tab === 'eksekusi' &&$can_eksekusi): ?>
                <a href="?page=penghapusan&tab=eksekusi&sub=order_penghapusan" 
                   class="border px-4 py-1.5 rounded-full flex items-center gap-2 transition-all no-underline text-xs <?php echo ($active_sub === 'order_penghapusan') ? 'border-blue-500 text-blue-600 bg-blue-50/80 font-semibold shadow-sm' : 'border-gray-300 text-gray-600 bg-white hover:bg-gray-50 font-medium'; ?>">
                    <i class="fa-solid fa-check text-xs"></i> Order Penghapusan
                </a>
                <a href="?page=penghapusan&tab=eksekusi&sub=mutasi_penghapusan" 
                   class="border px-4 py-1.5 rounded-full flex items-center gap-2 transition-all no-underline text-xs <?php echo ($active_sub === 'mutasi_penghapusan') ? 'border-blue-500 text-blue-600 bg-blue-50/80 font-semibold shadow-sm' : 'border-gray-300 text-gray-600 bg-white hover:bg-gray-50 font-medium'; ?>">
                    Mutasi Penghapusan
                </a>
            <?php else: ?>
                <a href="?page=penghapusan&tab=monitoring&sub=monitor_order" 
                   class="border px-4 py-1.5 rounded-full flex items-center gap-2 transition-all no-underline text-xs <?php echo ($active_sub === 'monitor_order') ? 'border-blue-500 text-blue-600 bg-blue-50/80 font-semibold shadow-sm' : 'border-gray-300 text-gray-600 bg-white hover:bg-gray-50 font-medium'; ?>">
                    <i class="fa-solid fa-list-check text-xs"></i> Monitoring Order Penghapusan
                </a>
                <a href="?page=penghapusan&tab=monitoring&sub=monitor_mutasi" 
                   class="border px-4 py-1.5 rounded-full flex items-center gap-2 transition-all no-underline text-xs <?php echo ($active_sub === 'monitor_mutasi') ? 'border-blue-500 text-blue-600 bg-blue-50/80 font-semibold shadow-sm' : 'border-gray-300 text-gray-600 bg-white hover:bg-gray-50 font-medium'; ?>">
                    <i class="fa-solid fa-chart-line text-xs"></i> Monitoring Mutasi Penghapusan
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php
}