# API Employees dan pengujian Bruno

README ini menjelaskan implementasi API pegawai yang tersedia saat ini. PHP project tetap Anda yang tulis.

## Endpoint saat ini

| Method | URL                                        | Hak akses | Tujuan                                                          |
| ------ | ------------------------------------------ | --------- | --------------------------------------------------------------- |
| GET    | `/api/employees/list.php?page=1&limit=20`  | Admin     | Daftar, pencarian, filter, pagination                           |
| GET    | `/api/employees/detail.php?id=EMP-00001`   | Admin     | Detail satu pegawai                                             |
| POST   | `/api/employees/create.php`                | Admin     | Membuat akun dan ID server-side                                 |
| PUT    | `/api/employees/update.php?id=EMP-00001`   | Admin     | Mengubah profil pegawai                                         |
| PATCH  | `/api/employees/status.php?id=EMP-00001`   | Admin     | Mengaktifkan/menonaktifkan dan mencabut sesi bila dinonaktifkan |
| POST   | `/api/employees/password.php?id=EMP-00002` | Admin     | Mengganti sandi pegawai lain dan mencabut sesi target           |

Endpoint password saat ini adalah tindakan admin untuk target pegawai lain. Endpoint menolak admin mengganti sandi akunnya sendiri. Ia meminta `new_password`, bukan konfirmasi sandi pemilik akun. Tidak ada `reset_password.php` pada source saat ini. Jangan menyamakan endpoint ini dengan fitur self-service ganti sandi.

Semua perubahan memerlukan cookie sesi admin, CSRF cookie/header yang cocok, dan `Origin` persis `APP_ORIGIN`. Role yang diterima pada profil adalah `admin`, `apoteker`, atau `kasir`. Sandi create dan password minimal 12 dan maksimal 72 byte; akun dibuat aktif dan sandi disimpan sebagai hash. ID employee dialokasikan melalui `id_sequences` dalam transaksi.

## Test PHPUnit endpoint create

`tests/employees/EmployeeCreateTest.php` mencakup CSRF tidak valid, method tidak sesuai, role bukan admin, validasi nama/role/telepon/username, panjang sandi, sukses menghasilkan ID berurutan, hash sandi tersimpan, username duplikat (`409`), dan rollback sequence.

Jalankan:

```powershell
vendor\bin\phpunit.bat tests\employees\EmployeeCreateTest.php
```

Test mengosongkan tabel test. Pastikan `tests/bootstrap.php` menunjuk ke database test khusus, bukan database aplikasi.

## Membuat request create di Bruno

Ikuti instalasi, pembuatan collection/environment, dan login admin pada [README login](../login/README.md). Setelah login, simpan CSRF terbaru dari response login ke `csrfToken`.

Buat request:

- Method: `POST`
- URL: `{{baseUrl}}/api/employees/create.php`
- Headers:
  - `Content-Type: application/json`
  - `Origin: {{appOrigin}}`
  - `X-CSRF-Token: {{csrfToken}}`
- Body → JSON:

  ```json
  {
    "employee_name": "Pegawai Uji Bruno",
    "employee_role": "apoteker",
    "employee_phone": "081234567890",
    "employee_username": "pegawai.bruno.uji",
    "employee_password": "LatihanBrunoAman123!"
  }
  ```

Cookie access admin dan CSRF harus terkirim otomatis dari Cookie Jar. Untuk hasil normal harapkan HTTP `201`, `status: success`, dan `employee_id` berformat `EMP-00001` (angka aktual mengikuti isi database).

Uji validasi tambahan pada database lokal khusus:

1. Hilangkan `Origin` atau kirim CSRF yang salah: harapkan `403`.
2. Ubah method menjadi GET: harapkan `405`.
3. Login sebagai akun non-admin lalu kirim POST create: harapkan `403`.
4. Kirim nama terlalu pendek, role yang tidak dikenal, username invalid, atau sandi di bawah 12 byte: harapkan `422`.
5. Buat username yang sama dua kali: request kedua harapkan `409`.

**Penting:** create menyimpan pegawai secara permanen dan endpoint delete belum ada. Gunakan database disposable/backup development dan username uji yang unik. Jangan jalankan pada data staging atau produksi. Bersihkan akun uji lewat DB hanya jika Anda sudah memastikan koneksi menunjuk database latihan yang benar.
