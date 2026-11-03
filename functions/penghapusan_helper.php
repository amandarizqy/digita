<?php
// File: functions/penghapusan_ui.php

function render_sub_entitas_penghapusan($current_action) {
    $menus = [
        'monitor_pk'  => ['label' => 'Order Penghapusan', 'icon' => 'fa-solid fa-check'],
        'form_pk'     => ['label' => 'Buat PK', 'icon' => 'fa-solid fa-file-contract'],
        'form_order'  => ['label' => 'Order Hapus', 'icon' => 'fa-solid fa-trash-can-arrow-up'],
        'form_ba'     => ['label' => 'BA Mutasi', 'icon' => 'fa-solid fa-file-signature']
    ];

    echo '<div class="flex items-center gap-3 mb-6 overflow-x-auto pb-2 text-sm font-sans">';
    echo '<span class="font-bold text-gray-700 text-xs uppercase tracking-wider">SUB ENTITAS:</span>';
    
    foreach ($menus as $act => $m) {
        $active = ($current_action === $act);
        $btn_class = $active 
            ? 'border-blue-500 text-blue-600 bg-blue-50 font-semibold shadow-sm' 
            : 'border-gray-300 text-gray-600 bg-white hover:bg-gray-50 font-medium';
            
        echo '<a href="?page=penghapusan&action=' . $act . '" class="border px-4 py-1.5 rounded-full flex items-center gap-2 transition-all duration-150 no-underline ' . $btn_class . '">';
        echo '<i class="' . $m['icon'] . ' text-xs"></i> ' . $m['label'];
        echo '</a>';
    }
    echo '</div>';
} 