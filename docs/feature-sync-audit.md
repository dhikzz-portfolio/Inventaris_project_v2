# Matriks Audit Sinkronisasi Fitur (Feature Sync Audit)

Tanggal audit: **22 Oktober 2026**
Acuan: `PROJECT_BRIEF.md` v2.1 (PRD utama), `DECISIONS.md`, `revisi.md`, `KNOWN_ISSUES.md`, `PROJECT_STATUS.md`.
Metode: pemeriksaan kode sumber, rute, view, service, dan test suite (130 tes, SQLite in-memory); klaim "IMPLEMENTED & VERIFIED" di `PROJECT_STATUS.md` diverifikasi ulang pada area yang terdampak revisi.

Status: **Sebagian selesai** — semua ketidaksesuaian kritis yang dapat diselesaikan berdasarkan dokumentasi terbaru telah diimplementasikan dan diuji; sisa gap tinggal yang tercatat sebagai keputusan transisi di `KNOWN_ISSUES.md` (butir yang masih terbuka ditandai **TERHAMBAT / KEPUTUSAN TRANSISI**).

---

## 1. Matriks audit

| Fitur | Aturan terbaru (dokumen) | Implementasi saat ini | Ketidaksesuaian | Dampak | Tindakan | Prioritas | Pengujian |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Katalog vs unit fisik (5.1–5.5) | Katalog = jenis; unit = barang fisik; jumlah dihitung dari unit | `item_catalogs` + `asset_units`; jumlah dihitung via `withCount`/`syncCatalogCounters` | Tidak ada | — | **Pertahankan** | — | `InventoryCatalogTest` |
| Kode inventaris `G1-02-12` (6.1–6.6) | Pola, validasi, ledger, batas 99, tanpa daur ulang nomor | `InventoryCodeService` + `asset_unit_codes`, lock ruangan, retry 3× | Tidak ada | — | **Pertahankan** | — | `CriticalBusinessRulesTest` |
| Mutasi unit & kode pensiun (7.1–7.4) | Kode diterbitkan ulang; riwayat dipertahankan; `last_label_printed_at` dikosongkan | `InventoryCodeService::move()` atomik + `asset_unit_movements` | Tidak ada | — | **Pertahankan** | — | `MovementAndWriteOffTest` |
| **Cetak label massal (keputusan 8)** | Label melekat pada **unit fisik**; massal = unit terpilih; QR menunjuk `units.show` | Sebelumnya: halaman `/label` memilih **baris katalog** dan mencetak 1 label per katalog (`printMassal(item_ids)`), tanpa pembatasan cakupan akses | **KRITIS** — melanggar keputusan 8; Ketua Laboran/Laboran dapat memilih katalog di luar cakupan; satu katalog 23 kursi hanya menghasilkan 1 stiker | UI, PDF label, akses, audit log | **REVISI → selesai**: `LabelService::printUnits()` (unit IDs, scope `visibleTo`, kecualikan `DIHAPUS`), `LabelController` baru (filter katalog/ruangan/kategori/kondisi/status + pencarian kode + pagination 25 + "pilih semua sesuai filter" lintas halaman), template `pdf/unit_massal.blade.php` (grid 2 kolom A4, QR per unit), tombol "Cetak Label Unit" dari detail katalog; template legacy `pdf/label_massal.blade.php` dihapus | Kritis | `LabelAndQrTest` (5 tes baru/diubah) |
| Cetak label satuan / re-print (revisi 4.1–4.3) | Layout 256×116pt, `@page margin 0`, footer normal flow, kode 2 baris | Sudah sesuai `revisi.md`; dipertahankan untuk label legacy & cetak ulang per unit via `units.label` | Tidak ada | — | **Pertahankan** | — | `LabelAndQrTest` |
| Dokumen SP/BAST (revisi 4.4–4.6, 4.9) | `SP/{id}/{m/Y}` non-event, `BAST/{id}/{m/Y}` event; loan batal/ditolak dikecualikan; proteksi page-break | `BastService::documentNumber/documentTitle/loansForEvent` sudah diterapkan; nama file unduhan sesuai jenis dokumen | Tidak ada | — | **Pertahankan** | — | `EventAndBastTest` |
| Hitungan terlambat laporan (revisi 4.8) | Hanya `APPROVED`/`DIPINJAM` aktif lewat jatuh tempo + `SELESAI` telat kembali | `ReportService` memakai `match` status | Tidak ada | — | **Pertahankan** | — | `ReportTest` |
| **Ringkasan laporan (bagian E, PRD 5.5)** | Bedakan jumlah katalog & jumlah unit fisik; rincian kondisi eksklusif per unit | Sebelumnya `baik/rusak/hilang` dihitung dari **status baris katalog** (model batch) | **TINGGI** — angka "Kondisi Baik/Rusak/Hilang" mewakili katalog, bukan unit; bertentangan dengan PRD 5.5 | Laporan PDF, Excel | **REVISI → selesai**: `ReportService::data()` menghitung unit (`AssetUnit::visibleTo` + scope), bucket eksklusif sesuai urutan prioritas PRD 5.5; label PDF menjadi "Unit Baik/Rusak/Hilang" | Tinggi | `ReportTest::test_ringkasan_laporan_menghitung_unit_fisik_bukan_baris_katalog` |
| **Sheet Excel laporan (keputusan 17)** | Sheet "Barang" diganti **"Unit"**: kode inventaris, katalog, lokasi, kondisi, status | Sheet 1 masih "Barang" berisi baris katalog + kolom legacy | **SEDANG** — ekspor tidak setara model data terbaru | Ekspor Excel | **REVISI → selesai**: sheet "Unit" satu baris per unit (kode, katalog, kategori, ruangan, gedung/lantai/no, kondisi, status, tahun, dana, register) | Sedang | `ReportTest` + ekspor pada `DashboardAndReportingTest` |
| **Penghapusan aset (bagian C, KNOWN_ISSUES 2)** | Pengurangan stok harus benar-benar memperbarui unit fisik; status unit, jumlah katalog, riwayat tetap konsisten | Persetujuan hanya mengurangi kolom legacy `quantity/qty_damaged/qty_lost`; **unit fisik tidak diarsipkan** → unit tetap tampil di katalog, bisa dicetak/dipinjam | **KRITIS** — angka katalog (legacy) dan jumlah unit (sumber kebenaran) divergen | Database, katalog, laporan, label | **REVISI → selesai**: `WriteOffController::approve()` mengarsipkan unit fisik (prioritas HILANG → RUSAK_BERAT → RUSAK_RINGAN → BAIK, nomor tertinggi dulu) lewat `AssetUnitService::archive()`, lalu `syncCatalogCounters()` | Kritis | `MovementAndWriteOffTest::test_persetujuan_penghapusan_mengarsipkan_unit_fisik_dan_sinkron_dengan_katalog` |
| **Cakupan mutasi & penghapusan (bagian 3.1)** | Ketua Laboran hanya lab; data di luar cakupan → 404 | Daftar & proses mutasi/penghapusan legacy memakai `Item::approved()` tanpa `visibleTo` | **TINGGI** — ADMIN_SARPRAS dapat memutasi/mengajukan penghapusan katalog non-lab lewat URL/form | Akses data | **REVISI → selesai**: `visibleTo` + larangan tujuan non-lab untuk non-Super Admin di `MovementController` & `WriteOffController` | Tinggi | `MovementAndWriteOffTest::test_ketua_laboran_tidak_dapat_mengajukan_penghapusan_barang_di_luar_laboratorium` |
| **Pilihan filter laporan (bagian 8.4)** | Pilihan filter diturunkan dari data yang terlihat pengguna | Dropdown ruangan laporan menampilkan semua ruangan | Rendah — membingungkan; data tetap terbatas cakupan | UI laporan | **REVISI → selesai**: dropdown ruangan lab-only untuk non-Super Admin | Rendah | manual/eksisting |
| Dashboard per role | Angka memakai scope backend yang sama | `AssetUnit::visibleTo` / `Item::visibleTo` / `Loan::visibleTo` | Tidak ada | — | **Pertahankan** | — | `DashboardAndReportingTest` |
| Pencarian kode inventaris (8.3) | Exact/prefix, normalisasi, pesan format, dibatasi katalog & cakupan | `CatalogUnits` Livewire sesuai; halaman label memakai normalisasi yang sama | Tidak ada | — | **Pertahankan** | — | `InventoryCatalogTest` |
| Peminjaman multi-item | Masih level katalog/kuantitas; unit ditugaskan saat serah terima (**belum**) | Sesuai status `KNOWN_ISSUES` 1 & 8 | Diketahui, di luar acceptance criteria inti 2.1 | Riwayat unit | **TERHAMBAT — keputusan transisi** (butir pekerjaan tersisa) | Tinggi | — |
| Penghapusan per unit (`asset_unit_id` + `batch_no`) | `KNOWN_ISSUES` 2 rencana lanjutan | Persetujuan kini mengarsipkan unit, tetapi pengajuan masih memilih katalog + jumlah | Diketahui | BA unit | **TERHAMBAT — pekerjaan tersisa** (alur pemilihan unit perlu desain produk) | Sedang | — |
| Mutasi/pengembalian legacy per baris katalog | `KNOWN_ISSUES` 9 | Halaman `/mutasi` legacy masih memutasi baris katalog | Diketahui — transisi `item_movements` → `asset_unit_movements` | Riwayat unit | **TERHAMBAT — keputusan transisi** | Sedang | — |
| Rute legacy `/items` | Alih permanen setelah modul lain selesai | Masih aktif, sudah dibatasi cakupan | Diketahui (`KNOWN_ISSUES` 4) | UX | **Pertahankan sementara** | Rendah | `InventoryCatalogTest` |
| File internal privat (keputusan 13) | `/files` dengan pemeriksaan peran/cakupan | `SecureFileController` memverifikasi prefix + pemilik/peminjaman | Tidak ada | — | **Pertahankan** | — | `ReportTest`, `SecurityAccessTest` |

