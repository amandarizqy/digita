<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$root_path = $_SERVER['DOCUMENT_ROOT'];
require_once __DIR__ . '/../../config/database.php';

if (file_exists(__DIR__ . '/../../akses.php')) {
    require_once __DIR__ . '/../../akses.php';
} elseif (file_exists(__DIR__ . '/../../config/akses.php')) {
    require_once __DIR__ . '/../../config/akses.php';
}

require_once __DIR__ . '/../../functions/pemasangan_helper.php';

$kode_hak = $_SESSION['KodeHak'] ?? $_SESSION['Role'] ?? '';
$unit_up  = $_SESSION['UnitUp'] ?? '';
$is_sa    = ($kode_hak === 'SA.KP'); // Bypass untuk Superadmin

// Validasi Otoritas Berdasarkan Rancangan
$can_pengujian     = in_array($kode_hak, ['SF.UP', 'SA.KP']); // TL.UP TIDAK BOLEH
$can_order_pasang  = in_array($kode_hak, ['TL.UP', 'SA.KP']);
$can_mutasi_pasang = in_array($kode_hak, ['SF.UP', 'SA.KP']);
$can_monitoring    = in_array($kode_hak, ['MB.UI', 'AM.UI', 'AM.AP', 'ML.UP', 'TL.AP', 'TL.UP', 'SF.UI', 'SF.AP', 'SF.UP', 'SF.VD', 'SA.KP']);

$can_pra_eksekusi = $can_pengujian;
$can_eksekusi     = ($can_order_pasang || $can_mutasi_pasang);

$menu = $_GET['menu'] ?? '';
$sub  = $_GET['sub'] ?? '';
$view = $_GET['view'] ?? 'daftar';

// Penanganan Redirect & Default Menu Berdasarkan Hak Akses
if (empty($menu)) {
    if ($can_pra_eksekusi) {
        $menu = 'pra_eksekusi'; $sub = 'pengujian';
    } elseif ($can_eksekusi) {
        $menu = 'eksekusi';
        $sub  = $can_order_pasang ? 'order_pasang' : 'mutasi_pasang';
    } else {
        $menu = 'monitoring'; $sub = 'status_pengujian';
    }
    header("Location: index.php?page=pemasangan&menu={$menu}&sub={$sub}&view=daftar");
    exit;
}

// Proteksi Menu Pra Eksekusi jika role tidak memiliki izin
if ($menu === 'pra_eksekusi' && !$can_pra_eksekusi) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
          <body style='background: transparent !important;'>
          <script>
              Swal.fire({
                  icon: 'error',
                  title: 'Akses Ditolak',
                  text: 'Anda tidak memiliki wewenang untuk membuka menu Pra Eksekusi.',
                  confirmButtonText: 'OK',
                  confirmButtonColor: '#0d6efd',
                  allowOutsideClick: false,
                  background: '#fff',
                  backdrop: 'rgba(0,0,0,0.1)'
              }).then(() => { 
                  window.location.href = 'index.php?page=pemasangan&menu=eksekusi&sub=" . ($can_order_pasang ? "order_pasang" : "mutasi_pasang") . "'; 
              });
          </script></body>";
    exit;
}

// Proteksi Sub-menu Eksekusi (Order Pasang / Mutasi Pasang)
if ($menu === 'eksekusi') {
    if (empty($sub)) {
        $sub = $can_order_pasang ? 'order_pasang' : 'mutasi_pasang';
        header("Location: index.php?page=pemasangan&menu=eksekusi&sub={$sub}&view=daftar");
        exit;
    }
    if ($sub === 'order_pasang' && !$can_order_pasang) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
              <body style='background: transparent !important;'>
              <script>
                  Swal.fire({
                      icon: 'error',
                      title: 'Akses Ditolak',
                      text: 'Anda tidak memiliki wewenang untuk membuka menu Order Pasang.',
                      confirmButtonText: 'OK',
                      confirmButtonColor: '#0d6efd',
                      allowOutsideClick: false,
                      background: '#fff',
                      backdrop: 'rgba(0,0,0,0.1)'
                  }).then(() => { 
                      window.location.href = 'index.php?page=pemasangan&menu=eksekusi&sub=mutasi_pasang'; 
                  });
              </script></body>";
        exit;
    }
    if ($sub === 'mutasi_pasang' && !$can_mutasi_pasang) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
              <body style='background: transparent !important;'>
              <script>
                  Swal.fire({
                      icon: 'error',
                      title: 'Akses Ditolak',
                      text: 'Anda tidak memiliki wewenang untuk membuka menu Mutasi Pasang.',
                      confirmButtonText: 'OK',
                      confirmButtonColor: '#0d6efd',
                      allowOutsideClick: false,
                      background: '#fff',
                      backdrop: 'rgba(0,0,0,0.1)'
                  }).then(() => { 
                      window.location.href = 'index.php?page=pemasangan&menu=eksekusi&sub=order_pasang'; 
                  });
              </script></body>";
        exit;
    }
}

// 1. MODUL PENGUJIAN
if ($menu === 'pra_eksekusi' && $sub === 'pengujian') {
    $filter_up = "";
    if (!$is_sa) {
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