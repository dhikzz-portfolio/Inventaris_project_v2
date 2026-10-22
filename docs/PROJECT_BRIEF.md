# Project Brief & PRD — Sistem Manajemen Inventaris & Aset Sekolah

Versi dokumen: **2.2** · Diperbarui: **22 Oktober 2026**
Status: **spesifikasi final; inti katalog, unit, kode inventaris, cakupan akses, dan laporan telah diimplementasikan dan diverifikasi** (lihat `PROJECT_STATUS.md`)
Dokumen ini adalah **PRD utama** dan sumber kebenaran untuk perilaku produk. Kamus data, keputusan arsitektur, dan status implementasi dirujuk ke `DATABASE.md`, `DECISIONS.md`, dan `PROJECT_STATUS.md`.

Riwayat revisi:

| Versi | Tanggal    | Perubahan                                                                                                          |
| ----- | ---------- | ------------------------------------------------------------------------------------------------------------------ |
| 2.2   | 2026-10-22 | Master gedung sebagai sumber kebenaran kode lokasi; tanda tangan digital opsional; status dokumen terpisah dari status transaksi; penugasan unit fisik saat serah terima; kewenangan persetujuan Wakasek |
| 2.1   | 2026-10-21 | Katalog dipisahkan dari unit fisik; format kode `G1-02-12`; kebijakan mutasi; pencarian/filter sadar cakupan akses |
| 2.0   | 2026-10-20 | Baseline implementasi v2 (model batch per baris barang)                                                            |

---

## 1. Latar belakang

Sekolah mengelola ratusan aset yang tersebar di banyak gedung, laboratorium, dan jurusan. Pencatatan manual menyebabkan:

- Barang hilang atau rusak tidak terlacak.
- Jadwal peminjaman bentrok karena tidak ada kalender ketersediaan.
- Nomor register dan label aset tidak konsisten.
- Dokumen serah terima (BAST) dan laporan dibuat ulang secara manual.
- Tidak ada jejak audit siapa meminjam, menyetujui, atau memindahkan barang.

Aplikasi ini menggantikan pencatatan manual tersebut dengan satu sistem web terpusat yang dapat diakses dari browser desktop maupun ponsel.

Konsekuensi penting dari kebutuhan audit dan label fisik: **satu unit barang harus dapat dikenali sendiri**. Karena itu revisi 2.1 memisahkan **katalog jenis barang** (apa yang dimiliki sekolah) dari **unit inventaris fisik** (barang yang benar-benar ada, lengkap dengan lokasi dan kode unik).

## 2. Tujuan

1. Menyediakan **katalog jenis barang** tunggal yang menjadi daftar resmi barang yang dimiliki sekolah, dengan jumlah unit yang dihitung dari data unit fisik.
2. Memberi **setiap unit fisik** identitas inventaris yang unik, terbaca dari lokasi, dan dapat dicetak pada label/QR: `G1-02-12`.
3. Mencatat siklus hidup aset: pengadaan, penempatan, mutasi, peminjaman, pengembalian, kerusakan/kehilangan, dan penghapusan.
4. Mencegah double-booking melalui validasi server dan transaksi database.
5. Menyediakan persetujuan berjenjang dan audit trail yang tidak dapat diubah dari aplikasi.
6. Menghasilkan label QR, surat peminjaman/BAST, laporan PDF, dan Excel multi-sheet.
7. Memberi pengalaman mobile-first bagi laboran (scan QR, pengembalian checklist).

## 3. Pengguna, peran, dan cakupan akses

Kode peran pada aplikasi dipertahankan agar tidak memutus data dan otorisasi yang sudah ada. Istilah produk dipetakan sebagai berikut.

| Peran (kode sistem)             | Istilah produk     | Kebutuhan utama                                                                                                                |
| ------------------------------- | ------------------ | ------------------------------------------------------------------------------------------------------------------------------ |
| `SUPER_ADMIN` (Wakasek Sarpras) | Super Admin        | Akses penuh seluruh katalog, unit, lokasi (termasuk ruang umum), approval pengadaan/penghapusan, approval level 2, pengaturan. Mengelola penuh inventaris sekolah: pengajuan peminjaman miliknya sendiri **disetujui atas kewenangan penuh** tanpa menunggu persetujuan tambahan. |
| `ADMIN_SARPRAS`                 | Ketua Laboran      | Mengelola inventaris **seluruh laboratorium di semua jurusan**; master data, mutasi, label, pengadaan, penghapusan, laporan.   |
| `LABORAN`                       | Laboran/K3         | Mengelola pinjaman/pengembalian dan inventaris **laboratorium yang ditetapkan untuk akunnya**; scan QR, approval level 1.      |
| `PEMINJAM`                      | Peminjam/Guru/Staf | Melihat katalog & ketersediaan, mengajukan multi-item, membatalkan pengajuan, melihat riwayat dan notifikasi sendiri.          |

### 3.1 Cakupan data katalog dan unit

| Peran         | Katalog & unit yang terlihat                                                                                                          | Kewenangan ubah                                  |
| ------------- | ------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------ |
| Super Admin   | Semua unit, termasuk ruang umum dan gudang.                                                                                           | Semua operasi sesuai fitur.                      |
| Ketua Laboran | Hanya unit yang lokasinya **ruang laboratorium** (`rooms.is_lab = true`), lintas jurusan.                                             | Mutasi, label, pengadaan, penghapusan, laporan.  |
| Laboran/K3    | Hanya unit pada ruangan yang tercatat di `user_room_scopes` untuk akun tersebut (semua ruang itu lab).                                | Pinjam/kembali, ubah kondisi, scan, cetak label. |
| Peminjam      | Unit aktif pada katalog yang tersedia untuk peminjaman (semua ruangan); tanpa nilai perolehan, catatan internal, atau riwayat mutasi. | Hanya melihat dan mengajukan.                    |

Aturan yang mengikat:

- Ruang di luar cakupan **tidak boleh terlihat** lewat daftar, detail, pencarian kode, filter, URL langsung, endpoint ekspor, maupun respons API di masa depan. Gunakan **404** (bukan 403) agar keberadaan data tidak bocor.
- Katalog yang tampil bagi Ketua Laboran/Laboran hanya katalog yang memiliki **minimal satu unit dalam cakupan**. Jumlah unit yang ditampilkan adalah jumlah unit dalam cakupan, bukan jumlah seluruh sekolah.
- Otorisasi diterapkan di **backend pada setiap query dan operasi**, bukan hanya dengan menyembunyikan menu. Policy/global scope menjadi satu-satunya jalur pengambilan data.
- Perubahan cakupan akses harus tercatat di audit log (siapa mengubah, kapan).

