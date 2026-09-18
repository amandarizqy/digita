<?php


header('Content-Type: application/json');
require_once '../../includes/auth_check.php';   // pastikan user sudah login
require_once '../../config/database.php';       // harus menyediakan $pdo (PDO instance)

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'list':
        handleList($pdo);
        break;
    case 'daftar_aset':
        handleDaftarAset($pdo);
        break;
    case 'simpan':
        handleSimpan($pdo);
        break;
    default:
        respond(false, null, 'Action tidak dikenali.');
}

/* ============================================================
   LIST — daftar riwayat pelunasan (dengan pencarian sederhana)
   ============================================================ */
function handleList($pdo)
{
    $keyword = trim($_GET['q'] ?? '');

    $sql = "SELECT rp.id, a.kode_aset, a.nama_aset, rp.tanggal_pelunasan,
                   rp.nominal, rp.metode_pembayaran, rp.jenis_pelunasan, rp.file_bukti
            FROM riwayat_pelunasan rp
            JOIN aset a ON a.id = rp.aset_id";

    $params = [];
    if ($keyword !== '') {
        $sql .= " WHERE a.kode_aset LIKE :kw OR a.nama_aset LIKE :kw";
        $params[':kw'] = "%{$keyword}%";
    }
    $sql .= " ORDER BY rp.tanggal_pelunasan DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        respond(true, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        respond(false, null, 'Gagal mengambil data: ' . $e->getMessage());
    }
}

/* ============================================================
   DAFTAR ASET — untuk dropdown di form, sekaligus sisa tagihan
   ============================================================ */
function handleDaftarAset($pdo)
{
    $sql = "SELECT a.id, a.kode_aset, a.nama_aset,
                   COALESCE(a.nilai_perolehan - IFNULL(SUM(rp.nominal), 0), a.nilai_perolehan) AS sisa_tagihan
            FROM aset a
            LEFT JOIN riwayat_pelunasan rp ON rp.aset_id = a.id
            GROUP BY a.id
            ORDER BY a.kode_aset ASC";

    try {
        $stmt = $pdo->query($sql);
        respond(true, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        respond(false, null, 'Gagal mengambil daftar aset: ' . $e->getMessage());
    }
}

/* ============================================================
   SIMPAN — insert data riwayat pelunasan baru
   ============================================================ */
function handleSimpan($pdo)
{
    $aset_id           = $_POST['aset_id'] ?? null;
    $jenis_pelunasan   = $_POST['jenis_pelunasan'] ?? null;
    $tanggal_pelunasan = $_POST['tanggal_pelunasan'] ?? null;
    $nominal           = $_POST['nominal'] ?? null;
    $metode_pembayaran = $_POST['metode_pembayaran'] ?? null;
    $no_referensi      = $_POST['no_referensi'] ?? null;
    $keterangan        = $_POST['keterangan'] ?? null;

    if (!$aset_id || !$jenis_pelunasan || !$tanggal_pelunasan || !$nominal || !$metode_pembayaran) {
        respond(false, null, 'Mohon lengkapi semua field wajib.');
        return;
    }

    // --- Tangani upload bukti pembayaran (opsional) ---
    $namaFile = null;
    if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] === UPLOAD_ERR_OK) {
        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['bukti_pembayaran']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt)) {
            respond(false, null, 'Format file bukti tidak didukung.');
            return;
        }
        if ($_FILES['bukti_pembayaran']['size'] > 5 * 1024 * 1024) {
            respond(false, null, 'Ukuran file maksimal 5MB.');
            return;
        }

        $uploadDir = '../../uploads/pelunasan/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $namaFile = 'pelunasan_' . time() . '_' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['bukti_pembayaran']['tmp_name'], $uploadDir . $namaFile);
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO riwayat_pelunasan
                (aset_id, jenis_pelunasan, tanggal_pelunasan, nominal, metode_pembayaran,
                 no_referensi, file_bukti, keterangan, dibuat_oleh, dibuat_pada)
            VALUES
                (:aset_id, :jenis_pelunasan, :tanggal_pelunasan, :nominal, :metode_pembayaran,
                 :no_referensi, :file_bukti, :keterangan, :dibuat_oleh, NOW())
        ");

        $stmt->execute([
            ':aset_id'           => $aset_id,
            ':jenis_pelunasan'   => $jenis_pelunasan,
            ':tanggal_pelunasan' => $tanggal_pelunasan,
            ':nominal'           => $nominal,
            ':metode_pembayaran' => $metode_pembayaran,
            ':no_referensi'      => $no_referensi,
            ':file_bukti'        => $namaFile,
            ':keterangan'        => $keterangan,
            ':dibuat_oleh'       => $_SESSION['user_id'] ?? null,
        ]);

        respond(true, ['id' => $pdo->lastInsertId()], 'Data berhasil disimpan.');
    } catch (PDOException $e) {
        respond(false, null, 'Gagal menyimpan data: ' . $e->getMessage());
    }
}

/* ============================================================
   HELPER — format response JSON konsisten
   ============================================================ */
function respond($success, $data = null, $message = '')
{
    echo json_encode([
        'success' => $success,
        'data'    => $data,
        'message' => $message,
    ]);
    exit;
}