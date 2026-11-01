<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/config.php';

try {
    $conn = getDbConnection();
    $opds = $conn->query('SELECT id_opd, nama_opd FROM perangkat_daerah ORDER BY nama_opd')->fetch_all(MYSQLI_ASSOC);
    if (!$opds) {
        fwrite(STDERR, "Daftar OPD kosong. Jalankan database.sql terlebih dahulu.\n");
        exit(1);
    }

    $read = static function (string $prompt): string {
        fwrite(STDOUT, $prompt);
        return trim((string)fgets(STDIN));
    };

    $name = $read('Nama petugas: ');
    $email = strtolower($read('Email: '));
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fwrite(STDERR, "Nama atau email tidak valid.\n");
        exit(1);
    }

    fwrite(STDOUT, "Daftar OPD:\n");
    foreach ($opds as $opd) {
        fwrite(STDOUT, $opd['id_opd'] . '. ' . $opd['nama_opd'] . "\n");
    }
    $opdId = filter_var($read('ID OPD petugas: '), FILTER_VALIDATE_INT);
    if (!$opdId || !in_array($opdId, array_column($opds, 'id_opd'), false)) {
        fwrite(STDERR, "ID OPD tidak ditemukan.\n");
        exit(1);
    }

    $role = $read('Peran (petugas/admin) [petugas]: ');
    $role = $role === '' ? 'petugas' : $role;
    if (!in_array($role, ['petugas', 'admin'], true)) {
        fwrite(STDERR, "Peran harus petugas atau admin.\n");
        exit(1);
    }
    $password = $read('Kata sandi (minimal 8 karakter; input terlihat di terminal): ');
    if (strlen($password) < 8) {
        fwrite(STDERR, "Kata sandi harus memiliki minimal 8 karakter.\n");
        exit(1);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $conn->prepare('INSERT INTO pengguna (nama, email, kata_sandi, peran, id_opd) VALUES (?, ?, ?, ?, ?)');
    $insert->bind_param('ssssi', $name, $email, $hash, $role, $opdId);
    $insert->execute();
    fwrite(STDOUT, "Akun $role berhasil dibuat untuk $email.\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Gagal membuat akun petugas: " . $e->getMessage() . "\n");
    exit(1);
}
