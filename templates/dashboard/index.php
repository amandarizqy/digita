<?php
/**
 * templates/dashboard/index.php - Dashboard Utama Digita S41 (v2 ringkas & cepat)
 * - 5 query saja, hasil di-cache di sesi 120 detik (paksa muat ulang: &refresh=1)
 * - Query gagal tidak mematikan halaman; SA.KP melihat daftar error di bawah
 * - Cari "SESUAIKAN" untuk bagian yang menebak nama kolom
 */
require_once __DIR__ . '/../../config/database.php'; // auth_check.php melewati database.php untuk dashboard
date_default_timezone_set('Asia/Jakarta'); // aplikasi belum mengatur zona waktu; tanpa ini jam mengikuti php.ini server

$kode_hak = $_SESSION['KodeHak'] ?? '';
$nama     = $_SESSION['NamaPengguna'] ?? ($_SESSION['NamaAkun'] ?? 'Pengguna');
$u_up = $_SESSION['UnitUp'] ?? ''; $u_ap = $_SESSION['UnitAp'] ?? ''; $u_upi = $_SESSION['UnitUpi'] ?? '';

function dash_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function dash_n($n) { return number_format((float)$n, 0, ',', '.'); }
function dash_rp($n) {
    $n = (float)$n;
    if ($n >= 1e9) return 'Rp ' . number_format($n / 1e9, 1, ',', '.') . ' M';
    if ($n >= 1e6) return 'Rp ' . number_format($n / 1e6, 1, ',', '.') . ' jt';
    return 'Rp ' . number_format($n, 0, ',', '.');
}
/** Jalankan query, kembalikan baris pertama (assoc). Error dicatat, bukan dilempar. */
function dash_q($conn, &$err, $label, $sql, $p = []) {
    try { $st = $conn->prepare($sql); $st->execute($p); return $st->fetch(PDO::FETCH_ASSOC) ?: []; }
    catch (Throwable $e) { $err[] = [$label, $e->getMessage()]; return []; }
}
/** Filter wilayah (pola modules/pengadaan/monitoring.php). $c = [kolomUp, kolomAp, kolomUpi] */
function dash_scope($kode, $c, $up, $ap, $upi) {
    if (in_array($kode, ['ML.UP', 'TL.UP', 'SF.UP'], true)) return [" AND {$c[0]} = ?", [$up]];
    if (in_array($kode, ['AM.AP', 'TL.AP', 'SF.AP'], true)) return [" AND {$c[1]} = ?", [$ap]];
    if ($kode !== 'SA.KP' && $upi !== '')                   return [" AND {$c[2]} = ?", [$upi]];
    return ['', []];
}

