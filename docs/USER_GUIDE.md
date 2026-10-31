# Panduan Pengguna

> **Catatan versi (26 Oktober 2026 — revisi V2).** Aplikasi memakai model katalog + unit fisik dan kode `G1-02-12`. Role resmi: **Wakasek Sarpras**, **Ketua Laboran**, **Laboran/K3**, dan **Peminjam**. Role lama `ADMIN_SARPRAS` sudah dimigrasikan menjadi `KETUA_LABORAN`.

## 1. Masuk & navigasi

1. Buka alamat aplikasi, masukkan **email** dan **password**.
2. Menu desktop ada di sidebar kiri; di ponsel gunakan bottom navigation: **Beranda, Pinjam, Riwayat/Persetujuan, Lainnya**. Akun peminjam memilih katalog langsung di halaman **Peminjaman**; role pengelola tetap memiliki menu **Katalog**.
3. Ikon bulan/matahari di kanan atas mengganti tema terang/gelap. Pilihan tersimpan di perangkat dan mengikuti preferensi sistem bila belum dipilih.

## 2. Matriks akses

| Fitur                                   | Super Admin | Ketua Laboran |       Laboran/K3       |               Peminjam               |
| --------------------------------------- | :---------: | :-----------: | :--------------------: | :----------------------------------: |
| Dashboard                               |   ✔ semua   |    ✔ semua    |       ✔ jurusan        |              ✔ pribadi               |
| Katalog & detail barang                 |      ✔      |       ✔       |           ✔            | ✔ terintegrasi di halaman peminjaman |
| Ajukan peminjaman                       |      ✔      |       ✔       |           ✔            |                  ✔                   |
| Setujui pinjaman level 1                |      ✔      |  ✔ semua lab  | ✔ lab sendiri/otomatis |                  —                   |
| Setujui pinjaman level 2 (nilai tinggi) |      ✔      |       —       |           —            |                  —                   |
| Serah terima & pengembalian             |      ✔      |       ✔       |       ✔ jurusan        |                  —                   |
| Pengadaan (ajukan)                      |      ✔      |       ✔       |           —            |                  —                   |
| Approval pengadaan                      |      ✔      |       —       |           —            |                  —                   |
| Mutasi lokasi                           |      ✔      |       ✔       |           —            |                  —                   |
| Penghapusan (ajukan)                    |      ✔      |       ✔       |           —            |                  —                   |
| Approval penghapusan                    |      ✔      |       —       |           —            |                  —                   |
| Cetak label                             |      ✔      |       ✔       |           ✔            |                  —                   |
| Scan QR (hasil di halaman scan)         |      ✔      |       ✔       |           ✔            |                  ✔                   |
| Laporan & ekspor                        |      ✔      |       ✔       |           —            |                  —                   |
| Master data                             |      ✔      |       ✔       |           —            |                  —                   |
| Pengguna & pengaturan instansi          |      ✔      |       —       |           —            |                  —                   |

Pemetaan istilah: **Super Admin** = `SUPER_ADMIN` (Wakasek Sarpras), **Ketua Laboran** = `KETUA_LABORAN`, **Laboran/K3** = `LABORAN`, **Peminjam** = `PEMINJAM`. Kategori peminjam (siswa/guru/staf) disimpan di `users.borrower_type`, bukan sebagai role tambahan.

**⚠ 2.1 — cakupan katalog:** Super Admin melihat semua unit (termasuk ruang umum). Ketua Laboran hanya melihat unit di ruangan laboratorium pada semua jurusan. Laboran/K3 hanya melihat unit pada laboratorium yang ditetapkan untuk akunnya. Batas ini berlaku juga untuk pencarian kode, filter, URL langsung, dan ekspor.

## 3. Katalog & detail unit (⚠ revisi 31 Oktober 2026)

Katalog kini bertingkat tiga: **katalog (jenis barang) → spesifikasi (merek + tipe/model) → unit inventaris**.

### Tingkat 1 — daftar katalog

1. Buka menu **Katalog**.
2. Setiap kartu mewakili **jenis barang** (mis. Kursi Umum), bukan satu barang fisik maupun satu merek.
3. Angka jumlah unit adalah agregasi seluruh spesifikasi dan unit dalam cakupan akses Anda.
4. Gunakan pencarian, filter (kategori/gedung/lantai/ruangan/ketersediaan), dan pagination.

