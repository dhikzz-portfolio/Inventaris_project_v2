# Known Issues & Limitations

Diperbarui: 26 Oktober 2026
Berlaku untuk: aplikasi v2 + revisi 2.1 + revisi role/seeder/peminjaman 24 Oktober 2026 + revisi besar V2 26 Oktober 2026 (`REVISION_V2.md`)

## A. Gap implementasi yang masih tersisa

| # | Isu                                                                                                                                  | Dampak                                                                                                                     | Tingkat | Rencana                                                                                                                  |
| - | ------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------- | ------- | ------------------------------------------------------------------------------------------------------------------------ |
| 1 | Katalog lama (`items.quantity`) masih dipakai berdampingan dengan unit fisik pada pembukuan peminjaman. Kini penugasan unit fisik saat serah terima wajib lengkap per baris (kecuali katalog tanpa unit fisik). | Katalog tanpa unit fisik (data lama) masih memakai pembukuan kuantitas warisan sehingga ada dua sumber kuantitas | Sedang | Migrasikan seluruh katalog lama ke unit fisik, lalu hapus kolom legacy |
| 2 | Penghapusan masih memilih katalog + jumlah (`write_offs.item_id` + `quantity`); unit yang dihapus tidak dipilih satu per satu                                                              | Persetujuan kini **mengarsipkan unit fisik** (HILANG → rusak → baik, nomor tertinggi dulu) sehingga jumlah unit katalog tetap konsisten; identitas unit yang masuk BA belum eksplisit | Sedang  | Ubah ke `asset_unit_id` + `batch_no`, sesuaikan approval dan BA                                                             |
| 3 | Kolom legacy `quantity`, `qty_damaged`, `qty_lost` masih ada dan disinkronkan dari unit                                                | Risiko salah input manual; modul lama masih membaca kolom ini                                                               | Sedang  | Hapus setelah peminjaman/penghapusan/laporan sepenuhnya memakai unit                                                       |
| 4 | Rute lama `/items` masih aktif (label massal per katalog sudah dihapus dan diganti per unit)                                          | Dua antarmuka katalog (lama dan baru) dapat membingungkan pengguna                                                         | Rendah  | Alihkan `/items` ke `/catalogs` setelah modul lain selesai; pertahankan redirect sementara                                 |
| 5 | Master gedung **A–G** sudah tersedia dan idempoten, tetapi data demo masih memakai gedung **G, H, I** (H/I di luar A–G) karena seluruh ruangan/unit/transaksi demo terelasi ke sana. | Kode unit demo tidak akan cocok bila sekolah hanya mengakui A–G; pemetaan ulang mengubah kode yang sudah tercetak | Tinggi | Konfirmasi daftar gedung & nomor ruangan resmi; pindahkan unit demo lewat mutasi/backfill terkontrol sebelum go-live |
| 6 | Kode unit baru dapat bertabrakan dengan `internal_id` lama (mis. `g1-01-01` → `G1-01-01`)                                             | Input manual/scan kode lama dapat membuka unit baru, bukan katalog lama                                                     | Sedang  | Scanner memprioritaskan unit; cetak ulang label lama; jadwalkan migrasi label                                              |
| 7 | Kapasitas 99 nomor per ruangan bersifat kumulatif (termasuk kode pensiun)                                                              | Ruangan dengan banyak mutasi dapat mencapai batas                                                                           | Sedang  | Pecah ruangan atau setujui perubahan format lewat keputusan produk                                                          |
| 8 | Unit yang sedang dipinjam sudah berstatus `DIPINJAM`, tetapi angka katalog masih memakai pembukuan warisan | Angka katalog dapat berbeda sementara dari jumlah unit fisik | Sedang | Selesaikan butir 1 (hapus pembukuan kuantitas warisan) |
| 9 | Pengembalian ke ruangan berbeda sudah memindahkan unit fisik (kode diterbitkan ulang + riwayat), tetapi mutasi katalog lama masih berjalan untuk baris tanpa unit | Riwayat katalog (`item_movements`) masih tersimpan untuk data lama | Sedang | Migrasikan sisa data lama ke `asset_unit_movements` |
| 10 | Peminjam memilih **katalog + jumlah**, bukan unit fisik individual pada saat pengajuan. Unit fisik tetap dipilih petugas saat serah terima (keputusan 28 & 32). | Alur berbeda dari contoh PRD yang menyebut peminjam memilih unit; kode unit hanya ditampilkan sebagai informasi di halaman peminjaman. | Sedang | Bila sekolah menghendaki pemilihan unit saat pengajuan, perlu reservasi `loan_item_units` sebelum approval + kebijakan pelepasan booking. |
| 10b | Ketua Laboran **tidak** dapat menyetujui level 2 (barang bernilai tinggi); hanya Wakasek sesuai keputusan 29. | Bila kebijakan sekolah mengizinkan Ketua Laboran menyetujui nilai tinggi, alur perlu diperluas. | Rendah | Tetapkan kebijakan resmi lalu ubah `User::isApproverLevel2()` + test. |
| 10c | Laporan katalog masih memuat katalog yang diarsipkan karena riwayat unit/transaksi harus tetap terlihat. | Katalog nonaktif dapat muncul di laporan historis, tetapi tidak ditawarkan untuk peminjaman baru. | Rendah | Tambahkan kolom/filter "Arsip" pada laporan bila diperlukan. |
| 10d | Data jurusan lama (TKJ/MM) hanya dihapus bila tidak memiliki relasi. | Database lama dapat menyimpan jurusan historis yang tidak termasuk lima jurusan resmi. | Rendah | Nonaktifkan lewat prosedur migrasi setelah relasi dipindahkan; jangan hapus otomatis. |