## 4. Cakupan implementasi

### Termasuk

- Autentikasi username/password, sesi, rate limit login, otorisasi role + cakupan laboratorium/ruangan.
- Dashboard berbeda untuk pengelola dan peminjam, memakai data aktual dan menghormati cakupan akses.
- **Katalog jenis barang** dan **unit inventaris fisik** sebagai dua entitas terpisah (bagian 5).
- **Kode inventaris `G1-02-12`** yang dibentuk otomatis, divalidasi, dan tidak pernah dipakai ganda (bagian 6).
- **Detail katalog** berisi daftar unit, pencarian kode, filter, sorting, dan pagination (bagian 8).
- CRUD katalog, kategori, jurusan, **master gedung**, ruangan; pencarian debounce, filter, sorting, pagination.
- Pengadaan barang dengan approval Wakasek.
- Peminjaman multi-item, keranjang, kalender ketersediaan, anti-bentrok.
- Approval berjenjang + tanda tangan gambar (signature pad) yang **opsional**.
- Serah terima barang dengan **penugasan unit fisik**, pembatalan, pengembalian parsial/akumulatif, selisih hilang, kerusakan + foto bukti.
- **Status dokumen** peminjaman terpisah dari status transaksi (belum dibuat → siap dicetak → dicetak → ditandatangani fisik/digital).
- Mutasi unit dengan penerbitan kode baru dan riwayat kode/pergerakan yang dapat ditelusuri (bagian 7).
- Penghapusan aset dengan Berita Acara dan approval.
- Label QR per unit, satuan/massal, re-print, scan kamera + input manual.
- BAST per peminjaman dan BAST gabungan kegiatan.
- Laporan bulanan (PDF + Excel 5 sheet), filter kategori/ruangan/jurusan, selalu dibatasi cakupan akses.
- Notifikasi in-app dan job terjadwal (jatuh tempo, terlambat, laporan bulanan).
- Tema terang/gelap, bottom navigation mobile, PWA manifest + service worker.
- Audit log immutable, file internal privat, pengujian otomatis dan dokumentasi.

### Tidak termasuk

- Akuntansi aset/penyusutan dan integrasi sistem pemerintah.
- Tanda tangan elektronik tersertifikasi (PSrE).
- API publik / integrasi pihak ketiga.
- Deployment ke server produksi nyata (tidak ada akses server pada lingkungan ini).
- Perubahan alur bisnis peminjaman selain penyesuaian referensi data (lihat `DECISIONS.md` §11 dan `DATABASE.md` §2.8).

## 5. Katalog barang dan unit inventaris

### 5.1 Definisi

- **Katalog barang** (`item_catalogs`) adalah **jenis** barang yang dimiliki sekolah: "Kursi", "Meja", "Proyektor". Katalog tidak memiliki lokasi dan tidak memiliki nomor unit.
- **Unit inventaris** (`asset_units`) adalah **satu barang fisik** yang terdaftar: setiap kursi, meja, atau proyektor memiliki baris sendiri, lokasi aktif, kondisi, status, dan kode inventaris.

Contoh katalog:

| Nama Barang | Kategori   | Jumlah Unit |
| ----------- | ---------- | ----------: |
| Kursi       | Furnitur   |          23 |
| Meja        | Furnitur   |          10 |
| Lemari      | Furnitur   |           4 |
| Proyektor   | Elektronik |           2 |

Dua puluh tiga kursi identik tampil sebagai **satu entri katalog dengan jumlah 23**, bukan 23 entri katalog. Namun 23 kursi tersebut tetap memiliki **23 baris unit** dengan kode `G1-02-01` sampai `G1-02-23` (bila berada di ruangan yang sama dan belum ada nomor terpakai).

### 5.2 Atribut katalog

| Atribut                    | Wajib | Keterangan                                                      |
| -------------------------- | :---: | --------------------------------------------------------------- |
| `id`                       |   ✔   | Kunci utama.                                                    |
| `name`                     |   ✔   | Nama jenis barang, mis. "Kursi".                                |
| `category_id`              |   ✔   | Kategori/klasifikasi.                                           |
| `brand`                    |       | Merek, mis. "Chitose".                                          |
| `model_type`               |       | Tipe/model, mis. "Y-2".                                         |
| `specification`            |       | Spesifikasi/deskripsi teknis.                                   |
| `unit`                     |   ✔   | Satuan tampilan: `unit`, `set`, `buah`.                         |
| `is_high_value`            |   ✔   | Menentukan kebutuhan approval level 2.                          |
| `photo_path`               |       | Foto representatif katalog (bukan bukti kondisi unit tertentu). |
| `description`              |       | Catatan umum.                                                   |
| `created_at`, `updated_at` |   ✔   | Jejak waktu.                                                    |

**Jumlah unit bukan kolom katalog.** Jumlah selalu dihitung dari unit yang terhubung (bagian 5.5) agar tidak berbeda dari data aktual.

### 5.3 Atribut unit inventaris

| Atribut                 | Wajib | Keterangan                                                                   |
| ----------------------- | :---: | ---------------------------------------------------------------------------- |
| `id`                    |   ✔   | Kunci utama; dipakai QR agar URL tetap stabil meskipun kode berubah.         |
| `item_catalog_id`       |   ✔   | Katalog induk.                                                               |
| `inventory_code`        |   ✔   | Kode inventaris aktif, unik, format `G1-02-12`.                              |
| `room_id`               |   ✔   | Lokasi aktif (gedung, lantai, dan nomor ruangan diturunkan dari `rooms`).    |
| `unit_no`               |   ✔   | Nomor unit 01–99 dalam ruangan; bagian ketiga kode.                          |
| `condition`             |   ✔   | `BAIK`, `RUSAK_RINGAN`, `RUSAK_BERAT` (`App\Enums\Condition`).               |
| `status`                |   ✔   | `AKTIF`, `DIPINJAM`, `HILANG`, `DIHAPUS` (enum baru `App\Enums\UnitStatus`). |
| `acquisition_date`      |       | Tanggal perolehan (boleh hanya tahun bila data lama tidak lengkap).          |
| `acquisition_year`      |       | Tahun perolehan bila tanggal lengkap tidak tersedia.                         |
| `acquisition_value`     |       | Nilai/harga perolehan; tidak ditampilkan ke Peminjam.                        |
| `register_no`           |       | Nomor register aset; unik per katalog bila diisi.                            |
| `funding_source`        |       | Sumber dana/perolehan, mis. BOS, komite, hibah.                              |
| `acquisition_source`    |       | Asal perolehan, mis. pembelian, hibah, sumbangan.                            |
| `photo_path`            |       | Foto unit aktual (opsional).                                                 |
| `notes`                 |       | Catatan internal.                                                            |
| `last_label_printed_at` |       | Untuk kebutuhan re-print.                                                    |
| `archived_at`           |       | Diisi saat unit dihapus; baris tidak dihapus permanen.                       |