### Tingkat 2 — daftar spesifikasi

1. Klik nama katalog, mis. **Kursi Umum**.
2. Halaman ini menampilkan seluruh **merek + tipe/model** pada katalog tersebut beserta jumlah unit, unit tersedia, dan unit rusak.
3. Gunakan pencarian (merek/tipe model) serta filter merek, tipe/model, kondisi, status, gedung, dan ruangan secara bersamaan. Filter semua/Reset menghapus seluruh pilihan.
4. Klik **Lihat unit** pada satu spesifikasi untuk masuk ke tingkat 3.

### Tingkat 3 — daftar unit inventaris

1. Halaman ini menampilkan **setiap barang fisik** pada satu spesifikasi, lengkap dengan kode inventaris, gedung, lantai, ruangan, kondisi, status, dan tahun pengadaan.
2. Search mencakup kode inventaris dan nama barang; filter kondisi, status, gedung, lantai, ruangan, dan tahun pengadaan dapat digabung; data besar dipaginasi.
3. Klik **Detail** untuk membuka rincian unit: kode aktif, kode lama, perolehan, foto, catatan, riwayat pemeliharaan, dan riwayat perpindahan (sesuai hak akses).
4. Gunakan tombol **Cetak Label** pada detail unit untuk stiker satuan, atau tombol **Cetak Label Unit** di halaman katalog untuk mencetak massal unit katalog tersebut (termasuk "pilih semua sesuai filter").

### Mencari dengan kode inventaris

Format kode: `G1-02-12` = Gedung G, lantai 1, Ruang 02, unit nomor 12.

| Yang diketik | Hasil                                                          |
| ------------ | -------------------------------------------------------------- |
| `G1-02-12`   | Unit tersebut (harus persis).                                  |
| `G1-02`      | Semua unit di Ruang 02, Gedung G lantai 1, pada katalog aktif. |
| `G1`         | Semua unit di Gedung G lantai 1 pada katalog aktif.            |
| `G1-02-1`    | Unit 10–19 di ruangan tersebut (prefix).                       |
| `g1-02-12`   | Sama dengan `G1-02-12` (huruf kecil otomatis dikapitalkan).    |
| `G1/02/12`   | Ditolak dengan pesan format.                                   |

- Pencarian kode di halaman detail katalog **tidak** menampilkan unit dari jenis barang lain.
- Kode di luar cakupan Anda akan tampil seperti "tidak ditemukan" (bukan pesan "tidak berhak").
- Gunakan **Reset** untuk menghapus semua filter dan kata kunci.

### Kode lama setelah barang dipindah (⚠ 2.1)

Saat unit dipindahkan ke ruangan lain, sistem menerbitkan **kode baru** dan menyimpan kode lama sebagai riwayat. Ketentuan:

- Nomor unit **tidak** kembali ke `01` dan tidak dipakai ulang oleh barang lain.
- Label lama harus segera dicetak ulang. Sistem menandai unit dalam daftar **label perlu dicetak ulang**.
- Memindai/mencari kode lama menampilkan unit yang sama beserta keterangan "kode lama, lokasi sekarang …".
- Unit yang pindah lalu kembali ke ruangan asal tetap mendapat nomor baru.

## 4. Peminjam

### Mengajukan peminjaman

1. Buka **Peminjaman**. Katalog yang boleh dipinjam langsung tampil (tanpa menu katalog terpisah).
2. Cari berdasarkan nama/spesifikasi/ID/kode unit dan filter kategori, gedung, ruangan, atau ketersediaan.
3. Klik **Tambah** pada katalog. Katalog yang sudah dipilih tidak dapat ditambahkan lagi; ubah jumlahnya di keranjang. Hapus dari keranjang bila ingin memilih ulang.
4. Isi tujuan, tanggal/waktu pinjam dan kembali, serta kegiatan (opsional). Kalender menandai tanggal yang sudah dibooking.
5. **Kirim Request Peminjaman**. Barang bernilai tinggi tetap memerlukan persetujuan Wakasek; pengajuan Laboran/K3 dari laboratorium sendiri untuk barang biasa disetujui otomatis.

