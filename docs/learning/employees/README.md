# Referensi belajar: API master pegawai

Kode ini bahan salin dan pelajari. Terapkan sendiri di `D:\Project\PHP\pos_apotek`; folder project tidak diubah.

## Dasar skema

SQL target memiliki tabel `employees` dengan kolom ID, nama, role, telepon, username, hash sandi, status aktif, dan waktu pembuatan/perubahan. Username unik. API tidak memilih atau mengembalikan `employee_password`.

Role yang dipakai sekarang `admin` dan `staff`, sesuai data SQL. Pegawai dinonaktifkan, bukan dihapus, karena riwayat transaksi dapat merujuk ke pegawai.

## Peta file referensi ke project

Salin isi folder `api/employees/` ke `\pos_apotek\api\employees\`:

| File referensi   | Lokasi di project              | Peran                                         |
| ---------------- | ------------------------------ | --------------------------------------------- |
| `_bootstrap.php` | `api/employees/_bootstrap.php` | Muat autentikasi, cek admin, validasi bersama |
| `list.php`       | `api/employees/list.php`       | Daftar, filter, pagination                    |
| `detail.php`     | `api/employees/detail.php`     | Baca satu pegawai                             |
| `create.php`     | `api/employees/create.php`     | Buat pegawai dan hash sandi awal              |
| `update.php`     | `api/employees/update.php`     | Ubah profil, username, role                   |
| `status.php`     | `api/employees/status.php`     | Aktif/nonaktif dan cabut sesi                 |
| `password.php`   | `api/employees/password.php`   | Ganti sandi dan cabut sesi                    |

Semua file endpoint memuat `_bootstrap.php`; file helper tersebut memberi 404 jika dibuka langsung.

## Endpoint

| Method dan URL                                                               | Tujuan                                         |
| ---------------------------------------------------------------------------- | ---------------------------------------------- |
| `GET /api/employees/list.php?page=1&limit=20&search=...&role=staff&active=1` | Daftar pegawai terfilter dan berpaginasi       |
| `GET /api/employees/detail.php?id=EMP-00002`                                 | Detail pegawai                                 |
| `POST /api/employees/create.php`                                             | Membuat akun aktif                             |
| `PUT /api/employees/update.php?id=EMP-00002`                                 | Mengganti profil, username, atau role          |
| `PATCH /api/employees/status.php?id=EMP-00002`                               | Mengaktifkan/menonaktifkan                     |
| `POST /api/employees/password.php?id=EMP-00002`                              | Mengganti sandi setelah konfirmasi sandi admin |

Tidak ada endpoint `DELETE`. Gunakan status nonaktif agar relasi dan jejak transaksi tetap utuh.

## Contoh body

Pembuatan akun:

```json
{
  "employee_id": "EMP-00004",
  "employee_name": "Sari Putri",
  "employee_role": "staff",
  "employee_phone": "081234567890",
  "employee_username": "sari.putri",
  "password": "SandiAwalYangPanjang"
}
```

ID diisi admin karena SQL target belum menyediakan generator ID pegawai. Pilih ID baru. Sandi di-hash menggunakan `password_hash()` dan tidak dikembalikan API.

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

`POST /api/employees/password.php?id=EMP-00004` meminta sandi admin saat ini sebagai konfirmasi:

```json
{
  "current_password": "SandiAdminSaatIni",
  "new_password": "SandiBaruYangPanjang"
}
```

## Contoh vanilla JS

Perubahan data memerlukan cookie sesi dan header CSRF. Jangan menyimpan access/refresh token di JavaScript atau `localStorage`.

```js
const response = await fetch("/api/employees/list.php?page=1&limit=20", {
  credentials: "same-origin",
});
const result = await response.json();
console.log(result);

const createResponse = await fetch("/api/employees/create.php", {
  method: "POST",
  credentials: "same-origin",
  headers: {
    "Content-Type": "application/json",
    "X-CSRF-Token": window.posCsrfToken,
  },
  body: JSON.stringify({
    employee_id: "EMP-00004",
    employee_name: "Sari Putri",
    employee_role: "staff",
    employee_phone: "081234567890",
    employee_username: "sari.putri",
    password: "SandiAwalYangPanjang",
  }),
});
console.log(createResponse.status, await createResponse.json());
```

`window.posCsrfToken` tersedia di tab yang menjalankan login. Setelah reload, minta token baru dari `/auth/csrf-token.php` sebelum melakukan perubahan.

## Urutan belajar

1. Mulai dari `list.php`: lihat query string, validasi pagination/filter, prepared statements, dan respons JSON.
2. Baca `detail.php`: pencarian satu baris dan respons 404.
3. Ikuti `create.php`: validasi masukan, hashing sandi, unique conflict, dan status HTTP 201.
4. Bandingkan `update.php` dengan `status.php`: transaksi dan `SELECT ... FOR UPDATE` sebelum perubahan.
5. Baca `password.php`: konfirmasi ulang sandi admin dan pencabutan seluruh sesi pegawai sasaran.
6. Buka `_bootstrap.php` untuk melihat pemeriksaan autentikasi/role dan validasi yang dipakai ulang.

## Keputusan dan batas tahap ini

- Perubahan data wajib lolos autentikasi admin dan CSRF.
- Sandi awal/baru dibatasi 12–72 byte agar sesuai dengan endpoint login referensi saat ini.
- Admin tidak dapat menonaktifkan akun atau menurunkan role admin miliknya sendiri.
- Penonaktifan dan reset sandi mencabut sesi pegawai dari `auth_sessions`.
- Belum ada audit trail perubahan pegawai karena skema target belum menyediakan tabel audit.
- Kode ini mengikuti skema SQL target dan belum dijalankan terhadap database Anda.
