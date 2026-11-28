# API Suppliers dan pengujian

Source supplier aktif berada di api/suppliers/ (plural). Folder lama api/supplier/ (singular) sudah dihapus dari working tree.

## Endpoint dan akses

Semua endpoint melewati autentikasi sesi dan membatasi role ke admin atau apoteker. Request mutasi juga memerlukan Origin yang sama dengan APP_ORIGIN dan CSRF header-cookie yang cocok.

| Method | Endpoint | CSRF | Fungsi |
|---|---|---:|---|
| GET | /api/suppliers/list.php?page=1&limit=20&search=&active=1 | Tidak | Daftar, pencarian, filter aktif, pagination |
| GET | /api/suppliers/detail.php?id=SUP-00001 | Tidak | Detail supplier |
| POST | /api/suppliers/create.php | Ya | Membuat supplier dengan ID server-side |
| PUT | /api/suppliers/update.php?id=SUP-00001 | Ya | Mengubah profil |
| PATCH | /api/suppliers/status.php?id=SUP-00001 | Ya | Mengaktifkan/nonaktifkan |

Tidak ada endpoint delete.

## Request dan validasi

Create dan update menerima JSON dengan supplier_name, supplier_phone, supplier_email, supplier_address. Nama wajib 2–100 karakter. Telepon opsional 8–15 karakter. Email opsional, dinormalisasi huruf kecil dan diperiksa dengan regex. Alamat opsional maksimal 255 karakter dan dibatasi whitelist karakter. Field opsional kosong menjadi NULL.

Status menerima JSON boolean atau integer 0/1, lalu nilai dinormalkan ke integer sebelum query. String "0"/"1" ditolak. ID dinormalisasi ke huruf besar dan harus berupa SUP- diikuti 5–11 digit.

Create memulai transaksi, membuat ID dari id_sequences dengan row lock, memasukkan supplier aktif, lalu commit. Query memakai prepared statement. Tidak ada delete; status nonaktif mempertahankan relasi dan riwayat.

## Test PHPUnit

- tests/suppliers/CreateSupplierTest.php
- tests/suppliers/SupplierListTest.php
- tests/suppliers/SupplierDetailTest.php
- tests/suppliers/SupplierUpdateTest.php
- tests/suppliers/SupplierStatusTest.php

Cakupan mencakup role, method, CSRF mutasi, validasi, 404, pagination/filter/search, update, status, dan alokasi ID. Celah: test create bernama duplikasi hanya membuat satu supplier lalu memeriksa sequence; belum membuktikan response 409 atau rollback karena duplikasi.

Semua setup test supplier menjalankan TRUNCATE TABLE suppliers dengan FOREIGN_KEY_CHECKS dimatikan. Test create juga mengosongkan id_sequences. Gunakan database PHPUnit disposable yang terpisah dari database aplikasi/Postman; truncation dapat meninggalkan transaksi yatim dan mereset counter ID entitas lain.

Test memanggil handler langsung, bukan HTTP penuh. Collection Postman melengkapi pemeriksaan sesi, cookie, Origin, CSRF, routing, dan role lewat server Herd.

## Pengujian dengan Postman

Import:

- postman/pos-apotek-auth-supplier.postman_collection.json
- postman/pos-apotek-local.postman_environment.json

Pilih environment POS Apotek Local. Jika host Herd Anda https://pos_apotek.test, cukup isi adminUsername dan adminPassword; jika berbeda, sesuaikan juga baseUrl dan appOrigin. Request create menulis record permanen. Collection mengubah profil dan status, lalu mengaktifkan kembali supplier; data uji tetap ada karena delete tidak tersedia.

Pengguna melaporkan run terakhir collection mendapatkan 50 pass, 0 failed, 0 error. Itu hasil uji HTTP Postman lokal, bukan hasil PHPUnit.

## Catatan skema dan risiko

ERD lama masih berbeda dari kolom yang dipakai endpoint: source memakai supplier_email, supplier_tax_id, is_active, updated_at, dan nama sampai 100 karakter, sementara ERD yang ada belum menunjukkan semua kolom tersebut dan menampilkan supplier_name VARCHAR(70). Cocokkan dengan SHOW CREATE TABLE suppliers lalu selaraskan SQL/ERD.

supplierHandleDatabaseError() memetakan semua SQLSTATE 23000 sebagai duplikasi supplier; pelanggaran constraint lain juga dapat dilaporkan keliru sebagai 409. Field opsional dengan tipe non-string saat ini dapat dinormalisasi menjadi NULL, bukan selalu ditolak. Detail mengembalikan supplier_tax_id kepada admin dan apoteker; pastikan itu memang sesuai kebutuhan akses.

Collection belum menghapus employeeId/supplierId lama dari environment sebelum create. Kosongkan variable sebelum mengulang setelah create gagal agar request mutasi tidak memakai ID uji dari run terdahulu.