---

## 2. Perubahan yang diimplementasikan pada audit ini

### 2.1 Cetak label per unit fisik (kritis)

- Sebelum: `/label` menampilkan daftar **katalog** dengan `item_ids[]`; `printMassal()` mencetak 1 stiker per baris katalog memakai kolom legacy `location_code`/`asset_code`; tidak ada filter cakupan akses sehingga Laboran dapat mencetak katalog di luar lingkupnya.
- Sesudah:
  - `LabelService::printUnits(array $unitIds)` — mengambil unit via `visibleTo(auth()->user())`, katalog disetujui, status ≠ `DIHAPUS`; setiap stiker memuat kode inventaris unit + QR `route('units.show', $unit)`; `last_label_printed_at` dicatat per unit dan aktivitas dicatat di audit log.
  - `LabelController::index()` — daftar unit dalam cakupan; filter katalog, ruangan, kategori, kondisi, status; pencarian kode (normalisasi + prefix, sama dengan halaman detail katalog); pagination 25 dengan query string dipertahankan.
  - `LabelController::print()` — menerima `unit_ids[]` **atau** `select_all=1` (dengan filter aktif yang dikirim sebagai input tersembunyi sehingga pemilihan **tidak terbatas pada halaman yang tampil**).
  - `resources/views/pdf/unit_massal.blade.php` — grid 2 kolom A4 (`@page margin 8mm`), ukuran mengikuti pola Dompdf yang sudah diverifikasi di `revisi.md` (tanpa `* { margin:0 }`, tanpa `position:absolute` di dalam sel, `page-break-inside: avoid` per baris).
  - Tombol **Cetak Label Unit** pada `catalogs.show` → `/label?catalog={id}` untuk mencetak seluruh unit satu katalog.
  - `pdf/label_massal.blade.php` (per baris katalog) dihapus; label satuan legacy `pdf/label_single.blade.php` dipertahankan untuk halaman legacy `/items/{id}`.

