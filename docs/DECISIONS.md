# Keputusan Arsitektur & Catatan Teknis (DECISIONS.md)

Dokumen ini mencatat keputusan arsitektur, asumsi teknis, dan trade-off implementasi Sistem Manajemen Inventaris & Aset Sekolah.

Nomor keputusan **tidak diubah** agar referensi lama tetap valid. Keputusan yang direvisi pada 21 Oktober 2026 diberi catatan "Direvisi 2.1". Keputusan baru ditambahkan mulai nomor 19. Keputusan revisi lanjutan 22 Oktober 2026 (master gedung, penugasan unit, persetujuan Wakasek, status dokumen terpisah, validasi penugasan) diberi catatan "Revisi 2.2" dan berada di keputusan 27–30.

---

## 1. Basis Data: MySQL / MariaDB (Asumsi 1)

- **Keputusan**: Menggunakan MySQL/MariaDB untuk lingkungan produksi/docker dan SQLite in-memory untuk kecepatan automated testing.
- **Rasional**: Dokumen PRD dan skema menggunakan tipe data standar MySQL. Konfigurasi `docker-compose.yml` menyertakan MySQL 8.0 / MariaDB 10.11 dan Redis 7.

## 2. Model Barang: Katalog vs Unit Fisik (Asumsi 2) — Direvisi 2.1

- **Keputusan**: Data inventaris dipecah menjadi dua entitas.
    - `item_catalogs` menyimpan **jenis** barang (nama, kategori, merek/tipe, spesifikasi, satuan, nilai tinggi) tanpa lokasi dan tanpa jumlah manual.
    - `asset_units` menyimpan **setiap barang fisik** dengan lokasi, kondisi, status, perolehan, dan kode inventaris sendiri.
- **Rasional**: Kebutuhan audit, label per barang, dan pencarian kode menuntut identitas per unit. Model batch lama (`items.quantity`) tidak dapat menjawab "kursi nomor 12 ada di mana dan kondisinya apa" tanpa pencatatan manual di luar sistem.
- **Konsekuensi**: Jumlah unit dihitung dari unit (bukan kolom), penghapusan dan mutasi dilakukan per unit, dan label/QR diterbitkan per unit. Rencana migrasi ada di `DATABASE.md` §6.

## 3. Perhitungan Ketersediaan & Anti-Bentrok Jadwal (Asumsi 3, R4)

- **Keputusan**: Ketersediaan peminjaman dihitung bukan hanya dari status instan barang, melainkan dari irisan rentang waktu peminjaman (`loans.start_date` sampai `loans.due_date`) yang berstatus `PENDING`, `APPROVED`, atau `DIPINJAM`.
- **Rasional**: Mencegah peminjaman ganda (double-booking) barang yang sama untuk jadwal mendatang meskipun saat ini barang masih berada di lab. Digunakan transaksi database dan pemeriksaan kuantitas kumulatif.
- **Revisi 2.1**: Kuantitas yang dibooking dihitung dari jumlah unit katalog yang tersedia (`status AKTIF`, `condition BAIK`), bukan dari `items.quantity`. Unit fisik ditugaskan pada saat serah terima (`loan_item_units`).

## 4. Mutasi Lokasi & Penerbitan Ulang Kode (Asumsi 4, Bagian 5.4, 5.5) — Direvisi 2.1

- **Keputusan**:
    - Mutasi dilakukan oleh Ketua Laboran (`KETUA_LABORAN`), Super Admin (`SUPER_ADMIN`), atau petugas yang berwenang atas unit tersebut.
    - Saat unit berpindah ruangan, `asset_units.room_id`, `unit_no`, dan `inventory_code` diperbarui dalam satu `DB::transaction`; kode lama ditutup (`revoked_at`) dan kode baru diterbitkan.
    - `asset_unit_movements` mencatat asal, tujuan, kode lama, kode baru, petugas, waktu, dan alasan.
    - `asset_units.last_label_printed_at` dikosongkan agar label baru wajib dicetak.
- **Rasional**: Kode inventaris adalah penanda lokasi. Kode yang tidak berubah membuat label dan pencarian menunjuk lokasi yang salah.
- **Konsekuensi**: Lihat keputusan 20 dan 21. Unit yang sedang dipinjam tidak boleh dimutasi sebelum dikembalikan.

