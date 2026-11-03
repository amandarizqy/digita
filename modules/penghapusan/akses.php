<?php
// File: modules/penghapusan/akses.php

if (!function_exists('pga_penuh')) {
    /** Role yang memiliki akses penuh administrator */
    function pga_penuh(): array
    {
        return ['SA.KP'];
    }
}

if (!function_exists('pg_menu_penghapusan')) {
    /** Matriks Submenu Modul Penghapusan */
    function pg_menu_penghapusan(): array
    {
        return [
            'eksekusi' => [
                'label' => 'Eksekusi',
                'items' => [
                    'order_penghapusan'  => 'Order Penghapusan',
                    'mutasi_penghapusan' => 'Mutasi Penghapusan'
                ]
            ],
            'monitoring' => [
                'label' => 'Monitoring',
                'items' => [
                    'monitor_order'  => 'Monitoring Order Penghapusan',
                    'monitor_mutasi' => 'Monitoring Mutasi Penghapusan'
                ]
            ]
        ];
    }
}

if (!function_exists('pga_matriks_penghapusan')) {
    /** 
     * Matriks Hak Akses Modul Penghapusan:
     * - Eksekusi (order_penghapusan, mutasi_penghapusan, form_pk, form_order, form_ba): Hanya SF.AP, SF.UP, SA.KP
     * - Monitoring (monitor_order, monitor_mutasi): Semua Role
     */
    function pga_matriks_penghapusan(): array
    {
        $semua_role    = ['MB.UI', 'AM.UI', 'AM.AP', 'ML.UP', 'TL.AP', 'TL.UP', 'SF.UI', 'SF.AP', 'SF.UP', 'SF.VD', 'SA.KP'];
        $eksekusi_role = ['SF.AP', 'SF.UP', 'SA.KP'];

        return [
            // EKSEKUSI (Khusus SF.AP dan SF.UP)
            'order_penghapusan'  => $eksekusi_role,
            'mutasi_penghapusan' => $eksekusi_role,
            'form_pk'            => $eksekusi_role,
            'form_order'         => $eksekusi_role,
            'form_ba'            => $eksekusi_role,

            // MONITORING (Bisa diakses oleh semua role)
            'monitor_order'      => $semua_role,
            'monitor_mutasi'     => $semua_role,
            'monitoring'         => $semua_role
        ];
    }
}

if (!function_exists('pga_boleh_hapus')) {
    /**
     * Memeriksa apakah user berhak mengakses menu/action tertentu
     */
    function pga_boleh_hapus(string $action, ?string $kode_hak = null): bool
    {
        $kode = $kode_hak ?? (string) ($_SESSION['KodeHak'] ?? $_SESSION['role'] ?? $_SESSION['UserRole'] ?? '');
        
        // Super Admin (SA.KP) selalu boleh
        if (in_array($kode, pga_penuh(), true)) {
            return true;
        }

        $matriks = pga_matriks_penghapusan();

        if (isset($matriks[$action])) {
            return in_array($kode, $matriks[$action], true);
        }

        // Monitoring default terbuka untuk semua
        if (strpos($action, 'monitor') !== false) {
            return true;
        }

        // Action eksekusi lainnya dibatasi untuk SF.AP dan SF.UP
        return in_array($kode, ['SF.AP', 'SF.UP'], true);
    }
}

if (!function_exists('pga_pertama_hapus')) {
    /** Mencari menu default pertama yang diizinkan untuk role user saat ini */
    function pga_pertama_hapus(?string $kode_hak = null): string
    {
        if (pga_boleh_hapus('order_penghapusan', $kode_hak)) {
            return 'order_penghapusan';
        }
        return 'monitor_order'; // Fallback otomatis ke Monitoring jika bukan SF.AP/SF.UP
    }
}