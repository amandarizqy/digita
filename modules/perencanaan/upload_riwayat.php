<?php
// modules/perencanaan/upload_riwayat.php
// Unggah XLSX riwayat pelunasan -> tabel pelunasan_ap2t.
// Cepat: file dibaca streaming, disimpan per 500 baris (1 query) dalam 1 transaksi.
// Kunci unik (IdPel, ThBlRek): data yang sudah ada otomatis DIPERBARUI, bukan dobel.
//
// WILAYAH TUJUAN: pengguna halaman ini memiliki Unit UPI. Sebelum mengunggah ia memilih
// Unit AP (di bawah UPI-nya) lalu Unit UP (di bawah AP itu). Ketiga unit tersebut yang
// disimpan pada setiap baris pelunasan_ap2t dan kategorisasi_risiko.
require_once __DIR__ . '/../../functions/xlsx_reader.php';

$riwayat_chunk = 500;

// ---- PETA KOLOM: kolom tabel => nama kolom di Excel (huruf kecil, tanpa spasi). Ubah di sini bila perlu.
$riwayat_peta = [
    'idpel' => ['idpel'],
    'bulan' => ['bulan', 'thblrek'],            // -> ThBlRek
    'tgl'   => ['tgltransaksi', 'tglbayar'],    // -> TglBayar
    'rpbk'  => ['rpbk'],                        // -> RpBK
    'rptag' => ['rptagihan', 'rptag'],          // -> RpTag
];
$riwayat_nama = ['idpel' => 'Idpel', 'bulan' => 'Bulan', 'tgl' => 'TglTransaksi', 'rpbk' => 'RpBK', 'rptag' => 'RpTagihan'];

// ---- Normalisasi nilai ------------------------------------------------------------
if (!function_exists('rw_idpel')) {
function rw_header($v): string { return preg_replace('/[^a-z0-9]/', '', strtolower((string) $v)); }

function rw_idpel($v): ?string
{
    $v = trim((string) $v);
    if (preg_match('/^\d+(\.0+)?$/', $v))            $v = preg_replace('/\.0+$/', '', $v);
    elseif (preg_match('/^\d+(\.\d+)?[eE]\+?\d+$/', $v)) $v = sprintf('%.0f', (float) $v);   // angka ilmiah dari Excel
    else return null;
    if (strlen($v) === 11) $v = '0' . $v;           // nol di depan hilang oleh Excel
    return preg_match('/^\d{12}$/', $v) ? $v : null;
}

function rw_bulan($v): ?string
{
    $v = preg_replace('/\.0+$/', '', trim((string) $v));
    return preg_match('/^(19|20)\d{2}(0[1-9]|1[0-2])$/', $v) ? $v : null;
}

/** null = kosong, false = tidak valid, string = Y-m-d */
function rw_tanggal($v)
{
    $v = trim((string) $v);
    if ($v === '') return null;
    if (preg_match('/^(\d{4})(\d{2})(\d{2})(\.0+)?$/', $v, $m)) return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "$m[1]-$m[2]-$m[3]" : false;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m))       return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "$m[1]-$m[2]-$m[3]" : false;
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m))   return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : false;
    if (is_numeric($v) && $v > 20000 && $v < 80000)             return gmdate('Y-m-d', ((int) $v - 25569) * 86400);   // tanggal serial Excel
    return false;
}

function rw_angka($v): ?float
{
    $v = trim((string) $v);
    return $v === '' ? 0.0 : (is_numeric($v) ? (float) $v : null);
}
}

// ---- 1. Unit akun yang login (UPI) + pilihan wilayah tujuan (AP -> UP) ------------------
$pesan_sukses = $pesan_error = '';
$hasil   = null;     // ringkasan setelah unggah
$ditolak = [];       // contoh baris ditolak (maks 50)

