# Revisi V8 — 5 November 2026

Dokumen ini mencatat implementasi revisi besar setelah V7. Keputusan bisnis pada
prompt revisi menjadi acuan perilaku; kode aktual menjadi acuan kondisi teknis.
Bagian yang belum diverifikasi ditulis apa adanya pada bagian 8.

## 1. Ringkasan

| Modul | Perubahan |
|---|---|
| Pengadaan & katalog | Katalog dapat dibuat tanpa merek; jumlah + satuan menjadi data inti; satu katalog mendukung beberapa kelompok spesifikasi. |
| Kelompok spesifikasi | Setiap kelompok memiliki merek, model/tipe, tahun, sumber dana, dan jumlah sendiri. |
| Alokasi | Setiap kelompok dialokasikan ke satu/lebih ruangan dengan jumlah, **kondisi**, dan **keterangan** per alokasi. |
| Penerimaan | Jumlah diterima dicatat per alokasi (id alokasi atau id ruangan untuk kompatibilitas). |
| Inventaris | Unit hanya terbit setelah `BARANG_DITERIMA`; barang rusak tetap terbit dan otomatis membuat laporan kerusakan. |
| RBAC | Tiga mode card: **Bisa Edit**, **Hanya Lihat**, **Sembunyikan**; audit perubahan role diperbaiki. |
| Akun pengguna | Filter kategori pada query backend (Siswa/Guru/Staf/Laboran/K3/Lainnya). |
| Autentikasi | Registrasi publik dihapus; kewajiban ganti password login pertama dihapus. |
| Operator | Cakupan laboratorium dimulai dari jurusan; validasi backend menolak laboratorium jurusan lain. |

## 2. Pengadaan, katalog, dan kelompok spesifikasi

- Form `items/create` memakai repeater kelompok spesifikasi dan alokasi bertingkat.
  Setiap kelompok menampilkan ringkasan jumlah dan total keseluruhan diperbarui
  langsung di klien; backend tetap memvalidasi ulang.
- Payload baru: `spec_groups[]` berisi `brand`, `model_type`, `acquisition_year`,
  `funding_source`, `quantity`, dan `allocations[]`
  (`room_id`, `quantity`, `condition`, `note`).
- Payload lama (`brand`, `model_type`, `allocations[]`, `room_id`) tetap diterima
  dan dinormalisasi menjadi satu kelompok. Unit test lama tidak diubah untuk
  memaksa format baru.
- Validasi backend:
  - Jumlah setiap kelompok harus sama dengan total alokasinya
    (`spec_groups.{i}.allocations`).
  - Total seluruh kelompok harus sama dengan `quantity` pengadaan (`quantity`).
  - Ruangan duplikat dalam **satu** kelompok ditolak; ruangan yang sama boleh
    dipakai kelompok berbeda (spesifikasi berbeda).
  - Ruangan tetap mengikuti aturan cakupan pengguna (`is_lab`, `user_room_scopes`).
- Katalog tetap satu jenis barang. Identitas spesifikasi tetap
  `(item_catalog_id, brand, model_type)`; tahun dan sumber dana disimpan pada
  alokasi sehingga dua kelompok dengan merek/model sama tetap dapat dibedakan
  laporan dan kondisi ruangannya.

## 3. Kondisi fisik dan keterangan

- Enum kondisi memakai `App\Enums\Condition` yang sudah ada
  (`BAIK`, `RUSAK_RINGAN`, `RUSAK_BERAT`); tidak ada enum duplikat.
- `acquisition_allocations.condition` dan `.note` menyimpan kondisi/keterangan
  per alokasi ruangan. Detail katalog menampilkan tabel kelompok spesifikasi,
  ruangan, jumlah, tahun, sumber dana, kondisi, dan keterangan.
- Kondisi fisik tidak dicampur dengan status transaksi (`UnitStatus`) atau
  tindakan inventaris (mutasi/peminjaman/kerusakan).

## 4. Penerimaan dan penerbitan inventaris

- State machine tidak berubah: unit hanya terbit saat pencatatan inventaris
  setelah status `BARANG_DITERIMA`.
