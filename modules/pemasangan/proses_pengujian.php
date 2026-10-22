<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$kode_hak = $_SESSION['KodeHak'] ?? '';
if (!in_array($kode_hak, ['SF.AP', 'SF.UP', 'SA.KP'])) die("Akses Ditolak.");

$action    = $_GET['action'] ?? '';
$unit_up   = $_SESSION['UnitUp'] ?? '56610';
$unit_ap   = $_SESSION['UnitAp'] ?? '56610';
$unit_upi  = $_SESSION['UnitUpi'] ?? '56';
$nama_akun = $_SESSION['NamaAkun'] ?? 'System';

try {
    // 1. BUAT DRAFT FORMULIR
    if ($action === 'create_draft') {
        $tgl_uji = date('Y-m-d');
        $tgl_format = date('Ymd');
        $prefix = $unit_up; 
        
        $stmt_seq = $conn->prepare("SELECT NoFormulir FROM formulir_pengujian WHERE NoFormulir LIKE :pola ORDER BY NoFormulir DESC LIMIT 1");
        $stmt_seq->execute([':pola' => $prefix . '___' . $tgl_format . '-F.D']);
        $last_form = $stmt_seq->fetchColumn();
        
        $urutan_baru = $last_form ? (int) substr($last_form, strlen($prefix), 3) + 1 : 1;
        $no_formulir = $prefix . str_pad($urutan_baru, 3, '0', STR_PAD_LEFT) . $tgl_format . '-F.D';
        
        // MENGGUNAKAN NAMA KOLOM: UnitUp, UnitAp, UnitUpi
        $stmt = $conn->prepare("INSERT INTO formulir_pengujian (NoFormulir, TglUji, NamaAkun, UnitUp, UnitAp, UnitUpi, StatusData) VALUES (?, ?, ?, ?, ?, ?, 'TIDAK')");
        $stmt->execute([$no_formulir, $tgl_uji, $nama_akun, $unit_up, $unit_ap, $unit_upi]);
        
        header("Location: ../../index.php?page=pemasangan&menu=pra_eksekusi&sub=pengujian&view=detail&no_form=" . urlencode($no_formulir));
        exit;
    }

    // 2. SIMPAN DRAFT ALAT (PAGING)
    if ($action === 'add_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $no_form = trim($_POST['no_formulir']);
        $tgl_uji = trim($_POST['tgl_uji'] ?? date('Y-m-d')); 
        
        $conn->beginTransaction();

        $conn->prepare("UPDATE formulir_pengujian SET TglUji = ? WHERE NoFormulir = ?")->execute([$tgl_uji, $no_form]);
             
        // Helper untuk memproses input kosong menjadi NULL agar Draft bisa diisi sebagian
        $val = function($key) { return (isset($_POST[$key]) && trim($_POST[$key]) !== '') ? trim($_POST[$key]) : null; };

        $stmt = $conn->prepare("INSERT INTO formulir_pengujian_detil (
            NoFormulir, NoRef, SimId, ImeiModem, CacatFisik, StikerQC, 
            SetAdmin, ListAdmin, DelAdmin, ResetAdmin, TeganganPhasa, LampuIndikator, 
            DetikStatusAwal, JawabanStatusAwal, IsiStatusAwal, 
            DetikPutus, JawabanPutus, DetikJawabanPutus, LampuDiputus, DetikStatusPutus, JawabanStatusPutus, IsiStatusPutus, 
            DetikNyala, JawabanNyala, DetikJawabanNyala, LampuMenyala, DetikStatusNyala, JawabanStatusNyala, IsiStatusNyala, Catatan
        ) VALUES (?,?,?,?,'TIDAK','ADA', ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE 
            SimId=VALUES(SimId), ImeiModem=VALUES(ImeiModem), SetAdmin=VALUES(SetAdmin), ListAdmin=VALUES(ListAdmin), DelAdmin=VALUES(DelAdmin), ResetAdmin=VALUES(ResetAdmin), TeganganPhasa=VALUES(TeganganPhasa), LampuIndikator=VALUES(LampuIndikator), DetikStatusAwal=VALUES(DetikStatusAwal), JawabanStatusAwal=VALUES(JawabanStatusAwal), IsiStatusAwal=VALUES(IsiStatusAwal), DetikPutus=VALUES(DetikPutus), JawabanPutus=VALUES(JawabanPutus), DetikJawabanPutus=VALUES(DetikJawabanPutus), LampuDiputus=VALUES(LampuDiputus), DetikStatusPutus=VALUES(DetikStatusPutus), JawabanStatusPutus=VALUES(JawabanStatusPutus), IsiStatusPutus=VALUES(IsiStatusPutus), DetikNyala=VALUES(DetikNyala), JawabanNyala=VALUES(JawabanNyala), DetikJawabanNyala=VALUES(DetikJawabanNyala), LampuMenyala=VALUES(LampuMenyala), DetikStatusNyala=VALUES(DetikStatusNyala), JawabanStatusNyala=VALUES(JawabanStatusNyala), IsiStatusNyala=VALUES(IsiStatusNyala), Catatan=VALUES(Catatan)");

        $noref = trim($_POST['noref']);
        if(empty($noref)) throw new Exception("Nomor Referensi Alat (NoRef) wajib dipilih!");

        $stmt->execute([
            $no_form, $noref, $val('simid'), $val('imei_modem'),
            $val('set_admin'), $val('list_admin'), $val('del_admin'), $val('reset_admin'),
            $val('cek_phasa'), $val('lampu_awal'), $val('detik_status_awal'), $val('jawab_status_awal'), $val('isi_status_awal'),
            $val('detik_plnoff'), $val('jawab_plnoff'), $val('durasi_jawab_off'), $val('lampu_off'), $val('detik_status_off'), $val('jawab_status_off'), $val('isi_status_off'),
            $val('detik_plnon'), $val('jawab_plnon'), $val('durasi_jawab_on'), $val('lampu_on'), $val('detik_status_on'), $val('jawab_status_on'), $val('isi_status_on'),
            $val('catatan')
        ]);

        $conn->commit();
        header("Location: ../../index.php?page=pemasangan&menu=pra_eksekusi&sub=pengujian&view=detail&no_form=" . urlencode($no_form) . "&active_tab=" . urlencode($noref) . "&alert=saved");
        exit;
    }

    // 3. HAPUS ALAT DARI DRAFT
    if ($action === 'delete_item') {
        $no_form = $_GET['no_form'];
        $conn->prepare("DELETE FROM formulir_pengujian_detil WHERE NoFormulir=? AND NoRef=?")->execute([$no_form, $_GET['noref']]);
        header("Location: ../../index.php?page=pemasangan&menu=pra_eksekusi&sub=pengujian&view=detail&no_form=" . urlencode($no_form));
        exit;
    }

    // 4. FINALISASI FORMULIR (SIMPAN SELURUHNYA)
    if ($action === 'execute_form') {
        $no_form = trim($_POST['no_formulir']);
        $conn->beginTransaction();
        
        $conn->prepare("UPDATE formulir_pengujian SET StatusData='AKTIF' WHERE NoFormulir=?")->execute([$no_form]);
        $items = $conn->prepare("SELECT NoRef, SimId, ImeiModem FROM formulir_pengujian_detil WHERE NoFormulir=?");
        $items->execute([$no_form]);
        
        $stmt_upd_modem = $conn->prepare("UPDATE master_modem SET SimId=? WHERE ImeiModem=?");
        $stmt_ins_modem = $conn->prepare("INSERT INTO master_modem (ImeiModem, UnitUp, UnitAp, UnitUpi, SimId, StatusData) VALUES (?,?,?,?,?,'AKTIF')");
        $stmt_upd_barang = $conn->prepare("UPDATE master_barang SET SimId=?, ImeiModem=? WHERE NoRef=?");
        
        foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $it) {
            // Hanya update master jika alat sudah ada SIM dan Modem-nya (Bypass yang belum lengkap)
            if(!empty($it['ImeiModem']) && !empty($it['SimId'])) {
                $chk = $conn->prepare("SELECT ImeiModem FROM master_modem WHERE ImeiModem=?");
                $chk->execute([$it['ImeiModem']]);
                if ($chk->fetch()) $stmt_upd_modem->execute([$it['SimId'], $it['ImeiModem']]);
                else $stmt_ins_modem->execute([$it['ImeiModem'], $unit_up, $unit_ap, $unit_upi, $it['SimId']]);
                
                $stmt_upd_barang->execute([$it['SimId'], $it['ImeiModem'], $it['NoRef']]);
            }
        }
        $conn->commit();
        header("Location: ../../index.php?page=pemasangan&menu=pra_eksekusi&sub=pengujian&view=daftar&status=sukses");
        exit;
    }
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    die("<script>alert('Gagal: " . addslashes($e->getMessage()) . "'); window.history.back();</script>");
}
?>