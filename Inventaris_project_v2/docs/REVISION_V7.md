# Revisi V7 — 3 November 2026

Revisi lanjutan setelah V6. Seluruh keputusan bisnis V2–V6 dipertahankan; dokumen
ini hanya mencatat perubahan dan batas verifikasi yang benar-benar dilakukan.

## 1. Peminjaman atas nama diri sendiri atau orang lain

- Form peminjaman (`App\Livewire\Loan\LoanRequest`) memiliki pilihan
  **Diri sendiri / Orang lain** untuk role operasional.
- Role `PEMINJAM` tidak memperoleh pilihan tersebut. Backend
  (`LoanService::resolveBorrowerIdentity`) memaksa `SELF`; request `OTHER`
  ditolak `ValidationException` meskipun dikirim langsung tanpa UI.
- Identitas pembuat transaksi dan peminjam disimpan terpisah:
  - `loans.requested_by` — akun yang membuat transaksi.
  - `loans.borrower_id` — akun peminjam jika ada (nullable).
  - `loans.borrower_mode` — `SELF` / `OTHER`.
  - `loans.borrower_name`, `borrower_category`, `borrower_class`,
    `borrower_work_unit` — snapshot identitas agar dokumen lama tidak berubah.
- Detail peminjaman, PDF, dan Excel menampilkan **Dibuat oleh** dan
  **Dipinjam oleh** secara terpisah.
- Peminjam tanpa akun diperbolehkan; pencarian akun hanya membantu pengaitan
  akun bila tersedia.

## 2. Rincian unit pada PDF dan Excel

- `App\Exports\MonthlyReportExport::tables()` adalah satu-satunya definisi tabel.
- PDF (`resources/views/pdf/report.blade.php`), file Excel, dan pratinjau Excel
  memakai hasil `tables()` yang sama; tidak ada query/transformasi terpisah.
- Sheet/tabel **Unit** berisi satu baris per unit fisik (kode inventaris, merek,
  tipe/model, spesifikasi, lokasi, kondisi, status, tahun, sumber dana, register).
- Header tabel PDF diulang tiap halaman (`thead { display: table-header-group }`),
  baris tidak terpotong, dan nomor halaman dicetak DomPDF.

## 3. Pratinjau Excel

- Route `reports.preview-excel` (`/laporan/pratinjau-excel/{period}`) tersedia
  untuk role yang juga dapat membuka laporan (Super Admin, Ketua Laboran,
  Laboran/K3), dengan cakupan data di backend.
- Halaman menampilkan judul laporan, periode, cakupan/filter, tab tiap sheet,
  header kolom, data, jumlah baris, pagination pratinjau (50 baris), tombol
  unduh Excel, dan tombol kembali.
- Pagination hanya membatasi tampilan; file ekspor tetap memuat seluruh baris.

## 4. Contoh file dan panduan impor

- Tombol **Unduh Contoh File** pada halaman impor mengunduh CSV
  (`reports.import.template`) dengan header yang benar-benar dibaca
  `ReportImportService`: `kode_inventaris`, `nama_barang`, `kategori`, `gedung`,
  `lantai`, `no_ruangan`, `kondisi`, `status`, `tahun`, `sumber_dana`,
  `no_register`, beserta dua baris contoh.
- Panduan kolom wajib/opsional, format kondisi/status/tahun, pola kode unit,
  relasi katalog/ruangan, dan arti hasil validasi tersedia pada panel yang sama.
- Impor tetap dua tahap: pratinjau (`analyze`) lalu konfirmasi (`apply`) dalam
  satu transaksi. Header salah, kolom wajib kosong, relasi tidak valid, kode
  duplikat, dan kode pensiun diblokir sebelum data disimpan.

## 5. Foto kerusakan pada detail unit

- `damage_report_photos` menyimpan banyak foto per laporan; `photo_path` lama
  tetap dipertahankan sebagai foto utama.
- Halaman detail unit menampilkan bagian **Riwayat Kerusakan dan Dokumentasi**:
  seluruh laporan kronologis, klasifikasi, status penanganan, pelapor,
  penangan, rekomendasi, dan galeri thumbnail yang dapat dibuka besar.
- Bukti foto dari pengembalian per unit (`loan_item_units.damage_photo_path`)
  juga ditampilkan pada unit yang sama.
- `SecureFileController` memeriksa setiap path foto terhadap laporan yang
  terlihat pengguna (bukan hanya `photo_path`), sehingga multi-foto tidak
  menghasilkan 403 dan path milik unit di luar cakupan tetap ditolak.