- `AcquisitionService::receive()` menerima kunci id alokasi
  (`received[allocation_id][quantity|condition|note]`) dan tetap menerima kunci
  id ruangan lama (`received[room_id] => jumlah`).
- `recordInventory()` membuat unit per alokasi dengan kondisi, tahun, sumber dana,
  dan catatan alokasi. Untuk alokasi rusak:
  1. Unit tetap dibuat sebagai inventaris resmi.
  2. `DamageReportService::report()` dipanggil otomatis dengan kondisi awal
     `BAIK` sehingga `previous_condition` tetap dapat ditelusuri.
  3. Laporan dihubungkan ke unit dan `damage_reports.acquisition_allocation_id`.
  4. Pembuatan unit + laporan berada dalam transaksi database.
  5. Pemeriksaan idempoten mencegah laporan/unit ganda bila pencatatan diulang.
- Rusak berat tidak menghapus unit dan tidak mengurangi stok katalog.

## 5. Integrasi mutasi, peminjaman, dan laporan kerusakan

- Tidak ada sistem mutasi/peminjaman baru. Halaman pengadaan hanya menjadi titik
  masuk; aksi tetap memakai `MovementController`/`LoanService` yang sudah ada
  beserta validasi unit, status pinjam, cakupan ruangan, dan histori lokasi.
- Laporan kerusakan otomatis muncul pada daftar laporan yang sudah ada karena
  ditulis melalui service yang sama, mengikuti filter, pencarian, notifikasi,
  dan cakupan pengguna.
- Riwayat mutasi tetap menyimpan lokasi asal, tujuan, waktu, pelaksana, unit,
  dan referensi transaksi (`asset_unit_movements`).

## 6. Role dan permission

- Arsitektur tetap Spatie Laravel Permission + `PermissionCatalog`; tidak ada
  sistem RBAC kedua.
- Card fitur memakai tiga tombol dan badge: **Bisa Edit** (`FULL`),
  **Hanya Lihat** (`VIEW_ONLY`), **Sembunyikan** (`HIDDEN`), serta **Custom**
  untuk kombinasi tindakan parsial.
- Bisa Edit mengaktifkan seluruh tindakan fitur; checkbox dapat membatasi
  tindakan. Hanya Lihat hanya memberi `view`. Sembunyikan tidak memberi
  permission sehingga menu, route, dan endpoint ditolak backend.
- `RolePermissionManager::update()`/`reset()` sekarang mengembalikan
  `[sebelum, sesudah]`; `RoleController` memakai hasil itu untuk audit log
  (sebelumnya destructuring menghasilkan nilai yang salah).
- Baseline lima role, batasan keamanan (Admin tidak mengelola akun, Operator
  tidak memvalidasi pengadaan, dan seterusnya), cache permission, dan
  perlindungan Super Admin tidak diubah. Seeder tetap tidak menimpa konfigurasi
  custom.
- Data scope tetap terpisah dari mode: Bisa Edit tidak memperluas cakupan
  laboratorium Operator.

## 7. Akun, autentikasi, dan cakupan Operator

### 7.1 Filter kategori pengguna

- `users.index` menerima `category` dan `search` bersamaan.
- Nilai filter mengikuti data aktual: `SISWA`, `GURU`, `STAF` (dari
  `borrower_type`), `LABORAN`, `K3` (dari `operator_category`), dan `LAINNYA`
  (keduanya kosong). Filter diterapkan pada query, bukan di browser.
- Pagination mempertahankan query string, tersedia tombol reset, dan empty state
  informatif.

### 7.2 Registrasi publik dihapus

- Route `register` (GET/POST), `RegisteredUserController`, dan view
  `register.blade.php`/`register-new.blade.php` dihapus; tombol Register pada
  halaman login dihilangkan.
- Pembuatan akun oleh Super Admin tetap tersedia melalui halaman pengguna.
- Role dan kategori tidak dapat dikirim pengguna biasa karena tidak ada jalur
  registrasi; endpoint pengguna dilindungi middleware role/permission.