## 5. Nomor Register Otomatis (Asumsi 5)

- **Keputusan**: Nomor register dibuat otomatis berurutan (sequential) dengan padding nol (`0001`, `0002`, dst.) dalam lingkup katalog (`item_catalog_id`), bukan lagi dalam lingkup baris batch.
- **Rasional**: Memenuhi format penomoran Standar Aset Pemerintah dan menjaga keunikan nomor register per jenis/klasifikasi barang.
- **Revisi 2.1**: Nomor register melekat pada unit, bukan pada katalog. Nomor register boleh kosong untuk barang yang tidak memerlukannya; bila diisi, unik per katalog.

## 6. Autentikasi Menggunakan Username (Bagian 3, 4)

- **Keputusan**: Laravel Breeze disesuaikan agar login menggunakan `email` (bukan `username`). Kolom `users.username` telah dihapus.
- **Rasional**: Pengguna sekolah (guru, staf, laboran) memiliki NIP sebagai identifier; sistem menggunakan email untuk autentikasi tanpa kolom username di tabel users.

## 7. Perlindungan Audit Log (R9)

- **Keputusan**: Event listener / Model protection pada `Activity` (Spatie Activitylog) mencegah operasi update dan hard-delete secara absolut baik via UI maupun internal API/Eloquent.
- **Rasional**: Menjamin keaslian jejak audit seluruh transaksi sensitif (mutasi, approval, penghapusan, pengembalian).
- **Revisi 2.1**: Perubahan kode inventaris, perubahan cakupan akses Laboran, dan penghapusan unit wajib masuk activity log dengan properti `old`/`new`.

## 8. Format Label Stiker & QR Code (Bagian 5.6) — Direvisi 2.1

- **Keputusan**: Label dicetak **per unit** dalam format PDF berisi logo instansi, kode lokasi/kode barang+register, **kode inventaris `G1-02-12`**, dan QR yang menunjuk ke detail unit. Label massal dicetak untuk unit yang dipilih, bukan untuk baris katalog.
- **Rasional**: Satu label harus mewakili satu barang fisik agar stiker dapat ditempel dan dipindai secara unik.
- **Konsekuensi**: Mencetak 23 kursi berarti 23 label. Re-print tersedia pada detail unit; setelah mutasi semua label unit terkait wajib dicetak ulang.

## 9. Database Runtime: MySQL 8.4 (v2)

- **Keputusan**: Lingkungan pengembangan v2 memakai MySQL 8.4 (`inventaris_sekolah_v2`) sesuai stack PRD. Automated test tetap memakai SQLite in-memory agar cepat dan terisolasi.
- **Rasional**: PRD menyebut PostgreSQL di judul skema namun contoh migrasi dan stack menggunakan MySQL/MariaDB; MySQL dipilih agar konsisten dengan migrasi dan deployment Laragon/Docker. Perbedaan dialek tetap diuji lewat test suite.
- **Konsekuensi**: Query yang hanya valid di MySQL tidak boleh dipakai tanpa fallback SQLite (test suite akan menangkapnya). Perbedaan dialek pada unique index dengan kolom `NULL` (lihat `loan_item_units`) harus diuji di kedua dialek.

## 10. Model Kuantitas: Katalog + Unit, Bukan Batch — Direvisi 2.1

- **Keputusan**: Model batch (`items.quantity` dengan `qty_damaged`/`qty_lost`) **digantikan** oleh katalog + unit fisik.
    - Jumlah unit katalog = hasil hitung unit dengan `status != DIHAPUS` dalam cakupan akses.
    - Ketersediaan = unit `AKTIF` + `BAIK` yang tidak dipinjam/dibooking.
    - Kondisi dan status melekat pada unit.
- **Rasional**: Menghilangkan kebutuhan menghitung counter yang mudah melenceng, mendukung QR per unit, dan membuat riwayat kondisi/kehilangan dapat diaudit per barang.
- **Konsekuensi**: Tabel `items` dan `item_movements` menjadi warisan (`legacy_*`) selama migrasi. Selama implementasi belum selesai, versi batch tetap berlaku; lihat `KNOWN_ISSUES.md` butir 1.