$unitUpi = $_SESSION['UnitUpi'] ?? '';
$unitAp  = $_SESSION['UnitAp']  ?? '';   // bila akun ternyata terikat ke AP/UP, pilihan dibatasi
$unitUp  = $_SESSION['UnitUp']  ?? '';
if (($unitUpi === '' || $unitAp === '' || $unitUp === '') && !empty($_SESSION['NamaAkun'])) {
    try {
        $st = $conn->prepare("SELECT UnitUp, UnitAp, UnitUpi FROM master_pengguna WHERE NamaAkun = ?");
        $st->execute([$_SESSION['NamaAkun']]);
        if ($u = $st->fetch(PDO::FETCH_ASSOC)) {
            $unitUpi = $unitUpi ?: (string) $u['UnitUpi'];
            $unitAp  = $unitAp  ?: (string) $u['UnitAp'];
            $unitUp  = $unitUp  ?: (string) $u['UnitUp'];
        }
    } catch (PDOException $e) { /* ditangani di bawah */ }
}
$unit_siap = $unitUpi !== '';

$nama_upi     = $unitUpi;
$opsi_ap      = [];    // [kode => SingkatanNama]
$peta_unit    = [];    // untuk JS: [ {kode, nama, ups: [ {kode, nama} ]} ]
$opsi_up_ap   = [];    // [kodeAp => [kodeUp => nama]] untuk validasi server
$sel_ap = trim((string) ($_POST['unit_ap'] ?? ''));
$sel_up = trim((string) ($_POST['unit_up'] ?? ''));

if ($unit_siap) {
    try {
        $st = $conn->prepare("SELECT COALESCE(NULLIF(SingkatanNama, ''), UnitUpi) FROM master_upi WHERE UnitUpi = ?");
        $st->execute([$unitUpi]);
        $nama_upi = $st->fetchColumn() ?: $unitUpi;

        // AP di bawah UPI (jika akun terikat AP: hanya AP itu)
        $sql = "SELECT UnitAp, COALESCE(NULLIF(SingkatanNama, ''), UnitAp) AS Nama FROM master_ap WHERE UnitUpi = :upi AND StatusData = 'AKTIF'";
        $prm = [':upi' => $unitUpi];
        if ($unitAp !== '') { $sql .= " AND UnitAp = :ap"; $prm[':ap'] = $unitAp; }
        $st = $conn->prepare($sql . " ORDER BY UnitAp");
        $st->execute($prm);
        $opsi_ap = $st->fetchAll(PDO::FETCH_KEY_PAIR);

        // UP di bawah tiap AP tersebut (jika akun terikat UP: hanya UP itu)
        if ($opsi_ap) {
            $kode_ap = array_map('strval', array_keys($opsi_ap));
            $ph = implode(',', array_fill(0, count($kode_ap), '?'));
            $sql = "SELECT UnitAp, UnitUp, COALESCE(NULLIF(SingkatanNama, ''), UnitUp) AS Nama FROM master_up WHERE UnitAp IN ($ph) AND StatusData = 'AKTIF'";
            $prm = $kode_ap;
            if ($unitUp !== '') { $sql .= " AND UnitUp = ?"; $prm[] = $unitUp; }
            $st = $conn->prepare($sql . " ORDER BY UnitUp");
            $st->execute($prm);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $opsi_up_ap[(string) $r['UnitAp']][(string) $r['UnitUp']] = $r['Nama'];
            }
        }
        foreach ($opsi_ap as $kode => $nama) {
            $ups = [];
            foreach ($opsi_up_ap[(string) $kode] ?? [] as $k_up => $n_up) $ups[] = ['kode' => (string) $k_up, 'nama' => $n_up];
            $peta_unit[] = ['kode' => (string) $kode, 'nama' => $nama, 'ups' => $ups];
        }
    } catch (PDOException $e) {
        $pesan_error = 'Gagal memuat daftar unit: ' . $e->getMessage();
        $unit_siap = false;
    }
}

