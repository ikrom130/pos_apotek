# Referensi belajar: API master pegawai

Kode ini bahan salin dan pelajari. Terapkan sendiri di `D:\Project\PHP\pos_apotek`; folder project tidak diubah.

## Dasar skema dan generator ID

Tabel `employees` memakai `employee_id VARCHAR(15)`, bukan `AUTO_INCREMENT`. Endpoint create tidak menerima ID dari browser. PHP mengambil nomor dari tabel counter umum `id_sequences` dalam transaksi, lalu membentuk `EMP-00004`. Counter disimpan di database; PHP menangani prefix dan format.

Satu tabel counter dapat melayani keluarga ID berbeda. Berdasarkan pola di SQL target:

| Tabel | Contoh | Sequence key | Aturan |
|---|---|---|---|
| `employees` | `EMP-00004` | `employee` | Berurutan global |
| `suppliers` | `SUP-00004` | `supplier` | Berurutan global |
| `products` | `PRD-00009` | `product` | Berurutan global |
| `prescriptions` | `RX-00006` | `prescription` | Berurutan global |
| `purchases` | `PO-202611-001` | `purchase:202611` | Berurutan per bulan |
| `goods_receipts` | `GR-202611-001` | `goods_receipt:202611` | Berurutan per bulan |
| `sales` | `SL-202611-01` | `sale:202611` | Berurutan per bulan |

