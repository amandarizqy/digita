<?php
require_once __DIR__ . '/../../config/database.php';

$kode_hak = $_SESSION['KodeHak'] ?? '';

// PERUBAHAN HAK AKSES SESUAI MATRIKS BARU
$can_pengujian     = ($kode_hak === 'SF.UP'); // Hanya SF.UP
$can_order_pasang  = ($kode_hak === 'TL.UP'); // Hanya TL.UP
$can_mutasi_pasang = ($kode_hak === 'SF.UP'); // Hanya SF.UP
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
$unit_up = $_SESSION['UnitUp'] ?? '';

// =================================================================
// 1. MODUL PENGUJIAN (Dipertahankan 100% dari versi sebelumnya)
// =================================================================
if ($menu === 'pra_eksekusi' && $sub === 'pengujian') {
    
    // LOGIKA 3-LAYER AKSES DATA (Hierarki Aset)
    $filter_up = "";
    if (!empty($_SESSION['UnitUp'])) {
        $filter_up = " AND UnitUp = '" . $_SESSION['UnitUp'] . "'";
    } elseif (!empty($_SESSION['UnitAp'])) {
        $filter_up = " AND UnitAp = '" . $_SESSION['UnitAp'] . "'"; 
    } elseif (!empty($_SESSION['UnitUpi'])) {
        $filter_up = " AND UnitUpi = '" . $_SESSION['UnitUpi'] . "'"; 
    }
    
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

        // Ambil Data Sesuai Hierarki 3-Layer 
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

// =================================================================
// 2. MODUL ORDER PASANG (FPK & SPK oleh TL.UP)
// =================================================================
if ($menu === 'eksekusi' && $sub === 'order_pasang' && $can_order_pasang) {
    
    if ($view === 'daftar') {
        $q = trim($_GET['q'] ?? '');
        
        // A. Ambil Daftar SPK (Order)
        $sql_order = "SELECT o.NoFormOrder, o.TglOrder, o.NamaAkun, o.StatusData, o.WaktuData,
                       (SELECT COUNT(NoFpk) FROM frm_order_detil d WHERE d.NoFormOrder = o.NoFormOrder) as TotalTarget
                FROM frm_order o
                WHERE o.UnitUp = :up AND (o.NoFormOrder LIKE :q)
                ORDER BY o.WaktuData DESC";
        $stmt_order = $conn->prepare($sql_order);
        $stmt_order->execute([':up' => $unit_up, ':q' => "%$q%"]);
        $list_order = $stmt_order->fetchAll(PDO::FETCH_ASSOC);

        // B. Ambil Daftar FPK (Beserta status apakah sudah masuk Order/SPK)
        $sql_fpk = "SELECT f.*, 
                           (SELECT NoFormOrder FROM frm_order_detil d WHERE d.NoFpk = f.NoFpk LIMIT 1) as TerikatOrder
                    FROM frm_pk f 
                    WHERE f.UnitUp = :up AND (f.NoFpk LIKE :q OR f.IdPel LIKE :q)
                    ORDER BY f.WaktuData DESC";
        $stmt_fpk = $conn->prepare($sql_fpk);
        $stmt_fpk->execute([':up' => $unit_up, ':q' => "%$q%"]);
        $list_fpk = $stmt_fpk->fetchAll(PDO::FETCH_ASSOC);

        // C. Ambil Data Pelanggan untuk Dropdown Modal FPK (Belum dipasang & Belum di-FPK-kan)
        $stmt_pelanggan = $conn->prepare("
            SELECT IdPel, Peringkat, TotalTagihan, SkalaPrioritas FROM hasil_prioritas 
            WHERE UnitUp = ? 
              AND IdPel NOT IN (SELECT IdPel FROM master_barang WHERE IdPel IS NOT NULL AND IdPel != '')
              AND IdPel NOT IN (SELECT IdPel FROM frm_pk)
            ORDER BY SkalaPrioritas DESC, Peringkat ASC
        ");
        $stmt_pelanggan->execute([$unit_up]);
        $list_pelanggan = $stmt_pelanggan->fetchAll(PDO::FETCH_ASSOC);

        // D. Ambil Data Alat untuk Dropdown Modal FPK (Sudah Uji & Belum di-FPK-kan)
        $stmt_alat = $conn->prepare("
            SELECT NoRef, ImeiModem, SimId FROM master_barang 
            WHERE UnitUp = ? AND StatusData = 'AKTIF' AND SimId IS NOT NULL AND SimId != '' 
              AND (IdPel IS NULL OR IdPel = '')
              AND NoRef NOT IN (SELECT NomorBarang FROM frm_pk)
            ORDER BY NoRef ASC
        ");
        $stmt_alat->execute([$unit_up]);
        $list_alat = $stmt_alat->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($view === 'baru') {
        header("Location: modules/pemasangan/proses_order.php?action=create_draft");
        exit;
        
    } elseif ($view === 'detail') {
        $no_form = $_GET['no_form'] ?? '';
        
        $stmt_head = $conn->prepare("SELECT * FROM frm_order WHERE NoFormOrder = ? AND UnitUp = ?");
        $stmt_head->execute([$no_form, $unit_up]);
        $formulir = $stmt_head->fetch(PDO::FETCH_ASSOC);
        
        if (!$formulir) die("Formulir Order tidak ditemukan atau bukan milik Unit Anda.");

        // Ambil FPK yang ada di dalam Order ini
        $stmt_detil = $conn->prepare("SELECT od.NoFpk, pk.IdPel, pk.NomorBarang as NoRef FROM frm_order_detil od JOIN frm_pk pk ON od.NoFpk = pk.NoFpk WHERE od.NoFormOrder = ?");
        $stmt_detil->execute([$no_form]);
        $items = $stmt_detil->fetchAll(PDO::FETCH_ASSOC);

        // Ambil FPK yang masih 'Nganggur' (Belum masuk Order manapun)
        $stmt_fpk_bebas = $conn->prepare("
            SELECT NoFpk, IdPel, NomorBarang as NoRef 
            FROM frm_pk 
            WHERE UnitUp = ? 
              AND NoFpk NOT IN (SELECT NoFpk FROM frm_order_detil)
        ");
        $stmt_fpk_bebas->execute([$unit_up]);
        $list_fpk_bebas = $stmt_fpk_bebas->fetchAll(PDO::FETCH_ASSOC);
    }
}

// =================================================================
// 3. MODUL MUTASI PASANG (Eksekusi Lapangan untuk SF.UP)
// =================================================================
else if ($menu === 'eksekusi' && $sub === 'mutasi_pasang' && $can_mutasi_pasang) {
    
    if ($view === 'daftar') {
        $q = trim($_GET['q'] ?? '');
        // Tampilkan semua SPK milik Unit ini tanpa memedulikan status untuk sementara
        $sql = "SELECT o.NoFormOrder, o.TglOrder, o.NamaAkun, o.StatusData, o.WaktuData,
                       (SELECT COUNT(NoFpk) FROM frm_order_detil d WHERE d.NoFormOrder = o.NoFormOrder) as TotalTarget,
                       (SELECT COUNT(d.NoFpk) FROM frm_order_detil d 
                        JOIN frm_pasang p ON d.NoFormOrder = p.NoFormOrder 
                        JOIN frm_pk pk ON d.NoFpk = pk.NoFpk AND p.NoRef = pk.NomorBarang
                        WHERE d.NoFormOrder = o.NoFormOrder AND p.StatusData = 'AKTIF') as TotalSelesai
                FROM frm_order o
                WHERE o.UnitUp = :up AND (o.NoFormOrder LIKE :q)
                ORDER BY o.WaktuData DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':up' => $unit_up, ':q' => "%$q%"]);
        $list_mutasi = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($view === 'detail') {
        $no_form = $_GET['no_form'] ?? '';
        
        $stmt_head = $conn->prepare("SELECT * FROM frm_order WHERE NoFormOrder = ? AND UnitUp = ?");
        $stmt_head->execute([$no_form, $unit_up]);
        $formulir = $stmt_head->fetch(PDO::FETCH_ASSOC);
        
        if (!$formulir) die("Surat Perintah Kerja (Order) tidak ditemukan atau bukan milik Unit Anda.");

        $stmt_detil = $conn->prepare("
            SELECT od.NoFpk, pk.IdPel, pk.NomorBarang as NoRef, pk.SimId, pk.ImeiModem,
                   p.NoFormPasang, p.TglPasang, p.PosisiPasang, p.Longitude, p.Latitude, p.StatusData as StatusPasang
            FROM frm_order_detil od
            JOIN frm_pk pk ON od.NoFpk = pk.NoFpk
            LEFT JOIN frm_pasang p ON od.NoFormOrder = p.NoFormOrder AND pk.NomorBarang = p.NoRef
            WHERE od.NoFormOrder = ?
        ");
        $stmt_detil->execute([$no_form]);
        $items = $stmt_detil->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Render Template Utama
$page_title = "Pemasangan - Digitas S41";
require_once __DIR__ . '/../../templates/pemasangan/index.php';
?>