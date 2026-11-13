<?php
// modules/penggunaan/shuntrip.php  -  Update Jenis Shuntrip (kolom dil.JenisShuntrip) berdasarkan IdPel
// Dua cara: (1) input satu IdPel lewat pop-up form, (2) upload Excel (kolom IdPel + JenisShuntrip).
// Aturan  : hanya MEMPERBARUI IdPel yang sudah ada di tabel dil dan berada di wilayah akun (kode unit session: UnitUpi selalu,
//           + UnitAp / UnitUp bila akun terikat). Tidak pernah menambah pelanggan baru dan tidak mengubah kolom lain (selain WaktuData).
// Nilai   : ENUM 'MCCB','RELAY','ALARM','S41','AMI','SDU'. Nilai yang sama dengan data sekarang dilewati (tidak diperbarui).
require_once __DIR__ . '/../../functions/xlsx_reader.php';

if (!defined('JENIS_SHUNTRIP')) define('JENIS_SHUNTRIP', ['MCCB', 'RELAY', 'ALARM', 'S41', 'AMI', 'SDU']);

$pesan_sukses = $pesan_error = $pesan_info = '';
$detail_import = [];
$form_lama = [];          // isian pop-up satu IdPel yang perlu dibuka kembali
$buka_modal = '';         // 'satu' bila pop-up harus dibuka lagi (gagal simpan / "lanjut input")

if (empty($_SESSION['pg_csrf'])) $_SESSION['pg_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['pg_csrf'];

$u         = pg_unit_akun($conn);
$unit_siap = $u['upi'] !== '';

if (!function_exists('st_idpel')) {
    /** IdPel dinormalkan: ".0"/angka ilmiah dari Excel dibereskan, angka kurang dari 12 digit dilengkapi nol di depan. */
    function st_idpel($v): string
    {
        $v = trim((string) $v);
        if (preg_match('/^\d+\.0+$/', $v))                     $v = preg_replace('/\.0+$/', '', $v);
        elseif (preg_match('/^\d+(\.\d+)?[eE]\+?\d+$/', $v))   $v = sprintf('%.0f', (float) $v);
        if (preg_match('/^\d{1,12}$/', $v))                    $v = str_pad($v, 12, '0', STR_PAD_LEFT);
        return $v;
    }
    /** Jenis shuntrip: huruf besar, tanda baca/spasi dibuang ("s-41" -> "S41"); null bila bukan nilai ENUM. */
    function st_jenis($v): ?string
    {
        $v = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string) $v)));
        return in_array($v, JENIS_SHUNTRIP, true) ? $v : null;
    }
}

if (!empty($_SESSION['pg_flash'])) {
    $fl = $_SESSION['pg_flash']; unset($_SESSION['pg_flash']);
    $pesan_sukses  = $fl['sukses'] ?? ''; $pesan_error = $fl['error'] ?? ''; $pesan_info = $fl['info'] ?? '';
    $detail_import = $fl['detail'] ?? []; $form_lama = $fl['form_lama'] ?? []; $buka_modal = $fl['buka'] ?? '';
}

