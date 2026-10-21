# Database Dictionary & Model Data

Database: **MySQL 8.4 / MariaDB 10.11+**
Nama database pengembangan: `inventaris_sekolah_v2`
Testing: SQLite `:memory:` (lihat `phpunit.xml`).

Seluruh skema dibuat melalui migration Laravel (`database/migrations`). Jangan mengubah tabel manual di produksi.

## 1. Daftar tabel

| Tabel                                                                                             | Fungsi                                                          |
| ------------------------------------------------------------------------------------------------- | --------------------------------------------------------------- |
| `majors`                                                                                          | Jurusan/kompetensi keahlian.                                    |
| `users`                                                                                           | Akun pengguna + role utama + jurusan (untuk cakupan laboran).   |
| `rooms`                                                                                           | Ruangan: gedung, lantai, kode unik, jurusan.                    |
| `categories`                                                                                      | Kategori/klasifikasi barang dan kode aset.                      |
| `items`                                                                                           | Master aset (satu baris = satu kelompok unit).                  |
| `item_movements`                                                                                  | Riwayat mutasi lokasi (asal, tujuan, petugas, waktu).           |
| `events`                                                                                          | Kegiatan untuk mengikat peminjaman dan BAST gabungan.           |
| `loans`                                                                                           | Header peminjaman (peminjam, periode, status, dokumen).         |
| `loan_items`                                                                                      | Baris barang dalam peminjaman + kuantitas kembali/rusak/hilang. |
| `loan_approvals`                                                                                  | Jejak approval berjenjang + tanda tangan gambar.                |
| `write_offs`                                                                                      | Permohonan penghapusan aset + Berita Acara.                     |
| `notifications_app`                                                                               | Notifikasi in-app per pengguna.                                 |
| `institution_settings`                                                                            | Identitas sekolah, logo, kode lokasi, kepala sekolah.           |
| `reports`                                                                                         | Arsip laporan bulanan (path PDF/Excel).                         |
| `activity_log`                                                                                    | Audit trail Spatie (immutable dari aplikasi).                   |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`        | Otorisasi Spatie Permission.                                    |
| `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens` | Infrastruktur Laravel.                                          |

## 2. Kolom penting

### `items`

| Kolom                                | Tipe             | Keterangan                                                                |
| ------------------------------------ | ---------------- | ------------------------------------------------------------------------- |
| `id`                                 | bigint PK        |                                                                           |
| `internal_id`                        | varchar unique   | ID internal/spasial, contoh `g1-01-01`. Berubah saat mutasi ruangan. |
| `location_code`                      | varchar          | Kode lokasi/instansi (baris 1 label).                                     |
| `asset_code`                         | varchar          | Kode barang/klasifikasi (dari kategori).                                  |
| `register_no`                        | varchar          | Nomor register, unik bersama `asset_code`.                                |
| `name`, `specification`              | varchar/text     | Nama & spesifikasi.                                                       |
| `category_id`, `room_id`             | FK               | Kategori & lokasi.                                                        |
| `acquisition_year`, `funding_source` | int/varchar      | Tahun perolehan & sumber dana.                                            |
| `quantity`                           | int              | Total unit pada baris ini.                                                |
| `qty_damaged`                        | int              | Unit rusak permanen (tidak dapat dipinjam).                               |
| `qty_lost`                           | int              | Unit hilang permanen.                                                     |
| `unit`                               | varchar          | Satuan (`unit`, `lembar`, `botol`, ...).                                  |
| `is_high_value`                      | bool             | Wajib approval level 1 + level 2.                                         |
| `status`                             | enum string      | `BAIK`, `RUSAK_RINGAN`, `RUSAK_BERAT`, `HILANG`, `DIPINJAM`, `DIHAPUS`.   |
| `photo_path`                         | varchar nullable | Foto publik (`storage/app/public/items`).                                 |
| `last_label_printed_at`              | timestamp        | Untuk kebutuhan re-print.                                                 |
| `acquisition_status`                 | string           | `PENDING`, `APPROVED`, `REJECTED`.                                        |
| `acquisition_*`                      | mixed            | Pengaju, penyetuju, waktu, catatan.                                       |

Index: unique `internal_id`, unique (`asset_code`,`register_no`), index `name`, `status`, `room_id`, `category_id`.

### `loans`

| Kolom                                           | Keterangan                                                             |
| ----------------------------------------------- | ---------------------------------------------------------------------- |
| `borrower_id`, `event_id`                       | Peminjam & kegiatan (opsional).                                        |
| `purpose`                                       | Tujuan peminjaman.                                                     |
| `start_date`, `due_date`, `returned_at`         | Periode & waktu kembali.                                               |
| `status`                                        | `PENDING`, `APPROVED`, `DIPINJAM`, `SELESAI`, `DITOLAK`, `DIBATALKAN`. |
| `needs_high_value_approval`                     | Snapshot apakah melibatkan barang bernilai tinggi.                     |
| `handed_over_at`, `handed_over_by`              | Bukti serah terima.                                                    |
| `cancelled_at`, `cancelled_by`, `cancel_reason` | Pembatalan.                                                            |
| `bast_path`, `letter_path`                      | Dokumen privat.                                                        |

Index: `(status, start_date, due_date)` untuk query ketersediaan.

### `loan_items`

| Kolom                           | Keterangan                                                           |
| ------------------------------- | -------------------------------------------------------------------- |
| `loan_id`, `item_id`            | Relasi.                                                              |
| `qty_borrowed`                  | Jumlah dipinjam.                                                     |
| `qty_returned`                  | **Akumulatif** jumlah kembali. `NULL` = belum ada pengembalian.      |
| `qty_damaged`                   | Akumulasi unit kembali dalam kondisi rusak.                          |
| `qty_lost`                      | Selisih yang belum kembali (pending, dipindah ke item saat ditutup). |
| `condition_out`, `condition_in` | Kondisi keluar/masuk.                                                |
| `damage_photo_path`             | Foto bukti privat.                                                   |
| `returned_room_id`              | Ruangan tujuan pengembalian.                                         |

### `loan_approvals`

`loan_id`, `approver_id`, `level` (1 laboran, 2 wakasek), `decision` (`APPROVED`/`REJECTED`), `note`, `signature_path`, `decided_at`.

### `item_movements`

`item_id`, `from_room_id`, `to_room_id`, `moved_by`, `quantity`, `note`, `moved_at`.

### `write_offs`

`item_id`, `quantity`, `reason`, `berita_acara_path`, `status`, `requested_by`, `approved_by`, `approved_at`, `decision_note`.

### `notifications_app`

`user_id`, `type`, `message`, `link`, `read_at`. Index `(user_id, read_at)`.

## 3. Relasi utama

```
majors 1─n rooms 1─n items n─1 categories
users 1─n loans 1─n loan_items n─1 items
loans 1─n loan_approvals n─1 users
items 1─n item_movements
items 1─n write_offs
events 1─n loans
users 1─n notifications_app
institution_settings (single row)
```

## 4. Model kuantitas & ketersediaan

- Tersedia sekarang = `quantity - qty_damaged - qty_lost - Σ(qty_borrowed - qty_returned)` untuk pinjaman `APPROVED`/`DIPINJAM`.
- Pemesanan pada rentang tanggal tertentu = `quantity - qty_damaged - qty_lost - Σ` pinjaman `PENDING`/`APPROVED`/`DIPINJAM` yang beririsan.
- Pengembalian parsial menambah `qty_returned`; selisih dicatat `loan_items.qty_lost`.
- Penutupan selisih memindahkan `loan_items.qty_lost` ke `items.qty_lost` dan menandai `HILANG` bila tidak ada unit tersisa.
- Penghapusan mengurangi `quantity` dan menyesuaikan `qty_damaged`/`qty_lost` agar tidak terhitung dua kali.

## 5. Urutan migrasi

1. `0001_01_01_*` — users, cache, jobs.
2. `2026_10_19_094459_create_permission_tables` — role/permission.
3. `2026_10_19_095001_create_rooms_and_categories_tables` — majors, rooms, categories.
4. `2026_10_19_095002_create_items_tables` — items, item_movements.
5. `2026_10_19_095003_create_events_and_loans_tables` — events, loans, loan_items, loan_approvals.
6. `2026_10_19_095004_create_writeoffs_notifications_settings_reports_tables`.
7. `2026_10_19_095005_create_activity_log_table`.
8. `2026_10_19_095006_add_acquisition_approval_to_items`, `095007_add_code_to_categories`, `202205_add_indexes_to_items_table`.
9. `2026_10_20_000001_add_lifecycle_and_quantity_tracking` — counter kondisi, serah terima, pembatalan, index.

## 6. Seeder & akun demo

`php artisan db:seed` membuat:

| Username        | Password     | Role          | Cakupan         |
| --------------- | ------------ | ------------- | --------------- |
| `wakasek`       | `wakasek123` | SUPER_ADMIN   | Seluruh sekolah |
| `admin.sarpras` | `admin123`   | ADMIN_SARPRAS | Seluruh sekolah |
| `laboran.tkj`   | `laboran123` | LABORAN       | TKJ             |
| `laboran.rpl`   | `laboran123` | LABORAN       | RPL             |
| `guru.budi`     | `guru123`    | PEMINJAM      | RPL             |
| `guru.sari`     | `guru123`    | PEMINJAM      | TKJ             |

Data demo mencakup 3 jurusan, 6 ruangan, 5 kategori, 12 barang, 2 kegiatan, dan 4 peminjaman (selesai, aktif, pending, terlambat). **Ganti seluruh kredensial demo sebelum produksi.**

## 7. Backup

```bash
mysqldump -u root -p --single-transaction inventaris_sekolah > backup-$(date +%F).sql
tar -czf storage-$(date +%F).tar.gz storage/app/private storage/app/public
```

Pulihkan dengan `mysql ... < backup.sql` lalu ekstrak storage. Lihat `DEPLOYMENT.md`.
