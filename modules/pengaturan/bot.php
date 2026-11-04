<?php
// modules/pengaturan/bot.php -> LOGIKA halaman Daftar Bot (tambah, ubah, aktif/nonaktif, hapus)
// Dipanggil oleh modules/pengaturan/index.php (?page=pengaturan&sub=bot)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';   // $conn (PDO)
require_once __DIR__ . '/../../functions/csrf.php';
require_once __DIR__ . '/bot_helper.php';               // bot_root(), bot_norm_folder(), bot_jenis_daftar(), dst.

$base_url = defined('BASE_URL') ? BASE_URL : '';
$url_bot  = $base_url . '/index.php?page=pengaturan&sub=bot';

// Jenis bot = ENUM pengaturan_bot.JenisBot ('PELUNASAN','DIL','TUL','SURVEY','KEPENTINGAN')
$jenis_info = bot_jenis_daftar();
$jenis_opsi = array_map(fn($j) => $j['label'], $jenis_info);
$pola_opsi  = ['*.xlsx' => '*.xlsx (Excel)'];

// ---------- Cakupan Batas Data (Scope) ----------
if (!function_exists('bot_scope')) {
    /** Batas data sesuai unit akun: SA.KP semua; lainnya hanya bot di unit (dan turunannya) miliknya. */
    function bot_scope(): array
    {
        if (($_SESSION['KodeHak'] ?? '') === 'SA.KP') return ['1=1', []];
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

// ---------- Ambil Master Unit AP & UP untuk Dropdown Form ----------
$daftar_ap = [];
$daftar_up = [];
try {
    $upi_user = trim((string) ($_SESSION['UnitUpi'] ?? ''));
    
    // Ambil Master AP
    $sql_ap = "SELECT UnitAp, NamaAp FROM master_ap WHERE StatusData = 'AKTIF'";
    $prm_ap = [];
    if ($upi_user !== '') {
        $sql_ap .= " AND UnitUpi = ?";
        $prm_ap[] = $upi_user;
    }
    $sql_ap .= " ORDER BY UnitAp ASC";
    $st_ap = $conn->prepare($sql_ap);
    $st_ap->execute($prm_ap);
    $daftar_ap = $st_ap->fetchAll(PDO::FETCH_ASSOC);

    // Ambil Master UP
    $sql_up = "SELECT UnitUp, NamaUp, UnitAp FROM master_up WHERE StatusData = 'AKTIF'";
    $prm_up = [];
    if ($upi_user !== '') {
        $sql_up .= " AND UnitUpi = ?";
        $prm_up[] = $upi_user;
    }
    $sql_up .= " ORDER BY UnitUp ASC";
    $st_up = $conn->prepare($sql_up);
    $st_up->execute($prm_up);
    $daftar_up = $st_up->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error ambil master unit: ' . $e->getMessage());
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
            $id    = (int) ($_POST['id'] ?? 0);
            $kode  = strtolower(trim((string) ($_POST['kode'] ?? '')));
            $nama  = trim((string) ($_POST['nama'] ?? ''));
            $jenis = (string) ($_POST['jenis'] ?? $_POST['JenisBot'] ?? '');
            $ket   = trim((string) ($_POST['keterangan'] ?? ''));
            $pola  = (string) ($_POST['pola'] ?? '*.xlsx');
            $stat  = (($_POST['status'] ?? 'AKTIF') === 'TIDAK') ? 'TIDAK' : 'AKTIF';
            $fm    = bot_norm_folder((string) ($_POST['folder_masuk'] ?? ''));
            $fb    = bot_norm_folder((string) ($_POST['folder_berhasil'] ?? ''));
            $fg    = bot_norm_folder((string) ($_POST['folder_gagal'] ?? ''));

            // Penentuan Unit: Prioritas Sesi -> Input Form -> Default Null
            $unit_upi = trim((string) ($_SESSION['UnitUpi'] ?? $_POST['unit_upi'] ?? ''));
            $unit_ap  = trim((string) ($_SESSION['UnitAp'] ?? $_POST['unit_ap'] ?? ''));
            $unit_up  = trim((string) ($_SESSION['UnitUp'] ?? $_POST['unit_up'] ?? ''));

            $form_isi = [
                'id' => $id, 'kode' => $kode, 'nama' => $nama, 'jenis' => $jenis, 'keterangan' => $ket,
                'folder_masuk' => (string) ($_POST['folder_masuk'] ?? ''),
                'folder_berhasil' => (string) ($_POST['folder_berhasil'] ?? ''),
                'folder_gagal' => (string) ($_POST['folder_gagal'] ?? ''),
                'pola' => $pola, 'status' => $stat,
                'unit_upi' => $unit_upi, 'unit_ap' => $unit_ap, 'unit_up' => $unit_up
            ];

            $lama = null;
            if ($id > 0) {
                $s = $conn->prepare("SELECT * FROM pengaturan_bot WHERE IdBot = ? AND ($w_scope)");
                $s->execute(array_merge([$id], $p_scope));
                $lama = $s->fetch(PDO::FETCH_ASSOC);
                if (!$lama) {
                    $errors[] = 'Bot tidak ditemukan atau bukan milik unit Anda.';
                } else {
                    $kode = $lama['KodeBot']; // kode bot lama tidak boleh diubah
                }
            } elseif (!preg_match('/^[a-z0-9_]{3,30}$/', $kode)) {
                $errors[] = 'Kode bot 3-30 karakter: huruf kecil, angka, dan garis bawah (_).';
            }

            if (mb_strlen($nama) < 3 || mb_strlen($nama) > 100) $errors[] = 'Nama bot 3-100 karakter.';
            if (!isset($jenis_opsi[$jenis])) $errors[] = 'Jenis bot tidak valid.';
            if (mb_strlen($ket) > 255) $errors[] = 'Keterangan maksimal 255 karakter.';
            if (!isset($pola_opsi[$pola])) $errors[] = 'Pola file tidak valid.';

            foreach (['Folder masuk' => $fm, 'Folder berhasil' => $fb, 'Folder gagal' => $fg] as $nm => $v) {
                if ($v === null) $errors[] = "$nm tidak valid. Pakai huruf, angka, _ dan -, dipisah / (contoh: riwayat/masuk).";
            }
            if ($fm !== null && $fb !== null && $fg !== null) {
                if (strtolower($fb) === strtolower($fg)) $errors[] = 'Folder berhasil dan folder gagal harus berbeda.';
                if (bot_di_dalam($fb, $fm) || bot_di_dalam($fg, $fm)) $errors[] = 'Folder berhasil/gagal tidak boleh sama dengan atau berada di dalam folder masuk.';
                if (bot_di_dalam($fm, $fb) || bot_di_dalam($fm, $fg)) $errors[] = 'Folder masuk tidak boleh berada di dalam folder berhasil/gagal.';
            }

            if ($id === 0 && $unit_upi === '') {
                $errors[] = 'Unit UPI belum ditentukan. Silakan pilih unit wilayah terlebih dahulu.';
            }

            if (!$errors) {
                // Cek ketersediaan kolom Keterangan di tabel database
                $has_ket_col = false;
                try {
                    $chk = $conn->query("SHOW COLUMNS FROM pengaturan_bot LIKE 'Keterangan'");
                    $has_ket_col = (bool) $chk->fetch();
                } catch (Exception $ex) { $has_ket_col = false; }

                if ($id > 0) {
                    if ($has_ket_col) {
                        $conn->prepare("UPDATE pengaturan_bot SET NamaBot=?, JenisBot=?, Keterangan=?, FolderMasuk=?, FolderBerhasil=?, FolderGagal=?, PolaFile=?, StatusData=?, UnitAp=?, UnitUp=? WHERE IdBot=?")
                             ->execute([$nama, $jenis, $ket !== '' ? $ket : null, $fm, $fb, $fg, $pola, $stat, $unit_ap !== '' ? $unit_ap : null, $unit_up !== '' ? $unit_up : null, $id]);
                    } else {
                        $conn->prepare("UPDATE pengaturan_bot SET NamaBot=?, JenisBot=?, FolderMasuk=?, FolderBerhasil=?, FolderGagal=?, PolaFile=?, StatusData=?, UnitAp=?, UnitUp=? WHERE IdBot=?")
                             ->execute([$nama, $jenis, $fm, $fb, $fg, $pola, $stat, $unit_ap !== '' ? $unit_ap : null, $unit_up !== '' ? $unit_up : null, $id]);
                    }
                } else {
                    $nama_akun = mb_substr((string) ($_SESSION['NamaAkun'] ?? 'admin'), 0, 20);
                    if ($has_ket_col) {
                        $conn->prepare("INSERT INTO pengaturan_bot (KodeBot, NamaBot, JenisBot, Keterangan, FolderMasuk, FolderBerhasil, FolderGagal, PolaFile, StatusData, UnitUpi, UnitAp, UnitUp, NamaAkun)
                                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                             ->execute([$kode, $nama, $jenis, $ket !== '' ? $ket : null, $fm, $fb, $fg, $pola, $stat,
                                        $unit_upi, $unit_ap !== '' ? $unit_ap : null, $unit_up !== '' ? $unit_up : null, $nama_akun]);
                    } else {
                        $conn->prepare("INSERT INTO pengaturan_bot (KodeBot, NamaBot, JenisBot, FolderMasuk, FolderBerhasil, FolderGagal, PolaFile, StatusData, UnitUpi, UnitAp, UnitUp, NamaAkun)
                                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                             ->execute([$kode, $nama, $jenis, $fm, $fb, $fg, $pola, $stat,
                                        $unit_upi, $unit_ap !== '' ? $unit_ap : null, $unit_up !== '' ? $unit_up : null, $nama_akun]);
                    }
                }

                $teks = "Bot <strong>" . htmlspecialchars($nama) . "</strong> berhasil disimpan.";
                if ($root_bot === '') {
                    $teks .= ' Folder belum dibuat karena folder bot tidak ditemukan (' . htmlspecialchars($root_konfig) . ').';
                } elseif ($gagal = bot_buat_folder($root_bot, [$fm, $fb, $fg])) {
                    $teks .= ' Folder gagal dibuat: ' . htmlspecialchars(implode(', ', $gagal)) . '.';
                }
                $flash = ['tipe' => 'success', 'teks' => $teks];
            } else {
                $buka_form = true;
            }

        } elseif ($aksi === 'status') {
            $ke = (($_POST['ke'] ?? '') === 'AKTIF') ? 'AKTIF' : 'TIDAK';
            $s  = $conn->prepare("SELECT NamaBot FROM pengaturan_bot WHERE IdBot = ? AND ($w_scope)");
            $s->execute(array_merge([(int) ($_POST['id'] ?? 0)], $p_scope));
            $nama_bot = $s->fetchColumn();

            if ($nama_bot === false) {
                $flash = ['tipe' => 'danger', 'teks' => 'Bot tidak ditemukan atau bukan milik unit Anda.'];
            } else {
                $conn->prepare("UPDATE pengaturan_bot SET StatusData = ? WHERE IdBot = ?")->execute([$ke, (int) $_POST['id']]);
                $flash = ['tipe' => 'success', 'teks' => 'Bot <strong>' . htmlspecialchars((string) $nama_bot) . '</strong> '
                          . ($ke === 'AKTIF' ? 'dimulai.' : 'dihentikan.')];
            }

        } elseif ($aksi === 'hapus') {
            $s = $conn->prepare("DELETE FROM pengaturan_bot WHERE IdBot = ? AND ($w_scope)");
            $s->execute(array_merge([(int) ($_POST['id'] ?? 0)], $p_scope));
            $flash = $s->rowCount() ? ['tipe' => 'success', 'teks' => 'Bot dihapus. Folder dan file di dalamnya tidak ikut dihapus.']
                                    : ['tipe' => 'danger', 'teks' => 'Bot tidak ditemukan atau bukan milik unit Anda.'];
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $errors[]  = 'Kode bot sudah dipakai. Gunakan kode lain.';
            $buka_form = true;
        } else {
            error_log('pengaturan_bot: ' . $e->getMessage());
            $flash = ['tipe' => 'danger', 'teks' => 'Terjadi kesalahan pada server: ' . htmlspecialchars($e->getMessage())];
        }
    }

    if ($flash !== null && !$errors) {
        $_SESSION['bot_flash'] = $flash;
        header('Location: ' . $url_bot);
        exit;
    }
}

// ---------- Ambil Daftar Bot ----------
try {
    $s = $conn->prepare("SELECT * FROM pengaturan_bot WHERE $w_scope ORDER BY StatusData ASC, NamaBot ASC");
    $s->execute($p_scope);
    $bots = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    error_log('pengaturan_bot (daftar): ' . $ex->getMessage());
    echo '<div class="alert alert-danger border-0 shadow-sm m-4"><i class="bi bi-database-exclamation me-2"></i>'
       . ($ex->getCode() === '42S02'
            ? '<strong>Tabel <code>pengaturan_bot</code> belum ada.</strong> Jalankan <code>sql/pengaturan_bot.sql</code> di HeidiSQL, lalu muat ulang halaman ini.'
            : '<strong>Data bot tidak bisa dibaca.</strong> Periksa log error PHP untuk rinciannya.')
       . '</div>';
    return;
}

require __DIR__ . '/../../templates/pengaturan/bot.php';