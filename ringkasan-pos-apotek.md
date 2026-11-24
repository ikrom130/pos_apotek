# Ringkasan rujukan proyek POS Apotek

## Tujuan dan cara kerja

- Pengguna membangun POS apotek bertahap sambil belajar dan ingin dapat menelusuri alasan/kode per fitur.
- Siapkan referensi kode dan panduan; pengguna sendiri yang menyalin/mengubah ke `D:\Project\PHP\pos_apotek`.
- Jangan mengedit project D: atau menjalankan SQL/kode terhadap database pengguna tanpa instruksi baru.

## Keputusan utama

- Teknologi: PHP native dengan patch keamanan terbaru yang tersedia melalui Herd, vanilla JavaScript, Tailwind CSS, dan MySQL.
- Auth browser: cookie `HttpOnly; Secure; SameSite=Strict`, sesi server-side, access JWT RS256 pendek, refresh token acak yang dirotasi dan disimpan sebagai hash, plus CSRF.
- PHP env autentikasi seperti `AUTH_RATE_LIMIT_KEY` dan `AUTH_DUMMY_PASSWORD_HASH` dibuat sekali dan disimpan di `.env`; jangan commit/bagikan `.env`, token, atau private key.
- Browser POS lokal yang berhasil dipakai: `https://pos_apotek.test` (underscore). Pastikan origin ini konsisten di konfigurasi auth; Herd sempat menampilkan domain hyphen, yang menimbulkan mismatch sertifikat.
- Login berhasil dicoba melalui form sementara di DevTools. GET `/auth/csrf-token.php` menghasilkan 200. Endpoint `auth/me.php` tetap berguna untuk memuat ulang profil akun aktif; file itu tidak perlu diubah saat ini.
- Pengguna menyetujui tabel `auth_login_attempts` untuk rate limit login persisten.
- Endpoint `api/employees/password.php` pada referensi kini untuk ubah sandi sendiri: session aktif + sandi saat ini + CSRF. Percobaan sandi salah dibatasi lewat tabel terpisah `auth_password_attempts` (5 per akun/IP atau 30 per IP per 15 menit); sukses mencabut sesi dan meminta login ulang. Reset admin untuk pegawai yang lupa sandi belum dibuat; rancang sebagai alur pemulihan terpisah dengan audit/forced change.
- README root project sebaiknya diisi sejak sekarang sebagai pengantar/status; panduan detail berada di README per fitur. Jangan commit rahasia.

## Database dan ID

- SQL target: `C:\Users\Silent Demon\Downloads\pos_apotek_target.sql`.
- Tabel utama auth: `employees`, `auth_sessions`, `auth_refresh_tokens`; password column menyimpan hash PHP. Dump memiliki password placeholder, bukan akun siap login.
- Sumber SQL memakai `sales`, `sale_items`, `product_units.conversion_factor`, `stock_batches.expiry_date` dan bukan nama lama di `api/selling.php`.
- ID prefiks yang ditetapkan berurutan: employees `EMP-`, suppliers `SUP-`, products `PRD-`, prescriptions `RX-`.
- ID transaksi memiliki counter per bulan: purchases `PO-YYYYMM-`, goods receipts `GR-YYYYMM-`, sales `SL-YYYYMM-`.
- Satu tabel `id_sequences` dipakai untuk semua counter. Counter dikunci dengan `SELECT ... FOR UPDATE` dalam transaksi; PHP membentuk prefix/format. Banyak ID numeric lain telah `AUTO_INCREMENT`; session ID adalah UUID.

## Referensi yang sudah disiapkan

- Login: `outputs/referensi-login/` berisi README, SQL percobaan login gagal, auth bootstrap, CSRF, login, refresh, logout, me, serta bootstrap admin. Referensi ini belum diterapkan seluruhnya ke project.
- Master pegawai: `outputs/referensi-master-employee/` berisi README, `id_sequences.sql`, endpoint per file di `api/employees/`, dan helper bersama `api/_shared/id-sequence.php`.
- Master pegawai menyediakan list, detail, create, update profil/role, status nonaktif/aktif, serta ubah sandi sendiri. Data master selain ubah sandi sendiri memerlukan admin; seluruh mutasi memakai CSRF. Pegawai dinonaktifkan, bukan dihapus. Create tidak menerima `employee_id`; server membuatnya.
- `id_sequences.sql` dijalankan sekali setelah schema dan seed target diimpor. Dengan seed employee hingga `EMP-00003`, endpoint create berikutnya menghasilkan `EMP-00004`.
- Bahan ini hanya referensi; belum dijalankan terhadap database. Tidak ada tes yang dijalankan.

## Langkah berikutnya

1. Pengguna meninjau dan menerapkan migrasi `id_sequences.sql`, shared ID helper, serta endpoint master pegawai dari paket referensi.
2. Pengguna mencoba list/detail dan membuat pegawai memakai admin; sampaikan status HTTP dan pesan error tanpa rahasia bila ada masalah.
3. Lanjutkan endpoint penjualan berdasarkan skema target, dengan autentikasi pegawai dari sesi (jangan percaya `employee_id` body), transaksi database, stok/batch, dan nomor sales bulanan.
4. Siapkan referensi master supplier/produk yang dapat memakai helper ID bersama; pengguna ingin belajar menerapkan pola master pegawai.
5. Gunakan pola sales sebagai bahan belajar untuk purchase/goods receipt.

## Batas dan risiko yang perlu diingat

- Beberapa file auth/project masih pernah berstatus untracked; jangan reset/clean Git atau menimpa file pengguna sembarangan.
- Pastikan endpoint mengikuti skema SQL target, bukan skema lama di `api/selling.php`.
- Jangan mengklaim kode/SQL sudah dites pada mesin pengguna. Jangan jalankan perintah destructive.
- Jika merujuk README utama project, jelaskan status fitur dengan jujur (mis. referensi tersedia vs sudah diterapkan).
