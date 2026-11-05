# Revisi V9 — Distribusi Berbasis Stok Penerimaan

Tanggal: 5 November 2026
Status: **diimplementasikan dan diuji** untuk alur baru; alur alokasi lama
dipertahankan sebagai jalur kompatibilitas (lihat bagian 7).

## 1. Ringkasan

| Modul | Perubahan |
|---|---|
| Pengadaan | Mode **stok**: hanya mencatat katalog, jumlah, satuan, tahun, dan sumber dana. Ruangan dan spesifikasi tidak wajib. |
| Penerimaan | Menyimpan **jumlah aktual** ke `procurement_receipts`. Tidak membuat unit inventaris. |
| Distribusi | Satu formulir dapat berisi banyak alokasi ruangan; setiap baris menentukan merek, model, tahun, sumber dana, jumlah, kondisi fisik, dan status operasional sendiri. Unit inventaris diterbitkan per baris. |
| Stok | Sisa stok = total penerimaan − total distribusi bersumber stok. Tidak ada penghitungan ganda unit. |
| Status | Riwayat status operasional disimpan di `asset_unit_status_logs`; perubahan manual wajib alasan. |
| Filter | Katalog menambah filter merek, model, kondisi, dan status dengan reset bertingkat gedung → lantai → ruangan. |

## 2. Alur baru

```
Kategori → Katalog → Pengadaan (jumlah)
    → Penerimaan (jumlah aktual, stok)
        → Distribusi (satu/lebih alokasi)
            → Alokasi ruangan + kelompok spesifikasi
                → Unit inventaris (kode dibuat backend)
```

1. `items.store` dengan `procurement_mode=STOK` membuat katalog tanpa alokasi
   ruangan. `item_catalogs.room_id` kini nullable; ID internal memakai format
   administratif `stok-NNN` sampai unit pertama terbit.
2. Pengajuan tetap mengikuti state machine tiga tahap. Pengadaan mode stok yang
   dibuat Wakasek langsung berstatus `DISETUJUI` (belum menjadi inventaris).
3. `items.acquisitions.receive` menyimpan `received_total` dan kondisi dominan
   ke `procurement_receipts`; status menjadi `BARANG_DITERIMA`.
4. `DistributionService::distributeFromStock()` memvalidasi sisa stok di dalam
   transaksi dengan `lockForUpdate`, lalu membuat unit dan baris distribusi.
   Status katalog berubah menjadi `INVENTARIS_TERCATAT` saat unit pertama terbit.
5. Distribusi dapat diulang selama sisa stok > 0 (bertahap, lintas ruangan,
   lintas spesifikasi).

## 3. Perhitungan stok

- `Item::receivedStockQuantity()` — jumlah `procurement_receipts.quantity`.
- `Item::distributedStockQuantity()` — jumlah `distributions.quantity` dengan
  `source = STOK`.
- `Item::remainingStockQuantity()` — selisih keduanya, minimum 0.

Distribusi lama (`source = UNIT`, memindahkan unit yang sudah ada) tidak
mengurangi stok penerimaan. Katalog lama yang unitnya sudah terbentuk sebelum
revisi ini tidak memiliki baris penerimaan, sehingga tidak muncul sebagai stok
bebas. Ini memenuhi aturan "barang yang sudah mempunyai unit inventaris tidak
boleh dihitung kembali sebagai stok belum didistribusikan".

## 4. Kondisi fisik dan status operasional

- `distributions.condition` menyimpan kondisi fisik kelompok alokasi.
- `distributions.status` menyimpan status operasional awal; default `AKTIF`.
- Kondisi rusak saat distribusi **tetap** menerbitkan unit dan membuat laporan
  kerusakan otomatis melalui `DamageReportService` (idempoten per unit).
- Status `HILANG` diizinkan saat distribusi dengan alasan wajib dan riwayat
  `asset_unit_status_logs` (`source = DISTRIBUSI`).
- Status `DIPINJAM` dan `DIHAPUS` **ditolak** pada distribusi karena memerlukan
  transaksi peminjaman/penghapusan tersendiri. Pesan validasi mengarahkan ke
  menu terkait.
- Perubahan status manual (`PATCH /units/{unit}`) wajib `status_reason` dan
  dicatat dengan `source = MANUAL`. Perubahan otomatis oleh transaksi tetap
  menambahkan baris riwayat baru (`source = TRANSAKSI`) pada serah terima
  peminjaman, pengembalian, dan penghapusan inventaris; histori manual tidak
  dihapus. Satu-satunya penulis status untuk alur manual/distribusi adalah
  `AssetUnitService::changeStatus()`; alur transaksi menulis ke ledger yang sama
  di dalam transaksi databasenya.

