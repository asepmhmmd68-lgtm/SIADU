<?php
require __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');

function respond(array $payload, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function stageLabel(string $status): string
{
    $labels = [
        'diterima' => 'Diterima',
        'diverifikasi' => 'Diverifikasi',
        'diproses' => 'Sedang dikerjakan',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak'
    ];
    return $labels[$status] ?? 'Status tidak diketahui';
}

function failRequest(string $message, int $httpCode = 400): void
{
    respond(['status' => 'error', 'message' => $message], $httpCode);
}

function requireApiCsrf(): bool
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!isValidCsrfToken($token)) {
        failRequest('Sesi keamanan tidak valid. Muat ulang halaman dan coba lagi.', 403);
        return false;
    }
    return true;
}

function saveComplaint(mysqli $conn): void
{
    $user = currentUser();
    if (!$user || $user['peran'] !== 'warga') {
        failRequest('Silakan masuk dengan akun warga untuk mengirim aduan.', 401);
        return;
    }
    if (!requireApiCsrf()) {
        return;
    }

    $categoryId = filter_var($_POST['id_kategori'] ?? null, FILTER_VALIDATE_INT);
    $title = trim((string)($_POST['judul'] ?? ''));
    $location = trim((string)($_POST['lokasi'] ?? ''));
    $description = trim((string)($_POST['deskripsi'] ?? ''));
    if (!$categoryId || $title === '' || strlen($title) > 180 || $location === '' || strlen($location) > 255 || $description === '') {
        failRequest('Kategori, judul, lokasi, dan deskripsi wajib diisi dengan benar.');
        return;
    }

    $categoryQuery = $conn->prepare('SELECT id_opd_default FROM kategori WHERE id_kategori = ?');
    $categoryQuery->bind_param('i', $categoryId);
    $categoryQuery->execute();
    $category = $categoryQuery->get_result()->fetch_assoc();
    if (!$category) {
        failRequest('Kategori yang dipilih tidak tersedia.');
        return;
    }

    $uploadedFile = null;
    $fileName = null;
    $mimeType = null;
    $fileSize = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['foto'];
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            failRequest('Foto gagal diunggah.');
            return;
        }
        if ($file['size'] < 1 || $file['size'] > 5 * 1024 * 1024) {
            failRequest('Ukuran foto maksimal 5 MB.');
            return;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mimeType])) {
            failRequest('Format foto harus JPG, PNG, atau WebP.');
            return;
        }

        $uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
            failRequest('Penyimpanan foto tidak tersedia.', 500);
            return;
        }
        $fileName = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
        $uploadedFile = $uploadDirectory . DIRECTORY_SEPARATOR . $fileName;
        if (!move_uploaded_file($file['tmp_name'], $uploadedFile)) {
            failRequest('Foto gagal disimpan.');
            return;
        }
        $fileSize = (int)ceil($file['size'] / 1024);
    }

    try {
        $conn->begin_transaction();
        $ticket = 'ADU-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(8)));
        $opdId = (int)$category['id_opd_default'];
        $userId = (int)$user['id_pengguna'];
        $insert = $conn->prepare("INSERT INTO pengaduan (no_tiket, id_pengguna, id_kategori, id_opd, judul, deskripsi, lokasi, status)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, 'diterima')");
        $insert->bind_param('siiisss', $ticket, $userId, $categoryId, $opdId, $title, $description, $location);
        $insert->execute();
        $complaintId = (int)$conn->insert_id;

        $history = $conn->prepare("INSERT INTO riwayat_status (id_pengaduan, id_pengguna, status, catatan)
                                   VALUES (?, ?, 'diterima', 'Aduan diterima')");
        $history->bind_param('ii', $complaintId, $userId);
        $history->execute();

        if ($uploadedFile !== null) {
            $storedPath = 'uploads/' . $fileName;
            $attachment = $conn->prepare('INSERT INTO lampiran (id_pengaduan, nama_berkas, lokasi_berkas, tipe_berkas, ukuran_kb) VALUES (?, ?, ?, ?, ?)');
            $attachment->bind_param('isssi', $complaintId, $fileName, $storedPath, $mimeType, $fileSize);
            $attachment->execute();
        }
        $conn->commit();
        respond(['status' => 'success', 'no_tiket' => $ticket, 'status_label' => stageLabel('diterima')], 201);
    } catch (Throwable $e) {
        $conn->rollback();
        if ($uploadedFile !== null && is_file($uploadedFile)) {
            unlink($uploadedFile);
        }
        error_log('Create complaint error: ' . $e->getMessage());
        failRequest('Aduan gagal disimpan. Silakan coba kembali.', 500);
    }
}