## 11. Siklus Peminjaman Eksplisit

- **Keputusan**: Status peminjaman: `PENDING → APPROVED → DIPINJAM → SELESAI`, dengan cabang `DITOLAK` dan `DIBATALKAN`. Serah terima barang wajib dilakukan petugas (`handed_over_at`, `handed_over_by`) sebelum pengembalian.
- **Rasional**: Memisahkan "disetujui" dari "barang benar-benar dibawa" sehingga audit dan pengembalian tidak ambigu.
- **Konsekuensi**: UI menampilkan aksi "Serahkan Barang" hanya untuk petugas berwenang; pembatalan ditolak setelah serah terima.
- **Revisi 2.1**: Saat serah terima, petugas memilih/memindai unit fisik yang diserahkan sehingga `loan_item_units` terisi.
- **Revisi 2.2**: Penugasan unit menjadi **wajib lengkap** saat serah terima: jumlah unit terpilih per baris harus sama dengan jumlah yang dipinjam, kecuali katalog yang belum memiliki unit fisik (data warisan). Lihat keputusan 30.

## 12. Pengembalian Akumulatif & Selisih

- **Keputusan**: `loan_items.qty_returned` bersifat akumulatif. Setiap pengembalian menambah jumlah, bukan menimpa. Selisih `qty_borrowed - qty_returned` dicatat sebagai `qty_lost` sementara pada baris pinjaman; Wakasek dapat menutup pinjaman dan memindahkannya menjadi status unit `HILANG`.
- **Rasional**: Mendukung pengembalian bertahap, barang yang ditemukan menyusul, dan tidak menyelesaikan pinjaman secara palsu saat masih ada kekurangan.
- **Konsekuensi**: Pinjaman dengan selisih tetap aktif sampai ditutup; notifikasi dikirim ke pengelola.

## 13. File Internal Disimpan Privat

- **Keputusan**: Foto bukti kerusakan, tanda tangan, Berita Acara, BAST, dan laporan disimpan di disk `local` (`storage/app/private`) dan disajikan melalui route `/files?path=...` dengan pemeriksaan peran/cakupan. Foto katalog dan logo institusi tetap publik.
- **Rasional**: Dokumen internal tidak boleh dapat diakses hanya dengan menebak URL.
- **Konsekuensi**: Backup harus mencakup `storage/app/private`; web server tidak boleh mengekspos direktori tersebut.

## 14. Livewire 4 Membundel Alpine

- **Keputusan**: `resources/js/app.js` **tidak** mengimpor/menjalankan Alpine lagi. Hanya Chart.js dan html5-qrcode yang didaftarkan ke `window`.
- **Rasional**: Menjalankan Alpine kedua kali membuat directive `wire:model` tidak terpasang; pencarian dan pagination tidak mengirim request (ditemukan saat verifikasi browser).
- **Konsekuensi**: Semua komponen interaktif memakai Alpine bawaan Livewire. Jangan menambahkan `Alpine.start()` di masa depan.

## 15. QR Berisi URL Detail Unit — Direvisi 2.1

- **Keputusan**: QR label berisi URL absolut `route('units.show', $unit)` (berbasis `asset_units.id`), bukan kode inventaris. Kode inventaris hanya tampil sebagai teks pada label. Input manual menerima kode aktif **dan** kode pensiun.
- **Rasional**: URL berbasis ID tetap valid meskipun unit pindah dan kodenya berubah; kode pada label bisa usang, tetapi tautan tidak.
- **Konsekuensi**: `APP_URL` harus benar di produksi. Kode pensiun harus dapat diselesaikan ke unit aktif beserta keterangan "kode lama".

## 16. Laporan Hanya untuk Pengelola

- **Keputusan**: Route laporan dan ekspor dibatasi `SUPER_ADMIN` dan `KETUA_LABORAN`. Laboran tetap dapat melihat label, scan, dan riwayat unit, tetapi tidak dapat mengekspor data seluruh sekolah.
- **Rasional**: Prinsip least privilege; permintaan PRD untuk laboran adalah pemantauan unit, bukan laporan institusi.
- **Revisi 2.1**: Laporan Ketua Laboran hanya memuat unit dalam lingkup laboratorium; laporan Super Admin memuat seluruh unit termasuk ruang umum.

