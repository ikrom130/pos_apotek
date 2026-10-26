# Database Connection

## Tujuan

Mengelola koneksi PHP ke MySQL menggunakan PDO melalui `config/database.php`.

## Konfigurasi

Variabel konfigurasi dibaca dari environment atau file `.env` pada root project.

| Variabel  | Fungsi         |
| --------- | -------------- |
| `DB_HOST` | Host MySQL     |
| `DB_PORT` | Port MySQL     |
| `DB_NAME` | Nama database  |
| `DB_USER` | Username MySQL |
| `DB_PASS` | Password MySQL |

## Cara Penggunaan

```php
// Sesuaikan jumlah tingkat folder mundur (../) dengan lokasi file pemanggil
require_once __DIR__ . '/config/database.php';

$database = new Database();
$pdo = $database->getConnection();
```

## Pengujian

1. Jalankan perintah `php -l config/database.php` untuk mengecek sintaks.
2. Pastikan ekstensi `PDO` dan `pdo_mysql` sudah aktif di PHP Anda.
3. Jalankan file pengujian koneksi: `php test_connection.php`.
4. Pastikan database yang dilaporkan terhubung adalah `pos_staging`.

## Status

- Implementasi: Selesai dan sukses bermigrasi ke PDO.
- Sintaks: Valid (Terverifikasi).
- Koneksi MySQL: Berhasil diverifikasi pada lingkungan lokal.
