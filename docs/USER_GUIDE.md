# Panduan Pengguna

## 1. Masuk & navigasi

1. Buka alamat aplikasi, masukkan **username** dan **password** (bukan email).
2. Menu desktop ada di sidebar kiri; di ponsel gunakan bottom navigation: **Beranda, Katalog, Pinjam, Riwayat/Persetujuan, Lainnya**.
3. Ikon bulan/matahari di kanan atas mengganti tema terang/gelap. Pilihan tersimpan di perangkat dan mengikuti preferensi sistem bila belum dipilih.

## 2. Matriks akses

| Fitur                                   | SUPER_ADMIN | ADMIN_SARPRAS |  LABORAN  | PEMINJAM  |
| --------------------------------------- | :---------: | :-----------: | :-------: | :-------: |
| Dashboard                               |   ✔ semua   |    ✔ semua    | ✔ jurusan | ✔ pribadi |
| Katalog & detail barang                 |      ✔      |       ✔       |     ✔     |     ✔     |
| Ajukan peminjaman                       |      ✔      |       ✔       |     ✔     |     ✔     |
| Setujui pinjaman level 1                |      ✔      |       ✔       | ✔ jurusan |     —     |
| Setujui pinjaman level 2 (nilai tinggi) |      ✔      |       —       |     —     |     —     |
| Serah terima & pengembalian             |      ✔      |       ✔       | ✔ jurusan |     —     |
| Pengadaan (ajukan)                      |      ✔      |       ✔       |     —     |     —     |
| Approval pengadaan                      |      ✔      |       —       |     —     |     —     |
| Mutasi lokasi                           |      ✔      |       ✔       |     —     |     —     |
| Penghapusan (ajukan)                    |      ✔      |       ✔       |     —     |     —     |
| Approval penghapusan                    |      ✔      |       —       |     —     |     —     |
| Label & scan QR                         |      ✔      |       ✔       |     ✔     |     —     |
| Laporan & ekspor                        |      ✔      |       ✔       |     —     |     —     |
| Master data                             |      ✔      |       ✔       |     —     |     —     |
| Pengguna & pengaturan instansi          |      ✔      |       —       |     —     |     —     |

## 3. Peminjam

### Mengajukan peminjaman

1. **Ajukan Peminjaman** → cari barang (minimal 2 huruf) → **Tambah**.
2. Ubah jumlah di keranjang. Sistem menampilkan ketersediaan pada rentang tanggal yang dipilih.
3. Isi tujuan, tanggal/waktu pinjam dan kembali, serta kegiatan (opsional).
4. Kalender menandai tanggal yang sudah dibooking. Barang bernilai tinggi memerlukan dua persetujuan.
5. **Kirim Request Peminjaman**.

Status: **Menunggu Persetujuan → Disetujui → Sedang Dipinjam → Selesai**. Pengajuan dapat **dibatalkan** selama barang belum diserahkan. Penolakan menyertakan alasan.

### Melihat riwayat

Menu **Peminjaman Saya** menampilkan semua pengajuan beserta status. Klik **Detail** untuk melihat barang, approval, dan mengunduh BAST/surat peminjaman bila tersedia.

## 4. Laboran / Admin

### Menyetujui pinjaman

1. Buka **Persetujuan Pinjam**.
2. Periksa barang, tujuan, dan jadwal.
3. Untuk level 1: isi catatan (opsional) dan klik **Setujui**, atau isi alasan lalu **Tolak Peminjaman**.
4. Barang bernilai tinggi dilanjutkan ke Wakasek. Level 2 **wajib** menggambar tanda tangan digital di kotak yang tersedia.

Laboran hanya dapat memproses pinjaman yang seluruh barangnya berada di jurusannya.

### Serah terima

Setelah disetujui, buka detail pinjaman → **Serahkan Barang**. Status berubah menjadi **Sedang Dipinjam** dan petugas/waktu tercatat. Jangan menyerahkan barang sebelum tombol ini ditekan agar audit benar.

### Pengembalian