Kode klasifikasi barang pada label diturunkan dari `categories.code`; nilainya tidak disimpan ulang di unit agar tidak menyimpang dari kategori. Gedung, lantai, dan ruangan ditampilkan dari relasi `rooms` (tidak diduplikasi menjadi kolom unit) sehingga selalu mengikuti lokasi aktif.

Riwayat pemeliharaan, riwayat perpindahan, dan riwayat kode disimpan pada tabel riwayat (lihat `DATABASE.md`), bukan sebagai kolom unit. Unit yang sudah `DIHAPUS` tetap ada untuk audit dan tetap **mengunci kodenya**.

### 5.4 Aturan pengelompokan katalog

1. Unit dengan **jenis dan spesifikasi katalog yang sama** digabung ke satu entri katalog, berapa pun jumlahnya.
2. Perbedaan yang penting bagi pengelolaan inventaris **tidak boleh digabung**: kategori berbeda, merek berbeda, tipe/model berbeda, atau spesifikasi material/ukuran yang berbeda.
3. Perbedaan **kondisi, lokasi, tanggal perolehan, nilai, atau nomor unit** bukan alasan membuat katalog baru. Perbedaan itu melekat pada unit.
4. Katalog tidak pernah memiliki kode inventaris; kode hanya dimiliki unit.
5. Katalog yang seluruh unitnya sudah dihapus **tidak boleh dihapus** selama masih dirujuk riwayat. Super Admin melihatnya sebagai arsip berjumlah 0 dengan penanda "tidak ada unit aktif"; peran lain tidak melihatnya di daftar operasional, tetapi riwayat unit tetap dapat ditelusuri.
6. Katalog serupa dicegah lewat kunci unik `(category_id, name, brand, model_type)`; impor/entri ganda harus gagal dengan pesan yang menyebut katalog yang sudah ada.

### 5.5 Perhitungan jumlah unit

Angka **Jumlah Unit** pada katalog dihitung dari unit aktif, dengan aturan eksplisit:

- `jumlah_unit` = jumlah unit dengan `status != DIHAPUS` **dan** berada dalam cakupan akses pengguna.
- Rincian yang wajib konsisten: `jumlah_unit = tersedia + dipinjam + rusak + hilang`.
- Unit `DIHAPUS` tidak dihitung pada jumlah, tetapi tetap tampil di riwayat/arus keluar.
- Angka **Tersedia** = unit `status = AKTIF` dan `condition = BAIK` yang tidak sedang dipinjam atau dibooking pada rentang tanggal yang diminta.
- Kategori rincian bersifat saling eksklusif dengan urutan prioritas: `DIHAPUS` (keluar dari hitungan) → `HILANG` → `DIPINJAM` → kondisi `RUSAK_RINGAN`/`RUSAK_BERAT` → `tersedia`. Dengan urutan ini, unit hilang yang kondisinya masih tercatat rusak tetap dihitung satu kali sebagai hilang.
- Unit `RUSAK_RINGAN` tidak dapat dipinjam sampai kondisinya diubah menjadi `BAIK` oleh petugas.
- Tidak boleh ada kolom jumlah manual pada katalog. Bila angka perlu di-cache, cache wajib dihitung ulang dari tabel unit dan tidak boleh menjadi sumber kebenaran.

## 6. Kode inventaris

### 6.1 Format resmi

```
G1-02-12
```

| Bagian | Contoh | Arti                                          |
| ------ | ------ | --------------------------------------------- |
| `G1`   | `G1`   | Kode gedung **G** dan lantai **1**.           |
| `02`   | `02`   | Nomor ruangan 02 pada gedung/lantai tersebut. |
| `12`   | `12`   | Nomor unit inventaris 12 di ruangan tersebut. |

`G1-02-12` berarti unit inventaris nomor 12 di ruangan 02, Gedung G lantai 1.

Contoh lain:

- `F1-03-15` = unit 15, ruangan 03, Gedung F lantai 1.
- `G1-02-23` = unit 23, ruangan 02, Gedung G lantai 1.
- `G2-01-01` = unit 1, ruangan 01, Gedung G lantai 2.

### 6.2 Tata bahasa dan normalisasi

| Aturan           | Ketentuan                                                                                               |
| ---------------- | ------------------------------------------------------------------------------------------------------- |
| Pola valid       | `^[A-Z][1-9]-[0-9]{2}-[0-9]{2}$`                                                                        |
| Panjang          | Tepat 8 karakter, dua tanda hubung wajib.                                                               |
| Huruf gedung     | Satu huruf kapital A–Z.                                                                                 |
| Lantai           | Satu digit 1–9 tanpa nol depan.                                                                         |
| Nomor ruangan    | Dua digit `01`–`99`.                                                                                    |
| Nomor unit       | Dua digit `01`–`99`.                                                                                    |
| Penyimpanan      | Selalu huruf kapital, tanpa spasi.                                                                      |
| Masukan pengguna | `trim` + konversi ke huruf kapital sebelum validasi/pencarian; bukan izin menyimpan bentuk non-kanonik. |
| Pemisah          | Hanya `-`. Spasi, `/`, `.`, atau `_` ditolak.                                                           |

Contoh normalisasi: `g1-02-12` → `G1-02-12` (valid); `G1/02/12`, `G1-2-12`, `G1-02-120`, `1102-12` ditolak.

### 6.3 Aturan pembentukan

