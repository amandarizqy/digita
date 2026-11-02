<?php
// modules/penggunaan/upload_tul.php
// Unggah XLSX "Daftar TUL VI-01 yang belum lunas" (laporan AP2T) -> tabel tul_601.
// Pola sama dengan perencanaan/upload_riwayat.php: file dibaca streaming, disimpan per 500 baris
// dalam 1 transaksi, dan kunci unik (NoTul) yang sudah ada otomatis DIPERBARUI, bukan dobel.
//
// Struktur tul_601:
//   NoTul VARCHAR(30) PK | WaktuCetak DATETIME | IdPel CHAR(12) FK->master_barang.IdPel | ThBlRekAwal CHAR(6) | ThBlRekAkhir CHAR(6)
//   Lembar INT | RpTag INT | StandBaca INT | TglBaca DATE | NomorPengirim VARCHAR(20) | NamaAkun VARCHAR(20)
//   UnitUp | UnitAp | UnitUpi | StatusData ENUM('AKTIF','TIDAK') | WaktuData TIMESTAMP
//
// CATATAN FK: IdPel hanya boleh berisi pelanggan yang sudah punya perangkat terpasang (ada di master_barang.IdPel).
//   IdPel yang belum ada di master_barang DITOLAK dengan alasan jelas (bukan ditambahkan ke DIL).
//
// WILAYAH TUJUAN: sama seperti upload riwayat — UPI dari akun, AP & UP dipilih pengguna sebelum mengunggah.
require_once __DIR__ . '/../../functions/xlsx_reader.php';

$tul_chunk = 500;

// ---- PETA KOLOM: field => alias judul kolom Excel (huruf kecil, tanpa spasi/titik/tanda baca). Ubah di sini bila perlu.
$tul_peta_wajib = [
    'notul'  => ['nomertulvi01', 'nomortulvi01', 'nomertul', 'nomortul', 'notul'],             // -> NoTul
    'cetak'  => ['tglpencetakantulvi01', 'tglpencetakan', 'waktucetak'],                       // -> WaktuCetak
    'idpel'  => ['idpelanggan', 'idpel'],                                                      // -> IdPel
    'awal'   => ['bulanrekeningawal', 'thblrekawal'],                                          // -> ThBlRekAwal
    'akhir'  => ['bulanrekeningakhir', 'thblrekakhir'],                                        // -> ThBlRekAkhir
    'lembar' => ['jumlahlembar', 'lembar'],                                                    // -> Lembar
    'rptag'  => ['rptagihan', 'rptag'],                                                        // -> RpTag
];
$tul_peta_opsi = [                                                                             // boleh tidak ada di file
    'nama'   => ['namapelanggan', 'nama'],                                                     // -> dil.NamaPelanggan (bila masih kosong)
    'stand'  => ['standpemutusan', 'standbaca'],                                               // -> StandBaca
    'tglput' => ['tglpelaksanaanputus', 'tglbaca'],                                            // -> TglBaca
];
$tul_nama = ['notul' => 'Nomer Tul VI-01', 'cetak' => 'Tgl Pencetakan Tul VI-01', 'idpel' => 'Id Pelanggan',
             'awal' => 'Bulan Rekening Awal', 'akhir' => 'Bulan Rekening Akhir', 'lembar' => 'Jumlah Lembar', 'rptag' => 'Rp. Tagihan'];
$tul_int_max = 2147483647;   // batas INT(11) bertanda; lebih dari ini membuat seluruh batch gagal di MySQL strict

