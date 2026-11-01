<?php
require __DIR__ . '/auth.php';

if (currentUser()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['nama'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['kata_sandi'] ?? '');
    $confirm = (string)($_POST['konfirmasi_kata_sandi'] ?? '');

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
    } elseif ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $error = 'Periksa kembali nama dan alamat email.';
    } elseif (strlen($password) < 8) {
        $error = 'Kata sandi harus memiliki minimal 8 karakter.';
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi kata sandi tidak sama.';
    } else {
        try {
            $conn = getDbConnection();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO pengguna (nama, email, kata_sandi, peran) VALUES (?, ?, ?, 'warga')");
            $stmt->bind_param('sss', $name, $email, $hash);
            $stmt->execute();
            header('Location: login.php?daftar=berhasil');
            exit;
        } catch (mysqli_sql_exception $e) {
            if ((int)$e->getCode() === 1062) {
                $error = 'Email tersebut sudah terdaftar.';
            } else {
                error_log('Registration error: ' . $e->getMessage());
                $error = 'Pendaftaran belum dapat diproses. Silakan coba kembali.';
            }
        } catch (Throwable $e) {
            error_log('Registration error: ' . $e->getMessage());
            $error = 'Pendaftaran belum dapat diproses. Silakan coba kembali.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar — SiAdu</title>
    <style>
        body{margin:0;background:#f2f4f6;color:#14202b;font:16px Arial,sans-serif}
        main{max-width:440px;margin:7vh auto;padding:32px;background:#fff;border:1px solid #dfe4e8;border-radius:12px}
        h1{margin:0 0 8px;color:#14304d}p{color:#57646f;line-height:1.5}
        label{display:block;margin:16px 0 6px;font-weight:600}
        input{width:100%;padding:12px;border:1px solid #cbd2d8;border-radius:8px;font:inherit;box-sizing:border-box}
        button{margin-top:22px;width:100%;padding:12px;border:0;border-radius:8px;background:#14304d;color:#fff;font:inherit;font-weight:bold;cursor:pointer}
        a{color:#1f4a73}.error{padding:10px;background:#fff0ee;color:#8b2e24;border-radius:8px}
    </style>
</head>
<body>
<main>
    <h1>Daftar akun warga</h1>
    <p>Akun diperlukan untuk mengirim aduan dan memantau laporan Anda.</p>
    <?php if ($error): ?><p class="error"><?= escapeHtml($error) ?></p><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
        <label for="nama">Nama lengkap</label>
        <input id="nama" name="nama" autocomplete="name" required maxlength="120" value="<?= escapeHtml((string)($_POST['nama'] ?? '')) ?>">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" required maxlength="190" value="<?= escapeHtml((string)($_POST['email'] ?? '')) ?>">
        <label for="kata_sandi">Kata sandi (minimal 8 karakter)</label>
        <input id="kata_sandi" name="kata_sandi" type="password" autocomplete="new-password" required minlength="8">
        <label for="konfirmasi_kata_sandi">Ulangi kata sandi</label>
        <input id="konfirmasi_kata_sandi" name="konfirmasi_kata_sandi" type="password" autocomplete="new-password" required minlength="8">
        <button type="submit">Buat akun</button>
    </form>
    <p>Sudah memiliki akun? <a href="login.php">Masuk</a>.</p>
    <p><a href="index.php">Kembali ke beranda</a></p>
</main>
</body>
</html>
