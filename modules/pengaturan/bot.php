<?php
// modules/pengaturan/bot.php -> LOGIKA halaman Daftar Bot (tabel bot_akses + bot_akses_riwayat)
// Aturan folder, cakupan unit, validasi unit, hak akses jenis bot, dan riwayat memakai bot_helper.php (sama dengan bot_cron.php).
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';   // $conn (PDO)
require_once __DIR__ . '/../../functions/csrf.php';
require_once __DIR__ . '/../../modules/pengaturan/bot_helper.php';

$base_url = defined('BASE_URL') ? BASE_URL : '';
$url_bot  = $base_url . '/index.php?page=pengaturan&sub=bot';

$jenis_info = bot_jenis_daftar();
$hak        = (string) ($_SESSION['KodeHak'] ?? '');

// ---------- Hak akses per jenis bot (SA.KP tidak dibatasi) ----------
$jenis_izin = bot_jenis_diizinkan($hak);
if (!$jenis_izin) {
    http_response_code(403);
    echo '<div class="alert alert-danger border-0 shadow-sm m-4"><i class="bi bi-shield-lock me-2"></i>'
       . '<strong>Akses ditolak.</strong> Akun Anda tidak memiliki hak untuk mengatur bot.</div>';
    return;
}
// Hanya jenis yang diizinkan yang tampil di dropdown
$jenis_opsi = [];
foreach ($jenis_izin as $j) $jenis_opsi[$j] = $jenis_info[$j]['label'];

// ---------- Cakupan Batas Data (Scope) ----------
// SA.KP : semua bot.
// Akun lain: WAJIB UnitUpi = UnitUpi session login (kosong -> tidak ada bot yang tampil);
//            bila session juga terikat AP / UP, cakupan dipersempit lagi sesuai AP / UP tersebut.
if (!function_exists('bot_scope')) {
    function bot_scope(): array
    {
        if (bot_super((string) ($_SESSION['KodeHak'] ?? ''))) return ['1=1', []];
        $upi = trim((string) ($_SESSION['UnitUpi'] ?? ''));
        $ap  = trim((string) ($_SESSION['UnitAp'] ?? ''));
        $up  = trim((string) ($_SESSION['UnitUp'] ?? ''));
        if ($upi === '') return ['1=0', []];
        $w = ['UnitUpi = ?']; $p = [$upi];
        if ($ap !== '') { $w[] = 'UnitAp = ?'; $p[] = $ap; }
        if ($up !== '') { $w[] = 'UnitUp = ?'; $p[] = $up; }
        return [implode(' AND ', $w), $p];
    }
}

[$root_bot, $root_konfig] = bot_root();
[$w_scope, $p_scope]      = bot_scope();

// Selain SA.KP, batasi juga pada jenis bot yang diizinkan.
// Semua query yang memakai $w_scope (cek bot yang diubah, status, hapus, daftar) otomatis ikut terbatas.
if (!bot_super($hak)) {
    $w_scope = "($w_scope) AND JenisBot IN (" . implode(',', array_fill(0, count($jenis_izin), '?')) . ')';
    $p_scope = array_merge($p_scope, $jenis_izin);
}

// Pesan flash dari proses sebelumnya
$pesan = $_SESSION['bot_flash'] ?? null;
unset($_SESSION['bot_flash']);

$errors    = [];
$form_isi  = null;
$buka_form = false;

