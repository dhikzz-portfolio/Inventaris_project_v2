# Panduan Pengguna

> **Catatan versi.** Aplikasi yang berjalan saat ini masih memakai model barang per kelompok (batch). Bagian yang ditandai **⚠ 2.1** menjelaskan perilaku katalog + unit fisik dan kode `G1-02-12` yang **baru berlaku setelah implementasi revisi 2.1**. Lihat `PROJECT_STATUS.md` dan `KNOWN_ISSUES.md`.

## 1. Masuk & navigasi

1. Buka alamat aplikasi, masukkan **username** dan **password** (bukan email).
2. Menu desktop ada di sidebar kiri; di ponsel gunakan bottom navigation: **Beranda, Katalog, Pinjam, Riwayat/Persetujuan, Lainnya**.
3. Ikon bulan/matahari di kanan atas mengganti tema terang/gelap. Pilihan tersimpan di perangkat dan mengikuti preferensi sistem bila belum dipilih.

## 2. Matriks akses

| Fitur                                   | Super Admin | Ketua Laboran | Laboran/K3 | Peminjam  |
| --------------------------------------- | :---------: | :-----------: | :--------: | :-------: |
| Dashboard                               |   ✔ semua   |    ✔ semua    | ✔ jurusan  | ✔ pribadi |
| Katalog & detail barang                 |      ✔      |       ✔       |     ✔      |     ✔     |
| Ajukan peminjaman                       |      ✔      |       ✔       |     ✔      |     ✔     |
| Setujui pinjaman level 1                |      ✔      |       ✔       | ✔ jurusan  |     —     |
| Setujui pinjaman level 2 (nilai tinggi) |      ✔      |       —       |     —      |     —     |
| Serah terima & pengembalian             |      ✔      |       ✔       | ✔ jurusan  |     —     |
| Pengadaan (ajukan)                      |      ✔      |       ✔       |     —      |     —     |
| Approval pengadaan                      |      ✔      |       —       |     —      |     —     |
| Mutasi lokasi                           |      ✔      |       ✔       |     —      |     —     |
| Penghapusan (ajukan)                    |      ✔      |       ✔       |     —      |     —     |
| Approval penghapusan                    |      ✔      |       —       |     —      |     —     |
| Label & scan QR                         |      ✔      |       ✔       |     ✔      |     —     |
| Laporan & ekspor                        |      ✔      |       ✔       |     —      |     —     |
| Master data                             |      ✔      |       ✔       |     —      |     —     |
| Pengguna & pengaturan instansi          |      ✔      |       —       |     —      |     —     |

Pemetaan istilah: **Super Admin** = `SUPER_ADMIN` (Wakasek Sarpras), **Ketua Laboran** = `ADMIN_SARPRAS`, **Laboran/K3** = `LABORAN`, **Peminjam** = `PEMINJAM`.

**⚠ 2.1 — cakupan katalog:** Super Admin melihat semua unit (termasuk ruang umum). Ketua Laboran hanya melihat unit di ruangan laboratorium pada semua jurusan. Laboran/K3 hanya melihat unit pada laboratorium yang ditetapkan untuk akunnya. Batas ini berlaku juga untuk pencarian kode, filter, URL langsung, dan ekspor.

## 3. Katalog & detail unit (⚠ 2.1)

### Melihat katalog

1. Buka menu **Katalog**.
2. Setiap baris mewakili **jenis barang** (mis. Kursi), bukan satu barang fisik.
3. Angka **Jumlah Unit** adalah jumlah unit aktif yang dihitung sistem dan dibatasi cakupan akses Anda.
4. Gunakan pencarian, filter (kategori/gedung/lantai/ruangan/ketersediaan), dan pagination. Filter tetap aktif saat berpindah halaman.

### Membuka detail unit

1. Klik nama katalog, mis. **Kursi**.
2. Halaman detail menampilkan **hanya unit kursi**, lengkap dengan kode inventaris, lokasi, kondisi, dan status.
3. Klik salah satu baris untuk melihat detail unit: kode aktif, kode lama, perolehan, foto, catatan, riwayat pemeliharaan, dan riwayat perpindahan (sesuai hak akses).
4. Gunakan tombol **Cetak Label** pada detail unit untuk stiker satuan, atau tombol **Cetak Label Unit** di halaman katalog untuk memilih/mencetak massal unit katalog tersebut (termasuk "pilih semua sesuai filter").

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

1. **Ajukan Peminjaman** → cari barang (minimal 2 huruf) → **Tambah**.
2. Ubah jumlah di keranjang. Sistem menampilkan ketersediaan pada rentang tanggal yang dipilih.
3. Isi tujuan, tanggal/waktu pinjam dan kembali, serta kegiatan (opsional).
4. Kalender menandai tanggal yang sudah dibooking. Barang bernilai tinggi memerlukan dua persetujuan.
5. **Kirim Request Peminjaman**.

