<?php
require __DIR__ . '/auth.php';
$staff = requireStaff();
$conn = getDbConnection();
$isAdmin = $staff['peran'] === 'admin';
$opdId = (int)($staff['id_opd'] ?? 0);
$statusLabels = [
    'diterima' => 'Diterima',
    'diverifikasi' => 'Diverifikasi',
    'diproses' => 'Sedang dikerjakan',
    'selesai' => 'Selesai',
    'ditolak' => 'Ditolak'
];

if ($isAdmin) {
    $statsQuery = $conn->query("SELECT COUNT(*) AS total,
        SUM(status = 'selesai') AS selesai,
        SUM(status IN ('diterima', 'diverifikasi', 'diproses')) AS diproses
        FROM pengaduan");
    $progressQuery = $conn->query("SELECT o.nama_opd, COUNT(p.id_pengaduan) AS total,
        COALESCE(SUM(p.status IN ('diterima', 'diverifikasi', 'diproses')), 0) AS diproses,
        COALESCE(SUM(p.status = 'selesai'), 0) AS selesai,
        COALESCE(SUM(p.status = 'ditolak'), 0) AS ditolak
        FROM perangkat_daerah o
        LEFT JOIN pengaduan p ON p.id_opd = o.id_opd
        GROUP BY o.id_opd, o.nama_opd
        ORDER BY o.nama_opd");
    $statusQuery = $conn->query("SELECT status, COUNT(*) AS jumlah
        FROM pengaduan GROUP BY status");
    $listQuery = $conn->query("SELECT p.id_pengaduan, p.no_tiket, p.judul, p.lokasi, p.status, p.tgl_masuk,
        k.nama_kategori, o.nama_opd, u.nama AS nama_pelapor
        FROM pengaduan p
        JOIN kategori k ON k.id_kategori = p.id_kategori
        JOIN perangkat_daerah o ON o.id_opd = p.id_opd
        LEFT JOIN pengguna u ON u.id_pengguna = p.id_pengguna
        ORDER BY p.tgl_masuk DESC");
    $attachmentQuery = $conn->query("SELECT l.id_pengaduan, l.lokasi_berkas
        FROM lampiran l
        JOIN pengaduan p ON p.id_pengaduan = l.id_pengaduan
        ORDER BY l.id_lampiran");
} else {
    $statsStatement = $conn->prepare("SELECT COUNT(*) AS total,
        SUM(status = 'selesai') AS selesai,
        SUM(status IN ('diterima', 'diverifikasi', 'diproses')) AS diproses
        FROM pengaduan WHERE id_opd = ?");
    $statsStatement->bind_param('i', $opdId);
    $statsStatement->execute();
    $statsQuery = $statsStatement->get_result();

    $progressStatement = $conn->prepare("SELECT o.nama_opd, COUNT(p.id_pengaduan) AS total,
        COALESCE(SUM(p.status IN ('diterima', 'diverifikasi', 'diproses')), 0) AS diproses,
        COALESCE(SUM(p.status = 'selesai'), 0) AS selesai,
        COALESCE(SUM(p.status = 'ditolak'), 0) AS ditolak
        FROM perangkat_daerah o
        LEFT JOIN pengaduan p ON p.id_opd = o.id_opd
        WHERE o.id_opd = ?
        GROUP BY o.id_opd, o.nama_opd");
    $progressStatement->bind_param('i', $opdId);
    $progressStatement->execute();
    $progressQuery = $progressStatement->get_result();

    $statusStatement = $conn->prepare("SELECT status, COUNT(*) AS jumlah
        FROM pengaduan WHERE id_opd = ? GROUP BY status");
    $statusStatement->bind_param('i', $opdId);
    $statusStatement->execute();
    $statusQuery = $statusStatement->get_result();

    $listStatement = $conn->prepare("SELECT p.id_pengaduan, p.no_tiket, p.judul, p.lokasi, p.status, p.tgl_masuk,
        k.nama_kategori, o.nama_opd, u.nama AS nama_pelapor
        FROM pengaduan p
        JOIN kategori k ON k.id_kategori = p.id_kategori
        JOIN perangkat_daerah o ON o.id_opd = p.id_opd
        LEFT JOIN pengguna u ON u.id_pengguna = p.id_pengguna
        WHERE p.id_opd = ? ORDER BY p.tgl_masuk DESC");
    $listStatement->bind_param('i', $opdId);
    $listStatement->execute();
    $listQuery = $listStatement->get_result();

    $attachmentStatement = $conn->prepare("SELECT l.id_pengaduan, l.lokasi_berkas
        FROM lampiran l
        JOIN pengaduan p ON p.id_pengaduan = l.id_pengaduan
        WHERE p.id_opd = ?
        ORDER BY l.id_lampiran");
    $attachmentStatement->bind_param('i', $opdId);
    $attachmentStatement->execute();
    $attachmentQuery = $attachmentStatement->get_result();
}
$stats = $statsQuery->fetch_assoc() ?: ['total' => 0, 'selesai' => 0, 'diproses' => 0];
$opdProgress = $progressQuery->fetch_all(MYSQLI_ASSOC);
$statusCounts = array_fill_keys(array_keys($statusLabels), 0);
while ($statusRow = $statusQuery->fetch_assoc()) {
    if (isset($statusCounts[$statusRow['status']])) {
        $statusCounts[$statusRow['status']] = (int)$statusRow['jumlah'];
    }
}
$maxStatusCount = max(1, max($statusCounts));
$complaints = $listQuery->fetch_all(MYSQLI_ASSOC);
$attachmentsByComplaint = [];
while ($attachment = $attachmentQuery->fetch_assoc()) {
    $attachmentsByComplaint[(int)$attachment['id_pengaduan']][] = $attachment['lokasi_berkas'];
}
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
        .charts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-bottom:22px}
        .chart-card h2{font-size:17px;margin:0 0 6px}.chart-note{margin:0 0 18px;color:var(--muted);font-size:13px}
        .chart-card.wide{grid-column:1/-1}.chart-toolbar{display:flex;gap:8px;margin:14px 0}
        .period-button{padding:8px 12px;border:1px solid var(--border);border-radius:7px;background:#fff;color:var(--blue)}
        .period-button[aria-pressed=true]{background:var(--blue);border-color:var(--blue);color:#fff}
        .trend-chart{display:block;width:100%;height:auto;min-height:180px;overflow:visible}
        .trend-chart text{font:12px Arial,sans-serif;fill:var(--muted)}
        .trend-summary{font-size:13px;color:var(--blue);font-weight:bold;margin:4px 0 0}
        .donut-layout{display:flex;align-items:center;justify-content:center;gap:24px;flex-wrap:wrap}
        .donut-chart{width:min(220px,55vw);height:auto;overflow:visible}
        .donut-chart circle{fill:none;stroke-width:24}
        .donut-chart text{font-family:Arial,sans-serif;text-anchor:middle;dominant-baseline:middle}
        .donut-total{font-size:26px;font-weight:bold;fill:var(--blue)}
        .donut-caption{font-size:11px;fill:var(--muted)}
        .donut-legend{display:grid;gap:10px;min-width:145px}
        .donut-legend-item{display:flex;align-items:center;gap:8px;color:var(--muted);font-size:13px}
        .donut-swatch{width:10px;height:10px;border-radius:50%;flex:none}
        .chart-row{display:grid;grid-template-columns:minmax(110px,1fr) 2fr 38px;align-items:center;gap:10px;margin:13px 0}
        .chart-label{font-size:13px}.chart-value{text-align:right;font-weight:bold;font-size:13px}
        .bar-track{height:12px;background:#edf2f7;border-radius:8px;overflow:hidden}
        .bar-fill{display:block;height:100%;border-radius:8px;background:#3978b8}
        .bar-fill.diverifikasi{background:#e5a72c}.bar-fill.diproses{background:#815ac7}
        .bar-fill.selesai{background:#2f9b68}.bar-fill.ditolak{background:#d45a52}
        .stacked-track{display:flex}.stacked-track span{height:100%}.stacked-track .diproses{background:#3978b8}
        .stacked-track .selesai{background:#2f9b68}.stacked-track .ditolak{background:#d45a52}
        .legend{display:flex;flex-wrap:wrap;gap:12px;margin-top:18px;color:var(--muted);font-size:12px}
        .legend span{display:inline-flex;align-items:center;gap:5px}.legend i{width:9px;height:9px;border-radius:50%;background:#3978b8}
        .legend .selesai i{background:#2f9b68}.legend .ditolak i{background:#d45a52}
        .attachment-list{display:flex;flex-wrap:wrap;gap:8px}.attachment-preview{display:block}
        .attachment-preview img{display:block;width:72px;height:72px;object-fit:cover;border:1px solid var(--border);border-radius:6px}
        @media(max-width:760px){.charts{grid-template-columns:1fr}}
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
        <div class="card"><div class="number" data-dashboard-total><?= (int)($stats['total'] ?? 0) ?></div><div class="label">Total aduan</div></div>
        <div class="card"><div class="number" data-dashboard-active><?= (int)($stats['diproses'] ?? 0) ?></div><div class="label">Dalam penanganan</div></div>
        <div class="card"><div class="number" data-dashboard-finished><?= (int)($stats['selesai'] ?? 0) ?></div><div class="label">Selesai</div></div>
    </div>
    <p id="dashboard-sync" class="chart-note" role="status" aria-live="polite">Data berasal dari portal pengaduan dan diperbarui otomatis setiap 15 detik.</p>
    <section class="charts" aria-label="Grafik progres aduan">
        <div class="card chart-card wide">
            <h2>Tren pengajuan warga</h2>
            <p class="chart-note">Jumlah laporan baru berdasarkan tanggal masuk. Pilih rentang harian, bulanan, atau tahunan.</p>
            <div class="chart-toolbar" role="group" aria-label="Rentang waktu grafik pengajuan">
                <button class="period-button" type="button" data-trend-period="daily" aria-pressed="true">Per hari</button>
                <button class="period-button" type="button" data-trend-period="monthly" aria-pressed="false">Per bulan</button>
                <button class="period-button" type="button" data-trend-period="yearly" aria-pressed="false">Per tahun</button>
            </div>
            <svg id="trend-chart" class="trend-chart" viewBox="0 0 760 280" role="img" aria-label="Grafik jumlah pengajuan"></svg>
            <p id="trend-summary" class="trend-summary" aria-live="polite"></p>
        </div>
        <div class="card chart-card">
            <h2>Komposisi status aduan</h2>
            <p class="chart-note">Perbandingan proporsi seluruh status laporan.</p>
            <div class="donut-layout">
                <svg id="status-donut" class="donut-chart" viewBox="0 0 220 220" role="img" aria-label="Diagram komposisi status aduan"></svg>
                <div id="status-donut-legend" class="donut-legend"></div>
            </div>
        </div>
        <div class="card chart-card">
            <h2>Jumlah aduan per status</h2>
            <p class="chart-note">Perbandingan jumlah aduan pada setiap status.</p>
            <?php foreach ($statusLabels as $status => $label): ?>
                <?php $jumlah = $statusCounts[$status]; ?>
                <div class="chart-row" data-status-row="<?= escapeHtml($status) ?>">
                    <span class="chart-label"><?= escapeHtml($label) ?></span>
                    <div class="bar-track" role="img" aria-label="<?= escapeHtml($label . ': ' . $jumlah . ' aduan') ?>">
                        <span class="bar-fill <?= escapeHtml($status) ?>" data-status-bar style="width: <?= ($jumlah / $maxStatusCount) * 100 ?>%"></span>
                    </div>
                    <span class="chart-value" data-status-count><?= $jumlah ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="card chart-card">
            <h2>Progres per perangkat daerah</h2>
            <p class="chart-note">Total laporan, yang sedang ditangani, selesai, dan ditolak.</p>
            <div id="opd-progress-chart">
            <?php if (!$opdProgress): ?>
                <p class="label">Belum ada perangkat daerah.</p>
            <?php else: foreach ($opdProgress as $opd): ?>
                <?php
                    $total = (int)$opd['total'];
                    $diproses = (int)$opd['diproses'];
                    $selesai = (int)$opd['selesai'];
                    $ditolak = (int)$opd['ditolak'];
                    $denominator = max(1, $total);
                ?>
                <div class="chart-row">
                    <span class="chart-label"><?= escapeHtml($opd['nama_opd']) ?></span>
                    <div class="bar-track stacked-track" role="img" aria-label="<?= escapeHtml($opd['nama_opd'] . ': ' . $total . ' laporan, ' . $diproses . ' sedang ditangani, ' . $selesai . ' selesai, ' . $ditolak . ' ditolak') ?>">
                        <span class="diproses" style="width: <?= ($diproses / $denominator) * 100 ?>%"></span>
                        <span class="selesai" style="width: <?= ($selesai / $denominator) * 100 ?>%"></span>
                        <span class="ditolak" style="width: <?= ($ditolak / $denominator) * 100 ?>%"></span>
                    </div>
                    <span class="chart-value"><?= $total ?></span>
                </div>
            <?php endforeach; endif; ?>
            </div>
            <div class="legend">
                <span><i></i>Sedang ditangani</span>
                <span class="selesai"><i></i>Selesai</span>
                <span class="ditolak"><i></i>Ditolak</span>
            </div>
        </div>
    </section>
    <div id="message" class="message" role="status"></div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Tiket / pelapor</th><th>Aduan</th><th>OPD</th><th>Lampiran</th><th>Status</th><th>Masuk</th><th>Perbarui status</th>
            </tr></thead>
            <tbody>
            <?php if (!$complaints): ?>
                <tr><td colspan="7">Belum ada aduan untuk ditampilkan.</td></tr>
            <?php else: foreach ($complaints as $complaint): ?>
                <tr>
                    <td><strong><?= escapeHtml($complaint['no_tiket']) ?></strong><small><?= escapeHtml($complaint['nama_pelapor'] ?? 'Akun lama') ?></small></td>
                    <td><strong><?= escapeHtml($complaint['judul']) ?></strong><small><?= escapeHtml($complaint['nama_kategori']) ?> · <?= escapeHtml($complaint['lokasi']) ?></small></td>
                    <td><?= escapeHtml($complaint['nama_opd']) ?></td>
                    <td>
                        <?php $attachments = $attachmentsByComplaint[(int)$complaint['id_pengaduan']] ?? []; ?>
                        <?php if ($attachments): ?>
                            <div class="attachment-list">
                                <?php foreach ($attachments as $attachmentPath): ?>
                                    <a class="attachment-preview" href="<?= escapeHtml($attachmentPath) ?>" target="_blank" rel="noopener" aria-label="Buka lampiran gambar">
                                        <img src="<?= escapeHtml($attachmentPath) ?>" alt="Lampiran aduan <?= escapeHtml($complaint['no_tiket']) ?>" loading="lazy">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <small>Tidak ada lampiran</small>
                        <?php endif; ?>
                    </td>
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
const STATUS_LABELS = <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const STATUS_COLORS = {
    diterima: '#3978b8',
    diverifikasi: '#e5a72c',
    diproses: '#815ac7',
    selesai: '#2f9b68',
    ditolak: '#d45a52'
};
let dashboardData = null;
let selectedTrendPeriod = 'daily';

function createSvgElement(name, attributes = {}) {
    const element = document.createElementNS('http://www.w3.org/2000/svg', name);
    Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, value));
    return element;
}

function buildTrendPoints(period, periodEnd, rows) {
    const counts = new Map(rows.map(row => [String(row.periode), Number(row.jumlah)]));
    const [year, month, day] = periodEnd.split('-').map(Number);
    const slots = [];

    if (period === 'daily') {
        const date = new Date(Date.UTC(year, month - 1, day - 13));
        for (let index = 0; index < 14; index++) {
            const key = date.toISOString().slice(0, 10);
            slots.push({key, label: new Intl.DateTimeFormat('id-ID', {day: 'numeric', month: 'short', timeZone: 'UTC'}).format(date)});
            date.setUTCDate(date.getUTCDate() + 1);
        }
    } else if (period === 'monthly') {
        const date = new Date(Date.UTC(year, month - 12, 1));
        for (let index = 0; index < 12; index++) {
            const key = `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}`;
            slots.push({key, label: new Intl.DateTimeFormat('id-ID', {month: 'short', year: '2-digit', timeZone: 'UTC'}).format(date)});
            date.setUTCMonth(date.getUTCMonth() + 1);
        }
    } else {
        for (let value = year - 4; value <= year; value++) {
            slots.push({key: String(value), label: String(value)});
        }
    }
    return slots.map(slot => ({...slot, count: counts.get(slot.key) || 0}));
}

function renderTrendChart() {
    if (!dashboardData) return;
    const points = buildTrendPoints(selectedTrendPeriod, dashboardData.period_end, dashboardData.trends[selectedTrendPeriod]);
    const svg = document.getElementById('trend-chart');
    const summary = document.getElementById('trend-summary');
    const width = 760;
    const height = 280;
    const left = 48;
    const right = 12;
    const top = 18;
    const bottom = 232;
    const maxCount = Math.max(1, ...points.map(point => point.count));
    const axisMax = Math.ceil(maxCount / 4) * 4 || 1;
    const x = index => left + index * (width - left - right) / Math.max(1, points.length - 1);
    const y = count => bottom - count / axisMax * (bottom - top);
    svg.replaceChildren();

    for (let tick = 0; tick <= 4; tick++) {
        const value = axisMax * tick / 4;
        const tickY = y(value);
        svg.append(createSvgElement('line', {x1: left, y1: tickY, x2: width - right, y2: tickY, stroke: '#e8edf3', 'stroke-width': 1}));
        const label = createSvgElement('text', {x: left - 8, y: tickY + 4, 'text-anchor': 'end'});
        label.textContent = Math.round(value);
        svg.append(label);
    }

    const coordinates = points.map((point, index) => `${x(index)},${y(point.count)}`);
    const area = createSvgElement('path', {
        d: `M ${x(0)} ${bottom} L ${coordinates.join(' L ')} L ${x(points.length - 1)} ${bottom} Z`,
        fill: 'rgba(57,120,184,0.12)'
    });
    const line = createSvgElement('polyline', {
        points: coordinates.join(' '),
        fill: 'none',
        stroke: '#3978b8',
        'stroke-width': 3,
        'stroke-linecap': 'round',
        'stroke-linejoin': 'round'
    });
    svg.append(area, line);

    points.forEach((point, index) => {
        const marker = createSvgElement('circle', {cx: x(index), cy: y(point.count), r: 4, fill: '#3978b8', stroke: '#fff', 'stroke-width': 2});
        const tooltip = createSvgElement('title');
        tooltip.textContent = `${point.label}: ${point.count} pengajuan`;
        marker.append(tooltip);
        svg.append(marker);
    });

    const labelStep = Math.ceil(points.length / 7);
    points.forEach((point, index) => {
        if (index % labelStep !== 0 && index !== points.length - 1) return;
        const label = createSvgElement('text', {x: x(index), y: 258, 'text-anchor': 'middle'});
        label.textContent = point.label;
        svg.append(label);
    });

    const total = points.reduce((sum, point) => sum + point.count, 0);
    const peak = points.reduce((highest, point) => point.count > highest.count ? point : highest, points[0]);
    const periodLabels = {daily: '14 hari terakhir', monthly: '12 bulan terakhir', yearly: '5 tahun terakhir'};
    summary.textContent = `Total ${periodLabels[selectedTrendPeriod]}: ${total} pengajuan · Tertinggi: ${peak.label} (${peak.count})`;
    svg.setAttribute('aria-label', `Tren ${periodLabels[selectedTrendPeriod]}, total ${total} pengajuan`);
}

function renderStatusDonut(statuses) {
    const svg = document.getElementById('status-donut');
    const legend = document.getElementById('status-donut-legend');
    const total = Object.values(statuses).reduce((sum, count) => sum + Number(count), 0);
    const circumference = 2 * Math.PI * 72;
    let offset = 0;
    svg.replaceChildren();
    legend.replaceChildren();

    Object.entries(STATUS_LABELS).forEach(([status, label]) => {
        const count = Number(statuses[status] || 0);
        if (total > 0 && count > 0) {
            const length = count / total * circumference;
            svg.append(createSvgElement('circle', {
                cx: 110, cy: 110, r: 72,
                stroke: STATUS_COLORS[status],
                'stroke-dasharray': `${length} ${circumference - length}`,
                'stroke-dashoffset': -offset,
                transform: 'rotate(-90 110 110)'
            }));
            offset += length;
        }

        const item = document.createElement('div');
        item.className = 'donut-legend-item';
        const swatch = document.createElement('span');
        swatch.className = 'donut-swatch';
        swatch.style.backgroundColor = STATUS_COLORS[status];
        const labelElement = document.createElement('span');
        labelElement.textContent = `${label}: ${count}`;
        item.append(swatch, labelElement);
        legend.append(item);
    });

    if (total === 0) {
        svg.append(createSvgElement('circle', {cx: 110, cy: 110, r: 72, stroke: '#e8edf3'}));
    }
    const totalText = createSvgElement('text', {x: 110, y: 104, class: 'donut-total'});
    totalText.textContent = total;
    const caption = createSvgElement('text', {x: 110, y: 130, class: 'donut-caption'});
    caption.textContent = 'Total aduan';
    svg.append(totalText, caption);
    svg.setAttribute('aria-label', `Komposisi status dari ${total} aduan`);
}

async function refreshDashboardProgress() {
    const syncMessage = document.getElementById('dashboard-sync');
    try {
        const response = await fetch('api.php?action=dashboard_progress', {cache: 'no-store'});
        const result = await response.json();
        if (!response.ok || result.status !== 'success') {
            throw new Error(result.message || 'Data progres belum dapat diperbarui.');
        }

        const data = result.data;
        dashboardData = {...data, period_end: result.period_end};
        document.querySelector('[data-dashboard-total]').textContent = data.totals.total;
        document.querySelector('[data-dashboard-active]').textContent = data.totals.diproses;
        document.querySelector('[data-dashboard-finished]').textContent = data.totals.selesai;
        renderTrendChart();
        renderStatusDonut(data.statuses);

        const maxStatusCount = Math.max(1, ...Object.values(data.statuses));
        Object.entries(STATUS_LABELS).forEach(([status, label]) => {
            const row = document.querySelector(`[data-status-row="${status}"]`);
            const count = data.statuses[status] || 0;
            row.querySelector('[data-status-count]').textContent = count;
            row.querySelector('[data-status-bar]').style.width = `${(count / maxStatusCount) * 100}%`;
            row.querySelector('.bar-track').setAttribute('aria-label', `${label}: ${count} aduan`);
        });

        const opdChart = document.getElementById('opd-progress-chart');
        opdChart.replaceChildren();
        if (data.opd_progress.length === 0) {
            const emptyMessage = document.createElement('p');
            emptyMessage.className = 'label';
            emptyMessage.textContent = 'Belum ada perangkat daerah.';
            opdChart.append(emptyMessage);
        } else {
            data.opd_progress.forEach(opd => {
                const total = Number(opd.total);
                const active = Number(opd.diproses);
                const finished = Number(opd.selesai);
                const rejected = Number(opd.ditolak);
                const denominator = Math.max(1, total);
                const row = document.createElement('div');
                row.className = 'chart-row';

                const label = document.createElement('span');
                label.className = 'chart-label';
                label.textContent = opd.nama_opd;
                row.append(label);

                const track = document.createElement('div');
                track.className = 'bar-track stacked-track';
                track.setAttribute('role', 'img');
                track.setAttribute('aria-label', `${opd.nama_opd}: ${total} laporan, ${active} sedang ditangani, ${finished} selesai, ${rejected} ditolak`);
                [['diproses', active], ['selesai', finished], ['ditolak', rejected]].forEach(([status, count]) => {
                    const bar = document.createElement('span');
                    bar.className = status;
                    bar.style.width = `${(count / denominator) * 100}%`;
                    track.append(bar);
                });
                row.append(track);

                const value = document.createElement('span');
                value.className = 'chart-value';
                value.textContent = total;
                row.append(value);
                opdChart.append(row);
            });
        }

        syncMessage.textContent = `Data tersinkronisasi dengan portal pengaduan · Terakhir diperbarui ${new Date().toLocaleTimeString('id-ID')}.`;
    } catch (error) {
        syncMessage.textContent = `Sinkronisasi gagal: ${error.message || 'coba lagi sebentar.'}`;
    }
}

document.querySelectorAll('[data-trend-period]').forEach(button => {
    button.addEventListener('click', () => {
        selectedTrendPeriod = button.dataset.trendPeriod;
        document.querySelectorAll('[data-trend-period]').forEach(periodButton => {
            periodButton.setAttribute('aria-pressed', String(periodButton === button));
        });
        renderTrendChart();
    });
});

refreshDashboardProgress();
window.setInterval(refreshDashboardProgress, 15000);

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