// ---------- Proses POST ----------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $aksi  = $_POST['aksi'] ?? '';
    $flash = null;

    try {
        if ($aksi === 'simpan') {
            $id        = (int) ($_POST['id'] ?? 0);
            $jenis     = (string) ($_POST['jenis'] ?? $_POST['JenisBot'] ?? '');
            $stat      = (($_POST['status'] ?? 'AKTIF') === 'TIDAK') ? 'TIDAK' : 'AKTIF';
            $dir_utama = bot_norm_folder((string) ($_POST['direktori_utama'] ?? $_POST['folder_masuk'] ?? ''));
            $dir_arsip = bot_norm_folder((string) ($_POST['direktori_arsip'] ?? $_POST['folder_berhasil'] ?? ''));

            // Unit: SA.KP bebas memilih (divalidasi ke master); akun lain terkunci pada cakupan session-nya,
            // dan hanya tingkat yang kosong di session yang boleh dipilih dari form.
            $ses_upi = trim((string) ($_SESSION['UnitUpi'] ?? ''));
            $ses_ap  = trim((string) ($_SESSION['UnitAp'] ?? ''));
            $ses_up  = trim((string) ($_SESSION['UnitUp'] ?? ''));
            $pos_upi = trim((string) ($_POST['unit_upi'] ?? ''));
            $pos_ap  = trim((string) ($_POST['unit_ap'] ?? ''));
            $pos_up  = trim((string) ($_POST['unit_up'] ?? ''));
            if (bot_super($hak)) {
                $unit_upi = $pos_upi !== '' ? $pos_upi : $ses_upi;
                $unit_ap  = $pos_ap;
                $unit_up  = $pos_up;
            } else {
                $unit_upi = $ses_upi;
                $unit_ap  = $ses_ap !== '' ? $ses_ap : $pos_ap;
                $unit_up  = $ses_up !== '' ? $ses_up : $pos_up;
            }

            $form_isi = [
                'id' => $id, 'jenis' => $jenis,
                'direktori_utama' => (string) ($_POST['direktori_utama'] ?? ''),
                'direktori_arsip' => (string) ($_POST['direktori_arsip'] ?? ''),
                'status' => $stat, 'unit_upi' => $unit_upi, 'unit_ap' => $unit_ap, 'unit_up' => $unit_up
            ];

            // Bot yang diubah harus ada di cakupan unit DAN jenis yang diizinkan (filter ada di $w_scope).
            if ($id > 0) {
                $s = $conn->prepare("SELECT * FROM bot_akses WHERE IdBot = ? AND ($w_scope)");
                $s->execute(array_merge([$id], $p_scope));
                if (!$s->fetch()) {
                    $errors[] = 'Bot tidak ditemukan atau bukan milik unit/jenis yang boleh Anda atur.';
                }
            }

            if (!isset($jenis_info[$jenis])) {
                $errors[] = 'Jenis bot tidak valid.';
            } elseif (!bot_boleh_jenis($hak, $jenis)) {
                $errors[] = 'Anda tidak berhak mengatur bot jenis ini.';
            }
            if ($dir_utama === null) $errors[] = 'Direktori Utama tidak valid (hanya huruf, angka, _ dan -, pisahkan tingkat dengan /).';
            if ($dir_arsip === null) $errors[] = 'Direktori Arsip tidak valid (hanya huruf, angka, _ dan -, pisahkan tingkat dengan /).';

            if ($dir_utama !== null && $dir_arsip !== null) {
                if (strtolower($dir_utama) === strtolower($dir_arsip)) {
                    $errors[] = 'Direktori Utama dan Direktori Arsip harus berbeda.';
                }
                // Arsip boleh berada di dalam Direktori Utama (cron hanya membaca file, bukan subfolder).
                if (bot_folder_dilarang($dir_utama) || bot_folder_dilarang($dir_arsip)) {
                    $errors[] = "Nama folder 'log' dicadangkan untuk berkas log cron.";
                }
            }

            // Cakupan unit harus ada di master dan saling berhubungan (UP di bawah AP di bawah UPI).
            if (!$errors) {
                $err_unit = bot_validasi_unit($conn, $unit_upi, $unit_ap, $unit_up);
                if ($err_unit !== null) $errors[] = $err_unit;
            }

            // Direktori Utama tidak boleh sama / bertumpuk dengan bot lain (kalau tidak, dua bot memproses file yang sama).
            if (!$errors) {
                $s = $conn->prepare('SELECT IdBot, DirektoriUtama FROM bot_akses WHERE IdBot <> ?');
                $s->execute([$id]);
                foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $o) {
                    $lain = bot_norm_folder((string) $o['DirektoriUtama']);
                    if ($lain === null) continue;
                    if (bot_di_dalam($dir_utama, $lain) || bot_di_dalam($lain, $dir_utama)) {
                        $errors[] = 'Direktori Utama bertabrakan dengan Bot #' . (int) $o['IdBot'] . ' (' . $lain . '). Pakai folder yang terpisah.';
                        break;
                    }
                }
            }

            if (!$errors) {
                $ap_db = $unit_ap !== '' ? $unit_ap : null;
                $up_db = $unit_up !== '' ? $unit_up : null;
                if ($id > 0) {
                    $conn->prepare("UPDATE bot_akses SET JenisBot=?, DirektoriUtama=?, DirektoriArsip=?, StatusData=?, UnitUpi=?, UnitAp=?, UnitUp=?, WaktuData=NOW() WHERE IdBot=?")
                         ->execute([$jenis, $dir_utama, $dir_arsip, $stat, $unit_upi, $ap_db, $up_db, $id]);
                } else {
                    $conn->prepare("INSERT INTO bot_akses (JenisBot, DirektoriUtama, DirektoriArsip, StatusData, UnitUpi, UnitAp, UnitUp, WaktuData)
                                    VALUES (?,?,?,?,?,?,?,NOW())")
                         ->execute([$jenis, $dir_utama, $dir_arsip, $stat, $unit_upi, $ap_db, $up_db]);
                }

                $teks = "Konfigurasi Bot <strong>" . htmlspecialchars($jenis) . "</strong> berhasil disimpan.";
                if ($root_bot === '') {
                    $teks .= ' Folder belum dibuat karena folder bot tidak ditemukan (' . htmlspecialchars($root_konfig) . ').';
                } else {
                    // Folder yang dibuat di sini sama persis dengan yang dipantau cron (bot_helper.php)
                    $baris_bot = ['JenisBot' => $jenis, 'DirektoriUtama' => $dir_utama, 'DirektoriArsip' => $dir_arsip,
                                  'UnitUpi' => $unit_upi, 'UnitAp' => $unit_ap, 'UnitUp' => $unit_up];
                    [, $gagal] = bot_siapkan_folder($root_bot, $conn, $baris_bot);
                    if ($gagal) $teks .= ' Folder gagal dibuat: ' . htmlspecialchars(implode(', ', $gagal)) . '.';
                }
                $flash = ['tipe' => 'success', 'teks' => $teks];
            } else {
                $buka_form = true;
            }

        } elseif ($aksi === 'status') {
            $ke = (($_POST['ke'] ?? '') === 'AKTIF') ? 'AKTIF' : 'TIDAK';
            $conn->prepare("UPDATE bot_akses SET StatusData = ? WHERE IdBot = ? AND ($w_scope)")
                 ->execute(array_merge([$ke, (int) ($_POST['id'] ?? 0)], $p_scope));
            $flash = ['tipe' => 'success', 'teks' => 'Status bot berhasil diperbarui.'];

        } elseif ($aksi === 'hapus') {
            $id_hapus = (int) ($_POST['id'] ?? 0);
            $s = $conn->prepare("SELECT 1 FROM bot_akses WHERE IdBot = ? AND ($w_scope)");
            $s->execute(array_merge([$id_hapus], $p_scope));
            if ($s->fetchColumn()) {
                // Riwayat milik bot ini ikut dihapus (IdBot di bot_akses_riwayat merujuk ke bot_akses).
                $conn->beginTransaction();
                $conn->prepare("DELETE FROM bot_akses_riwayat WHERE IdBot = ?")->execute([$id_hapus]);
                $conn->prepare("DELETE FROM bot_akses WHERE IdBot = ?")->execute([$id_hapus]);
                $conn->commit();
                $flash = ['tipe' => 'success', 'teks' => 'Bot dan riwayatnya dihapus. Folder dan file di server tidak ikut dihapus.'];
            } else {
                $flash = ['tipe' => 'danger', 'teks' => 'Bot tidak ditemukan atau bukan milik unit/jenis yang boleh Anda atur.'];
            }
        }
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        error_log('bot_akses: ' . $e->getMessage());
        $flash = ['tipe' => 'danger', 'teks' => 'Terjadi kesalahan pada server: ' . htmlspecialchars($e->getMessage())];
    }

    if ($flash !== null && !$errors) {
        $_SESSION['bot_flash'] = $flash;
        header('Location: ' . $url_bot);
        exit;
    }
}