1. Setiap unit fisik memiliki tepat satu kode inventaris aktif yang unik.
2. Kode menunjukkan gedung, lantai, ruangan, dan nomor urut unit.
3. Nomor unit diurutkan **dalam lingkup ruangan**, bukan global sekolah.
4. Barang sejenis tetap memiliki kode berbeda bila merupakan unit fisik berbeda.
5. Tidak ada dua unit aktif dengan kode yang sama.
6. Struktur kode divalidasi sebelum data disimpan; kode tidak dapat diisi bebas oleh pengguna.
7. Kode dibentuk otomatis dari lokasi dan nomor urut berikutnya yang tersedia.
8. Pengguna tidak boleh memilih nomor unit yang sudah terpakai atau kode yang tidak sesuai lokasi.
9. Perpindahan lokasi mengikuti kebijakan bagian 7 dan tetap meninggalkan riwayat.
10. Kode lama tetap dapat ditelusuri setelah kode berubah.

### 6.4 Pembentukan otomatis

Algoritma (dijalankan di dalam transaksi):

```
prefix   = buildings.code + lantai            # G1  (huruf dari master gedung)
ruangan  = zeroPad(rooms.room_no, 2)         # 02
nomor    = max(unit_no pada ruangan itu, termasuk kode yang sudah pensiun) + 1
kode     = prefix + "-" + ruangan + "-" + zeroPad(nomor, 2)
```

- `max` dihitung dari `asset_unit_codes` untuk ruangan yang sama (mencakup kode aktif dan pensiun). Celah nomor akibat unit dihapus/pindah **tidak diisi ulang**.
- Nomor tidak pernah kembali ke `01` setelah unit pindah atau dihapus. Alasannya ada pada bagian 7.3.
- Bila `nomor > 99`, pembuatan unit **ditolak** (bagian 6.6); sistem tidak boleh memakai tiga digit secara diam-diam.

Contoh: ruangan belum punya unit lain, 23 kursi dibuat berurutan `G1-02-01`, `G1-02-02`, … `G1-02-12`, … `G1-02-23`. Bila ruangan yang sama juga berisi meja, lemari, dan proyektor, nomor berikutnya melanjutkan urutan yang sama (`G1-02-24`, `G1-02-25`, …). **Jangan** memakai penomoran terpisah per jenis barang karena akan menghasilkan kode duplikat.

### 6.5 Validasi

| Lapisan            | Pemeriksaan                                                                                                                        |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------- |
| Form/UI            | Kolom kode bersifat baca-saja; hanya menampilkan pratinjau sebelum simpan.                                                         |
| Validasi server    | Pola `^[A-Z][1-9]-[0-9]{2}-[0-9]{2}$`, konsistensi segmen dengan ruangan terpilih, dan rentang nomor 01–99.                        |
| Konsistensi lokasi | Segmen 1 harus sama dengan `rooms.building_letter + rooms.floor` (huruf dari `buildings.code`); segmen 2 harus sama dengan `rooms.room_no`. |
| Keunikan           | Unique index `asset_units.inventory_code` dan unique `asset_unit_codes(room_id, unit_no)` serta `asset_unit_codes.inventory_code`. |
| Konkurensi         | Kunci baris ruangan (`SELECT … FOR UPDATE`) + transaksi; bila unique index tetap bentrok, ulangi maksimal 3 kali.                  |
| Impor/legacy       | Hanya lewat perintah khusus yang memakai validator yang sama; tidak ada jalur UI untuk memasukkan kode manual.                     |

Kode kesalahan yang wajib ditampilkan dengan pesan jelas (bukan halaman error mentah):

| Kode                        | Kondisi                                                  |
| --------------------------- | -------------------------------------------------------- |
| `KODE_FORMAT_TIDAK_VALID`   | Pola tidak sesuai.                                       |
| `KODE_TIDAK_SESUAI_LOKASI`  | Segmen gedung/lantai/ruangan tidak cocok dengan ruangan. |
| `KODE_SUDAH_DIGUNAKAN`      | Kode/nomor unit sudah dipakai unit lain.                 |
| `RUANGAN_PENUH`             | Nomor unit melebihi 99.                                  |
| `UNIT_TIDAK_DAPAT_DIPINDAH` | Unit sedang dipinjam atau sudah dihapus.                 |

### 6.6 Batas 99 unit per ruangan

Format resmi memakai dua digit, sehingga satu ruangan menampung maksimal **99 nomor unit seumur hidup** (termasuk nomor unit yang sudah pensiun, lihat 7.3). Bila batas tercapai:

1. Sistem menolak pembuatan/pemindahan unit dengan `RUANGAN_PENUH` dan menjelaskan nomor terakhir yang terpakai.
2. Tindakan yang diizinkan: memecah ruangan menjadi dua ruangan resmi, atau memindahkan unit ke ruangan lain.
3. Perubahan format (mis. tiga digit) **tidak boleh** dilakukan sebagai tambalan. Perubahan harus menjadi keputusan produk tersendiri yang memperbarui PRD, kamus data, label, dan alat scan, serta menyediakan migrasi kode lama.

> **Perlu konfirmasi:** apakah sekolah membutuhkan lebih dari 99 unit dalam satu ruangan (mis. kursi aula/ruang rapat). Bila ya, format alternatif harus ditetapkan sebelum implementasi; gunakan asumsi aman (99) sampai ada keputusan.

### 6.7 Sumber kebenaran identitas gedung

Huruf gedung pada kode inventaris **tidak boleh diketik bebas** pada formulir ruangan. Identitas gedung berasal dari **master gedung** (`buildings`).

Aturan yang mengikat:

1. Satu master gedung = satu huruf A–Z (`buildings.code`) + nama (`buildings.name`) + status aktif (`is_active`).
2. Setiap ruangan terikat ke **tepat satu** master gedung (`rooms.building_id`); `rooms.building_letter` adalah salinan yang dijaga sinkron dan bukan sumber kebenaran.
3. Formulir ruangan hanya meminta **gedung (dropdown dari master), lantai, dan nomor ruangan**. Nama ruangan opsional (default `Ruang NN`); **kode lokasi diturunkan sistem** menjadi `{huruf gedung}{lantai}-{nomor 2 digit}`, mis. `C1-03`.
4. Gedung nonaktif tidak dapat dipilih untuk lokasi baru, tetapi ruangannya yang sudah ada tetap utuh.
5. **Kode gedung tidak boleh berubah diam-diam.** Perubahan kode ditolak bila gedung tersebut sudah punya unit inventaris; petugas harus memutasikan unit lebih dulu. Bila tidak ada unit, kode lokasi ruangan ikut diperbarui sementara **kode inventaris yang sudah terbit tidak disentuh**.
6. Gedung tidak dapat dihapus selama masih memiliki ruangan.
7. Migrasi data lama memetakan gedung warisan ke master gedung dan menulis ulang `rooms.code` & `rooms.building_letter` **tanpa mengubah kode unit yang sudah terbit** (`asset_unit_codes`).