### 2.2 Penghapusan mengarsipkan unit fisik (kritis)

- `WriteOffController::approve()` kini mengarsipkan unit fisik terpilih (HILANG → RUSAK_BERAT → RUSAK_RINGAN → BAIK; nomor unit tertinggi lebih dulu), menyetel `archived_at`, lalu menghitung ulang counter katalog dari unit (`AssetUnitService::syncCatalogCounters`). Jika seluruh unit habis, katalog berstatus `DIHAPUS`. Angka katalog, dashboard, laporan, dan daftar unit kini tetap berasal dari satu sumber: tabel `asset_units`.

### 2.3 Laporan & ekspor selaras model unit

- `ReportService::data()` menambah koleksi `units` (scope akses + filter scope laporan) dan menghitung ringkasan kondisi per unit dengan urutan prioritas eksklusif PRD 5.5.
- `MonthlyReportExport` sheet 1 menjadi **"Unit"** (satu baris per unit, kolom kode inventaris/katalog/lokasi/kondisi/status).
- Label PDF ringkasan membedakan "Katalog Barang" vs "Total Unit" dan "Unit Baik/Rusak/Hilang".

### 2.4 Pengetatan cakupan akses

- `MovementController` (index/store) dan `WriteOffController` (index/store) memakai `visibleTo`; tujuan mutasi non-lab ditolak untuk non-Super Admin (404).
- Dropdown ruangan pada halaman laporan dibatasi lab untuk Ketua Laboran.