// ---------- Ambil Data Master AP & UP ----------
$daftar_ap = [];
$daftar_up = [];
try {
    $upi_ses = trim((string) ($_SESSION['UnitUpi'] ?? ''));
    $ap_ses  = trim((string) ($_SESSION['UnitAp'] ?? ''));

    if (bot_super($hak)) {
        $s_ap = $conn->query("SELECT UnitAp, UnitUpi, NamaUnit FROM master_ap ORDER BY UnitAp ASC");
        $daftar_ap = $s_ap->fetchAll(PDO::FETCH_ASSOC);

        $s_up = $conn->query("SELECT UnitUp, UnitAp, UnitUpi, NamaUnit FROM master_up ORDER BY UnitUp ASC");
        $daftar_up = $s_up->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sql_ap = "SELECT UnitAp, UnitUpi, NamaUnit FROM master_ap WHERE UnitUpi = ?";
        $params_ap = [$upi_ses];
        if ($ap_ses !== '') {
            $sql_ap .= " AND UnitAp = ?";
            $params_ap[] = $ap_ses;
        }
        $sql_ap .= " ORDER BY UnitAp ASC";
        $s_ap = $conn->prepare($sql_ap);
        $s_ap->execute($params_ap);
        $daftar_ap = $s_ap->fetchAll(PDO::FETCH_ASSOC);

        $sql_up = "SELECT UnitUp, UnitAp, UnitUpi, NamaUnit FROM master_up WHERE UnitUpi = ?";
        $params_up = [$upi_ses];
        if ($ap_ses !== '') {
            $sql_up .= " AND UnitAp = ?";
            $params_up[] = $ap_ses;
        }
        $sql_up .= " ORDER BY UnitUp ASC";
        $s_up = $conn->prepare($sql_up);
        $s_up->execute($params_up);
        $daftar_up = $s_up->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $ex) {
    error_log('master_unit (daftar): ' . $ex->getMessage());
}

// ---------- Ambil Daftar Bot ----------
try {
    $s = $conn->prepare("SELECT * FROM bot_akses WHERE $w_scope ORDER BY StatusData ASC, IdBot ASC");
    $s->execute($p_scope);
    $bots = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    error_log('bot_akses (daftar): ' . $ex->getMessage());
    echo '<div class="alert alert-danger border-0 shadow-sm m-4"><i class="bi bi-database-exclamation me-2"></i>'
       . '<strong>Data bot tidak bisa dibaca.</strong> Pastikan tabel <code>bot_akses</code> tersedia.</div>';
    return;
}

// ---------- Ambil Riwayat Bot (hanya bot yang lolos cakupan di atas) ----------
// $riwayat[IdBot] = daftar baris terbaru lebih dulu (maks BOT_RIWAYAT_MAKS); baris pertama = proses terakhir.
$riwayat = [];
if ($bots) {
    try {
        $st = $conn->prepare("SELECT TiketBot, JmlData, JmlBerhasil, JmlGagal, NamaAkun, UnitUp, UnitAp, UnitUpi, WaktuData
                              FROM bot_akses_riwayat WHERE IdBot = ?
                              ORDER BY WaktuData DESC, TiketBot DESC LIMIT " . (int) BOT_RIWAYAT_MAKS);
        foreach ($bots as $b) {
            $st->execute([(int) $b['IdBot']]);
            $baris = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $ok = (int) $r['JmlBerhasil'];
                $gl = (int) $r['JmlGagal'];
                $baris[] = [
                    'tiket'    => (string) $r['TiketBot'],
                    'waktu'    => $r['WaktuData'] ? date('d/m/Y H:i:s', strtotime((string) $r['WaktuData'])) : '-',
                    'upi'      => (string) ($r['UnitUpi'] ?? ''),
                    'ap'       => (string) ($r['UnitAp'] ?? ''),
                    'up'       => (string) ($r['UnitUp'] ?? ''),
                    'data'     => (int) $r['JmlData'],
                    'berhasil' => $ok,
                    'gagal'    => $gl,
                    'status'   => bot_status_riwayat($ok, $gl),
                    'akun'     => (string) ($r['NamaAkun'] ?? ''),
                ];
            }
            $riwayat[(int) $b['IdBot']] = $baris;
        }
    } catch (PDOException $ex) {
        error_log('bot_akses_riwayat (daftar): ' . $ex->getMessage());   // halaman tetap tampil tanpa riwayat
    }
}

require __DIR__ . '/../../templates/pengaturan/bot.php';