## 7. Perpindahan unit dan kebijakan kode

### 7.1 Keputusan

**Kode inventaris bersifat spasial dan diterbitkan ulang saat unit berpindah ruangan.** Unit mempertahankan `id` dan riwayat, tetapi kode aktifnya berubah mengikuti lokasi baru.

Alasan:

- Kode wajib menunjukkan lokasi aktual (bagian 6.3 poin 2). Kode yang tidak berubah akan menyesatkan pencarian, audit fisik, dan staf yang menemukan barang.
- Label lama yang masih menempel harus bisa dikenali sebagai kode **pensiun** dan diarahkan ke unit yang sama, bukan dianggap unit lain.
- Nomor unit tidak boleh dipakai ulang (7.3), sehingga riwayat tetap tunggal dan tidak ambigu.

Alternatif yang ditolak: kode permanen yang tidak berubah saat mutasi. Alternatif ini membuat kode tidak lagi menunjukkan lokasi dan bertentangan dengan format resmi; bila suatu saat dibutuhkan, harus menjadi keputusan produk baru dengan format kode non-spasial.

### 7.2 Prosedur mutasi (atomik)

1. Validasi: unit ada, tidak `DIHAPUS`, tidak `DIPINJAM`, ruangan tujuan valid dan berbeda.
2. Kunci unit dan ruangan tujuan dalam satu transaksi.
3. Hitung nomor unit berikutnya pada ruangan tujuan (tidak mengisi celah).
4. Tutup baris kode aktif: isi `revoked_at` pada `asset_unit_codes`.
5. Terbitkan kode baru dan catat baris baru pada `asset_unit_codes` (`issued_at`, alasan `MUTASI`).
6. Perbarui `asset_units.room_id`, `unit_no`, `inventory_code`; kosongkan `last_label_printed_at` agar label baru wajib dicetak.
7. Catat `asset_unit_movements` (`from_room_id`, `to_room_id`, `old_code`, `new_code`, `moved_by`, `moved_at`, `note`).
8. Catat audit log dan tampilkan notifikasi "cetak ulang label" kepada petugas.

Jika salah satu langkah gagal, seluruh transaksi dibatalkan. Tidak boleh ada kondisi unit sudah pindah tetapi kode lama masih aktif.

### 7.3 Penomoran ulang dan kode pensiun

- Nomor unit **tidak direset** dan **tidak diisi ulang**. Unit yang pindah ke ruangan lain mendapat nomor berikutnya di ruangan tujuan.
- Kode lama disimpan permanen di `asset_unit_codes` dengan `revoked_at` terisi dan **tetap dikunci**: kode tersebut tidak boleh diberikan kepada unit lain, meskipun unit aslinya sudah dihapus.
- Unit yang kembali ke ruangan asal tidak mendapatkan kode lamanya; ia mendapat nomor baru. Hal ini mencegah dua label fisik berbeda menunjuk ke dua unit berbeda dengan kode yang sama.
- Pencarian/scan kode pensiun menampilkan unit terkait beserta keterangan "kode lama, lokasi sekarang …" dan tautan ke kode aktif. Bila kode pensiun tidak dikenal, tampilkan pesan tidak ditemukan.
- Label fisik: setelah mutasi, label lama harus dicetak ulang. Sistem menandai `last_label_printed_at = NULL` dan menyediakan daftar "label perlu dicetak ulang".

### 7.4 Dampak yang diterima

- Kode pada stiker lama menjadi usang; prosedur operasional wajib mencetak ulang label segera setelah mutasi.
- Ruang dengan banyak mutasi dapat lebih cepat mencapai batas 99 nomor.
- Riwayat kode dan riwayat perpindahan menjadi dua catatan berbeda: riwayat kode menjawab "kode apa yang pernah menempel pada unit ini", riwayat perpindahan menjawab "unit ini pernah berada di mana".

## 8. Katalog, detail unit, pencarian, dan filter

### 8.1 Daftar katalog

- Menampilkan satu baris per katalog: nama, kategori, merek/tipe, jumlah unit, dan ringkasan ketersediaan.
- Pencarian teks mencocokkan nama katalog, spesifikasi, merek, dan kode unit yang berada dalam cakupan pengguna.
- Filter: kategori, gedung, lantai, ruangan, dan ketersediaan ("ada unit tersedia"/"tidak ada").
- Sorting default nama A–Z; opsi jumlah unit terbanyak.
- Pagination 12 katalog per halaman; kata kunci dan filter dipertahankan di URL dan saat berpindah halaman.
- Katalog tanpa unit dalam cakupan pengguna tidak ditampilkan. Katalog tanpa unit aktif hanya terlihat oleh Super Admin sebagai arsip (jumlah 0) melalui filter khusus.

### 8.2 Detail katalog

Membuka katalog **Kursi** menampilkan daftar unit **kursi saja**, bukan unit semua jenis barang:

| Kode Inventaris | Lokasi                       | Nama Barang | Kondisi      | Status |
| --------------- | ---------------------------- | ----------- | ------------ | ------ |
| `G1-02-01`      | Gedung G, Lantai 1, Ruang 02 | Kursi       | Baik         | Aktif  |
| `G1-02-02`      | Gedung G, Lantai 1, Ruang 02 | Kursi       | Baik         | Aktif  |
| `G1-02-12`      | Gedung G, Lantai 1, Ruang 02 | Kursi       | Rusak Ringan | Aktif  |
| `F1-03-15`      | Gedung F, Lantai 1, Ruang 03 | Kursi       | Baik         | Aktif  |

Data di atas adalah ilustrasi, bukan data inventaris sekolah yang terverifikasi.

