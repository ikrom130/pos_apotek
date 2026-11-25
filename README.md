# POS Apotek

POS apotek berbasis PHP native, MySQL, vanilla JavaScript, dan Tailwind CSS.

## Status implementasi yang sudah diuji dengan PHPUnit

- Login, refresh sesi, logout, dan pemeriksaan profil tersedia pada `auth/`.
- API pembuatan pegawai tersedia di `api/employees/create.php`; hanya admin terautentikasi yang boleh membuat akun.
- Unit test mencakup handler login, logout, refresh, profil, middleware autentikasi pegawai, allocator ID, dan endpoint create pegawai.
- Endpoint `auth/csrf-token.php` belum memiliki unit test khusus.
- API produk dan penjualan masih dalam pengembangan.

## Menjalankan PHPUnit

Dari PowerShell pada folder project:

```powershell
vendor\bin\phpunit.bat
```

Test menggunakan database `test_pos_apotek_target` sesuai konfigurasi test. Test mengosongkan tabel pegawai, sesi, refresh token, percobaan login, dan sequence. Jangan arahkan test ke database development utama atau produksi. Periksa `tests/bootstrap.php` dan `phpunit.xml` sebelum menjalankannya; bootstrap saat ini menetapkan koneksi database test secara langsung.

PHPUnit memanggil handler PHP secara langsung, bukan mengirim request melalui web server. Untuk uji HTTP manual dengan Bruno, ikuti [panduan Login dan Bruno](docs/learning/login/README.md) dan [panduan Employees dan Bruno](docs/learning/employees/README.md).

## Pengujian HTTP dengan Bruno

Gunakan HTTPS lokal dan database development khusus. Request POST harus menyertakan `Origin` yang persis sama dengan `APP_ORIGIN`, cookie `csrf_token`, dan header `X-CSRF-Token` yang cocok dengan isi cookie. Bruno harus menyimpan dan mengirim cookie dari respons. Jangan menyalin access token atau refresh token ke variabel, file, atau log.

Request create employee menambah data secara permanen; endpoint delete tidak tersedia. Gunakan akun dan database latihan, bukan data kerja.

## Teknologi

- PHP native, PDO, dan Laravel Herd.
- MySQL.
- Vanilla JavaScript.
- Tailwind CSS.
- `firebase/php-jwt`.
- PHPUnit.

## Dokumentasi

- [Arsitektur project](docs/architecture.md)
- [Panduan belajar](docs/learning/README.md)
- [Login dan Bruno](docs/learning/login/README.md)
- [API Employees dan Bruno](docs/learning/employees/README.md)
