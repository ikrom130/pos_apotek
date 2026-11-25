# Login dan pengujian auth dengan Bruno

Dokumen ini menjelaskan endpoint auth yang ada sekarang, cakupan PHPUnit, dan langkah menguji request HTTP dengan Bruno. Source PHP tetap Anda yang pelajari dan ubah.

## Endpoint auth

| Method | URL                       | Fungsi                                              | Pemeriksaan utama                                                     |
| ------ | ------------------------- | --------------------------------------------------- | --------------------------------------------------------------------- |
| GET    | `/auth/csrf-token.php`    | Membuat token CSRF dan memasang cookie `csrf_token` | Method GET; cookie Secure dan SameSite Strict                         |
| POST   | `/auth/login-process.php` | Memvalidasi kredensial dan membuat sesi             | CSRF, Origin, JSON, username/sandi, status aktif, rate limit          |
| GET    | `/auth/me.php`            | Membaca pegawai dari sesi aktif                     | JWT access cookie, sesi aktif, batas idle dan absolut, status pegawai |
| POST   | `/auth/refresh.php`       | Memutar refresh token dan menerbitkan access baru   | CSRF, refresh cookie, sesi, expiry, reuse detection                   |
| POST   | `/auth/logout.php`        | Mencabut sesi dan membersihkan cookie               | CSRF; idempotent jika cookie sesi tidak ada                           |

Semua token auth berada di cookie `HttpOnly` (CSRF cookie tidak `HttpOnly`). Jangan menyimpan access/refresh token di `localStorage` atau menyalinnya ke environment Bruno. Login dan refresh mengeluarkan token CSRF baru; perbarui nilai `csrfToken` dari JSON response sebelum request POST berikutnya.

## PHPUnit yang tersedia

- `tests/auth/AuthLoginTest.php`
- `tests/auth/AuthLogoutTest.php`
- `tests/auth/AuthMeTest.php`
- `tests/auth/AuthRefreshTest.php`
- `tests/auth/AuthRequireEmployeeTest.php` menguji helper middleware, bukan endpoint HTTP.
- `tests/IdSequenceTest.php`
- `tests/employees/EmployeeCreateTest.php`

Belum ada PHPUnit khusus untuk `csrf-token.php`. PHPUnit memanggil fungsi handler PHP secara langsung, jadi Bruno melengkapi pengujian dengan HTTP request ke situs Herd.

Jalankan dari PowerShell di folder project:

```powershell
vendor\bin\phpunit.bat
```

**Peringatan database test:** bootstrap test mengosongkan beberapa tabel (`employees`, `auth_sessions`, `auth_refresh_tokens`, `auth_login_attempts`, dan sequence pada test allocator/create). Pastikan konfigurasi mengarah ke database khusus test. `tests/bootstrap.php` menetapkan koneksi sendiri; periksa isinya sebelum menjalankan dan jangan gunakan database development utama/produksi.

## Instal atau buka Bruno di Windows

