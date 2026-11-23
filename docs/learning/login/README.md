# Referensi belajar: melengkapi login JWT

File-file ini adalah **bahan salin dan pelajari**, bukan hasil edit langsung pada `D:\project\PHP\pos_apotek`. Source project tetap Anda yang ubah.

## Temuan audit yang menjadi dasar

- `config/auth.php` sudah menetapkan RS256, jalur kunci, issuer, audience, dan batas access-token maksimal 15 menit.
- Pasangan kunci RSA 3072 bit sudah dibuat di luar folder source. Saya hanya memeriksa nama file, tidak membaca isi private key.
- `auth/login-process.php` masih contoh: belum memeriksa username/sandi, `JWT::encode` masih dikomentari, refresh token belum ditulis ke database, dan redirect mengarah ke `dashboard.php` yang tidak ada di struktur proyek.
- Tabel target yang cocok adalah `employees`, `auth_sessions`, dan `auth_refresh_tokens`. Kolom database menyimpan `token_hash`; contoh lama menyebut tabel `user_refresh_tokens` dan menyimpan token mentah.
- `api/selling.php` saat ini belum autentikasi dan menerima `employee_id` dari body. Tahap sales nanti harus mengambil pegawai dari JWT yang sudah diverifikasi.
- Endpoint sales yang ada tampak memakai skema versi sebelumnya (selling, selling_details, product_unit_conversions, expired_date), sedangkan SQL target mendefinisikan sales, sale_items, product_units.conversion_factor, dan expiry_date. Sales berikutnya harus mengikuti SQL target, bukan menyalin query lama.
- Saya melihat file autentikasi/Composer masih berstatus untracked di Git. Hindari perintah reset/clean; panduan ini tidak mengubahnya.

## Keputusan implementasi

- Gunakan library `firebase/php-jwt` yang sudah ada, kunci RS256 yang sudah dibuat, dan skema target SQL.
- Access JWT berlaku 5 menit; refresh token acak 256-bit dirotasi setiap refresh, disimpan hanya sebagai SHA-256 hash, berlaku maksimal 7 hari per token.
- Sesi memiliki batas maksimum 30 hari dan kedaluwarsa setelah 12 jam tidak aktif.
- Access dan refresh dikirim sebagai cookie `HttpOnly; Secure; SameSite=Strict`. Semua POST memerlukan token CSRF dan origin aplikasi yang tepat. Jalankan situs Herd lewat HTTPS.
- Batas login: 5 kegagalan per kombinasi username/IP atau 30 per IP dalam 15 menit. Tabel baru menyimpan HMAC username/IP, bukan nilainya.
- Sandi menggunakan `password_hash()`/`password_verify()` dengan `PASSWORD_DEFAULT`. Hash dummy menyeimbangkan waktu respons ketika username tidak ditemukan.

## Urutan penerapan oleh Anda

1. Pilih PHP **8.5.12 atau patch keamanan yang lebih baru** untuk situs ini di Herd. Di folder proyek, jalankan `herd php -v`. Kode referensi tetap kompatibel dengan PHP 8.4.
2. Pastikan situs Herd menggunakan HTTPS. Cookie Secure tidak bekerja pada HTTP.
3. Buat `AUTH_RATE_LIMIT_KEY` dan `AUTH_DUMMY_PASSWORD_HASH` memakai perintah di bawah, lalu tambahkan nilainya ke `.env` lokal. Jangan menaruh nilai sungguhan di Git atau membagikannya.
4. Tinjau lalu jalankan SQL `auth_login_attempts.sql` pada database target lokal. Ini tabel tambahan yang Anda setujui. Hapus catatan lama berkala dengan `DELETE FROM auth_login_attempts WHERE attempted_at < UTC_TIMESTAMP(6) - INTERVAL 30 DAY;`.
5. Salin/periksa `config/auth.php`, lalu letakkan helper dan endpoint ke folder `auth/` sesuai peta file.
6. Tambahkan variabel konfigurasi yang ditunjukkan. Jangan menyalin kredensial dari file `.env` ke dokumen ini.
7. Jika belum ada akun admin dengan hash valid, salin `create-first-admin.php` ke `scripts/` dan jalankan wrapper PowerShell di bawah. Script hanya mengisi admin placeholder `EMP-00001` atau membuatnya saat tabel pegawai kosong; ia berhenti bila ada data lain yang tak cocok.
8. Buat halaman login yang meminta token dari `auth/csrf-token.php`, mengirim JSON ke `auth/login-process.php`, dan menyimpan CSRF token di memori JavaScript saja.
9. Lindungi halaman dashboard serta setiap API dengan `authRequireEmployee()`. Jangan percaya `employee_id` dari body.

Pembuatan nilai lokal (jalankan dari terminal folder proyek):

```powershell
herd php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
herd php -r "echo password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), PHP_EOL;"
```

