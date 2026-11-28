# API supplier dan pengujiannya

README ini mencatat kondisi endpoint supplier yang terlihat di source. PHP tetap Anda yang tulis sendiri.

## Kondisi endpoint

| File | Status saat ini | Catatan |
|---|---|---|
| `api/supplier/create.php` | Endpoint baru, terlindungi | POST; login; role `admin` atau `apoteker`; CSRF dan Origin; validasi body; ID `SUP-xxxxx` dibuat server; transaksi database. |
| `api/supplier/_bootstrap.php` | Helper endpoint baru | Memuat bootstrap auth/API, validasi nama/telepon/email/alamat, dan pemetaan error database. Jangan diakses langsung. |
| `api/supplier/suppliers.php` | Endpoint lama | Belum memakai autentikasi; query memakai tabel `supplier` dan kolom `officer_phone` yang tidak cocok dengan skema target yang didokumentasikan. |
| `api/supplier/add_supplier.php` | Endpoint lama | Belum memakai autentikasi/CSRF; menerima `supplier_id` dari request. Jangan dipakai pada data penting. |
| `api/supplier/set_supplier.php` | Endpoint lama | Belum memakai autentikasi/CSRF; ada ketidakcocokan nama parameter SQL dan field `supplier_adress`. |
| `api/supplier/delete_supplier.php` | Endpoint lama/berbahaya | Nama file menyiratkan supplier, tetapi aksi menghapus dari tabel `products` dan memeriksa action `delete_product`; belum ada autentikasi/CSRF. Jangan panggil endpoint ini. |

Endpoint baru yang dapat diuji:

```text
POST {{baseUrl}}/api/supplier/create.php
```

Header: `Content-Type: application/json`, `Origin: {{appOrigin}}`, dan `X-CSRF-Token: {{csrfToken}}`. Cookie CSRF dan sesi harus tersimpan di Postman Cookie Jar. Body:

```json
{
  "supplier_name": "PT Supplier Uji",
  "supplier_phone": "0812-3456-7890",
  "supplier_email": "uji@example.test",
  "supplier_address": "Jl. Veteran No. 9, Jakarta Pusat"
}
```

Respons normal `201` berisi `supplier_id`. Role yang boleh membuat supplier adalah `admin` dan `apoteker`; role `kasir` ditolak. Field telepon, email, dan alamat boleh kosong; nilai kosong disimpan sebagai `NULL`. Nama 2–100 karakter; telepon 8–15 karakter; alamat maksimal 255 karakter. Format email diperiksa dengan regex sederhana. Validasi ini bukan jaminan bahwa alamat email benar-benar ada.

## File Postman

File import disimpan terpisah dari source project:

- [Collection POS Apotek — Auth dan Supplier](C:/Users/Silent%20Demon/Documents/Codex/2026-11-18/in/pos-apotek-auth-supplier.postman_collection.json)
- [Environment POS Apotek Local](C:/Users/Silent%20Demon/Documents/Codex/2026-11-18/in/pos-apotek-local.postman_environment.json)

Import keduanya dari Postman (`Import` → pilih file). Pilih environment `POS Apotek Local`, lalu isi `baseUrl`, `appOrigin`, `adminUsername`, dan `adminPassword` dengan konfigurasi Herd dan akun development Anda. Jangan simpan password produksi di environment yang dibagikan. Collection menjalankan request berurutan: ambil CSRF → login → cek sesi → create supplier → uji validasi nama → logout. Cookie auth ditangani Cookie Jar; access/refresh token tidak disimpan ke variable.

Request create berhasil menyimpan record di database. Jalankan hanya ke database development lokal yang datanya boleh berubah. Pengujian invalid name tidak mengubah database.

## Cakupan PHPUnit dan kekurangannya

Test yang ada: `tests/supplier/CreateSupplierTest.php`. Maksud cakupannya meliputi CSRF, method, role, validasi, ID berurutan, normalisasi field, nilai NULL, dan duplikasi.

Ada beberapa hal yang perlu diperbaiki sebelum test suite dapat dipercaya:

1. `tests/bootstrap.php` tidak memuat `api/supplier/create.php`; test memanggil `handleCreateSupplier()` sehingga kemungkinan gagal dengan undefined function. Tambahkan pemuatan file handler dalam bootstrap test atau require khusus dalam test, dengan urutan konstanta bootstrap yang benar.
2. Test `test_duplikat_kolom_unik_mengembalikan_409_dan_rollback_sequence()` hanya membuat satu supplier lalu memeriksa sequence bernilai 1. Ia tidak membuat data duplikat, tidak memeriksa status 409, dan tidak menguji rollback sequence.
3. Test mematikan `FOREIGN_KEY_CHECKS`, lalu `TRUNCATE suppliers` dan `TRUNCATE id_sequences`. Ini dapat meninggalkan relasi yatim di database test; jangan pernah jalankan ke database aplikasi. Gunakan database test disposable khusus.
4. Test memanggil handler langsung dengan actor buatan. Itu menguji role di handler, tetapi tidak membuktikan jalur HTTP memanggil `authRequireEmployee()` dengan benar. Tambahkan pengujian HTTP/Postman untuk tanpa sesi, role kasir, CSRF/Origin salah, serta cookie.
5. Belum tampak kasus JSON rusak, JSON bukan object, `Content-Type` salah, Origin hilang/tidak cocok, nilai tipe bukan string, email/telepon/alamat batas valid, kegagalan insert, rollback transaksi, dan konkurensi allocator.

## Catatan validasi, schema, dan risiko

- README skema/ERD yang ada mendeskripsikan `supplier_name` maksimal 70 karakter dan tidak menampilkan `supplier_email` atau `is_active`, sementara handler baru menerima nama sampai 100 karakter dan menginsert kolom `supplier_email` serta `is_active`. Cocokkan dengan `SHOW CREATE TABLE suppliers` pada database target sebelum menganggap endpoint siap; dump SQL yang cocok tidak ditemukan di folder project saat audit ini.
- `supplierHandleDatabaseError()` memetakan semua SQLSTATE `23000` sebagai data supplier duplikat. SQLSTATE tersebut juga bisa berarti pelanggaran foreign key atau constraint lain, sehingga pesan `409` bisa keliru. Bedakan constraint duplikat yang memang dikenal.
- Endpoint baru aman dari SQL injection melalui prepared statement dan ID tidak dipercaya dari client. Tetapi endpoint lama yang belum dilindungi masih dapat melewati batas akses selama file tetap dapat dirutekan web server.
- Filter karakter alamat membantu menolak markup, tetapi output tetap perlu di-escape saat ditampilkan di HTML. Validasi input bukan pengganti output encoding.
- Jangan pakai `delete_supplier.php` untuk uji. Ia menghapus produk; pengujian request itu berisiko menghapus data yang bukan supplier.

Saya tidak mengubah file PHP dan tidak menjalankan PHPUnit atau request Postman pada database project.
