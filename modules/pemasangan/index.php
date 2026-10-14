<?php
require_once __DIR__ . '/../../config/database.php';

$kode_hak = $_SESSION['KodeHak'] ?? '';

// Definisi Hak Akses Level 3 (Berdasarkan matriks rancangan.xlsx)
$can_pengujian     = in_array($kode_hak, ['SF.AP', 'SF.UP', 'SA.KP']);
$can_order_pasang  = in_array($kode_hak, ['TL.AP', 'TL.UP', 'SA.KP']);
$can_mutasi_pasang = in_array($kode_hak, ['SF.AP', 'SF.UP', 'SA.KP']);
$can_monitoring    = in_array($kode_hak, ['MB.UI', 'AM.UI', 'AM.AP', 'ML.UP', 'TL.AP', 'TL.UP', 'SF.UI', 'SF.AP', 'SF.UP', 'SA.KP']);

// Hak Akses Level 2 (Terbuka jika minimal punya 1 akses di Level 3-nya)
$can_pra_eksekusi = $can_pengujian;
$can_eksekusi     = ($can_order_pasang || $can_mutasi_pasang);

// Jika user sama sekali tidak punya akses ke modul pemasangan
if (!$can_pra_eksekusi && !$can_eksekusi && !$can_monitoring) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
          <body class='bg-light'><script>
            Swal.fire({icon: 'error', title: 'Akses ditolak', text: 'Anda tidak memiliki wewenang untuk membuka modul ini.', confirmButtonColor: '#0d6efd', allowOutsideClick: false}).then(() => { window.history.back(); });
          </script></body>";
    exit;
}

// Redirect otomatis berdasarkan role tertinggi/prioritas jika hanya mengakses ?page=pemasangan
if (!isset($_GET['menu'])) {
    if ($can_pra_eksekusi) {
        header("Location: index.php?page=pemasangan&menu=pra_eksekusi&sub=pengujian&view=daftar");
    } elseif ($can_eksekusi) {
        $def_sub = $can_order_pasang ? 'order_pasang' : 'mutasi_pasang';
        header("Location: index.php?page=pemasangan&menu=eksekusi&sub=$def_sub&view=daftar");
    } elseif ($can_monitoring) {
        header("Location: index.php?page=pemasangan&menu=monitoring&sub=status_pengujian");
    }
    exit;
}

$menu = $_GET['menu'] ?? 'pra_eksekusi'; 
$sub  = $_GET['sub'] ?? '';
$view = $_GET['view'] ?? 'daftar'; 

// Penetapan sub default jika kosong berdasarkan URL menu
if ($menu === 'eksekusi' && $sub === '') {
    $sub = $can_order_pasang ? 'order_pasang' : 'mutasi_pasang';
} elseif ($menu === 'pra_eksekusi' && $sub === '') {
    $sub = 'pengujian';
} elseif ($menu === 'monitoring' && $sub === '') {
    $sub = 'status_pengujian';
}

// PENGAMANAN AKSES BACKEND (Mencegah bypass URL manual)
if ($menu === 'pra_eksekusi' && !$can_pra_eksekusi) {
    header("Location: index.php?page=pemasangan&menu=monitoring&sub=status_pengujian"); exit;
}
if ($menu === 'eksekusi' && !$can_eksekusi) {
    header("Location: index.php?page=pemasangan&menu=monitoring&sub=status_pengujian"); exit;
}
if ($menu === 'eksekusi' && $sub === 'order_pasang' && !$can_order_pasang) {
    header("Location: index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang&view=daftar"); exit;
}
if ($menu === 'eksekusi' && $sub === 'mutasi_pasang' && !$can_mutasi_pasang) {
    header("Location: index.php?page=pemasangan&menu=eksekusi&sub=order_pasang&view=daftar"); exit;
}
if ($menu === 'monitoring' && !$can_monitoring) {
    header("Location: index.php"); exit;
}

$page_title = "Pemasangan S41 - Digita";

// --- AMBIL DATA UNTUK FORM PENGUJIAN ---
if ($menu === 'pra_eksekusi' && $sub === 'pengujian') {
    if ($view === 'baru') {
        $unit_up_login = $_SESSION['UnitUp'] ?? '';
        
        // 1. Ambil Alat (NoRef) yang ada di UP petugas, berstatus AKTIF, dan belum terpasang (IdPel NULL)
        $stmt_alat = $conn->prepare("
            SELECT NoRef 
            FROM master_barang 
            WHERE UnitUp = :up AND StatusData = 'AKTIF' AND (IdPel IS NULL OR IdPel = '')
        ");
        $stmt_alat->execute([':up' => $unit_up_login]);
        $list_alat = $stmt_alat->fetchAll(PDO::FETCH_ASSOC);

        // 2. Ambil SIM Card yang ada di UP petugas dan berstatus AKTIF
        $stmt_sim = $conn->prepare("
            SELECT SimId, NomorAkun 
            FROM master_nomor 
            WHERE KodeUp = :up AND StatusData = 'AKTIF'
        ");
        $stmt_sim->execute([':up' => $unit_up_login]);
        $list_sim = $stmt_sim->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Tampilkan Template View
require_once __DIR__ . '/../../templates/pemasangan/index.php';
?>