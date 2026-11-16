<div class="container">
    <table>
        <tr>
            <td style="width: 30%; font-weight: bold; font-size: 13px; vertical-align: top;">
                PT PLN (PERSERO)<br><?= htmlspecialchars($kop_upi) ?><br><?= htmlspecialchars($kop_ap) ?><br><?= htmlspecialchars($kop_up) ?>
            </td>
            <td style="width: 40%; text-align: center; font-weight: bold; font-size: 14px; vertical-align: top;">
                FORMULIR<br><u>PENGUJIAN PEMASANGAN</u>
            </td>
            <td style="width: 30%; text-align: right; font-weight: bold; font-size: 13px; vertical-align: top;">
                NOMOR<br><?= htmlspecialchars($no_form) ?>
            </td>
        </tr>
    </table>

    <table style="width: 50%; margin-top: 15px; margin-bottom: 5px; font-size: 12px; font-weight: bold;">
        <tr><td style="width: 100px; padding: 2px 0;">Unit</td><td>: <?= htmlspecialchars($head['UnitUp']) ?></td></tr>
        <tr><td style="padding: 2px 0;">Tanggal Uji</td><td>: <?= date('d-m-Y', strtotime($head['TglUji'])) ?></td></tr>
        <tr><td style="padding: 2px 0;">IMEI</td><td style="font-family: monospace;">: <?= htmlspecialchars($it['ImeiModem']) ?></td></tr>
        <tr><td style="padding: 2px 0;">Nomor SIM</td><td style="font-family: monospace;">: <?= htmlspecialchars($it['SimId'] ?? '-') ?></td></tr>
        <tr><td style="padding: 2px 0;">Nomor Kontak</td><td style="font-family: monospace;">: +62 <?= htmlspecialchars(ltrim($it['NomorAkun'] ?? '-', '0+62')) ?></td></tr>
    </table>

    <div class="section-title">KONDISI ADMIN</div>
    <table class="table-detail">
        <tr>
            <td style="width: 120px;">SET ADMIN</td><td style="width: 10px;">:</td>
            <td style="width: 120px;"><?= cek($it['SetAdmin']=='BERHASIL') ?> Berhasil</td>
            <td style="width: 120px;"><?= cek($it['SetAdmin']=='GAGAL') ?> Gagal</td>
            <td style="border-bottom: 1px solid #000;"></td>
        </tr>
        <tr>
            <td>DEL ADMIN</td><td>:</td>
            <td><?= cek($it['DelAdmin']=='BERHASIL') ?> Berhasil</td>
            <td><?= cek($it['DelAdmin']=='GAGAL') ?> Gagal</td>
            <td></td>
        </tr>
        <tr>
            <td>RESET ADMIN</td><td>:</td>
            <td><?= cek($it['ResetAdmin']=='BERHASIL') ?> Berhasil</td>
            <td><?= cek($it['ResetAdmin']=='GAGAL') ?> Gagal</td>
            <td></td>
        </tr>
        <tr>
            <td>LIST ADMIN</td><td>:</td>
            <td><?= cek($it['ListAdmin']=='BERHASIL') ?> Berhasil</td>
            <td><?= cek($it['ListAdmin']=='GAGAL') ?> Gagal</td>
            <td></td>
        </tr>
    </table>

    <div class="section-title">KONDISI AWAL</div>
    <table class="table-detail">
        <tr>
            <td style="width: 120px;">Cek phasa</td><td style="width: 10px;">:</td>
            <td style="width: 150px;"><?= cek($it['TeganganPhasa']=='ADA') ?> Ada Tegangan</td>
            <td><?= cek($it['TeganganPhasa']=='TIDAK') ?> Tidak Ada Tegangan</td>
        </tr>
        <tr>
            <td>Lampu indikator</td><td>:</td>
            <td><?= cek($it['LampuIndikator']=='AKTIF') ?> Menyala</td>
            <td><?= cek($it['LampuIndikator']=='TIDAK') ?> Tidak Menyala</td>
        </tr>
        <tr>
            <td>PLNSTATUS</td><td>:</td>
            <td colspan="2"><u>&nbsp;&nbsp;&nbsp; <?= htmlspecialchars($it['DetikStatusAwal'] ?? '......') ?> &nbsp;&nbsp;&nbsp;</u> detik</td>
        </tr>
        <tr>
            <td>Dijawab</td><td>:</td>
            <td><?= cek($it['JawabanStatusAwal']=='ADA') ?> Ya</td>
            <td><?= cek($it['JawabanStatusAwal']=='TIDAK') ?> Tidak</td>
        </tr>
        <tr>
            <td>Isi status</td><td>:</td>
            <td><?= cek($it['IsiStatusAwal']=='SESUAI') ?> Sesuai</td>
            <td><?= cek($it['IsiStatusAwal']=='TIDAK') ?> Tidak Sesuai</td>
        </tr>
    </table>

    <div class="section-title">KONDISI DIPUTUS SEMENTARA</div>
    <table class="table-detail">
        <tr><td style="width: 120px;">PLNOFF</td><td style="width: 10px;">:</td><td colspan="2"><u>&nbsp;&nbsp;&nbsp; <?= htmlspecialchars($it['DetikPutus'] ?? '......') ?> &nbsp;&nbsp;&nbsp;</u> detik</td></tr>
        <tr><td>Dijawab</td><td>:</td><td style="width: 150px;"><?= cek($it['JawabanPutus']=='ADA') ?> Ya</td><td><?= cek($it['JawabanPutus']=='TIDAK') ?> Tidak</td></tr>
        <tr><td>Durasi Jawab</td><td>:</td><td colspan="2"><u>&nbsp;&nbsp;&nbsp; <?= htmlspecialchars($it['DetikJawabanPutus'] ?? '......') ?> &nbsp;&nbsp;&nbsp;</u> detik</td></tr>
        <tr><td>Lampu beban</td><td>:</td><td><?= cek($it['LampuDiputus']=='MENYALA') ?> Menyala</td><td><?= cek($it['LampuDiputus']=='PADAM') ?> Tidak Menyala</td></tr>
        <tr><td>PLNSTATUS</td><td>:</td><td colspan="2"><u>&nbsp;&nbsp;&nbsp; <?= htmlspecialchars($it['DetikStatusPutus'] ?? '......') ?> &nbsp;&nbsp;&nbsp;</u> detik</td></tr>
        <tr><td>Dijawab</td><td>:</td><td><?= cek($it['JawabanStatusPutus']=='ADA') ?> Ya</td><td><?= cek($it['JawabanStatusPutus']=='TIDAK') ?> Tidak</td></tr>
        <tr><td>Isi status</td><td>:</td><td><?= cek($it['IsiStatusPutus']=='SESUAI') ?> Sesuai</td><td><?= cek($it['IsiStatusPutus']=='TIDAK') ?> Tidak Sesuai</td></tr>
    </table>

    <div class="section-title">KONDISI DINYALAKAN KEMBALI</div>
    <table class="table-detail">
        <tr><td style="width: 120px;">PLNON</td><td style="width: 10px;">:</td><td colspan="2"><u>&nbsp;&nbsp;&nbsp; <?= htmlspecialchars($it['DetikNyala'] ?? '......') ?> &nbsp;&nbsp;&nbsp;</u> detik</td></tr>
        <tr><td>Dijawab</td><td>:</td><td style="width: 150px;"><?= cek($it['JawabanNyala']=='ADA') ?> Ya</td><td><?= cek($it['JawabanNyala']=='TIDAK') ?> Tidak</td></tr>
        <tr><td>Durasi Jawab</td><td>:</td><td colspan="2"><u>&nbsp;&nbsp;&nbsp; <?= htmlspecialchars($it['DetikJawabanNyala'] ?? '......') ?> &nbsp;&nbsp;&nbsp;</u> detik</td></tr>
        <tr><td>Lampu beban</td><td>:</td><td><?= cek($it['LampuMenyala']=='MENYALA') ?> Menyala</td><td><?= cek($it['LampuMenyala']=='PADAM') ?> Tidak Menyala</td></tr>
        <tr><td>PLNSTATUS</td><td>:</td><td colspan="2"><u>&nbsp;&nbsp;&nbsp; <?= htmlspecialchars($it['DetikStatusNyala'] ?? '......') ?> &nbsp;&nbsp;&nbsp;</u> detik</td></tr>
        <tr><td>Dijawab</td><td>:</td><td><?= cek($it['JawabanStatusNyala']=='ADA') ?> Ya</td><td><?= cek($it['JawabanStatusNyala']=='TIDAK') ?> Tidak</td></tr>
        <tr><td>Isi status</td><td>:</td><td><?= cek($it['IsiStatusNyala']=='SESUAI') ?> Sesuai</td><td><?= cek($it['IsiStatusNyala']=='TIDAK') ?> Tidak Sesuai</td></tr>
    </table>

    <table class="ttd-box">
        <tr>
            <td>Menguji,<br>Staf Pengendalian Piutang<br><br><br><br><br>( ____________________ )</td>
            <td>Memeriksa,<br>Team Leader Pengendalian Piutang<br><br><br><br><br>( ____________________ )</td>
            <td>Menyetujui,<br>Asman Niaga/Manajer Unit Layanan<br><br><br><br><br>( ____________________ )</td>
        </tr>
    </table>

    <div style="margin-top:10px; font-weight:bold; font-size:12px;">Catatan Pengujian:</div>
    <div style="border-bottom: 1px dotted #000; margin-top: 20px; font-size: 11px;">
        <?= htmlspecialchars($it['Catatan'] ?? '') ?>
    </div>
</div>