1. Buka **Pengembalian** → pilih pinjaman → **Proses Pengembalian**.
2. Untuk setiap barang isi jumlah yang kembali, kondisi akhir, dan ruangan tujuan.
3. Jika kondisi **Rusak Ringan/Berat**, unggah **foto bukti** (wajib).
4. Jika ruangan tujuan berbeda dari asal, konfirmasi peringatan — sistem akan mencatat mutasi otomatis dan memperbarui ID internal.
5. Jika jumlah kembali kurang, selisih dicatat sebagai **Hilang sementara**. Pinjaman tetap aktif sampai barang ditemukan/dikembalikan atau ditutup oleh Wakasek.
6. Pengembalian dapat dilakukan bertahap; jumlah kembali bersifat akumulatif.

### Scan QR

Tombol kamera mengambang / menu **Scan QR**. Arahkan kamera ke stiker. Jika kamera tidak tersedia, masukkan **ID Internal** secara manual. Scan hanya membuka detail; hak ubah tetap diperiksa server.

## 5. Admin Sarpras

- **Pengadaan Barang**: isi data barang. Jika dibuat oleh Admin, status pengadaan `PENDING` dan menunggu Wakasek; jika oleh Wakasek, langsung disetujui.
- **Mutasi Lokasi**: pilih barang dan ruangan tujuan. ID internal otomatis diperbarui dan riwayat tersimpan.
- **Inventaris Keluar**: ajukan penghapusan dengan jumlah, alasan, dan **Berita Acara** (PDF/gambar). Stok baru berkurang setelah disetujui Wakasek. Barang dengan riwayat tidak dihapus permanen.
- **Master Data**: tambah/ubah/hapus jurusan, ruangan, kategori. Penghapusan ditolak jika masih dipakai.
- **Label**: pilih satu/beberapa barang → **Cetak Massal**. Label satuan dan re-print ada di detail barang.
- **Laporan**: pilih periode, cakupan (sekolah/kategori/ruangan/jurusan), lalu **PDF** atau **Excel**. **Simpan ke Arsip** membuat snapshot yang dapat diunduh kembali.

## 6. Super Admin (Wakasek Sarpras)

Selain fitur Admin:

- **Approval Pengadaan** dan **Approval Penghapusan**.
- Approval level 2 pinjaman bernilai tinggi (wajib tanda tangan).
- Menutup pinjaman dengan selisih: buka detail pinjaman → **Tutup Pinjaman**. Selisih menjadi **Hilang permanen**; riwayat peminjam terakhir tetap tersimpan.
- **Pengguna**: buat/ubah akun, tetapkan role dan jurusan. Tidak dapat menghapus akun sendiri atau akun yang punya riwayat pinjaman.
- **Pengaturan Instansi**: nama sekolah, kode lokasi, alamat, kepala sekolah, logo. Berlaku untuk label, BAST, dan laporan.

## 7. Status & warna

| Status          | Warna        | Arti                                                  |
| --------------- | ------------ | ----------------------------------------------------- |
| Baik            | Hijau        | Kondisi fisik baik.                                   |
| Rusak Ringan    | Kuning       | Perlu perbaikan ringan; sebagian unit tidak tersedia. |
| Rusak Berat     | Oranye/Merah | Tidak dapat dipinjam.                                 |
| Hilang          | Merah        | Unit hilang permanen.                                 |
| Sedang Dipinjam | Biru         | Unit berada di peminjam.                              |
| Dihapus         | Abu-abu      | Seluruh unit dihapus dari inventaris aktif.           |

Status selalu disertai teks, tidak hanya warna. Angka **Tersedia** di katalog dihitung dari total unit dikurangi rusak, hilang, dan yang sedang di luar.

## 8. Notifikasi

Ikon lonceng menampilkan notifikasi: pengajuan baru, persetujuan/penolakan, kebutuhan approval level 2, pengingat jatuh tempo, keterlambatan, kerusakan, dan selisih pengembalian. Klik notifikasi untuk membuka tautan terkait; **Tandai semua dibaca** tersedia di halaman notifikasi.

Notifikasi dihasilkan oleh queue worker. Jika tidak muncul, pastikan worker berjalan (lihat `INSTALLATION.md`).

## 9. Tips

- Gunakan pencarian ID internal/nomor register untuk menemukan barang cepat.
- Cetak ulang label jika stiker pudar; waktu cetak terakhir tercatat.
- Jangan menutup pinjaman dengan selisih sebelum barang benar-benar dicari.
- Unduh BAST setelah approval lengkap agar memuat tanda tangan terbaru.
- Di ponsel, gunakan mode gelap saat kondisi ruangan redup.