## B. Keterbatasan umum yang masih berlaku

| #  | Isu                                                                                          | Dampak                                                                                        | Tingkat                     | Rencana/Workaround                                                                                                       |
| -- | -------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------- | --------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| 11 | Tanda tangan digital adalah **gambar tanda tangan** (bukan e-signature PSrE) dan kini **opsional** - dokumen resmi ditandatangani fisik/manual | Dokumen BAST bukan dokumen legal bertanda tangan elektronik tersertifikasi | Sedang | Bila kebijakan sekolah mewajibkan TTD elektronik, integrasikan penyedia e-signature tersertifikasi |
| 12 | Kamera QR memerlukan HTTPS atau `localhost`                                                  | Di deployment HTTP biasa, tombol kamera gagal; input manual tetap tersedia                    | Rendah                      | Aktifkan HTTPS (Let's Encrypt)                                                                                           |
| 13 | Worker queue & scheduler belum berjalan sebagai service persisten                            | Notifikasi terjadwal/laporan otomatis tidak terkirim sampai worker dijalankan                 | Sedang                      | Jalankan Supervisor + cron (lihat `DEPLOYMENT.md`)                                                                       |
| 14 | Redis tidak aktif secara default                                                             | Cache/session/queue memakai database; beban lebih tinggi pada trafik besar                    | Rendah                      | Pasang `phpredis`/predis lalu ubah `.env`                                                                                |
| 15 | Uji konkurensi nyata di MySQL belum dilakukan                                                | Race condition ekstrem belum diukur                                                           | Sedang                      | Load test staging; lock ruangan + unique index sudah diterapkan                                                           |
| 16 | Pengadaan mendukung alokasi multi-lokasi, tetapi belum memiliki vendor/PO/termin               | Distribusi unit sudah benar; administrasi pengadaan masih sederhana                            | Rendah                      | Modul vendor/PO dapat ditambahkan tanpa mengubah alokasi unit                                                              |
| 17 | Notifikasi hanya in-app                                                                      | Tidak ada email/WhatsApp/SMS                                                                  | Rendah                      | Tambahkan channel Laravel Notification                                                                                    |
| 18 | Tidak ada API publik                                                                         | Integrasi eksternal harus lewat web                                                           | Rendah                      | API wajib mewarisi `visibleTo` dan policy yang sama                                                                       |
| 19 | Laporan PDF besar belum diuji di atas ribuan baris                                           | DomPDF dapat lambat pada data sangat besar                                                    | Rendah                      | Gunakan filter periode/cakupan atau ekspor Excel                                                                          |
| 20 | Reset password mandiri dinonaktifkan                                                         | Pengguna lupa password harus direset admin                                                    | Rendah                      | Aktifkan reset email jika SMTP tersedia                                                                                   |
| 28 | Verifikasi email belum diaktifkan (SMTP belum dikonfigurasi, `User` belum `MustVerifyEmail`) | Format Gmail valid, tetapi kepemilikan alamat belum dibuktikan; fitur yang mensyaratkan verifikasi belum dibatasi | Sedang | Konfigurasi SMTP + `MustVerifyEmail`, lalu uji tautan verifikasi |
| 29 | Impor laporan hanya memulihkan baris unit berkode inventaris stabil (CSV/XLSX)                 | Relasi, transaksi, pengguna, dan arsip PDF tidak pulih dari impor; bukan pengganti backup database | Sedang | Gunakan backup database + storage untuk migrasi penuh; dokumentasikan batas impor |
| 30 | File PDF/Excel arsip laporan yang dihapus tidak dapat dipulihkan dari aplikasi                | Soft delete menyimpan metadata, tetapi file fisik sudah dihapus                                | Rendah | Unduh arsip penting sebelum menghapus; andalkan backup storage |
| 31 | Form kegiatan belum menautkan peminjaman saat pengajuan                                       | Relasi `loans.event_id` ada, tetapi pemilihan kegiatan pada form pinjaman belum tersedia       | Rendah | Tambahkan pemilih kegiatan opsional pada form peminjaman bila dibutuhkan |
| 21 | Audit log immutable hanya pada level aplikasi                                                | Administrator database masih dapat mengubah `activity_log`                                    | Rendah                      | Batasi akses DB, backup/WORM, user DB least-privilege                                                                     |
| 22 | Foto katalog & logo tetap publik                                                              | Siapa pun yang tahu URL dapat melihat foto/logo                                                | Rendah                      | Tidak mengandung data pribadi                                                                                              |
| 23 | Docker image belum dibangun/diuji di lingkungan ini                                          | Docker Compose belum diverifikasi runtime                                                     | Sedang                      | Jalankan `docker compose build` di host ber-Docker                                                                        |
| 24 | Deployment produksi belum dilakukan                                                          | Belum ada URL publik                                                                          | —                           | Ikuti `DEPLOYMENT.md`; jangan klaim online sebelum diverifikasi                                                           |
| 25 | Akun demo memiliki password yang mudah ditebak                                               | Risiko jika dipakai di produksi                                                               | Tinggi (jika tidak diganti) | Wajib ganti/hapus akun demo sebelum go-live                                                                               |
| 26 | Zona waktu default `Asia/Jakarta`                                                            | Server di zona lain harus menyesuaikan `APP_TIMEZONE`                                         | Rendah                      | Setel sesuai lokasi sekolah                                                                                              |
| 27 | Service worker hanya cache shell                                                             | Aplikasi tidak dapat digunakan sepenuhnya offline                                             | Rendah                      | Offline penuh bukan target                                                                                                |

## C. Diselesaikan pada revisi 2.1

### C.0 Revisi besar 24 Oktober 2026 — Status: SELESAI (implementasi + test otomatis)

- **Role**: `ADMIN_SARPRAS` → `KETUA_LABORAN` melalui migration data (users + Spatie), seluruh route/middleware/seeder/scope/notifikasi/Blade diselaraskan. Wakasek Sarpras menjadi Super Admin penuh; Ketua Laboran hanya laboratorium; Laboran/K3 hanya ruangan yang ditugaskan.
- **Kategori peminjam** (`users.borrower_type`) dipisahkan dari role; akun demo siswa, guru, dan staf tersedia.
- **Seeder** dipecah menjadi master, akun demo, inventaris demo, dan transaksi demo; lima jurusan resmi; 42 katalog/125 unit; aman dijalankan ulang, tidak mereset password, tidak menghapus transaksi.
- **Persetujuan otomatis Laboran/K3** untuk barang biasa di laboratorium sendiri; barang bernilai tinggi tetap menunggu Wakasek; campuran lintas lingkup tetap `PENDING`; pemohon tidak dapat menyetujui pengajuan sendiri secara manual. Semua keputusan otomatis tercatat di `loan_approvals` dan `activity_log` dengan alasan jelas.
- **Katalog peminjam** digabung ke halaman peminjaman dengan pencarian/filter; katalog duplikat ditolak di UI dan backend.
- **Hapus katalog aman**: hapus permanen hanya tanpa unit/transaksi; selain itu arsip `archived_at`, unit/riwayat tidak dihapus, katalog arsip tidak dapat dipinjam dan tidak tampil di daftar.
- **Batas penyerahan unit**: pilihan dibatasi `qty_borrowed`, unit berlebih disabled di UI dan ditolak backend; unit di luar transaksi, unit ganda, dan request berulang ditolak dalam transaksi terkunci.

### C.1 Revisi lanjutan 22 Oktober 2026 — Status: SELESAI sepenuhnya (implementasi + verifikasi)

- **Error 422 saat serah terima** (`Hanya peminjaman yang disetujui dapat diserahkan`): alur persetujuan menyimpan keputusan di `loan_approvals`, tetapi tidak pernah mengubah `loans.status` ke `APPROVED`, sehingga halaman serah terima selalu menolak. Kini `decide()` menutup rantai persetujuan dan menulis status `APPROVED`; halaman serah terima menampilkan pesan yang jelas (bukan exception 422 mentah) bila syarat belum terpenuhi.
- **Serah terima ganda** dimungkinkan oleh permintaan berulang. Kini `handover()` mengunci baris peminjaman dan menolak peminjaman yang sudah punya `handed_over_at`.
- **Persetujuan barang bernilai tinggi** menuntut Admin tambahan meski persetujuan Wakasek sudah sah. Kini satu persetujuan Wakasek pada level akhir langsung menutup rantai; persetujuan oleh role lain (Laboran → Wakasek) tetap berjenjang.
- **Wakasek** kini dapat mengajukan peminjaman tanpa menunggu persetujuan orang lain; persetujuannya dicatat atas namanya sendiri (tidak dipalsukan sebagai approval Admin lain) beserta audit log.
- **Tanda tangan digital** tidak lagi menjadi syarat persetujuan. `signature` opsional; status dokumen (`BELUM_DIBUAT / SIAP_DICETAK / DICETAK / DITANDATANGANI_FISIK / DITANDATANGANI_DIGITAL`) terpisah dari status transaksi.
- **Master data lokasi** disederhanakan: tabel `buildings` (master gedung) + `rooms.building_id`; formulir ruangan hanya meminta gedung (dropdown), lantai, dan nomor ruangan; kode lokasi & kode inventaris diturunkan dari master gedung. Data lama dimigrasikan tanpa mengubah kode unit yang sudah terbit.
- **Penugasan unit fisik** saat serah terima sudah tersedia (`loan_item_units`); unit berstatus `DIPINJAM` dan kembali ke `AKTIF` + kondisi tersimpan saat pengembalian.
- **Validasi penugasan unit** wajib lengkap per baris: jumlah unit yang dipilih harus sama dengan jumlah yang dipinjam (kecuali katalog tanpa unit fisik). Unit yang ditugaskan harus aktif, kondisi baik, belum dipinjam orang lain, dan belum pernah dikembalikan pada pinjaman yang sama; sistem menolak serah terima dengan pesan yang menjelaskan masalahnya.

### C.2 Revisi 2.1 — Status: SELESAI sepenuhnya (implementasi + verifikasi browser + test)

- **Cetak label massal masih per baris katalog** (satu stiker per katalog, tanpa filter cakupan). Kini `/label` mendaftar **unit fisik** dalam cakupan akses, mendukung filter + pencarian kode + pemilihan per unit + "pilih semua sesuai filter" (lintas halaman), dan mencetak PDF grid 2 kolom berisi kode inventaris serta QR per unit.
- **Persetujuan penghapusan tidak menyentuh unit fisik** (hanya kolom legacy). Kini unit yang dihapus diarsipkan (`status DIHAPUS` + `archived_at`) dan counter katalog dihitung ulang dari unit.
- **Ringkasan laporan menghitung baris katalog, bukan unit fisik** untuk kolom kondisi. Kini dihitung per unit dengan urutan prioritas eksklusif PRD 5.5; sheet Excel "Barang" diganti "Unit" (kode inventaris, lokasi, kondisi, status per unit).
- **Mutasi/penghapusan legacy dapat menembus cakupan akses**. Kini katalog di luar cakupan menghasilkan 404 dan tujuan mutasi non-lab ditolak untuk non-Super Admin; dropdown ruangan laporan lab-only untuk Ketua Laboran.

- **Tailwind CSS v4 tidak menghasilkan utility** (masih memakai direktif v3) sehingga seluruh UI tampil tanpa style. Diperbaiki dengan `@import "tailwindcss"`, `@plugin "@tailwindcss/forms"`, `@source` untuk Blade/pagination, `@theme` Figtree, dan varian gelap berbasis class.
- **Dashboard tidak mengikuti cakupan role**: angka katalog/unit/kerusakan sekarang memakai `AssetUnit::visibleTo` (Super Admin seluruh sekolah, Ketua Laboran seluruh lab, Laboran ruang yang ditetapkan); daftar pinjaman, grafik, dan aktivitas ikut dibatasi.
- **Menu Laboran menampilkan Mutasi Lokasi** padahal route hanya untuk pengelola; menu kini mengikuti permission backend.
- **Tidak ada UI mengubah kondisi unit**: detail unit kini memiliki form kondisi/catatan untuk Laboran dalam cakupan, serta status (Aktif/Hilang) khusus pengelola; perubahan dicatat di audit log.
- **Katalog legacy `/items/{id}`** dapat dibuka di luar cakupan; sekarang 404, dan pengadaan oleh Ketua Laboran dibatasi ke ruangan laboratorium.
- Laporan/ekspor (PDF, Excel, arsip) memakai cakupan laboratorium untuk Ketua Laboran; arsip seluruh sekolah hanya untuk Super Admin; arsip milik sendiri dapat diunduh pembuatnya.
- Peminjam tidak melihat nilai perolehan, catatan internal, riwayat mutasi, dan riwayat kode pada detail unit.
- Perubahan cakupan ruangan pengguna dicatat di audit log (ruangan sebelum/sesudah, pelaku, waktu).
- Tabel `items` diganti menjadi `item_catalogs`; katalog dipisahkan dari unit fisik (`asset_units`).
- 40 unit demo terbentuk dari 10 baris batch, dengan kode unik per unit.
- Kode `G1-02-12` diterbitkan otomatis, divalidasi, memakai ledger `asset_unit_codes`, dan tidak dapat dipakai ganda.
- Nomor unit tidak dipakai ulang; kode pensiun tetap dapat dicari.
- Mutasi unit menerbitkan kode baru, menyimpan kode lama dan riwayat perpindahan.
- Detail katalog hanya menampilkan unit katalog tersebut; pencarian exact/prefix, filter, sorting, pagination.
- Cakupan katalog/unit/pencarian/URL diterapkan untuk Super Admin, Ketua Laboran, dan Laboran/K3.
- Ruangan memiliki `building_letter`, `room_no`, `is_lab`; pengguna memiliki `user_room_scopes`.
- Pengadaan yang disetujui langsung menerbitkan unit; pengajuan yang masih pending/rejected tidak memakai kode.
- Label per unit (PDF) dan tombol cetak ulang tersedia di detail unit.

### C.3 Revisi besar V2 26 Oktober 2026 — Status: SELESAI (implementasi + test otomatis)

- **Registrasi mandiri** dengan Gmail, nama lengkap, gelar, username (dihapus), kelas; role selalu `PEMINJAM` dan tidak dapat dikirim dari browser; welcome screen memakai nama lengkap/gelar.
- **Scan universal** untuk semua role login; kamera + input manual, hasil langsung di halaman scan, data internal dimasking untuk peminjam, unit di luar cakupan tidak dibocorkan, scan tidak menulis data.
- **Master gedung A–G** ditambahkan idempoten; H/I dipertahankan untuk data demo; kode lokasi/unit tetap dari master gedung.
- **`asset_code` dihapus** dari skema dan seluruh tampilan/label/QR/laporan; identitas tunggal adalah kode unit.
- **Pengadaan multi-lokasi** dengan alokasi per ruangan, validasi total backend, unit otomatis per lokasi, dan transaksi atomik.
- **Mutasi multi-unit** dengan pencarian, filter, ringkasan pilihan, penolakan unit ganda/di luar cakupan/sedang dipinjam, serta riwayat per unit.
- **Impor laporan** CSV/XLSX dengan pratinjau, deteksi duplikat, token konfirmasi, dan transaksi; **hapus arsip** soft delete + hapus file tanpa menghapus data inventaris, dengan audit log.
- **Rekap kegiatan** untuk guru/staf dengan status `Belum/Sedang/Sudah Berjalan` tersimpan; status tidak mengubah persetujuan peminjaman.
- **Surat peminjaman** tidak lagi memuat field kegiatan untuk transaksi biasa, tanggal tanpa jam, identitas per kategori, kode unit per unit, serta pratinjau cetak `@media print`.
- **UI pengguna** memakai panel kartu yang sama untuk tambah/edit dengan field kondisional dan validasi dekat field.
- **Wakasek/Super Admin** dapat menghapus data dalam kewenangannya tanpa persetujuan admin lain; role lain tidak memperoleh hak baru.

## Bug yang sudah diperbaiki selama verifikasi

- Livewire/Alpine dimuat dua kali → pencarian dan pagination tidak berfungsi.
- Pencarian form peminjaman memakai `wire:model.debounce` tanpa `.live` → hasil tidak muncul.
- Pengembalian parsial menimpa `qty_returned` (bukan akumulatif).
- Tombol pengembalian tidak pernah tampil karena flag `canAct` khusus status pending.
- Status `APPROVED` tidak memiliki aksi serah terima.
- Stok parsial rusak/hilang mengubah status seluruh baris barang.
- File bukti/BA/BAST dapat diakses langsung dari URL publik.
- Selector JavaScript label massal salah dan atribut Alpine tidak valid.
- `/logout` melalui GET tidak tersedia.
- Route closure menghalangi `route:cache`.
- Validasi `exists:items,id` gagal setelah tabel diganti menjadi `item_catalogs`.