Perintah pertama mengeluarkan secret HMAC 64 karakter; simpan sebagai `AUTH_RATE_LIMIT_KEY`. Perintah kedua mengeluarkan hash acak; simpan sebagai `AUTH_DUMMY_PASSWORD_HASH`. Sandi acak yang di-hash tidak ditampilkan.

Contoh nama variabel `.env` saja (isi rahasia tetap lokal):

```dotenv
APP_ORIGIN=https://<domain-herd-anda>
JWT_PRIVATE_KEY_PATH=D:/Project/PHP/secrets/pos_apotek/private_key.pem
JWT_PUBLIC_KEY_PATH=D:/Project/PHP/secrets/pos_apotek/private_public.pem
JWT_ISSUER=https://<domain-herd-anda>
JWT_AUDIENCE=https://<domain-herd-anda>
JWT_ACCESS_TTL=300
JWT_REFRESH_TTL=604800
AUTH_SESSION_TTL=2592000
AUTH_IDLE_TTL=43200
AUTH_RATE_LIMIT_KEY=<hasil-perintah-pertama>
AUTH_DUMMY_PASSWORD_HASH=<hasil-perintah-kedua>
```

`APP_ORIGIN`, issuer, dan audience harus persis menggunakan origin HTTPS situs Herd Anda (tanpa slash path). Jalur kunci di atas mengikuti skrip generator yang sudah ada; sesuaikan jika lokasi aktual Anda berbeda.

## Peta file referensi → lokasi di proyek

| File referensi            | Salin/terapkan pada proyek                                                    |
| ------------------------- | ----------------------------------------------------------------------------- |
| `auth_login_attempts.sql` | Jalankan manual pada database target; jangan otomatis dari PHP                |
| `config-auth.php`         | Ganti/merge ke `config/auth.php`                                              |
| `auth-bootstrap.php`      | `auth/bootstrap.php`                                                          |
| `auth-csrf-token.php`     | `auth/csrf-token.php`                                                         |
| `auth-login-process.php`  | Ganti isi `auth/login-process.php`                                            |
| `auth-refresh.php`        | `auth/refresh.php`                                                            |
| `auth-logout.php`         | `auth/logout.php`                                                             |
| `auth-me.php`             | `auth/me.php`                                                                 |
| `create-first-admin.php`  | `scripts/create-first-admin.php` (jalankan manual sekali; bukan endpoint web) |

Jalankan admin bootstrap dari PowerShell. Password dibaca sebagai secure string, diteruskan sementara melalui environment proses, lalu dihapus; jangan ubahnya menjadi argumen command line.

```powershell
$secure = Read-Host 'Sandi admin awal' -AsSecureString
$ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
try {
  $env:BOOTSTRAP_ADMIN_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)
  $env:BOOTSTRAP_ADMIN_USERNAME = Read-Host 'Username admin'
  $env:BOOTSTRAP_EMPLOYEE_NAME = Read-Host 'Nama admin'
  herd php scripts/create-first-admin.php
} finally {
  Remove-Item Env:BOOTSTRAP_ADMIN_PASSWORD -ErrorAction SilentlyContinue
  Remove-Item Env:BOOTSTRAP_ADMIN_USERNAME -ErrorAction SilentlyContinue
  Remove-Item Env:BOOTSTRAP_EMPLOYEE_NAME -ErrorAction SilentlyContinue
  [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr)
  $secure.Dispose()
}
```

## Cara halaman vanilla JS memanggil login

```js
const csrfResponse = await fetch("/auth/csrf-token.php", {
  credentials: "same-origin",
});
const { csrf_token: csrfToken } = await csrfResponse.json();

const response = await fetch("/auth/login-process.php", {
  method: "POST",
  credentials: "same-origin",
  headers: {
    "Content-Type": "application/json",
    "X-CSRF-Token": csrfToken,
  },
  body: JSON.stringify({ username, password }),
});

const result = await response.json();
if (response.ok) {
  // Cookie token dipasang browser; simpan hanya CSRF token di memori.
  window.location.assign("/index.php");
} else {
  // Tampilkan result.message tanpa membedakan username tak ada dan sandi salah.
}
```

Setelah login, halaman memanggil `GET /auth/me.php` untuk memeriksa sesi. Jika access token kedaluwarsa, panggil `POST /auth/refresh.php` dengan cookie otomatis dan header `X-CSRF-Token` terbaru. Jangan simpan access/refresh token di `localStorage`.

## Hal yang belum termasuk tahap ini

- Tabel dan endpoint master employee/user (berikutnya). Akun demo di SQL memakai hash placeholder, jadi tidak dapat dipakai login; login memerlukan minimal satu pegawai aktif dengan hash `password_hash()` yang valid.
- API produk lengkap dan sales terautentikasi.
- MFA, pemulihan akun, dan integrasi klien non-browser.
- Saya tidak menjalankan kode atau SQL di mesin Anda dan tidak mengubah proyek D:.

File berikut adalah kode referensi yang bisa Anda terapkan bertahap. Periksa setiap langkah dan pastikan tidak ada output PHP sebelum header/cookie dikirim.