Status: **Menunggu Persetujuan → Disetujui → Sedang Dipinjam → Selesai**. Pengajuan dapat **dibatalkan** selama barang belum diserahkan. Penolakan menyertakan alasan.

### Melihat riwayat

Menu **Peminjaman Saya** menampilkan semua pengajuan beserta status. Klik **Detail** untuk melihat barang, approval, dan mengunduh BAST/surat peminjaman bila tersedia.

## 5. Laboran / Ketua Laboran

### Menyetujui pinjaman

1. Buka **Persetujuan Pinjam**.
2. Periksa barang, tujuan, dan jadwal.
3. Untuk level 1: isi catatan (opsional) dan klik **Setujui**, atau isi alasan lalu **Tolak Peminjaman**.
4. Barang bernilai tinggi dilanjutkan ke Wakasek. Tanda tangan digital **opsional**; dokumen dapat dicetak dan ditandatangani fisik.

Laboran/K3 hanya dapat memproses pinjaman yang seluruh barangnya berada pada laboratorium yang ditugaskan (bukan seluruh jurusan). Ketua Laboran hanya dapat memproses pinjaman yang seluruh barangnya berada di laboratorium, lintas jurusan.

**Persetujuan otomatis:** bila Laboran/K3 mengajukan barang biasa dari laboratoriumnya sendiri, sistem langsung menyetujui dengan catatan "Persetujuan otomatis". Barang bernilai tinggi tetap menunggu Wakasek, dan pengajuan yang mencampur laboratorium lain/inventaris umum tidak disetujui otomatis.

### Serah terima

Setelah disetujui, buka detail pinjaman → **Serahkan Barang**. Status berubah menjadi **Sedang Dipinjam** dan petugas/waktu tercatat. Jangan menyerahkan barang sebelum tombol ini ditekan agar audit benar.

Saat serah terima, pilih unit fisik yang sesungguhnya diserahkan. Jumlah yang wajib dipilih sama dengan jumlah yang dipinjam pada baris tersebut; setelah batas terpenuhi, unit tambahan otomatis dinonaktifkan. Hapus centang untuk mengganti pilihan sebelum menyimpan. Unit yang dipilih dicatat pada `loan_item_units` (kondisi keluar, `opened_at`), ditandai `DIPINJAM`, dan muncul di detail pinjaman. Server menolak unit di luar transaksi, unit ganda, jumlah berlebih, dan pengiriman ulang.

- **Pilih Semua Unit yang Dapat Diserahkan** memilih unit yang memenuhi syarat untuk seluruh barang pada peminjaman, tetapi berhenti pada jumlah yang dipinjam per baris. **Batal Pilih Semua** mengosongkan pilihan. Jumlah terpilih dan sisa kapasitas tampil di atas daftar.
- **Cari unit** berdasarkan nama katalog, spesifikasi/merek, kode inventaris, gedung, atau ruangan. Filter kategori, katalog, spesifikasi, gedung, dan ruangan dapat digabungkan. Pilihan yang sudah dibuat tidak hilang saat filter berubah.
- **Scan QR Unit** memasukkan unit langsung ke daftar pilihan: petugas memindai stiker atau mengetik kode inventaris. Pesan akan menjelaskan bila unit sudah dipilih, sudah diserahkan, bukan bagian peminjaman ini, atau tidak memenuhi syarat. Scan tidak menyelesaikan serah terima; tombol simpan tetap harus ditekan. Jika kamera ditolak/tidak tersedia, gunakan input kode manual.

> **Catatan:** bila katalog belum memiliki unit fisik (data lama yang belum dimigrasikan), serah terima tetap dapat dilakukan hanya dengan mengklik **Serahkan Barang**, tanpa memilih unit.

### Pengembalian

