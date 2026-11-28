# POS Apotek

POS apotek berbasis PHP native, MySQL, vanilla JavaScript, dan Tailwind CSS.

## Status implementasi dan pengujian

- Login, refresh sesi, logout, dan pemeriksaan profil tersedia pada `auth/`.
- API pembuatan pegawai tersedia di `api/employees/create.php`; hanya admin terautentikasi yang boleh membuat akun.
- API employees menyediakan list, detail, create, update, status, dan reset password pegawai oleh admin.
- API suppliers menyediakan list, detail, create, update, dan status untuk role admin/apoteker.
- PHPUnit mencakup handler auth, employees, supplier, dan allocator ID. Test duplikasi create supplier belum benar-benar membuat duplikat.
- Pengguna melaporkan hasil terakhir collection Postman: 50 pass, 0 failed, 0 error. Ini pengujian HTTP lokal, bukan hasil PHPUnit.
- Endpoint `auth/csrf-token.php` belum memiliki unit test khusus.
- API produk dan penjualan masih dalam pengembangan.

## Menjalankan PHPUnit

Dari PowerShell pada folder project:

```powershell
vendor\bin\phpunit.bat
```

Test menggunakan database `test_pos_apotek_target` sesuai konfigurasi test. Setup mengosongkan tabel seperti pegawai, sesi, refresh token, percobaan login, supplier, dan sequence. Jangan arahkan test ke database development/Postman atau produksi. Periksa `tests/bootstrap.php` dan `phpunit.xml` sebelum menjalankannya; koneksi test ditetapkan di bootstrap.

PHPUnit memanggil handler PHP secara langsung, bukan mengirim request melalui web server. Untuk pengujian HTTP, import collection dan environment Postman di folder `postman/`.

## Pengujian HTTP dengan Postman

Gunakan HTTPS lokal dan database development khusus. Request mutasi harus menyertakan `Origin` yang sama persis dengan `APP_ORIGIN`, cookie `csrf_token`, dan header `X-CSRF-Token` yang cocok dengan cookie. Postman Cookie Jar menyimpan cookie sesi. Jangan menyalin access/refresh token ke variable atau log.

Collection mencakup auth, employees, dan suppliers. Collection membuat pegawai dan supplier uji, lalu mengubah statusnya. Karena endpoint delete tidak tersedia, record tetap ada di database. Gunakan database latihan yang aman diubah. Periksa environment sebelum commit/export ulang; nilainya dapat terisi saat run.

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
- [Login dan sesi](docs/learning/login/README.md)
- [API Employees](docs/learning/employees/README.md)
- [API Suppliers](docs/learning/supplier/README.md)
- [Postman collection](postman/pos-apotek-auth-supplier.postman_collection.json)
- [Postman environment](postman/pos-apotek-local.postman_environment.json)