// ===== DATA (dengan cache sesi) ==================================================================
$kunci = 'v3|' . $kode_hak . '|' . $u_up . '|' . $u_ap . '|' . $u_upi;
$c = $_SESSION['dash_cache'] ?? null;
if ($c && $c['k'] === $kunci && (time() - $c['t']) < 120 && !isset($_GET['refresh'])) {
    $D = $c['d'];
} else {
    $err = [];
    [$s, $p]   = dash_scope($kode_hak, ['UnitUp', 'UnitAp', 'UnitUpi'], $u_up, $u_ap, $u_upi);
    [$s2, $p2] = dash_scope($kode_hak, ['KodeUp_Tujuan', 'KodeAp_Tujuan', 'KodeUpi_Tujuan'], $u_up, $u_ap, $u_upi);
    $awal  = date('Y-m-01'); $akhir = date('Y-m-01', strtotime('+1 month'));

    // SESUAIKAN: "terpasang" = master_barang.IdPel terisi
    $b = dash_q($conn, $err, 'Aset S41',
        "SELECT COUNT(*) AS tot, COALESCE(SUM(IdPel IS NOT NULL AND IdPel <> ''),0) AS pasang
           FROM master_barang WHERE StatusData = 'AKTIF'" . $s, $p);
    // SESUAIKAN: tabel dil belum difilter wilayah
    $d = dash_q($conn, $err, 'DIL',
        "SELECT COUNT(*) AS aktif, COALESCE(SUM(NamaPelanggan IS NULL OR NamaPelanggan = ''),0) AS kosong
           FROM dil WHERE StatusData = 'AKTIF'");
    // SESUAIKAN: tunggakan = TglBayar kosong. Rentang tanggal (bukan DATE_FORMAT) agar indeks TglBayar terpakai
    $l = dash_q($conn, $err, 'Pelunasan',
        "SELECT COALESCE(SUM(CASE WHEN TglBayar IS NULL THEN RpTag ELSE 0 END),0) AS tunggakan,
                COALESCE(SUM(TglBayar >= ? AND TglBayar < ?),0) AS lunas
           FROM pelunasan_ap2t WHERE 1=1" . $s, array_merge([$awal, $akhir], $p));
    // Pemeliharaan: dihitung dari FPK + Berita Acara + detil kondisi lampu (tabel yang dipakai modul Pemeliharaan)
    // SESUAIKAN: "rusak" = LampuIndikator pada BA mengandung kata TIDAK (TIDAK BERFUNGSI)
    $akh_hak = substr($kode_hak, -3);
    $sp = ''; $pp = [];
    if ($akh_hak === '.UP')     { $sp = ' AND p.UnitUp = ?'; $pp = [$u_up]; }
    elseif ($akh_hak === '.AP') { $sp = ' AND p.UnitAp = ?'; $pp = [$u_ap]; }
    // .UI / .VD / SA.KP melihat semua unit, sama seperti aturan di modul Pemeliharaan
    $m = dash_q($conn, $err, 'Pemeliharaan',
        "SELECT COALESCE(SUM(b.TglFba >= ? AND b.TglFba < ?),0) AS bulan_ini,
                COALESCE(SUM(UPPER(bd.LampuIndikator) LIKE '%TIDAK%'),0) AS rusak
           FROM frm_ba b
           JOIN frm_pk p ON p.NoFpk = b.NoFpk
           LEFT JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.StatusPekerjaan = 'PASANG'
          WHERE b.JenisPekerjaan = 'PEMELIHARAAN' AND b.StatusData = 'AKTIF'" . $sp,
        array_merge([$awal, $akhir], $pp));
    // Penghapusan: FPK (JenisPk 'BERHENTI') -> Order -> BA 'BONGKAR'.
    // Status FPK/BA: 'TIDAK' = belum selesai (terbuka / pending), 'AKTIF' = selesai (alat sudah dilepas dari pelanggan).
    // Lampu 'TIDAK' pada BA bongkar = "perlu mutasi" (lihat PGH_LAMPU di penghapusan_helper.php).
    $h = dash_q($conn, $err, 'Penghapusan',
        "SELECT COALESCE(SUM(p.StatusData = 'TIDAK' AND od.NoFpk IS NULL),0) AS belum_order,
                COALESCE(SUM(p.StatusData = 'TIDAK' AND od.NoFpk IS NOT NULL AND b.NoFba IS NULL),0) AS menunggu,
                COALESCE(SUM(p.StatusData = 'TIDAK' AND b.NoFba IS NOT NULL),0) AS pending,
                COALESCE(SUM(b.StatusData = 'AKTIF' AND b.TglFba >= ? AND b.TglFba < ?),0) AS selesai_bln,
                COALESCE(SUM(b.StatusData = 'AKTIF' AND b.TglFba >= ? AND b.TglFba < ? AND bd.LampuIndikator = 'TIDAK'),0) AS lampu_mati
           FROM frm_pk p
           LEFT JOIN frm_order_detil od ON od.NoFpk = p.NoFpk
           LEFT JOIN frm_ba b ON b.NoFpk = p.NoFpk AND b.JenisPekerjaan = 'BONGKAR'
           LEFT JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.StatusPekerjaan = 'BONGKAR'
          WHERE p.JenisPk = 'BERHENTI'" . $sp,
        array_merge([$awal, $akhir, $awal, $akhir], $pp));
    $k = dash_q($conn, $err, 'Pengiriman',
        "SELECT COUNT(*) AS n FROM formulir_pengiriman WHERE StatusPengiriman = 'DIKIRIM'" . $s2, $p2);
    $r = dash_q($conn, $err, 'Prioritas',
        "SELECT COUNT(*) AS n, MAX(Periode) AS periode FROM hasil_prioritas
          WHERE SkalaPrioritas >= 7 AND Periode = (SELECT MAX(Periode) FROM hasil_prioritas)");
    // Catatan: SkalaPrioritas bertipe TINYINT 1-9 (bukan teks). 7-9 = tingkat tertinggi (merah).
    // Periode TIDAK diambil dari periode berjalan, melainkan tahun terbaru yang ada di tabel hasil_prioritas.
    $periode_prio = $r['periode'] ?? null;

    $izin = null;
    try {
        $st = $conn->prepare("SELECT m.UrlRoute, m.NamaMenu, m.Icon FROM pengaturan_menu m JOIN pengaturan_hak_akses h ON m.IdMenu = h.IdMenu
                              WHERE h.KodeHak = :k AND m.StatusData = 'AKTIF' ORDER BY m.Urutan ASC");
        $st->execute([':k' => $kode_hak]);
        $izin = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $mn) {
            $u  = $mn['UrlRoute'];
            $pt = explode('/', trim($u, '/'));
            $mk = (strpos($u, 'modules') === false) ? 'dashboard' : ($pt[1] ?? 'dashboard');
            $izin[$mk] = ['nama' => $mn['NamaMenu'], 'icon' => $mn['Icon']];   // sama persis dengan sidebar
        }
    } catch (Throwable $e) { $err[] = ['Menu hak akses', $e->getMessage()]; }

    $tot = (int)($b['tot'] ?? 0); $pasang = (int)($b['pasang'] ?? 0);
    $D = [
        'tot' => $tot, 'pasang' => $pasang, 'gudang' => max(0, $tot - $pasang),
        'dil' => (int)($d['aktif'] ?? 0), 'dil_kosong' => (int)($d['kosong'] ?? 0),
        'tunggakan' => (float)($l['tunggakan'] ?? 0), 'lunas' => (int)($l['lunas'] ?? 0),
        'rusak' => (int)($m['rusak'] ?? 0), 'pml' => (int)($m['bulan_ini'] ?? 0),
        'kirim' => (int)($k['n'] ?? 0), 'prio' => (int)($r['n'] ?? 0), 'periode_prio' => $periode_prio ?? null,
        'pgh_belum_order' => (int)($h['belum_order'] ?? 0), 'pgh_menunggu' => (int)($h['menunggu'] ?? 0), 'pgh_pending' => (int)($h['pending'] ?? 0),
        'pgh_selesai' => (int)($h['selesai_bln'] ?? 0), 'pgh_lampu_mati' => (int)($h['lampu_mati'] ?? 0),
        'izin' => $izin, 'err' => $err, 'waktu' => time(),
    ];
    $_SESSION['dash_cache'] = ['k' => $kunci, 't' => time(), 'd' => $D];
}
extract($D, EXTR_SKIP);
$pgh_terbuka = $pgh_belum_order + $pgh_menunggu + $pgh_pending;   // FPK penghapusan yang belum selesai

// ===== TAMPILAN ==================================================================================
$modul = [
    'perencanaan'  => ['Perencanaan',      'bi-bullseye',               dash_n($prio),   'prioritas sangat tinggi', !empty($periode_prio) ? 'periode ' . $periode_prio : 'belum ada hasil prioritas',                1],
    'pengadaan'    => ['Pengadaan & Stok', 'bi-box-seam',               dash_n($gudang), 'S41 di stok',            dash_n($kirim) . ' pengiriman berjalan', 2],
    'pemasangan'   => ['Pemasangan',       'bi-tools',                  dash_n($pasang), 'S41 terpasang',          dash_n($gudang) . ' belum terpasang',    3],
    'penggunaan'   => ['Penggunaan',       'bi-people',                 dash_n($dil),    'pelanggan DIL aktif',    dash_n($lunas) . ' pelunasan bulan ini', 4],
    'pemeliharaan' => ['Pemeliharaan',     'bi-wrench-adjustable',      dash_n($pml),    'pemeliharaan bulan ini', dash_n($rusak) . ' relay rusak',         5],
    'penghapusan'  => ['Penghapusan',      'bi-trash3',                 dash_n($pgh_terbuka), 'FPK penghapusan terbuka', dash_n($pgh_selesai) . ' alat dibongkar bulan ini', 6],
    'master'       => ['Master Data',      'bi-database',               'Kelola',        'unit, pengguna, modem',  'data acuan aplikasi',                   0],
    'laporan'      => ['Laporan',          'bi-file-earmark-bar-graph', 'Lihat',         'rekap & ekspor',         'seluruh modul',                         0],
];
function dash_link($m, $kode) {
    if ($m === 'master') return BASE_URL . '/index.php?page=master&sub=' . ($kode === 'AM.UI' ? 'pesan' : 'unit');
    return BASE_URL . '/index.php?page=' . urlencode($m);
}
$kpi = [
    ['Total S41',           dash_n($tot),       dash_n($pasang) . ' terpasang, ' . dash_n($gudang) . ' di stok', 'bi-hdd-network',        'teal'],
    ['Pelanggan DIL aktif', dash_n($dil),       dash_n($dil_kosong) . ' belum berisi nama',                      'bi-person-lines-fill', 'navy'],
    ['Total tunggakan',     dash_rp($tunggakan), 'tagihan belum dibayar',                                        'bi-cash-stack',        'amber'],
    ['S41 relay rusak',     dash_n($rusak),     'perlu reparasi atau mutasi',                                    'bi-exclamation-triangle', 'red'],
];
$todo = array_filter([
    ['bi-truck',                'Pengiriman menunggu diterima',      $kirim,      'pengadaan'],
    ['bi-person-x',             'DIL belum berisi nama pelanggan',   $dil_kosong, 'penggunaan'],
    ['bi-exclamation-triangle', 'S41 dengan relay rusak',            $rusak,      'pemeliharaan'],
    ['bi-graph-up-arrow',       'Pelanggan prioritas sangat tinggi', $prio,       'perencanaan'],
], fn($t) => (int)$t[2] > 0);
$hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$bln  = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$tgl  = $hari[(int)date('w')] . ', ' . date('j') . ' ' . $bln[(int)date('n')] . ' ' . date('Y');
$jam  = (int)date('G');
$salam = $jam < 11 ? 'Selamat pagi' : ($jam < 15 ? 'Selamat siang' : ($jam < 18 ? 'Selamat sore' : 'Selamat malam'));
?>
<style>
.dash{--navy:#0b253a;--teal:#005080;--amber:#f0b000;--red:#c0392b;--ink:#1f2d3a;--mute:#6b7885;--line:#e6eaee}
.dash .head{display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:.5rem;margin-bottom:1.5rem}
.dash .head h3{font-size:1.4rem;font-weight:700;color:var(--ink);margin:0}
.dash .head small,.dash .sub{color:var(--mute);font-size:.8rem}
.dash .box{background:#fff;border-radius:1rem;box-shadow:0 1px 3px rgba(11,37,58,.08);padding:1.1rem 1.25rem;height:100%}
.dash .sec{font-size:.95rem;font-weight:700;color:var(--ink);margin:0 0 .15rem}
.dash .kpi{display:flex;gap:.9rem;align-items:center;border-left:4px solid var(--c)}
.dash .kpi .ic{width:46px;height:46px;border-radius:12px;display:grid;place-items:center;font-size:1.3rem;color:var(--c);background:color-mix(in srgb,var(--c) 12%,#fff);flex:none}
.dash .kpi .v{font-size:1.55rem;font-weight:700;line-height:1.1;color:var(--ink)}
.dash .kpi .l,.dash .kpi .n{font-size:.78rem;color:var(--mute)}
.dash .t-teal{--c:var(--teal)}.dash .t-navy{--c:var(--navy)}.dash .t-amber{--c:var(--amber)}.dash .t-red{--c:var(--red)}
.dash .mod{position:relative;display:flex;flex-direction:column;gap:.6rem;transition:box-shadow .15s}
.dash .mod:hover{box-shadow:0 6px 18px rgba(11,37,58,.14)}
.dash .mod .top{display:flex;justify-content:space-between;align-items:center}
.dash .mod .ic{width:40px;height:40px;border-radius:10px;background:var(--navy);color:#fff;display:grid;place-items:center;font-size:1.15rem}
.dash .mod .tahap{font-size:.72rem;color:var(--mute);border:1px solid var(--line);border-radius:6px;padding:.1rem .45rem}
.dash .mod .nm{font-weight:700;color:var(--ink)}
.dash .mod .big{font-size:1.6rem;font-weight:700;color:var(--teal);line-height:1}
.dash .mod .lb{font-size:.8rem;color:var(--mute)}
.dash .mod .ft{font-size:.78rem;color:var(--ink);border-top:1px dashed var(--line);padding-top:.5rem;margin-top:auto}
.dash .mod.off{opacity:.55}
.dash .row-i{display:flex;align-items:center;gap:.7rem;padding:.6rem 0;border-bottom:1px solid var(--line);font-size:.85rem;color:var(--ink);text-decoration:none}
.dash .row-i:last-child{border-bottom:0}
.dash .row-i .ic{width:32px;height:32px;border-radius:8px;display:grid;place-items:center;background:#f1f4f7;color:var(--teal);flex:none}
.dash .row-i .cnt{margin-left:auto;font-weight:700;background:#fdf0cc;color:#8a6200;border-radius:999px;padding:.1rem .6rem;font-size:.8rem}
.dash .empty{color:var(--mute);font-size:.85rem;padding:1rem 0;text-align:center}
/* Alur penghapusan */
.dash .pgh{position:relative;border-top:3px solid var(--c,var(--teal));transition:box-shadow .15s}
.dash .pgh:hover{box-shadow:0 6px 18px rgba(11,37,58,.14)}
.dash .pgh .n{font-size:1.6rem;font-weight:700;color:var(--ink);line-height:1}
.dash .pgh .t{font-weight:700;color:var(--ink);font-size:.9rem;margin-top:.5rem}
.dash .pgh .d{font-size:.78rem;color:var(--mute)}
</style>

<div class="dash">
  <div class="head">
    <div><h3><?= dash_h($salam) ?>, <?= dash_h($nama) ?></h3><small><?= dash_h($tgl) ?></small></div>
    <small>Diperbarui <?= date('H:i', $waktu) ?> WIB &middot; <a href="<?= BASE_URL ?>/index.php?page=dashboard&refresh=1"><i class="bi bi-arrow-clockwise"></i> Muat ulang</a></small>
  </div>

  <div class="row g-3 mb-4">
    <?php foreach ($kpi as $x): ?>
    <div class="col-xl-3 col-md-6"><div class="box kpi t-<?= $x[4] ?>">
      <div class="ic"><i class="bi <?= $x[3] ?>"></i></div>
      <div><div class="l"><?= dash_h($x[0]) ?></div><div class="v"><?= dash_h($x[1]) ?></div><div class="n"><?= dash_h($x[2]) ?></div></div>
    </div></div>
    <?php endforeach; ?>
  </div>

  <h5 class="sec">Modul</h5><div class="sub mb-2">Tahap 1-6 mengikuti siklus hidup perangkat S41.</div>
  <div class="row g-3 mb-4">
    <?php foreach ($modul as $key => $m):
        if ($izin !== null && !isset($izin[$key])) continue;
        $off = false;
        $ikon = ($izin[$key]['icon'] ?? '') ?: $m[1];   // ikon dari menu DB, cadangan = bawaan dashboard
        $judul = ($izin[$key]['nama'] ?? '') ?: $m[0]; ?>
    <div class="col-xl-3 col-md-6"><div class="box mod <?= $off ? 'off' : '' ?>">
      <div class="top"><div class="ic"><i class="bi <?= dash_h($ikon) ?>"></i></div><?php if ($m[5]): ?><span class="tahap">Tahap <?= $m[5] ?></span><?php endif; ?></div>
      <div class="nm"><?= dash_h($judul) ?></div>
      <div><div class="big"><?= dash_h($m[2]) ?></div><div class="lb"><?= dash_h($m[3]) ?></div></div>
      <div class="ft"><?= dash_h($m[4]) ?></div>
      <?php if (!$off): ?><a href="<?= dash_h(dash_link($key, $kode_hak)) ?>" class="stretched-link" aria-label="Buka <?= dash_h($judul) ?>"></a><?php endif; ?>
    </div></div>
    <?php endforeach; ?>
  </div>

<?php if ($izin === null || isset($izin['penghapusan'])):
      $pgh_eks = in_array($kode_hak, ['SF.AP', 'SF.UP', 'SA.KP'], true);   // yang boleh eksekusi, sama dengan modules/penghapusan/akses.php
      $pgh_tahap = [
          ['Belum masuk order',     $pgh_belum_order, 'FPK menunggu digabung ke order',      $pgh_eks ? 'order_penghapusan'  : 'monitoring_order',  'amber'],
          ['Menunggu pembongkaran', $pgh_menunggu,    'sudah di order, belum ada BA',        $pgh_eks ? 'mutasi_penghapusan' : 'monitoring_mutasi', 'navy'],
          ['BA pending',            $pgh_pending,     'alat belum dilepas dari pelanggan',   $pgh_eks ? 'mutasi_penghapusan' : 'monitoring_mutasi', 'red'],
          ['Selesai bulan ini',     $pgh_selesai,     'alat dilepas dan dinonaktifkan',      'monitoring_mutasi',                                   'teal'],
      ]; ?>

<?php endif; ?>

</div>