## 17. Excel Multi-Sheet

- **Keputusan**: Ekspor Excel laporan berisi lima sheet: **Unit** (satu baris per unit), Peminjaman, Kerusakan, Mutasi Lokasi, Penghapusan.
- **Rasional**: Isi Excel setara dengan PDF dan dapat difilter per bagian oleh pengelola. Sheet unit memudahkan rekonsiliasi fisik per kode.
- **Revisi 2.1**: Sheet "Barang" diganti "Unit" dan memuat kolom kode inventaris, katalog, lokasi, kondisi, dan status.

## 18. Redis Opsional

- **Keputusan**: Default v2 memakai driver database untuk cache/session/queue karena ekstensi `phpredis` tidak tersedia di lingkungan ini. Konfigurasi Redis tetap ada di `.env.example`.
- **Rasional**: Aplikasi harus dapat dijalankan tanpa layanan tambahan; Redis dapat diaktifkan di produksi setelah ekstensi/predis dipasang.
- **Konsekuensi**: Worker queue tetap wajib dijalankan; lihat `DEPLOYMENT.md`.

---

## 19. Format Kode Inventaris `G1-02-12` (2.1)

- **Keputusan**: Format resmi `[A-Z][1-9]-[0-9]{2}-[0-9]{2}`: huruf gedung + digit lantai, nomor ruangan dua digit, nomor unit dua digit. Disimpan kapital dan kanonik.
- **Rasional**: Kode harus dapat dibaca manusia, menunjukkan lokasi, dan diurutkan secara deterministik. Padding dua digit membuat urutan leksikal sama dengan numerik.
- **Konsekuensi**: Lantai 1–9 dan nomor 1–99 saja. Kapasitas lebih besar membutuhkan perubahan format yang disetujui, bukan penambahan digit diam-diam. Detail di `PROJECT_BRIEF.md` §6.

## 20. Kode Diterbitkan Ulang Saat Mutasi (2.1)

- **Keputusan**: Kode bersifat spasial; mutasi ruangan menghasilkan kode baru. Kode lama dipensiunkan, bukan dihapus.
- **Rasional**: Kode harus mencerminkan lokasi aktual; kode permanen akan menyesatkan pencarian dan audit fisik. Kode lama tetap dapat ditelusuri sehingga audit tidak hilang.
- **Alternatif yang ditolak**: kode permanen non-spasial. Alternatif ini bertentangan dengan format resmi dan definisi kode pada PRD.
- **Konsekuensi**: Wajib cetak ulang label setelah mutasi; ruangan dengan mutasi tinggi lebih cepat mencapai batas 99 nomor.

## 21. Nomor Unit Tidak Pernah Dipakai Ulang (2.1)

- **Keputusan**: `asset_unit_codes` mengunci setiap kombinasi `(room_id, unit_no)` dan `inventory_code` selamanya, termasuk setelah unit pindah atau dihapus. Nomor berikutnya selalu `MAX(unit_no) + 1`.
- **Rasional**: Label lama dapat tertinggal di lapangan. Memakai ulang nomor menciptakan dua label berbeda untuk kode yang sama dan merusak audit.
- **Konsekuensi**: Celah nomor tidak diisi; kapasitas 99 bersifat kumulatif. Pemecahan ruangan resmi adalah solusi saat penuh.

## 22. Normalisasi Gedung dan Ruangan (2.1)

- **Keputusan**: `rooms` memakai `building_letter` (A–Z), `floor` (1–9), `room_no` (1–99), dan `is_lab`, dengan unique `(building_letter, floor, room_no)`. String `building` lama tidak dipakai untuk membentuk kode.
- **Rasional**: Data lama `building = G1/G2/G3` bertabrakan dengan arti "huruf gedung + lantai"; `G2` dapat berarti Gedung G lantai 2 atau Gedung G2 lantai 1. Backfill otomatis akan menghasilkan kode duplikat.
- **Konsekuensi**: Pemetaan huruf gedung harus ditetapkan sekolah sebelum migrasi; migrasi berhenti jika pemetaan belum ada. Ini **blocker** implementasi, bukan asumsi yang boleh ditebak.