function trackComplaint(mysqli $conn): void
{
    $ticket = trim((string)($_GET['no_tiket'] ?? ''));
    if ($ticket === '') {
        failRequest('Nomor tiket tidak boleh kosong.');
        return;
    }

    $query = $conn->prepare(
        'SELECT p.id_pengguna, p.no_tiket, p.judul, p.status, p.tgl_masuk, k.nama_kategori, o.nama_opd
         FROM pengaduan p
         JOIN kategori k ON k.id_kategori = p.id_kategori
         JOIN perangkat_daerah o ON o.id_opd = p.id_opd
         WHERE p.no_tiket = ? LIMIT 1'
    );
    $query->bind_param('s', $ticket);
    $query->execute();
    $complaint = $query->get_result()->fetch_assoc();
    if (!$complaint) {
        failRequest('Nomor tiket tidak ditemukan.', 404);
        return;
    }

    $historyQuery = $conn->prepare(
        'SELECT status, waktu, catatan FROM riwayat_status
         WHERE id_pengaduan = (SELECT id_pengaduan FROM pengaduan WHERE no_tiket = ?)
         ORDER BY waktu ASC, id_riwayat ASC'
    );
    $historyQuery->bind_param('s', $ticket);
    $historyQuery->execute();
    $history = $historyQuery->get_result()->fetch_all(MYSQLI_ASSOC);

    $isOwner = currentUser() && (int)currentUser()['id_pengguna'] === (int)$complaint['id_pengguna'];
    $attachments = [];
    if ($isOwner) {
        $attachmentQuery = $conn->prepare(
            'SELECT l.lokasi_berkas FROM lampiran l
             JOIN pengaduan p ON p.id_pengaduan = l.id_pengaduan
             WHERE p.no_tiket = ?'
        );
        $attachmentQuery->bind_param('s', $ticket);
        $attachmentQuery->execute();
        $attachments = $attachmentQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    $complaint['status_label'] = stageLabel($complaint['status']);
    if (!$isOwner) {
        unset($complaint['judul']);
        $history = array_map(static function (array $row): array {
            unset($row['catatan']);
            return $row;
        }, $history);
    }
    unset($complaint['id_pengguna']);
    $complaint['riwayat'] = array_map(static function (array $row): array {
        $row['status_label'] = stageLabel($row['status']);
        return $row;
    }, $history);
    $complaint['lampiran'] = array_column($attachments, 'lokasi_berkas');
    respond(['status' => 'success', 'data' => $complaint]);
}

function updateComplaintStatus(mysqli $conn): void
{
    $user = currentUser();
    if (!$user || !in_array($user['peran'], ['petugas', 'admin'], true)) {
        failRequest('Akses hanya untuk petugas.', 403);
        return;
    }
    if (!requireApiCsrf()) {
        return;
    }

    $ticket = trim((string)($_POST['no_tiket'] ?? ''));
    $status = (string)($_POST['status'] ?? '');
    $note = trim((string)($_POST['catatan'] ?? ''));
    $allowedStatuses = ['diterima', 'diverifikasi', 'diproses', 'selesai', 'ditolak'];
    if ($ticket === '' || !in_array($status, $allowedStatuses, true) || strlen($note) > 2000) {
        failRequest('Data perubahan status tidak valid.');
        return;
    }

    try {
        $conn->begin_transaction();
        $query = $conn->prepare('SELECT id_pengaduan, id_opd FROM pengaduan WHERE no_tiket = ? FOR UPDATE');
        $query->bind_param('s', $ticket);
        $query->execute();
        $complaint = $query->get_result()->fetch_assoc();
        if (!$complaint) {
            $conn->rollback();
            failRequest('Nomor tiket tidak ditemukan.', 404);
            return;
        }
        if ($user['peran'] !== 'admin' && (int)$user['id_opd'] !== (int)$complaint['id_opd']) {
            $conn->rollback();
            failRequest('Anda hanya dapat memperbarui aduan pada OPD Anda.', 403);
            return;
        }

        $complaintId = (int)$complaint['id_pengaduan'];
        $userId = (int)$user['id_pengguna'];
        $update = $conn->prepare("UPDATE pengaduan
                                  SET status = ?, tgl_selesai = IF(? = 'selesai', COALESCE(tgl_selesai, CURRENT_TIMESTAMP), NULL)
                                  WHERE id_pengaduan = ?");
        $update->bind_param('ssi', $status, $status, $complaintId);
        $update->execute();
        $history = $conn->prepare('INSERT INTO riwayat_status (id_pengaduan, id_pengguna, status, catatan) VALUES (?, ?, ?, ?)');
        $history->bind_param('iiss', $complaintId, $userId, $status, $note);
        $history->execute();
        $conn->commit();
        respond(['status' => 'success', 'status_label' => stageLabel($status)]);
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Update complaint status error: ' . $e->getMessage());
        failRequest('Status gagal diperbarui.', 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    failRequest('Metode permintaan tidak didukung.', 405);
    exit;
}

try {
    $conn = getDbConnection();
    $action = (string)($_GET['action'] ?? '');
    if ($action === 'kirim' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        saveComplaint($conn);
    } elseif ($action === 'lacak' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        trackComplaint($conn);
    } elseif ($action === 'ubah_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        updateComplaintStatus($conn);
    } else {
        failRequest('Aksi tidak valid.', 404);
    }
} catch (Throwable $e) {
    error_log('API error: ' . $e->getMessage());
    failRequest('Layanan belum dapat memproses permintaan.', 500);
}
