<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

$kode_hak = $_SESSION['KodeHak'] ?? $_SESSION['Role'] ?? '';
$unit_up  = $_SESSION['UnitUp'] ?? '';
$is_sa    = ($kode_hak === 'SA.KP'); // Bypass untuk Superadmin

$can_pengujian     = ($kode_hak === 'SF.UP' || $kode_hak === 'SA.KP');
$can_order_pasang  = ($kode_hak === 'TL.UP' || $kode_hak === 'SA.KP');
$can_mutasi_pasang = ($kode_hak === 'SF.UP' || $kode_hak === 'SA.KP');
$can_monitoring    = in_array($kode_hak, ['MB.UI', 'AM.UI', 'AM.AP', 'ML.UP', 'TL.AP', 'TL.UP', 'SF.UI', 'SF.AP', 'SF.UP', 'SF.VD', 'SA.KP']);

$can_pra_eksekusi = $can_pengujian;
$can_eksekusi     = ($can_order_pasang || $can_mutasi_pasang);

if (!$can_pra_eksekusi && !$can_eksekusi && !$can_monitoring) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script><body class='bg-light'><script>Swal.fire({icon: 'error', title: 'Akses ditolak', confirmButtonColor: '#0d6efd'}).then(() => { window.history.back(); });</script></body>";
    exit;
}

$menu = $_GET['menu'] ?? '';
$sub  = $_GET['sub'] ?? '';
$view = $_GET['view'] ?? 'daftar';

if (empty($menu)) {
    if ($can_pra_eksekusi) {
        $menu = 'pra_eksekusi';
        $sub  = 'pengujian';
    } elseif ($can_eksekusi) {
        $menu = 'eksekusi';
        $sub  = $can_order_pasang ? 'order_pasang' : 'mutasi_pasang';
    } else {
        $menu = 'monitoring';
        $sub  = 'status_pengujian';
    }
    header("Location: index.php?page=pemasangan&menu={$menu}&sub={$sub}&view=daftar");
    exit;
}

// 1. MODUL PENGUJIAN (DENGAN BYPASS SA.KP)
if ($menu === 'pra_eksekusi' && $sub === 'pengujian') {
    $filter_up = "";
    if (!$is_sa) { // Hanya filter unit jika BUKAN Superadmin
        if (!empty($_SESSION['UnitUp'])) {
            $filter_up = " AND UnitUp = '" . $_SESSION['UnitUp'] . "'";
        } elseif (!empty($_SESSION['UnitAp'])) {
            $filter_up = " AND UnitAp = '" . $_SESSION['UnitAp'] . "'"; 
        } elseif (!empty($_SESSION['UnitUpi'])) {
            $filter_up = " AND UnitUpi = '" . $_SESSION['UnitUpi'] . "'"; 
        }
    }
    $filter_kode_up = str_replace('Unit', 'Kode', $filter_up);

    if ($view === 'daftar') {
        $q = trim($_GET['q'] ?? '');
        $sql = "SELECT f.NoFormulir, f.TglUji, f.NamaAkun, f.StatusData, f.WaktuData,
                       (SELECT COUNT(NoRef) FROM formulir_pengujian_detil d WHERE d.NoFormulir = f.NoFormulir) as TotalAlat
                FROM formulir_pengujian f
                WHERE (f.NoFormulir LIKE :q) $filter_up
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

        // Filter list alat & sim agar sesuai hak akses unit (atau tampil semua untuk SA.KP)
        $stmt_alat = $conn->prepare("SELECT NoRef FROM master_barang WHERE StatusData = 'AKTIF' AND (IdPel IS NULL OR IdPel = '') $filter_up ORDER BY NoRef ASC");
        $stmt_alat->execute();
        $list_alat = $stmt_alat->fetchAll(PDO::FETCH_ASSOC);

        $stmt_sim = $conn->prepare("SELECT SimId, NomorAkun FROM master_nomor WHERE StatusData = 'AKTIF' $filter_kode_up ORDER BY SimId ASC");
        $stmt_sim->execute();
        $list_sim = $stmt_sim->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt_modem = $conn->prepare("SELECT m.ImeiModem, m.SimId, n.NomorAkun FROM master_modem m LEFT JOIN master_nomor n ON m.SimId = n.SimId WHERE m.StatusData = 'AKTIF' $filter_kode_up ORDER BY m.ImeiModem ASC");
        $stmt_modem->execute();
        $list_modem = $stmt_modem->fetchAll(PDO::FETCH_ASSOC);
    }
}

$page_title = "Pemasangan - Digitas";
require_once __DIR__ . '/../../templates/pemasangan/index.php';
?>