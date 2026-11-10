<?php
// modules/pengaturan/bot_handlers.php
// Logika penanganan per JenisBot sesuai tabel bot_akses.
require_once __DIR__ . '/../../functions/xlsx_reader.php';

const BH_CHUNK = 500;

function bh_header($v): string { return preg_replace('/[^a-z0-9]/', '', strtolower((string) $v)); }

function bh_idpel($v): ?string
{
    $v = trim((string) $v);
    if (preg_match('/^\d+(\.0+)?$/', $v))                  $v = preg_replace('/\.0+$/', '', $v);
    elseif (preg_match('/^\d+(\.\d+)?[eE]\+?\d+$/', $v))   $v = sprintf('%.0f', (float) $v);
    else return null;
    if (strlen($v) === 11) $v = '0' . $v;
    return preg_match('/^\d{12}$/', $v) ? $v : null;
}

function bh_bulan($v): ?string
{
    $v = preg_replace('/\.0+$/', '', trim((string) $v));
    return preg_match('/^(19|20)\d{2}(0[1-9]|1[0-2])$/', $v) ? $v : null;
}

function bh_tanggal($v)
{
    $v = trim((string) $v);
    if ($v === '') return null;
    if (preg_match('/^(\d{4})(\d{2})(\d{2})(\.0+)?$/', $v, $m)) return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "$m[1]-$m[2]-$m[3]" : false;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m))       return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "$m[1]-$m[2]-$m[3]" : false;
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m))   return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : false;
    if (is_numeric($v) && $v > 20000 && $v < 80000)             return gmdate('Y-m-d', ((int) $v - 25569) * 86400);
    return false;
}

function bh_waktu($v)
{
    $v = trim((string) $v);
    if ($v === '') return null;
    $jam = '(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?';
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})' . $jam . '/', $v, $m)) { $y = $m[1]; $b = $m[2]; $t = $m[3]; $h = $m[4] ?? 0; $i = $m[5] ?? 0; $s = $m[6] ?? 0; }
    elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})' . $jam . '$#', $v, $m)) { $t = $m[1]; $b = $m[2]; $y = $m[3]; $h = $m[4] ?? 0; $i = $m[5] ?? 0; $s = $m[6] ?? 0; }
    elseif (preg_match('/^(\d{4})(\d{2})(\d{2})(\.0+)?$/', $v, $m)) { $y = $m[1]; $b = $m[2]; $t = $m[3]; $h = $i = $s = 0; }
    elseif (is_numeric($v) && $v > 20000 && $v < 80000) {
        return gmdate('Y-m-d H:i:s', (int) round(((float) $v - 25569) * 86400));
    } else return false;
    if (!checkdate((int) $b, (int) $t, (int) $y) || (int) $h > 23 || (int) $i > 59 || (int) $s > 59) return false;
    return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $y, $b, $t, $h, $i, $s);
}

function bh_angka($v): ?float
{
    $v = trim((string) $v);
    return $v === '' ? 0.0 : (is_numeric($v) ? (float) $v : null);
}

function bh_int($v): ?int
{
    $v = trim((string) $v);
    if ($v === '' || !is_numeric($v) || (float) $v < 0) return null;
    return (int) round((float) $v);
}

function bh_teks($v, int $maks): string
{
    return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $v)), 0, $maks);
}

function bh_norm_sr($v): ?string
{
    $v = preg_replace('/\.0+$/', '', trim((string) $v));
    return $v === '0' || $v === '1' ? $v : null;
}

function bh_norm_kepentingan($v): ?string
{
    $v = strtoupper(trim((string) $v));
    return in_array($v, ['RENDAH', 'MODERAT', 'TINGGI'], true) ? $v : null;
}

function bh_periode($v): ?int
{
    $v = preg_replace('/\.0+$/', '', trim((string) $v));
    return preg_match('/^\d{4}$/', $v) && $v >= 1901 && $v <= 2155 ? (int) $v : null;
}

function bh_akun(array $bot): string
{
    return 'BOT_' . $bot['IdBot'];
}

function bh_peta_unit(PDO $conn, string $upi, string $ap = '', string $up = ''): array
{
    $peta = ['ap' => [], 'up' => []];
    $sql = "SELECT UnitAp FROM master_ap WHERE UnitUpi = ? AND StatusData = 'AKTIF'";
    $prm = [$upi];
    if ($ap !== '') { $sql .= ' AND UnitAp = ?'; $prm[] = $ap; }
    $st = $conn->prepare($sql);
    $st->execute($prm);
    $daftar_ap = array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
    foreach ($daftar_ap as $k) $peta['ap'][strtoupper(trim($k))] = trim($k);
    if (!$daftar_ap) return $peta;

    $sql = "SELECT UnitAp, UnitUp FROM master_up WHERE UnitAp IN (" . implode(',', array_fill(0, count($daftar_ap), '?')) . ") AND StatusData = 'AKTIF'";
    $prm = $daftar_ap;
    if ($up !== '') { $sql .= ' AND UnitUp = ?'; $prm[] = $up; }
    $st = $conn->prepare($sql);
    $st->execute($prm);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $peta['up'][strtoupper(trim((string) $r['UnitUp']))] = ['kode' => trim((string) $r['UnitUp']), 'ap' => trim((string) $r['UnitAp'])];
    }
    return $peta;
}

function bh_hasil(string $status, string $pesan, int $tersimpan = 0, int $ditolak = 0, array $detail = []): array
{
    return ['status' => $status, 'pesan' => $pesan, 'tersimpan' => $tersimpan, 'ditolak' => $ditolak, 'detail' => $detail];
}

function bh_jalankan(PDO $conn, array $bot, string $jenis, string $file, string $ap, string $up): array
{
    @set_time_limit(0);
    @ini_set('memory_limit', '512M');

    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'xlsx') {
        return bh_hasil('gagal', 'Format file harus .xlsx (Excel).');
    }

    try {
        switch ($jenis) {
            case 'PELUNASAN':   return bh_pelunasan($conn, $bot, $file, $ap, $up);
            case 'DIL':         return bh_dil($conn, $bot, $file, $ap, $up);
            case 'TUL':         return bh_tul($conn, $bot, $file, $ap, $up);
            case 'SURVEY':      return bh_kategorisasi($conn, $bot, $file, $ap, $up, [
                                    'kolom' => 'PosisiSR', 'nama_excel' => 'PosisiSR', 'alias' => ['posisisr', 'sr'],
                                    'format' => '0 atau 1', 'norm' => 'bh_norm_sr', 'label' => 'Posisi SR']);
            case 'KEPENTINGAN': return bh_kategorisasi($conn, $bot, $file, $ap, $up, [
                                    'kolom' => 'LevelKepentingan', 'nama_excel' => 'LevelKepentingan', 'alias' => ['levelkepentingan', 'kepentingan'],
                                    'format' => 'RENDAH, MODERAT, atau TINGGI', 'norm' => 'bh_norm_kepentingan', 'label' => 'Level Kepentingan']);
        }
        return bh_hasil('gagal', "Jenis bot '$jenis' tidak dikenal.");
    } catch (Throwable $e) {
        return bh_hasil('gagal', 'Kesalahan bot: ' . $e->getMessage());
    }
}

// Logic pembacaan file Excel (bh_pelunasan, bh_dil, bh_tul, bh_kategorisasi)
// disesuaikan memasukkan data dengan menyertakan UnitUpi, UnitAp, dan UnitUp dari $bot.