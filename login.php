<?php
require __DIR__ . '/auth.php';

if (currentUser()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['kata_sandi'] ?? '');
        try {
            $conn = getDbConnection();
            $stmt = $conn->prepare('SELECT id_pengguna, nama, email, kata_sandi, peran, id_opd FROM pengguna WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if ($user && password_verify($password, $user['kata_sandi'])) {
                session_regenerate_id(true);
                unset($user['kata_sandi']);
                $_SESSION['user'] = $user;
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: ' . (in_array($user['peran'], ['petugas', 'admin'], true) ? 'admin.php' : 'index.php'));
                exit;
            }
            $error = 'Email atau kata sandi tidak sesuai.';
        } catch (Throwable $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'Login belum dapat diproses. Silakan coba kembali.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — SiAdu</title>
    <style>
        body{margin:0;background:#f2f4f6;color:#14202b;font:16px Arial,sans-serif}
        main{max-width:440px;margin:9vh auto;padding:32px;background:#fff;border:1px solid #dfe4e8;border-radius:12px}
        h1{margin:0 0 8px;color:#14304d}p{color:#57646f;line-height:1.5}
        label{display:block;margin:18px 0 6px;font-weight:600}
        input{width:100%;padding:12px;border:1px solid #cbd2d8;border-radius:8px;font:inherit;box-sizing:border-box}
        button{margin-top:22px;width:100%;padding:12px;border:0;border-radius:8px;background:#14304d;color:#fff;font:inherit;font-weight:bold;cursor:pointer}
        a{color:#1f4a73}.error,.success{padding:10px;border-radius:8px}.error{background:#fff0ee;color:#8b2e24}.success{background:#e7f3ec;color:#1f7a4d}
    </style>
</head>
<body>
<main>
    <h1>Masuk ke SiAdu</h1>
    <p>Gunakan akun warga atau petugas untuk melanjutkan.</p>
    <?php if (($_GET['daftar'] ?? '') === 'berhasil'): ?><p class="success">Pendaftaran berhasil. Silakan masuk menggunakan akun Anda.</p><?php endif; ?>
    <?php if ($error): ?><p class="error"><?= escapeHtml($error) ?></p><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" required maxlength="190" value="<?= escapeHtml((string)($_POST['email'] ?? '')) ?>">
        <label for="kata_sandi">Kata sandi</label>
        <input id="kata_sandi" name="kata_sandi" type="password" autocomplete="current-password" required>
        <button type="submit">Masuk</button>
    </form>
    <p>Belum punya akun warga? <a href="register.php">Daftar di sini</a>.</p>
    <p><a href="index.php">Kembali ke beranda</a></p>
</main>
</body>
</html>
