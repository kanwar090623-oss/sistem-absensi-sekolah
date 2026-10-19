# SiAbsen

Website absensi siswa sederhana: PHP (PDO) + MySQL, tanpa framework dan tanpa library tambahan.

## Fitur
- Daftar, masuk, dan keluar (kata sandi di-hash, proteksi CSRF)
- Dashboard: ringkasan hari ini, grafik 7 hari, progres per kelas, alpa terbanyak bulan ini
- Absensi: pilih kelas dan tanggal, tandai Hadir/Izin/Sakit/Alpa, keterangan, pencarian, tombol "Semua hadir"
- Rekap bulanan per kelas, bisa dicetak dan diekspor ke CSV (terbuka di Excel)
- Kelola siswa dan kelas (tambah, ubah, hapus, cari)

## Cara pasang (XAMPP / Laragon)
1. Salin folder `siabsen` ke `htdocs` (XAMPP) atau `www` (Laragon).
2. Buka phpMyAdmin, pilih tab **Import**, lalu impor `database.sql`.
3. Jika perlu, ubah `DB_HOST`, `DB_USER`, `DB_PASS` di `config.php` (bawaan XAMPP: user `root`, tanpa kata sandi).
4. Buka `http://localhost/siabsen`, klik **Daftar sekarang**, lalu masuk.

Kebutuhan: PHP 7.4 atau lebih baru, MySQL 5.7+ atau MariaDB 10.3+.

## Struktur database (4 tabel, 2 relasi)
```
users            kelas  <--  siswa  <--  absensi
(id, nama,       (id,        (id, nis,    (id, siswa_id, tanggal,
 email,           nama_      nama, jk,     status H/I/S/A,
 password)        kelas)     kelas_id)     keterangan)
```
- `siswa.kelas_id` mengacu ke `kelas.id`
- `absensi.siswa_id` mengacu ke `siswa.id` (unik per siswa per tanggal)
- Menghapus siswa ikut menghapus riwayat absensinya; kelas yang masih punya siswa tidak bisa dihapus.

## Struktur file
```
siabsen/
├── config.php        koneksi database + fungsi bantu
├── database.sql
├── index.php  login.php  register.php  logout.php
├── dashboard.php  absensi.php  rekap.php  siswa.php  kelas.php
├── includes/         header, footer, kerangka login
└── assets/           css, js, img/logo.png
```
