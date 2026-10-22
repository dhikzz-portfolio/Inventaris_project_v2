# Known Issues & Limitations

Diperbarui: 22 Oktober 2026
Berlaku untuk: aplikasi v2 + implementasi inti revisi 2.1 (katalog & unit fisik) + pengetatan cakupan laporan

## A. Gap implementasi yang masih tersisa

| # | Isu                                                                                                                                  | Dampak                                                                                                                     | Tingkat | Rencana                                                                                                                  |
| - | ------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------- | ------- | ------------------------------------------------------------------------------------------------------------------------ |
| 1 | Peminjaman masih level katalog/kuantitas; unit fisik belum ditugaskan saat serah terima (`loan_item_units` belum dibuat)              | Riwayat pinjaman belum menunjuk unit tertentu; status unit tidak otomatis `DIPINJAM`; kondisi per unit tidak terhubung ke pinjaman | Tinggi  | Buat `loan_item_units`, pilih unit saat serah terima, tutup penugasan saat kembali, sinkronkan kondisi/status unit         |
| 2 | Penghapusan masih memilih katalog + jumlah (`write_offs.item_id` + `quantity`); unit yang dihapus tidak dipilih satu per satu                                                              | Persetujuan kini **mengarsipkan unit fisik** (HILANG → rusak → baik, nomor tertinggi dulu) sehingga jumlah unit katalog tetap konsisten; identitas unit yang masuk BA belum eksplisit | Sedang  | Ubah ke `asset_unit_id` + `batch_no`, sesuaikan approval dan BA                                                             |
| 3 | Kolom legacy `quantity`, `qty_damaged`, `qty_lost` masih ada dan disinkronkan dari unit                                                | Risiko salah input manual; modul lama masih membaca kolom ini                                                               | Sedang  | Hapus setelah peminjaman/penghapusan/laporan sepenuhnya memakai unit                                                       |
| 4 | Rute lama `/items` masih aktif (label massal per katalog sudah dihapus dan diganti per unit)                                          | Dua antarmuka katalog (lama dan baru) dapat membingungkan pengguna                                                         | Rendah  | Alihkan `/items` ke `/catalogs` setelah modul lain selesai; pertahankan redirect sementara                                 |
| 5 | Pemetaan huruf gedung data demo bersifat sementara (`G1`→G, `G2`→H, `G3`→I)                                                          | Huruf final sekolah belum ditetapkan; migrasi produksi berisiko bila ditebak                                                | Tinggi  | Konfirmasi pemetaan gedung dan nomor ruangan sebelum migrasi data nyata                                                    |
| 6 | Kode unit baru dapat bertabrakan dengan `internal_id` lama (mis. `g1-01-01` → `G1-01-01`)                                             | Input manual/scan kode lama dapat membuka unit baru, bukan katalog lama                                                     | Sedang  | Scanner memprioritaskan unit; cetak ulang label lama; jadwalkan migrasi label                                              |
| 7 | Kapasitas 99 nomor per ruangan bersifat kumulatif (termasuk kode pensiun)                                                              | Ruangan dengan banyak mutasi dapat mencapai batas                                                                           | Sedang  | Pecah ruangan atau setujui perubahan format lewat keputusan produk                                                          |
| 8 | Unit yang sedang dipinjam tidak otomatis berstatus `DIPINJAM`                                                                          | Detail unit dapat menampilkan `AKTIF/BAIK` meskipun barang sedang di luar; angka tersedia katalog sudah dikurangi pinjaman aktif | Tinggi  | Selesai bersama butir 1 (penugasan unit pada serah terima)                                                                 |
| 9 | Pengembalian ke ruangan berbeda masih memindahkan katalog/baris lama, bukan unit fisik                                                  | Kode unit tidak berubah pada alur pengembalian lintas ruangan; riwayat unit tidak mencatat perpindahan tersebut             | Sedang  | Gunakan `InventoryCodeService::move()` per unit setelah penugasan unit tersedia                                             |

## B. Keterbatasan umum yang masih berlaku