// ---------------------------------------------------------
// PROSES FORM (POST)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = (string) ($_POST['aksi'] ?? '');

    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $pesan_error = 'File melebihi batas upload server (post_max_size = ' . ini_get('post_max_size') . ', upload_max_filesize = ' . ini_get('upload_max_filesize') . '). Naikkan nilai tersebut di php.ini lalu restart web server.';
    } elseif (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $pesan_error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (!$unit_siap) {
        $pesan_error = 'Akun Anda belum memiliki Unit UPI, sehingga data tidak dapat diperbarui. Hubungi administrator.';

    } elseif ($aksi === 'satu') {
        // ---- (1) satu IdPel ----
        $idpel = st_idpel($_POST['idpel'] ?? '');
        $jenis = st_jenis($_POST['jenis'] ?? '');
        $lanjut = !empty($_POST['lanjut']);
        if (!preg_match('/^[A-Za-z0-9]{12}$/', $idpel)) {
            $pesan_error = 'IdPel harus 12 karakter (huruf/angka).';
        } elseif ($jenis === null) {
            $pesan_error = 'Pilih jenis shuntrip: ' . implode(', ', JENIS_SHUNTRIP) . '.';
        } else {
            try {
                $p = [':id' => $idpel]; $scope = pg_scope($u, 'd', 'Unit', $p);
                $st = $conn->prepare("SELECT d.Nama, d.JenisShuntrip FROM dil d WHERE d.IdPel = :id AND $scope LIMIT 1");
                $st->execute($p);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    $pesan_error = 'IdPel <strong>' . pg_e($idpel) . '</strong> tidak ditemukan di DIL pada wilayah Anda. Tambahkan dulu lewat Input DIL bila belum ada.';
                } elseif ((string) $row['JenisShuntrip'] === $jenis) {
                    $pesan_info = 'IdPel <strong>' . pg_e($idpel) . '</strong> (' . pg_e($row['Nama']) . ') sudah berjenis shuntrip <strong>' . pg_e($jenis) . '</strong>, tidak ada perubahan.';
                } else {
                    $pu = [':id' => $idpel, ':jn' => $jenis]; $scope_u = pg_scope($u, 'd', 'Unit', $pu);
                    $conn->prepare("UPDATE dil d SET d.JenisShuntrip = :jn, d.WaktuData = NOW() WHERE d.IdPel = :id AND $scope_u")->execute($pu);
                    $pesan_sukses = 'IdPel <strong>' . pg_e($idpel) . '</strong> (' . pg_e($row['Nama']) . '): jenis shuntrip '
                                  . ((string) $row['JenisShuntrip'] === '' ? '<em>kosong</em>' : pg_e($row['JenisShuntrip'])) . ' &rarr; <strong>' . pg_e($jenis) . '</strong>.';
                }
            } catch (PDOException $e) {
                $pesan_error = 'Gagal memperbarui: ' . pg_e($e->getMessage());
            }
        }
        if ($pesan_error !== '') { $form_lama = ['idpel' => $idpel, 'jenis' => (string) $jenis]; $buka_modal = 'satu'; }
        elseif ($lanjut)         { $form_lama = ['idpel' => '', 'jenis' => (string) $jenis, 'lanjut' => '1']; $buka_modal = 'satu'; }   // siap input berikutnya

    } elseif ($aksi === 'excel') {
        // ---- (2) upload Excel: kolom IdPel + JenisShuntrip ----
        $f = $_FILES['file_xlsx'] ?? null;
        if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
            $pesan_error = 'Pilih file Excel (.xlsx) terlebih dahulu.';
        } elseif ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
            $pesan_error = 'File melebihi batas upload server (upload_max_filesize = ' . ini_get('upload_max_filesize') . ', post_max_size = ' . ini_get('post_max_size') . ').';
        } elseif ($f['error'] !== UPLOAD_ERR_OK) {
            $pesan_error = 'Upload gagal (kode ' . (int) $f['error'] . ').';
        } elseif (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
            $pesan_error = 'Format file harus .xlsx.';
        } elseif ($f['size'] > 10 * 1024 * 1024) {
            $pesan_error = 'Ukuran file maksimal 10 MB.';
        } else {
            @set_time_limit(300);
            $kolom_id = $kolom_jn = null;
            $baris = [];                                  // IdPel => [jenis, nomor baris]
            $total = $salah = $dobel = $tidak_ada = $sama = 0;
            $tambah_detail = function (string $t) use (&$detail_import) { if (count($detail_import) < 20) $detail_import[] = $t; };
            try {
                foreach (xlsx_baris($f['tmp_name']) as $no => $sel) {
                    if ($kolom_id === null) {             // cari baris judul (30 baris pertama)
                        if ($no > 30) throw new RuntimeException('Baris judul kolom (IDPEL, JENISSHUNTRIP) tidak ditemukan di 30 baris pertama.');
                        $ti = $tj = null;
                        foreach ($sel as $huruf => $isi) {
                            $k = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $isi));
                            if ($k === 'IDPEL' && $ti === null) $ti = $huruf;
                            if (($k === 'JENISSHUNTRIP' || $k === 'SHUNTRIP') && $tj === null) $tj = $huruf;
                        }
                        if ($ti !== null && $tj !== null) { $kolom_id = $ti; $kolom_jn = $tj; }
                        continue;
                    }
                    $id_mentah = trim((string) ($sel[$kolom_id] ?? '')); $jn_mentah = trim((string) ($sel[$kolom_jn] ?? ''));
                    if ($id_mentah === '' && $jn_mentah === '') continue;       // baris kosong
                    $total++;
                    $id = st_idpel($id_mentah); $jn = st_jenis($jn_mentah);
                    if (!preg_match('/^[A-Za-z0-9]{12}$/', $id))  { $salah++; $tambah_detail("Baris $no: IdPel tidak valid (" . ($id_mentah === '' ? 'kosong' : $id_mentah) . ')'); continue; }
                    if ($jn === null)                             { $salah++; $tambah_detail("Baris $no: jenis shuntrip tidak valid (" . ($jn_mentah === '' ? 'kosong' : $jn_mentah) . ') untuk IdPel ' . $id); continue; }
                    if (isset($baris[$id])) { $dobel++; $tambah_detail("Baris $no: IdPel $id dobel di dalam file (nilai terakhir dipakai)"); }
                    $baris[$id] = [$jn, $no];
                }
                if ($kolom_id === null) throw new RuntimeException('Baris judul kolom tidak ditemukan. File harus punya kolom IDPEL dan JENISSHUNTRIP.');
                if (!$baris) throw new RuntimeException($total === 0 ? 'File tidak berisi data di bawah baris judul.' : 'Tidak ada baris valid yang bisa diproses.');

                // cek ke dil: hanya IdPel yang ada di wilayah akun; nilai yang sama dilewati
                $ubah = [];
                foreach (array_chunk(array_map('strval', array_keys($baris)), 500) as $paket) {   // strval: IdPel angka murni jadi int bila dipakai sebagai kunci array
                    $ph = []; $p = [];
                    foreach ($paket as $i => $id) { $ph[] = ":w$i"; $p[":w$i"] = $id; }
                    $scope = pg_scope($u, 'd', 'Unit', $p);
                    $st = $conn->prepare("SELECT d.IdPel, d.JenisShuntrip FROM dil d WHERE d.IdPel IN (" . implode(',', $ph) . ") AND $scope");
                    $st->execute($p);
                    $ada = [];
                    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $ada[(string) trim((string) $r['IdPel'])] = (string) $r['JenisShuntrip'];
                    foreach ($paket as $id) {
                        [$jn, $no] = $baris[$id];
                        if (!array_key_exists($id, $ada)) { $tidak_ada++; $tambah_detail("Baris $no: IdPel $id tidak ada di DIL pada wilayah Anda"); }
                        elseif ($ada[$id] === $jn)        { $sama++; }
                        else                              { $ubah[$id] = $jn; }
                    }
                }

                // perbarui sekaligus per 500 IdPel (satu transaksi; gagal = tidak ada yang berubah)
                $diperbarui = 0;
                if ($ubah) {
                    $conn->beginTransaction();
                    foreach (array_chunk($ubah, 500, true) as $paket) {
                        $case = []; $in = []; $p = [];
                        $i = 0;
                        foreach ($paket as $id => $jn) { $id = (string) $id; $case[] = "WHEN :i$i THEN :j$i"; $in[] = ":w$i"; $p[":i$i"] = $id; $p[":j$i"] = $jn; $p[":w$i"] = $id; $i++; }
                        $scope = pg_scope($u, 'd', 'Unit', $p);
                        $st = $conn->prepare("UPDATE dil d SET d.JenisShuntrip = CASE d.IdPel " . implode(' ', $case) . " ELSE d.JenisShuntrip END, d.WaktuData = NOW()
                                              WHERE d.IdPel IN (" . implode(',', $in) . ") AND $scope");
                        $st->execute($p);
                        $diperbarui += count($paket);
                    }
                    $conn->commit();
                }

                $pesan_sukses = 'Proses selesai: <strong>' . pg_n($diperbarui) . '</strong> IdPel diperbarui'
                              . ($sama ? ', ' . pg_n($sama) . ' sudah bernilai sama (dilewati)' : '')
                              . ($tidak_ada ? ', ' . pg_n($tidak_ada) . ' tidak ada di DIL wilayah Anda' : '')
                              . ($salah ? ', ' . pg_n($salah) . ' baris tidak valid' : '')
                              . ($dobel ? ', ' . pg_n($dobel) . ' dobel di dalam file' : '') . '.';
                if ($diperbarui === 0) { $pesan_info = $pesan_sukses; $pesan_sukses = ''; }
            } catch (Throwable $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                $pesan_error = 'Proses gagal, tidak ada data yang diubah: ' . pg_e($e->getMessage());
            }
        }
    }

    // Post-Redirect-Get supaya refresh tidak mengirim ulang form
    if (!headers_sent()) {
        $_SESSION['pg_flash'] = ['sukses' => $pesan_sukses, 'error' => $pesan_error, 'info' => $pesan_info, 'detail' => $detail_import, 'form_lama' => $form_lama, 'buka' => $buka_modal];
        header('Location: ' . pg_url('shuntrip'));
        exit;
    }
}

// // ---------------------------------------------------------
// // RINGKASAN: jumlah pelanggan per jenis shuntrip di wilayah akun
// // ---------------------------------------------------------
// $ringkas = array_fill_keys(JENIS_SHUNTRIP, 0); $ringkas['_kosong'] = 0; $total_dil = 0;
// if ($unit_siap) {
//     try {
//         $p = []; $scope = pg_scope($u, 'd', 'Unit', $p);
//         $st = $conn->prepare("SELECT d.JenisShuntrip, COUNT(*) FROM dil d WHERE $scope GROUP BY d.JenisShuntrip");
//         $st->execute($p);
//         foreach ($st->fetchAll(PDO::FETCH_NUM) as [$jn, $n]) {
//             $total_dil += (int) $n;
//             if (isset($ringkas[(string) $jn])) $ringkas[(string) $jn] += (int) $n; else $ringkas['_kosong'] += (int) $n;
//         }
//     } catch (PDOException $e) {
//         $pesan_error = trim($pesan_error . ' Gagal memuat ringkasan: ' . pg_e($e->getMessage()));
//     }
// }

$boleh_monitor = pga_boleh('monitor_dil');

include __DIR__ . '/../../templates/penggunaan/shuntrip.php';