CREATE DATABASE IF NOT EXISTS siadu_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE siadu_db;

SET @legacy_pengaduan = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'pengaduan'
      AND column_name = 'stage_idx'
);
SET @legacy_backup = (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'pengaduan_lama'
);
SET @rename_legacy = IF(
    @legacy_pengaduan > 0 AND @legacy_backup = 0,
    'RENAME TABLE pengaduan TO pengaduan_lama',
    'SELECT 1'
);
PREPARE rename_statement FROM @rename_legacy;
EXECUTE rename_statement;
DEALLOCATE PREPARE rename_statement;

CREATE TABLE IF NOT EXISTS perangkat_daerah (
    id_opd INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_opd VARCHAR(150) NOT NULL UNIQUE,
    bidang_layanan VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pengguna (
    id_pengguna INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    kata_sandi VARCHAR(255) NOT NULL,
    peran ENUM('warga', 'petugas', 'admin') NOT NULL DEFAULT 'warga',
    id_opd INT UNSIGNED NULL,
    dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pengguna_opd FOREIGN KEY (id_opd)
        REFERENCES perangkat_daerah (id_opd) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kategori (
    id_kategori INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kategori_key VARCHAR(50) NOT NULL UNIQUE,
    nama_kategori VARCHAR(120) NOT NULL,
    id_opd_default INT UNSIGNED NOT NULL,
    CONSTRAINT fk_kategori_opd FOREIGN KEY (id_opd_default)
        REFERENCES perangkat_daerah (id_opd) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pengaduan (
    id_pengaduan BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    no_tiket VARCHAR(30) NOT NULL UNIQUE,
    id_pengguna INT UNSIGNED NULL,
    id_kategori INT UNSIGNED NOT NULL,
    id_opd INT UNSIGNED NOT NULL,
    judul VARCHAR(180) NOT NULL,
    deskripsi TEXT NOT NULL,
    lokasi VARCHAR(255) NOT NULL,
    status ENUM('diterima', 'diverifikasi', 'diproses', 'selesai', 'ditolak')
        NOT NULL DEFAULT 'diterima',
    tgl_masuk TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tgl_selesai TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_pengaduan_pengguna (id_pengguna),
    INDEX idx_pengaduan_opd_status (id_opd, status),
    INDEX idx_pengaduan_masuk (tgl_masuk),
    CONSTRAINT fk_pengaduan_pengguna FOREIGN KEY (id_pengguna)
        REFERENCES pengguna (id_pengguna) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pengaduan_kategori FOREIGN KEY (id_kategori)
        REFERENCES kategori (id_kategori) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pengaduan_opd FOREIGN KEY (id_opd)
        REFERENCES perangkat_daerah (id_opd) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lampiran (
    id_lampiran BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pengaduan BIGINT UNSIGNED NOT NULL,
    nama_berkas VARCHAR(255) NOT NULL,
    lokasi_berkas VARCHAR(500) NOT NULL,
    tipe_berkas VARCHAR(100) NOT NULL,
    ukuran_kb INT UNSIGNED NOT NULL,
    diunggah_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lampiran_pengaduan (id_pengaduan),
    CONSTRAINT fk_lampiran_pengaduan FOREIGN KEY (id_pengaduan)
        REFERENCES pengaduan (id_pengaduan) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS riwayat_status (
    id_riwayat BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pengaduan BIGINT UNSIGNED NOT NULL,
    id_pengguna INT UNSIGNED NULL,
    status ENUM('diterima', 'diverifikasi', 'diproses', 'selesai', 'ditolak') NOT NULL,
    catatan TEXT NULL,
    waktu TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_riwayat_pengaduan_waktu (id_pengaduan, waktu),
    CONSTRAINT fk_riwayat_pengaduan FOREIGN KEY (id_pengaduan)
        REFERENCES pengaduan (id_pengaduan) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_riwayat_pengguna FOREIGN KEY (id_pengguna)
        REFERENCES pengguna (id_pengguna) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO perangkat_daerah (nama_opd, bidang_layanan)
VALUES
    ('Dinas Pekerjaan Umum', 'Jalan, jembatan, dan drainase'),
    ('Dinas Lingkungan Hidup', 'Kebersihan dan fasilitas lingkungan'),
    ('Dinas Perhubungan', 'Penerangan dan lalu lintas'),
    ('Sekretariat Daerah', 'Pengaduan umum')
ON DUPLICATE KEY UPDATE nama_opd = VALUES(nama_opd);

INSERT INTO kategori (kategori_key, nama_kategori, id_opd_default)
SELECT 'jalan', 'Jalan & jembatan rusak', id_opd FROM perangkat_daerah WHERE nama_opd = 'Dinas Pekerjaan Umum'
ON DUPLICATE KEY UPDATE kategori_key = VALUES(kategori_key);
INSERT INTO kategori (kategori_key, nama_kategori, id_opd_default)
SELECT 'sampah', 'Kebersihan & sampah', id_opd FROM perangkat_daerah WHERE nama_opd = 'Dinas Lingkungan Hidup'
ON DUPLICATE KEY UPDATE kategori_key = VALUES(kategori_key);
INSERT INTO kategori (kategori_key, nama_kategori, id_opd_default)
SELECT 'lampu', 'Lampu jalan mati', id_opd FROM perangkat_daerah WHERE nama_opd = 'Dinas Perhubungan'
ON DUPLICATE KEY UPDATE kategori_key = VALUES(kategori_key);
INSERT INTO kategori (kategori_key, nama_kategori, id_opd_default)
SELECT 'banjir', 'Banjir & drainase', id_opd FROM perangkat_daerah WHERE nama_opd = 'Dinas Pekerjaan Umum'
ON DUPLICATE KEY UPDATE kategori_key = VALUES(kategori_key);
INSERT INTO kategori (kategori_key, nama_kategori, id_opd_default)
SELECT 'fasum', 'Fasilitas umum rusak', id_opd FROM perangkat_daerah WHERE nama_opd = 'Dinas Lingkungan Hidup'
ON DUPLICATE KEY UPDATE kategori_key = VALUES(kategori_key);
INSERT INTO kategori (kategori_key, nama_kategori, id_opd_default)
SELECT 'lain', 'Lainnya', id_opd FROM perangkat_daerah WHERE nama_opd = 'Sekretariat Daerah'
ON DUPLICATE KEY UPDATE kategori_key = VALUES(kategori_key);

SET @migrate_legacy_opds = IF(
    (SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = 'pengaduan_lama') > 0,
    'INSERT IGNORE INTO perangkat_daerah (nama_opd, bidang_layanan)
     SELECT DISTINCT dinas, ''Migrasi data pengaduan sebelumnya''
     FROM pengaduan_lama WHERE dinas IS NOT NULL AND dinas <> ''''',
    'SELECT 1'
);
PREPARE legacy_opd_statement FROM @migrate_legacy_opds;
EXECUTE legacy_opd_statement;
DEALLOCATE PREPARE legacy_opd_statement;

SET @migrate_legacy_categories = IF(
    (SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = 'pengaduan_lama') > 0,
    'INSERT INTO kategori (kategori_key, nama_kategori, id_opd_default)
     SELECT l.kategori_key, MAX(l.kategori_nama), MIN(o.id_opd)
     FROM pengaduan_lama l
     JOIN perangkat_daerah o ON o.nama_opd = l.dinas
     WHERE l.kategori_key IS NOT NULL AND l.kategori_key <> ''''
     GROUP BY l.kategori_key
     ON DUPLICATE KEY UPDATE nama_kategori = VALUES(nama_kategori)',
    'SELECT 1'
);
PREPARE legacy_category_statement FROM @migrate_legacy_categories;
EXECUTE legacy_category_statement;
DEALLOCATE PREPARE legacy_category_statement;

SET @migrate_legacy = IF(
    (SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = 'pengaduan_lama') > 0,
    'INSERT IGNORE INTO pengaduan (no_tiket, id_pengguna, id_kategori, id_opd, judul, deskripsi, lokasi, status, tgl_masuk, tgl_selesai)
     SELECT l.nomor_tiket, NULL, k.id_kategori, o.id_opd,
            LEFT(l.kategori_nama, 180), l.deskripsi, l.lokasi,
            CASE l.stage_idx WHEN 1 THEN ''diverifikasi'' WHEN 2 THEN ''diproses'' WHEN 3 THEN ''selesai'' ELSE ''diterima'' END,
            l.created_at, CASE WHEN l.stage_idx = 3 THEN l.updated_at ELSE NULL END
     FROM pengaduan_lama l
     JOIN perangkat_daerah o ON o.nama_opd = l.dinas
     JOIN kategori k ON k.kategori_key = l.kategori_key',
    'SELECT 1'
);
PREPARE migrate_statement FROM @migrate_legacy;
EXECUTE migrate_statement;
DEALLOCATE PREPARE migrate_statement;

SET @migrate_history = IF(
    (SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = 'pengaduan_lama') > 0,
    'INSERT INTO riwayat_status (id_pengaduan, id_pengguna, status, catatan, waktu)
     SELECT p.id_pengaduan, NULL, p.status, ''Migrasi data pengaduan lama'', p.tgl_masuk
     FROM pengaduan p
     JOIN pengaduan_lama l ON l.nomor_tiket = p.no_tiket
     WHERE NOT EXISTS (SELECT 1 FROM riwayat_status r WHERE r.id_pengaduan = p.id_pengaduan)',
    'SELECT 1'
);
PREPARE history_statement FROM @migrate_history;
EXECUTE history_statement;
DEALLOCATE PREPARE history_statement;

SET @migrate_attachments = IF(
    (SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = 'pengaduan_lama') > 0,
    'INSERT INTO lampiran (id_pengaduan, nama_berkas, lokasi_berkas, tipe_berkas, ukuran_kb)
     SELECT p.id_pengaduan, SUBSTRING_INDEX(l.foto, ''/'', -1), l.foto, ''application/octet-stream'', 0
     FROM pengaduan_lama l
     JOIN pengaduan p ON p.no_tiket = l.nomor_tiket
     WHERE l.foto IS NOT NULL AND l.foto <> ''''
       AND NOT EXISTS (SELECT 1 FROM lampiran a WHERE a.id_pengaduan = p.id_pengaduan AND a.lokasi_berkas = l.foto)',
    'SELECT 1'
);
PREPARE attachment_statement FROM @migrate_attachments;
EXECUTE attachment_statement;
DEALLOCATE PREPARE attachment_statement;