## 23. Cakupan Akses Berbasis Ruangan/Laboratorium (2.1)

- **Keputusan**: Cakupan katalog dan unit ditentukan oleh peran dan ruangan:
    - Super Admin: semua unit.
    - Ketua Laboran (`KETUA_LABORAN`): unit pada `rooms.is_lab = true`, lintas jurusan.
    - Laboran/K3 (`LABORAN`): unit pada `user_room_scopes`.
    - Peminjam: unit aktif pada semua ruangan (tidak dibatasi laboratorium agar alur peminjaman yang ada tidak berubah), tanpa field internal.
- **Rasional**: Permintaan eksplisit: Ketua Laboran tidak boleh membaca inventaris umum di luar domain laboratorium, dan Laboran hanya laboratorium yang ditetapkan. Cakupan berbasis jurusan (`users.major_id`) terlalu luas karena satu jurusan dapat memiliki beberapa lab dan satu lab dapat dipakai lintas jurusan.
- **Konsekuensi**: `users.major_id` tetap dipakai untuk pelaporan/penugasan, tetapi **bukan** lagi satu-satunya pembatas akses. Scope diterapkan di query, policy, ekspor, dan resolusi QR; data di luar cakupan menghasilkan 404.

## 24. Semantik Pencarian dan Filter Katalog (2.1)

- **Keputusan**:
    - Kode lengkap = pencocokan tepat; potongan kode = prefix pada kode berpadding (`G1-02` mencakup satu ruangan, `G1-02-1` mencakup unit 10–19).
    - Input dinormalisasi (trim + uppercase). Karakter di luar `A-Z0-9-` atau panjang > 8 ditolak dengan pesan format, tanpa query.
    - Pencarian selalu dibatasi katalog aktif (pada halaman detail) dan cakupan akses pengguna.
    - Filter memakai logika AND dan pilihannya diturunkan dari data yang terlihat.
- **Rasional**: Perilaku harus dapat diprediksi dan diuji; pencarian "mirip" berbasis wildcard di tengah kode membuat hasil sulit dijelaskan dan berpotensi lambat pada data besar.
- **Konsekuensi**: Pencarian kode tidak menemukan kode yang ditulis tanpa padding (`G1-2-1`); UI menampilkan contoh format. Pencarian nama tetap tersedia pada daftar katalog.

## 25. Jumlah Unit Dihitung, Bukan Disimpan (2.1)

- **Keputusan**: Katalog tidak memiliki kolom jumlah. Semua angka dihitung dari `asset_units` dan dibatasi cakupan akses. Bila di-cache, cache hanya percepatan dan wajib dapat dihitung ulang.
- **Rasional**: Angka manual pasti menyimpang dari data unit dan membuat acceptance criteria "sinkronisasi jumlah" tidak dapat dijamin.
- **Konsekuensi**: Daftar katalog memakai `withCount` dengan scope akses; rincian kondisi/status dihitung dalam query teragregasi agar tidak N+1.

## 26. UI Mempertahankan Desain Lama + Tailwind CSS v4 (22 Oktober 2026)

- **Keputusan**: Identitas visual lama dipertahankan (sidebar slate gelap, aksen amber, kartu `rounded-xl`, toolbar filter, tabel detail, Figtree, ikon Material Round, bottom nav mobile). Stylesheet dimigrasikan ke sintaks Tailwind v4: `@import "tailwindcss"`, `@plugin "@tailwindcss/forms"`, `@source` untuk Blade dan pagination, `@theme` untuk font, serta `@custom-variant dark` karena tema memakai class `.dark`.
- **Rasional**: Repository memakai Tailwind 4.3 tetapi CSS masih memakai direktif v3 (`@tailwind base/components/utilities`), sehingga utility tidak dihasilkan dan aplikasi tampil tanpa style meskipun build "berhasil". Desain lama tetap menjadi acuan agar pengguna mengenali aplikasi.
- **Konsekuensi**: `tailwind.config.js` lama tidak lagi menjadi sumber utama; kelas baru harus ditulis di Blade/CSS dan build wajib menghasilkan utility (dicek di `public/build/assets`). Dashboard, statistik, dan menu memakai scope backend yang sama dengan katalog/unit agar UI tidak menampilkan data di luar kewenangan.