1. Buka **Pengembalian** → pilih pinjaman → **Proses Pengembalian**.
2. Pada pinjaman dengan **unit fisik**, setiap unit tampil terpisah beserta kode inventaris dan lokasinya. Centang unit yang sedang diperiksa, lalu isi kondisi akhir, catatan, dan ruangan tujuan untuk unit tersebut. Gunakan **Cari unit**, filter status validasi/kondisi/status pengembalian/ruangan asal, atau **Scan QR untuk Menemukan Unit** untuk menyorot baris yang tepat. Scan hanya memilih unit; konfirmasi tetap lewat tombol **Konfirmasi unit ini** atau **Konfirmasi unit terpilih**.
3. **Centang saja tidak mengonfirmasi pengembalian.** Tekan **Konfirmasi unit ini** untuk satu unit, atau **Konfirmasi unit terpilih** untuk beberapa unit sekaligus. Setiap unit dapat memiliki kondisi dan catatan yang berbeda.
4. Jika kondisi **Rusak Ringan/Berat**, unggah **foto bukti** (wajib) pada unit tersebut.
5. Untuk kelompok katalog sejenis dengan **minimal 11 unit**, tombol **Validasi Semua** tersedia. Sistem menampilkan konfirmasi berisi jumlah unit dan nama kelompok sebelum memproses; hanya unit yang belum dikonfirmasi pada kelompok itu yang diproses, unit dari katalog lain tidak ikut.
6. Jika ruangan tujuan berbeda dari asal, konfirmasi peringatan — sistem akan mencatat mutasi unit dan menerbitkan kode baru (⚠ 2.1).
7. Tombol **Selesaikan Pengembalian** baru aktif setelah seluruh unit dikonfirmasi. Status pinjaman berubah menjadi **Selesai** hanya setelah tombol ini ditekan; backend memeriksa ulang seluruh unit sebelum menyelesaikan.
8. Untuk pinjaman **tanpa unit fisik (data lama)**, jumlah kembali, kondisi, dan ruangan diisi per baris katalog. Pengembalian dapat dilakukan bertahap dan jumlahnya akumulatif. Jika jumlah kembali kurang, selisih dicatat sebagai **Hilang sementara** dan pinjaman tetap aktif sampai barang ditemukan/dikembalikan atau ditutup oleh Wakasek.

### Rincian barang pada detail peminjaman

Bagian **Rincian Barang** menampilkan satu kartu per unit fisik: kode inventaris, katalog, spesifikasi/merek, lokasi, kondisi, tanggal pemeriksaan, status validasi, dan catatan. Kartu yang memiliki **foto bukti kerusakan** dapat diklik untuk membuka popup berisi foto, kode unit, nama/spesifikasi, tanggal, catatan, dan nama petugas. Kartu tanpa foto tidak menampilkan tombol foto. Gunakan pencarian dan filter (kategori, katalog, spesifikasi, gedung, ruangan, kondisi, status inventaris, status validasi, rentang tanggal pemeriksaan, ada/tidak foto) lalu **Terapkan filter**; **Reset filter** mengembalikan seluruh daftar. Foto hanya dapat dibuka petugas yang berwenang dan selalu terikat pada unit yang diperiksa.

### Scan QR

Tombol kamera mengambang / menu **Scan QR**. Arahkan kamera ke stiker. Jika kamera tidak tersedia, masukkan **kode inventaris** secara manual. Hasil tampil langsung di halaman scan (tanpa pindah ke katalog); data unit selalu diverifikasi ulang ke database dan hak ubah tetap diperiksa server.

## 6. Ketua Laboran (pengelola inventaris)

- **Pengadaan Barang**: isi data katalog dan jumlah unit. Jika dibuat oleh Ketua Laboran, status pengadaan `PENDING` dan menunggu Wakasek; jika oleh Wakasek, langsung disetujui.
- **Mutasi Lokasi** (⚠ 2.1): pilih unit dan ruangan tujuan. Kode baru diterbitkan, kode lama disimpan sebagai riwayat, dan label wajib dicetak ulang.
- **Inventaris Keluar**: ajukan penghapusan per unit dengan alasan dan **Berita Acara** (PDF/gambar). Status unit baru berubah setelah disetujui Wakasek. Unit dengan riwayat tidak dihapus permanen.
- **Master Data**: tambah/ubah/hapus jurusan, **gedung**, ruangan, kategori. Penghapusan ditolak jika masih dipakai. Lima jurusan awal: RPL, DKV, KLN, TP, TKP.
- **Hapus/Nonaktifkan Katalog**: di detail katalog tersedia tindakan dengan konfirmasi. Katalog tanpa unit/transaksi dihapus permanen; katalog berunit atau bertransaksi diarsipkan (`archived_at`) dan tidak lagi ditawarkan untuk peminjaman baru.
- **Label**: pilih satu/beberapa unit → **Cetak Massal**. Label satuan dan re-print ada di detail unit.
- **Laporan**: pilih periode, cakupan (sekolah/kategori/ruangan/jurusan), lalu **PDF** atau **Excel**. **Simpan ke Arsip** membuat snapshot yang dapat diunduh kembali. Laporan selalu dibatasi cakupan laboratorium Anda.

