# Revisi Besar V2 — Laporan Implementasi

Tanggal: **26 Oktober 2026**
Cakupan: autentikasi & registrasi, scan universal, master data, pengadaan multi-lokasi, label/QR, mutasi multi-unit, laporan & impor, rekap kegiatan, dokumen peminjaman, UI pengguna, dokumentasi.
Status keseluruhan: **TERIMPLEMENTASI & TERUJI** kecuali butir yang secara eksplisit dicatat sebagai keterbatasan di bagian 9.

Dokumen ini adalah catatan revisi. Sumber kebenaran skema tetap `DATABASE.md`, perilaku rinci ada di kode dan test, dan status keseluruhan ada di `PROJECT_STATUS.md`.

---

## 1. Audit awal

Audit dilakukan pada repository berjalan, bukan aplikasi baru:

- `composer.json`: Laravel 13, PHP 8.3, Livewire 4, Spatie Permission, Maatwebsite Excel, DomPDF.
- `package.json`: Vite 8, Tailwind CSS 4, Alpine (via Livewire), Chart.js, `html5-qrcode` 2.3.8.
- Skema: `item_catalogs` + `asset_units` + `asset_unit_codes` + `asset_unit_movements`; kuantitas katalog legacy (`items.quantity`) masih disinkronkan.
- Master lokasi: `buildings` (kode satu huruf), `rooms` (gedung, lantai, nomor ruangan), `user_room_scopes`.
- Autentikasi lama: login username/password, rate limit 5 percobaan, tanpa registrasi publik.
- Sumber kode: `InventoryCodeService` (unit, format `G1-02-12`) dan `IdGenerator` (ID internal katalog lama).
- Dokumen lama: label memakai `asset_code` + `register_no`; QR katalog mengarah ke detail katalog; scan langsung me-redirect ke katalog.
- Laporan: arsip PDF/Excel tanpa tombol hapus; belum ada impor.
- Kegiatan: tanpa status tersimpan; hanya pengelola inventaris yang dapat mencatat.
- Pengadaan: satu ruangan per pengajuan.
- Mutasi: per unit melalui halaman detail unit, belum ada pemilihan banyak unit.

Temuan yang diperbaiki pada revisi ini:

1. Registrasi publik belum ada; tidak ada validasi domain Gmail; belum ada pemisahan gelar.
2. Scan tidak menampilkan hasil di halaman scan dan tidak membedakan cakupan data.
3. `asset_code` masih menjadi kode kedua pada label, laporan, dan form.
4. Pengadaan belum mendukung alokasi beberapa lokasi.
5. Mutasi belum mendukung pilih banyak unit, pencarian, dan filter.
6. Surat peminjaman selalu memuat bagian kegiatan dan menampilkan waktu pada tanggal.
7. Belum ada pratinjau cetak surat.
8. Form edit pengguna masih berbeda dari form tambah (dropdown vs kartu).
9. Rekap kegiatan belum memiliki status `Belum/Sedang/Sudah Berjalan`.
10. Arsip laporan belum dapat dihapus dengan aman dan belum ada impor data.

---

## 2. Autentikasi dan registrasi mandiri

### 2.1 Formulir registrasi

Route: `GET/POST /register` (`routes/auth.php`, `RegisteredUserController`).

Field:

- Kategori pengguna: **Siswa / Guru / Staf** (radio, wajib).
- Nama lengkap (wajib).
- Gelar (opsional; hanya disimpan untuk kategori guru).
- Email Gmail (wajib).
- Username (wajib, unik, pola `A-Z a-z 0-9 . _ -`).
- Kelas (wajib untuk siswa; jurusan opsional).
- Password + konfirmasi (minimal 8 karakter, di-hash oleh cast `password`).

Perilaku:

- `degree` dan `class_name` dikosongkan bila tidak relevan dengan kategori.
- `users.name` tetap nama lengkap; gelar tidak digabung ke username/email.
- `User::displayName()` menggabungkan `nama, gelar`; `greetingName()` menjadi fallback username.
- Halaman register menjelaskan bahwa semua akun memperoleh role Peminjam.

### 2.2 Validasi Gmail

