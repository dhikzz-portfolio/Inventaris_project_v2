# Database Dictionary & Model Data

Database: **MySQL 8.4 / MariaDB 10.11+**
Nama database pengembangan: `inventaris_sekolah_v2`
Testing: SQLite `:memory:` (lihat `phpunit.xml`).

Seluruh skema dibuat melalui migration Laravel (`database/migrations`). Jangan mengubah tabel manual di produksi.

> **Status dokumen (22 Oktober 2026).** Dokumen ini memuat skema katalog/unit yang **sudah berjalan** di aplikasi. Tabel inti revisi 2.1 (`item_catalogs`, `asset_units`, `asset_unit_codes`, `asset_unit_movements`, `maintenance_records`, `user_room_scopes`, `loan_item_units`) serta revisi lanjutan (`buildings`, `loans.document_status`) sudah dibuat lewat migration dan diuji. Yang masih tersisa hanyalah **pembukuan kuantitas warisan** pada tabel `items` (lihat `KNOWN_ISSUES.md` butir 1 & 3); rencana migrasi warisan ada di bagian 6.

## 1. Daftar tabel

| Tabel                                                                                             | Fungsi                                                                     | Status dokumen |
| ------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- | -------------- |
| `majors`                                                                                          | Jurusan/kompetensi keahlian.                                               | Ada            |
| `users`                                                                                           | Akun pengguna + role utama + jurusan.                                      | Ada            |
| `buildings`                                                                                       | **Master gedung**: nama + kode satu huruf (A–Z) yang menjadi sumber kebenaran kode lokasi & kode inventaris. | Baru           |
| `rooms`                                                                                           | Ruangan: gedung (relasi), lantai, nomor ruangan, jurusan, penanda laboratorium. | Berubah        |
| `categories`                                                                                      | Kategori/klasifikasi barang dan kode aset.                                 | Ada            |
| `item_catalogs`                                                                                   | **Katalog jenis barang** (definisi, tanpa lokasi dan tanpa jumlah manual). | Baru           |
| `asset_units`                                                                                     | **Unit inventaris fisik**; satu baris = satu barang, termasuk kode aktif.  | Baru           |
| `asset_unit_codes`                                                                                | Buku besar seluruh kode yang pernah diterbitkan (aktif + pensiun).         | Baru           |
| `asset_unit_movements`                                                                            | Riwayat perpindahan unit (asal, tujuan, kode lama, kode baru, petugas).    | Baru           |
| `maintenance_records`                                                                             | Riwayat pemeliharaan perbaikan per unit.                                   | Baru           |
| `user_room_scopes`                                                                                | Penetapan cakupan laboratorium/ruangan untuk Laboran/K3.                   | Baru           |
| `loan_item_units`                                                                                 | Penugasan unit fisik pada baris peminjaman (saat serah terima).            | Baru           |
| `events`                                                                                          | Kegiatan untuk mengikat peminjaman dan BAST gabungan.                      | Ada            |
| `loans`                                                                                           | Header peminjaman (peminjam, periode, status transaksi, status dokumen).   | Berubah        |
| `loan_items`                                                                                      | Baris barang dalam peminjaman + kuantitas kembali/rusak/hilang.            | Berubah        |
| `loan_approvals`                                                                                  | Jejak approval berjenjang + tanda tangan gambar.                           | Ada            |
| `write_offs`                                                                                      | Permohonan penghapusan aset + Berita Acara.                                | Berubah        |
| `notifications_app`                                                                               | Notifikasi in-app per pengguna.                                            | Ada            |
| `institution_settings`                                                                            | Identitas sekolah, logo, kode lokasi, kepala sekolah.                      | Ada            |
| `reports`                                                                                         | Arsip laporan bulanan (path PDF/Excel).                                    | Ada            |
| `activity_log`                                                                                    | Audit trail Spatie (immutable dari aplikasi).                              | Ada            |
| `items`, `item_movements`                                                                         | Model batch lama; dipertahankan sementara sebagai `legacy_*` saat migrasi. | Warisan        |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`        | Otorisasi Spatie Permission.                                               | Ada            |
| `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens` | Infrastruktur Laravel.                                                     | Ada            |

## 2. Kolom penting

### 2.1 `item_catalogs` (katalog jenis barang)

| Kolom                      | Tipe                       | Keterangan                                                     |
| -------------------------- | -------------------------- | -------------------------------------------------------------- |
| `id`                       | bigint PK                  |                                                                |
| `name`                     | varchar(150)               | Nama jenis barang, mis. `Kursi`.                               |
| `category_id`              | FK `categories`            | Kategori/klasifikasi.                                          |
| `brand`                    | varchar(100) default `''`  | Merek; string kosong, bukan `NULL`, agar unique index bekerja. |
| `model_type`               | varchar(100) default `''`  | Tipe/model.                                                    |
| `specification`            | text nullable              | Spesifikasi teknis.                                            |
| `description`              | text nullable              | Catatan umum katalog.                                          |
| `unit`                     | varchar(20) default `unit` | Satuan tampilan.                                               |
| `is_high_value`            | boolean default false      | Wajib approval level 2.                                        |
| `photo_path`               | varchar nullable           | Foto representatif katalog.                                    |
| `created_at`, `updated_at` | timestamp                  |                                                                |

Index:

- `unique(category_id, name, brand, model_type)` — mencegah katalog duplikat.
- `index(name)`, `index(category_id)`.
- **Tidak ada** kolom `quantity`, `qty_damaged`, `qty_lost`, `room_id`, atau `internal_id`. Jumlah unit selalu dihitung dari `asset_units`.

### 2.2 `asset_units` (unit inventaris fisik)

| Kolom                      | Tipe                          | Keterangan                                                    |
| -------------------------- | ----------------------------- | ------------------------------------------------------------- |
| `id`                       | bigint PK                     | Stabil; dipakai URL QR agar tidak berubah saat mutasi.        |
| `item_catalog_id`          | FK `item_catalogs`            | Katalog induk.                                                |
| `inventory_code`           | varchar(8) unique             | Kode aktif, mis. `G1-02-12`. Kanonik huruf kapital.           |
| `room_id`                  | FK `rooms`                    | Lokasi aktif.                                                 |
| `unit_no`                  | unsignedTinyInteger           | 1–99; bagian ketiga kode.                                     |
| `condition`                | string default `BAIK`         | `BAIK`, `RUSAK_RINGAN`, `RUSAK_BERAT` (`Condition`).          |
| `status`                   | string default `AKTIF`        | `AKTIF`, `DIPINJAM`, `HILANG`, `DIHAPUS` (`UnitStatus`).      |
| `acquisition_date`         | date nullable                 | Tanggal perolehan.                                            |
| `acquisition_year`         | unsignedSmallInteger nullable | Tahun perolehan bila tanggal tidak lengkap.                   |
| `acquisition_value`        | decimal(15,2) nullable        | Nilai perolehan; tidak ditampilkan ke Peminjam.               |
| `funding_source`           | varchar(50) nullable          | BOS, komite, hibah, dst.                                      |
| `acquisition_source`       | varchar(50) nullable          | Pembelian, hibah, sumbangan, dst.                             |
| `register_no`              | varchar(50) nullable          | Nomor register aset; unik per katalog bila diisi.             |
| `photo_path`               | varchar nullable              | Foto unit aktual.                                             |
| `notes`                    | text nullable                 | Catatan internal.                                             |
| `last_label_printed_at`    | timestamp nullable            | `NULL` berarti label perlu dicetak (termasuk setelah mutasi). |
| `archived_at`              | timestamp nullable            | Diisi saat unit dihapus; baris tidak dihapus permanen.        |
| `created_at`, `updated_at` | timestamp                     |                                                               |

Index:

- `unique(inventory_code)`.
- `unique(room_id, unit_no)` — menegakkan nomor unik per ruangan untuk unit aktif.
- `unique(item_catalog_id, register_no)` — nomor register unik dalam katalog; `NULL` berulang diizinkan sesuai dialek.
- `index(item_catalog_id)`, `index(room_id, status)`, `index(status, condition)`, `index(acquisition_date)`.
- Pencarian kode memakai prefix index pada `inventory_code`; pencarian katalog memakai `index(name)` pada `item_catalogs`.

Catatan: `inventory_code` sengaja **diredundansi** dari `asset_unit_codes` yang aktif untuk mempercepat pencarian dan unique constraint. Konsistensi dijaga transaksi; perintah `inventory:verify-codes` mencocokkan keduanya secara berkala.

### 2.3 `asset_unit_codes` (buku besar kode)

Mencatat **setiap** kode yang pernah diterbitkan, termasuk yang sudah pensiun. Tabel ini adalah otoritas nomor unit dan mencegah pemakaian ulang.

| Kolom                      | Tipe                | Keterangan                                                              |
| -------------------------- | ------------------- | ----------------------------------------------------------------------- |
| `id`                       | bigint PK           |                                                                         |
| `asset_unit_id`            | FK `asset_units`    | Unit pemilik kode.                                                      |
| `inventory_code`           | varchar(8) unique   | Kode yang diterbitkan; unik seumur hidup.                               |
| `room_id`                  | FK `rooms`          | Ruangan saat kode diterbitkan.                                          |
| `unit_no`                  | unsignedTinyInteger | Nomor unit saat kode diterbitkan.                                       |
| `issued_at`                | timestamp           | Waktu terbit.                                                           |
| `revoked_at`               | timestamp nullable  | Diisi saat kode pensiun karena mutasi/penghapusan; `NULL` = kode aktif. |
| `reason`                   | string              | `PENEMPATAN`, `MUTASI`, `PEMULIHAN`, `PENGHAPUSAN`.                     |
| `created_at`, `updated_at` | timestamp           |                                                                         |

Index:

- `unique(inventory_code)` — kode tidak pernah dipakai dua kali.
- `unique(room_id, unit_no)` — nomor unit tidak pernah dipakai ulang di ruangan tersebut, termasuk setelah unit pindah/dihapus.
- `index(asset_unit_id, revoked_at)` untuk mengambil kode aktif.

Konsekuensi yang disengaja: kapasitas satu ruangan adalah 99 nomor **seumur hidup** (`unit_no` 1–99). Lihat `PROJECT_BRIEF.md` §6.6.

### 2.4 `asset_unit_movements` (riwayat perpindahan)

| Kolom                      | Tipe             | Keterangan                          |
| -------------------------- | ---------------- | ----------------------------------- |
| `id`                       | bigint PK        |                                     |
| `asset_unit_id`            | FK `asset_units` |                                     |
| `from_room_id`             | FK `rooms`       | Ruangan asal.                       |
| `to_room_id`               | FK `rooms`       | Ruangan tujuan.                     |
| `old_code`                 | varchar(8)       | Kode sebelum mutasi (kode pensiun). |
| `new_code`                 | varchar(8)       | Kode setelah mutasi.                |
| `moved_by`                 | FK `users`       | Petugas.                            |
| `moved_at`                 | timestamp        | Waktu perpindahan.                  |
| `reason`                   | string nullable  | Alasan mutasi.                      |
| `note`                     | text nullable    | Catatan tambahan.                   |
| `created_at`, `updated_at` | timestamp        |                                     |

Index: `index(asset_unit_id, moved_at)`, `index(from_room_id)`, `index(to_room_id)`.

### 2.5 `maintenance_records`

`id`, `asset_unit_id`, `performed_at`, `type` (`PERBAIKAN`, `SERVIS`, `PENGGANTIAN`), `description`, `cost` (decimal nullable), `performed_by` (varchar/FK user nullable), `document_path` (nullable, privat), `created_at`, `updated_at`.

Index: `index(asset_unit_id, performed_at)`.

### 2.6 `buildings` dan `rooms` (perubahan)

`buildings` — master gedung. Satu baris = satu gedung sekolah; **huruf kode di sini adalah sumber kebenaran** generator kode lokasi dan kode inventaris.

| Kolom                      | Tipe                  | Keterangan                                                                |
| -------------------------- | --------------------- | ------------------------------------------------------------------------- |
| `id`                       | bigint PK             |                                                                           |
| `name`                     | varchar(100) unique   | Nama gedung, mis. `Gedung G`.                                            |
| `code`                     | char(1) unique        | Huruf gedung A–Z; segmen pertama kode lokasi & kode inventaris.          |
| `is_active`                | boolean default true  | Gedung nonaktif tidak dapat dipakai untuk lokasi baru.                  |
| `created_at`, `updated_at` | timestamp             |                                                                           |

`rooms`:

| Kolom                      | Tipe                  | Keterangan                                                                       |
| -------------------------- | --------------------- | -------------------------------------------------------------------------------- |
| `id`                       | bigint PK             |                                                                                  |
| `major_id`                 | FK `majors` nullable  | Jurusan/penanggung jawab.                                                        |
| `building_id`              | FK `buildings` nullable | Master gedung induk; sumber kebenaran `building_letter`.                       |
| `building_letter`          | char(1)               | Huruf gedung A–Z; segmen pertama kode. Disalin dari master gedung.              |
| `floor`                    | unsignedTinyInteger   | Lantai 1–9; digit kedua segmen pertama.                                         |
| `room_no`                  | unsignedTinyInteger   | Nomor ruangan 1–99; segmen kedua kode.                                          |
| `is_lab`                   | boolean default false | Penanda laboratorium untuk cakupan Ketua Laboran.                               |
| `name`                     | varchar               | Nama ruangan, mis. `Lab Komputer 1`.                                            |
| `code`                     | varchar unique        | Kode ruangan tampilan, mis. `G1-02`; diturunkan dari gedung + lantai + nomor.  |
| `legacy_building`          | varchar nullable      | Teks gedung lama (mis. `G1`), **hanya untuk penelusuran historis**.           |
| `created_at`, `updated_at` | timestamp             |                                                                                  |

Index: `unique(building_letter, floor, room_no)` — menjamin prefix `G1-02` unik dan konsisten dengan kode unit. `unique(code)`. `index(building_id)`.

Catatan: kolom `rooms.building` versi lama **di-rename** menjadi `legacy_building` oleh migration `2026_10_22_195827_create_buildings_table_and_link_rooms.php` dan tidak lagi dipakai membentuk kode. Formulir ruangan sekarang hanya meminta **gedung (dropdown dari master), lantai, dan nomor ruangan**; nama dan kode lokasi diturunkan sistem.

### 2.7 `user_room_scopes`

| Kolom                      | Tipe                | Keterangan                                     |
| -------------------------- | ------------------- | ---------------------------------------------- |
| `id`                       | bigint PK           |                                                |
| `user_id`                  | FK `users`          | Harus berperan `LABORAN` (divalidasi service). |
| `room_id`                  | FK `rooms`          | Harus `is_lab = true`.                         |
| `assigned_by`              | FK `users` nullable | Petugas yang menetapkan.                       |
| `created_at`, `updated_at` | timestamp           |                                                |

Index: `unique(user_id, room_id)`, `index(room_id)`.

Cakupan efektif:

| Peran           | Query unit yang diizinkan                                        |
| --------------- | ---------------------------------------------------------------- |
| `SUPER_ADMIN`   | Semua unit.                                                      |
| `ADMIN_SARPRAS` | Unit pada `rooms.is_lab = true`.                                 |
| `LABORAN`       | Unit pada ruangan yang ada di `user_room_scopes` milik pengguna. |
| `PEMINJAM`      | Unit aktif pada semua ruangan; field internal disembunyikan.     |

### 2.8 `loan_items` dan `loan_item_units` (perubahan)

`loan_items`:

- `item_id` diganti `item_catalog_id` (FK `item_catalogs`). Kuantitas peminjaman tetap dicatat di level katalog.
- `qty_borrowed`, `qty_returned`, `qty_damaged`, `qty_lost`, `condition_out`, `condition_in`, `damage_photo_path`, `returned_room_id` dipertahankan.
- Selama masa transisi, `legacy_item_id` (nullable) menunjuk baris `items` lama untuk keperluan rekonsiliasi.

`loan_item_units`:

| Kolom                      | Tipe               | Keterangan                                                 |
| -------------------------- | ------------------ | ---------------------------------------------------------- |
| `id`                       | bigint PK          |                                                            |
| `loan_item_id`             | FK `loan_items`    | Baris peminjaman.                                          |
| `asset_unit_id`            | FK `asset_units`   | Unit fisik yang diserahkan.                                |
| `assigned_at`              | timestamp          | Waktu serah terima unit.                                   |
| `returned_at`              | timestamp nullable | Waktu unit kembali; `NULL` = penugasan masih terbuka.     |
| `condition_out`            | string             | Kondisi saat keluar.                                       |
| `condition_in`             | string nullable    | Kondisi saat kembali.                                      |
| `opened_at`                | timestamp nullable | Penugasan dibuka (barang keluar ke peminjam).              |
| `closed_at`                | timestamp nullable | Penugasan ditutup (barang kembali).                        |
| `created_at`, `updated_at` | timestamp          |                                                            |

Index: `unique(loan_item_id, asset_unit_id)` — satu unit tidak boleh ditugaskan dua kali pada baris peminjaman yang sama. `index(asset_unit_id)`, `index(asset_unit_id, returned_at)`, dan `unique(asset_unit_id, returned_at)` sebagai pengaman penugasan terbuka.

> **Catatan dialek:** pada MySQL/MariaDB, `NULL` pada unique index diperlakukan sebagai berbeda, sehingga `unique(asset_unit_id, returned_at)` **tidak** dengan sendirinya mencegah dua penugasan terbuka pada unit yang sama. Aturan "satu unit = satu penugasan terbuka" ditegakkan di service (`LoanService::assignUnitsOnHandover()` memakai `LoanItemUnit::openForUnit()`) dan diuji pada kedua dialek.

Catatan: penugasan unit dibuat **saat serah terima**. Peminjaman lama yang belum memiliki penugasan unit tetap sah dan dihitung dari `qty_borrowed`; laporan menandainya `unit_assignment_status = LEGACY`.

### 2.9 `write_offs` (perubahan)

- `item_id` diganti `asset_unit_id` (FK `asset_units`) — penghapusan dieksekusi per unit.
- `quantity` tidak dipakai lagi untuk unit fisik (selalu 1 per baris). Kolom dipertahankan sementara untuk data lama.
- Tambah `batch_no` (varchar nullable) untuk mengelompokkan beberapa unit dalam satu Berita Acara.
- `berita_acara_path`, `status`, `requested_by`, `approved_by`, `approved_at`, `decision_note` tetap.

### 2.10 `loans` — status dokumen terpisah

`loans` mendapat satu kolom baru:

| Kolom           | Tipe                  | Keterangan                                                                                                                     |
| --------------- | --------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| `document_status` | string(30) default `BELUM_DIBUAT` | Status dokumen peminjaman, terpisah dari status transaksi. `BELUM_DIBUAT`, `SIAP_DICETAK`, `DICETAK`, `DITANDATANGANI_FISIK`, `DITANDATANGANI_DIGITAL` (`App\Enums\DocumentStatus`). |

Status dokumen **tidak menentukan kelayakan transaksi**: dokumen yang belum dibuat atau belum ditandatangani tidak memblokir serah terima, dan pencetakan dokumen tidak mengubah status peminjaman. Kolom ini diisi lewat `LoanService::markDocumentStatus()` yang hanya menyentuh kolom `document_status`.

### 2.11 Tabel lain yang tidak berubah

`majors`, `users` (kecuali relasi `roomScopes`), `categories`, `events`, `loan_approvals`, `notifications_app`, `institution_settings`, `reports`, `activity_log` tetap seperti implementasi saat ini. `categories.code` tetap menjadi kode klasifikasi barang (dipakai pada label bersama `register_no`).

## 3. ERD

```
majors 1─n rooms n─1 buildings
                  │            │
                  │            ├─1 asset_unit_codes        (semua kode, aktif + pensiun)
                  │            ├─n asset_unit_movements    (riwayat pindah)
                  │            └─n maintenance_records
                  │
                  ├─n user_room_scopes n─1 users
                  │
                  └─n loan_items (via loan_item_units)