### 6.1 Master gedung (⚠ revisi terbaru)

Master gedung memisahkan identitas gedung dari ruangan.

- Halaman **Master Data** kini memiliki bagian **Gedung** terpisah.
- Tiap gedung punya **nama** dan **kode satu huruf** (A–Z). Kode inilah yang dipakai generator kode lokasi dan kode inventaris.
- Setiap ruangan terhubung ke **tepat satu** master gedung.
- Formulir ruangan hanya meminta **gedung** (pilih dari dropdown master), **lantai**, dan **nomor ruangan**; nama ruangan dan kode diturunkan sistem secara otomatis.
- Status gedung (aktif/nonaktif) mengontrol apakah gedung dapat dipakai untuk lokasi baru.
- Gedung yang sudah terpakai unit/fisik tidak boleh dihapus; kode gedung yang sudah dipakai unit tidak boleh diubah kecuali setelah unit dipindah.

⚠ **Catatan:** huruf gedung yang muncul di filter (termasuk pilihan tanpa nama seperti "Gedung G") berasal dari data yang sudah ada. Sekolah harus menetapkan nama gedung resmi di master agar pilihan tersebut memiliki nama yang tepat.

### 6.2 Tombol baru pada detail pinjaman

- **Serah Terima Unit** (rekomendasi): membuka form pilihan unit. Isi jumlah unit yang diserahkan per barang.
- **Serah Terima Tanpa Unit** (opsional, untuk data lama): melanjutkan serah terima langsung tanpa memilih unit.
- **Cetak Dokumen**: mencetak surat peminjaman/BAST (sesuai jenis pinjaman).
- **Tandai Dokumen**: mencatat status dokumen (`Belum dibuat → Siap dicetak → Sudah dicetak → Ditandatangani (fisik/digital)`).

Persetujuan level 2 (barang bernilai tinggi) hanya melibatkan Wakasek. Wakasek dapat menyetujui langsung tanpa menunggu Admin lain; satu persetujuan Wakasek sudah cukup untuk menyelesaikan rangkaian persetujuan. Ketua Laboran tidak memperoleh akses level 2.

**Tanda tangan digital bersifat opsional.** Dokumen dapat dicetak dan ditandatangani fisik tanpa menggambar tanda tangan digital. Status dokumen hanya mencatat apakah dokumen sudah dibuat, dicetak, atau ditandatangani — tidak memengaruhi kelayakan transaksi.

## 7. Super Admin (Wakasek Sarpras)

Selain fitur Ketua Laboran:

- **Approval Pengadaan** dan **Approval Penghapusan**.
- Approval level 2 pinjaman bernilai tinggi (wajib tanda tangan).
- Menutup pinjaman dengan selisih: buka detail pinjaman → **Tutup Pinjaman**. Selisih menjadi **Hilang permanen**; riwayat peminjam terakhir tetap tersimpan.
- **Pengguna**: buat/ubah akun, tetapkan role, kategori peminjam, jurusan, dan cakupan laboratorium untuk Laboran/K3. Tidak dapat menghapus akun sendiri atau akun yang punya riwayat pinjaman.
- **Pengaturan Instansi**: nama sekolah, kode lokasi, alamat, kepala sekolah, logo. Berlaku untuk label, BAST, dan laporan.
- Akses penuh ke seluruh unit, termasuk ruang umum dan gudang.

## 8. Status & warna