- Backend: `App\Rules\GmailAddress` memeriksa domain `gmail.com` dengan `strcasecmp` (tidak membedakan huruf besar/kecil), local-part tidak boleh kosong.
- Email dinormalisasi menjadi huruf kecil **sebelum** validasi unik, sehingga `Pengguna@GMAIL.com` dan `pengguna@gmail.com` dianggap sama.
- Frontend: atribut `pattern` pada input email + `type="email"` + `required`. Validasi backend tetap penentu akhir.
- Verifikasi email **belum diaktifkan**: kolom `email_verified_at` dan event `Registered` tersedia, tetapi `User` belum memakai `MustVerifyEmail` dan SMTP belum dikonfigurasi. Tidak ada status verifikasi palsu. Lihat bagian 9 dan `INSTALLATION.md`.

### 2.3 Keamanan role

- `RegisteredUserController` mengabaikan seluruh input `role`, `is_admin`, atau permission dari browser.
- Role `PEMINJAM` diisi langsung ke `users.role` dan `syncRoles()` dari backend.
- `SpatieRole::findOrCreate()` menjaga instalasi baru tanpa seeder tetap dapat mendaftar.
- Role istimewa hanya dapat diubah melalui `/pengguna` yang dibatasi middleware `role:SUPER_ADMIN`.
- Profil sendiri tidak dapat mengubah role (`ProfileUpdateRequest` hanya nama, gelar, email, kelas, jurusan).

### 2.4 Welcome screen

Dashboard menampilkan `Selamat datang, {displayName}!` dari pengguna terautentikasi (`auth()->user()`), bukan parameter URL. Fallback username berlaku bila nama kosong. Registrasi juga mengirim flash `Akun berhasil dibuat. Selamat datang, ...`.

### 2.5 Akun lama

Akun demo/seeder tetap dapat login: `email` nullable, `degree`/`class_name` nullable, dan registrasi tidak menghapus akun. Password akun lama tidak diubah oleh seeder.

---

## 3. Scan inventaris universal

Route:

- `GET /scan` → `LabelController@scan` (semua role login).
- `GET /scan/{internalId}` → `LabelController@scanResolve` (kompatibilitas URL lama, hasil tetap di halaman scan).

Input:

- Kamera memakai `html5-qrcode` (sudah dibundel di `resources/js/app.js`), tombol aktif/nonaktif.
- Input manual + tombol cari; formulir juga dapat dikirim dengan Enter.
- Jika kamera tidak tersedia/ditolak, pesan jelas dan input manual tetap berfungsi.

Hasil di halaman yang sama (`labels/scan.blade.php`):

- Nama, spesifikasi, merek/tipe, kategori, jurusan.
- Kode inventaris, gedung, lantai, ruangan, nomor unit.
- Kondisi dan status unit, penanda arsip bila `archived_at`/`DIHAPUS`.
- Untuk pengelola/approver: nomor register dan nilai perolehan. Untuk peminjam: field internal (`register_no`, nilai, catatan) tidak dikirim ke view.
- Status hasil: `found`, `restricted`, `missing`, `invalid`, `catalog` (stiker lama berisi ID internal katalog).
- Unit di luar `visibleTo($user)` mengembalikan pesan "di luar cakupan" tanpa membocorkan detail.

Keamanan/kinerja:

- QR hanya petunjuk; unit selalu dicari ulang di database berdasarkan ID unit atau kode.
- Format kode divalidasi `InventoryCodeService::PATTERN`; pencarian memakai kode unik, bukan LIKE bebas.
- Scan tidak menulis data inventaris apa pun.
- Kode lama (`g1-01-01`) dan kode unit baru (`G1-01-01`) dinormalisasi; unit diprioritaskan.

---

## 4. Master data wajib dan gedung A–G

### 4.1 Validasi wajib

