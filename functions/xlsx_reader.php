<?php
// functions/xlsx_reader.php
// Pembaca XLSX streaming (tanpa Composer). Butuh ekstensi PHP: zip + xmlreader (bawaan XAMPP).
// Memori kecil walau barisnya puluhan ribu karena sheet dibaca sebagai stream.

/**
 * Baca sheet pertama. Menghasilkan [nomor_baris_excel => ['A' => nilai, 'C' => nilai, ...]].
 * Nilai selalu string mentah dari XLSX (angka tetap presisi penuh, tanggal berupa angka serial).
 */
function xlsx_baris(string $berkas, bool $paksa_murni = false): Generator
{
    // Tanpa ekstensi zip/xmlreader (mis. server kantor), pakai pembaca murni PHP (hanya butuh zlib).
    if ($paksa_murni || !class_exists('ZipArchive') || !class_exists('XMLReader')) {
        yield from xlsx_baris_murni($berkas);
        return;
    }
    $zip = new ZipArchive();
    if ($zip->open($berkas) !== true) {
        throw new RuntimeException('File bukan XLSX yang valid.');
    }

    // Sheet pertama
    $sheet = 'xl/worksheets/sheet1.xml';
    if ($zip->locateName($sheet) === false) {
        $sheet = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/[^/]+\.xml$#', $n)) { $sheet = $n; break; }
        }
        if ($sheet === null) throw new RuntimeException('Sheet tidak ditemukan di dalam file.');
    }

    // Shared strings
    $ss = [];
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml !== false) {
        $r = new XMLReader();
        $r->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT);
        while ($r->read()) {
            if ($r->nodeType === XMLReader::ELEMENT && $r->name === 'si') {
                $ss[] = html_entity_decode(strip_tags($r->readInnerXml()), ENT_XML1 | ENT_QUOTES, 'UTF-8');
            }
        }
        $r->close();
    }
    $zip->close();

    // Sheet (stream)
    $r = new XMLReader();
    if (!$r->open('zip://' . $berkas . '#' . $sheet, null, LIBXML_NONET | LIBXML_COMPACT)) {
        throw new RuntimeException('Gagal membuka sheet pertama.');
    }
    while ($r->read()) {
        if ($r->nodeType !== XMLReader::ELEMENT || $r->name !== 'row') continue;

        $no    = (int) $r->getAttribute('r');
        $sel   = [];
        if (!$r->isEmptyElement) {
            $dRow = $r->depth;
            while ($r->read() && !($r->nodeType === XMLReader::END_ELEMENT && $r->name === 'row' && $r->depth === $dRow)) {
                if ($r->nodeType !== XMLReader::ELEMENT || $r->name !== 'c') continue;

                $huruf = preg_replace('/\d+/', '', (string) $r->getAttribute('r'));
                $tipe  = $r->getAttribute('t');
                $nilai = null;
                if (!$r->isEmptyElement) {
                    $dCell = $r->depth;
                    while ($r->read() && !($r->nodeType === XMLReader::END_ELEMENT && $r->name === 'c' && $r->depth === $dCell)) {
                        if ($r->nodeType !== XMLReader::ELEMENT) continue;
                        if ($r->name === 'v')      $nilai = $r->readString();
                        elseif ($r->name === 't')  $nilai = ($nilai ?? '') . $r->readString();   // inlineStr
                    }
                }
                if ($nilai !== null) {
                    $sel[$huruf] = $tipe === 's' ? ($ss[(int) $nilai] ?? '') : $nilai;
                }
            }
        }
        yield $no => $sel;
    }
    $r->close();
}


// ---------------------------------------------------------------------------------
// JALUR CADANGAN: tanpa ZipArchive & XMLReader. Hanya butuh fungsi gzinflate (zlib).
// ---------------------------------------------------------------------------------

/**
 * Cari entri di dalam ZIP tanpa memuat seluruh file ke memori (hanya membaca direktori pusat).
 * $nama berupa string, atau callable(nama): bool untuk mencari pola.
 * @return array{method:int,csize:int,mulai:int}|null
 */
function xlsx_zip_cari($fh, int $ukuran, $nama): ?array
{
    $ekor = min($ukuran, 70000);
    fseek($fh, $ukuran - $ekor);
    $buf = (string) fread($fh, $ekor);
    $pos = strrpos($buf, "PK\x05\x06");
    if ($pos === false) throw new RuntimeException('File bukan XLSX yang valid.');
    $e = unpack('vdisk/vcd_disk/ventries_disk/ventries/Vcd_size/Vcd_offset', substr($buf, $pos + 4, 16));

    fseek($fh, $e['cd_offset']);
    $cd = (string) fread($fh, $e['cd_size']);
    $off = 0;
    for ($i = 0; $i < $e['entries']; $i++) {
        $h = unpack('Vsig/vvm/vvn/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnl/vel/vcl/vdisk/viattr/Veattr/Vloff', substr($cd, $off, 46));
        if ($h['sig'] !== 0x02014b50) break;
        $n = substr($cd, $off + 46, $h['nl']);
        $off += 46 + $h['nl'] + $h['el'] + $h['cl'];
        if (is_callable($nama) ? $nama($n) : $n === $nama) {
            fseek($fh, $h['loff'] + 26);
            $l = unpack('vnl/vel', (string) fread($fh, 4));
            return ['method' => $h['method'], 'csize' => $h['csize'], 'mulai' => $h['loff'] + 30 + $l['nl'] + $l['el']];
        }
    }
    return null;
}