## 27. Master Data Gedung Dipisah dari Ruangan (Revisi 22 Oktober 2026)

- **Keputusan**: Identitas gedung dipisahkan dari ruangan.
    - Tabel `buildings` menyimpan nama gedung dan kode satu huruf (A–Z); kode inilah yang dipakai generator kode lokasi dan kode inventaris.
    - Ruangan terhubung ke **tepat satu** master gedung (`rooms.building_id`); `rooms.building_letter` menjadi salinan yang dijaga sinkron dan bukan sumber kebenaran.
    - Formulir ruangan hanya meminta gedung (dropdown dari master), lantai, dan nomor ruangan; nama ruangan dan kode diturunkan sistem.
- **Rasional**: Tanpa pemisahan, teks gedung diketik ulang tiap kali menambah ruangan dan dua ruangan berbeda bisa kebetulan pakai huruf yang sama meski dimaksud gedung berbeda.
- **Konsekuensi**: Pengguna tidak perlu (dan tidak boleh) mengetik nama gedung saat menambah ruangan. Huruf gedung di kode inventaris selalu berasal dari master gedung. Data lama yang belum punya master gedung tetap muncul di filter sebagai pilihan terpisah tanpa nama.

## 28. Penugasan Unit Fisik Saat Serah Terima (Revisi 22 Oktober 2026)

- **Keputusan**: Saat serah terima, petugas memilih/memindai unit fisik yang diserahkan sehingga `loan_item_units` terisi.
- **Konsekuensi**: Status unit `DIPINJAM` dan tercatat pada detail pinjaman.

## 29. Persetujuan Wakasek Tanpa Admin Tambahan (Revisi 22 Oktober 2026)

- **Keputusan**: Wakasek Sarpras berwenang penuh atas inventaris sekolah. Satu persetujuan sah darinya sudah cukup — tidak dituntut persetujuan Admin tambahan yang berulang.
- **Konsekuensi**: Alur berjenjang tetap untuk pihak lain; hanya Wakasek yang bisa memutuskan level akhir sekaligus.

## 30. Status Dokumen Terpisah dari Status Transaksi (Revisi 22 Oktober 2026)

- **Keputusan**: Status dokumen peminjaman dipisahkan dari status transaksi.
- **Rasional**: Status dokumen tidak memengaruhi kelayakan transaksi: dokumen belum dibuat, sudah dicetak, atau sudah ditandatangani secara fisik tidak menentukan apakah peminjaman sah.
- **Konsekuensi**: Status dokumen tidak menentukan kelayakan transaksi: dokumen yang belum dibuat atau belum ditandatangani tidak memblokir serah terima, dan pencetakan dokumen tidak mengubah status peminjaman.

## 31. Kolom Optional di Tabel Relasi (22 Oktober 2026)

- **Keputusan**: `loan_item_units` dan kolom relasi lain yang perlu dukungan legacy membiarkan nilai `NULL` di kolom kunci asing saat data belum siap, dan hanya memberlakukan keunikan/validasi pada level service.
- **Rasional**: Mengizinkan `NULL` memudahkan transisi dari pembukuan kuantitas warisan ke unit fisik tanpa memaksa migrasi sekaligus.
- **Konsekuensi**: Pembatasan siklus unit dan keunikan hanya teroperasi penuh setelah aplikasi beralih ke mode unit; SQLite dan MySQL harus tetap konsisten pada constraint yang bersifat `NULL`-able.

## 32. Validasi Penugasan Unit Wajib Lengkap per Baris (Revisi 22 Oktober 2026)

- **Keputusan**: Saat serah terima dengan unit fisik, jumlah unit yang dipilih per baris peminjaman harus sama dengan jumlah yang dipinjam, kecuali katalog yang belum memiliki unit fisik (data warisan). Unit yang ditugaskan harus aktif, kondisi baik, belum dipinjam orang lain, dan belum pernah dikembalikan pada pinjaman yang sama.
- **Rasional**: Penugasan parsial membuat pencatatan unit fisik dan pembukuan kuantitas tidak sinkron.
- **Konsekuensi**: Sistem menolak serah terima dengan pesan yang menjelaskan masalahnya bila syarat tidak terpenuhi; serah terima tanpa unit masih tersedia untuk katalog warisan.

