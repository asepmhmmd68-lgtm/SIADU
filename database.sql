CREATE DATABASE IF NOT EXISTS siadu_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE siadu_db;

CREATE TABLE IF NOT EXISTS pengaduan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nomor_tiket VARCHAR(30) NOT NULL UNIQUE,
    kategori_key VARCHAR(50) NOT NULL,
    kategori_nama VARCHAR(100) NOT NULL,
    dinas VARCHAR(100) NOT NULL,
    lokasi VARCHAR(255) NOT NULL,
    deskripsi TEXT NOT NULL,
    stage_idx TINYINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE pengaduan
    ADD COLUMN IF NOT EXISTS foto VARCHAR(255) NULL AFTER deskripsi,
    MODIFY stage_idx TINYINT NOT NULL DEFAULT 0;

INSERT INTO pengaduan (nomor_tiket, kategori_key, kategori_nama, dinas, lokasi, deskripsi, foto, stage_idx, created_at)
SELECT 'ADU-2026-AB12C', 'jalan', 'Jalan & jembatan rusak', 'Dinas Pekerjaan Umum', 'Jl. Merdeka No. 10', 'Lubang besar di sisi jalan dekat sekolah.', NULL, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM pengaduan WHERE nomor_tiket = 'ADU-2026-AB12C');

INSERT INTO pengaduan (nomor_tiket, kategori_key, kategori_nama, dinas, lokasi, deskripsi, foto, stage_idx, created_at)
SELECT 'ADU-2026-CD34D', 'sampah', 'Kebersihan & sampah', 'Dinas Lingkungan Hidup', 'Pasar Tradisional', 'TPS penuh sejak 2 hari lalu.', NULL, 2, NOW()
WHERE NOT EXISTS (SELECT 1 FROM pengaduan WHERE nomor_tiket = 'ADU-2026-CD34D');

INSERT INTO pengaduan (nomor_tiket, kategori_key, kategori_nama, dinas, lokasi, deskripsi, foto, stage_idx, created_at)
SELECT 'ADU-2026-EF56G', 'lampu', 'Lampu jalan mati', 'Dinas Perhubungan', 'Jl. Pahlawan Raya', 'Lampu jalan di dekat bundaran mati total.', NULL, 3, NOW()
WHERE NOT EXISTS (SELECT 1 FROM pengaduan WHERE nomor_tiket = 'ADU-2026-EF56G');