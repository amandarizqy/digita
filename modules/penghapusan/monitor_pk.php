<?php
// File: modules/penghapusan/monitor_pk.php
$current_action = $_GET['action'] ?? 'monitor_pk';
?>

<!-- Pastikan Tailwind & FontAwesome dimuat untuk halaman ini -->
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<div class="p-6 max-w-7xl mx-auto w-full font-sans">
    
    <!-- Header Judul -->
    <h1 class="text-2xl font-bold mb-1 text-gray-900">Modul Penghapusan Aset</h1>
    <p class="text-gray-500 mb-6 text-sm">Kelola Perintah Kerja, Order Penghapusan, dan Berita Acara Mutasi (BAM).</p>

    <!-- Render Tab Navigasi Sub Entitas -->
    <?php 
    if (function_exists('render_sub_entitas_penghapusan')) {
        render_sub_entitas_penghapusan($current_action); 
    }
    ?>

    <!-- Card Form & Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        
        <!-- Action Bar: Input Search & Buttons -->
        <div class="flex justify-between items-center mb-6">
            
            <!-- Search Bar Menyatu -->
            <div class="flex w-96 shadow-sm rounded-md">
                <input type="text" placeholder="Cari no order, nama aset..." class="w-full border-y border-l border-gray-300 rounded-l-md py-2 px-3 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                <button class="bg-gray-50 border-y border-r border-gray-300 rounded-r-md px-4 text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex gap-2 text-sm font-bold">
                <a href="?page=penghapusan&action=form_pk" class="bg-yellow-400 hover:bg-yellow-500 text-gray-900 px-4 py-2 rounded-md flex items-center gap-2 shadow-sm transition-colors no-underline">
                    <i class="fa-solid fa-file-contract"></i> 1. Buat PK
                </a>
                <a href="?page=penghapusan&action=form_order" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md flex items-center gap-2 shadow-sm transition-colors no-underline">
                    <i class="fa-solid fa-trash-can-arrow-up"></i> 2. Order Hapus
                </a>
                <a href="?page=penghapusan&action=form_ba" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md flex items-center gap-2 shadow-sm transition-colors no-underline">
                    <i class="fa-solid fa-file-signature"></i> 3. Buat BA Mutasi
                </a>
            </div>
        </div>

        <!-- Data Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-700 border-collapse">
                <thead class="border-y bg-gray-50 text-xs text-gray-500 uppercase font-bold">
                    <tr>
                        <th class="py-3 px-4 w-12 text-center">#</th>
                        <th class="py-3 px-4">NO PK / ORDER</th>
                        <th class="py-3 px-4">ASET / ITEM</th>
                        <th class="py-3 px-4 w-2/5">ALASAN PENGHAPUSAN</th>
                        <th class="py-3 px-4 text-center">STATUS</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="py-4 px-4 text-center text-gray-400 font-medium">1</td>
                        <td class="py-4 px-4">
                            <div class="font-bold text-gray-900 text-[13px]">PK-2026-DEL-001</div>
                            <div class="text-xs text-blue-600 font-semibold mt-0.5">ORD-DEL-001</div>
                        </td>
                        <td class="py-4 px-4 font-bold text-gray-900 text-[13px]">Modem Maestro 100 Ext</td>
                        <td class="py-4 px-4 text-gray-500 italic text-[13px]">Perangkat rusak total (Mati Total).</td>
                        <td class="py-4 px-4 text-center">
                            <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded text-xs font-bold border border-yellow-200 inline-block">PENDING</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Footer / Pagination -->
        <div class="mt-5 text-sm text-gray-500 border-t pt-4 flex justify-between items-center">
            <div>Menampilkan <span class="font-bold text-gray-800">1 - 1</span> dari <span class="font-bold text-gray-800">1</span> data</div>
        </div>

    </div>
</div>