- Form master gedung: nama + kode satu huruf wajib; kode dinormalisasi kapital dan unik.
- Form ruangan: gedung (dropdown master), lantai 1–9, nomor 1–99, nama opsional (default `Ruang NN`); kombinasi `gedung+lantai+nomor` unik.
- Form jurusan: nama wajib; kode opsional (contoh `TP`) dan unik bila diisi.
- Katalog: nama, kategori, tahun, sumber dana, jumlah, satuan wajib; jumlah minimal 1.
- Unit: lokasi wajib dan identitas ruangan lengkap sebelum kode diterbitkan.
- Pengguna: nama, username, role, password (saat tambah) wajib; email opsional untuk akun non-registrasi.
- Registrasi: nama, email, username, kategori, password, konfirmasi wajib; kelas wajib untuk siswa.
- Indikator `*` dan pesan error dekat field ditampilkan pada form yang direvisi.
- Validasi backend selalu dijalankan; atribut HTML tidak dijadikan satu-satunya penjaga.

### 4.2 Gedung

`MasterDataSeeder` menambahkan **Gedung A–G** (`code` = `A`…`G`) secara idempoten dengan `updateOrCreate`. Gedung **H dan I** dipertahankan karena seluruh ruangan, unit, dan transaksi demo masih terelasi ke sana. Tidak ada tabel master gedung kedua. Penghapusan gedung/ruangan yang masih dipakai ditolak (422). Penghapusan gedung yang masih memiliki ruangan juga ditolak.

Generator kode mengambil huruf dari `rooms.building_id → buildings.code` (`Room::buildingCode()`), bukan teks bebas. `rooms.building_letter` hanya salinan sinkron.

---

## 5. Penghapusan kode aset terpisah

- Kolom `item_catalogs.asset_code` dan unique index `(asset_code, register_no)` dihapus melalui migration `2026_10_26_202332_drop_asset_code_from_item_catalogs.php`.
- Seluruh referensi aplikasi (`app`, `resources/views`, `database`, test) tidak lagi memakai `asset_code`.
- Identitas tunggal barang = `asset_units.inventory_code` (contoh `G1-01-01`), juga tercatat di `asset_unit_codes`.
- `register_no` dipertahankan sebagai nomor register administratif opsional; tidak ditampilkan sebagai kode kedua pada label/QR.
- Migration memeriksa keberadaan kolom dan index, aman untuk database baru maupun lama; `down()` hanya membuat kembali kolom nullable (data lama tidak dapat dipulihkan otomatis).
- Kode unit final selalu dibuat backend; form menampilkan pratinjau/readonly dan pengguna tidak dapat mengetik kode final.

---

## 6. Pengadaan multi-lokasi

### 6.1 Alur

1. Buka **Pengadaan** (`GET /items/create`, form `items/create.blade.php`).
2. Bagian A: katalog (nama, kategori, spesifikasi, tahun, sumber dana, jumlah, satuan, foto, nilai tinggi).
3. Bagian B: baris alokasi berulang — gedung, lantai, ruangan, jumlah, pratinjau kode.
4. Sisa unit dihitung langsung; simpan dinonaktifkan sampai sisa = 0.
5. Backend memvalidasi ulang: total alokasi **harus sama** dengan jumlah pengadaan, minimal 1 per baris, ruangan unik, dan kapasitas nomor unit (maks. 99 seumur hidup).
6. Pengajuan oleh selain Wakasek berstatus `PENDING`; unit baru dibuat setelah disetujui. Pengajuan oleh Wakasek langsung `APPROVED` dan unit dibuat saat itu.
7. Unit dibuat per baris alokasi dengan kode dari `InventoryCodeService`; counter katalog disinkronkan dari jumlah unit nyata.

### 6.2 Rumus dan penjagaan

- `Q` = jumlah pengadaan, `q_i` = jumlah per lokasi, `A = Σq_i`, `S = Q − A`.
- Backend menolak `A ≠ Q` (termasuk `A > Q` dan `A < Q`). Karena alur saat ini mewajibkan seluruh unit ditempatkan, tidak ada status "belum lengkap" yang tersimpan.
- Alokasi disimpan di `acquisition_allocations` dengan unique `(item_catalog_id, room_id)`.
- Proses katalog + alokasi + unit dibungkus `DB::transaction`; penerbitan kode memakai lock ruangan + retry pada unique index.
- `provisionUnits()` tidak membuat unit lagi bila unit sudah ada, sehingga request ganda tidak menggandakan unit.

Contoh yang diuji:

- 40 = 20 + 15 + 5 → valid.
- 40 dengan 20 + 15 + 6 → ditolak (total 41).
- 40 dengan 20 saja → ditolak karena sisa 20.
- 40 dengan 20 + 15 → sisa 5, simpan belum boleh.
- Dua unit di ruangan yang sama mendapat kode berbeda.

---

## 7. Label dan QR

### 7.1 Label

- Nama barang menjadi header (huruf kapital), diikuti kode inventaris unit dan QR.
- Setiap unit fisik = satu label; cetak massal memakai unit terpilih (bukan baris katalog).
- Lokasi pada label mengikuti lokasi aktif unit saat dicetak.
- Label tidak menampilkan `asset_code` lagi.
- `last_label_printed_at` diperbarui dan aktivitas `label` dicatat.

### 7.2 Payload QR

`LabelService::unitQrPayload()` menghasilkan URL `route('scan')` dengan parameter:

| Param | Isi |
| ----- | --- |
| `u` | ID stabil `asset_units.id` |
| `c` | kode inventaris unit |
| `r` | release skema QR (`LabelService::QR_RELEASE = 1`) |
| `b` | nama gedung |
| `f` | lantai |
| `rn` | nomor ruangan |
| `un` | nomor unit |

Aturan:

- `r` adalah versi skema, bukan data bisnis; dinaikkan bila struktur berubah.
- Nama barang tidak dimasukkan sebagai sumber kebenaran; aplikasi membacanya dari katalog.
- QR tidak memuat password, token sesi, data peminjam, atau nilai perolehan.
- Saat dipindai, server mengabaikan lokasi di QR dan memakai lokasi aktif database.
- QR katalog lama (URL `items.show`) dan stiker ID internal masih diterima untuk kompatibilitas; hasilnya diarahkan ke halaman yang sesuai.
- Perubahan lokasi tercermin pada scan berikutnya; riwayat tetap di `asset_unit_movements`.

---

## 8. Mutasi barang

Route:

- `GET /mutasi` → `MovementController@index`.
- `POST /mutasi` → `MovementController@store`.
- `POST /units/{unit}/move` tetap tersedia untuk mutasi satu unit dari halaman detail.

Fitur:

- Pencarian kode (prefix), filter katalog, gedung, lantai, ruangan, kondisi, status.
- Daftar unit dengan checkbox; ringkasan unit terpilih berisi nama, kode, lokasi asal, tujuan.
- `unit_ids` divalidasi `array`, `min:1`, `distinct`, dan `exists`; unit ganda ditolak.
- Hanya unit dalam `visibleTo($user)` yang dapat dipilih; laboran tidak dapat memilih unit/ruangan di luar cakupan.
- Tujuan harus ruangan valid dan berbeda dari lokasi asal.
- Seluruh mutasi dibungkus satu transaksi; satu kegagalan membatalkan semuanya.
- Setiap unit: kode lama dipensiunkan, kode baru diterbitkan, `asset_unit_movements` dicatat, label ditandai perlu dicetak ulang.
- Unit `DIPINJAM` ditolak dengan pesan harus dikembalikan lebih dulu; unit `DIHAPUS` ditolak.
- Transaksi peminjaman lama tidak diubah.

---

## 9. Laporan: impor, arsip, dan penghapusan

### 9.1 Impor

Route pengelola (`role:SUPER_ADMIN|KETUA_LABORAN`): `GET /laporan/impor`, `POST /laporan/impor/pratinjau`, `POST /laporan/impor`.

- Format: **CSV/TXT/XLSX**, maksimal 2 MB, baris header.
- Kolom yang dibaca: `kode_inventaris` (wajib), `nama`, `gedung`, `lantai`, `ruangan`, `kondisi`, `status`, `catatan` (nama kolom dinormalisasi).
- Mode: `create` (hanya kode baru; kode yang sudah ada ditolak dan diarahkan ke mode perbarui) dan `update` (memperbarui unit yang cocok berdasarkan kode inventaris).
- Pratinjau menampilkan total, valid, invalid, duplikat, dan alasan per baris; impor diblokir bila masih ada baris bermasalah.
- Konfirmasi memakai token sesi dari pratinjau; impor dijalankan atomik dan tidak menghapus data lain.
- Hasil impor dicatat di `activity_log` (aktor, waktu, nama file, jumlah).
- **Bukan pengganti backup database**: hanya memulihkan baris unit yang memiliki kode stabil. Relasi transaksi, pengguna, kegiatan, dan arsip PDF tidak dipulihkan dari file ini.

