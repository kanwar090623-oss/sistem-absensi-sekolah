-- SiAbsen - database.sql
-- Impor file ini lewat phpMyAdmin (tab Import) atau: mysql -u root -p < database.sql

CREATE DATABASE IF NOT EXISTS siabsen_baru CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE siabsen_baru;

-- 1) Akun guru / petugas yang login
CREATE TABLE IF NOT EXISTS users (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nama       VARCHAR(100) NOT NULL,
  email      VARCHAR(100) NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  foto       VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2) Kelas
CREATE TABLE IF NOT EXISTS kelas (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nama_kelas VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 3) Siswa (relasi: siswa -> kelas)
CREATE TABLE IF NOT EXISTS siswa (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  nisn          VARCHAR(20)  NOT NULL UNIQUE,
  nama          VARCHAR(100) NOT NULL,
  jenis_kelamin ENUM('L','P') NOT NULL DEFAULT 'L',
  kelas_id      INT NOT NULL,
  foto          VARCHAR(255) NULL,
  FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 4) Absensi (relasi: absensi -> siswa). Satu siswa hanya punya satu catatan per tanggal.
CREATE TABLE IF NOT EXISTS absensi (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  siswa_id   INT NOT NULL,
  tanggal    DATE NOT NULL,
  status     ENUM('H','I','S','A') NOT NULL DEFAULT 'H', -- Hadir, Izin, Sakit, Alpa
  keterangan VARCHAR(150) NULL,
  UNIQUE KEY uq_siswa_tanggal (siswa_id, tanggal),
  KEY idx_tanggal (tanggal),
  FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Data contoh (boleh dihapus lewat menu Siswa dan Kelas)
INSERT IGNORE INTO kelas (id, nama_kelas) VALUES
  (1, 'X IPA 1'),
  (2, 'X IPS 1'),
  (3, 'XI IPA 1');

INSERT IGNORE INTO siswa (nisn, nama, jenis_kelamin, kelas_id) VALUES
  ('24001', 'Ahmad Fauzi',       'L', 1),
  ('24002', 'Bunga Lestari',     'P', 1),
  ('24003', 'Citra Maharani',    'P', 1),
  ('24004', 'Dimas Prasetyo',    'L', 1),
  ('24005', 'Eka Putri Wulandari','P', 2),
  ('24006', 'Fajar Nugroho',     'L', 2),
  ('24007', 'Gita Ayu Safitri',  'P', 2),
  ('23001', 'Hendra Saputra',    'L', 3),
  ('23002', 'Intan Permata Sari','P', 3);