### 7.3 Kewajiban ganti password login pertama dihapus

- Middleware `EnsurePasswordChanged`, alias `password.changed`, dan redirect
  paksa dihapus dari route, dashboard, profil, dan tampilan pengguna.
- Migration V8 mengosongkan `users.must_change_password` yang sudah ada sehingga
  akun lama tidak tetap terkena redirect.
- Kolom `must_change_password`/`password_changed_at` tidak dihapus (menghindari
  perubahan destruktif). Penggantian password sukarela melalui profil dan reset
  password administrator tetap berfungsi; hashing tidak berubah.

### 7.4 Cakupan laboratorium Operator

- Form pengguna menampilkan dropdown jurusan dan checkbox laboratorium yang
  dimuat dari master ruangan; seluruh laboratorium jurusan aktif secara default
  bila tidak ada yang dipilih.
- Backend memvalidasi setiap `room_ids` berasal dari jurusan operator
  (`assertRoomScopeMatchesMajor`) dan hanya laboratorium aktif.
- `syncRoomScopes` menghapus cakupan lama lalu menyimpan ulang hanya ruangan
  laboratorium yang sesuai `major_id`, sehingga perubahan jurusan tidak
  menyisakan pilihan lama yang valid secara tampilan.
- Migration V8 membersihkan baris `user_room_scopes` lama yang jelas tidak sah:
  operator yang memiliki `major_id` tetapi cakupannya menunjuk laboratorium
  tanpa jurusan atau jurusan lain.
- Seluruh query operasional (katalog, unit, pengadaan, mutasi, peminjaman,
  kerusakan, laporan, dashboard, pencarian) tetap memakai `roomScopes()` dan
  scope `visibleTo`, sehingga ID ruangan/unit yang dimanipulasi ditolak.

## 8. Database

Migration baru: `2026_11_05_000001_add_procurement_condition_and_scope_metadata.php`.

- `acquisition_allocations`: `acquisition_year`, `funding_source`, `condition`
  (default `BAIK`), `note`.
- `damage_reports.acquisition_allocation_id` (FK nullable, null on delete).
- `users.must_change_password` diisi `false`.
- Pembersihan cakupan Operator lintas jurusan (lihat 7.4).
- Setiap perubahan kolom memeriksa `Schema::hasColumn`/`Schema::hasTable` agar
  aman pada database yang sudah memiliki kolom atau berjalan ulang.
- Tidak ada kolom/tabel lama yang dihapus dan tidak ada data inventaris,
  transaksi, atau riwayat yang dihapus.

## 9. Asumsi teknis

1. Identitas kelompok spesifikasi mengikuti struktur yang sudah ada, yaitu
   `(katalog, merek, model/tipe)`. Tahun dan sumber dana adalah atribut alokasi,
   bukan bagian identitas. Dua kelompok dengan merek+model identik akan berbagi
   satu baris `specifications`, tetapi alokasi, kondisi, tahun, dan sumber dana
   tetap terpisah.
2. Kondisi awal unit yang diterima rusak dibuat `BAIK` lalu diubah oleh laporan
   kerusakan agar `previous_condition` dan audit perubahan kondisi tetap terisi.
3. `received` memakai id alokasi pada form baru; id ruangan dipertahankan untuk
   kompatibilitas test/API lama.
4. Operator memiliki satu jurusan (`users.major_id`); kategori Laboran/K3 tetap
   satu role.
5. Filter `LAINNYA` berarti tidak memiliki `borrower_type` maupun
   `operator_category` (mis. Admin/Super Admin).

## 10. Pengujian

Perintah dan hasil aktual:

- `php artisan test --compact` (seluruh suite, SQLite in-memory):
  **402 passed, 0 failed, 2265 assertions**.
- `vendor/bin/pint --dirty --format agent`: **PASS** (2 file dirapikan).
- `php artisan view:cache` lalu `view:clear`: **PASS** (seluruh Blade dikompilasi).
- `php artisan route:list --except-vendor`: **PASS** setelah route registrasi dan
  middleware password dihapus.