Detail juga menampilkan ringkasan: jumlah unit dalam cakupan, rincian kondisi/status, dan tautan aksi (cetak label, riwayat unit) sesuai hak akses. Baris unit membuka detail unit (`/units/{unit}`) yang memuat kode aktif, kode lama, lokasi, kondisi, status, perolehan, foto, catatan, riwayat pemeliharaan, dan riwayat perpindahan.

### 8.3 Pencarian kode inventaris

Berlaku pada daftar katalog dan, terutama, pada detail katalog (konteks katalog terkunci). Pencarian di detail katalog khusus menerima kode; pencarian nama dilakukan pada daftar katalog.

| Masukan                   | Perilaku                                                                                                                   |
| ------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| `G1-02-12`                | Pencocokan **tepat**; bila ditemukan, tampilkan unit tersebut dan sorot barisnya.                                          |
| `G1-02`                   | Semua unit di ruangan 02 Gedung G lantai 1 **dalam katalog aktif**.                                                        |
| `G1`                      | Semua unit di Gedung G lantai 1 dalam katalog aktif.                                                                       |
| `G1-02-1`                 | Prefix nomor unit: cocok untuk `10`–`19`. Untuk `01`–`09`, pengguna mengetik `G1-02-0`.                                    |
| `kursi`                   | Pada daftar katalog: pencarian nama. Pada detail katalog: ditolak dengan pesan "Gunakan kode inventaris, contoh G1-02-12". |
| `g1-02-12`                | Dinormalisasi menjadi `G1-02-12`.                                                                                          |
| `G1/02/12` atau `G1-2-12` | Ditolak dengan pesan "Format kode tidak valid. Contoh: G1-02-12". Tidak ada query dijalankan.                              |
| Lebih dari 8 karakter     | Ditolak dengan pesan yang sama.                                                                                            |

Ketentuan hasil:

- Hasil selalu dibatasi cakupan akses dan, pada detail katalog, dibatasi `item_catalog_id` yang sedang dibuka.
- Setiap hasil menampilkan kode, lokasi lengkap, kondisi, dan status.
- Bila tidak ada hasil: tampilkan kondisi kosong informatif — "Tidak ada unit dengan kode `…` pada katalog Kursi di cakupan Anda" — beserta tombol reset filter.
- Pencarian tidak boleh membocorkan keberadaan unit di luar cakupan (jangan tampilkan "ditemukan tetapi tidak dapat diakses").
- Pencarian kode pensiun mengikuti bagian 7.3.

### 8.4 Filter

Detail katalog: gedung, lantai, ruangan, kondisi, status, dan tanggal/tahun perolehan (rentang tahun bila tanggal lengkap tidak tersedia).
Daftar katalog: kategori, gedung, lantai, ruangan, ketersediaan.

- Semua filter memakai logika **AND**, baik antar-filter maupun dengan kata kunci kode.
- Pilihan filter diturunkan dari data yang **terlihat** oleh pengguna (tidak menampilkan gedung/ruangan di luar cakupan).
- Contoh: buka detail Kursi → pilih Gedung G, Lantai 1, Ruang 02 → cari `G1-02-12` → hanya unit yang memenuhi seluruh kondisi yang tampil.
- Filter aktif ditampilkan sebagai chip dan dapat direset sekaligus.

### 8.5 Sorting, pagination, dan jumlah

- Unit diurutkan berdasarkan `inventory_code` menaik secara default (padded sehingga urutan leksikal = numerik dalam satu ruangan). Opsi lain: perolehan terbaru, kondisi terburuk.
- Pagination unit 25 per halaman; halaman, filter, dan kata kunci dipertahankan.
- Label jumlah: "Menampilkan 1–25 dari 63 unit" dan angka tersebut harus sama dengan hasil filter, bukan total katalog.
- Sorting/pagination diterapkan setelah scoping akses, bukan sebelum.

### 8.6 Rute dan antarmuka

| Halaman           | Rute                      | Nama rute        | Catatan                                                                 |
| ----------------- | ------------------------- | ---------------- | ----------------------------------------------------------------------- |
| Daftar katalog    | `GET /catalogs`           | `catalogs.index` | Menggantikan daftar `/items` setelah migrasi.                           |
| Detail katalog    | `GET /catalogs/{catalog}` | `catalogs.show`  | Konteks katalog terkunci untuk pencarian dan filter unit.               |
| Detail unit       | `GET /units/{unit}`       | `units.show`     | Target URL QR; berbasis `asset_units.id` agar stabil saat kode berubah. |
| Scan/resolve kode | `GET /scan/{code}`        | `scan.resolve`   | Menerima kode aktif dan kode pensiun; tetap memeriksa cakupan akses.    |
| Ekspor            | rute ekspor yang ada      | —                | Wajib memakai scope yang sama dengan daftar; tidak ada jalur bypass.    |

Selama masa transisi, `/items` dan `/items/{item}` tetap melayani data legacy, lalu dialihkan permanen (301) ke rute baru setelah cutover.

### 8.7 Keadaan kosong dan galat

| Keadaan                   | Tampilan                                                                          |
| ------------------------- | --------------------------------------------------------------------------------- |
| Katalog tidak punya unit  | "Katalog ini belum memiliki unit terdaftar." + aksi tambah unit (jika berwenang). |
| Filter tidak menghasilkan | "Tidak ada unit yang cocok dengan filter." + reset.                               |
| Kode tidak ditemukan      | Pesan kode yang dicari + saran periksa format/contoh.                             |
| Kode di luar cakupan      | Sama seperti tidak ditemukan (tidak membocorkan data).                            |
| Format kode salah         | Pesan validasi inline, tanpa request ke server bila memungkinkan.                 |

## 9. Persetujuan, serah terima, dan dokumen

### 9.1 Rantai persetujuan