---

## 3. Verifikasi

- `php artisan test --compact`: **130 passed, 613 assertions** (SQLite in-memory), termasuk tes baru:
  - `LabelAndQrTest`: cetak massal per unit, penolakan unit di luar cakupan, `select_all` sesuai filter & cakupan, halaman label membatasi daftar unit.
  - `MovementAndWriteOffTest`: penghapusan mengarsipkan unit & sinkron katalog; Ketua Laboran ditolak mengajukan penghapusan non-lab.
  - `ReportTest`: ringkasan laporan menghitung unit fisik, bukan baris katalog.
- `vendor/bin/pint --dirty --format agent`: bersih.
- `npm run build`: berhasil; utility CSS baru (`lg:grid-cols-5`, `max-h-[60vh]`) tergenerasi.
- Verifikasi visual PDF (koordinat render Dompdf) mengikuti pola yang sudah diverifikasi di `revisi.md`; layout `unit_massal` memakai dimensi yang sama dengan label massal lama yang lolos verifikasi koordinat.

## 4. Yang sengaja tidak diubah

- Model peminjaman sudah punya tabel `loan_item_units` (lihat revisi lanjutan di bagian 5).
- Alur mutasi legacy per baris katalog (`/mutasi`, `item_movements`) — tetap berfungsi dan tercatat di `KNOWN_ISSUES` 9.
- Rute legacy `/items` — tetap untuk masa transisi (404 di luar cakupan sudah diverifikasi).
- Desain UI lama (sidebar slate, aksen amber, kartu `rounded-xl`, ikon Material Round, Figtree) dipertahankan; hanya daftar unit di halaman label yang diganti mengikuti pola tabel yang sudah ada.

---

## 5. Revisi lanjutan 22 Oktober 2026 (serah terima, persetujuan Wakasek, TTD opsional, master lokasi)

Acuan: master prompt *revisi lanjutan* — stabilitas sistem, persetujuan Wakasek, peminjaman, dan master data lokasi.
Metode: audit kode + data (Tinker pada DB pengembangan), pembaruan skema lewat migrasi, uji feature baru (`LoanApprovalHandoverTest`, `BuildingMasterDataTest`), regresi seluruh suite.

### 5.1 Matriks audit revisi lanjutan

