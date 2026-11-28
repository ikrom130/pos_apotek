# API Employees dan pengujian dengan Postman

README ini menjelaskan endpoint employees saat ini. Source PHP tetap Anda yang kembangkan.

## Endpoint

| Method | Endpoint | Akses | Tujuan |
|---|---|---|---|
| GET | /api/employees/list.php?page=1&limit=20 | Admin | Daftar, pencarian, filter, pagination |
| GET | /api/employees/detail.php?id=EMP-00001 | Admin | Detail tanpa hash password |
| POST | /api/employees/create.php | Admin | Membuat pegawai; ID dibuat server |
| PUT | /api/employees/update.php?id=EMP-00001 | Admin | Mengubah profil |
| PATCH | /api/employees/status.php?id=EMP-00001 | Admin | Mengaktifkan/nonaktifkan; nonaktif mencabut sesi |
| POST | /api/employees/password.php?id=EMP-00002 | Admin | Mengganti password pegawai lain dan mencabut sesinya |

Mutasi memerlukan sesi admin, Origin sesuai APP_ORIGIN, cookie CSRF, dan X-CSRF-Token yang sama dengan cookie. Role pegawai yang diterima di profil: admin, apoteker, kasir. Create menerima employee_name, employee_role, employee_phone, employee_username, employee_password; password awal 12–72 byte. ID tidak dikirim client dan dihasilkan melalui id_sequences di dalam transaksi.

## Reset password oleh admin

Endpoint password.php adalah reset oleh admin untuk pegawai lain, bukan self-service. Request body berisi new_password. Endpoint menolak admin mereset akun sendiri dan menolak target nonaktif; setelah berhasil, semua sesi target dicabut. Implementasi saat ini tidak memaksa pegawai mengganti password pada login berikutnya dan tidak menulis audit event khusus reset.

## Test PHPUnit

- tests/employees/EmployeeListTest.php
- tests/employees/EmployeeDetailTest.php
- tests/employees/EmployeeCreateTest.php
- tests/employees/EmployeeUpdateTest.php
- tests/employees/EmployeeStatusTest.php
- tests/employees/EmployeePasswordTest.php

Test create mencakup CSRF, method, role, validasi profil/password, hash, ID berurutan, username duplikat dan rollback sequence.

Jalankan test khusus dari PowerShell:

    vendor\bin\phpunit.bat tests\employees

Periksa tests/bootstrap.php dan phpunit.xml. Test memerlukan database test terpisah; beberapa setup melakukan truncation. Jangan arahkan PHPUnit ke database aplikasi/Postman atau produksi.

## Pengujian dengan Postman

Import collection dan environment di folder postman/:

- postman/pos-apotek-auth-supplier.postman_collection.json
- postman/pos-apotek-local.postman_environment.json

Pilih POS Apotek Local, isi adminUsername/adminPassword, lalu jalankan collection berurutan. Collection membuat satu pegawai kasir uji, mengganti profil/password/statusnya, login dengan akun tersebut untuk menguji penolakan akses kasir, lalu logout. Tidak ada endpoint delete sehingga pegawai uji tetap aktif di database.

Sebelum mengulang collection, periksa employeeId/supplierId di environment. Jika create gagal pada run sebelumnya dan ID lama tersimpan, request selanjutnya berpotensi memakai ID lama. Collection membuat password uji dinamis dan menyimpannya ke environment aktif; tinjau nilai environment sebelum export/commit.

Gunakan database development khusus yang aman diubah. Jangan memakai akun atau data staging/produksi.
