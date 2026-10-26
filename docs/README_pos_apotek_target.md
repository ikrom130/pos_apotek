# POS Apotek — Dokumentasi Skema Target v1

## 1. Tujuan

Dokumen ini menjelaskan struktur database target POS Apotek berbasis PHP native dan MySQL.

Database target dibuat terpisah dari database lama agar struktur dapat diperiksa terlebih dahulu sebelum digunakan oleh endpoint PHP.

**Status:** rancangan struktur telah disusun. SQL belum dinyatakan berhasil dijalankan atau diuji pada MySQL lokal.

## 2. Berkas proyek

- `pos_apotek_target_schema.sql`: membuat database dan tabel target.
- `pos_apotek_target_validation.sql`: memeriksa struktur dan beberapa aturan konsistensi.
- `README_pos_apotek_target.md`: dokumentasi struktur, aturan bisnis, dan pengujian.

## 3. Database target

Nama database: `pos_apotek_target`

Database ini ditujukan untuk pengujian terpisah. Skrip skema tidak memindahkan data dari database lama dan tidak membuat data dummy.

Sebelum menjalankan SQL:

1. Pastikan backup database lama tersedia.
2. Pastikan MySQL yang digunakan mendukung `CHECK` constraint.
3. Jalankan skrip hanya pada lingkungan lokal atau staging.
4. Periksa hasil eksekusi dan simpan pesan error jika terjadi kegagalan.

Contoh menjalankan dari terminal:

```bash
mysql -u USER_DATABASE -p < pos_apotek_target_schema.sql
```

Ganti `USER_DATABASE` dengan username MySQL lokal.

## 4. Daftar tabel

### A. Master data

| Tabel                    | Fungsi                                                 |
| ------------------------ | ------------------------------------------------------ |
| `employees`              | Data pegawai dan identitas untuk autentikasi mendatang |
| `suppliers`              | Data pemasok                                           |
| `supplier_bank_accounts` | Beberapa rekening bank milik pemasok                   |
| `categories`             | Kategori produk                                        |
| `dosage_forms`           | Bentuk sediaan produk                                  |
| `units`                  | Master satuan                                          |
| `products`               | Data utama produk dan ringkasan stok                   |
| `product_units`          | Satuan produk, faktor konversi, dan harga              |

### B. Pembelian dan penerimaan

| Tabel                       | Fungsi                                      |
| --------------------------- | ------------------------------------------- |
| `purchases`                 | Pesanan pembelian                           |
| `purchase_items`            | Detail produk dan jumlah yang dipesan       |
| `stock_batches`             | Stok berdasarkan batch                      |
| `goods_receipts`            | Dokumen penerimaan barang                   |
| `goods_receipt_items`       | Detail barang yang benar-benar diterima     |
| `purchase_costs`            | Biaya tambahan penerimaan                   |
| `purchase_cost_allocations` | Alokasi biaya tambahan ke detail penerimaan |
| `purchase_payments`         | Riwayat pembayaran pembelian                |

### C. Penetapan harga

| Tabel                      | Fungsi                                                     |
| -------------------------- | ---------------------------------------------------------- |
| `product_pricing_settings` | Target margin setiap produk                                |
| `product_price_proposals`  | Usulan harga yang menunggu persetujuan atau sudah ditinjau |

### D. Penjualan dan resep

| Tabel           | Fungsi                                            |
| --------------- | ------------------------------------------------- |
| `prescriptions` | Identitas resep                                   |
| `sales`         | Transaksi penjualan                               |
| `sale_items`    | Detail produk, batch, jumlah, dan harga transaksi |

### E. Pergerakan stok

| Tabel                 | Fungsi                               |
| --------------------- | ------------------------------------ |
| `inventory_movements` | Ledger utama seluruh pergerakan stok |

Total target: **22 tabel**.

## 5. Keputusan bisnis penting

### Produk dan satuan

- Produk boleh didaftarkan sebelum mempunyai stok.
- Setiap produk mempunyai satu satuan dasar stok.
- `product_units` menjadi sumber konversi satuan khusus produk.
- Faktor konversi harus berupa bilangan bulat positif.
- Perubahan harga dan konversi tidak boleh mengubah riwayat transaksi.
- Produk yang sudah mempunyai riwayat transaksi dinonaktifkan, bukan dihapus.
- `products.storage_location` adalah teks opsional untuk keterangan rak atau lokasi penyimpanan. Petunjuk pengisiannya akan ditambahkan di frontend.

Aplikasi wajib menjaga agar satuan dasar di `products.stock_unit_id` sesuai dengan baris satuan dasar di `product_units`.

### Supplier