Status: **Menunggu Persetujuan → Disetujui → Sedang Dipinjam → Selesai**. Pengajuan dapat **dibatalkan** selama barang belum diserahkan. Penolakan menyertakan alasan.

### Melihat riwayat

Menu **Peminjaman Saya** menampilkan semua pengajuan beserta status. Klik **Detail** untuk melihat barang, approval, dan mengunduh BAST/surat peminjaman bila tersedia.

## 5. Laboran / Ketua Laboran

### Menyetujui pinjaman

1. Buka **Persetujuan Pinjam**.
2. Periksa barang, tujuan, dan jadwal.
3. Untuk level 1: isi catatan (opsional) dan klik **Setujui**, atau isi alasan lalu **Tolak Peminjaman**.
4. Barang bernilai tinggi dilanjutkan ke Wakasek. Level 2 **wajib** menggambar tanda tangan digital di kotak yang tersedia.

Laboran hanya dapat memproses pinjaman yang seluruh barangnya berada dalam cakupan laboratoriumnya.

### Serah terima

Setelah disetujui, buka detail pinjaman → **Serahkan Barang**. Status berubah menjadi **Sedang Dipinjam** dan petugas/waktu tercatat. Jangan menyerahkan barang sebelum tombol ini ditekan agar audit benar.

**⚠ 2.1:** saat serah terima, pindai/pilih unit fisik yang diserahkan. Unit yang dipinjam akan ditandai `DIPINJAM` dan tercatat pada detail pinjaman.

### Pengembalian

1. Buka **Pengembalian** → pilih pinjaman → **Proses Pengembalian**.
2. Untuk setiap barang isi jumlah yang kembali, kondisi akhir, dan ruangan tujuan.
3. Jika kondisi **Rusak Ringan/Berat**, unggah **foto bukti** (wajib).
4. Jika ruangan tujuan berbeda dari asal, konfirmasi peringatan — sistem akan mencatat mutasi dan menerbitkan kode baru (⚠ 2.1).
5. Jika jumlah kembali kurang, selisih dicatat sebagai **Hilang sementara**. Pinjaman tetap aktif sampai barang ditemukan/dikembalikan atau ditutup oleh Wakasek.
6. Pengembalian dapat dilakukan bertahap; jumlah kembali bersifat akumulatif.

### Scan QR

Tombol kamera mengambang / menu **Scan QR**. Arahkan kamera ke stiker. Jika kamera tidak tersedia, masukkan **kode inventaris** secara manual. Scan hanya membuka detail; hak ubah tetap diperiksa server.

## 6. Ketua Laboran (pengelola inventaris)

- **Pengadaan Barang**: isi data katalog dan jumlah unit. Jika dibuat oleh Ketua Laboran, status pengadaan `PENDING` dan menunggu Wakasek; jika oleh Wakasek, langsung disetujui.
- **Mutasi Lokasi** (⚠ 2.1): pilih unit dan ruangan tujuan. Kode baru diterbitkan, kode lama disimpan sebagai riwayat, dan label wajib dicetak ulang.
- **Inventaris Keluar**: ajukan penghapusan per unit dengan alasan dan **Berita Acara** (PDF/gambar). Status unit baru berubah setelah disetujui Wakasek. Unit dengan riwayat tidak dihapus permanen.
- **Master Data**: tambah/ubah/hapus jurusan, ruangan, kategori. Penghapusan ditolak jika masih dipakai.
- **Label**: pilih satu/beberapa unit → **Cetak Massal**. Label satuan dan re-print ada di detail unit.
- **Laporan**: pilih periode, cakupan (sekolah/kategori/ruangan/jurusan), lalu **PDF** atau **Excel**. **Simpan ke Arsip** membuat snapshot yang dapat diunduh kembali. Laporan selalu dibatasi cakupan laboratorium Anda.

## 7. Super Admin (Wakasek Sarpras)

Selain fitur Ketua Laboran:

- **Approval Pengadaan** dan **Approval Penghapusan**.
- Approval level 2 pinjaman bernilai tinggi (wajib tanda tangan).
- Menutup pinjaman dengan selisih: buka detail pinjaman → **Tutup Pinjaman**. Selisih menjadi **Hilang permanen**; riwayat peminjam terakhir tetap tersimpan.
- **Pengguna**: buat/ubah akun, tetapkan role, jurusan, dan cakupan laboratorium untuk Laboran/K3. Tidak dapat menghapus akun sendiri atau akun yang punya riwayat pinjaman.
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

## 10. Tips

- Gunakan kode inventaris untuk menemukan unit cepat; gunakan nama katalog bila belum tahu kodenya.
- Cetak ulang label segera setelah mutasi; waktu cetak terakhir tercatat.
- Jangan menutup pinjaman dengan selisih sebelum barang benar-benar dicari.
- Unduh BAST setelah approval lengkap agar memuat tanda tangan terbaru.
- Di ponsel, gunakan mode gelap saat kondisi ruangan redup.
