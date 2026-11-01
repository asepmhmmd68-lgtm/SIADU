<?php
require __DIR__ . '/auth.php';

try {
    $conn = getDbConnection();
} catch (Exception $e) {
    error_log('Homepage database error: ' . $e->getMessage());
    http_response_code(500);
    exit('Layanan belum dapat dibuka. Silakan coba kembali nanti.');
}

$user = currentUser();
$total_bulan_ini = 0;
$stat_query = $conn->query("SELECT COUNT(*) AS total FROM pengaduan WHERE YEAR(tgl_masuk) = YEAR(CURRENT_DATE()) AND MONTH(tgl_masuk) = MONTH(CURRENT_DATE())");
if ($stat_query) {
    $stat_data = $stat_query->fetch_assoc();
    $total_bulan_ini = (int)($stat_data['total'] ?? 0);
}
$resolvedPercent = 0;
$resolvedQuery = $conn->query("SELECT COUNT(*) AS total, SUM(status = 'selesai') AS selesai FROM pengaduan");
if ($resolvedQuery && ($resolvedData = $resolvedQuery->fetch_assoc()) && (int)$resolvedData['total'] > 0) {
    $resolvedPercent = (int)round(((int)$resolvedData['selesai'] / (int)$resolvedData['total']) * 100);
}
$averageDays = '—';
$averageQuery = $conn->query("SELECT AVG(TIMESTAMPDIFF(HOUR, tgl_masuk, tgl_selesai)) / 24 AS hari FROM pengaduan WHERE tgl_selesai IS NOT NULL");
if ($averageQuery && ($averageData = $averageQuery->fetch_assoc()) && $averageData['hari'] !== null) {
    $averageDays = number_format((float)$averageData['hari'], 1, ',', '.') . ' hari';
}
$categoriesQuery = $conn->query("SELECT k.id_kategori, k.kategori_key, k.nama_kategori, o.nama_opd
    FROM kategori k JOIN perangkat_daerah o ON o.id_opd = k.id_opd_default
    ORDER BY k.id_kategori");
$categories = $categoriesQuery ? $categoriesQuery->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>SiAdu — Layanan Pengaduan Masyarakat</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/3.48.0/tabler-icons.min.css">
<style>
  :root {
    --navy: #14304D; 
    --navy-2: #1F4A73; 
    --gold: #B9832A; 
    --gold-light: #F1E4CC;
    --bg: #F2F4F6; 
    --surface: #FFFFFF; 
    --text-primary: #14202B; 
    --text-secondary: #57646F;
    --text-muted: #8A959D; 
    --success: #1F7A4D; 
    --success-bg: #E7F3EC;
    --warning: #B9832A; 
    --warning-bg: #F1E4CC; 
    --border: #DFE4E8; 
    --radius: 10px;
  }
  * { box-sizing: border-box; }
  html { scroll-behavior: smooth; }
  body { margin: 0; font-family: 'Public Sans', -apple-system, sans-serif; background: var(--bg); color: var(--text-primary); line-height: 1.6; }
  button { font-family: inherit; }
  .wrap { max-width: 1120px; margin: 0 auto; padding: 0 24px; }

  header { background: var(--navy); color: #fff; position: sticky; top: 0; z-index: 30; }
  .nav { display: flex; align-items: center; justify-content: space-between; padding: 16px 24px; max-width: 1120px; margin: 0 auto; }
  .brand { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 18px; }
  .brand i { font-size: 22px; color: var(--gold); }
  .brand small { display: block; font-weight: 400; font-size: 11px; color: #B9C6D4; margin-top: 1px; }
  nav.links { display: flex; gap: 28px; font-size: 14px; color: #CBD7E1; }
  nav.links a { text-decoration: none; color: inherit; }
  nav.links a:hover { color: #fff; }
  .btn-outline-light { border: 1px solid rgba(255,255,255,0.35); color: #fff; background: transparent; padding: 9px 16px; border-radius: var(--radius); font-size: 14px; font-weight: 500; cursor: pointer; }

  .hero { background: var(--navy); color: #fff; padding: 56px 0 90px; position: relative; }
  .hero-grid { display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 48px; align-items: start; }
  .eyebrow-badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.16); padding: 6px 12px; border-radius: 20px; font-size: 13px; color: #CBD7E1; margin-bottom: 20px; }
  .eyebrow-badge i { color: var(--gold); font-size: 15px; }
  h1.hero-title { font-size: 40px; font-weight: 700; line-height: 1.2; margin: 0 0 16px; letter-spacing: -0.3px; }
  .hero-sub { font-size: 16px; color: #CBD7E1; max-width: 480px; margin: 0 0 28px; }
  
  .hero-ctas { display: flex; gap: 12px; flex-wrap: wrap; position: relative; z-index: 10; }
  .btn-gold { background: var(--gold); color: #221802; border: none; padding: 13px 22px; border-radius: var(--radius); font-size: 15px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
  .btn-ghost-light { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,0.3); padding: 13px 22px; border-radius: var(--radius); font-size: 15px; font-weight: 500; cursor: pointer; }

  .lookup-card { background: var(--surface); color: var(--text-primary); border-radius: 14px; padding: 24px; box-shadow: 0 20px 40px rgba(10,25,45,0.28); }
  .lookup-card h3 { margin: 0 0 4px; font-size: 17px; font-weight: 600; }
  .lookup-card p.hint { margin: 0 0 16px; font-size: 13px; color: var(--text-secondary); }
  .lookup-row { display: flex; gap: 8px; }
  .lookup-row input { flex: 1; padding: 11px 12px; border-radius: var(--radius); border: 1px solid var(--border); font-size: 14px; font-family: inherit; background: var(--bg); color: var(--text-primary); }
  .lookup-row button { background: var(--navy); color: #fff; border: none; padding: 0 16px; border-radius: var(--radius); font-size: 14px; font-weight: 600; cursor: pointer; }
  .status-preview { margin-top: 18px; border-top: 1px solid var(--border); padding-top: 16px; }
  .status-preview .row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px; gap: 12px; }
  .status-preview .row span.label { color: var(--text-secondary); flex-shrink: 0; }
  .pill { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
  .pill.warn { background: var(--warning-bg); color: #7A5719; }
  .pill.success { background: var(--success-bg); color: var(--success); }
  .bar-track { height: 6px; background: var(--border); border-radius: 4px; margin-top: 10px; overflow: hidden; }
  .bar-fill { height: 100%; background: var(--gold); border-radius: 4px; transition: width .4s ease; }
  .sim-btn { margin-top: 14px; width: 100%; background: var(--bg); border: 1px dashed var(--navy-2); color: var(--navy-2); padding: 9px; border-radius: var(--radius); font-size: 13px; font-weight: 600; cursor: pointer; }
  .empty-note { font-size: 13px; color: var(--text-secondary); margin-top: 12px; }

  section { padding: 64px 0; }
  .section-head { max-width: 560px; margin: 0 0 36px; }
  .section-head .kicker { font-size: 13px; font-weight: 600; color: var(--navy-2); margin: 0 0 8px; }
  .section-head h2 { font-size: 26px; font-weight: 700; margin: 0 0 10px; letter-spacing: -0.2px; }
  .section-head p { color: var(--text-secondary); margin: 0; font-size: 15px; }

  .cat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
  .cat-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px; text-align: left; cursor: pointer; transition: border-color .15s ease; width: 100%; }
  .cat-card:hover { border-color: var(--navy-2); }
  .cat-card .icon { width: 40px; height: 40px; border-radius: 9px; background: var(--gold-light); color: var(--gold); display: flex; align-items: center; justify-content: center; font-size: 19px; margin-bottom: 14px; }
  .cat-card h4 { margin: 0 0 4px; font-size: 15px; font-weight: 600; }
  .cat-card p { margin: 0; font-size: 13px; color: var(--text-secondary); }

  .timeline-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 32px 28px; }
  .timeline { display: grid; grid-template-columns: repeat(4, 1fr); position: relative; }
  .timeline::before { content: ""; position: absolute; top: 19px; left: 12.5%; right: 12.5%; height: 2px; background: var(--border); }
  .t-step { position: relative; text-align: center; padding: 0 8px; }
  .t-dot { width: 38px; height: 38px; border-radius: 50%; background: var(--navy); color: #fff; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px; font-size: 16px; position: relative; z-index: 1; border: 4px solid var(--bg); }
  .t-step h4 { margin: 0 0 4px; font-size: 14px; font-weight: 600; }
  .t-step p { margin: 0; font-size: 12.5px; color: var(--text-secondary); }

  .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
  .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 22px; }
  .stat-card .num { font-size: 30px; font-weight: 700; color: var(--navy); letter-spacing: -0.5px; }
  .stat-card .lbl { font-size: 13px; color: var(--text-secondary); margin-top: 4px; }

  footer { background: var(--navy); color: #B9C6D4; padding: 40px 0 28px; margin-top: 8px; }
  .foot-grid { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 24px; font-size: 13px; }
  .foot-brand { color: #fff; font-weight: 700; font-size: 15px; margin-bottom: 6px; }
  .foot-cols { display: flex; gap: 56px; flex-wrap: wrap; }
  .foot-cols h5 { color: #fff; font-size: 13px; font-weight: 600; margin: 0 0 10px; }
  .foot-cols a { display: block; color: #B9C6D4; text-decoration: none; font-size: 13px; margin-bottom: 8px; }
  .foot-bottom { text-align: center; font-size: 12px; color: #7F92A3; margin-top: 28px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.08); }

  .overlay { position: fixed; inset: 0; background: rgba(10,20,32,0.65); display: none; align-items: center; justify-content: center; z-index: 9999; padding: 20px; }
  .overlay.open { display: flex !important; }
  .modal { background: var(--surface); border-radius: 14px; padding: 26px; max-width: 440px; width: 100%; max-height: 88vh; overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
  .modal h3 { margin: 0 0 4px; font-size: 18px; font-weight: 700; }
  .modal p.sub { margin: 0 0 20px; font-size: 13px; color: var(--text-secondary); }
  .field { margin-bottom: 14px; }
  .field label { display: block; font-size: 12.5px; color: var(--text-secondary); margin-bottom: 5px; font-weight: 500; }
  .field select, .field input, .field textarea { width: 100%; padding: 10px 12px; border-radius: var(--radius); border: 1px solid var(--border); font-size: 14px; font-family: inherit; background: var(--bg); color: var(--text-primary); }
  .modal-actions { display: flex; gap: 10px; margin-top: 18px; }
  .modal-actions button { flex: 1; padding: 11px; border-radius: var(--radius); font-size: 14px; font-weight: 600; cursor: pointer; border: none; }
  .btn-cancel { background: var(--bg); color: var(--text-secondary); }
  .btn-submit { background: var(--navy); color: #fff; }

  .ticket-result { text-align: center; padding: 4px 0; }
  .ticket-result i.big { font-size: 40px; color: var(--gold); }
  .ticket-result .num { font-size: 20px; font-weight: 700; margin: 12px 0 4px; letter-spacing: 0.5px; }

  @media (max-width: 860px) {
    .hero-grid { grid-template-columns: 1fr; }
    .cat-grid { grid-template-columns: repeat(2, 1fr); }
    .timeline { grid-template-columns: repeat(2, 1fr); row-gap: 28px; }
    .timeline::before { display: none; }
    .stats-grid { grid-template-columns: 1fr; }
    nav.links { display: none; }
    h1.hero-title { font-size: 30px; }
  }
</style>
</head>
<body>

<header>
  <div class="nav">
    <div class="brand">
      <i class="ti ti-building-bank" aria-hidden="true"></i>
      <span>SiAdu<small>Layanan Pengaduan Masyarakat</small></span>
    </div>
    <nav class="links">
      <a href="#kategori">Kategori</a>
      <a href="#alur">Alur pengaduan</a>
      <a href="#statistik">Statistik</a>
    </nav>
    <div style="display:flex;align-items:center;gap:10px;">
      <button class="btn-outline-light" onclick="document.getElementById('nomorTiket').focus()">
        <i class="ti ti-ticket" aria-hidden="true" style="vertical-align:-2px;margin-right:6px;"></i>Lacak tiket
      </button>
      <?php if ($user): ?>
        <?php if (in_array($user['peran'], ['petugas', 'admin'], true)): ?>
          <a class="btn-outline-light" href="admin.php">Dashboard</a>
        <?php else: ?>
          <a class="btn-outline-light" href="aduan_saya.php">Aduan saya</a>
        <?php endif; ?>
        <form method="post" action="logout.php" style="margin:0;">
          <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
          <button class="btn-outline-light" type="submit">Keluar</button>
        </form>
      <?php else: ?>
        <a class="btn-outline-light" href="login.php">Masuk</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<section class="hero">
  <div class="wrap hero-grid">
    <div>
      <span class="eyebrow-badge"><i class="ti ti-shield-check" aria-hidden="true"></i>Layanan resmi Pemerintah Daerah</span>
      <h1 class="hero-title">Laporkan masalah di sekitar anda, pantau sampai selesai.</h1>
      <p class="hero-sub">Ajukan aduan dalam hitungan menit. Setiap laporan diteruskan langsung ke dinas terkait dan bisa anda pantau kapan saja tanpa perlu datang ke kantor.</p>
      <div class="hero-ctas">
        <button type="button" class="btn-gold" onclick="<?= $user && $user['peran'] === 'warga' ? 'openFormModal()' : "window.location.href='login.php'" ?>"><i class="ti ti-pencil-plus" aria-hidden="true"></i>Ajukan aduan baru</button>
        <button type="button" class="btn-ghost-light" onclick="document.getElementById('alur').scrollIntoView()">Lihat cara kerja</button>
      </div>
    </div>

    <div class="lookup-card">
      <h3>Lacak status aduan</h3>
      <p class="hint">Masukkan nomor tiket Anda untuk mengecek progres penanganan.</p>
      <div class="lookup-row">
        <input type="text" id="nomorTiket" placeholder="Contoh: ADU-2026-XXXXX" aria-label="Nomor tiket">
        <button onclick="cariTiket()">Cari</button>
      </div>
      <div id="statusArea"></div>
    </div>
  </div>
</section>

<section id="kategori">
  <div class="wrap">
    <div class="section-head">
      <p class="kicker">Kategori aduan</p>
      <h2>Pilih kategori sesuai masalah anda</h2>
      <p>Klik salah satu kategori untuk langsung mengajukan aduan — setiap kategori otomatis diteruskan ke dinas yang menangani.</p>
    </div>
    <div class="cat-grid" id="catGrid"></div>
  </div>
</section>

<section id="alur">
  <div class="wrap">
    <div class="section-head">
      <p class="kicker">Alur pengaduan</p>
      <h2>Empat langkah dari laporan sampai selesai</h2>
      <p>Prosesnya transparan — anda bisa melihat di tahap mana aduan anda berada setiap saat.</p>
    </div>
    <div class="timeline-wrap">
      <div class="timeline">
        <div class="t-step"><div class="t-dot"><i class="ti ti-pencil" aria-hidden="true"></i></div><h4>Ajukan aduan</h4><p>Isi kategori, lokasi, dan deskripsi masalah.</p></div>
        <div class="t-step"><div class="t-dot"><i class="ti ti-ticket" aria-hidden="true"></i></div><h4>Tiket diterbitkan</h4><p>Anda menerima nomor tiket untuk melacak status.</p></div>
        <div class="t-step"><div class="t-dot"><i class="ti ti-arrow-forward-up" aria-hidden="true"></i></div><h4>Diteruskan ke dinas</h4><p>Aduan otomatis dikirim ke dinas terkait.</p></div>
        <div class="t-step"><div class="t-dot"><i class="ti ti-circle-check" aria-hidden="true"></i></div><h4>Selesai</h4><p>Dinas menyelesaikan dan menutup laporan.</p></div>
      </div>
    </div>
  </div>
</section>

<section id="statistik">
  <div class="wrap">
    <div class="section-head">
      <p class="kicker">Statistik layanan</p>
      <h2>Transparansi kinerja penanganan aduan</h2>
    </div>
    <div class="stats-grid">
      <div class="stat-card"><div class="num" id="statTotal"><?= number_format($total_bulan_ini, 0, ',', '.') ?></div><div class="lbl">Total aduan diterima bulan ini</div></div>
      <div class="stat-card"><div class="num"><?= $resolvedPercent ?>%</div><div class="lbl">Aduan telah terselesaikan</div></div>
      <div class="stat-card"><div class="num"><?= escapeHtml($averageDays) ?></div><div class="lbl">Rata-rata waktu penyelesaian</div></div>
    </div>
  </div>
</section>

<footer>
  <div class="wrap foot-grid">
    <div>
      <div class="foot-brand">SiAdu</div>
      <p style="max-width:220px;margin:0;">Sistem informasi pengaduan masyarakat Pemerintah Daerah.</p>
    </div>
    <div class="foot-cols">
      <div>
        <h5>Layanan</h5>
        <a href="#kategori">Ajukan aduan</a>
        <a href="#" onclick="document.getElementById('nomorTiket').focus();return false;">Lacak tiket</a>
        <a href="#alur">Alur pengaduan</a>
      </div>
      <div>
        <h5>Bantuan</h5>
        <a href="#" onclick="return false;">Pertanyaan umum</a>
        <a href="#" onclick="return false;">Hubungi kami</a>
      </div>
    </div>
  </div>
  <div class="foot-bottom wrap">© 2026 Pemerintah Daerah — Sistem Informasi Pengaduan Masyarakat</div>
</footer>

<div class="overlay" id="formOverlay">
  <div class="modal">
    <h3>Ajukan aduan</h3>
    <p class="sub">Isi data di bawah untuk mengirimkan pengaduan ke dinas terkait.</p>
    
    <div class="field">
      <label for="fKategori">Kategori aduan</label>
      <select id="fKategori">
        <?php foreach ($categories as $category): ?>
          <option value="<?= (int)$category['id_kategori'] ?>"><?= escapeHtml($category['nama_kategori']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="fJudul">Judul aduan</label>
      <input type="text" id="fJudul" maxlength="180" placeholder="Ringkas masalah yang dilaporkan">
    </div>
    
    <div class="field">
      <label for="fLokasi">Lokasi</label>
      <input type="text" id="fLokasi" placeholder="Contoh: Jl. Merdeka No. 12">
    </div>
    
    <div class="field">
      <label for="fDeskripsi">Deskripsi</label>
      <textarea id="fDeskripsi" rows="3" placeholder="Jelaskan masalah yang anda temukan"></textarea>
    </div>
    
    <div class="field">
      <label for="fFoto">Foto Pendukung (Opsional)</label>
      <input type="file" id="fFoto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
    </div>

    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeModal('formOverlay')">Batal</button>
      <button class="btn-submit" onclick="kirimAduan()">Kirim aduan</button>
    </div>
  </div>
</div>

<div class="overlay" id="ticketOverlay">
  <div class="modal">
    <div class="ticket-result">
      <i class="ti ti-circle-check-filled big" aria-hidden="true"></i>
      <h3>Aduan berhasil diajukan</h3>
      <p class="sub" style="margin-bottom:6px;">Nomor tiket anda</p>
      <div class="num" id="hasilTiketNomor">ADU-2026-00000</div>
      <p class="sub">Simpan nomor ini untuk melacak status aduan kapan saja.</p>
    </div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeModal('ticketOverlay')">Tutup</button>
      <button class="btn-submit" onclick="lacakDariModal()">Lacak tiket ini</button>
    </div>
  </div>
</div>

<script>
  const KATEGORI = <?= json_encode($categories, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const IS_WARGA = <?= json_encode($user && $user['peran'] === 'warga') ?>;
  const CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;
  const STATUS_PROGRESS = { diterima: 25, diverifikasi: 50, diproses: 75, selesai: 100, ditolak: 100 };
  const CATEGORY_ICONS = { jalan: 'ti-road', sampah: 'ti-trash', lampu: 'ti-bulb-off', banjir: 'ti-droplet', fasum: 'ti-building-community', lain: 'ti-dots' };
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  }[char]));

  let currentTicket = null;

  function renderCategories() {
    const grid = document.getElementById('catGrid');
    if (!grid) return;
    grid.innerHTML = KATEGORI.map(k => `
      <button type="button" class="cat-card" onclick="${IS_WARGA ? `openFormModal(${Number(k.id_kategori)})` : "window.location.href='login.php'"}">
        <div class="icon"><i class="ti ${CATEGORY_ICONS[k.kategori_key] || 'ti-message-circle'}" aria-hidden="true"></i></div>
        <h4>${escapeHtml(k.nama_kategori)}</h4>
        <p>${escapeHtml(k.nama_opd)}</p>
      </button>
    `).join('');
  }

  function openFormModal(kategoriId) {
    if (!IS_WARGA) {
      window.location.href = 'login.php';
      return;
    }
    const overlay = document.getElementById('formOverlay');
    if (overlay) overlay.classList.add('open');
    
    const sel = document.getElementById('fKategori');
    if (sel && kategoriId) sel.value = String(kategoriId);
    
    if (document.getElementById('fJudul')) document.getElementById('fJudul').value = '';
    if (document.getElementById('fLokasi')) document.getElementById('fLokasi').value = '';
    if (document.getElementById('fDeskripsi')) document.getElementById('fDeskripsi').value = '';
    if (document.getElementById('fFoto')) document.getElementById('fFoto').value = '';
  }

  function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('open');
  }

  async function kirimAduan() {
    const kategoriId = document.getElementById('fKategori').value;
    const judul = document.getElementById('fJudul').value.trim();
    const lokasi = document.getElementById('fLokasi').value.trim();
    const deskripsi = document.getElementById('fDeskripsi').value.trim();
    const fotoFile = document.getElementById('fFoto').files[0];

    if (!judul || !lokasi || !deskripsi) {
      alert('Harap isi judul, lokasi, dan deskripsi aduan!');
      return;
    }

    const formData = new FormData();
    formData.append('id_kategori', kategoriId);
    formData.append('judul', judul);
    formData.append('lokasi', lokasi);
    formData.append('deskripsi', deskripsi);
    if (fotoFile) {
      formData.append('foto', fotoFile);
    }

    try {
      const res = await fetch('api.php?action=kirim', {
        method: 'POST',
        headers: {'X-CSRF-Token': CSRF_TOKEN},
        body: formData
      });

      const data = await res.json();

      if (data.status === 'success') {
        closeModal('formOverlay');
        document.getElementById('hasilTiketNomor').textContent = data.no_tiket;
        currentTicket = data.no_tiket;
        document.getElementById('ticketOverlay').classList.add('open');
      } else {
        alert(data.message || 'Gagal mengirim aduan.');
      }
    } catch (e) {
      console.error(e);
      alert('Terjadi kesalahan koneksi backend ke api.php.');
    }
  }

  async function cariTiket() {
    const no = document.getElementById('nomorTiket').value.trim();
    const statusArea = document.getElementById('statusArea');

    if (!no) {
      statusArea.innerHTML = '<p class="empty-note">Masukkan nomor tiket terlebih dahulu.</p>';
      return;
    }

    try {
      const res = await fetch(`api.php?action=lacak&no_tiket=${encodeURIComponent(no)}`);
      const data = await res.json();

      if (data.status === 'success') {
        const item = data.data;
        const isDone = item.status === 'selesai' || item.status === 'ditolak';
        const history = (item.riwayat || []).map(entry => `
          <div class="row"><span class="label">${escapeHtml(entry.waktu)}</span>
          <span class="val">${escapeHtml(entry.status_label)}${entry.catatan ? `<br>${escapeHtml(entry.catatan)}` : ''}</span></div>
        `).join('');
        const attachments = (item.lampiran || []).map(path => `
          <div class="row"><span class="label">Lampiran:</span>
          <span class="val"><a href="${escapeHtml(path)}" target="_blank" rel="noopener">Lihat berkas</a></span></div>
        `).join('');

        statusArea.innerHTML = `
          <div class="status-preview">
            <div class="row">
              <span class="label">Kategori:</span>
              <span class="val"><strong>${escapeHtml(item.nama_kategori)}</strong></span>
            </div>
            <div class="row">
              <span class="label">Penanggung Jawab:</span>
              <span class="val">${escapeHtml(item.nama_opd)}</span>
            </div>
            ${item.judul ? `<div class="row">
              <span class="label">Aduan:</span>
              <span class="val">${escapeHtml(item.judul)}</span>
            </div>` : ''}
            <div class="row">
              <span class="label">Status:</span>
              <span class="val">
                <span class="pill ${isDone ? 'success' : 'warn'}">${escapeHtml(item.status_label)}</span>
              </span>
            </div>
            ${attachments}
            <div class="bar-track">
              <div class="bar-fill" style="width: ${STATUS_PROGRESS[item.status] || 0}%"></div>
            </div>
            <h4>Riwayat status</h4>
            ${history || '<p class="empty-note">Belum ada riwayat status.</p>'}
          </div>
        `;
      } else {
        statusArea.innerHTML = `<p class="empty-note">${escapeHtml(data.message || 'Nomor tiket tidak ditemukan.')}</p>`;
      }
    } catch (e) {
      console.error(e);
      statusArea.innerHTML = '<p class="empty-note">Gagal mengambil data dari server.</p>';
    }
  }

  function lacakDariModal() {
    closeModal('ticketOverlay');
    if (currentTicket) {
      document.getElementById('nomorTiket').value = currentTicket;
      cariTiket();
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    renderCategories();
    const ticketFromLink = new URLSearchParams(window.location.search).get('no_tiket');
    if (ticketFromLink) {
      document.getElementById('nomorTiket').value = ticketFromLink;
      cariTiket();
    }
  });
</script>

</body>
</html>