loans 1─n loan_items n─1 item_catalogs
loans 1─n loan_approvals n─1 users
loan_items 1─n loan_item_units n─1 asset_units
users 1─n loans
events 1─n loans
asset_units 1─n write_offs
users 1─n notifications_app
institution_settings (single row)

Warisan (sementara):
items 1─n item_movements
items 1─n loan_items.legacy_item_id
items 1─n write_offs (legacy_item_id)
```

Aturan relasi:

- Satu katalog memiliki banyak unit; setiap unit memiliki tepat satu katalog.
- Setiap ruangan terikat ke **tepat satu master gedung** (`rooms.building_id`); `rooms.building_letter` adalah salinan yang dijaga sinkron dan tidak boleh dipakai sebagai sumber kebenaran.
- Setiap unit memiliki tepat satu lokasi aktif yang valid (`room_id` wajib, `rooms` wajib punya `building_letter`, `floor`, `room_no`).
- Setiap unit memiliki tepat satu kode aktif (`asset_unit_codes.revoked_at IS NULL`), dan kode tersebut sama dengan `asset_units.inventory_code`.
- Riwayat perpindahan tidak pernah dihapus; unit yang dihapus tetap menyimpan kode dan riwayatnya.
- Satu unit hanya boleh punya satu penugasan terbuka pada `loan_item_units` (ditegakkan di service, bukan semata-mata oleh index — lihat §2.8).

## 4. Aturan kode inventaris di database

1. Format kanonik: `^[A-Z][1-9]-[0-9]{2}-[0-9]{2}$`.
2. `asset_units.inventory_code` unik; `asset_unit_codes.inventory_code` unik lintas aktif/pensiun.
3. `asset_unit_codes(room_id, unit_no)` unik **selamanya** — mencegah nomor dipakai ulang setelah pindah/penghapusan.
4. `rooms(building_letter, floor, room_no)` unik — mencegah dua ruangan berbagi prefix/segmen kode yang sama.
5. Nomor unit berikutnya:

```sql
SELECT COALESCE(MAX(unit_no), 0) + 1
FROM asset_unit_codes
WHERE room_id = :room_id;
```

6. Penerbitan kode dilakukan di dalam transaksi dengan `SELECT ... FOR UPDATE` pada baris `rooms` (MySQL). Unique index menjadi pengaman terakhir; pelanggaran unique diulang maksimal 3 kali sebelum gagal.
7. Kode divalidasi ulang terhadap ruangan sebelum simpan:

```
segmen1 = rooms.building_letter || rooms.floor      # huruf dari master gedung
segmen2 = lpad(rooms.room_no, 2, '0')
segmen3 = lpad(unit_no, 2, '0')
kode    = segmen1 || '-' || segmen2 || '-' || segmen3
```

> `rooms.building_letter` selalu berasal dari `buildings.code` (master gedung). Identitas gedung tidak boleh diketik bebas pada formulir ruangan.

8. Kolom `inventory_code` tidak dapat diubah langsung lewat mass assignment; hanya service `InventoryCodeService` yang boleh menulisnya.

## 5. Model kuantitas & ketersediaan

- **Jumlah unit katalog** = `COUNT(asset_units)` dengan `status != 'DIHAPUS'` dan sesuai cakupan akses.
- **Tersedia** = unit `status = 'AKTIF'` dan `condition = 'BAIK'` yang tidak memiliki penugasan terbuka (`loan_item_units.returned_at IS NULL`) dan tidak dibooking pada rentang tanggal yang diminta.
- **Booking tanggal** tetap dihitung dari irisan `loans.start_date`–`loans.due_date` berstatus `PENDING`/`APPROVED`/`DIPINJAM`, dikonversi ke jumlah unit per katalog.
- **Dipinjam** = unit dengan penugasan terbuka pada loan `APPROVED`/`DIPINJAM`, atau `status = 'DIPINJAM'` untuk data legacy.
- **Rusak** = unit `condition IN ('RUSAK_RINGAN','RUSAK_BERAT')` dan `status = 'AKTIF'`.
- **Hilang** = unit `status = 'HILANG'`; tidak menambah ketersediaan dan tidak dihitung sebagai unit aktif yang dapat dipinjam.
- **Dihapus** = unit `status = 'DIHAPUS'` dengan `archived_at` terisi; tetap mengunci kode dan tetap muncul di laporan arsip.
- Pengembalian parsial menambah `loan_items.qty_returned` dan menutup `loan_item_units` terkait; unit yang kembali dengan kondisi rusak mengubah `condition`, bukan `quantity`.
- Selisih hilang menutup penugasan unit dan mengubah `status` unit menjadi `HILANG` setelah disetujui; tidak ada lagi counter `items.qty_lost`.

## 6. Rencana migrasi dari model batch

Migrasi struktur **sudah diimplementasikan** pada `database/migrations/2026_10_21_230001_create_inventory_core_tables.php` dan backfill `App\Services\InventoryBackfillService` (diuji di `InventoryCoreTest`). Tabel `loan_item_units` dibuat pada `2026_10_22_000001_create_loan_item_units_table.php`, master gedung pada `2026_10_22_195827_create_buildings_table_and_link_rooms.php`, dan status dokumen pada `2026_10_22_195826_add_document_status_to_loans_table.php`. Penerapan ke **data sekolah produksi** tetap menunggu pemetaan gedung final (lihat `KNOWN_ISSUES.md` butir 5). Urutan wajib:

### 6.1 Prasyarat: normalisasi ruangan (blocker)

Data lama memakai `rooms.building` berisi `G1`, `G2`, `G3` dan `rooms.code` berisi `G1-01`, `G2-01`, dst. Format baru menafsirkan digit setelah huruf sebagai **lantai**, sehingga `G1`/`G2` lama bertabrakan:

- `building = G1, floor = 1` → prefix `G1`
- `building = G2, floor = 1` → prefix `G1` (**tabrakan**)
- `building = G1, floor = 2` → prefix `G2` (juga bertabrakan dengan gedung `G2`)

Karena itu backfill tidak boleh menebak. Sekolah harus menetapkan pemetaan huruf gedung final (mis. Gedung G, F, H) dan nomor ruangan per lantai. Langkahnya:

1. Buat tabel sementara `room_code_mapping(old_room_id, building_letter, floor, room_no, is_lab)`.
2. Isi pemetaan manual; validasi `unique(building_letter, floor, room_no)`.
3. Baru jalankan migrasi kode. Bila pemetaan belum ada, migrasi harus berhenti dengan pesan jelas, bukan menghasilkan kode ganda.

### 6.2 Langkah migrasi

| #   | Langkah                                                                                                                                                                                             | Catatan                                                                                                           |
| --- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| 1   | Backup database + storage; hentikan penulisan (maintenance mode).                                                                                                                                   | `mysqldump --single-transaction`.                                                                                 |
| 2   | Buat tabel baru: `item_catalogs`, `asset_units`, `asset_unit_codes`, `asset_unit_movements`, `maintenance_records`, `user_room_scopes`, `loan_item_units`.                                          | Additive; aplikasi lama tetap berjalan.                                                                           |
| 3   | Normalisasi `rooms` sesuai §6.1 dan tambah `is_lab`.                                                                                                                                                | Manual + validasi unik.                                                                                           |
| 4   | Bentuk katalog dari `items` dengan pengelompokan `(category_id, name, specification, unit, is_high_value)`; isi `brand` dan `model_type` dengan string kosong karena data lama tidak memisahkannya. | Barang dengan merek/tahun berbeda perlu keputusan; default: satu katalog per kombinasi kategori+nama+spesifikasi. |
| 5   | Pecah setiap baris `items` menjadi `quantity` unit; urutkan berdasarkan `internal_id`; alokasikan `unit_no` per ruangan secara berurutan lintas jenis barang.                                       | Hasil: 23 kursi = 23 baris unit, satu katalog.                                                                    |
| 6   | Terbitkan kode untuk setiap unit dan tulis ke `asset_unit_codes` (`reason = PENEMPATAN`).                                                                                                           | Kode lama `items.internal_id` disimpan di `legacy_internal_id` untuk audit.                                       |
| 7   | Petakan `item_movements` lama ke `asset_unit_movements`.                                                                                                                                            | Pemetaan unit per baris batch bersifat **best-effort**; tandai `note = 'BACKFILL'` bila kuantitas > 1.            |
| 8   | Petakan `loan_items.item_id` ke `item_catalog_id`; isi `legacy_item_id`. Buat `loan_item_units` hanya untuk pinjaman aktif yang dapat dipastikan unitnya.                                           | Pinjaman lama lain ditandai `LEGACY`.                                                                             |
| 9   | Petakan `write_offs.item_id` ke `asset_unit_id`; bila quantity > 1, pecah menjadi beberapa baris dengan `batch_no` sama.                                                                            | Status approval dipertahankan.                                                                                    |
| 10  | Ganti nama tabel lama menjadi `legacy_items` dan `legacy_item_movements`; jangan di-drop pada rilis pertama.                                                                                        | Jalan mundur cepat bila rekonsiliasi gagal.                                                                       |
| 11  | Jalankan `inventory:verify-codes` dan rekonsiliasi: jumlah unit = Σ quantity lama; tidak ada kode ganda; setiap unit punya kode aktif.                                                              | Harus 0 selisih sebelum go-live.                                                                                  |
| 12  | Hapus kolom jumlah pada tabel katalog dan hentikan pembacaan `legacy_*` setelah satu siklus rilis.                                                                                                  | Drop kolom lama pada rilis terpisah setelah backup.                                                               |

### 6.3 Migration yang sudah berjalan

```
2026_10_19_095002_create_items_tables.php
2026_10_20_000001_add_lifecycle_and_quantity_tracking.php
2026_10_21_213347_regenerate_internal_ids_to_new_format.php
2026_10_21_230001_create_inventory_core_tables.php
2026_10_22_000001_create_loan_item_units_table.php
2026_10_22_195826_add_document_status_to_loans_table.php
2026_10_22_195827_create_buildings_table_and_link_rooms.php
2026_10_22_200643_normalize_room_codes_and_internal_ids_to_building_master.php
```

Migration terakhir (`normalize_room_codes_and_internal_ids_to_building_master`) memetakan gedung lama → master gedung, menulis ulang `rooms.code` & `rooms.building_letter`, memperbarui `internal_id` katalog, dan **tidak menyentuh kode unit yang sudah terbit** (`asset_unit_codes`).

### 6.4 Verifikasi pasca-migrasi

- `SELECT COUNT(*) FROM asset_units` = Σ `items.quantity` (baris non-DIHAPUS).
- `SELECT COUNT(*) FROM asset_unit_codes WHERE revoked_at IS NULL` = jumlah unit aktif.
- Tidak ada `asset_units` tanpa kode aktif; tidak ada kode aktif ganda.
- Setiap `asset_units.inventory_code` cocok dengan `rooms.building_letter`, `rooms.floor`, `rooms.room_no`, dan `unit_no`.
- Total unit per katalog pada UI = hasil hitung SQL.
- Riwayat kode unit yang pernah pindah memiliki >1 baris `asset_unit_codes` dan kode lama `revoked_at NOT NULL`.

## 7. Seeder & akun demo

`php artisan db:seed` membuat:

| Username        | Password     | Role            | Istilah produk | Cakupan                                                    |
| --------------- | ------------ | --------------- | -------------- | ---------------------------------------------------------- |
| `wakasek`       | `wakasek123` | `SUPER_ADMIN`   | Super Admin    | Seluruh sekolah                                            |
| `admin.sarpras` | `admin123`   | `ADMIN_SARPRAS` | Ketua Laboran  | Seluruh laboratorium                                       |
| `laboran.tkj`   | `laboran123` | `LABORAN`       | Laboran/K3     | `G1-01` (Lab Komputer 1) + `G2-02` (Lab Jaringan)         |
| `laboran.rpl`   | `laboran123` | `LABORAN`       | Laboran/K3     | `H1-01` (Lab RPL)                                          |
| `guru.budi`     | `guru123`    | `PEMINJAM`      | Peminjam       | RPL                                                        |
| `guru.sari`     | `guru123`    | `PEMINJAM`      | Peminjam       | TKJ                                                        |

Cakupan Laboran/K3 diisi lewat `user_room_scopes`. Ruangan dipetakan lewat identitas **(gedung, lantai, nomor ruangan)** — bukan string kode lokasi — sehingga tetapan tetap benar walaupun format kode berubah.

Data demo hasil `migrate:fresh --seed`: **3 master gedung** (G, H, I), 3 jurusan, 4 kategori, **6 ruangan**, **10 katalog barang**, **40 unit fisik**, 2 kegiatan, 4 peminjaman (selesai, aktif, pending, terlambat), dan 0 arsip laporan (arsip dibuat dari menu **Laporan → Simpan ke Arsip**). Kode lokasi ruang demo: `G1-01`, `G2-02`, `H1-01`, `H2-02`, `G1-03`, `I1-01`. **Ganti seluruh kredensial demo sebelum produksi.**

## 8. Backup

```bash
mysqldump -u root -p --single-transaction inventaris_sekolah > backup-$(date +%F).sql
tar -czf storage-$(date +%F).tar.gz storage/app/private storage/app/public
```

Pulihkan dengan `mysql ... < backup.sql` lalu ekstrak storage. Lihat `DEPLOYMENT.md`.