| Status          | Warna        | Arti                                                   |
| --------------- | ------------ | ------------------------------------------------------ |
| Baik            | Hijau        | Kondisi fisik baik.                                    |
| Rusak Ringan    | Kuning       | Perlu perbaikan ringan; belum dapat dipinjam.          |
| Rusak Berat     | Oranye/Merah | Tidak dapat dipinjam.                                  |
| Hilang          | Merah        | Unit hilang permanen.                                  |
| Sedang Dipinjam | Biru         | Unit berada di peminjam.                               |
| Dihapus         | Abu-abu      | Unit dihapus dari inventaris aktif; riwayat tetap ada. |

Status selalu disertai teks, tidak hanya warna. Angka **Tersedia** dihitung dari unit aktif berkondisi Baik yang tidak sedang dipinjam atau dibooking.

> Pada model 2.1, **kondisi** (`BAIK`, `RUSAK_RINGAN`, `RUSAK_BERAT`) dan **status** (`AKTIF`, `DIPINJAM`, `HILANG`, `DIHAPUS`) adalah dua atribut terpisah. Tabel di atas adalah tampilan gabungan agar mudah dibaca.

## 9. Notifikasi

Ikon lonceng menampilkan notifikasi: pengajuan baru, persetujuan/penolakan, kebutuhan approval level 2, pengingat jatuh tempo, keterlambatan, kerusakan, dan selisih pengembalian. Klik notifikasi untuk membuka tautan terkait; **Tandai semua dibaca** tersedia di halaman notifikasi.

Notifikasi dihasilkan oleh queue worker. Jika tidak muncul, pastikan worker berjalan (lihat `INSTALLATION.md`).

## 10. Catatan penting tentang unit fisik (⚠ revisi terbaru)

Aplikasi kini mendukung dua cara serah terima, tergantung kesiapan data:

- **Serah terima dengan unit fisik (direkomendasikan):** petugas memilih/memindai unit fisik yang diserahkan. Unit akan dicatat pada pinjaman, statusnya menjadi `DIPINJAM`, dan kondisi keluar tersimpan. Saat pengembalian, setiap unit dikonfirmasi terpisah; kondisi masuk, catatan pemeriksaan, petugas konfirmasi, dan waktu konfirmasi tercatat. Unit kembali ke `AKTIF` dengan kondisi hasil pemeriksaan.
- **Validasi massal** hanya muncul untuk kelompok sejenis dengan minimal 11 unit (termasuk unit yang sudah dikonfirmasi) dan tidak pernah mencampur katalog. Status pemeriksaan (`BELUM_DIPERIKSA`, `MENUNGGU_KONFIRMASI`, `SUDAH_DIKONFIRMASI`, `PERLU_TINDAK_LANJUT`) terpisah dari kondisi fisik barang.
- **Serah terima tanpa unit fisik (data lama):** bila katalog belum memiliki unit fisik, serah terima tetap dapat dilakukan dengan langsung menekan **Serahkan Barang**. Penugasan unit tidak wajib; pembukuan masih memakai jumlah katalog.

Aturan penugasan unit:

- Saat menugaskan unit, jumlah unit yang dipilih per barang **harus sama** dengan jumlah yang dipinjam (kecuali katalog tanpa unit fisik).
- Unit yang ditugaskan harus aktif, dalam kondisi baik, dan belum dipinjam orang lain.
- Unit yang sudah pernah dikembalikan pada pinjaman yang sama tidak boleh ditugaskan ulang.
- Jika penugasan tidak lengkap atau unit tidak valid, sistem menolak serah terima dengan pesan yang menjelaskan masalahnya.

Status dokumen peminjaman **terpisah** dari status transaksi:

- Dokumen dapat dicetak setelah persetujuan lengkap, tanpa mengubah status pinjaman.
- Tanda tangan fisik/digital hanya mencatat status dokumen, bukan persetujuan transaksi.
- Dokumen yang belum dibuat tidak memblokir serah terima.

## 11. Tips

- Gunakan kode inventaris untuk menemukan unit cepat; gunakan nama katalog bila belum tahu kodenya.
- Cetak ulang label segera setelah mutasi; waktu cetak terakhir tercatat.
- Jangan menutup pinjaman dengan selisih sebelum barang benar-benar dicari.
- Unduh BAST setelah approval lengkap agar memuat tanda tangan terbaru.
- Di ponsel, gunakan mode gelap saat kondisi ruangan redup.

## 12. Fitur baru revisi V2

### Registrasi mandiri

