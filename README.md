# SiAdu

## Instalasi lokal

1. Pastikan PHP memiliki ekstensi `mysqli` dan `fileinfo`, lalu jalankan MySQL/MariaDB.
2. Impor `database.sql` melalui phpMyAdmin atau klien MySQL. Skrip membuat tabel akun, OPD, kategori, pengaduan, lampiran, dan riwayat status.
3. Jika database masih memakai skema SiAdu lama, buat cadangan terlebih dahulu. Skrip akan mengganti nama tabel lama menjadi `pengaduan_lama`, membuat skema baru, dan memindahkan tiket, status, serta nama berkas yang sudah ada. Akun untuk tiket lama tidak dapat dipulihkan, sehingga tiket lama akan ditandai sebagai data tanpa pemilik.
4. Periksa nama database dan kredensial pada `config.php`, lalu buka proyek melalui server web lokal.
5. Buat akun petugas/admin dari terminal pada direktori proyek dengan `C:\xampp\php\php.exe buat_petugas.php`. Skrip hanya dapat dijalankan melalui CLI; pendaftaran web biasa hanya membuat akun warga.

Pengguna mendaftar melalui halaman **Daftar**, masuk, lalu mengirim aduan. Petugas hanya dapat mengelola aduan pada OPD yang ditetapkan untuk akunnya; admin dapat melihat semua OPD. Perubahan status disimpan sebagai riwayat.