### 9.2 Penghapusan arsip

- Tombol **Hapus** pada tabel arsip dengan konfirmasi berisi periode, cakupan, dan peringatan bahwa data inventaris tidak ikut terhapus.
- `reports` memakai soft delete; file PDF/Excel dihapus dari disk, baris tetap untuk audit.
- Super Admin dapat menghapus arsip mana pun; pengguna lain hanya arsip yang ia buat (dan route hanya untuk pengelola).
- Penghapusan dicatat di `activity_log`.
- Tidak ada persetujuan admin kedua untuk Super Admin/Wakasek.
- Peminjam tidak memiliki akses ke halaman/route ini.

---

## 10. Rekap kegiatan

- `EventStatus`: `BELUM_BERJALAN`, `SEDANG_BERJALAN`, `SUDAH_BERJALAN`, tersimpan di `events.status` (default `BELUM_BERJALAN`).
- Guru dan staf (`canRecordActivities()`) serta pengelola inventaris dapat membuat kegiatan: nama, penanggung jawab, deskripsi, tanggal mulai/selesai, status awal.
- Guru/staf hanya dapat mengubah kegiatan miliknya (`Event::isManageableBy()`); pengelola dapat memantau seluruh kegiatan.
- Perubahan status dicatat (`status_sebelum`, `status_sesudah`, `updated_by`).
- Status tidak mengubah status persetujuan peminjaman; relasi `loans.event_id` tetap opsional.
- Daftar kegiatan mendukung filter status dan menampilkan ringkasan peminjaman terkait.

---

## 11. Penghapusan oleh Wakasek/Super Admin

- Penghapusan katalog, arsip laporan, dan data dalam kewenangan Super Admin tidak memerlukan persetujuan admin lain.
- Route tetap dibatasi `role:SUPER_ADMIN`; backend memeriksa relasi sebelum menghapus (katalog dengan unit/transaksi diarsipkan, bukan dihapus).
- Setiap penghapusan dicatat di `activity_log` dengan aktor dan waktu.
- Role lain tidak memperoleh hak hapus baru.
- Perbedaan objek tetap jelas:
  - **Katalog**: hapus permanen hanya tanpa relasi; jika ada relasi → arsip.
  - **Arsip laporan**: soft delete + hapus file, data sumber utuh.
  - **Unit inventaris**: dinonaktifkan/diarsipkan melalui penghapusan (bukan hard delete).
  - **Master data**: ditolak bila masih dipakai.

---

## 12. Dokumen peminjaman dan pratinjau cetak

- Template surat dipisah menjadi `pdf/letter_body.blade.php` (isi) dan `pdf/bast.blade.php` (kerangka).
- Bagian kegiatan hanya dirender bila peminjaman benar-benar memiliki `event_id`; surat biasa tidak memuat nama/pic/tanggal kegiatan.
- Tanggal memakai `translatedFormat('d F Y')` tanpa jam; waktu hanya muncul pada field yang memang membutuhkan (mis. waktu serah terima).
- Identitas peminjam mengikuti kategori:
  - Siswa: nama, kelas, jurusan.
  - Guru: nama + gelar, jurusan.
  - Staf: nama + gelar bila ada, unit kerja/jurusan bila ada.
- NIM/NIP tidak dicetak.
- Rincian barang menampilkan nama, spesifikasi, jumlah, dan **kode inventaris setiap unit** yang ditugaskan; bukan hanya agregat katalog.
- Pratinjau: `GET /loans/{loan}/surat` (name `loans.letter.preview`) menampilkan HTML ukuran A4 dengan CSS `@media print`, tombol cetak memanggil `window.print()`, dan tombol unduh PDF tetap tersedia.
- Pratinjau/unduh memakai otorisasi yang sama dengan detail peminjaman; pengguna lain tidak dapat membuka surat dengan mengganti ID.
- Timezone mengikuti `APP_TIMEZONE` (`Asia/Jakarta`).

