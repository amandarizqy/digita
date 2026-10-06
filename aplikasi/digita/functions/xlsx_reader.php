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

/** Ambil isi satu file di dalam ZIP. $nama berupa string, atau callable(nama): bool untuk mencari pola. */
function xlsx_zip_ambil(string $data, $nama): ?string
{
    $pos = strrpos($data, "PK\x05\x06");
    if ($pos === false) throw new RuntimeException('File bukan XLSX yang valid.');
    $e   = unpack('vdisk/vcd_disk/ventries_disk/ventries/Vcd_size/Vcd_offset', substr($data, $pos + 4, 16));
    $off = $e['cd_offset'];

    for ($i = 0; $i < $e['entries']; $i++) {
        $h = unpack('Vsig/vvm/vvn/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnl/vel/vcl/vdisk/viattr/Veattr/Vloff', substr($data, $off, 46));
        if ($h['sig'] !== 0x02014b50) break;
        $n = substr($data, $off + 46, $h['nl']);
        $off += 46 + $h['nl'] + $h['el'] + $h['cl'];

        if (is_callable($nama) ? $nama($n) : $n === $nama) {
            $l     = unpack('vnl/vel', substr($data, $h['loff'] + 26, 4));
            $mulai = $h['loff'] + 30 + $l['nl'] + $l['el'];
            $isi   = substr($data, $mulai, $h['csize']);
            if ($h['method'] === 0) return $isi;
            if ($h['method'] === 8) {
                if (!function_exists('gzinflate')) throw new RuntimeException('Ekstensi PHP zlib belum aktif.');
                $hasil = gzinflate($isi);
                if ($hasil === false) throw new RuntimeException('Isi file XLSX rusak.');
                return $hasil;
            }
            throw new RuntimeException('Metode kompresi XLSX tidak didukung.');
        }
    }
    return null;
}

function xlsx_baris_murni(string $berkas): Generator
{
    $data = file_get_contents($berkas);
    if ($data === false) throw new RuntimeException('File tidak bisa dibaca.');

    $sheet = xlsx_zip_ambil($data, 'xl/worksheets/sheet1.xml')
          ?? xlsx_zip_ambil($data, fn($n) => (bool) preg_match('#^xl/worksheets/[^/]+\.xml$#', $n));
    if ($sheet === null) throw new RuntimeException('Sheet tidak ditemukan di dalam file.');

    $ss  = [];
    $xml = xlsx_zip_ambil($data, 'xl/sharedStrings.xml');
    if ($xml !== null) {
        $xml = preg_replace('#<rPh\b.*?</rPh>#s', '', $xml);
        if (preg_match_all('#<si\b[^>]*?(?:/>|>(.*?)</si>)#s', $xml, $m, PREG_SET_ORDER)) {
            foreach ($m as $x) $ss[] = html_entity_decode(strip_tags($x[1] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        }
    }
    unset($data, $xml);

    $pos = 0;
    while (preg_match('#<row\b([^>]*?)(?:/>|>(.*?)</row>)#s', $sheet, $r, PREG_OFFSET_CAPTURE, $pos)) {
        $pos = $r[0][1] + strlen($r[0][0]);
        $no  = preg_match('/\br="(\d+)"/', $r[1][0], $mr) ? (int) $mr[1] : 0;
        $sel = [];
        $dalam = $r[2][0] ?? '';
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
}
