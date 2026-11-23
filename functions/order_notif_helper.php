<?php
// functions/order_notif_helper.php
use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * 1. NOTIFIKASI ORDER PASANG BARU -> DIKIRIM KE SF.UP
 */
function kirimNotifOrderPasang(PDO $conn, string $no_order): array
{
    $mail_cfg = require __DIR__ . '/../config/mail.php';

    try {
        $st = $conn->prepare('SELECT o.NoFormOrder, o.TglOrder, o.KdPetugas, o.UnitUp, o.NamaAkun, u.NamaUnit
                              FROM frm_order o
                              LEFT JOIN master_up u ON u.UnitUp = o.UnitUp
                              WHERE o.NoFormOrder = ? LIMIT 1');
        $st->execute([$no_order]);
        $order = $st->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return ['status' => false, 'message' => 'Data Order tidak ditemukan untuk notifikasi.'];
        }

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
                $valid_recipients[$email] = [
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

        $html_pdf = buildHtmlOrderPdf($order, $rows);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html_pdf, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf_binary = $dompdf->output();

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

/**
 * 2. NOTIFIKASI FINALISASI MUTASI PASANG -> DIKIRIM KE PEMBUAT ORDER & TL.UP
 */
function kirimNotifFinalisasiMutasi(PDO $conn, string $no_order): array
{
    $mail_cfg = require __DIR__ . '/../config/mail.php';

    try {
        // 1. Ambil Header Order
        $st = $conn->prepare('SELECT o.NoFormOrder, o.TglOrder, o.KdPetugas, o.UnitUp, o.NamaAkun, u.NamaUnit
                              FROM frm_order o
                              LEFT JOIN master_up u ON u.UnitUp = o.UnitUp
                              WHERE o.NoFormOrder = ? LIMIT 1');
        $st->execute([$no_order]);
        $order = $st->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return ['status' => false, 'message' => 'Data Order tidak ditemukan untuk notifikasi finalisasi.'];
        }

        // 2. Ambil Seluruh Data FPK + Berita Acara Lengkap (termasuk foto)
        $st_det = $conn->prepare("SELECT p.NoFpk, p.TglFpk, p.JenisPk, p.IdPel, p.LongDil, p.LatDil,
                                         pd.NoRef, pd.SimId, pd.ImeiModem, pd.NomorBarang,
                                         d.Nama,
                                         b.NoFba, b.TglFba, b.NamaAkun AS PetugasBa,
                                         bd.PosisiPasang, bd.LampuIndikator, bd.`Long` AS LongPasang, bd.`Lat` AS LatPasang,
                                         bd.FotoBarang, bd.FotoTerpasang
                                  FROM frm_order_detil od
                                  JOIN frm_pk p ON p.NoFpk = od.NoFpk
                                  LEFT JOIN frm_pk_detil pd ON pd.NoFpk = p.NoFpk
                                  LEFT JOIN dil d ON d.IdPel = p.IdPel
                                  LEFT JOIN frm_ba b ON b.NoFpk = p.NoFpk AND b.JenisPekerjaan = 'PASANG'
                                  LEFT JOIN frm_ba_detil bd ON bd.NoFba = b.NoFba AND bd.NoRef = pd.NoRef AND bd.StatusPekerjaan = 'PASANG'
                                  WHERE od.NoFormOrder = ?
                                  ORDER BY p.NoFpk ASC");
        $st_det->execute([$no_order]);
        $rows = $st_det->fetchAll(PDO::FETCH_ASSOC);

        // 3. Kumpulkan Penerima: Pembuat Order (NamaAkun) + TL.UP pada UnitUp yang sama
        $valid_recipients = [];

        // 3a. Pembuat Order
        if (!empty($order['NamaAkun'])) {
            $st_creator = $conn->prepare("SELECT NamaPengguna, AlamatEmail 
                                          FROM master_pengguna 
                                          WHERE NamaAkun = ? 
                                            AND StatusData = 'AKTIF' 
                                            AND AlamatEmail IS NOT NULL 
                                            AND AlamatEmail != '' LIMIT 1");
            $st_creator->execute([$order['NamaAkun']]);
            if ($creator = $st_creator->fetch(PDO::FETCH_ASSOC)) {
                $email = trim($creator['AlamatEmail']);
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $valid_recipients[$email] = [
                        'email' => $email,
                        'nama'  => $creator['NamaPengguna'] ?: $order['NamaAkun']
                    ];
                }
            }
        }

        // 3b. TL.UP pada UnitUp yang sama
        $st_tl = $conn->prepare("SELECT NamaPengguna, AlamatEmail 
                                 FROM master_pengguna 
                                 WHERE KodeHak = 'TL.UP' 
                                   AND UnitUp = ? 
                                   AND StatusData = 'AKTIF' 
                                   AND AlamatEmail IS NOT NULL 
                                   AND AlamatEmail != ''");
        $st_tl->execute([$order['UnitUp']]);
        foreach ($st_tl->fetchAll(PDO::FETCH_ASSOC) as $tl) {
            $email = trim($tl['AlamatEmail']);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid_recipients[$email] = [
                    'email' => $email,
                    'nama'  => $tl['NamaPengguna'] ?: 'Team Leader UP'
                ];
            }
        }

        if (empty($valid_recipients)) {
            return [
                'status'  => false,
                'message' => "Tidak ditemukan email valid untuk pembuat order maupun TL.UP pada UnitUp {$order['UnitUp']}."
            ];
        }

        // 4. Generate PDF Lengkap Mutasi Pasang + Foto Barang & Foto Terpasang
        $html_pdf = buildHtmlMutasiBaPdf($order, $rows);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html_pdf, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf_binary = $dompdf->output();

        // 5. Susun Isi Email Ringkasan Realisasi Pemasangan
        $tgl_order_fmt = $order['TglOrder'] ? date('d-m-Y', strtotime($order['TglOrder'])) : '-';
        $unit_label    = trim((string)($order['NamaUnit'] ?? '')) !== '' ? $order['NamaUnit'] . " ({$order['UnitUp']})" : $order['UnitUp'];

        $list_ba_html = '';
        foreach ($rows as $idx => $r) {
            $no = $idx + 1;
            $tgl_psg = !empty($r['TglFba']) ? date('d-m-Y', strtotime($r['TglFba'])) : '-';
            $list_ba_html .= "<tr>
                <td style='border:1px solid #ddd; padding:6px; text-align:center;'>{$no}</td>
                <td style='border:1px solid #ddd; padding:6px; font-family:monospace;'>" . htmlspecialchars($r['NoFba'] ?? '-') . "</td>
                <td style='border:1px solid #ddd; padding:6px; font-family:monospace;'>" . htmlspecialchars($r['IdPel']) . "</td>
                <td style='border:1px solid #ddd; padding:6px;'>" . htmlspecialchars($r['Nama'] ?? '-') . "</td>
                <td style='border:1px solid #ddd; padding:6px; font-family:monospace;'>" . htmlspecialchars($r['NoRef'] ?? '-') . "</td>
                <td style='border:1px solid #ddd; padding:6px; text-align:center;'>{$tgl_psg}</td>
            </tr>";
        }

        $body_email = "
        <div style='font-family:Arial,sans-serif; color:#333; line-height:1.5; max-width:680px;'>
            <div style='background:#198754; color:#fff; padding:15px 20px; border-radius:6px 6px 0 0;'>
                <h3 style='margin:0;'>Laporan Finalisasi Mutasi Pemasangan S41</h3>
                <small>Sistem Manajemen Aset S41 - PT PLN (Persero)</small>
            </div>
            <div style='border:1px solid #ddd; border-top:none; padding:20px; border-radius:0 0 6px 6px;'>
                <p>Halo <strong>Pembuat Order / Team Leader (TL.UP)</strong>,</p>
                <p>Pelaksanaan pemasangan lapangan untuk <strong>Order / SPK</strong> berikut telah <strong>selesai difinalisasi</strong> oleh tim lapangan (SF.UP):</p>
                
                <table style='width:100%; border-collapse:collapse; margin:15px 0; background:#f8f9fa;'>
                    <tr><td style='padding:6px 10px; width:160px;'><strong>Nomor Order</strong></td><td style='padding:6px 10px;'>: {$order['NoFormOrder']}</td></tr>
                    <tr><td style='padding:6px 10px;'><strong>Unit Pelaksana</strong></td><td style='padding:6px 10px;'>: {$unit_label}</td></tr>
                    <tr><td style='padding:6px 10px;'><strong>Tanggal Order</strong></td><td style='padding:6px 10px;'>: {$tgl_order_fmt}</td></tr>
                    <tr><td style='padding:6px 10px;'><strong>Total Terpasang</strong></td><td style='padding:6px 10px;'>: " . count($rows) . " Unit Perangkat S41 (Final)</td></tr>
                </table>

                <p style='margin-bottom:8px;'><strong>Rincian Berita Acara Pemasangan:</strong></p>
                <table style='width:100%; border-collapse:collapse; font-size:12px;'>
                    <thead>
                        <tr style='background:#e9ecef;'>
                            <th style='border:1px solid #ddd; padding:6px;'>No</th>
                            <th style='border:1px solid #ddd; padding:6px;'>No BA</th>
                            <th style='border:1px solid #ddd; padding:6px;'>IDPEL</th>
                            <th style='border:1px solid #ddd; padding:6px;'>Nama Pelanggan</th>
                            <th style='border:1px solid #ddd; padding:6px;'>No Ref Alat</th>
                            <th style='border:1px solid #ddd; padding:6px;'>Tgl Pasang</th>
                        </tr>
                    </thead>
                    <tbody>{$list_ba_html}</tbody>
                </table>

                <p style='margin-top:15px;'>Dokumen lengkap <strong>Rekapitulasi SPK &amp; Berita Acara Pemasangan (beserta dokumentasi foto)</strong> terlampir dalam format PDF pada email ini.</p>
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

        $nama_file_pdf = 'BA_Mutasi_Pasang_' . preg_replace('/[^A-Za-z0-9\-]/', '_', $no_order) . '.pdf';
        $mail->addStringAttachment($pdf_binary, $nama_file_pdf, 'base64', 'application/pdf');

        $mail->isHTML(true);
        $mail->Subject = "[Digita S41] Mutasi Pasang Selesai (Final): {$no_order} (Unit {$order['UnitUp']})";
        $mail->Body    = $body_email;
        $mail->send();

        $daftar_email = implode(', ', array_column($valid_recipients, 'email'));
        return [
            'status'  => true,
            'message' => "Laporan PDF Berita Acara berhasil dikirim ke: {$daftar_email}."
        ];
    } catch (MailException $e) {
        return ['status' => false, 'message' => 'Gagal mengirim email SMTP: ' . $e->getMessage()];
    } catch (\Throwable $e) {
        return ['status' => false, 'message' => 'Gagal memproses PDF/Notifikasi Finalisasi: ' . $e->getMessage()];
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

        <?php foreach ($rows as $d): ?>
            <?php
            $cb_pasang      = ($d['JenisPk'] === 'PASANG') ? '&#9745;' : '&#9744;';
            $cb_bongkar     = ($d['JenisPk'] === 'BONGKAR') ? '&#9745;' : '&#9744;';
            $cb_pelihara    = ($d['JenisPk'] === 'PEMELIHARAAN') ? '&#9745;' : '&#9744;';
            $tgl_pasang_fpk = !empty($d['TglFba']) ? date('d-m-Y', strtotime($d['TglFba'])) : '.......................';
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
                <tr><td class="lbl-fpk">Tikor DIL</td><td colspan="2">: Long: <?= $v($d['LongDil']) ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Lat: <?= v($d['LatDil']) ?></td></tr>
                
                <tr class="judul bg-light"><td class="lbl-fpk"></td><td style="width:39%;">BONGKAR</td><td style="width:39%;">PASANG</td></tr>
                
                <tr><td class="judul" style="text-align:center;" colspan="3">MODEM</td></tr>
                <tr><td class="lbl-fpk">IMEI</td><td>: </td><td>: <?= $v($d['ImeiModem']) ?></td></tr>
                <tr><td class="lbl-fpk">Nomor SIM</td><td>: </td><td>: <?= $v($d['SimId']) ?></td></tr>
                <tr><td class="lbl-fpk">Nomor Kontak</td><td>: </td><td>: <?= $v($d['NomorBarang']) ?></td></tr>
                
                <tr><td class="judul" style="text-align:center;" colspan="3">S41</td></tr>
                <tr><td class="lbl-fpk">NoRef IMEI</td><td>: </td><td>: <?= $v($d['NoRef']) ?></td></tr>
                <tr><td class="lbl-fpk">Nomor SIM</td><td>: </td><td>: <?= $v($d['SimId']) ?></td></tr>
                <tr><td class="lbl-fpk">Nomor Kontak</td><td>: </td><td>: <?= $v($d['NomorBarang']) ?></td></tr>
                <tr><td class="lbl-fpk">Tanggal Pasang</td><td>: .......................</td><td>: <?= $tgl_pasang_fpk ?></td></tr>
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

function buildHtmlMutasiBaPdf(array $order, array $rows): string
{
    $tgl         = $order['TglOrder'] ? date('d-m-Y', strtotime($order['TglOrder'])) : '-';
    $unit        = trim((string)($order['NamaUnit'] ?? '')) !== '' ? $order['NamaUnit'] : ($order['UnitUp'] ?? '-');
    $total_baris = max(25, count($rows));

    $v = function ($val) {
        return htmlspecialchars(trim((string)$val) === '' ? '' : (string)$val, ENT_QUOTES, 'UTF-8');
    };
    $cb = function ($cond) {
        return $cond ? '&#9745;' : '&#9744;';
    };

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <style>
            @page { size: A4 portrait; margin: 9mm; }
            * { box-sizing: border-box; }
            body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9px; color: #000; margin: 0; padding: 0; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
            td, th { border: 1px solid #000; padding: 2.5px 5px; vertical-align: middle; }
            .judul { text-align: center; font-weight: bold; }
            .lbl { width: 18%; }
            .lbl-fpk { width: 22%; }
            .daftar th { font-weight: bold; text-align: center; background-color: #f8f9fa; }
            .daftar td { height: 17px; font-size: 8.5px; }
            .daftar .no { width: 4%; text-align: center; }
            .mono { font-family: "DejaVu Sans Mono", monospace; }
            .foto-wrap { text-align: center; padding: 4px; height: 115px; }
            .foto-wrap img { max-width: 100%; max-height: 105px; }
            .page-break { page-break-before: always; }
            .bg-light { background-color: #f8f9fa; }
        </style>
    </head>
    <body>
        <!-- HALAMAN 1: REKAP HASIL MUTASI PEMASANGAN -->
        <table>
            <tr class="judul">
                <td style="width:25%;text-align:left;">PT PLN (PERSERO)<br>UID BANTEN<br><?= $v($unit) ?></td>
                <td style="width:50%; font-size:11.5px;">FORMULIR<br>HASIL MUTASI PEMASANGAN (SPK)</td>
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
                <td style="width:33%; vertical-align:top; height:80px; padding-top:8px;">
                    Memeriksa,<br>Team Leader Pengendalian Piutang<br><br><br><br>(____________________)
                </td>
                <td style="width:34%; vertical-align:top; height:80px; padding-top:8px;">
                    Memeriksa,<br>Team Leader Pengendalian Piutang<br><br><br><br>(____________________)
                </td>
                <td style="width:33%; vertical-align:top; height:80px; padding-top:8px;">
                    Menerima,<br>Petugas<br><br><br><br>( <?= $v($order['KdPetugas'] ?? '____________________') ?> )
                </td>
            </tr>
        </table>

        <!-- HALAMAN SELANJUTNYA: DETAIL BERITA ACARA + FOTO -->
        <?php foreach ($rows as $d): ?>
            <?php
            $cb_pasang   = ($d['JenisPk'] === 'PASANG') ? '&#9745;' : '&#9744;';
            $cb_bongkar  = ($d['JenisPk'] === 'BONGKAR') ? '&#9745;' : '&#9744;';
            $cb_pelihara = ($d['JenisPk'] === 'PEMELIHARAAN') ? '&#9745;' : '&#9744;';

            $posisi        = trim((string) ($d['PosisiPasang'] ?? ''));
            $lampu         = trim((string) ($d['LampuIndikator'] ?? ''));
            $ada_foto_brg  = !empty($d['FotoBarang']);
            $ada_foto_psg  = !empty($d['FotoTerpasang']);
            $tgl_fpk_fmt   = !empty($d['TglFpk']) ? date('d-m-Y', strtotime($d['TglFpk'])) : '-';
            $tgl_pasang_ba = !empty($d['TglFba']) ? date('d-m-Y', strtotime($d['TglFba'])) : '-';
            $long_pasang   = trim((string) ($d['LongPasang'] ?? '')) !== '' ? $d['LongPasang'] : ($d['LongDil'] ?? '-');
            $lat_pasang    = trim((string) ($d['LatPasang'] ?? '')) !== '' ? $d['LatPasang'] : ($d['LatDil'] ?? '-');
            ?>
            <div class="page-break"></div>
            <table>
                <tr class="judul">
                    <td style="width:25%; text-align:left;">PT PLN (PERSERO)<br>UID BANTEN<br><?= $v($unit) ?></td>
                    <td style="width:50%; font-size:11.5px;">BERITA ACARA<br>PERINTAH KERJA PEMASANGAN</td>
                    <td style="width:25%; font-weight:normal;">NOMOR BA<br><?= $v($d['NoFba'] ?: 'FM-2026-Z') ?></td>
                </tr>
            </table>
            <table>
                <tr><td class="lbl-fpk">Unit</td><td colspan="2">: <?= $v($unit) ?></td></tr>
                <tr><td class="lbl-fpk">Tanggal</td><td colspan="2">: <?= $tgl_fpk_fmt ?></td></tr>
                <tr><td class="lbl-fpk">Jenis PK</td><td colspan="2">: <?= $cb_pasang ?> PASANG &nbsp;&nbsp;&nbsp;&nbsp; <?= $cb_bongkar ?> BONGKAR &nbsp;&nbsp;&nbsp;&nbsp; <?= $cb_pelihara ?> PEMELIHARAAN</td></tr>
                <tr><td class="lbl-fpk">Nomor Form FPK</td><td colspan="2">: <?= $v($d['NoFpk']) ?></td></tr>
                <tr><td class="lbl-fpk">Id Pelanggan</td><td colspan="2">: <?= $v($d['IdPel']) ?></td></tr>
                <tr><td class="lbl-fpk">Nama</td><td colspan="2">: <?= $v($d['Nama']) ?></td></tr>
                <tr><td class="lbl-fpk">Tikor DIL</td><td colspan="2">: Long: <?= $v($d['LongDil'] ?: '-') ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Lat: <?= $v($d['LatDil'] ?: '-') ?></td></tr>
                
                <tr class="judul bg-light"><td class="lbl-fpk"></td><td style="width:39%;">BONGKAR</td><td style="width:39%;">PASANG</td></tr>
                
                <tr><td class="judul" style="text-align:center;" colspan="3">MODEM</td></tr>
                <tr><td class="lbl-fpk">IMEI</td><td>: </td><td>: <?= $v($d['ImeiModem']) ?></td></tr>
                <tr><td class="lbl-fpk">Nomor SIM</td><td>: </td><td>: <?= $v($d['SimId']) ?></td></tr>
                <tr><td class="lbl-fpk">Nomor Kontak</td><td>: </td><td>: <?= $v($d['NomorBarang']) ?></td></tr>
                
                <tr><td class="judul" style="text-align:center;" colspan="3">S41</td></tr>
                <tr><td class="lbl-fpk">NoRef IMEI</td><td>: </td><td>: <?= $v($d['NoRef']) ?></td></tr>
                <tr><td class="lbl-fpk">Nomor SIM</td><td>: </td><td>: <?= $v($d['SimId']) ?></td></tr>
                <tr><td class="lbl-fpk">Nomor Kontak</td><td>: </td><td>: <?= $v($d['NomorBarang']) ?></td></tr>
                <tr><td class="lbl-fpk">Tanggal Pasang</td><td>: .......................</td><td>: <strong><?= $tgl_pasang_ba ?></strong></td></tr>
                <tr><td class="lbl-fpk">Tikor</td>
                    <td>: Long: ....................... Lat: .......................</td>
                    <td>: Long: <strong><?= $v($long_pasang) ?></strong> &nbsp; Lat: <strong><?= $v($lat_pasang) ?></strong></td>
                </tr>
                <tr><td class="lbl-fpk">Posisi</td>
                    <td>&#9744; Pangkal &nbsp; &#9744; Tengah &nbsp; &#9744; Ujung</td>
                    <td><?= $cb(strcasecmp($posisi, 'Pangkal') === 0) ?> Pangkal &nbsp; <?= $cb(strcasecmp($posisi, 'Tengah') === 0) ?> Tengah &nbsp; <?= $cb(strcasecmp($posisi, 'Ujung') === 0) ?> Ujung</td>
                </tr>
                <tr><td class="lbl-fpk">Lampu indikator</td>
                    <td>&#9744; Menyala &nbsp;&nbsp;&nbsp;&nbsp; &#9744; Tidak Menyala</td>
                    <td><?= $cb($lampu === 'MENYALA') ?> Menyala &nbsp;&nbsp;&nbsp;&nbsp; <?= $cb($lampu === 'TIDAK') ?> Tidak Menyala</td>
                </tr>
                <tr><td class="lbl-fpk">Foto Barang</td>
                    <td>&#9744; Ada Dilampirkan &nbsp; &#9744; Tidak Dilampirkan</td>
                    <td><?= $cb($ada_foto_brg) ?> Ada Dilampirkan &nbsp; <?= $cb(!$ada_foto_brg) ?> Tidak Dilampirkan</td>
                </tr>
                <tr><td class="lbl-fpk">Foto Terpasang</td>
                    <td>&#9744; Ada Dilampirkan &nbsp; &#9744; Tidak Dilampirkan</td>
                    <td><?= $cb($ada_foto_psg) ?> Ada Dilampirkan &nbsp; <?= $cb(!$ada_foto_psg) ?> Tidak Dilampirkan</td>
                </tr>
            </table>

            <table>
                <tr class="judul bg-light">
                    <td style="width:50%;">FOTO BARANG / PERANGKAT S41</td>
                    <td style="width:50%;">FOTO TERPASANG DI LAPANGAN</td>
                </tr>
                <tr>
                    <td class="foto-wrap">
                        <?php if ($ada_foto_brg): ?>
                            <img src="data:image/jpeg;base64,<?= base64_encode($d['FotoBarang']) ?>" alt="Foto Barang">
                        <?php else: ?>
                            <span style="color:#777; font-style:italic;">(Tidak ada foto barang)</span>
                        <?php endif; ?>
                    </td>
                    <td class="foto-wrap">
                        <?php if ($ada_foto_psg): ?>
                            <img src="data:image/jpeg;base64,<?= base64_encode($d['FotoTerpasang']) ?>" alt="Foto Terpasang">
                        <?php else: ?>
                            <span style="color:#777; font-style:italic;">(Tidak ada foto terpasang)</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <table style="text-align:center;">
                <tr>
                    <td style="width:33%; vertical-align:top; height:75px; padding-top:6px;">
                        Memasang,<br>Petugas<br><br><br>( <?= $v($d['PetugasBa'] ?: '____________________') ?> )
                    </td>
                    <td style="width:34%; vertical-align:top; height:75px; padding-top:6px;">
                        Memeriksa,<br>Staf Pengendalian Piutang<br><br><br>(____________________)
                    </td>
                    <td style="width:33%; vertical-align:top; height:75px; padding-top:6px;">
                        Menyetujui,<br>Team Leader Pengendalian Piutang<br><br><br>(____________________)
                    </td>
                </tr>
            </table>
            <table>
                <tr>
                    <td style="height:40px; vertical-align:top; font-weight:bold;">Catatan Pemasangan:<br></td>
                </tr>
            </table>
        <?php endforeach; ?>
    </body>
    </html>
    <?php
    return ob_get_clean();
}