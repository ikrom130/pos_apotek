# POS Apotek — Arsitektur dan Rencana Belajar

**Status:** Dokumen diperbarui berdasarkan proyek yang sudah ada. Hanya dokumen ini yang diperbarui; source code tetap milik Anda untuk dikembangkan.

## Proyek saat ini

Source project berada di `D:\project\PHP\pos_apotek`. Folder tersebut sudah berisi aplikasi PHP dan dokumentasi, jadi rencana ini berlaku untuk mengembangkan proyek yang ada, bukan memulai proyek kosong.

Struktur yang terlihat:

```text
pos_apotek/
├── index.php                 # entry point aplikasi
├── api/                      # endpoint produk, pemasok, penjualan
├── auth/                     # alur login
├── config/                   # konfigurasi database dan autentikasi
├── views/                    # bagian tampilan bersama
├── assets/
│   ├── js/                   # JavaScript browser
│   └── css/                  # sumber dan hasil build Tailwind
├── scripts/                  # utilitas pemeriksaan dan kunci JWT
├── docs/                     # dokumentasi skema dan koneksi database
├── composer.json             # dependency PHP
└── package.json              # Tailwind CLI
```

Nama file menunjukkan sudah ada pondasi login, API produk/pemasok/penjualan, dan tampilan dashboard. Dokumen ini tidak menganggap setiap fitur sudah lengkap atau aman sebelum ditinjau. File `.env` dan folder `secrets` tidak diperiksa.

## Teknologi

| Bagian         | Pilihan                                           | Catatan                                                                                                                                                                                                                                                                                                                                                  |
| -------------- | ------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Backend        | PHP murni tanpa framework PHP                     | Tetap gunakan pemisahan file dan fungsi yang mudah diikuti; validasi, otorisasi, dan keamanan ditangani eksplisit.                                                                                                                                                                                                                                       |
| Runtime lokal  | Laravel Herd untuk Windows                        | Tidak perlu instal PHP terpisah jika Herd menyediakan versi yang dibutuhkan. Atur versi per aplikasi/proyek di Herd.                                                                                                                                                                                                                                     |
| Versi PHP      | PHP 8.5.12 atau patch keamanan yang lebih baru    | Per 28 Oktober 2026, 8.5.12 adalah rilis keamanan terbaru yang ditemukan. Versi 8.5.12 memperbaiki dua kerentanan kritis. Setelah memilih versi proyek di Herd, verifikasi dari folder proyek dengan `herd php -v`. Jika hasilnya 8.4, ubah versi yang dipin untuk aplikasi di Herd; instalasi PHP manual di PATH tidak otomatis mengganti pilihan Herd. |
| API            | REST dengan JSON                                  | Endpoint dikelompokkan menurut fitur di `api/`. Target berikutnya dapat memakai prefix `/api/v1/` secara konsisten.                                                                                                                                                                                                                                      |
| Browser        | HTML dan vanilla JavaScript                       | `assets/js/dashboard.js` adalah JavaScript aplikasi; tidak memakai framework JavaScript.                                                                                                                                                                                                                                                                 |
| CSS            | Tailwind CSS melalui CLI                          | `package.json` saat ini mendeklarasikan Tailwind CLI dan Tailwind `^4.3.3`; hasil build berada di `assets/css/output.css`.                                                                                                                                                                                                                               |
| Database       | MySQL 8 melalui PDO                               | Ada `config/database.php` dan dokumentasi koneksi. Gunakan parameter query PDO, kredensial dari environment, dan transaksi untuk perubahan stok.                                                                                                                                                                                                         |
| Dependency PHP | Composer, saat ini memuat `firebase/php-jwt ^7.2` | Ini library JWT, bukan framework. Pertahankan hanya sesuai kebutuhan autentikasi API; untuk POS browser satu origin, sesi server-side biasanya lebih sederhana.                                                                                                                                                                                          |