## 5. Filter inventaris

`App\Livewire\Catalog\CatalogDirectory` kini menerapkan urutan filter:

1. kategori, 2. pencarian nama katalog/kode, 3. gedung, 4. lantai, 5. ruangan,
6. merek, 7. model, 8. kondisi fisik, 9. status operasional, 10. ketersediaan.

Semua filter dijalankan di query backend dengan cakupan pengguna
(`visibleTo`). Perubahan gedung mereset lantai/ruangan, perubahan lantai
mereset ruangan, dan perubahan merek mereset model. Halaman spesifikasi dan unit
sudah memiliki filter setara (merek/model/kondisi/status/gedung/lantai/ruangan)
dan tidak diubah strukturnya.

## 6. Perubahan database

Migration `2026_11_05_000002_add_stock_receipts_and_distribution_details`:

| Objek | Perubahan |
|---|---|
| `item_catalogs.room_id` | Menjadi nullable (pengadaan belum berlokasi). |
| `procurement_receipts` | Baru: katalog, jumlah, kondisi, catatan, penerima, waktu. |
| `distributions` | Tambah `source` (`STOK`/`UNIT`), `condition`, `status`, `reason`; index `(item_catalog_id, source)`. |
| `asset_unit_status_logs` | Baru: unit, status sebelum/sesudah, sumber, alasan, pelaku, waktu. |

Tidak ada kolom atau tabel lama yang dihapus. Tidak ada backfill yang menebak
merek/model/lokasi. Data lama tetap dapat ditelusuri.

## 7. Kompatibilitas dan batasan

- **Alur alokasi lama tetap aktif**: payload `allocations`/`spec_groups` tanpa
  `procurement_mode` dinormalisasi sebagai mode `ALOKASI` dan perilakunya tidak
  berubah (termasuk pengadaan langsung oleh Wakasek). Ini menjaga data dan
  pengujian lama; form baru memakai mode stok sebagai bawaan.
- Belum ada entitas `procurements` terpisah. Satu baris `item_catalogs` masih
  menjadi induk satu pengadaan. Karena itu menambah stok lewat mode stok ke
  katalog yang sudah tercatat ditolak dengan pesan validasi (jumlah pengadaan
  baru tidak boleh hilang); formulir otomatis beralih ke mode alokasi ruangan
  saat katalog lama dipilih. Dukungan beberapa pengadaan per katalog
  memerlukan tabel `procurements` tersendiri dan belum dikerjakan.
- Distribusi belum membuat transaksi peminjaman/mutasi otomatis untuk status
  khusus; status tersebut sengaja ditolak agar tidak ada status tanpa transaksi.
- Distribusi ulang unit antar-ruangan tetap tersedia sebagai bagian
  "Relokasi unit yang sudah ada" dan akan dipensiunkan setelah semua pemakaian
  beralih ke menu Mutasi.
- Pengujian konkurensi dilakukan melalui `lockForUpdate` + validasi ulang di
  dalam transaksi; belum ada uji beban paralel pada MySQL.

## 8. Pengujian

| Suite | Hasil |
|---|---|
| `StockDistributionTest` (12 tes) | Lulus — pengadaan stok, penerimaan, distribusi bertahap, spesifikasi per alokasi, kondisi rusak, status khusus, alasan manual, penolakan penambahan stok ke katalog tercatat, kompatibilitas alokasi lama. |
| `CatalogFilterTest` (4 tes) | Lulus — filter merek/model/kondisi/status, reset bertingkat, reset semua filter. |
| `SpecificationDistributionTest`, `MultiLocationProcurementTest`, `AcquisitionWorkflowTest` (27 tes) | Lulus — alur lama tidak berubah. |
| `RevisionV8Test`, `InventoryFlowTest`, `InventoryCoreTest`, `ItemQuantityConsistencyTest`, `MovementAndWriteOffTest`, `CatalogDeletionTest` (57 tes) | Lulus — satu tes diperbarui karena aturan alasan perubahan status manual. |
| `SearchableCatalogTest` | Lulus. |

Database uji: SQLite `:memory:` (sesuai `phpunit.xml`). Migration juga dijalankan
pada MySQL pengembangan (`inventaris_sekolah_v2`) dan berhasil. Uji paralel
MySQL dan uji end-to-end Playwright tidak dijalankan pada revisi ini.
