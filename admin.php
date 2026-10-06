<?php
require __DIR__ . '/config.php';

try {
    $conn = getDbConnection();
} catch (Exception $e) {
    die($e->getMessage());
}

$stats = $conn->query("SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN stage_idx = 3 THEN 1 ELSE 0 END) AS selesai,
    SUM(CASE WHEN stage_idx BETWEEN 1 AND 2 THEN 1 ELSE 0 END) AS diproses
FROM pengaduan");
$statsData = $stats && $stats->num_rows ? $stats->fetch_assoc() : ['total' => 0, 'selesai' => 0, 'diproses' => 0];

$list = $conn->query("SELECT * FROM pengaduan ORDER BY created_at DESC");

function getStatusLabel(int $stageIdx): string {
    $labels = ['Diterima', 'Diverifikasi', 'Sedang dikerjakan', 'Selesai'];
    return $labels[max(0, min($stageIdx, count($labels) - 1))];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard SiAdu</title>
    <style>
        :root {
            --navy: #14304D;
            --navy-2: #1F4A73;
            --gold: #B9832A;
            --bg: #F4F7FB;
            --surface: #FFFFFF;
            --text: #1F2A37;
            --muted: #64748B;
            --border: #E2E8F0;
            --success: #1F7A4D;
            --warning: #B9832A;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        .wrap { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .topbar {
            background: var(--navy);
            color: #fff;
            padding: 18px 0;
        }
        .topbar-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }
        h1 { margin: 0; font-size: 28px; }
        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(180px, 1fr));
            gap: 16px;
            margin: 28px 0;
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
        }
        .number {
            font-size: 32px;
            font-weight: 700;
            color: var(--navy-2);
            margin-bottom: 6px;
        }
        .label { color: var(--muted); font-size: 14px; }
        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }
        th, td {
            text-align: left;
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }
        th {
            background: var(--navy);
            color: #fff;
            font-size: 13px;
            text-transform: uppercase;
        }
        tbody tr:hover { background: #F8FAFC; }
        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            background: #E7F3EC;
            color: var(--success);
        }
        .badge.warn {
            background: #F1E4CC;
            color: var(--warning);
        }
        .muted { color: var(--muted); }
        a { color: var(--navy-2); text-decoration: none; }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="topbar-inner">
            <h1>Dashboard SiAdu</h1>
            <a href="index.php">Lihat website utama</a>
        </div>
    </div>

    <div class="wrap">
        <div class="stats">
            <div class="card">
                <div class="number">
                    <?= (int)($statsData['total'] ?? 0) ?>
                </div>
                <div class="label">Total aduan</div>
            </div>
            <div class="card">
                <div class="number">
                    <?= (int)($statsData['diproses'] ?? 0) ?>
                </div>
                <div class="label">Sedang diproses</div>
            </div>
            <div class="card">
                <div class="number">
                    <?= (int)($statsData['selesai'] ?? 0) ?>
                </div>
                <div class="label">Selesai</div>
            </div>
        </div>

        <div class="card" style="padding:0; overflow:hidden;">
            <table>
                <thead>
                    <tr>
                        <th>No. Tiket</th>
                        <th>Kategori</th>
                        <th>Lokasi</th>
                        <th>Dinas</th>
                        <th>Status</th>
                        <th>Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($list && $list->num_rows > 0): ?>
                        <?php while ($row = $list->fetch_assoc()): ?>
                            <?php $stageIdx = (int)($row['stage_idx'] ?? 0); ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['nomor_tiket']) ?></strong></td>
                                <td><?= htmlspecialchars($row['kategori_nama']) ?></td>
                                <td><?= htmlspecialchars($row['lokasi']) ?></td>
                                <td><?= htmlspecialchars($row['dinas']) ?></td>
                                <td>
                                    <span class="badge <?= $stageIdx >= 3 ? '' : 'warn' ?>"><?= getStatusLabel($stageIdx) ?></span>
                                </td>
                                <td class="muted"><?= htmlspecialchars($row['created_at']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="padding: 20px; color: var(--muted);">Belum ada aduan masuk.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>