| # | Area | Aturan revisi | Kondisi sebelum | Akar masalah | Tindakan | Status |
| - | ---- | ------------- | --------------- | ------------ | -------- | ------ |
| R1 | Error 422 saat serah terima (`/loans/{id}/serah-terima`) | Peminjaman yang sudah disetujui wajib dapat diserahkan; yang belum disetujui ditolak dengan pesan jelas | `abort_unless($loan->status === LoanStatus::APPROVED, 422)` memeriksa status yang **tidak pernah ditulis** oleh alur persetujuan: `decide()` menyimpan approval di `loan_approvals` tetapi tidak pernah mengubah `loans.status` ke `APPROVED`; `isFullyApproved()` hanya dipakai untuk memBUAT approval, tidak untuk menutup rantai | Status transaksi tidak pernah transisi `PENDING → APPROVED` | `Loan::isFullyApproved()` kini menjadi sumber kebenaran tunggal; `decide()` menutup rantai dan menulis `status = APPROVED` (level final) ketika seluruh persetujuan terpenuhi; `handoverForm()` memakai `handoverBlocker()` sehingga blokir tampil sebagai pesan di UI, bukan exception 422 mentah; `handover()` tetap menolak status belum sah dengan pesan domain | **Selesai** |
| R2 | Serah terima ganda | Permintaan berulang tidak boleh menghasilkan penyerahan ganda | Tidak ada pengaman lapis kedua | `handover()` kini mengunci baris (`lockForUpdate`) dan menolak `handed_over_at !== null`; transaksi DB membungkus perubahan status + penugasan unit | **Selesai** |
| R3 | Persetujuan barang bernilai tinggi | Persetujuan sah Wakasek sudah cukup — tanpa persetujuan Admin tambahan | `nextApprovalLevel()` selalu menuntut level 1 (Laboran) lalu level 2; approval Wakasek pun tetap menyisakan tahap | Rantai persetujuan tidak mengenal kewenangan | `Loan::nextApprovalLevelFor()` memberi Wakasek (`isApproverLevel2()`) hak memutuskan pada level akhir; `decide()` mencatat approval Wakasek pada level final sehingga `isFullyApproved()` langsung terpenuhi; `LoanService::requestLoan()` mencatat *persetujuan melekat* atas nama Wakasek sendiri bila Wakasek yang mengajukan (identitas aktor tidak dipalsukan); alur persetujuan oleh role lain (Laboran → Wakasek) tetap utuh | **Selesai** |
| R4 | Tanda tangan digital | TTD tidak boleh menjadi penghalang mutlak | `signature` diasumsikan wajib; dokumen tidak bisa dicetak lalu ditandatangani fisik | Validasi/backend belum memisahkanTTD dari kelayakan | `signature` menjadi `nullable` pada `approve`/`reject`; `DocumentStatus` (enum baru) terpisah dari status transaksi: `BELUM_DIBUAT → SIAP_DICETAK → DICETAK → DITANDATANGANI_FISIK / DITANDATANGANI_DIGITAL`; `markDocumentStatus()` hanya mengubah status dokumen, tidak pernah status peminjaman; BAST menyisakan blok nama untuk tanda tangan manual | **Selesai** |
| R5 | Kewenangan Wakasek | Wakasek mengelola penuh inventaris sekolah | Menu, scope query, dan controller sudah memakai `SUPER_ADMIN` sebagai pengelola, tetapi pengajuan peminjaman Wakasek ikut menunggu persetujuan | Jalur peminjaman belum membedakan kewenangan | `LoanService::requestLoan()` menandai pengajuan Wakasek sebagai *self-approved* dengan audit log & approval record atas namanya sendiri; `User::managesInventory()`/`isApproverLevel2()` menjadi satu-satunya sumber kewenangan (backend, bukan parameter request) | **Selesai** |
| R6 | Master data lokasi | Master Gedung → Master Ruangan → Unit; formulir ruangan cukup gedung/lantai/nomor | Nama gedung disimpan sebagai teks di `rooms.building_letter`; pengguna mengetik nama gedung tiap kali menambah ruangan | Tidak ada master gedung | Tabel `buildings` + model `Building`; `rooms.building_id` (relasi) + `building_letter` (cadangan); halaman master data memisahkan bagian Gedung dan Ruangan; formulir ruangan memakai dropdown gedung, lantai, nomor; kode lokasi & kode inventaris diturunkan dari master gedung | **Selesai** |
| R7 | Normalisasi data lama | Data gedung/ruangan lama tidak boleh berubah diam-diam | Kode ruangan & `internal_id` warisan memakai pola lama (mis. `G1-01`) | Data lama tidak sinkron dengan master | Migrasi `normalize_room_codes_and_internal_ids_to_building_master` memetakan gedung lama → master, menulis ulang `rooms.code` & `rooms.building_letter`, memperbarui `internal_id` katalog, dan **tidak** menyentuh kode unit yang sudah terbit (`asset_unit_codes`) | **Selesai** |
| R8 | Transisi status lain (kembali/batal/tolak) | Semua transisi harus konsisten | Pengembalian & pembatalan sudah memakai guard status, tetapi pesan galat masih berupa exception | UX | `returnForm()`/`processReturn()`/`cancel()` memakai `statusMessage()` yang sama; pembatalan menolak loan yang sudah diserahterimakan dengan pesan mengarahkan ke pengembalian | **Selesai** |