1. Cek Start Menu Windows dengan mengetik `Bruno`. Jika aplikasi sudah ada, buka aplikasi tersebut.
2. Jika belum terpasang, unduh installer Windows x64 dari [halaman download resmi Bruno](https://www.usebruno.com/downloads). Pilih Windows x64; gunakan Arm64 hanya jika perangkat Windows Anda berbasis ARM.
3. Alternatif lewat Windows Package Manager dari PowerShell:

   ```powershell
   winget install bruno.bruno
   ```

4. Setelah instalasi selesai, buka Bruno dari Start Menu.
5. Pilih untuk membuat collection baru. Beri nama `POS Apotek Local`. Simpan collection di luar repository bila belum ingin menambahkan file Bruno ke Git.
6. Buat environment `Local` dengan variabel:

   | Nama            | Nilai                                                                  |
   | --------------- | ---------------------------------------------------------------------- |
   | `baseUrl`       | `https://<domain-Herd-lokal>` tanpa slash akhir                        |
   | `appOrigin`     | origin persis dari `APP_ORIGIN`, tanpa slash akhir                     |
   | `adminUsername` | username admin development                                             |
   | `adminPassword` | sandi admin development, tandai secret/private bila fitur itu tersedia |
   | `csrfToken`     | kosong pada awalnya; isi manual dari response CSRF terbaru             |

   Jangan gunakan `http://` karena cookie auth disetel `Secure`. Pastikan sertifikat HTTPS Herd dipercaya Windows dan `baseUrl` membuka aplikasi di browser.

7. Pastikan Cookie Jar Bruno aktif. Request berikutnya ke host yang sama harus menyimpan cookie `csrf_token`, `access_token`, dan `refresh_token` dari response. Token access/refresh bersifat HttpOnly di server; gunakan cookie jar, bukan header Authorization manual.

## Membuat dan mengirim request

Untuk setiap request, pilih method dan URL berikut. Pada tab Headers/Body Bruno, masukkan nilai yang ditampilkan.

### 1. Ambil CSRF awal

- Method: `GET`
- URL: `{{baseUrl}}/auth/csrf-token.php`
- Tidak memerlukan body atau header CSRF.
- Kirim request; harapkan HTTP `200` dan JSON berisi `csrf_token`.
- Pastikan Cookie Jar menyimpan cookie `csrf_token` untuk host lokal.
- Salin nilai JSON `csrf_token` ke environment `csrfToken`.

### 2. Login sebagai admin

- Method: `POST`
- URL: `{{baseUrl}}/auth/login-process.php`
- Headers:
  - `Content-Type: application/json`
  - `Origin: {{appOrigin}}`
  - `X-CSRF-Token: {{csrfToken}}`
- Body → JSON:

  ```json
  {
    "username": "{{adminUsername}}",
    "password": "{{adminPassword}}"
  }
  ```

- Bruno mengirim cookie CSRF dari Cookie Jar. Harapkan HTTP `200`, `status: success`, objek `employee`, dan cookie auth baru.
- Ganti environment `csrfToken` dengan `csrf_token` dari response login. Login merotasi token CSRF.

### 3. Periksa sesi

- Method: `GET`
- URL: `{{baseUrl}}/auth/me.php`
- Body/header tambahan tidak diperlukan.
- Cookie Jar harus mengirim `access_token` otomatis.
- Harapkan HTTP `200` dan profil admin.

### 4. Refresh sesi

- Method: `POST`
- URL: `{{baseUrl}}/auth/refresh.php`
- Headers: `Origin: {{appOrigin}}` dan `X-CSRF-Token: {{csrfToken}}`.
- Body kosong.
- Cookie Jar mengirim `refresh_token` dan `csrf_token`.
- Harapkan HTTP `200`; refresh cookie dirotasi. Perbarui environment `csrfToken` dari JSON response.

### 5. Periksa access token setelah refresh

Ulangi request `GET {{baseUrl}}/auth/me.php`. Harapkan `200`. Ini memastikan cookie access yang baru diterbitkan digunakan.

### 6. Logout

- Method: `POST`
- URL: `{{baseUrl}}/auth/logout.php`
- Headers: `Origin: {{appOrigin}}` dan `X-CSRF-Token: {{csrfToken}}`.
- Body kosong.
- Harapkan HTTP `200` dan response sukses. Cookie sesi dibersihkan.

### 7. Pastikan sesi sudah ditolak

Ulangi `GET {{baseUrl}}/auth/me.php`. Harapkan HTTP `401`. Bila Bruno masih mengirim cookie lama, periksa Cookie Jar dan hapus cookie host tersebut, lalu pastikan request logout memang menerima Origin/CSRF yang benar.

## Kasus validasi tambahan

Jalankan pada database development terpisah:

- Login dengan sandi salah: harapkan `401` dengan pesan generik.
- Login body tidak valid (contoh username di bawah 3 karakter): harapkan `422`.
- POST tanpa `Origin` tepat atau token CSRF cocok: harapkan `403`.
- GET ke endpoint POST: harapkan `405`.
- Enam login gagal dengan username uji yang sama dalam 15 menit: percobaan berikutnya dapat menerima `429`. Hindari ini pada akun admin penting karena rate limit juga dapat memblokir login benar untuk kombinasi tersebut.

Jika muncul `403`, periksa ketiga hal ini sekaligus: origin persis sama dengan `APP_ORIGIN`, cookie CSRF tersimpan, dan nilai header cocok dengan cookie terbaru. Jika muncul `401`, cek akun aktif dan cookie access/refresh.

## Referensi Bruno

- [Download resmi Bruno](https://www.usebruno.com/downloads)
- [Dokumentasi Bruno](https://docs.usebruno.com/)