// ---- Normalisasi nilai ------------------------------------------------------------
if (!function_exists('tul_header')) {
    function tul_header($v): string { return preg_replace('/[^a-z0-9]/', '', strtolower((string) $v)); }

    function tul_idpel($v): ?string
    {
        $v = trim((string) $v);
        if (preg_match('/^\d+(\.0+)?$/', $v))                  $v = preg_replace('/\.0+$/', '', $v);
        elseif (preg_match('/^\d+(\.\d+)?[eE]\+?\d+$/', $v))   $v = sprintf('%.0f', (float) $v);   // angka ilmiah dari Excel
        else return null;
        if (strlen($v) === 11) $v = '0' . $v;                  // nol di depan hilang oleh Excel
        return preg_match('/^\d{12}$/', $v) ? $v : null;
    }

    function tul_bulan($v): ?string
    {
        $v = preg_replace('/\.0+$/', '', trim((string) $v));
        return preg_match('/^(19|20)\d{2}(0[1-9]|1[0-2])$/', $v) ? $v : null;
    }

    /** null = kosong, false = tidak valid, string = 'Y-m-d H:i:s'. Terima 'Y-m-d H:i:s', 'd/m/Y H:i', YYYYMMDD, serial Excel. */
    function tul_waktu($v)
    {
        $v = trim((string) $v);
        if ($v === '') return null;
        $jam = '(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?';
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})' . $jam . '/', $v, $m)) { $y = $m[1]; $b = $m[2]; $t = $m[3]; $h = $m[4] ?? 0; $i = $m[5] ?? 0; $s = $m[6] ?? 0; }
        elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})' . $jam . '$#', $v, $m)) { $t = $m[1]; $b = $m[2]; $y = $m[3]; $h = $m[4] ?? 0; $i = $m[5] ?? 0; $s = $m[6] ?? 0; }
        elseif (preg_match('/^(\d{4})(\d{2})(\d{2})(\.0+)?$/', $v, $m)) { $y = $m[1]; $b = $m[2]; $t = $m[3]; $h = $i = $s = 0; }
        elseif (is_numeric($v) && $v > 20000 && $v < 80000) {            // tanggal serial Excel (pecahan = jam)
            $detik = (int) round(((float) $v - 25569) * 86400);
            return gmdate('Y-m-d H:i:s', $detik);
        } else return false;
        if (!checkdate((int) $b, (int) $t, (int) $y) || (int) $h > 23 || (int) $i > 59 || (int) $s > 59) return false;
        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $y, $b, $t, $h, $i, $s);
    }

    /** Bilangan bulat >= 0. null = kosong / bukan angka. */
    function tul_int($v): ?int
    {
        $v = trim((string) $v);
        if ($v === '' || !is_numeric($v) || (float) $v < 0) return null;
        return (int) round((float) $v);
    }
}

// ---- 1. Unit akun yang login (UPI) + pilihan wilayah tujuan (AP -> UP) ------------------
$pesan_sukses = $pesan_error = '';
$hasil   = null;     // ringkasan setelah unggah
$ditolak = [];       // contoh baris ditolak (maks 50): [no, idpel, notul, alasan]

