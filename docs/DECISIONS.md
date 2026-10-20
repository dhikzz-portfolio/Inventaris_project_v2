# Keputusan Arsitektur & Catatan Teknis (DECISIONS.md)

Dokumen ini mencatat keputusan arsitektur, asumsi teknis, dan trade-off implementasi Sistem Manajemen Inventaris & Aset Sekolah sesuai Master Prompt Bagian 2, 6, dan 10.

---

## 1. Basis Data: MySQL / MariaDB (Asumsi 1)

- **Keputusan**: Menggunakan MySQL/MariaDB untuk lingkungan produksi/docker dan SQLite in-memory untuk kecepatan automated testing.
- **Rasional**: Dokumen PRD dan skema menggunakan tipe data standar MySQL. Konfigurasi `docker-compose.yml` menyertakan MySQL 8.0 / MariaDB 10.11 dan Redis 7.

## 2. Model Barang & Kuantitas (Asumsi 2)

- **Keputusan**:
    - Barang aset/investasi (`is_consumable = false`): dicatat per unit fisik unik (`quantity = 1` atau kuantitas batch unik dengan ID internal dan nomor register).
    - Bahan habis pakai (`is_consumable = true`): dicatat dalam satu baris dengan kolom `quantity` sebagai representasi stok berjalan serta `min_stock` untuk memicu notifikasi `STOK_MENIPIS`.
- **Rasional**: Menjaga integritas nomor register aset pemerintah untuk barang bernilai sekaligus mempermudah pencatatan stok bahan praktikum (misal kertas, spidol, kabel rol).

## 3. Perhitungan Ketersediaan & Anti-Bentrok Jadwal (Asumsi 3, R4)

- **Keputusan**: Ketersediaan peminjaman dihitung bukan hanya dari status instan barang (`items.status`), melainkan dari irisan rentang waktu peminjaman (`loans.start_date` sampai `loans.due_date`) yang berstatus `PENDING`, `APPROVED`, atau `DIPINJAM`.
- **Rasional**: Mencegah peminjaman ganda (double-booking) barang yang sama untuk jadwal mendatang meskipun saat ini barang masih berada di lab. Digunakan transaksi database dan pemeriksaan kuantitas kumulatif.

## 4. Mutasi Lokasi & Sinkronisasi ID Internal (Asumsi 4, Bagian 5.4, 5.5)

- **Keputusan**:
    - Mutasi dilakukan langsung oleh Admin Sarpras atau Super Admin (Wakasek).
    - Ketika barang dipindahkan ke ruangan baru, `items.room_id` dan `items.internal_id` (format `PREFIX-KODERUANGAN-URUTAN`, misal `PC-G1-01-01`) diperbarui secara otomatis dalam satu `DB::transaction`, disertai pencatatan riwayat pada tabel `item_movements`.
- **Rasional**: Menjamin konsistensi spasial antara fisik barang di lapangan dan ID yang tertera pada sistem maupun stiker QR.

## 5. Nomor Register Otomatis (Asumsi 5)

- **Keputusan**: Nomor register dibuat otomatis berurutan (sequential) 4 digit bertipe integer dengan padding nol (`0001`, `0002`, dst.) berdasarkan kombinasi `asset_code + register_no`.
- **Rasional**: Memenuhi format penomoran Standar Aset Pemerintah.

## 6. Autentikasi Menggunakan Username (Bagian 3, 4)

- **Keputusan**: Laravel Breeze disesuaikan agar login menggunakan `username` alih-alih `email`. Kolom `users.username` bersifat unik.
- **Rasional**: Praktis untuk lingkungan sekolah (guru, staf, laboran memiliki NIP/username akun sekolah tanpa keharusan memiliki email unik publik).

## 7. Perlindungan Audit Log (R9)

- **Keputusan**: Event listener / Model protection pada `Activity` (Spatie Activitylog) mencegah operasi update dan hard-delete secara absolut baik via UI maupun internal API/Eloquent.
- **Rasional**: Menjamin keaslian jejak audit seluruh transaksi sensitif (mutasi, approval, penghapusan, pengembalian).

## 8. Format Label Stiker & QR Code (Bagian 5.6)

- **Keputusan**: Label dicetak dalam format PDF lanskap memanjang berisi logo instansi, kode lokasi, kode klasifikasi barang + nomor register, kode internal, dan QR Code terenkripsi/terarah ke URL detail barang (`/items/{id}`). Re-print tersedia langsung di detail barang (R5).
- **Rasional**: Format standar label stiker aset siap cetak thermal maupun kertas stiker A4.

---

## 9. Database Runtime: MySQL 8.4 (v2)

- **Keputusan**: Lingkungan pengembangan v2 memakai MySQL 8.4 (`inventaris_sekolah_v2`) sesuai stack PRD. Automated test tetap memakai SQLite in-memory agar cepat dan terisolasi.
- **Rasional**: PRD menyebut PostgreSQL di judul skema namun contoh migrasi dan stack menggunakan MySQL/MariaDB; MySQL dipilih agar konsisten dengan migrasi dan deployment Laragon/Docker. Perbedaan dialek tetap diuji lewat test suite.
- **Konsekuensi**: Query yang hanya valid di MySQL tidak boleh dipakai tanpa fallback SQLite (test suite akan menangkapnya).