1. Level 1 = Laboran/Ketua Laboran; level 2 = Wakasek Sarpras, wajib untuk barang bernilai tinggi (`is_high_value`).
2. **Satu sumber kebenaran kelayakan**: `Loan::isFullyApproved()` memeriksa apakah persetujuan pada **level akhir** sudah tercatat. Level akhir sekaligus menutup level di bawahnya.
3. `Loan::nextApprovalLevelFor(User)` menentukan siapa yang berhak memutuskan berikutnya. **Wakasek (approver level 2) memutuskan pada level akhir**, sehingga satu persetujuan yang sah darinya sudah cukup — tidak dituntut persetujuan Admin tambahan yang berulang.
4. **Pengajuan milik Wakasek disetujui atas kewenangan penuh** inventaris sekolah. Persetujuannya dicatat atas namanya sendiri (identitas aktor tidak dipalsukan sebagai approver lain) beserta entri audit log, dan dokumen kegiatan (BAST) otomatis dibuat bila peminjaman terkait kegiatan.
5. Alur oleh role lain tetap berjenjang: Laboran/Ketua Laboran (level 1) → Wakasek (level 2).
6. Penolakan wajib disertai alasan (`reject_reason`).
7. Otorisasi keputusan ditentukan di **backend** dari peran pengguna (`User::isApproverLevel2()`, `User::canDecideAt()`), bukan dari parameter request.

### 9.2 Serah terima dan penugasan unit fisik

1. Serah terima hanya sah pada status `APPROVED`. Prasyarat yang belum terpenuhi ditampilkan sebagai **pesan di antarmuka** (`Loan::handoverBlocker()`), bukan halaman exception 422 mentah.
2. **Penjagaan serah terima ganda**: baris peminjaman dikunci (`lockForUpdate`) dan `handed_over_at` yang sudah terisi ditolak. Satu peminjaman hanya dapat diserahterimakan sekali.
3. Halaman serah terima (`/loans/{id}/serah-terima`) menampilkan **unit yang dapat diserahkan** per baris peminjaman: unit dalam cakupan akses petugas, `AKTIF`, kondisi `BAIK`, dan belum ditugaskan pada peminjaman lain yang terbuka.
4. Jumlah unit yang dipilih **harus sama** dengan jumlah yang dipinjam pada baris tersebut. Penugasan parsial ditolak karena membuat pencatatan unit fisik dan pembukuan kuantitas tidak sinkron. Pengecualian: katalog tanpa unit fisik (data warisan) tetap memakai pembukuan kuantitas lama.
5. Unit yang dipilih dicatat pada `loan_item_units` (waktu serah terima, kondisi keluar, `opened_at`), status unit menjadi `DIPINJAM`, dan audit log menyimpan daftar kode unit yang diserahkan.
6. Saat pengembalian, penugasan ditutup (`returned_at`, `condition_in`, `closed_at`) dan unit kembali berstatus `AKTIF` dengan kondisi hasil pemeriksaan.

### 9.3 Status dokumen terpisah dari status transaksi

| Status dokumen            | Arti                                                              |
| ------------------------- | ----------------------------------------------------------------- |
| `BELUM_DIBUAT`            | Dokumen belum dibuat.                                             |
| `SIAP_DICETAK`            | Dokumen siap dicetak.                                             |
| `DICETAK`                 | Dokumen sudah dicetak.                                            |
| `DITANDATANGANI_FISIK`    | Sudah ditandatangani manual oleh pihak terkait.                  |
| `DITANDATANGANI_DIGITAL`  | Sudah ditandatangani dengan tanda tangan digital tersimpan.      |

Aturan yang mengikat:

1. Status dokumen **tidak menentukan kelayakan transaksi**. Dokumen yang belum dibuat atau belum ditandatangani tidak memblokir serah terima; pencetakan dokumen tidak membuat transaksi menjadi disetujui.
2. **Tanda tangan digital bersifat opsional.** Dokumen resmi dapat dicetak dan ditandatangani fisik/manual; BAST menyediakan blok nama untuk tanda tangan manual.
3. Pencatatan status dokumen hanya boleh dilakukan pengguna berwenang (`User::canManageLoanDocuments()`) dan hanya menyentuh kolom `document_status` — bukan status transaksi.
4. Status dokumen **tidak boleh** bertukar dengan status transaksi. Contoh: menandai "sudah dicetak" tidak mengubah `PENDING` menjadi `APPROVED`.

---

## 10. Kebutuhan non-fungsional

- **Kinerja**: halaman katalog/dashboard < 1 detik dan pencarian < 0,5 detik pada lingkungan uji untuk 5.000 unit. Query daftar unit wajib menghindari N+1 (eager load ruangan/katalog, agregat sekali jalan).
- **Integritas**: pembentukan kode, mutasi, penghapusan, dan peminjaman berjalan dalam transaksi. Tidak boleh ada kode ganda meskipun dua petugas menyimpan bersamaan.
- **Audit**: pembuatan unit, perubahan kode, mutasi, perubahan kondisi/status, penghapusan, dan perubahan cakupan akses tercatat di `activity_log` yang immutable dari aplikasi.
- **Privasi**: nilai perolehan, catatan internal, dan dokumen privat tidak terlihat oleh Peminjam serta tidak dapat diakses lewat URL langsung.
- **Aksesibilitas**: status/kondisi selalu disertai teks, bukan hanya warna; target sentuh memadai di ponsel.

## 11. Acceptance criteria

Status pada tabel ini adalah status **spesifikasi** (dokumen). Status implementasi dan bukti pengujian ada di `PROJECT_STATUS.md` dan `TESTING.md` §9.

Catatan AC-10: aplikasi belum memiliki API publik; aturan ini tetap mengikat untuk route web saat ini dan wajib diwarisi bila API ditambahkan.

