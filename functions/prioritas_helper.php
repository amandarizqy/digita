<?php
// functions/prioritas_helper.php

/**
 * Label singkat tiap Skala Prioritas 1-9 (Kemungkinan x Dampak).
 */
function labelSkalaPrioritas($skala) {
    $label = [
        1 => 'Jarang terjadi · Dampak sangat rendah',
        2 => 'Jarang terjadi · Dampak moderat',
        3 => 'Jarang terjadi · Dampak sangat tinggi',
        4 => 'Bisa terjadi · Dampak sangat rendah',
        5 => 'Bisa terjadi · Dampak moderat',
        6 => 'Bisa terjadi · Dampak sangat tinggi',
        7 => 'Sangat mungkin · Dampak sangat rendah',
        8 => 'Sangat mungkin · Dampak moderat',
        9 => 'Sangat mungkin · Dampak sangat tinggi',
    ];
    return $label[(int) $skala] ?? '-';
}

/**
 * Warna Bootstrap per Skala Prioritas (1-3 hijau, 4-6 kuning, 7-9 merah).
 */
function warnaSkalaPrioritas($skala) {
    $skala = (int) $skala;
    if ($skala >= 7) return 'danger';
    if ($skala >= 4) return 'warning';
    return 'success';
}

/**
 * Nama tampilan metode pemeringkatan.
 */
function labelMetodePeringkat($metode) {
    $label = [
        'gabungan' => 'Skor Gabungan',
        'nominal'  => 'Nominal Tagihan Terbesar',
        'telat'    => 'Frekuensi Keterlambatan Terbanyak',
        'tunggak'  => 'Tunggakan / Lewat Bulan Terbanyak',
    ];
    return $label[$metode] ?? (string) $metode;
}

/**
 * Hitung peringkat pelanggan di dalam SATU grup Skala Prioritas.
 *
 * @param array $rows  Tiap elemen: IdPel, total_amount, late_count, lewat_bulan_count
 * @param int   $bobot_nominal / $bobot_telat / $bobot_tunggak  Bobot dalam persen (total 100)
 * @return array  Elemen yang sama + skor (0-100) + peringkat, urut dari peringkat 1
 *
 * Langkah:
 *  1. Tiap indikator dinormalisasi min-max 0..1 DI DALAM grup (jika semua nilai sama -> 0).
 *  2. skor = 100 x (bobot_nominal x n_nominal + bobot_telat x n_telat + bobot_tunggak x n_tunggak)
 *  3. Urut: skor DESC, nominal DESC, telat DESC, tunggakan DESC, IdPel ASC.
 *  4. Peringkat model "competition ranking": data yang identik (skor & ketiga indikator
 *     sama) berbagi peringkat yang sama, peringkat berikutnya melompat (1, 2, 2, 4).
 */
function hitungPeringkatPrioritas(array $rows, $bobot_nominal, $bobot_telat, $bobot_tunggak) {
    if (empty($rows)) return [];

    foreach ($rows as &$r) {
        $r['total_amount']       = round((float) $r['total_amount'], 2);
        $r['late_count']         = (int) $r['late_count'];
        $r['lewat_bulan_count']  = (int) $r['lewat_bulan_count'];
    }
    unset($r);

    $amounts = array_column($rows, 'total_amount');
    $lates   = array_column($rows, 'late_count');
    $lewats  = array_column($rows, 'lewat_bulan_count');

    $min_a = min($amounts); $max_a = max($amounts);
    $min_l = min($lates);   $max_l = max($lates);
    $min_k = min($lewats);  $max_k = max($lewats);

    $norm = function ($v, $min, $max) {
        return ($max > $min) ? (($v - $min) / ($max - $min)) : 0.0;
    };

    $wn = $bobot_nominal / 100;
    $wt = $bobot_telat   / 100;
    $wk = $bobot_tunggak / 100;

    foreach ($rows as &$r) {
        $skor = 100 * (
            $wn * $norm($r['total_amount'],      $min_a, $max_a) +
            $wt * $norm($r['late_count'],        $min_l, $max_l) +
            $wk * $norm($r['lewat_bulan_count'], $min_k, $max_k)
        );
        $r['skor'] = round($skor, 4);
    }
    unset($r);

    usort($rows, function ($a, $b) {
        $cmp = [$b['skor'], $b['total_amount'], $b['late_count'], $b['lewat_bulan_count']]
           <=> [$a['skor'], $a['total_amount'], $a['late_count'], $a['lewat_bulan_count']];
        return $cmp !== 0 ? $cmp : strcmp((string) $a['IdPel'], (string) $b['IdPel']);
    });

    $prev_key = null;
    $rank     = 0;
    foreach ($rows as $i => &$r) {
        $key = $r['skor'] . '|' . $r['total_amount'] . '|' . $r['late_count'] . '|' . $r['lewat_bulan_count'];
        if ($key !== $prev_key) {
            $rank     = $i + 1;
            $prev_key = $key;
        }
        $r['peringkat'] = $rank;
    }
    unset($r);

    return $rows;
}