Counter satu baris dikunci dengan `SELECT ... FOR UPDATE` saat nomor dibaca dan dinaikkan, dalam transaksi yang sama dengan insert. InnoDB memakai locking read untuk pola baca-lalu-perbarui counter seperti ini.[MySQL: Locking Reads](https://dev.mysql.com/doc/refman/8.0/en/innodb-locking-reads.html)

Jalankan `id_sequences.sql` satu kali setelah mengimpor SQL target beserta data seed. Skrip mengisi angka tertinggi dari ID yang sudah ada, termasuk setiap bulan transaksi. Untuk bulan baru, allocator membuat baris counter saat pertama dipakai. Setelah memakai generator, jangan menambahkan ID berpola tersebut secara manual tanpa menyelaraskan counter.

ID numerik lain seperti `category_id`, `dosage_form_id`, `product_unit_id`, `batch_id`, dan ID detail sudah menggunakan `AUTO_INCREMENT` pada SQL. `auth_sessions.session_id` adalah UUID. `product_code` adalah kode produk bisnis yang berbeda dari `product_id` dan mengikuti kebijakan kode tersendiri.

## Peta file referensi ke project

1. Jalankan `id_sequences.sql` satu kali pada database target setelah data awal diimpor.
2. Salin `api/_shared/id-sequence.php` ke `D:\Project\PHP\pos_apotek\api\_shared\id-sequence.php`.
3. Salin isi folder `api/employees/` ke `D:\Project\PHP\pos_apotek\api\employees\`.

| File referensi | Lokasi di project | Peran |
|---|---|---|
| `_shared/id-sequence.php` | `api/_shared/id-sequence.php` | Allocator counter bersama untuk seluruh entitas |
| `_bootstrap.php` | `api/employees/_bootstrap.php` | Muat autentikasi, cek admin, validasi bersama |
| `list.php` | `api/employees/list.php` | Daftar, filter, pagination |
| `detail.php` | `api/employees/detail.php` | Baca satu pegawai |
| `create.php` | `api/employees/create.php` | Buat pegawai, generate ID, hash sandi awal |
| `update.php` | `api/employees/update.php` | Ubah profil, username, role |
| `status.php` | `api/employees/status.php` | Aktif/nonaktif dan cabut sesi |
| `password.php` | `api/employees/password.php` | Ganti sandi dan cabut sesi |

`_bootstrap.php` hanya boleh dimuat oleh endpoint dan memberi 404 jika dibuka langsung.

## Endpoint

| Method dan URL | Tujuan |
|---|---|
| `GET /api/employees/list.php?page=1&limit=20&search=...&role=staff&active=1` | Daftar terfilter dan berpaginasi |
| `GET /api/employees/detail.php?id=EMP-00002` | Detail pegawai |
| `POST /api/employees/create.php` | Membuat akun; server menetapkan ID |
| `PUT /api/employees/update.php?id=EMP-00002` | Mengganti profil, username, atau role |
| `PATCH /api/employees/status.php?id=EMP-00002` | Mengaktifkan/menonaktifkan |
| `POST /api/employees/password.php?id=EMP-00002` | Mengganti sandi setelah konfirmasi sandi admin |

Tidak ada endpoint `DELETE`. Gunakan status nonaktif agar relasi dan riwayat transaksi tetap utuh.

## Contoh body

Pembuatan akun **tidak mengirim `employee_id`**:

```json
{
  "employee_name": "Sari Putri",
  "employee_role": "staff",
  "employee_phone": "081234567890",
  "employee_username": "sari.putri",
  "password": "SandiAwalYangPanjang"
}
```

Respons `201` mengembalikan ID yang ditetapkan server, misalnya `EMP-00004`.

`PUT /api/employees/update.php?id=EMP-00004` menerima seluruh profil:

```json
{
  "employee_name": "Sari Putri",
  "employee_role": "staff",
  "employee_phone": "081234567890",
  "employee_username": "sari.putri"
}
```

`PATCH /api/employees/status.php?id=EMP-00004`:

```json
{ "is_active": false }
```

`POST /api/employees/password.php?id=EMP-00004` meminta sandi admin saat ini:

```json
{
  "current_password": "SandiAdminSaatIni",
  "new_password": "SandiBaruYangPanjang"
}
```

## Contoh vanilla JS

Perubahan data memerlukan cookie sesi dan header CSRF. Jangan menyimpan access/refresh token di JavaScript atau `localStorage`.

```js
const response = await fetch('/api/employees/list.php?page=1&limit=20', {
  credentials: 'same-origin'
});
const result = await response.json();
console.log(result);

const createResponse = await fetch('/api/employees/create.php', {
  method: 'POST',
  credentials: 'same-origin',
  headers: {
    'Content-Type': 'application/json',
    'X-CSRF-Token': window.posCsrfToken
  },
  body: JSON.stringify({
    employee_name: 'Sari Putri',
    employee_role: 'staff',
    employee_phone: '081234567890',
    employee_username: 'sari.putri',
    password: 'SandiAwalYangPanjang'
  })
});
const created = await createResponse.json();
console.log(createResponse.status, created.employee_id);
```

`window.posCsrfToken` tersedia di tab yang menjalankan login. Setelah reload, minta token baru dari `/auth/csrf-token.php` sebelum mengubah data.

## Memakai allocator di entitas lain

Endpoint supplier dan product nantinya dapat memakai fungsi bersama yang sama di dalam transaksi:

```php
$supplierId = nextSequentialId($db, 'supplier', 'SUP-', 5, 15);
$productId = nextSequentialId($db, 'product', 'PRD-', 5, 15);
```

ID transaksi memasukkan bulan pada sequence key dan prefix, misalnya untuk November 2026:

```php
$saleId = nextSequentialId($db, 'sale:202611', 'SL-202611-', 2, 20);
$purchaseId = nextSequentialId($db, 'purchase:202611', 'PO-202611-', 3, 20);
```

Pastikan tanggal transaksi divalidasi di endpoint; sequence key dan prefix bulan harus dibuat dari tanggal server yang dipakai transaksi, bukan nilai format bebas dari browser.

## Urutan belajar

1. Mulai dari `list.php`: query string, validasi filter/pagination, prepared statements, dan JSON.
2. Lanjut `detail.php`: pencarian satu baris dan respons 404.
3. Ikuti `create.php`: transaksi, row lock, allocator ID, hash sandi, dan respons 201.
4. Bandingkan `update.php` dan `status.php`: transaksi serta `SELECT ... FOR UPDATE` sebelum perubahan.
5. Baca `password.php`: konfirmasi sandi admin dan pencabutan sesi pegawai sasaran.
6. Buka `_bootstrap.php` untuk cek autentikasi/role dan validasi pegawai, lalu `api/_shared/id-sequence.php` untuk allocator umum yang dapat dipakai endpoint supplier/produk/transaksi.

## Keputusan dan batas tahap ini

- Perubahan data wajib lolos autentikasi admin dan CSRF.
- Sandi awal/baru dibatasi 12–72 byte agar sesuai endpoint login referensi saat ini.
- Admin tidak dapat menonaktifkan akun atau menurunkan role admin miliknya sendiri.
- Penonaktifan dan reset sandi mencabut sesi pegawai dari `auth_sessions`.
- Belum ada audit trail perubahan pegawai karena skema target belum menyediakan tabel audit.
- Kode mengikuti SQL target dan belum dijalankan terhadap database Anda.