## 10. Model Kuantitas Aset: Batch + Counter Kondisi

- **Keputusan**: Satu baris `items` mewakili satu kelompok unit dengan `quantity`. Kondisi unit yang tidak dapat dipinjam dilacak melalui `qty_damaged` dan `qty_lost`; selisih pengembalian yang belum ditutup dilacak di `loan_items.qty_lost`.
- **Rumus ketersediaan**: `quantity - qty_damaged - qty_lost - sisa pinjaman aktif`.
- **Rasional**: Menghindari perubahan status seluruh baris ketika hanya sebagian unit dipinjam/rusak/hilang. Unit individual tidak dilacak satu per satu; QR per baris menunjuk ke detail aset.
- **Konsekuensi**: Jika sekolah membutuhkan QR unik per unit fisik, perlu tabel `asset_units` tambahan (lihat `KNOWN_ISSUES.md`).

## 11. Siklus Peminjaman Eksplisit

- **Keputusan**: Status peminjaman: `PENDING → APPROVED → DIPINJAM → SELESAI`, dengan cabang `DITOLAK` dan `DIBATALKAN`. Serah terima barang wajib dilakukan petugas (`handed_over_at`, `handed_over_by`) sebelum pengembalian.
- **Rasional**: Memisahkan "disetujui" dari "barang benar-benar dibawa" sehingga audit dan pengembalian tidak ambigu.
- **Konsekuensi**: UI menampilkan aksi "Serahkan Barang" hanya untuk petugas berwenang; pembatalan ditolak setelah serah terima.

## 12. Pengembalian Akumulatif & Selisih

- **Keputusan**: `loan_items.qty_returned` bersifat akumulatif. Setiap pengembalian menambah jumlah, bukan menimpa. Selisih `qty_borrowed - qty_returned` dicatat sebagai `qty_lost` sementara pada baris pinjaman; Wakasek dapat menutup pinjaman dan memindahkannya menjadi `items.qty_lost` permanen.
- **Rasional**: Mendukung pengembalian bertahap, barang yang ditemukan menyusul, dan tidak menyelesaikan pinjaman secara palsu saat masih ada kekurangan.
- **Konsekuensi**: Pinjaman dengan selisih tetap aktif sampai ditutup; notifikasi dikirim ke pengelola.

## 13. File Internal Disimpan Privat

- **Keputusan**: Foto bukti kerusakan, tanda tangan, Berita Acara, BAST, dan laporan disimpan di disk `local` (`storage/app/private`) dan disajikan melalui route `/files?path=...` dengan pemeriksaan peran/cakupan. Foto barang dan logo institusi tetap publik.
- **Rasional**: Dokumen internal tidak boleh dapat diakses hanya dengan menebak URL.
- **Konsekuensi**: Backup harus mencakup `storage/app/private`; web server tidak boleh mengekspos direktori tersebut.

## 14. Livewire 4 Membundel Alpine

- **Keputusan**: `resources/js/app.js` **tidak** mengimpor/menjalankan Alpine lagi. Hanya Chart.js dan html5-qrcode yang didaftarkan ke `window`.
- **Rasional**: Menjalankan Alpine kedua kali membuat directive `wire:model` tidak terpasang; pencarian dan pagination tidak mengirim request (ditemukan saat verifikasi browser).
- **Konsekuensi**: Semua komponen interaktif memakai Alpine bawaan Livewire. Jangan menambahkan `Alpine.start()` di masa depan.

## 15. QR Berisi URL Detail

- **Keputusan**: QR label berisi URL absolut `route('items.show', $item)`, bukan hanya ID internal. Scanner internal mendeteksi URL dan membuka langsung; input manual tetap menerima ID internal untuk stiker lama.
- **Rasional**: Kamera bawaan ponsel dapat membuka detail aset tanpa aplikasi tambahan, dan `redirect()->intended()` mengembalikan pengguna ke halaman tersebut setelah login.
- **Konsekuensi**: `APP_URL` harus benar di produksi agar QR lama tetap valid.

## 16. Laporan Hanya untuk Pengelola

- **Keputusan**: Route laporan dan ekspor dibatasi `SUPER_ADMIN|ADMIN_SARPRAS`. Laboran tetap dapat melihat label, scan, dan riwayat unit, tetapi tidak dapat mengekspor data seluruh sekolah.
- **Rasional**: Prinsip least privilege; permintaan PRD untuk laboran adalah pemantauan unit, bukan laporan institusi.

## 17. Excel Multi-Sheet

- **Keputusan**: Ekspor Excel laporan berisi lima sheet: Barang, Peminjaman, Kerusakan, Mutasi Lokasi, Penghapusan.
- **Rasional**: Isi Excel setara dengan PDF dan dapat difilter per bagian oleh pengelola.

## 18. Redis Opsional

- **Keputusan**: Default v2 memakai driver database untuk cache/session/queue karena ekstensi `phpredis` tidak tersedia di lingkungan ini. Konfigurasi Redis tetap ada di `.env.example`.
- **Rasional**: Aplikasi harus dapat dijalankan tanpa layanan tambahan; Redis dapat diaktifkan di produksi setelah ekstensi/predis dipasang.
- **Konsekuensi**: Worker queue tetap wajib dijalankan; lihat `DEPLOYMENT.md`.
