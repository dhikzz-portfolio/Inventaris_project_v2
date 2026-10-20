# CLAUDE.md — Sistem Manajemen Inventaris & Aset Sekolah

Panduan pengerjaan proyek Sistem Manajemen Inventaris & Aset Sekolah SMK berbasis PHP 8.3 + Laravel 13, Livewire 4, Alpine.js, Tailwind CSS, spatie/permission, spatie/activitylog, DomPDF, Maatwebsite Excel, dan html5-qrcode.

---

## Ringkasan Peran & Konvensi
- **Bahasa Antarmuka**: Bahasa Indonesia
- **Kode, Variabel, Tabel, Komentar**: Bahasa Inggris / bilingual sesuai konvensi Laravel
- **Tech Stack**: PHP 8.3, Laravel 13, Livewire 4, Alpine.js, Tailwind CSS, MySQL/MariaDB (Docker/Prod) / SQLite (Testing), Redis
- **Arsitektur**: Modular MVC, Service Layer (`LoanService`, `BastService`, `LabelService`, `ReportService`, `IdGenerator`), Form Requests, In-App Notifications (`NotifyApp`), Background Jobs (`CheckOverdueLoans`, `CheckLowStock`, `GenerateMonthlyReport`).

---

## Perintah Penting (CLI)
```bash
# Testing
php artisan test
php artisan test --filter=CriticalBusinessRulesTest
php artisan test --filter=InventoryFlowTest

# Code Formatting (Pint)
vendor/bin/pint --dirty --format agent

# Build Assets
npm run build

# Docker
docker compose up -d --build
```

---

## 9 Aturan Bisnis Kritis (Wajib Lulus Test)
1. **R1**: Kuantitas kembali < pinjam -> pinjaman TIDAK SELESAI, selisih flag `HILANG`.
2. **R2**: Kembali ke ruangan lain -> check-in tervalidasi, tercatat mutasi otomatis, ID Internal diperbarui otomatis.
3. **R3**: Kondisi rusak -> wajib foto bukti, riwayat peminjam terakhir tercatat.
4. **R4**: Jadwal bentrok -> ditolak validasi saat rentang tanggal beririsan pada barang yang sama.
5. **R5**: Label pudar/rusak -> re-print label PDF tersedia di detail barang.
6. **R6**: Barang `is_high_value` -> approval level 1 (Laboran) lalu level 2 (Wakasek) + TTD digital; status tidak boleh `DIPINJAM` sebelum semua APPROVED.
7. **R7**: Write-off -> wajib Berita Acara (file), hanya efektif setelah disetujui Wakasek.
8. **R8**: Laboran lintas jurusan -> ditolak (HTTP 403).
9. **R9**: Audit log -> immutable (tidak bisa dihapus/diubah dari UI atau API).

---

## Akun Demo
- `wakasek` / `wakasek123` (SUPER_ADMIN)
- `admin.sarpras` / `admin123` (ADMIN_SARPRAS)
- `laboran.tkj` / `laboran123` (LABORAN TKJ)
- `laboran.rpl` / `laboran123` (LABORAN RPL)
- `guru.budi` / `guru123` (PEMINJAM)
- `guru.sari` / `guru123` (PEMINJAM)