if (empty($_SESSION['pg_csrf'])) $_SESSION['pg_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['pg_csrf'];

$u         = pg_unit_akun($conn);
$unitUpi   = $u['upi'];
$unit_siap = $unitUpi !== '';

$nama_upi   = $unitUpi;
$opsi_ap    = [];   // [kode => SingkatanNama]
$peta_unit  = [];   // untuk JS dropdown bertingkat
$opsi_up_ap = [];   // [kodeAp => [kodeUp => nama]] untuk validasi server
$sel_ap = trim((string) ($_POST['unit_ap'] ?? ''));
$sel_up = trim((string) ($_POST['unit_up'] ?? ''));

if ($unit_siap) {
    try {
        $w = pg_opsi_wilayah($conn, $u);
        $nama_upi = $w['nama_upi']; $opsi_ap = $w['opsi_ap']; $opsi_up_ap = $w['opsi_up_ap']; $peta_unit = $w['peta'];
    } catch (PDOException $e) {
        $pesan_error = 'Gagal memuat daftar unit: ' . $e->getMessage();
        $unit_siap = false;
    }
}

// ---- 2. Proses unggah -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = $_FILES['file_xlsx'] ?? null;

    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $pesan_error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (!$unit_siap) {
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
        $baris = [];            // dedup per NoTul: [no_excel, NoTul, WaktuCetak, IdPel, Awal, Akhir, Lembar, RpTag, Stand, TglBaca]
        $nama_dil = [];         // IdPel => NamaPelanggan (dari file) untuk melengkapi DIL
        $tanpaBarang = 0;       // baris ditolak: IdPel belum ada di master_barang
        $idTanpaBarang = [];    // IdPel unik yang belum ada di master_barang
        $dibaca = $tolak = $duplikat = $bentrok = 0;
        $peta = null;

        try {
            foreach (xlsx_baris($f['tmp_name']) as $no => $sel) {
                // a. cari baris judul kolom (laporan AP2T punya kop surat + judul 2 tingkat; yang dipakai baris berisi "Id Pelanggan")
                if ($peta === null) {
                    if ($no > 20) throw new RuntimeException('Baris judul kolom tidak ditemukan pada 20 baris pertama.');
                    $kol = [];
                    foreach ($sel as $huruf => $nilai) $kol[tul_header($nilai)] = $huruf;
                    if (!isset($kol['idpelanggan']) && !isset($kol['idpel'])) continue;

                    $peta = [];
                    foreach ($tul_peta_wajib + $tul_peta_opsi as $field => $alias) {
                        foreach ($alias as $a) if (isset($kol[$a])) { $peta[$field] = $kol[$a]; break; }
                    }
                    $kurang = array_diff(array_keys($tul_peta_wajib), array_keys($peta));
                    if ($kurang) throw new RuntimeException('Kolom wajib tidak ditemukan: ' . implode(', ', array_map(fn($k) => $tul_nama[$k], $kurang)) . '.');
                    continue;
                }

                // b. baris data
                if (trim(implode('', $sel)) === '') continue;
                if (strtoupper(trim((string) reset($sel))) === 'TOTAL') continue;   // baris jumlah di akhir laporan
                $dibaca++;
                $g = fn($field) => isset($peta[$field]) ? ($sel[$peta[$field]] ?? '') : '';

                $notul = trim((string) $g('notul'));
                $id    = tul_idpel($g('idpel'));
                $cetak = tul_waktu($g('cetak'));
                $awal  = tul_bulan($g('awal'));
                $akhir = tul_bulan($g('akhir'));
                $lbr   = tul_int($g('lembar'));
                $tag   = tul_int($g('rptag'));
                $stand = tul_int($g('stand'));                  // opsional: tidak valid / kosong -> NULL
                $tglp  = tul_waktu($g('tglput'));               // opsional
                $tglp  = is_string($tglp) ? substr($tglp, 0, 10) : null;

                $alasan = ($notul === '' || mb_strlen($notul) > 30) ? 'Nomer Tul kosong atau lebih dari 30 karakter'
                        : ($id === null ? 'IdPel harus 12 digit angka'
                        : (!is_string($cetak) ? 'Tgl Pencetakan kosong / tidak valid'
                        : ($awal === null ? 'Bulan Rekening Awal harus format YYYYMM'
                        : ($akhir === null ? 'Bulan Rekening Akhir harus format YYYYMM'
                        : ($awal > $akhir ? 'Bulan Rekening Awal lebih besar dari Akhir'
                        : ($lbr === null || $lbr < 1 ? 'Jumlah Lembar kosong / bukan angka'
                        : ($tag === null ? 'Rp. Tagihan kosong / bukan angka'
                        : (max($lbr, $tag, (int) $stand) > $tul_int_max ? 'Angka melebihi batas kolom (INT)' : null))))))));
                if ($alasan !== null) {
                    $tolak++;
                    if (count($ditolak) < 50) $ditolak[] = [$no, (string) $g('idpel'), $notul, $alasan];
                    continue;
                }

                if (isset($baris[$notul])) $duplikat++;          // baris terakhir yang dipakai
                $baris[$notul] = [$no, $notul, $cetak, $id, $awal, $akhir, $lbr, $tag, $stand, $tglp];
                $nm = trim(preg_replace('/\s+/', ' ', (string) $g('nama')));
                if ($nm !== '') $nama_dil[$id] = mb_substr($nm, 0, 50);
            }
            if ($peta === null) throw new RuntimeException('Baris judul kolom (Id Pelanggan, Nomer Tul VI-01, ...) tidak ditemukan.');

            // c. simpan batch
            if ($baris) {
                $conn->beginTransaction();

                // c0. NoTul adalah kunci tunggal: tolak NoTul yang sudah dimiliki wilayah LAIN supaya tidak menimpa data unit lain.
                foreach (array_chunk(array_keys($baris), $tul_chunk) as $paket) {
                    $st = $conn->prepare('SELECT NoTul, UnitUp, UnitAp, UnitUpi FROM tul_601 WHERE NoTul IN (' . implode(',', array_fill(0, count($paket), '?')) . ')');
                    $st->execute($paket);
                    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $ada) {
                        if ((string) $ada['UnitUp'] === $tujuanUp && (string) $ada['UnitAp'] === $tujuanAp && (string) $ada['UnitUpi'] === $tujuanUpi) continue;
                        $b = $baris[$ada['NoTul']];
                        unset($baris[$ada['NoTul']]);
                        $bentrok++; $tolak++;
                        if (count($ditolak) < 50) $ditolak[] = [$b[0], $b[3], $b[1], 'Nomer Tul sudah tercatat pada wilayah lain'];
                    }
                }

                // c1. FK tul_601.IdPel -> master_barang.IdPel: hanya IdPel yang sudah punya perangkat terpasang yang boleh masuk.
                $ada_barang = [];
                $idpel_unik = array_values(array_unique(array_column($baris, 3)));
                foreach (array_chunk($idpel_unik, $tul_chunk) as $paket) {
                    $st = $conn->prepare('SELECT DISTINCT IdPel FROM master_barang WHERE IdPel IN (' . implode(',', array_fill(0, count($paket), '?')) . ')');
                    $st->execute($paket);
                    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $idp) $ada_barang[(string) $idp] = true;
                }
                foreach ($baris as $notul => $b) {
                    if (isset($ada_barang[$b[3]])) continue;
                    unset($baris[$notul]);
                    $tanpaBarang++; $tolak++; $idTanpaBarang[$b[3]] = true;
                    if (count($ditolak) < 50) $ditolak[] = [$b[0], $b[3], $b[1], 'IdPel belum terpasang perangkat (tidak ada di master_barang)'];
                }

                // c1b. Nama dari file hanya melengkapi DIL yang SUDAH ada dan namanya masih kosong (tidak menambah pelanggan baru).
                $nama_pakai = array_intersect_key($nama_dil, $ada_barang);
                foreach (array_chunk($nama_pakai, $tul_chunk, true) as $paket) {
                    $case = $param = [];
                    foreach ($paket as $id => $nm) { $case[] = 'WHEN ? THEN ?'; array_push($param, (string) $id, $nm); }
                    $in = implode(',', array_fill(0, count($paket), '?'));
                    foreach (array_keys($paket) as $id) $param[] = (string) $id;
                    $conn->prepare("UPDATE dil SET NamaPelanggan = CASE IdPel " . implode(' ', $case) . " END WHERE IdPel IN ($in) AND (NamaPelanggan IS NULL OR NamaPelanggan = '')")
                         ->execute($param);
                }

                // c2. tul_601. StatusData baris lama TIDAK diubah; StandBaca/TglBaca lama dipertahankan bila file baru kosong;
                //     NomorPengirim tidak disentuh (diisi proses lain).
                $namaAkunInput = mb_substr((string) ($_SESSION['NamaAkun'] ?? ''), 0, 20);
                $head = "INSERT INTO tul_601 (NoTul, WaktuCetak, IdPel, ThBlRekAwal, ThBlRekAkhir, Lembar, RpTag, StandBaca, TglBaca, NamaAkun, UnitUp, UnitAp, UnitUpi, StatusData, WaktuData) VALUES ";
                $tail = " ON DUPLICATE KEY UPDATE WaktuCetak = VALUES(WaktuCetak), IdPel = VALUES(IdPel), ThBlRekAwal = VALUES(ThBlRekAwal), ThBlRekAkhir = VALUES(ThBlRekAkhir),
                          Lembar = VALUES(Lembar), RpTag = VALUES(RpTag), StandBaca = COALESCE(VALUES(StandBaca), StandBaca), TglBaca = COALESCE(VALUES(TglBaca), TglBaca),
                          NamaAkun = VALUES(NamaAkun), UnitUp = VALUES(UnitUp), UnitAp = VALUES(UnitAp), UnitUpi = VALUES(UnitUpi), WaktuData = VALUES(WaktuData)";
                foreach (array_chunk(array_values($baris), $tul_chunk) as $paket) {
                    $stmt  = $conn->prepare($head . implode(',', array_fill(0, count($paket), "(?,?,?,?,?,?,?,?,?,?,?,?,?,'AKTIF',NOW())")) . $tail);
                    $param = [];
                    foreach ($paket as $b) array_push($param, $b[1], $b[2], $b[3], $b[4], $b[5], $b[6], $b[7], $b[8], $b[9], $namaAkunInput, $tujuanUp, $tujuanAp, $tujuanUpi);
                    $stmt->execute($param);
                }
                $conn->commit();
            }

            $hasil = ['dibaca' => $dibaca, 'tersimpan' => count($baris), 'duplikat' => $duplikat, 'ditolak' => $tolak, 'bentrok' => $bentrok,
                      'tanpa_barang' => $tanpaBarang, 'id_tanpa_barang' => count($idTanpaBarang),
                      'durasi' => round(microtime(true) - $t0, 2), 'nama' => $f['name'], 'wilayah' => $label_tujuan];
            if ($baris) {
                $pesan_sukses = 'Berhasil memproses ' . number_format(count($baris), 0, ',', '.') . ' baris TUL VI-01 untuk wilayah ' . $label_tujuan . '.'
                              . ($tanpaBarang ? ' ' . number_format($tanpaBarang, 0, ',', '.') . ' baris dilewati karena IdPel belum terpasang perangkat (lihat tabel Baris Ditolak).' : '');
            } else {
                $pesan_error = $tanpaBarang
                    ? 'Tidak ada baris yang bisa disimpan: ' . number_format($tanpaBarang, 0, ',', '.') . ' baris ditolak karena IdPel belum terpasang perangkat (tidak ada di master_barang).'
                    : 'Tidak ada baris valid yang bisa disimpan.';
            }
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $pesan_error = 'Gagal memproses file: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../templates/penggunaan/upload_tul.php';