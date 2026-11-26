<?php
// modules/penggunaan/padam_sdu.php  -  Daftar Padam SDU (HANYA TAMPIL data; tidak ada tindakan/ubah data)
// Sumber: VIEW vw_tiket_putus_sdu_padam (tiket pemutusan SDU yang sudah ditindak: hanya yang PADAM). Tampilan: templates/penggunaan/padam_sdu.php
// Dibatasi wilayah akun (pg_scope) lewat JOIN ke tabel tiket, sama seperti halaman pemutusan.

// ---- PENGATURAN ----
$halaman  = 'padam_sdu';
$judul    = 'Padam SDU';
$tabel    = 'trx_order_putus_sdu';          // tabel tiket (hanya untuk pg_scope wilayah akun)
$view     = 'vw_tiket_putus_sdu_padam';             // view data padam
$pk       = 'TiketPutusSdu';                // kunci tiket pada view & tabel
$monitor  = 'monitor_sdu';                  // halaman tujuan tombol di sudut kanan atas
$boleh_monitor = true;                      // GANTI: samakan dengan pengecekan hak akses tombol Monitoring di halaman lain (mis. isi_pulsa); true = tombol tampil untuk semua akun
$per_hal  = 50;                             // baris per halaman
$urut     = 'v.WaktuTiket DESC, v.`' . $pk . '` DESC';   // terbaru dahulu

// Kolom yang ditampilkan: [label, [kemungkinan nama kolom di view (tanpa membedakan huruf besar/kecil)], tipe]
// tipe: '' teks | 'mono' | 'tiket' | 'nama' | 'angka' rata kanan | 'jenis' (badge JenisTiket) | 'badge' (JenisTindakan). Kolom yang tidak ada di view dilewati.
$kolom = [
    ['Tiket Putus',     [$pk],                         'tiket'],
    ['Jenis',           ['JenisTiket'],                'jenis'],
    ['No. TUL',         ['NoTul', 'NoTulTampil'],      'mono'],
    ['IDPEL',           ['IdPel'],                     'mono'],
    ['Nama Pelanggan',  ['Nama'],                      'nama'],
    ['Goltar',          ['Goltar']],
    ['Daya',            ['Daya'],                      'angka'],
    ['No. Meter',       ['NomorMeter'],                'mono'],
    ['No. HP',          ['NomorHp'],                   'mono'],
    ['Periode',         ['ThblRek'],                   'mono'],
    ['UPI',             ['UnitUpi'],                   'mono'],
    ['AP',              ['UnitAp'],                    'mono'],
    ['UP',              ['UnitUp'],                    'mono'],
    ['Waktu Tiket',     ['WaktuTiket'],                'mono'],
    ['Tindakan',        ['JenisTindakan'],             'badge'],
    ['Tiket Nyala',     ['TiketNyalaSdu'],            'mono'],
    ['Tgl Bayar',       ['TglBayar'],                  'mono'],
];

$u         = pg_unit_akun($conn);
$unit_siap = $u['upi'] !== '';
$pesan_error = '';

// ---- pencarian & halaman (GET) ----
$q   = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 50);
$hal = max(1, (int) ($_GET['hal'] ?? 1));

$daftar = []; $total = 0; $jml_hal = 1; $kolom_tampil = [];

if (!$unit_siap) {
    $pesan_error = 'Unit akun Anda belum diatur, sehingga data tidak bisa ditampilkan. Hubungi administrator.';
} else {
    try {
        $p = []; $sc = pg_scope($u, 't', 'Unit', $p);
        $where = [$sc, "v.JenisTindakan = 'PADAM'"];     // halaman ini hanya menampilkan yang PADAM
        if ($q !== '') {
            $where[] = "(v.IdPel LIKE :q1 OR v.Nama LIKE :q2 OR v.NomorMeter LIKE :q3 OR v.`$pk` LIKE :q4)";
            $like = '%' . $q . '%';
            $p[':q1'] = $p[':q2'] = $p[':q3'] = $p[':q4'] = $like;
        }
        $dari = "FROM `$view` v JOIN `$tabel` t ON t.`$pk` = v.`$pk` WHERE " . implode(' AND ', $where);

        $st = $conn->prepare("SELECT COUNT(*) $dari");
        $st->execute($p);
        $total   = (int) $st->fetchColumn();
        $jml_hal = max(1, (int) ceil($total / $per_hal));
        $hal     = min($hal, $jml_hal);
        $offset  = ($hal - 1) * $per_hal;

        $st = $conn->prepare("SELECT v.* $dari ORDER BY $urut LIMIT " . (int) $per_hal . " OFFSET " . (int) $offset);
        $st->execute($p);
        $daftar = $st->fetchAll(PDO::FETCH_ASSOC);

        // cocokkan nama kolom view (tanpa membedakan huruf besar/kecil)
        $peta = [];
        if ($daftar) foreach (array_keys($daftar[0]) as $nk) $peta[strtolower((string) $nk)] = $nk;
        foreach ($kolom as $k) {
            $nama_kolom = null;
            foreach ($k[1] as $cand) { if (!$daftar || isset($peta[strtolower($cand)])) { $nama_kolom = $daftar ? $peta[strtolower($cand)] : $cand; break; } }
            if ($nama_kolom !== null) $kolom_tampil[] = ['label' => $k[0], 'key' => $nama_kolom, 'tipe' => $k[2] ?? ''];
        }
    } catch (PDOException $e) {
        $pesan_error = 'Gagal memuat data: ' . pg_e($e->getMessage());
    }
}

include __DIR__ . '/../../templates/penggunaan/padam_sdu.php';