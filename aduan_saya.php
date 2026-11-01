<?php
require __DIR__ . '/auth.php';
$user = requireLogin();
if ($user['peran'] !== 'warga') {
    header('Location: admin.php');
    exit;
}

$conn = getDbConnection();
$statement = $conn->prepare(
    'SELECT p.no_tiket, p.judul, p.lokasi, p.deskripsi, p.status, p.tgl_masuk, p.tgl_selesai,
            k.nama_kategori, o.nama_opd
     FROM pengaduan p
     JOIN kategori k ON k.id_kategori = p.id_kategori
     JOIN perangkat_daerah o ON o.id_opd = p.id_opd
     WHERE p.id_pengguna = ?
     ORDER BY p.tgl_masuk DESC'
);
$userId = (int)$user['id_pengguna'];
$statement->bind_param('i', $userId);
$statement->execute();
$reports = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
$labels = [
    'diterima' => 'Diterima',
    'diverifikasi' => 'Diverifikasi',
    'diproses' => 'Sedang dikerjakan',
    'selesai' => 'Selesai',
    'ditolak' => 'Ditolak'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aduan Saya — SiAdu</title>
    <style>
        body{margin:0;background:#f2f4f6;color:#14202b;font:15px Arial,sans-serif}
        header{background:#14304d;color:#fff;padding:18px 24px;display:flex;justify-content:space-between;align-items:center}
        header h1{font-size:22px;margin:0}header a{color:#fff}
        main{max-width:900px;margin:28px auto;padding:0 20px}
        article{background:#fff;border:1px solid #dfe4e8;border-radius:10px;padding:20px;margin:14px 0}
        h2{margin:0 0 8px;color:#14304d;font-size:19px}
        .meta{color:#57646f;font-size:13px;margin:6px 0}
        .description{white-space:pre-wrap;line-height:1.55}
        .empty{background:#fff;padding:20px;border-radius:10px}
        form{display:inline}
        button{background:transparent;color:#fff;border:1px solid #fff;border-radius:6px;padding:8px 12px;cursor:pointer}
    </style>
</head>
<body>
<header>
    <h1>Aduan Saya</h1>
    <div><a href="index.php">Beranda</a>
        <form method="post" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
            <button type="submit">Keluar</button>
        </form>
    </div>
</header>
<main>
    <p>Selamat datang, <?= escapeHtml($user['nama']) ?>. Berikut laporan yang terhubung dengan akun Anda.</p>
    <?php if (!$reports): ?>
        <div class="empty">Anda belum mengirim aduan. <a href="index.php">Buat aduan pertama</a>.</div>
    <?php else: foreach ($reports as $report): ?>
        <article>
            <h2><?= escapeHtml($report['judul']) ?></h2>
            <div class="meta">Tiket: <strong><?= escapeHtml($report['no_tiket']) ?></strong></div>
            <div class="meta">Kategori: <?= escapeHtml($report['nama_kategori']) ?> · OPD: <?= escapeHtml($report['nama_opd']) ?></div>
            <div class="meta">Status: <strong><?= escapeHtml($labels[$report['status']] ?? $report['status']) ?></strong>
                · Masuk: <?= escapeHtml($report['tgl_masuk']) ?>
                <?= $report['tgl_selesai'] ? ' · Selesai: ' . escapeHtml($report['tgl_selesai']) : '' ?>
            </div>
            <div class="meta">Lokasi: <?= escapeHtml($report['lokasi']) ?></div>
            <p class="description"><?= escapeHtml($report['deskripsi']) ?></p>
            <a href="index.php?no_tiket=<?= rawurlencode($report['no_tiket']) ?>">Lihat riwayat status</a>
        </article>
    <?php endforeach; endif; ?>
</main>
<script>
const ticket = new URLSearchParams(location.search).get('no_tiket');
if (ticket) {
    location.href = 'index.php?no_tiket=' + encodeURIComponent(ticket) + '#nomorTiket';
}
</script>
</body>
</html>