Sumber versi: [PHP 8.5.12](https://www.php.net/releases/8_5_12.php), [masa dukungan PHP](https://www.php.net/supported-versions.php), dan [Herd untuk Windows](https://herd.laravel.com/windows). Herd dapat mengelola dan memilih beberapa versi PHP; versi yang terpasang di Windows dan versi yang dipakai sebuah proyek Herd adalah dua hal berbeda.

## Batas modul

```text
Aplikasi PHP
├── Auth & Employees
├── Product Catalog
│   ├── Categories
│   ├── Dosage Forms
│   ├── Units
│   ├── Products
│   ├── Product Units
│   └── Pricing
├── Sales
├── Inventory (batch, expiry, movements)
└── Purchases (bahan belajar lanjutan)
```

Modul pembelian, penerimaan barang, pemasok dan master lanjutan tetap dapat menjadi latihan Anda sesudah alur inti stabil. Jangan menghapus atau mengimpor data SQL sebelum hubungan skemanya dipahami.

## Keputusan keamanan yang perlu dipertahankan

Login POS web satu origin disarankan memakai sesi browser server-side dan cookie, bukan menyimpan token di `localStorage`:

- Cookie `HttpOnly`, `Secure` ketika HTTPS, dan `SameSite=Lax` atau lebih ketat bila alur aplikasi mendukungnya.
- `session.use_strict_mode` dan `session.use_only_cookies` aktif; regenerasi ID sesi saat login dan invalidasi sesi saat logout.
- Sandi disimpan dengan `password_hash()` dan diperiksa dengan `password_verify()`; hash placeholder dari SQL tidak boleh dipakai untuk akun aktif.
- Permintaan perubahan data perlu token CSRF; endpoint memeriksa autentikasi dan hak akses di server.
- Batasi percobaan login, gunakan pesan gagal generik, validasi semua masukan, dan gunakan prepared statement PDO.
- Mutasi penjualan dan stok dilakukan atomik di transaksi database.
- Library JWT dan skrip terkait sudah ada di proyek. Sebelum memperluasnya, putuskan apakah API akan dipakai klien non-browser. Jangan mencampur sesi cookie dan JWT tanpa kebutuhan dan aturan masa berlaku/revokasi yang jelas.

Keputusan ini adalah panduan untuk menilai dan mengembangkan implementasi yang ada, bukan klaim bahwa pemeriksaan keamanan tersebut sudah diterapkan.

## Peta belajar bertahap

| Tahap                  | Fokus                                                                                                                      | Hasil belajar dan batas                                                                                                          |
| ---------------------- | -------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| 0. Orientasi (selesai) | Kenali struktur proyek, file konfigurasi, endpoint yang ada, serta skema target                                            | Catat tujuan tiap file sebelum mengubahnya. Jangan membaca atau menyalin rahasia ke dokumentasi.                                 |
| 1. Runtime dan fondasi | Pilih PHP 8.5.12+ untuk aplikasi di Herd; pahami `index.php`, konfigurasi PDO, environment, dan bentuk respons API         | Pastikan versi CLI dan versi PHP situs Herd sama. Pelajari alur request → validasi → database → response.                        |
| 2. Login dan otorisasi | Tinjau `auth/login-process.php`, `config/auth.php`, cara sesi/JWT saat ini, dan alur logout                                | Tentukan satu pola untuk web POS; pelajari hash sandi, rotasi sesi, CSRF, rate limit, role checks, serta pengelolaan kunci.      |
| 3. API master produk   | Rapikan dan lengkapi API kategori, bentuk sediaan, satuan, produk, unit produk, dan harga                                  | Gunakan pola response, validasi, autentikasi dan otorisasi yang sama; pahami konversi unit dan snapshot harga.                   |
| 4. Sales               | Tinjau endpoint `api/selling.php`; bangun alur keranjang, checkout, pembayaran tunai, riwayat, dan pembatalan terotorisasi | Terapkan transaksi atomik, FEFO, batch/kedaluwarsa, stok cukup, dan ledger. Data stok latihan harus terpisah dari data produksi. |
| 5. Latihan mandiri     | Purchase, supplier, penerimaan, biaya tambahan, pembayaran supplier, master lain                                           | Gunakan pola yang dipelajari dari master produk dan sales; tulis alasan keputusan serta latihan lanjutan sendiri.                |

## Cara menelusuri proses belajar

Untuk tiap tahap, catat di `docs/learning/`:

1. Tujuan fitur dan masalah yang dipecahkan.
2. Urutan file yang Anda ubah dan fungsi tiap file.
3. Alur data dari browser sampai database dan kembali sebagai response.
4. Alasan memilih validasi, aturan bisnis, dan kontrol keamanan.
5. Contoh request/response serta cara memeriksa hasil.
6. Keputusan yang ditunda dan latihan yang dapat Anda lanjutkan.

Perubahan kode dikerjakan oleh Anda. Dokumen ini memberi urutan dan bahan belajar; tidak ada perubahan source code proyek yang dibuat sebagai bagian dari revisi ini.

## Catatan skema target

Dokumentasi database proyek menyebut target berisi 22 tabel. SQL `pos_apotek_target.sql` yang Anda lampirkan sebelumnya berisi 24 pernyataan `CREATE TABLE`. Sebelum skema dipakai, cocokkan versi SQL dan dokumentasi agar jumlah tabel serta definisi kolom yang menjadi acuan tidak bertentangan.

Catatan penting dari kedua referensi: ada tabel produk dan unit produk, batch/kedaluwarsa, pergerakan stok, penjualan, pembelian, penerimaan barang, serta usulan harga. Dump juga memuat hash sandi placeholder. Data demo bukan data produksi dan tidak boleh otomatis diimpor.

## Kondisi Herd

Anda menyampaikan `herd php -v` masih menampilkan PHP 8.4 walaupun PHP 8.5 telah dipasang. Itu menunjukkan Herd atau aplikasi proyek masih memilih 8.4; instalasi PHP manual di `C:\` tidak otomatis mengganti versi terpilih Herd. Pilih PHP 8.5 pada pengaturan aplikasi/proyek di Herd, buka terminal baru di folder proyek, lalu periksa kembali dengan `herd php -v`. Untuk target keamanan, pastikan hasilnya 8.5.12 atau patch yang lebih baru.