### 5.2 Perubahan yang diimplementasikan

**Status & serah terima**

- `app/Models/Loan.php` — `finalApprovalLevel()`, `isFullyApproved()`, `nextApprovalLevelFor(User)`; approval Wakasek pada level final langsung menutup rantai.
- `app/Services/LoanService.php` — `decide()` menulis `status = APPROVED` saat rantai terpenuhi; `requestLoan()` mencatat persetujuan melekat untuk Wakasek; `handover()` memakai `lockForUpdate()` + guard `handed_over_at`; `markDocumentStatus()` hanya menyentuh status dokumen.
- `app/Http/Controllers/LoanController.php` — `handoverForm()` memakai `Loan::handoverBlocker()` (pesan di UI, bukan 422 mentah); `approve`/`reject` menerima `signature` opsional; endpoint status dokumen baru (`dicetak`, `tanda-tangan-fisik`, `tanda-tangan-digital`).
- `app/Enums/DocumentStatus.php` (baru) — status dokumen terpisah dari status transaksi.

**Kewenangan Wakasek**

- `app/Models/User.php` — `isApproverLevel2()`, `managesInventory()`, `canApproveLoans()`, `canManageLoanDocuments()` sebagai gerbang backend tunggal.
- `resources/views/layouts/menu.blade.php` — menu mengikuti permission backend; blok Administrasi (approval pengadaan/penghapusan, pengguna, pengaturan) hanya untuk Wakasek.

**Master lokasi & generator kode**

- `app/Models/Building.php` (baru), `database/migrations/2026_10_22_195827_create_buildings_table_and_link_rooms.php` — master gedung + relasi ruangan.
- `app/Http/Controllers/MasterController.php` — CRUD gedung, CRUD ruangan (dropdown gedung + lantai + nomor), validasi gedung nonaktif, unik triplet, dan prosedur aman saat kode gedung diubah (dilarang bila sudah ada unit).
- `app/Models/Room.php` — `buildingCode()`, `codePrefix()` (mis. `C1`), `locationCode()` (mis. `C1-03`).
- `app/Services/IdGenerator.php` & `app/Services/InventoryCodeService.php` — sumber kebenaran identitas gedung = master gedung; format kode inventaris `C1-03-21` dipertahankan.
- `database/migrations/2026_10_22_200643_normalize_room_codes_and_internal_ids_to_building_master.php` — migrasi data lama (aman, dapat di-rollback).

### 5.3 Verifikasi

- `php artisan test --compact`: **183 passed (887 assertions)** (SQLite in-memory).
  - `LoanApprovalHandoverTest` (baru): serah terima setelah persetujuan sah; penolakan status belum sah dengan pesan; rantai persetujuan Wakasek tanpa Admin tambahan; approval role lain tetap berjenjang; audit log mencatat aktor benar; TTD kosong tidak menggagalkan alur; status dokumen tidak tertukar dengan status transaksi; serah terima ganda ditolak.
  - `BuildingMasterDataTest` (baru): master gedung terpisah; dropdown gedung dari master; formulir ruangan hanya meminta gedung/lantai/nomor; kode lokasi & kode unit terbentuk benar & unik; ruang/unit lama tetap terhubung setelah migrasi.
- `vendor/bin/pint --format agent`: bersih.
- Migrasi diuji naik-turun (`migrate` → `migrate:rollback`) pada database pengembangan.