/** Isi entri kecil (mis. sharedStrings) sekaligus. */
function xlsx_zip_isi($fh, array $en): string
{
    fseek($fh, $en['mulai']);
    $isi = (string) fread($fh, $en['csize']);
    if ($en['method'] === 0) return $isi;
    if ($en['method'] === 8) {
        if (!function_exists('gzinflate')) throw new RuntimeException('Ekstensi PHP zlib belum aktif.');
        $h = gzinflate($isi);
        if ($h === false) throw new RuntimeException('Isi file XLSX rusak.');
        return $h;
    }
    throw new RuntimeException('Metode kompresi XLSX tidak didukung.');
}

/** Alirkan isi entri besar potong demi potong (memori tetap kecil walau hasil ekstrak ratusan MB). */
function xlsx_zip_alir($fh, array $en): Generator
{
    fseek($fh, $en['mulai']);
    $sisa = $en['csize'];
    if ($en['method'] === 8) {
        if (!function_exists('inflate_init')) throw new RuntimeException('Ekstensi PHP zlib belum aktif (inflate_init).');
        $ctx = inflate_init(ZLIB_ENCODING_RAW);
    } elseif ($en['method'] !== 0) {
        throw new RuntimeException('Metode kompresi XLSX tidak didukung.');
    }
    while ($sisa > 0) {
        $chunk = (string) fread($fh, min(65536, $sisa));
        if ($chunk === '') break;
        $sisa -= strlen($chunk);
        if ($en['method'] === 8) {
            $chunk = inflate_add($ctx, $chunk, $sisa > 0 ? ZLIB_NO_FLUSH : ZLIB_FINISH);
            if ($chunk === false) throw new RuntimeException('Isi file XLSX rusak.');
        }
        if ($chunk !== '') yield $chunk;
    }
}

function xlsx_baris_murni(string $berkas): Generator
{
    $fh = fopen($berkas, 'rb');
    if (!$fh) throw new RuntimeException('File tidak bisa dibaca.');
    try {
        $ukuran = (int) filesize($berkas);
        $sheet  = xlsx_zip_cari($fh, $ukuran, 'xl/worksheets/sheet1.xml')
               ?? xlsx_zip_cari($fh, $ukuran, fn($n) => (bool) preg_match('#^xl/worksheets/[^/]+\.xml$#', $n));
        if ($sheet === null) throw new RuntimeException('Sheet tidak ditemukan di dalam file.');

        $ss  = [];
        $en  = xlsx_zip_cari($fh, $ukuran, 'xl/sharedStrings.xml');
        if ($en !== null) {
            $xml = preg_replace('#<rPh\b.*?</rPh>#s', '', xlsx_zip_isi($fh, $en));
            if (preg_match_all('#<si\b[^>]*?(?:/>|>(.*?)</si>)#s', $xml, $m, PREG_SET_ORDER)) {
                foreach ($m as $x) $ss[] = html_entity_decode(strip_tags($x[1] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
            }
            unset($xml, $m);
        }

        $buf = '';
        foreach (xlsx_zip_alir($fh, $sheet) as $potong) {
            $buf .= $potong;
            $pos = 0;
            while (preg_match('#<row\b([^>]*?)(?:/>|>(.*?)</row>)#s', $buf, $r, PREG_OFFSET_CAPTURE, $pos)) {
                $pos = $r[0][1] + strlen($r[0][0]);
                yield from xlsx_baris_satu($r[1][0], $r[2][0] ?? '', $ss);
            }
            $buf = substr($buf, $pos);          // sisakan potongan baris yang belum lengkap
        }
    } finally {
        fclose($fh);
    }
}

/** Urai satu elemen <row> menjadi [nomor_baris => [huruf_kolom => nilai]]. */
function xlsx_baris_satu(string $attr, string $dalam, array $ss): Generator
{
    $no  = preg_match('/\br="(\d+)"/', $attr, $mr) ? (int) $mr[1] : 0;
    $sel = [];
    if ($dalam !== '' && preg_match_all('#<c\b([^>]*?)(?:/>|>(.*?)</c>)#s', $dalam, $cs, PREG_SET_ORDER)) {
        foreach ($cs as $c) {
            if (!isset($c[2]) || !preg_match('/\br="([A-Z]+)\d+"/', $c[1], $mc)) continue;
            $tipe = preg_match('/\bt="([^"]*)"/', $c[1], $mt) ? $mt[1] : '';
            if (preg_match('#<v>(.*?)</v>#s', $c[2], $mv))         $nilai = html_entity_decode($mv[1], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            elseif (preg_match('#<is\b.*?</is>#s', $c[2], $mi))    $nilai = html_entity_decode(strip_tags($mi[0]), ENT_XML1 | ENT_QUOTES, 'UTF-8');
            else continue;
            $sel[$mc[1]] = $tipe === 's' ? ($ss[(int) $nilai] ?? '') : $nilai;
        }
    }
    yield $no => $sel;
}