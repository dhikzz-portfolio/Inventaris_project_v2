# Project Brief — Sistem Manajemen Inventaris & Aset Sekolah

## 1. Latar belakang

Sekolah mengelola ratusan aset yang tersebar di banyak gedung, laboratorium, dan jurusan. Pencatatan manual menyebabkan:

- Barang hilang atau rusak tidak terlacak.
- Jadwal peminjaman bentrok karena tidak ada kalender ketersediaan.
- Nomor register dan label aset tidak konsisten.
- Dokumen serah terima (BAST) dan laporan dibuat ulang secara manual.
- Tidak ada jejak audit siapa meminjam, menyetujui, atau memindahkan barang.

Aplikasi ini menggantikan pencatatan manual tersebut dengan satu sistem web terpusat yang dapat diakses dari browser desktop maupun ponsel.

## 2. Tujuan

1. Menyediakan katalog aset tunggal dengan identitas inventaris ganda (ID internal/spasial, kode lokasi, kode barang, nomor register).
2. Mencatat siklus hidup aset: pengadaan, penempatan, mutasi, peminjaman, pengembalian, kerusakan/kehilangan, dan penghapusan.
3. Mencegah double-booking melalui validasi server dan transaksi database.
4. Menyediakan persetujuan berjenjang dan audit trail yang tidak dapat diubah dari aplikasi.
5. Menghasilkan label QR, surat peminjaman/BAST, laporan PDF, dan Excel multi-sheet.
6. Memberi pengalaman mobile-first bagi laboran (scan QR, pengembalian checklist).

## 3. Pengguna dan peran

| Peran                           | Kebutuhan utama                                                                                                        |
| ------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| `SUPER_ADMIN` (Wakasek Sarpras) | Akses penuh, approval pengadaan/penghapusan, approval level 2 pinjaman bernilai tinggi, pengaturan institusi dan akun. |
| `ADMIN_SARPRAS`                 | Kelola inventaris lintas unit, master data, mutasi, label, pengadaan, penghapusan, laporan.                            |
| `LABORAN`                       | Kelola pinjaman/pengembalian di jurusannya, scan QR, approval level 1, pantau riwayat unit.                            |
| `PEMINJAM`                      | Lihat katalog & ketersediaan, ajukan multi-item, batalkan pengajuan, lihat riwayat dan notifikasi sendiri.             |

## 4. Cakupan implementasi

### Termasuk

- Autentikasi username/password, sesi, rate limit login, otorisasi role + cakupan jurusan.
- Dashboard berbeda untuk pengelola dan peminjam, memakai data aktual.
- CRUD barang, kategori, jurusan, ruangan; pencarian debounce, filter, sorting, pagination.
- Pengadaan barang dengan approval Wakasek.
- Peminjaman multi-item, keranjang, kalender ketersediaan, anti-bentrok.
- Approval berjenjang + tanda tangan gambar (signature pad).
- Serah terima barang, pembatalan, pengembalian parsial/akumulatif, selisih hilang, kerusakan + foto bukti.
- Mutasi ruangan atomik dengan pembaruan ID internal dan riwayat.
- Penghapusan aset dengan Berita Acara dan approval.
- Label QR satuan/massal, re-print, scan kamera + input manual.
- BAST per peminjaman dan BAST gabungan kegiatan.
- Laporan bulanan (PDF + Excel 5 sheet), filter kategori/ruangan/jurusan.
- Notifikasi in-app dan job terjadwal (jatuh tempo, terlambat, stok menipis, laporan bulanan).
- Tema terang/gelap, bottom navigation mobile, PWA manifest + service worker.
- Audit log immutable, file internal privat, pengujian otomatis dan dokumentasi.

### Tidak termasuk (lihat `KNOWN_ISSUES.md`)

- Akuntansi aset/penyusutan dan integrasi sistem pemerintah.
- Tanda tangan elektronik tersertifikasi (PSrE).
- Pelacakan satu QR per unit fisik untuk barang dengan kuantitas besar (model batch + counter).
- API publik / integrasi pihak ketiga.
- Deployment ke server produksi nyata (tidak ada akses server pada lingkungan ini).

## 5. Asumsi

- Satu sekolah, banyak gedung/jurusan; tidak multi-tenant.
- Barang bernilai tinggi ditandai manual (`is_high_value`) dan wajib approval dua level.
- Kode lokasi/instansi dan kode klasifikasi barang diisi sekolah melalui pengaturan/kategori; aplikasi tidak mengarang kode resmi.
- Barang habis pakai dicatat sebagai stok (kuantitas + ambang minimum), bukan unit individual.
- Waktu server menggunakan zona waktu aplikasi (`APP_TIMEZONE`, default `UTC` pada `config/app.php`).

## 6. Indikator keberhasilan

- Seluruh alur inti lulus pengujian otomatis dan verifikasi browser.
- Tidak ada akses lintas jurusan/IDOR pada halaman sensitif.
- Pengajuan yang ditolak/dibatalkan tidak memblokir ketersediaan.
- Pengembalian parsial tidak menimpa jumlah sebelumnya.
- File bukti/BA/BAST hanya dapat diakses pengguna berwenang.
- Halaman katalog/dashboard < 1 detik dan pencarian < 0,5 detik pada lingkungan uji (hasil aktual di `TESTING.md`).