| #  | Isu                                                                                          | Dampak                                                                                        | Tingkat                     | Rencana/Workaround                                                                                                       |
| -- | -------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------- | --------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| 11 | Tanda tangan digital adalah **gambar tanda tangan**, bukan e-signature tersertifikasi PSrE   | Dokumen BAST bukan dokumen legal bertanda tangan elektronik tersertifikasi                    | Sedang                      | Integrasikan penyedia e-signature tersertifikasi bila diperlukan                                                         |
| 12 | Kamera QR memerlukan HTTPS atau `localhost`                                                  | Di deployment HTTP biasa, tombol kamera gagal; input manual tetap tersedia                    | Rendah                      | Aktifkan HTTPS (Let's Encrypt)                                                                                           |
| 13 | Worker queue & scheduler belum berjalan sebagai service persisten                            | Notifikasi terjadwal/laporan otomatis tidak terkirim sampai worker dijalankan                 | Sedang                      | Jalankan Supervisor + cron (lihat `DEPLOYMENT.md`)                                                                       |
| 14 | Redis tidak aktif secara default                                                             | Cache/session/queue memakai database; beban lebih tinggi pada trafik besar                    | Rendah                      | Pasang `phpredis`/predis lalu ubah `.env`                                                                                |
| 15 | Uji konkurensi nyata di MySQL belum dilakukan                                                | Race condition ekstrem belum diukur                                                           | Sedang                      | Load test staging; lock ruangan + unique index sudah diterapkan                                                           |
| 16 | Pengadaan disederhanakan pada level barang                                                   | Tidak ada vendor/PO/termin                                                                     | Rendah                      | Modul pengadaan lanjutan dapat ditambahkan                                                                                |
| 17 | Notifikasi hanya in-app                                                                      | Tidak ada email/WhatsApp/SMS                                                                  | Rendah                      | Tambahkan channel Laravel Notification                                                                                    |
| 18 | Tidak ada API publik                                                                         | Integrasi eksternal harus lewat web                                                           | Rendah                      | API wajib mewarisi `visibleTo` dan policy yang sama                                                                       |
| 19 | Laporan PDF besar belum diuji di atas ribuan baris                                           | DomPDF dapat lambat pada data sangat besar                                                    | Rendah                      | Gunakan filter periode/cakupan atau ekspor Excel                                                                          |
| 20 | Reset password mandiri dinonaktifkan                                                         | Pengguna lupa password harus direset admin                                                    | Rendah                      | Aktifkan reset email jika SMTP tersedia                                                                                   |
| 21 | Audit log immutable hanya pada level aplikasi                                                | Administrator database masih dapat mengubah `activity_log`                                    | Rendah                      | Batasi akses DB, backup/WORM, user DB least-privilege                                                                     |
| 22 | Foto katalog & logo tetap publik                                                              | Siapa pun yang tahu URL dapat melihat foto/logo                                                | Rendah                      | Tidak mengandung data pribadi                                                                                              |
| 23 | Docker image belum dibangun/diuji di lingkungan ini                                          | Docker Compose belum diverifikasi runtime                                                     | Sedang                      | Jalankan `docker compose build` di host ber-Docker                                                                        |
| 24 | Deployment produksi belum dilakukan                                                          | Belum ada URL publik                                                                          | —                           | Ikuti `DEPLOYMENT.md`; jangan klaim online sebelum diverifikasi                                                           |
| 25 | Akun demo memiliki password yang mudah ditebak                                               | Risiko jika dipakai di produksi                                                               | Tinggi (jika tidak diganti) | Wajib ganti/hapus akun demo sebelum go-live                                                                               |
| 26 | Zona waktu default `Asia/Jakarta`                                                            | Server di zona lain harus menyesuaikan `APP_TIMEZONE`                                         | Rendah                      | Setel sesuai lokasi sekolah                                                                                              |
| 27 | Service worker hanya cache shell                                                             | Aplikasi tidak dapat digunakan sepenuhnya offline                                             | Rendah                      | Offline penuh bukan target                                                                                                |

## C. Diselesaikan pada revisi 2.1

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
