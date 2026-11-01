<?php
require __DIR__ . '/auth.php';
$staff = requireStaff();
$conn = getDbConnection();
$isAdmin = $staff['peran'] === 'admin';
$opdId = (int)($staff['id_opd'] ?? 0);

if ($isAdmin) {
    $statsQuery = $conn->query("SELECT COUNT(*) AS total,
        SUM(status = 'selesai') AS selesai,
        SUM(status IN ('diterima', 'diverifikasi', 'diproses')) AS diproses
        FROM pengaduan");
    $listQuery = $conn->query("SELECT p.no_tiket, p.judul, p.lokasi, p.status, p.tgl_masuk,
        k.nama_kategori, o.nama_opd, u.nama AS nama_pelapor
        FROM pengaduan p
        JOIN kategori k ON k.id_kategori = p.id_kategori
        JOIN perangkat_daerah o ON o.id_opd = p.id_opd
        LEFT JOIN pengguna u ON u.id_pengguna = p.id_pengguna
        ORDER BY p.tgl_masuk DESC");
} else {
    $statsStatement = $conn->prepare("SELECT COUNT(*) AS total,
        SUM(status = 'selesai') AS selesai,
        SUM(status IN ('diterima', 'diverifikasi', 'diproses')) AS diproses
        FROM pengaduan WHERE id_opd = ?");
    $statsStatement->bind_param('i', $opdId);
    $statsStatement->execute();
    $statsQuery = $statsStatement->get_result();

    $listStatement = $conn->prepare("SELECT p.no_tiket, p.judul, p.lokasi, p.status, p.tgl_masuk,
        k.nama_kategori, o.nama_opd, u.nama AS nama_pelapor
        FROM pengaduan p
        JOIN kategori k ON k.id_kategori = p.id_kategori
        JOIN perangkat_daerah o ON o.id_opd = p.id_opd
        LEFT JOIN pengguna u ON u.id_pengguna = p.id_pengguna
        WHERE p.id_opd = ? ORDER BY p.tgl_masuk DESC");
    $listStatement->bind_param('i', $opdId);
    $listStatement->execute();
    $listQuery = $listStatement->get_result();
}
$stats = $statsQuery->fetch_assoc() ?: ['total' => 0, 'selesai' => 0, 'diproses' => 0];
$complaints = $listQuery->fetch_all(MYSQLI_ASSOC);
$statusLabels = [
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
    <title>Dashboard Petugas — SiAdu</title>
    <style>
        :root{--navy:#14304d;--blue:#1f4a73;--bg:#f4f7fb;--border:#e2e8f0;--muted:#64748b}
        *{box-sizing:border-box}body{margin:0;font:15px Arial,sans-serif;background:var(--bg);color:#1f2a37}
        header{background:var(--navy);color:#fff;padding:18px 24px;display:flex;justify-content:space-between;align-items:center}
        header h1{font-size:22px;margin:0}header a{color:#fff}form.inline{display:inline}
        button{cursor:pointer}header button{background:transparent;color:#fff;border:1px solid #fff;border-radius:6px;padding:8px 12px}
        main{max-width:1200px;margin:28px auto;padding:0 20px}.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px}
        .card{background:#fff;border:1px solid var(--border);border-radius:10px;padding:18px}.number{font-size:28px;font-weight:bold;color:var(--blue)}
        .label{margin-top:5px;color:var(--muted)}.table-wrap{overflow:auto;background:#fff;border:1px solid var(--border);border-radius:10px}
        table{width:100%;border-collapse:collapse;min-width:950px}th,td{text-align:left;padding:12px;border-bottom:1px solid var(--border);vertical-align:top}
        th{background:var(--navy);color:#fff;font-size:12px;text-transform:uppercase}td small{display:block;color:var(--muted);margin-top:4px}
        select,input{padding:8px;border:1px solid #cbd5e1;border-radius:6px;max-width:180px}td button{padding:8px 10px;background:var(--blue);color:#fff;border:0;border-radius:6px}
        .message{margin:0 0 16px;padding:12px;border-radius:8px;display:none}.message.error{display:block;background:#fff0ee;color:#8b2e24}
        .message.success{display:block;background:#e7f3ec;color:#1f7a4d}
        @media(max-width:650px){header{align-items:flex-start;gap:12px}.stats{grid-template-columns:1fr}}
    </style>
</head>
<body>
<header>
    <div>
        <h1>Dashboard SiAdu</h1>
        <small><?= escapeHtml($staff['nama']) ?><?= $isAdmin ? ' — Admin' : ' — Petugas OPD' ?></small>
    </div>
    <div>
        <a href="index.php">Beranda</a>
        <form class="inline" method="post" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
            <button type="submit">Keluar</button>
        </form>
    </div>
</header>
<main>
    <div class="stats">
        <div class="card"><div class="number"><?= (int)($stats['total'] ?? 0) ?></div><div class="label">Total aduan</div></div>
        <div class="card"><div class="number"><?= (int)($stats['diproses'] ?? 0) ?></div><div class="label">Belum selesai</div></div>
        <div class="card"><div class="number"><?= (int)($stats['selesai'] ?? 0) ?></div><div class="label">Selesai</div></div>
    </div>
    <div id="message" class="message" role="status"></div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Tiket / pelapor</th><th>Aduan</th><th>OPD</th><th>Status</th><th>Masuk</th><th>Perbarui status</th>
            </tr></thead>
            <tbody>
            <?php if (!$complaints): ?>
                <tr><td colspan="6">Belum ada aduan untuk ditampilkan.</td></tr>
            <?php else: foreach ($complaints as $complaint): ?>
                <tr>
                    <td><strong><?= escapeHtml($complaint['no_tiket']) ?></strong><small><?= escapeHtml($complaint['nama_pelapor'] ?? 'Akun lama') ?></small></td>
                    <td><strong><?= escapeHtml($complaint['judul']) ?></strong><small><?= escapeHtml($complaint['nama_kategori']) ?> · <?= escapeHtml($complaint['lokasi']) ?></small></td>
                    <td><?= escapeHtml($complaint['nama_opd']) ?></td>
                    <td><?= escapeHtml($statusLabels[$complaint['status']] ?? $complaint['status']) ?></td>
                    <td><?= escapeHtml($complaint['tgl_masuk']) ?></td>
                    <td>
                        <form class="status-form">
                            <input type="hidden" name="no_tiket" value="<?= escapeHtml($complaint['no_tiket']) ?>">
                            <select name="status" aria-label="Status baru">
                                <?php foreach ($statusLabels as $key => $label): ?>
                                    <option value="<?= escapeHtml($key) ?>" <?= $key === $complaint['status'] ? 'selected' : '' ?>><?= escapeHtml($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input name="catatan" type="text" maxlength="2000" placeholder="Catatan (opsional)" aria-label="Catatan">
                            <button type="submit">Simpan</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</main>
<script>
const CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;
document.querySelectorAll('.status-form').forEach(form => {
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const message = document.getElementById('message');
        const button = form.querySelector('button');
        button.disabled = true;
        try {
            const response = await fetch('api.php?action=ubah_status', {
                method: 'POST',
                headers: {'X-CSRF-Token': CSRF_TOKEN},
                body: new FormData(form)
            });
            const result = await response.json();
            if (!response.ok || result.status !== 'success') {
                throw new Error(result.message || 'Status gagal diperbarui.');
            }
            message.className = 'message success';
            message.textContent = 'Status aduan berhasil diperbarui.';
            window.location.reload();
        } catch (error) {
            message.className = 'message error';
            message.textContent = error.message || 'Terjadi kesalahan saat memperbarui status.';
            button.disabled = false;
        }
    });
});
</script>
</body>
</html>