- Empty state eksplisit; tidak ada placeholder yang menyamar sebagai bukti.

## 6. Halaman Super Admin Pengguna

- Seluruh route `/pengguna` hanya berada dalam middleware
  `role:SUPER_ADMIN_PENGGUNA`. Super Admin Sarpras, Ketua Laboran, Laboran/K3,
  dan Peminjam ditolak backend.
- Menu **Akun Pengguna** hanya dirender untuk `isSuperAdmin()`.
- Halaman menyediakan ringkasan akun, daftar, pencarian, filter role/status,
  detail, tambah, ubah, aktif/nonaktif, kelola role & cakupan ruangan, serta
  riwayat perubahan dari activity log (tanpa password).
- Super Admin Pengguna tidak memperoleh akses operasional inventaris; route
  operasional dilindungi middleware `inventory.access`.
- Perlindungan tetap: tidak dapat menetapkan role `SUPER_ADMIN`, tidak dapat
  mengubah/menonaktifkan Super Admin utama, dan sistem menolak kehilangan
  Super Admin utama aktif terakhir.

## 7. Bootstrap akun awal Super Admin Pengguna

```bash
# Isi email lebih dulu (jangan mengarang alamat):
#   BOOTSTRAP_USER_ADMIN_EMAIL=admin.akun@sekolah.sch.id
#   BOOTSTRAP_USER_ADMIN_NAME="Super Admin Pengguna"
#   BOOTSTRAP_USER_ADMIN_PASSWORD=password-awal-min-12-karakter   (opsional)

php artisan user:bootstrap-admin
# atau eksplisit:
php artisan user:bootstrap-admin --email=admin.akun@sekolah.sch.id

# Setel ulang password akun bootstrap yang sudah ada (wajib ganti lagi):
php artisan user:bootstrap-admin --reset-password
```

- Email wajib valid dan unik; jika sudah dipakai akun lain command gagal.
  Email bootstrap harus berbeda dari `SUPER_ADMIN_EMAIL`.
- Urutan sumber password awal: `--password`, lalu
  `BOOTSTRAP_USER_ADMIN_PASSWORD`, lalu acak (`Str::password(20)`).
- `BOOTSTRAP_USER_ADMIN_PASSWORD` **opsional**: diisi hanya bila administrator
  ingin kredensial awal bisa dibaca kembali dari `.env`. `.env` tidak masuk
  repository (di-ignore git) dan nilainya tidak pernah ditulis ke log.
  Kosongkan variabel itu setelah login pertama; `.env` hanya dibaca saat
  command dijalankan, bukan saat login.
- Password di-hash oleh cast `hashed` Laravel.
- `must_change_password = true`; middleware `password.changed` mengarahkan
  pengguna ke halaman profil sampai password diganti.
- Command idempoten: akun bootstrap yang sudah ada tidak diduplikasi dan
  password-nya tidak diubah kecuali dijalankan dengan `--reset-password`.
  Reset hanya menyentuh akun ber-role `SUPER_ADMIN_PENGGUNA` dengan email yang
  sama, dan mengaktifkan kembali kewajiban ganti password.

## 8. Database

Migration baru:

- `2026_11_03_000002_add_borrower_identity_to_loans_table.php`
- `2026_11_03_000003_add_password_change_requirement_to_users_table.php`
- `2026_11_03_000004_create_damage_report_photos_table.php`

Tidak ada data lama yang dihapus. Backfill mengisi `requested_by` dan snapshot
identitas dari akun lama.

## 9. Pengujian

- `tests/Feature/RevisionV5Test.php` mencakup peminjaman diri/orang lain,
  penolakan manipulasi role Peminjam, pemisahan identitas di detail, kesamaan
  PDF/Excel/pratinjau, template impor, header salah, multi-foto + akses file,
  akses halaman akun per role, eskalasi role, bootstrap, dan wajib ganti password.
- Suite penuh: **379 passed, 0 failed, 2065 assertions** (SQLite in-memory).
- `phpunit.xml` menetapkan `SEED_DEMO_DATA=true`, sehingga hasil tes tidak
  bergantung pada nilai `SEED_DEMO_DATA` di `.env` lokal.
- `vendor/bin/pint --dirty --format agent`: **PASS**.

Belum diverifikasi pada revisi ini: cetak fisik, pemindai QR fisik, E2E
Playwright ulang, uji beban produksi, dan audit responsif ulang.