| ID    | Kriteria                                                                                                                        | Rujukan  | Status spesifikasi |
| ----- | ------------------------------------------------------------------------------------------------------------------------------- | -------- | ------------------ |
| AC-01 | 23 kursi dengan jenis katalog sama tampil sebagai satu entri katalog "Kursi" dengan jumlah 23.                                  | 5.1, 5.4 | Terpenuhi          |
| AC-02 | Setiap kursi memiliki data unit inventaris dan kode inventaris masing-masing.                                                   | 5.3, 6.3 | Terpenuhi          |
| AC-03 | Kode `G1-02-12` diinterpretasikan sebagai Gedung G, lantai 1, Ruang 02, unit nomor 12.                                          | 6.1, 6.5 | Terpenuhi          |
| AC-04 | Sistem menolak dua unit berbeda memakai kode inventaris yang sama secara bersamaan.                                             | 6.5      | Terpenuhi          |
| AC-05 | Nomor unit tidak bentrok antara kursi dan meja di ruangan yang sama.                                                            | 6.4      | Terpenuhi          |
| AC-06 | Membuka katalog Kursi hanya menampilkan unit kursi.                                                                             | 8.2      | Terpenuhi          |
| AC-07 | Pencarian `F1-03-15` pada detail Kursi menampilkan unit yang cocok bila terdaftar sebagai kursi dan berada dalam cakupan akses. | 8.3, 3.1 | Terpenuhi          |
| AC-08 | Filter gedung, lantai, dan ruangan menghasilkan data sesuai lokasi aktif unit.                                                  | 8.4      | Terpenuhi          |
| AC-09 | Jumlah unit katalog konsisten dengan unit inventaris sesuai aturan status dan cakupan.                                          | 5.5      | Terpenuhi          |
| AC-10 | Pencarian, filter, URL langsung, API, dan ekspor tidak dapat membaca data di luar cakupan role.                                 | 3.1, 8.3 | Terpenuhi          |
| AC-11 | Perubahan lokasi mengikuti kebijakan kode terdokumentasi dan menjaga keterlacakan riwayat.                                      | 7        | Terpenuhi          |
| AC-12 | PRD, ERD, spesifikasi fitur, dan acceptance criteria tidak saling bertentangan.                                                 | Semua    | Terpenuhi          |
| AC-13 | Kode lokasi dan kode inventaris diturunkan dari master gedung; pengguna tidak dapat mengetik bebas huruf gedung.              | 6.7      | Terpenuhi          |
| AC-14 | Kode gedung tidak dapat diubah ketika gedung tersebut sudah memiliki unit inventaris.                                          | 6.7      | Terpenuhi          |
| AC-15 | Peminjaman yang sudah disetujui dapat diserahterimakan; peminjaman yang belum disetujui ditolak dengan pesan yang jelas.        | 9.2      | Terpenuhi          |
| AC-16 | Satu persetujuan sah Wakasek cukup menutup rantai persetujuan; role lain tetap berjenjang.                                       | 9.1      | Terpenuhi          |
| AC-17 | Serah terima ganda atas peminjaman yang sama ditolak.                                                                          | 9.2      | Terpenuhi          |
| AC-18 | Serah terima mencatat unit fisik yang diserahkan; jumlah unit terpilih harus sama dengan jumlah yang dipinjam.                | 9.2      | Terpenuhi          |
| AC-19 | Tanda tangan digital tidak wajib; dokumen dapat ditandatangani secara fisik.                                                  | 9.3      | Terpenuhi          |
| AC-20 | Status dokumen terpisah dari status transaksi dan tidak saling mengubah.                                                       | 9.3      | Terpenuhi          |

## 12. Asumsi dan keputusan yang perlu dikonfirmasi

Asumsi yang dipakai (aman dan konsisten dengan kebutuhan):

1. Satu sekolah, banyak gedung/jurusan; tidak multi-tenant.
2. Huruf gedung unik per sekolah (A–Z) dan berasal dari **master gedung** (`buildings`), bukan string bebas pada formulir ruangan. Normalisasi data lama dibahas di `DATABASE.md` §6.
3. Lantai yang dapat dikodekan adalah 1–9. Lantai 10 ke atas memerlukan perubahan format.
4. Kapasitas 99 nomor unit per ruangan seumur hidup (termasuk kode pensiun).
5. Kode inventaris diterbitkan ulang saat mutasi; nomor tidak pernah dipakai ulang.
6. Laboratorium didefinisikan oleh `rooms.is_lab`; cakupan Laboran/K3 ditetapkan per ruangan melalui `user_room_scopes`.
7. Hanya unit `AKTIF` + `BAIK` yang dapat dipinjam.
8. Barang bernilai tinggi ditandai manual (`is_high_value`) dan wajib approval dua level.
9. Wakasek Sarpras adalah pengelola penuh inventaris sekolah: pengajuan miliknya sendiri disetujui atas kewenangan, dan satu persetujuannya pada level akhir sudah cukup.

Keputusan bisnis yang **masih perlu konfirmasi** (tidak menghambat revisi dokumen lain):

- **Daftar gedung resmi sekolah** (nama + satu huruf) dan nomor ruangan per lantai. Data demo memakai pemetaan `G1`→G, `G2`→H, `G3`→I yang **wajib dikonfirmasi sebelum migrasi data produksi** (lihat `KNOWN_ISSUES.md` butir 5).
- Kebutuhan lebih dari 99 unit per ruangan.
- Apakah kode pensiun boleh dipakai ulang setelah unit dihapus permanen (asumsi saat ini: **tidak boleh**).
- Apakah Ketua Laboran boleh melihat katalog laboratorium lintas jurusan untuk keperluan peminjaman saja, sementara laporan tetap dibatasi (asumsi saat ini: ya, seluruh lab).
- Penetapan daftar ruangan laboratorium dan penugasan Laboran/K3.
- Apakah diperlukan tanda tangan elektronik tersertifikasi (asumsi saat ini: **tidak**; TTD digital berupa gambar dan opsional).

## 13. Indikator keberhasilan

- Seluruh alur inti lulus pengujian otomatis dan verifikasi browser.
- Tidak ada akses lintas laboratorium/IDOR pada halaman sensitif, termasuk pencarian kode dan ekspor.
- Tidak ada kode inventaris ganda; setiap unit dapat ditelusuri ke lokasi dan riwayatnya.
- Jumlah unit katalog selalu sama dengan hasil hitung unit (tidak ada selisih manual).
- Pengajuan yang ditolak/dibatalkan tidak memblokir ketersediaan.
- Pengembalian parsial tidak menimpa jumlah sebelumnya.
- Setiap serah terima mencatat unit fisik yang benar-benar diserahkan.
- File bukti/BA/BAST hanya dapat diakses pengguna berwenang.
- Halaman katalog/dashboard < 1 detik dan pencarian < 0,5 detik pada lingkungan uji (hasil aktual di `TESTING.md`).

## 14. Referensi

- `DATABASE.md` — kamus tabel, ERD, constraint, rencana migrasi.
- `DECISIONS.md` — rasional keputusan arsitektur, termasuk kebijakan kode dan mutasi.
- `KNOWN_ISSUES.md` — selisih antara spesifikasi dan implementasi saat ini.
- `PROJECT_STATUS.md` — status implementasi per modul.
- `USER_GUIDE.md` — panduan pengguna (bagian bertanda ⚠ baru berlaku setelah implementasi).
- `TESTING.md` — hasil pengujian dan rencana pengujian revisi.