Tes baru `tests/Feature/RevisionV8Test.php` mencakup:

1. Katalog dengan dua kelompok spesifikasi, kondisi/keterangan berbeda per
   alokasi, dan unit mewarisi tahun/sumber dana kelompok.
2. Penolakan alokasi kelompok yang tidak sama dengan jumlah kelompok.
3. Penerimaan barang rusak: tetap menjadi inventaris, laporan otomatis terhubung
   ke unit + alokasi, kondisi awal tercatat, dan percobaan ulang tidak membuat
   unit/laporan duplikat.
4. Filter kategori pengguna dijalankan pada query backend (termasuk kombinasi
   pencarian).
5. Operator dengan laboratorium jurusan lain ditolak; tanpa pilihan, seluruh
   laboratorium jurusan aktif.

Tes lama yang terdampak dijalankan ulang: `MultiLocationProcurementTest`,
`AcquisitionWorkflowTest`, `RegistrationTest`, `UserManagementUiTest`,
`UserAdministrationTest`, `RolePermissionTest`, `RevisionV5Test`, dan suite penuh.

### Perbaikan yang ditemukan saat pengujian

- `ItemController::store` masih merujuk variabel `$allocations`/`$groups` yang
  tidak ada di closure transaksi → diperbaiki.
- `distinct` pada wildcard bersarang berlaku lintas kelompok → diganti
  pemeriksaan duplikat per kelompok.
- `validated()` tidak mengembalikan parent array `received` saat aturan wildcard
  anak dipakai → input tervalidasi dibaca langsung dari request.
- `provisionAllocations()` selalu membuat spesifikasi "tanpa merek" cadangan →
  dibuat lazy sehingga tidak ada spesifikasi hantu pada katalog multi-kelompok.

## 11. Belum diverifikasi / batasan

- Pengujian ulang di MySQL, uji beban, dan uji konkurensi penerimaan paralel
  tidak dijalankan pada revisi ini (suite memakai SQLite in-memory).
- Playwright/E2E dan audit responsif tidak diulang; validasi UI baru dilakukan
  melalui kompilasi Blade dan test HTTP, bukan inspeksi browser.
- Pembersihan cakupan Operator lintas jurusan di migration tidak memiliki tes
  khusus (dijalankan pada database kosong saat suite); validasi penulisan baru
  diuji di `RevisionV8Test`.
- Cetak fisik label/QR dan verifikasi email tetap di luar cakupan revisi ini.
- Approvals/notifikasi tambahan di luar mekanisme yang sudah ada tidak dibuat.

## 12. File utama yang berubah

- `app/Http/Controllers/ItemController.php` — kelompok spesifikasi, validasi
  alokasi, penerimaan per alokasi, provisioning kondisi.
- `app/Services/AcquisitionService.php` — penerimaan per alokasi, unit + laporan
  kerusakan otomatis idempoten.
- `app/Services/DamageReportService.php` — parameter `acquisitionAllocationId`.
- `app/Models/AcquisitionAllocation.php`, `app/Models/DamageReport.php` — field
  dan relasi baru.
- `app/Http/Controllers/UserController.php` — filter kategori, cakupan jurusan.
- `app/Support/RolePermissionManager.php` — nilai balik audit permission.
- `resources/views/items/create.blade.php`, `receive.blade.php`, `show.blade.php`
  — form kelompok/alokasi/kondisi dan detail.
- `resources/views/users/index.blade.php`, `_form.blade.php` — filter & cakupan.
- `resources/views/roles/show.blade.php` — label tiga mode.
- `routes/auth.php`, `routes/web.php`, `bootstrap/app.php` — registrasi dan
  middleware password dihapus.
- `database/migrations/2026_11_05_000001_add_procurement_condition_and_scope_metadata.php`.
- `tests/Feature/RevisionV8Test.php` — tes regresi baru.
- `docs/ROLE_PERMISSION.md`, `docs/DATABASE.md`, `docs/PROJECT_STATUS.md` —
  diselaraskan dengan perilaku V8.