---

## 13. UI pengelolaan pengguna

- `resources/views/users/index.blade.php` menampilkan daftar pengguna dan panel tambah/edit berbentuk kartu.
- `resources/views/users/_form.blade.php` dipakai untuk tambah dan edit, sehingga struktur field konsisten.
- Field dikelompokkan: identitas (nama, gelar), akun (email, username, password), kategori/role, kelas/jurusan, cakupan laboratorium.
- Field kondisional: gelar untuk guru/staf, kelas untuk siswa, cakupan ruangan hanya untuk Laboran.
- Validasi ditampilkan dekat field; tombol simpan/batal jelas.
- Perubahan role tetap hanya untuk Super Admin dan divalidasi backend; pengguna tidak dapat menaikkan role sendiri lewat profil.

---

## 14. Skema dan migration

| Migration | Perubahan |
| --- | --- |
| `2026_10_26_202329_add_profile_fields_to_users_table.php` | `users.email` (nullable unique bila belum ada), `email_verified_at`, `degree`, `class_name`. |
| `2026_10_26_202330_create_acquisition_allocations_table.php` | Tabel `acquisition_allocations` + unique katalog/ruangan. |
| `2026_10_26_202331_add_status_to_events_table.php` | `events.status`, `events.updated_by`, default status kegiatan lama. |
| `2026_10_26_202332_drop_asset_code_from_item_catalogs.php` | Hapus `item_catalogs.asset_code` + unique index terkait. |
| `2026_10_26_202333_add_soft_deletes_to_reports_table.php` | `reports.deleted_at` untuk penghapusan arsip. |

Tidak ada migration destruktif otomatis: penghapusan kolom hanya `asset_code` setelah semua referensi dibersihkan; migration memeriksa keberadaan kolom/index. Tidak ada reset database dalam proses ini.

---

## 15. Route utama yang berubah/ditambah

| Method | URI | Name | Akses |
| --- | --- | --- | --- |
| GET | `/register` | `register` | guest |
| POST | `/register` | `register` | guest |
| GET | `/scan` | `scan` | semua login |
| GET | `/scan/{internalId}` | `scan.resolve` | semua login |
| GET | `/mutasi` | `movements.index` | `SUPER_ADMIN\|KETUA_LABORAN` |
| POST | `/mutasi` | `movements.store` | `SUPER_ADMIN\|KETUA_LABORAN` |
| GET | `/kegiatan` | `events.index` | semua login |
| POST | `/kegiatan` | `events.store` | guru/staf/pengelola |
| PATCH | `/kegiatan/{event}` | `events.update` | pemilik/pengelola |
| GET | `/laporan/impor` | `reports.import.form` | `SUPER_ADMIN\|KETUA_LABORAN` |
| POST | `/laporan/impor/pratinjau` | `reports.import.preview` | `SUPER_ADMIN\|KETUA_LABORAN` |
| POST | `/laporan/impor` | `reports.import.confirm` | `SUPER_ADMIN\|KETUA_LABORAN` |
| DELETE | `/laporan/{report}` | `reports.destroy` | Super Admin (semua arsip) / pembuat arsip |
| GET | `/loans/{loan}/surat` | `loans.letter.preview` | pemilik/berwenang |
| GET | `/users?edit={id}` | `users.index` | Super Admin |

---

## 16. Pengujian

Hasil terakhir (27 Oktober 2026):

```text
Tests:    249 passed (1190 assertions)
Duration: ~172s
Database: SQLite in-memory
```

Test baru/berubah pada revisi ini:

- `RegistrationTest` — registrasi lengkap, Gmail ditolak, domain kapital diterima & dinormalisasi, duplikat email/username, password hash, role selalu Peminjam, eskalasi `role` diabaikan, kelas wajib untuk siswa, sapaan nama+gelar/username, profil tidak dapat mengubah role, akun lama non-Gmail dapat mempertahankan email lama, perubahan email ke domain non-Gmail ditolak.
- `UniversalScanTest` — akses semua role, hasil di halaman scan, kode tidak valid/tidak ditemukan, pembatasan data internal, scan tidak mengubah data, payload QR bertanda rilis + metadata lokasi, dan stiker ID internal katalog lama diarahkan tanpa redirect.
- `MultiLocationProcurementTest` — 40 = 20+15+5, total lebih/kurang ditolak, kode unit otomatis & unik, alokasi tersimpan.
- `ReportImportAndArchiveTest` — pratinjau, format/duplikat ditolak, impor atomik, hapus arsip tidak menghapus unit, audit log, mode buat menolak kode yang sudah ada tanpa mengubah unit.
- `EventActivityTest` — guru/staf dapat mencatat, siswa tidak, status tersimpan & berubah, kepemilikan, status tidak mengubah peminjaman.
- `LoanLetterTest` — surat biasa tanpa field kegiatan, surat kegiatan memuat kegiatan, tanggal tanpa jam, identitas per kategori, kode unit, otorisasi.
- `UserManagementUiTest` — panel kartu tambah/edit, field kondisional, role hanya Super Admin.
- `BuildingMasterDataTest`, `LabelAndQrTest`, `MovementAndWriteOffTest`, `InventoryCatalogTest` disesuaikan dengan `asset_code` yang dihapus dan perilaku baru.

Regresi: seluruh suite (249 tes) lulus. `vendor/bin/pint --dirty` dijalankan. Build frontend tidak diubah pada revisi ini (tidak ada perubahan CSS/JS baru selain atribut HTML).

---

## 17. Dokumentasi yang diperbarui

- `docs/REVISION_V2.md` (dokumen ini).
- `docs/PROJECT_STATUS.md` — status modul, jumlah tes, data master.
- `docs/DATABASE.md` — tabel/kolom baru, `asset_code` dihapus, seeder A–I.
- `docs/TESTING.md` — hasil 249 tes dan daftar test baru.
- `docs/KNOWN_ISSUES.md` — butir yang selesai dan sisa keterbatasan.
- `docs/IMPLEMENTATION_PLAN.md` — penunjuk status revisi V2.

---

## 18. Keterbatasan dan sisa pekerjaan

1. **Verifikasi email belum aktif.** Dibutuhkan konfigurasi SMTP dan `MustVerifyEmail`; saat ini hanya format Gmail yang divalidasi. Jangan menganggap email terbukti dimiliki pengguna.
2. **Impor laporan bukan restore penuh.** Hanya unit dengan kode inventaris valid yang dapat dipulihkan; relasi, transaksi, pengguna, dan arsip PDF tidak. Untuk migrasi server tetap gunakan backup database + storage.
3. **Impor belum memiliki riwayat khusus** selain `activity_log`; belum ada tabel `report_imports`.
4. **Kegiatan belum menautkan peminjaman dari form kegiatan.** Relasi `loans.event_id` sudah ada dan ditampilkan, tetapi pemilihan kegiatan saat mengajukan pinjaman mengikuti alur yang ada.
5. **Mutasi unit yang sedang dipinjam ditolak**, bukan dipindahkan; kebijakan ini konsisten dengan status unit.
6. **Kapasitas 99 nomor per ruangan** tetap kumulatif seumur hidup.
7. **Data demo memakai gedung H/I.** Gedung A–G resmi sudah tersedia, tetapi pemetaan ulang data demo ke A–G belum dilakukan karena akan mengubah kode unit yang sudah tercetak.
8. **Uji konkurensi MySQL nyata** belum dijalankan; pengaman unique index + lock sudah ada.
9. **Deployment produksi** belum dilakukan pada lingkungan ini.

---

## 19. Cara menjalankan

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm install
npm run build
php artisan serve
```

Pengujian:

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
php artisan inventory:verify-codes
```

Untuk impor laporan: login Super Admin → **Laporan** → **Impor** → unggah CSV/XLSX → periksa pratinjau → konfirmasi. Untuk rollback, gunakan backup database; impor tidak menyediakan undo otomatis.

> Jangan menjalankan `migrate:fresh`, `db:wipe`, atau menghapus data produksi untuk menerapkan revisi ini.
