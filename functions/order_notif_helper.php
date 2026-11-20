<?php
// functions/order_notif_helper.php
use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

require_once __DIR__ . '/../vendor/autoload.php';

function kirimNotifOrderPasang(PDO $conn, string $no_order): array
{
    $mail_cfg = require __DIR__ . '/../config/mail.php';

    try {
        // 1. Ambil Header Order & Nama Unit
        $st = $conn->prepare('SELECT o.NoFormOrder, o.TglOrder, o.KdPetugas, o.UnitUp, o.NamaAkun, u.NamaUnit
                              FROM frm_order o
                              LEFT JOIN master_up u ON u.UnitUp = o.UnitUp
                              WHERE o.NoFormOrder = ? LIMIT 1');
        $st->execute([$no_order]);
        $order = $st->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return ['status' => false, 'message' => 'Data Order tidak ditemukan untuk notifikasi.'];
        }

        // 2. Ambil Detail FPK yang tergabung dalam Order
        $st_det = $conn->prepare("SELECT p.NoFpk, p.TglFpk, p.JenisPk, p.IdPel, p.LongDil, p.LatDil,
                                         pd.NoRef, pd.SimId, pd.ImeiModem, pd.NomorBarang,
                                         d.Nama, b.TglFba
                                  FROM frm_order_detil od
                                  JOIN frm_pk p ON p.NoFpk = od.NoFpk
                                  LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk
                                  LEFT JOIN dil d ON d.IdPel = p.IdPel
                                  LEFT JOIN frm_ba b ON b.NoFpk = p.NoFpk AND b.JenisPekerjaan = 'PASANG'
                                  WHERE od.NoFormOrder = ?
                                  ORDER BY p.NoFpk ASC");
        $st_det->execute([$no_order]);
        $rows = $st_det->fetchAll(PDO::FETCH_ASSOC);

        // 3. Cari seluruh akun SF.UP aktif pada UnitUp yang sama
        $st_user = $conn->prepare("SELECT NamaPengguna, AlamatEmail 
                                   FROM master_pengguna 
                                   WHERE KodeHak = 'SF.UP' 
                                     AND UnitUp = ? 
                                     AND StatusData = 'AKTIF' 
                                     AND AlamatEmail IS NOT NULL 
                                     AND AlamatEmail != ''");
        $st_user->execute([$order['UnitUp']]);
        $penerima_list = $st_user->fetchAll(PDO::FETCH_ASSOC);

        $valid_recipients = [];
        foreach ($penerima_list as $u) {
            $email = trim($u['AlamatEmail']);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid_recipients[] = [
                    'email' => $email,
                    'nama'  => $u['NamaPengguna'] ?: 'Petugas SF.UP'
                ];
            }
        }

        if (empty($valid_recipients)) {
            return [
                'status'  => false,
                'message' => "Tidak ditemukan akun SF.UP aktif dengan email valid pada UnitUp {$order['UnitUp']}."
            ];
        }

        // 4. Generate HTML & Render PDF Lengkap (SPK + Semua FPK)
        $html_pdf = buildHtmlOrderPdf($order, $rows);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html_pdf, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf_binary = $dompdf->output();

        // 5. Susun Ringkasan Tabel FPK untuk Isi Email
        $tgl_order_fmt = $order['TglOrder'] ? date('d-m-Y', strtotime($order['TglOrder'])) : '-';
        $unit_label    = trim((string)($order['NamaUnit'] ?? '')) !== '' ? $order['NamaUnit'] . " ({$order['UnitUp']})" : $order['UnitUp'];

        $list_fpk_html = '';
        foreach ($rows as $idx => $r) {
            $no = $idx + 1;
            $list_fpk_html .= "<tr>
                <td style='border:1px solid #ddd; padding:6px; text-align:center;'>{$no}</td>
                <td style='border:1px solid #ddd; padding:6px; font-family:monospace;'>" . htmlspecialchars($r['NoFpk']) . "</td>
                <td style='border:1px solid #ddd; padding:6px; font-family:monospace;'>" . htmlspecialchars($r['IdPel']) . "</td>
                <td style='border:1px solid #ddd; padding:6px;'>" . htmlspecialchars($r['Nama'] ?? '-') . "</td>
                <td style='border:1px solid #ddd; padding:6px; font-family:monospace;'>" . htmlspecialchars($r['NoRef'] ?? '-') . "</td>
            </tr>";
        }

        $body_email = "
        <div style='font-family:Arial,sans-serif; color:#333; line-height:1.5; max-width:650px;'>
            <div style='background:#0d6efd; color:#fff; padding:15px 20px; border-radius:6px 6px 0 0;'>
                <h3 style='margin:0;'>Notifikasi Order Pemasangan Baru (SPK)</h3>
                <small>Sistem Manajemen Aset S41 - PT PLN (Persero)</small>
            </div>
            <div style='border:1px solid #ddd; border-top:none; padding:20px; border-radius:0 0 6px 6px;'>
                <p>Halo Tim Lapangan (<strong>SF.UP</strong>),</p>
                <p>Telah diterbitkan <strong>Surat Perintah Kerja (SPK / Order Pemasangan)</strong> baru yang siap untuk dieksekusi dan diinput Berita Acaranya pada menu <em>Mutasi Pasang</em>:</p>
                
                <table style='width:100%; border-collapse:collapse; margin:15px 0; background:#f8f9fa;'>
                    <tr><td style='padding:6px 10px; width:150px;'><strong>Nomor Order</strong></td><td style='padding:6px 10px;'>: {$order['NoFormOrder']}</td></tr>
                    <tr><td style='padding:6px 10px;'><strong>Unit Pelaksana</strong></td><td style='padding:6px 10px;'>: {$unit_label}</td></tr>
                    <tr><td style='padding:6px 10px;'><strong>Tanggal Order</strong></td><td style='padding:6px 10px;'>: {$tgl_order_fmt}</td></tr>
                    <tr><td style='padding:6px 10px;'><strong>Dibuat Oleh</strong></td><td style='padding:6px 10px;'>: " . htmlspecialchars($order['NamaAkun']) . "</td></tr>
                    <tr><td style='padding:6px 10px;'><strong>Jumlah Target</strong></td><td style='padding:6px 10px;'>: " . count($rows) . " FPK Pemasangan</td></tr>
                </table>

                <p style='margin-bottom:8px;'><strong>Rincian Target Pemasangan:</strong></p>
                <table style='width:100%; border-collapse:collapse; font-size:12px;'>
                    <thead>
                        <tr style='background:#e9ecef;'>
                            <th style='border:1px solid #ddd; padding:6px;'>No</th>
                            <th style='border:1px solid #ddd; padding:6px;'>No FPK</th>
                            <th style='border:1px solid #ddd; padding:6px;'>IDPEL</th>
                            <th style='border:1px solid #ddd; padding:6px;'>Nama Pelanggan</th>
                            <th style='border:1px solid #ddd; padding:6px;'>No Ref Alat</th>
                        </tr>
                    </thead>
                    <tbody>{$list_fpk_html}</tbody>
                </table>

                <p style='margin-top:15px;'>Dokumen lengkap <strong>Formulir Order &amp; seluruh FPK</strong> terlampir dalam format PDF pada email ini.</p>
                <hr style='border:none; border-top:1px solid #eee; margin:20px 0;'>
                <small style='color:#777;'>Email ini dikirim otomatis oleh Aplikasi Digita S41. Mohon tidak membalas email ini.</small>
            </div>
        </div>";

        // 6. Kirim Email via PHPMailer
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $mail_cfg['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $mail_cfg['smtp_user'];
        $mail->Password   = $mail_cfg['smtp_pass'];
        $mail->SMTPSecure = $mail_cfg['smtp_secure'];
        $mail->Port       = $mail_cfg['smtp_port'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($mail_cfg['from_email'], $mail_cfg['from_name']);

        foreach ($valid_recipients as $rec) {
            $mail->addAddress($rec['email'], $rec['nama']);
        }

        // Lampirkan file PDF Order & FPK
        $nama_file_pdf = 'Order_SPK_' . preg_replace('/[^A-Za-z0-9\-]/', '_', $no_order) . '.pdf';
        $mail->addStringAttachment($pdf_binary, $nama_file_pdf, 'base64', 'application/pdf');

        $mail->isHTML(true);
        $mail->Subject = "[Digita S41] Order Pemasangan Baru: {$no_order} (Unit {$order['UnitUp']})";
        $mail->Body    = $body_email;

        $mail->send();

        $daftar_email = implode(', ', array_column($valid_recipients, 'email'));
        return [
            'status'  => true,
            'message' => "Notifikasi & PDF Order berhasil dikirim ke SF.UP ({$daftar_email})."
        ];
    } catch (MailException $e) {
        return ['status' => false, 'message' => 'Gagal mengirim email SMTP: ' . $e->getMessage()];
    } catch (\Throwable $e) {
        return ['status' => false, 'message' => 'Gagal memproses PDF/Notifikasi: ' . $e->getMessage()];
    }
}

function buildHtmlOrderPdf(array $order, array $rows): string
{
    $tgl         = $order['TglOrder'] ? date('d-m-Y', strtotime($order['TglOrder'])) : '-';
    $unit        = trim((string)($order['NamaUnit'] ?? '')) !== '' ? $order['NamaUnit'] : ($order['UnitUp'] ?? '-');
    $total_baris = max(25, count($rows));

    $v = function ($val) {
        return htmlspecialchars(trim((string)$val) === '' ? '' : (string)$val, ENT_QUOTES, 'UTF-8');
    };

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <style>
            @page { size: A4 portrait; margin: 10mm; }
            * { box-sizing: border-box; }
            body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9.5px; color: #000; margin: 0; padding: 0; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
            td, th { border: 1px solid #000; padding: 3px 5px; vertical-align: middle; }
            .judul { text-align: center; font-weight: bold; }
            .lbl { width: 18%; }
            .lbl-fpk { width: 22%; }
            .daftar th { font-weight: bold; text-align: center; background-color: #f8f9fa; }
            .daftar td { height: 18px; font-size: 9px; }
            .daftar .no { width: 4%; text-align: center; }
            .mono { font-family: "DejaVu Sans Mono", monospace; }
            .page-break { page-break-before: always; }
            .bg-light { background-color: #f8f9fa; }
        </style>
    </head>
    <body>
        <!-- HALAMAN 1: REKAP FORMULIR ORDER PERINTAH KERJA (SPK) -->
        <table>
            <tr class="judul">
                <td style="width:25%;text-align:left;">PT PLN (PERSERO)<br>UID BANTEN<br><?= $v($unit) ?></td>
                <td style="width:50%; font-size:12px;">FORMULIR<br>ORDER PERINTAH KERJA</td>
                <td style="width:25%; font-weight:normal;">NOMOR DOKUMEN<br>FM-2026-Z</td>
            </tr>
        </table>
        <table>
            <tr><td class="lbl">Nomor Order</td><td>: <?= $v($order['NoFormOrder']) ?></td></tr>
            <tr><td class="lbl">Unit</td><td>: <?= $v($unit) ?></td></tr>
            <tr><td class="lbl">Nama Petugas</td><td>: <?= $v($order['KdPetugas'] ?? '-') ?></td></tr>
            <tr><td class="lbl">Tanggal</td><td>: <?= $v($tgl) ?></td></tr>
        </table>

        <table class="daftar">
            <thead>
                <tr>
                    <th class="no">No</th>
                    <th style="width:27%;">No Perintah Kerja</th>
                    <th style="width:15%;">IdPel</th>
                    <th style="width:19%;">No Ref IMEI</th>
                    <th style="width:24%;">SIM Id &ndash; Nomor Kontak</th>
                    <th style="width:11%;">Tgl Pasang</th>
                </tr>
            </thead>
            <tbody>
            <?php for ($i = 0; $i < $total_baris; $i++): ?>
                <?php $r = $rows[$i] ?? null; ?>
                <tr>
                    <td class="no"><?= $i + 1 ?></td>
                    <?php if ($r): ?>
                        <?php
                        $sim = trim((string) ($r['SimId'] ?? ''));
                        $knt = trim((string) ($r['NomorBarang'] ?? ''));
                        $sim_kontak = ($sim === '' && $knt === '') ? '' : ($sim !== '' ? $sim : '-') . ' - ' . ($knt !== '' ? $knt : '-');
                        ?>
                        <td class="mono" style="text-align:center;"><?= $v($r['NoFpk']) ?></td>
                        <td style="text-align:center;"><?= $v($r['IdPel']) ?></td>
                        <td class="mono" style="text-align:center;"><?= $v($r['NoRef'] ?? '') ?></td>
                        <td class="mono" style="text-align:center;"><?= $v($sim_kontak) ?></td>
                        <td style="text-align:center;"><?= !empty($r['TglFba']) ? date('d-m-Y', strtotime($r['TglFba'])) : '' ?></td>
                    <?php else: ?>
                        <td></td><td></td><td></td><td></td><td></td>
                    <?php endif; ?>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>

        <table style="text-align:center;">
            <tr>
                <td style="width:33%; vertical-align:top; height:85px; padding-top:8px;">
                    Memeriksa,<br>Team Leader Pengendalian Piutang<br><br><br><br>(____________________)
                </td>
                <td style="width:34%; vertical-align:top; height:85px; padding-top:8px;">
                    Memeriksa,<br>Team Leader Pengendalian Piutang<br><br><br><br>(____________________)
                </td>
                <td style="width:33%; vertical-align:top; height:85px; padding-top:8px;">
                    Menerima,<br>Petugas<br><br><br><br>( <?= $v($order['KdPetugas'] ?? '____________________') ?> )
                </td>
            </tr>
        </table>

        <!-- HALAMAN SELANJUTNYA: LOOPING FORMULIR PERINTAH KERJA (FPK) -->
        <?php foreach ($rows as $d): ?>
            <?php
            $cb_pasang   = ($d['JenisPk'] === 'PASANG') ? '&#9745;' : '&#9744;';
            $cb_bongkar  = ($d['JenisPk'] === 'BONGKAR') ? '&#9745;' : '&#9744;';
            $cb_pelihara = ($d['JenisPk'] === 'PEMELIHARAAN') ? '&#9745;' : '&#9744;';
            ?>
            <div class="page-break"></div>
            <table>
                <tr class="judul">
                    <td style="width:25%; text-align:left;">PT PLN (PERSERO)<br>UID BANTEN<br><?= $v($unit) ?></td>
                    <td style="width:50%; font-size:12px;">FORMULIR<br>PERINTAH KERJA</td>
                    <td style="width:25%; font-weight:normal;">NOMOR DOKUMEN<br>FM-2026-Z</td>
                </tr>
            </table>
            <table>
                <tr><td class="lbl-fpk">Unit</td><td colspan="2">: <?= $v($unit) ?></td></tr>
                <tr><td class="lbl-fpk">Tanggal</td><td colspan="2">: <?= date('d-m-Y', strtotime($d['TglFpk'])) ?></td></tr>
                <tr><td class="lbl-fpk">Jenis PK</td><td colspan="2">: <?= $cb_pasang ?> PASANG &nbsp;&nbsp;&nbsp;&nbsp; <?= $cb_bongkar ?> BONGKAR &nbsp;&nbsp;&nbsp;&nbsp; <?= $cb_pelihara ?> PEMELIHARAAN</td></tr>
                <tr><td class="lbl-fpk">Nomor Form</td><td colspan="2">: <?= $v($d['NoFpk']) ?></td></tr>
                <tr><td class="lbl-fpk">Id Pelanggan</td><td colspan="2">: <?= $v($d['IdPel']) ?></td></tr>
                <tr><td class="lbl-fpk">Nama</td><td colspan="2">: <?= $v($d['Nama']) ?></td></tr>
                <tr><td class="lbl-fpk">Tikor DIL</td><td colspan="2">: Long: <?= $v($d['LongDil']) ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Lat: <?= $v($d['LatDil']) ?></td></tr>
                
                <tr class="judul bg-light"><td class="lbl-fpk"></td><td style="width:39%;">BONGKAR</td><td style="width:39%;">PASANG</td></tr>
                
                <tr><td class="judul" style="text-align:center;" colspan="3">MODEM</td></tr>
                <tr><td class="lbl-fpk">IMEI</td><td>: </td><td>: <?= $v($d['ImeiModem']) ?></td></tr>
                <tr><td class="lbl-fpk">Nomor SIM</td><td>: </td><td>: <?= $v($d['SimId']) ?></td></tr>
                <tr><td class="lbl-fpk">Nomor Kontak</td><td>: </td><td>: <?= $v($d['NomorBarang']) ?></td></tr>
                
                <tr><td class="judul" style="text-align:center;" colspan="3">S41</td></tr>
                <tr><td class="lbl-fpk">NoRef IMEI</td><td>: </td><td>: <?= $v($d['NoRef']) ?></td></tr>
                <tr><td class="lbl-fpk">Nomor SIM</td><td>: </td><td>: <?= $v($d['SimId']) ?></td></tr>
                <tr><td class="lbl-fpk">Nomor Kontak</td><td>: </td><td>: <?= $v($d['NomorBarang']) ?></td></tr>
                <tr><td class="lbl-fpk">Tikor</td><td>: Long: ....................... Lat: .......................</td><td>: Long: ....................... Lat: .......................</td></tr>
                <tr><td class="lbl-fpk">Posisi</td>
                    <td>&#9744; Pangkal &nbsp; &#9744; Tengah &nbsp; &#9744; Ujung</td>
                    <td>&#9744; Pangkal &nbsp; &#9744; Tengah &nbsp; &#9744; Ujung</td>
                </tr>
                <tr><td class="lbl-fpk">Lampu indikator</td>
                    <td>&#9744; Menyala &nbsp;&nbsp;&nbsp;&nbsp; &#9744; Tidak Menyala</td>
                    <td>&#9744; Menyala &nbsp;&nbsp;&nbsp;&nbsp; &#9744; Tidak Menyala</td>
                </tr>
                <tr><td class="lbl-fpk">Foto Barang</td>
                    <td>&#9744; Ada Dilampirkan &nbsp; &#9744; Tidak Dilampirkan</td>
                    <td>&#9744; Ada Dilampirkan &nbsp; &#9744; Tidak Dilampirkan</td>
                </tr>
                <tr><td class="lbl-fpk">Foto Terpasang</td>
                    <td>&#9744; Ada Dilampirkan &nbsp; &#9744; Tidak Dilampirkan</td>
                    <td>&#9744; Ada Dilampirkan &nbsp; &#9744; Tidak Dilampirkan</td>
                </tr>
            </table>

            <table style="text-align:center;">
                <tr>
                    <td style="width:33%; vertical-align:top; height:85px; padding-top:8px;">
                        Memasang,<br>Petugas<br><br><br><br>(____________________)
                    </td>
                    <td style="width:34%; vertical-align:top; height:85px; padding-top:8px;">
                        Memeriksa,<br>Staf Pengendalian Piutang<br><br><br><br>(____________________)
                    </td>
                    <td style="width:33%; vertical-align:top; height:85px; padding-top:8px;">
                        Menyetujui,<br>Team Leader Pengendalian Piutang<br><br><br><br>(____________________)
                    </td>
                </tr>
            </table>
            <table>
                <tr>
                    <td style="height:60px; vertical-align:top; font-weight:bold;">Catatan Pemasangan:<br></td>
                </tr>
            </table>
        <?php endforeach; ?>
    </body>
    </html>
    <?php
    return ob_get_clean();
}