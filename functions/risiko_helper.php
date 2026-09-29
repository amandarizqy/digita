<?php
// functions/risiko_helper.php

/**
 * Hitung Keterlambatan Pembayaran
 */
function hitungKeterlambatan($late_count, $lewat_bulan_count) {
    if ($late_count <= 1 && $lewat_bulan_count == 0) return 'RENDAH';
    if ($late_count <= 1 && $lewat_bulan_count > 0) return 'MODERAT';
    if ($late_count >= 2 && $lewat_bulan_count > 0) return 'TINGGI';
    return 'MODERAT';
}

/**
 * Hitung Level Kemungkinan & Kuadran (1-9)
 *
 * PENTING: $kepentingan di sini WAJIB berasal dari kolom LevelKepentingan yang
 * diisi lewat modul input_kepentingan.php (RENDAH/MODERAT/TINGGI). Ini adalah
 * atribut "seberapa penting pelanggan ini secara bisnis", dan TIDAK ADA
 * hubungannya dengan PosisiSR.
 *
 * PosisiSR (dari modul input_survey.php) adalah atribut yang sama sekali
 * berbeda: apakah lokasi sambungan pelanggan saling bergantung/paralel dengan
 * pelanggan lain ('0' = mandiri/tidak tergantung, '1' = tergantung dengan
 * pelanggan lain). PosisiSR TIDAK dipakai untuk menentukan LevelKepentingan,
 * Kuadran, maupun Skala Prioritas — ia hanya data atribut pelanggan tersendiri.
 */
function hitungKemungkinan($kepentingan, $keterlambatan) {
    $kuadran = 0;
    if ($kepentingan == 'RENDAH') {
        if ($keterlambatan == 'RENDAH') $kuadran = 1;
        elseif ($keterlambatan == 'MODERAT') $kuadran = 2;
        elseif ($keterlambatan == 'TINGGI') $kuadran = 3;
    } elseif ($kepentingan == 'MODERAT') {
        if ($keterlambatan == 'RENDAH') $kuadran = 4;
        elseif ($keterlambatan == 'MODERAT') $kuadran = 5;
        elseif ($keterlambatan == 'TINGGI') $kuadran = 6;
    } elseif ($kepentingan == 'TINGGI') {
        if ($keterlambatan == 'RENDAH') $kuadran = 7;
        elseif ($keterlambatan == 'MODERAT') $kuadran = 8;
        elseif ($keterlambatan == 'TINGGI') $kuadran = 9;
    }

    $kemungkinan = '';
    if (in_array($kuadran, [1, 2])) $kemungkinan = 'SANGAT JARANG TERJADI';
    elseif (in_array($kuadran, [3, 4, 5])) $kemungkinan = 'BISA TERJADI';
    elseif (in_array($kuadran, [6, 7, 8, 9])) $kemungkinan = 'SANGAT MUNGKIN TERJADI';

    return ['kuadran' => $kuadran, 'LevelKemungkinan' => $kemungkinan];
}

/**
 * Hitung Nilai Persentil dari Array Data Total Tagihan
 */
function hitungNilaiPercentile(array $data_arr, $percentile) {
    if (empty($data_arr)) return 0;
    sort($data_arr);
    $count = count($data_arr);
    $index = max(0, (int)floor($count * $percentile) - 1);
    return $data_arr[$index] ?? 0;
}

/**
 * Hitung Level Dampak berdasarkan Threshold P10 & P70
 */
function hitungDampak($total_amount, $p10, $p70) {
    if ($total_amount <= $p10) return 'SANGAT RENDAH';
    if ($total_amount > $p70) return 'SANGAT TINGGI';
    return 'MODERAT';
}

/**
 * Hitung Skala Prioritas (1-9)
 */
function hitungSkalaPrioritas($kemungkinan, $dampak) {
    if ($kemungkinan == 'SANGAT JARANG TERJADI') {
        if ($dampak == 'SANGAT RENDAH') return 1;
        if ($dampak == 'MODERAT') return 2;
        if ($dampak == 'SANGAT TINGGI') return 3;
    } elseif ($kemungkinan == 'BISA TERJADI') {
        if ($dampak == 'SANGAT RENDAH') return 4;
        if ($dampak == 'MODERAT') return 5;
        if ($dampak == 'SANGAT TINGGI') return 6;
    } elseif ($kemungkinan == 'SANGAT MUNGKIN TERJADI') {
        if ($dampak == 'SANGAT RENDAH') return 7;
        if ($dampak == 'MODERAT') return 8;
        if ($dampak == 'SANGAT TINGGI') return 9;
    }
    return 0;
}

/**
 * Ambil 4 karakter pertama dari ThBlRek (format YYYYMM) sebagai Periode (YEAR).
 * Dipakai agar nilai kolom `Periode` selalu konsisten diturunkan dari data tagihan,
 * bukan dari tanggal sistem/server saat proses dijalankan.
 */
function getPeriodeFromThBlRek($thBlRek) {
    $thBlRek = trim((string) $thBlRek);
    if (strlen($thBlRek) < 4) return null;
    return substr($thBlRek, 0, 4);
}

/**
 * Cek apakah PosisiSR sudah diisi (nilai '0' atau '1').
 * PosisiSR = apakah lokasi pelanggan saling tergantung/paralel dengan pelanggan
 * lain atau tidak. Diisi lewat modul input_survey.php.
 */
function isPosisiSrTerisi($posisi_sr) {
    return $posisi_sr !== null && trim((string) $posisi_sr) !== '';
}

/**
 * Cek apakah LevelKepentingan sudah diisi dengan nilai valid.
 * LevelKepentingan = seberapa penting pelanggan secara bisnis (RENDAH/MODERAT/
 * TINGGI). Diisi lewat modul input_kepentingan.php, TIDAK diturunkan dari
 * PosisiSR maupun kolom lain.
 */
function isLevelKepentinganTerisi($level_kepentingan) {
    $valid = ['RENDAH', 'MODERAT', 'TINGGI'];
    if ($level_kepentingan === null) return false;
    return in_array(strtoupper(trim((string) $level_kepentingan)), $valid, true);
}

/**
 * Cek kelengkapan data prasyarat sebelum kalkulasi risiko (Kuadran & Skala
 * Prioritas) boleh dijalankan untuk seorang pelanggan.
 *
 * Kalkulasi TIDAK BOLEH dijalankan jika salah satu dari PosisiSR atau
 * LevelKepentingan belum diisi:
 * - PosisiSR wajib ada karena disimpan sebagai atribut pelanggan (walau tidak
 *   dipakai dalam rumus Kuadran/Skala Prioritas).
 * - LevelKepentingan wajib ada karena inilah input utama matriks Kemungkinan
 *   (Kepentingan x Keterlambatan), dan HANYA berasal dari input_kepentingan.php.
 */
function isDataSiapDiproses($posisi_sr, $level_kepentingan) {
    return isPosisiSrTerisi($posisi_sr) && isLevelKepentinganTerisi($level_kepentingan);
}