<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require __DIR__ . '/config.php';

try {
    $conn = getDbConnection();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($action)) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

switch ($action) {
    case 'kirim':
        kirimAduan($conn);
        break;

    case 'lacak':
        lacakAduan($conn);
        break;

    case 'simulasi':
        simulasiProgres($conn);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Aksi tidak valid']);
        break;
}

function getStageLabel(int $stageIdx): string {
    $labels = ['Diterima', 'Diverifikasi', 'Sedang dikerjakan', 'Selesai'];
    $idx = max(0, min($stageIdx, count($labels) - 1));
    return $labels[$idx];
}

function kirimAduan($conn) {
    $kategori_key  = $conn->real_escape_string($_POST['kategori_key'] ?? '');
    $kategori_nama = $conn->real_escape_string($_POST['kategori_nama'] ?? '');
    $dinas         = $conn->real_escape_string($_POST['dinas'] ?? '');
    $lokasi        = trim((string)($_POST['lokasi'] ?? ''));
    $deskripsi     = trim((string)($_POST['deskripsi'] ?? ''));

    if (empty($lokasi) || empty($deskripsi)) {
        echo json_encode(['status' => 'error', 'message' => 'Lokasi dan deskripsi wajib diisi']);
        return;
    }

    $foto_path = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_ext  = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $file_name = time() . '_' . uniqid() . ($file_ext ? '.' . $file_ext : '');
        $target    = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['foto']['tmp_name'], $target)) {
            $foto_path = 'uploads/' . $file_name;
        }
    }

    $nomor_tiket = 'ADU-' . date('Y') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));
    $lokasi_esc = $conn->real_escape_string($lokasi);
    $deskripsi_esc = $conn->real_escape_string($deskripsi);
    $kategori_key_esc = $conn->real_escape_string($kategori_key);
    $kategori_nama_esc = $conn->real_escape_string($kategori_nama);
    $dinas_esc = $conn->real_escape_string($dinas);

    $sql = "INSERT INTO pengaduan (nomor_tiket, kategori_key, kategori_nama, dinas, lokasi, deskripsi, foto, stage_idx, created_at)
            VALUES ('$nomor_tiket', '$kategori_key_esc', '$kategori_nama_esc', '$dinas_esc', '$lokasi_esc', '$deskripsi_esc', " . ($foto_path ? "'$foto_path'" : "NULL") . ", 0, NOW())";

    if ($conn->query($sql)) {
        echo json_encode([
            'status' => 'success',
            'nomor_tiket' => $nomor_tiket,
            'stage_label' => getStageLabel(0)
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan ke database: ' . $conn->error]);
    }
}

function lacakAduan($conn) {
    $nomor_tiket = $conn->real_escape_string($_GET['nomor_tiket'] ?? '');

    if (empty($nomor_tiket)) {
        echo json_encode(['status' => 'error', 'message' => 'Nomor tiket tidak boleh kosong']);
        return;
    }

    $sql = "SELECT * FROM pengaduan WHERE nomor_tiket = '$nomor_tiket' LIMIT 1";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stageIdx = (int)($row['stage_idx'] ?? 0);

        echo json_encode([
            'status' => 'success',
            'data' => [
                'nomor_tiket'   => $row['nomor_tiket'],
                'kategori_nama' => $row['kategori_nama'],
                'dinas'         => $row['dinas'],
                'lokasi'        => $row['lokasi'],
                'deskripsi'     => $row['deskripsi'],
                'foto'          => $row['foto'] ?? null,
                'stage_idx'     => $stageIdx,
                'stage_label'   => getStageLabel($stageIdx)
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Nomor tiket tidak ditemukan']);
    }
}

function simulasiProgres($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    $nomor_tiket = $conn->real_escape_string($input['nomor_tiket'] ?? '');

    if (empty($nomor_tiket)) {
        echo json_encode(['status' => 'error', 'message' => 'Nomor tiket kosong']);
        return;
    }

    $sql = "UPDATE pengaduan SET stage_idx = LEAST(stage_idx + 1, 3) WHERE nomor_tiket = '$nomor_tiket'";

    if ($conn->query($sql)) {
        echo json_encode(['status' => 'success', 'stage_label' => getStageLabel(3)]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui status']);
    }
}