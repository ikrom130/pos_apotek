# Login dan pengujian auth dengan Postman

Dokumen ini mengikuti endpoint auth yang ada di project. Pengujian HTTP menggunakan collection Postman bersama employees dan suppliers, bukan Bruno.

## Endpoint auth

| Method | URL | Fungsi | Pemeriksaan |
|---|---|---|---|
| GET | /auth/csrf-token.php | Membuat token CSRF dan cookie csrf_token | GET, cookie Secure/SameSite |
| POST | /auth/login-process.php | Memeriksa kredensial dan membuat sesi | CSRF, Origin, JSON, akun aktif, rate limit |
| GET | /auth/me.php | Mengembalikan profil dari sesi aktif | Access cookie, JWT, sesi, masa berlaku, status pegawai |
| POST | /auth/refresh.php | Merotasi refresh token dan menerbitkan access baru | CSRF, refresh cookie, sesi, masa berlaku, reuse |
| POST | /auth/logout.php | Mencabut sesi dan membersihkan cookie | CSRF; logout idempotent |

Access dan refresh token dikirim melalui cookie HttpOnly, Secure, SameSite=Strict. Cookie CSRF dapat dibaca klien untuk dikirim kembali di header X-CSRF-Token. Semua POST memerlukan Origin yang sama persis dengan APP_ORIGIN dan token CSRF header-cookie yang cocok.

## Alur autentikasi

1. GET csrf-token.php memperoleh token awal dan memasang cookie CSRF.
2. POST login-process.php mengirim username/password, Origin, X-CSRF-Token, dan cookie CSRF.
3. Jika login valid, server membuat sesi dan cookie access/refresh. Token CSRF ikut diperbarui di response JSON.
4. GET me.php memeriksa sesi aktif.
5. POST refresh.php menggunakan refresh cookie dan CSRF; token refresh serta CSRF diputar.
6. POST logout.php mencabut sesi. Request me.php berikutnya harus mendapat 401.

Jangan simpan access/refresh token di environment, localStorage, atau log. Postman Cookie Jar harus menyimpan cookie otomatis.

## PHPUnit

Test auth yang tersedia:

- tests/auth/AuthLoginTest.php
- tests/auth/AuthLogoutTest.php
- tests/auth/AuthMeTest.php
- tests/auth/AuthRefreshTest.php
- tests/auth/AuthRequireEmployeeTest.php — menguji helper middleware, bukan request HTTP.
- tests/IdSequenceTest.php

Belum ada test PHPUnit khusus csrf-token.php. PHPUnit memanggil handler secara langsung; Postman melengkapi uji HTTP untuk cookie, Origin, routing, dan HTTPS.

Jalankan seluruh test dari PowerShell project:

    vendor\bin\phpunit.bat

Sebelum menjalankan, periksa tests/bootstrap.php dan phpunit.xml. Bootstrap memakai database test yang ditetapkan langsung dan test dapat mengosongkan tabel pegawai, sesi, refresh token, login attempts, serta sequence. Jangan arahkan ke database aplikasi/Postman atau database produksi.

## Pengujian dengan Postman

Import file project berikut:

- postman/pos-apotek-auth-supplier.postman_collection.json
- postman/pos-apotek-local.postman_environment.json

Pilih environment POS Apotek Local. Nilai baseUrl dan appOrigin default ke https://pos_apotek.test; sesuaikan keduanya jika domain Herd Anda berbeda. Isi adminUsername dan adminPassword dengan akun development. Credential tidak dicantumkan dalam file template.

Jalankan collection secara berurutan agar CSRF, login, refresh, cookie, dan logout bekerja sesuai alur. Collection mencakup auth, employees, dan suppliers; pengujian auth tidak dipisah menjadi collection kedua. Jangan menjalankan collection ke database yang datanya harus dipertahankan.

Pengguna melaporkan hasil run lokal terakhir: 50 pass, 0 failed, 0 error. Itu hasil Postman, bukan hasil PHPUnit.

## Jika request gagal

- 403: periksa Origin, cookie csrf_token, dan X-CSRF-Token; ketiganya harus sesuai konfigurasi/origin dan token terbaru.
- 401: periksa akun aktif dan cookie sesi. Setelah logout, me.php seharusnya 401.
- 429: rate limit login tercapai. Hindari mencoba berulang kali pada akun penting.
- Jangan ekspor environment setelah run tanpa meninjau nilainya. Script collection mengisi variable akun uji saat berjalan.
