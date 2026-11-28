# Ringkasan rujukan proyek POS Apotek

Diperbarui: 28 November 2026

## Tujuan dan cara kerja

- Pengguna membangun POS Apotek bertahap sambil belajar dan ingin memahami alur serta alasan kode.
- Source project berada di D:\Project\PHP\pos_apotek. Pengguna menulis dan mengubah file PHP sendiri; Codex boleh memeriksa source dan memperbarui README/ringkasan atau artefak yang diminta.
- Jangan menjalankan PHPUnit atau operasi database tanpa memeriksa konfigurasi. Test mengosongkan beberapa tabel. Jangan membaca/menyalin .env, private key, kredensial DB, token, atau password ke dokumentasi/Git.
- Pengguna menggunakan Git lokal. Periksa status/diff sebelum perubahan; jangan reset/clean atau menimpa perubahan pengguna.
- Bila diminta membuat fitur, pengguna belajar dengan menulis file PHP sendiri. Jangan mengubah PHP kecuali diminta. Output file yang diminta harus ditampilkan utuh sesuai instruksi pengguna.

## Project dan runtime

- Stack: PHP native, PDO, MySQL, vanilla JavaScript, Tailwind CSS, Laravel Herd, firebase/php-jwt.
- Origin lokal pengujian: https://pos_apotek.test. APP_ORIGIN harus sama persis dengan header Origin. Cookie auth Secure memerlukan HTTPS.
- Auth mempunyai csrf-token.php, login-process.php, me.php, refresh.php, logout.php. Login membuat sesi/JWT/cookie; refresh merotasi refresh token; logout mencabut sesi. CSRF dan Origin diperiksa pada request mutasi.
- Auth memakai cookie HttpOnly/Secure/SameSite=Strict untuk token sesi. Jangan menyimpan token di localStorage atau environment/Postman yang dibagikan.

## Employees

- Endpoint di api/employees/: list, detail, create, update, status, password.
- Operasi admin dibatasi role dan mutasi memakai CSRF. Status nonaktif mencabut sesi. Tidak ada delete.
- password.php adalah reset admin untuk pegawai lain, memakai new_password. Endpoint menolak reset diri dan target nonaktif; setelah berhasil sesi target dicabut. Tidak ada forced change saat login berikutnya atau audit event reset khusus.
- Test berada di tests/employees/. PHPUnit handler tidak menggantikan pengujian HTTP/cookie Postman.

## Suppliers

- Folder aktif api/suppliers/ (plural): list, detail, create, update, status. Folder api/supplier/ lama (singular) sudah dihapus. Tidak ada endpoint delete.
- Semua endpoint mensyaratkan sesi dan role admin/apoteker; mutasi juga CSRF/Origin. Create menghasilkan SUP-xxxxx melalui id_sequences dalam transaksi. Status menerima boolean atau integer 0/1 dan menormalkannya ke integer.
- Validasi supplier: nama wajib 2–100 karakter, telepon opsional 8–15, email regex opsional, alamat whitelist maks 255. Field opsional kosong menjadi NULL. Query memakai prepared statement.
- Risiko tersisa: test duplikasi create belum benar-benar membuat duplikat/menegaskan rollback; semua test supplier truncate tabel suppliers dan test create truncate id_sequences. Cocokkan ERD dengan struktur aktual; handler memetakan semua SQLSTATE 23000 sebagai duplikasi. Detail memaparkan supplier_tax_id kepada admin/apoteker.

## Postman

- Collection: postman/pos-apotek-auth-supplier.postman_collection.json.
- Environment: postman/pos-apotek-local.postman_environment.json. File yang diperiksa memiliki credential admin kosong dan baseUrl/appOrigin default https://pos_apotek.test.
- Collection menguji auth, employees, suppliers dalam urutan 27 request. Pengguna melaporkan 50 pass, 0 failed, 0 error pada run lokal terakhir; ini bukan hasil PHPUnit.
- Collection membuat satu pegawai kasir dan supplier uji, memperbarui profil/password/status, lalu meninggalkan record aktif karena tidak ada endpoint delete.
- Collection belum mengosongkan employeeId/supplierId environment sebelum create. Jika create gagal dan variable lama masih ada, request setelahnya berpotensi memutasi record uji terdahulu. Kosongkan variable sebelum retry. Script juga mengisi password akun uji ke environment Postman; tinjau sebelum export/commit.

## PHPUnit dan keamanan database

- Test tersedia di tests/auth/, tests/employees/, tests/suppliers/, serta tests/IdSequenceTest.php.
- tests/bootstrap.php memuat handler test dan menetapkan database test test_pos_apotek_target beserta kredensial lokal di source. Pindahkan secret ke konfigurasi lokal ignored Git dan pertimbangkan guard nama DB.
- Setup PHPUnit dapat mengosongkan employees, sesi, refresh token, login attempts, suppliers, dan sequence. Jalankan hanya terhadap database disposable terpisah dari development/Postman/production.
- Tidak ada PHPUnit yang dijalankan pada pembaruan dokumentasi ini. Hasil 50/0/0 yang disebut di atas berasal dari Postman menurut laporan pengguna.

## Dokumentasi

README root, panduan belajar, login, employees, dan suppliers diselaraskan ke penggunaan Postman dan source terbaru. README skema/ERD supplier masih perlu dicocokkan dengan SHOW CREATE TABLE suppliers.