- Satu supplier dapat mempunyai beberapa rekening melalui `supplier_bank_accounts`.
- Nama petugas supplier yang mengantarkan barang dicatat pada setiap penerimaan melalui `goods_receipts.supplier_delivery_person`.
- Pembayaran transfer menyimpan snapshot informasi rekening yang digunakan agar riwayat pembayaran tetap dapat ditelusuri.

### Pembelian dan penerimaan

- Pesanan pembelian berbeda dari penerimaan barang.
- Satu pesanan dapat diterima secara bertahap.
- Jumlah penerimaan tidak boleh melebihi sisa pesanan.
- Hanya penerimaan yang dikonfirmasi yang boleh menambah stok dan menjadi sumber biaya terbaru.
- Pembayaran tidak menambah stok.
- Pembayaran sebagian diperbolehkan, tetapi total pembayaran tidak boleh melebihi saldo tagihan.
- Penerimaan yang sudah dikonfirmasi tidak boleh dihapus atau diubah diam-diam.

### Biaya perolehan

Biaya perolehan memperhitungkan nilai barang setelah diskon, alokasi biaya tambahan, dan pajak yang tidak dapat dipulihkan.

Biaya tambahan dialokasikan secara proporsional terhadap nilai bersih barang. Jika nilai bersih total nol, konfirmasi harus ditolak kecuali metode alokasi khusus telah disetujui dan diimplementasikan.

Biaya perolehan per satuan dasar dihitung berdasarkan total biaya akhir baris dibagi jumlah satuan dasar yang diterima.

### Penetapan harga

- Target margin ditetapkan per produk.
- Rekomendasi harga dihitung dari biaya perolehan dan target margin.
- Perubahan biaya naik atau turun sebesar 20% atau lebih menghasilkan peringatan.
- Peringatan tidak otomatis memblokir proses.
- Admin harus menyetujui usulan sebelum harga aktif berubah.
- Harga transaksi lama tidak boleh berubah setelah harga master diperbarui.

Rumus rekomendasi:

`Harga rekomendasi = biaya per unit / (1 - target margin / 100)`

### Stok

- `stock_batches.batch_stock` adalah saldo stok per batch.
- `products.product_stock` adalah ringkasan stok produk.
- `inventory_movements` adalah ledger utama.
- Pembaruan batch, ledger, dan ringkasan produk harus dilakukan dalam satu transaksi database dari PHP.
- Tidak menggunakan trigger MySQL.
- Batch kedaluwarsa tidak boleh dijual.
- Pemilihan batch menggunakan FEFO: batch dengan tanggal kedaluwarsa paling awal diprioritaskan.
- Koreksi transaksi dilakukan melalui pergerakan pembalik, bukan penghapusan riwayat.

### Penjualan

- Versi pertama hanya mendukung pembayaran tunai.
- Penjualan yang sudah selesai tidak boleh diedit diam-diam.
- Pembatalan harus mencatat siapa yang membatalkan, kapan, dan alasannya.
- Retur penjualan belum termasuk lingkup versi pertama.

## 6. Aturan yang wajib ditangani oleh PHP

Foreign key dan `CHECK` constraint tidak cukup untuk menjaga seluruh aturan bisnis.

Endpoint PHP harus menangani:

1. Konsistensi satuan dasar produk.
2. Batas penerimaan terhadap sisa pesanan.
3. Alokasi biaya tambahan dan pembulatan.
4. Batas pembayaran terhadap saldo tagihan.
5. Sinkronisasi saldo batch, ringkasan produk, dan ledger.
6. Pemilihan batch FEFO dan penolakan batch kedaluwarsa.
7. Pencegahan persetujuan harga ganda.
8. Pembaruan harga aktif dan status persetujuan secara atomik.
9. Idempotensi mutasi stok menggunakan `event_key`.
10. Penggunaan transaksi database dan rollback saat operasi gagal.

Password pegawai harus disimpan menggunakan hash dari `password_hash()` dan diperiksa menggunakan `password_verify()`.

## 7. Rencana pengujian

- [ ] SQL skema berhasil dijalankan pada database target.
- [ ] Terdapat 22 tabel.
- [ ] Kolom `products.storage_location` tersedia dan boleh bernilai `NULL`.
- [ ] Foreign key dan unique index terdaftar dengan benar.
- [ ] `CHECK` constraint diuji dengan nilai valid dan tidak valid.
- [ ] Relasi produk, satuan, pembelian, penerimaan, dan batch diperiksa.
- [ ] Setelah endpoint tersedia, uji transaksi stok dan rollback.
- [ ] Jalankan pemeriksaan konsistensi saldo batch dan ringkasan produk.

**Catatan:** dokumen ini bukan skrip migrasi data dari database lama. Migrasi perlu dirancang secara terpisah jika nantinya dibutuhkan.
