<?php
require_once __DIR__ . '/../../config/database.php';

$kode_hak = $_SESSION['KodeHak'] ?? '';
$can_pengujian     = in_array($kode_hak, ['SF.AP', 'SF.UP', 'SA.KP']);
$can_order_pasang  = in_array($kode_hak, ['TL.AP', 'TL.UP', 'SA.KP']);
$can_mutasi_pasang = in_array($kode_hak, ['SF.AP', 'SF.UP', 'SA.KP']);
$can_monitoring    = in_array($kode_hak, ['MB.UI', 'AM.UI', 'AM.AP', 'ML.UP', 'TL.AP', 'TL.UP', 'SF.UI', 'SF.AP', 'SF.UP', 'SA.KP']);

$can_pra_eksekusi = $can_pengujian;
$can_eksekusi     = ($can_order_pasang || $can_mutasi_pasang);

if (!$can_pra_eksekusi && !$can_eksekusi && !$can_monitoring) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script><body class='bg-light'><script>Swal.fire({icon: 'error', title: 'Akses ditolak', confirmButtonColor: '#0d6efd'}).then(() => { window.history.back(); });</script></body>";
    exit;
}

if (!isset($_GET['menu'])) {
    if ($can_pra_eksekusi) header("Location: index.php?page=pemasangan&menu=pra_eksekusi&sub=pengujian&view=daftar");
    elseif ($can_eksekusi) header("Location: index.php?page=pemasangan&menu=eksekusi&sub=" . ($can_order_pasang ? 'order_pasang' : 'mutasi_pasang') . "&view=daftar");
    else header("Location: index.php?page=pemasangan&menu=monitoring&sub=status_pengujian");
    exit;
}

$menu = $_GET['menu'] ?? 'pra_eksekusi'; 
$sub  = $_GET['sub'] ?? 'pengujian';
$view = $_GET['view'] ?? 'daftar'; 

// --- PENGAMBILAN DATA UNTUK PENGUJIAN ---
if ($menu === 'pra_eksekusi' && $sub === 'pengujian') {
    
    // LOGIKA 3-LAYER AKSES DATA (Hierarki Aset)
    $filter_up = "";
    if (!empty($_SESSION['UnitUp'])) {
        // UP hanya bisa melihat data di UP-nya sendiri
        $filter_up = " AND UnitUp = '" . $_SESSION['UnitUp'] . "'";
    } elseif (!empty($_SESSION['UnitAp'])) {
        // AP bisa melihat data miliknya dan UP di bawahnya
        $filter_up = " AND UnitAp = '" . $_SESSION['UnitAp'] . "'"; 
    } elseif (!empty($_SESSION['UnitUpi'])) {
        // UPI bisa melihat seluruh data AP & UP di bawahnya
        $filter_up = " AND UnitUpi = '" . $_SESSION['UnitUpi'] . "'"; 
    }
    
    // Penyesuaian nama field (karena tabel nomor dan modem menggunakan awalan Kode)
    $filter_kode_up = str_replace('Unit', 'Kode', $filter_up);

    if ($view === 'daftar') {
        $q = trim($_GET['q'] ?? '');
        $sql = "SELECT f.NoFormulir, f.TglUji, f.NamaAkun, f.StatusData, f.WaktuData,
                       (SELECT COUNT(NoRef) FROM formulir_pengujian_detil d WHERE d.NoFormulir = f.NoFormulir) as TotalAlat
                FROM formulir_pengujian f
                WHERE (f.NoFormulir LIKE :q)
                ORDER BY f.WaktuData DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':q' => "%$q%"]);
        $list_pengujian = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($view === 'baru') {
        header("Location: modules/pemasangan/proses_pengujian.php?action=create_draft");
        exit;
        
    } elseif ($view === 'detail') {
        $no_form = $_GET['no_form'] ?? '';
        
        $stmt_head = $conn->prepare("SELECT * FROM formulir_pengujian WHERE NoFormulir = ?");
        $stmt_head->execute([$no_form]);
        $formulir = $stmt_head->fetch(PDO::FETCH_ASSOC);
        
        if (!$formulir) die("Formulir tidak ditemukan.");

        $stmt_detil = $conn->prepare("SELECT * FROM formulir_pengujian_detil WHERE NoFormulir = ? ORDER BY NoRef ASC");
        $stmt_detil->execute([$no_form]);
        $items = $stmt_detil->fetchAll(PDO::FETCH_ASSOC);

        // Ambil Data Sesuai Hierarki 3-Layer (Alat yang belum dipasang ke Pelanggan)
        $stmt_alat = $conn->prepare("SELECT NoRef FROM master_barang WHERE StatusData = 'AKTIF' AND (IdPel IS NULL OR IdPel = '') $filter_up ORDER BY NoRef ASC");
        $stmt_alat->execute();
        $list_alat = $stmt_alat->fetchAll(PDO::FETCH_ASSOC);

        $stmt_sim = $conn->prepare("SELECT SimId, NomorAkun FROM master_nomor WHERE StatusData = 'AKTIF' $filter_kode_up ORDER BY SimId ASC");
        $stmt_sim->execute();
        $list_sim = $stmt_sim->fetchAll(PDO::FETCH_ASSOC);
        
        // Ambil Modem + JOIN untuk mendapatkan Nomor Akun (Nomor Admin)
        $stmt_modem = $conn->prepare("SELECT m.ImeiModem, m.SimId, n.NomorAkun FROM master_modem m LEFT JOIN master_nomor n ON m.SimId = n.SimId WHERE m.StatusData = 'AKTIF' $filter_kode_up ORDER BY m.ImeiModem ASC");
        $stmt_modem->execute();
        $list_modem = $stmt_modem->fetchAll(PDO::FETCH_ASSOC);
    }
}

$page_title = "Pemasangan S41 - Digita";
require_once __DIR__ . '/../../templates/pemasangan/index.php';
?>