// ---- 2. Proses unggah -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = $_FILES['file_xlsx'] ?? null;

    if (!$unit_siap) {
        $pesan_error = $pesan_error ?: 'Akun Anda belum memiliki Unit UPI di master pengguna.';
    } elseif (!isset($opsi_ap[$sel_ap]) || !isset($opsi_up_ap[$sel_ap][$sel_up])) {
        $pesan_error = 'Pilih Unit AP dan Unit UP tujuan terlebih dahulu (UP harus berada di bawah AP yang dipilih).';
    } elseif ($f === null || in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        $pesan_error = 'File melebihi batas server (upload_max_filesize ' . ini_get('upload_max_filesize') . ', post_max_size ' . ini_get('post_max_size') . ').';
    } elseif ($f['error'] !== UPLOAD_ERR_OK) {
        $pesan_error = 'Pilih file .xlsx terlebih dahulu.';
    } elseif (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'xlsx' || file_get_contents($f['tmp_name'], false, null, 0, 2) !== 'PK') {
        $pesan_error = 'Format file harus .xlsx (Excel).';
    } else {
        // Unit yang disimpan pada setiap baris: UPI akun + AP & UP pilihan (sudah divalidasi di atas)
        $tujuanUpi = $unitUpi;
        $tujuanAp  = (string) $sel_ap;
        $tujuanUp  = (string) $sel_up;
        $label_tujuan = $nama_upi . ' › ' . $opsi_ap[$sel_ap] . ' › ' . $opsi_up_ap[$sel_ap][$sel_up];

        set_time_limit(300);
        $t0 = microtime(true);
        $baris = [];            // dedup per IdPel|ThBlRek
        $kat   = [];            // unik per IdPel|Periode (tahun) -> kategorisasi_risiko
        $katBaru = 0;
        $dilBaru = 0;           // pelanggan baru yang otomatis masuk DIL
        $dibaca = $tolak = $duplikat = 0;
        $peta = null;

        try {
            foreach (xlsx_baris($f['tmp_name']) as $no => $sel) {
                // a. cari baris judul kolom
                if ($peta === null) {
                    if ($no > 20) throw new RuntimeException('Baris judul kolom tidak ditemukan pada 20 baris pertama.');
                    $kol = [];
                    foreach ($sel as $huruf => $nilai) $kol[rw_header($nilai)] = $huruf;
                    if (!isset($kol['idpel'])) continue;

                    $peta = [];
                    foreach ($riwayat_peta as $field => $alias) {
                        foreach ($alias as $a) if (isset($kol[$a])) { $peta[$field] = $kol[$a]; break; }
                    }
                    $kurang = array_diff(array_keys($riwayat_peta), array_keys($peta));
                    if ($kurang) throw new RuntimeException('Kolom wajib tidak ditemukan: ' . implode(', ', array_map(fn($k) => $riwayat_nama[$k], $kurang)) . '.');
                    continue;
                }

                // b. baris data
                if (trim(implode('', $sel)) === '') continue;
                $dibaca++;
                $g = fn($field) => $sel[$peta[$field]] ?? '';

                $id  = rw_idpel($g('idpel'));
                $bln = rw_bulan($g('bulan'));
                $tgl = rw_tanggal($g('tgl'));
                $bk  = rw_angka($g('rpbk'));
                $tagRaw = trim((string) $g('rptag'));
                $tag = $tagRaw === '' ? null : rw_angka($tagRaw);

                $alasan = $id === null ? 'IdPel harus 12 digit angka'
                        : ($bln === null ? 'Bulan harus format YYYYMM'
                        : ($tgl === false ? 'TglTransaksi tidak valid'
                        : ($bk === null ? 'RpBK bukan angka'
                        : ($tag === null ? 'RpTagihan kosong / bukan angka' : null))));
                if ($alasan !== null) {
                    $tolak++;
                    if (count($ditolak) < 50) $ditolak[] = [$no, (string) $g('idpel'), $alasan];
                    continue;
                }

                $kunci = $id . '|' . $bln;
                if (isset($baris[$kunci])) $duplikat++;          // baris terakhir yang dipakai
                $baris[$kunci] = [$id, $bln, $tgl, (int) round($bk), (int) round($tag)];
                $thn = (int) substr($bln, 0, 4);                 // Periode = tahun dari Bulan
                $kat[$id . '|' . $thn] = [$id, $thn];
            }
            if ($peta === null) throw new RuntimeException('Baris judul kolom (Idpel, Bulan, ...) tidak ditemukan.');

            // c. simpan batch
            if ($baris) {
                $head = "INSERT INTO pelunasan_ap2t (IdPel, ThBlRek, TglBayar, RpBK, RpTag, UnitUp, UnitAp, UnitUpi) VALUES ";
                $tail = " ON DUPLICATE KEY UPDATE TglBayar = VALUES(TglBayar), RpBK = VALUES(RpBK), RpTag = VALUES(RpTag),
                          UnitUp = VALUES(UnitUp), UnitAp = VALUES(UnitAp), UnitUpi = VALUES(UnitUpi)";
                $conn->beginTransaction();
                foreach (array_chunk(array_values($baris), $riwayat_chunk) as $paket) {
                    $stmt  = $conn->prepare($head . implode(',', array_fill(0, count($paket), '(?,?,?,?,?,?,?,?)')) . $tail);
                    $param = [];
                    foreach ($paket as $b) array_push($param, $b[0], $b[1], $b[2], $b[3], $b[4], $tujuanUp, $tujuanAp, $tujuanUpi);
                    $stmt->execute($param);
                }

                // d0. DIL: daftarkan setiap IdPel di file (StatusData otomatis 'AKTIF'). Dilakukan SEBELUM
                //     kategorisasi_risiko agar aman bila ada foreign key ke dil. INSERT IGNORE: pelanggan yang sudah
                //     ada di DIL tidak diubah (status AKTIF/TIDAK dan nama yang sudah ada dipertahankan).
                //     NamaPelanggan tidak ada di file riwayat -> kosong; lengkapi lewat Penggunaan > Input DIL.
                $idpel_unik = array_values(array_unique(array_column($baris, 0)));
                foreach (array_chunk($idpel_unik, $riwayat_chunk) as $paket) {
                    $stmt = $conn->prepare("INSERT IGNORE INTO dil (IdPel, NamaPelanggan, StatusData, WaktuData) VALUES "
                          . implode(',', array_fill(0, count($paket), "(?, '', 'AKTIF', NOW())")));
                    $stmt->execute($paket);
                    $dilBaru += $stmt->rowCount();
                }

                // d. kategorisasi_risiko: tambah (IdPel, Periode) yang belum ada. Yang sudah ada TIDAK diubah
                //    (Posisi SR, Kepentingan, dan hasil klasifikasinya aman). INSERT IGNORE: kunci ganda dilewati tanpa error.
                foreach (array_chunk(array_values($kat), $riwayat_chunk) as $paket) {
                    $stmt  = $conn->prepare("INSERT IGNORE INTO kategorisasi_risiko (IdPel, Periode, UnitUp, UnitAp, UnitUpi) VALUES "
                           . implode(',', array_fill(0, count($paket), '(?,?,?,?,?)')));
                    $param = [];
                    foreach ($paket as $k) array_push($param, $k[0], $k[1], $tujuanUp, $tujuanAp, $tujuanUpi);
                    $stmt->execute($param);
                    $katBaru += $stmt->rowCount();
                }
                $conn->commit();
                // daftar tahun periode ikut diperbarui (cache lama & baru)
                unset($_SESSION['pr_periode_data'], $_SESSION['pr_periode_data_v2']);
            }

            $hasil = ['dibaca' => $dibaca, 'tersimpan' => count($baris), 'duplikat' => $duplikat, 'ditolak' => $tolak,
                      'durasi' => round(microtime(true) - $t0, 2), 'nama' => $f['name'], 'kat_baru' => $katBaru, 'dil_baru' => $dilBaru, 'kat_total' => count($kat),
                      'wilayah' => $label_tujuan];
            $pesan_sukses = count($baris) > 0
                ? 'Berhasil memproses ' . number_format(count($baris), 0, ',', '.') . ' baris riwayat pelunasan untuk wilayah ' . $label_tujuan . '; '
                  . number_format($katBaru, 0, ',', '.') . ' pelanggan-periode baru ditambahkan ke kategorisasi_risiko, '
                  . number_format($dilBaru, 0, ',', '.') . ' pelanggan baru ditambahkan ke DIL (status AKTIF).'
                : '';
            if (!$baris) $pesan_error = 'Tidak ada baris valid yang bisa disimpan.';
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $pesan_error = 'Gagal memproses file: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../templates/perencanaan/upload_riwayat.php';