1. Buka **Daftar** dari halaman login.
2. Pilih kategori **Siswa / Guru / Staf**, isi nama lengkap (guru dapat menambahkan gelar), email **@gmail.com**, dan password.
3. Siswa wajib mengisi kelas; jurusan opsional.
4. Setelah mendaftar, akun langsung masuk dengan role **Peminjam** (hak minimum). Role khusus hanya diberikan administrator melalui menu Pengguna.

### Scan inventaris

1. Buka menu **Scan** (tersedia untuk semua pengguna yang sudah login).
2. Aktifkan kamera lalu arahkan ke QR, **atau** ketik kode unit (mis. `G1-02-12`) dan tekan Enter/Cari.
3. Hasil muncul di halaman yang sama: nama, spesifikasi, kode, gedung/lantai/ruangan, nomor unit, kondisi, dan status.
4. Bila unit di luar cakupan akses, aplikasi hanya menampilkan pesan terbatas — bukan detail internal.

### Pengadaan banyak lokasi dengan merek & tipe/model

1. Buka **Pengadaan**. Pilih **katalog yang sudah ada** bila hanya menambah stok, atau isi nama katalog baru + kategori.
2. Isi **merek/brand** dan **tipe/model**. Kosongkan bila memang tidak ada; sistem menyimpan `Tanpa Merek` / `Tidak Ditentukan` agar data tidak kosong.
3. Isi jumlah (mis. 40 kursi) lalu tambahkan baris lokasi (gedung → lantai → ruangan) dan jumlahnya (mis. 20 + 15 + 5).
4. Sisa unit terlihat langsung; tombol simpan aktif hanya saat sisa 0. Kode unit dibuat otomatis setelah pengadaan disetujui.
5. Merek/tipe yang berbeda pada katalog yang sama **tidak** membuat katalog baru — keduanya menjadi spesifikasi terpisah.

### Distribusi berdasarkan merek & tipe/model

1. Buka **Distribusi** (Ketua Laboran dan Wakasek/Super Admin).
2. Pilih **katalog → spesifikasi (merek + tipe/model) → jumlah → lokasi tujuan**; panel kanan menampilkan stok setiap spesifikasi secara terpisah.
3. Ringkasan sebelum simpan menampilkan stok spesifikasi, jumlah distribusi, tujuan, dan sisa stok.
4. Distribusi melebihi stok spesifikasi ditolak. Unit yang sudah didistribusikan atau sedang dipinjam tidak dapat dipakai lagi; stok merek lain tidak ikut terpakai.
5. Setiap unit mendapat kode baru dan riwayat distribusi terhubung hingga kode inventarisnya (lihat riwayat pada halaman distribusi atau detail unit).

### Mutasi banyak unit

1. Buka **Mutasi**, cari/filter unit (kode, katalog, gedung, lantai, ruangan, kondisi, status).
2. Centang beberapa unit, pilih ruangan tujuan, lalu simpan.
3. Unit yang sedang dipinjam atau di luar cakupan tidak dapat dipilih. Setiap unit mendapat kode baru dan riwayat lokasi; cetak ulang labelnya.

### Rekap kegiatan

- Guru dan staf dapat membuat kegiatan (nama, penanggung jawab, deskripsi, tanggal) dan memperbarui status **Belum Berjalan / Sedang Berjalan / Sudah Berjalan**.
- Siswa hanya melihat daftar; status kegiatan tidak mengubah status persetujuan peminjaman.

### Laporan

- **Impor** (pengelola — Wakasek/Ketua Laboran): unggah CSV/XLSX berisi kode inventaris, periksa pratinjau, lalu konfirmasi. Impor tidak menghapus data lain dan bukan pengganti backup database.
- **Hapus arsip**: tombol Hapus pada tabel arsip menghapus file dan menyembunyikan arsip (soft delete), tanpa menghapus unit/inventaris sumber. Super Admin dapat menghapus arsip mana pun.

### Surat peminjaman

- Buka detail pinjaman → **Pratinjau Surat** → periksa → **Cetak** (dialog browser). Unduh PDF tetap tersedia.
- Surat biasa tidak memuat bagian kegiatan; surat yang terhubung kegiatan menampilkannya. Tanggal dicetak tanpa jam.