## 33. Role `KETUA_LABORAN` Menggantikan `ADMIN_SARPRAS` (Revisi 24 Oktober 2026)

- **Keputusan**: Kode role `ADMIN_SARPRAS` diganti `KETUA_LABORAN`. Migration memperbarui `users.role`, tabel `roles` Spatie, dan pivot `model_has_roles`/`role_has_permissions`; tidak ada akun yang kehilangan akses.
- **Rasional**: Label lama "Admin Sarpras" menyiratkan akses seluruh inventaris, padahal cakupannya hanya laboratorium. Wakasek Sarpras sudah menjadi Super Admin, sehingga role kedua harus mencerminkan Ketua Laboran.
- **Konsekuensi**: Seluruh middleware, route, seeder, policy, query scope, notifikasi, dan Blade memakai `KETUA_LABORAN`. Role ini tetap **tidak** memperoleh inventaris nonlaboratorium.

## 34. Kategori Peminjam Dipisahkan dari Role (Revisi 24 Oktober 2026)

- **Keputusan**: Role sistem tetap empat. Kategori siswa/guru/staf disimpan pada `users.borrower_type` (nullable) dan hanya berlaku untuk role `PEMINJAM`.
- **Rasional**: Semua kategori memakai alur peminjaman yang sama; menambah role teknis per kategori akan menduplikasi otorisasi tanpa manfaat.
- **Konsekuensi**: Form pengguna menampilkan kategori hanya untuk peminjam; akun non-peminjam menyimpan `borrower_type = NULL`.

## 35. Persetujuan Otomatis Laboran/K3 dalam Lingkup Sendiri (Revisi 24 Oktober 2026)

- **Keputusan**: Pengajuan Laboran/K3 disetujui otomatis **hanya bila** seluruh barang berasal dari laboratorium yang ditugaskan, bukan barang bernilai tinggi, dan validasi ketersediaan lolos. Barang bernilai tinggi mendapat endorsemen level 1 otomatis lalu menunggu Wakasek. Pengajuan lintas lingkup tetap `PENDING`.
- **Rasional**: Laboran/K3 adalah pihak yang menguasai laboratoriumnya; endorsement ulang oleh pihak lain menambah gesekan tanpa menambah kontrol. Barang bernilai tinggi tetap memerlukan persetujuan khusus.
- **Konsekuensi**: Persetujuan otomatis dicatat atas identitas pemohon dengan catatan "Persetujuan otomatis" pada `loan_approvals` dan `activity_log`. Pemohon tidak dapat menyetujui pengajuan sendiri secara manual.

## 36. Penghapusan Katalog: Hapus atau Arsip (Revisi 24 Oktober 2026)

- **Keputusan**: Katalog tanpa unit, riwayat peminjaman, dan penghapusan boleh dihapus permanen. Katalog yang memiliki salah satunya diarsipkan melalui `item_catalogs.archived_at`; unit dan riwayat transaksi tidak disentuh.
- **Rasional**: Menghapus katalog yang pernah dipakai akan memutus audit dan membuat referensi transaksi menggantung. Arsip mempertahankan integritas data sambil menarik katalog dari penawaran.
- **Konsekuensi**: Katalog diarsipkan tidak tampil di katalog/peminjaman baru dan detailnya hanya dapat dibuka pengelola. Penghapusan diperiksa di backend terhadap role dan cakupan.

## 37. Batas Penyerahan Unit di UI dan Backend (Revisi 24 Oktober 2026)

- **Keputusan**: Form serah terima membatasi pilihan per baris sesuai `qty_borrowed`; unit tambahan disabled setelah batas terpenuhi tetapi centang masih dapat dibatalkan. Backend memvalidasi ulang jumlah, kepemilikan unit, duplikasi, dan request berulang dalam transaksi dengan `lockForUpdate`.
- **Rasional**: Tombol disabled saja tidak cukup; request dapat dikirim langsung. Pembatasan dua lapis mencegah penyerahan berlebih dan penugasan unit ganda.
- **Konsekuensi**: Unit yang tidak dipilih tidak berubah status; penyerahan parsial/berlebih ditolak dan transaksi tetap `APPROVED`.