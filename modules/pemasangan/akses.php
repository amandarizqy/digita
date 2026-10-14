<?php
// Fungsi untuk memvalidasi hak akses sub-entitas (Level 3) pada modul Pemasangan
function validasi_akses_pemasangan(&$lvl2, &$lvl3, $user_role) {
    // Array rule akses berdasarkan rancangan.xlsx
    $access_rules = [
        // Level 2: Pra Eksekusi
        'pengujian'            => ['TL.UP', 'TL.AP', 'SF.UP', 'SF.VD', 'SA.KP'],
        
        // Level 2: Eksekusi
        'order_pasang'         => ['TL.UP', 'TL.AP', 'SA.KP'],
        'mutasi_pasang'        => ['TL.UP', 'TL.AP', 'SA.KP'],
        
        // Level 2: Monitoring
        'monitoring_pengujian' => ['AM.UI', 'AM.AP', 'MB.UI', 'SA.KP', 'TL.UP', 'TL.AP'],
        'riwayat_pengujian'    => ['AM.UI', 'AM.AP', 'MB.UI', 'SA.KP', 'TL.UP', 'TL.AP'],
        'monitoring_order'     => ['AM.UI', 'AM.AP', 'MB.UI', 'SA.KP', 'TL.UP', 'TL.AP'],
        'monitoring_mutasi'    => ['AM.UI', 'AM.AP', 'MB.UI', 'SA.KP', 'TL.UP', 'TL.AP']
    ];

    // Jika level 3 yang diakses ada di daftar rule, tapi user_role TIDAK ADA di dalamnya
    if (isset($access_rules[$lvl3]) && !in_array($user_role, $access_rules[$lvl3])) {
        // Set pesan error untuk SweetAlert2
        $_SESSION['alert_error'] = "Anda tidak memiliki hak akses ke menu tersebut.";
        
        // Alihkan ke menu default yang aman berdasarkan rolenya
        if(in_array($user_role, ['AM.UI', 'MB.UI', 'AM.AP'])) {
            $lvl2 = 'monitoring';
            $lvl3 = 'monitoring_pengujian';
        } else {
            $lvl2 = 'pra_eksekusi';
            $lvl3 = 'pengujian';
        }
    }
}
?>