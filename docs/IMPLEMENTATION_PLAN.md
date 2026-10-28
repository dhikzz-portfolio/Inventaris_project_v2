# Implementation Plan & Status

Terakhir diperbarui: 24 Oktober 2026

Legenda status: **NOT STARTED**, **IN PROGRESS**, **IMPLEMENTED**, **VERIFIED**, **BLOCKED**.
`VERIFIED` berarti sudah dijalankan/diuji, bukan hanya file dibuat.

| Fase | Pekerjaan                                                                               | Status      | Bukti                                                                |
| ---- | --------------------------------------------------------------------------------------- | ----------- | -------------------------------------------------------------------- |
| 0    | Discovery: PRD, lingkungan, repositori, keputusan                                       | VERIFIED    | `PROJECT_BRIEF.md`, `DECISIONS.md`                                   |
| 1    | Analisis sistem: requirement, use case, matriks akses, ERD/dictionary                   | VERIFIED    | `PROJECT_BRIEF.md`, `DATABASE.md`                                    |
| 2    | Perencanaan teknis: stack, arsitektur, service layer, testing                           | VERIFIED    | `DECISIONS.md`, struktur `app/`                                      |
| 3    | Setup Laravel 13, Livewire 4, Tailwind, Vite, MySQL                                     | VERIFIED    | `composer install`, `npm run build`, migrasi MySQL                   |
| 4    | Database, model, seeder, autentikasi username, 4 role, audit log                        | VERIFIED    | migrasi + 6 akun demo + tes Auth/role                                |
| 5    | Design system: layout, sidebar, bottom nav, komponen, tema gelap                        | VERIFIED    | verifikasi browser 20 halaman, toggle tema                           |
| 6    | Modul inventaris: katalog, master data, pengadaan, mutasi, penghapusan, riwayat         | VERIFIED    | tes katalog, mutasi, write-off, master                               |
| 7    | Modul peminjaman: keranjang, kalender, approval, serah terima, pembatalan, pengembalian | VERIFIED    | 9 tes lifecycle + E2E browser                                        |
| 8    | QR, label satuan/massal, scan, BAST, tanda tangan                                       | VERIFIED    | tes label/QR/BAST + PDF dihasilkan                                   |
| 9    | Laporan bulanan PDF/Excel, filter, arsip                                                | VERIFIED    | PDF 7 KB, XLSX 5 sheet, tes ekspor                                   |
| 10   | Notifikasi, queue, scheduler, retry, deduplikasi                                        | VERIFIED    | tes job overdue/due soon/laporan                                     |
| 11   | Integrasi end-to-end seluruh modul                                                      | VERIFIED    | Playwright: ajukan → setujui → serah → kembali → selesai             |
| 12   | Testing & bug fixing                                                                    | VERIFIED    | 91 lulus, 0 skipped, 407 assertion                                   |
| 13   | Performance & security review                                                           | VERIFIED    | pengukuran halaman/pencarian, tes otorisasi & file privat            |
| 14   | Dokumentasi & deployment preparation                                                    | VERIFIED    | README + dokumen di `docs/`                                          |
| 15   | Final verification                                                                      | VERIFIED    | tes ulang, build, route, config cache, laporan akhir                 |
| 16   | Revisi dokumentasi PRD 2.1: katalog vs unit, kode `G1-02-12`, mutasi, pencarian/filter  | VERIFIED    | `PROJECT_BRIEF.md`, `DATABASE.md`, `DECISIONS.md`, dokumen pendukung |
| 17   | Implementasi inti revisi 2.1 (katalog/unit, kode, scope, label, laporan)                | VERIFIED    | 130 tes lulus; verifikasi browser; lihat `PROJECT_STATUS.md`         |
| 18   | Restorasi UI/UX desain lama + penyesuaian revisi (dashboard role, menu, form, tema)     | VERIFIED    | Audit 19 halaman, mobile 390px, tema gelap, Tailwind v4 diperbaiki   |
| 19   | Revisi lanjutan 22 Oktober: serah terima, persetujuan Wakasek, TTD opsional, master lokasi | VERIFIED | 183 tes; `LoanApprovalHandoverTest`, `BuildingMasterDataTest` |
| 20   | Revisi besar 24 Oktober: role `KETUA_LABORAN`, 5 jurusan, seeder terpisah, akun demo lengkap | VERIFIED | Migrasi role + seeder terpisah; 194 tes |
| 21   | Alur peminjaman: auto-approval Laboran/K3, nilai tinggi, katalog peminjam, duplikasi | VERIFIED | `LaboranApprovalTest`; regresi suite |
| 22   | Katalog aman + batas penyerahan unit + sinkronisasi dokumentasi | VERIFIED | `CatalogDeletionTest`, `LoanUnitFlowTest`; dokumen diperbarui |
| 23   | Revisi besar V2: registrasi Gmail, scan universal, gedung A–G, pengadaan multi-lokasi, mutasi multi-unit, impor/hapus laporan, status kegiatan, surat + pratinjau, UI pengguna | VERIFIED | `REVISION_V2.md`; 249 tes, 1190 asersi |

## Catatan fase 14

Deployment ke server nyata **BLOCKED**: tidak ada kredensial/akses server pada lingkungan ini. Seluruh artefak dan instruksi deployment telah disiapkan di `docs/DEPLOYMENT.md`.

## Pekerjaan lanjutan yang disarankan

1. Jalankan `queue:work` dan `schedule:run` sebagai service produksi (Supervisor/cron).
2. Ganti akun demo, setel `APP_DEBUG=false`, dan aktifkan HTTPS sebelum go-live.
3. Integrasikan penyedia e-signature tersertifikasi bila diperlukan dokumen legal formal.
4. Aktifkan verifikasi email (SMTP + `MustVerifyEmail`) dan reset password mandiri.
5. Pindahkan data demo dari gedung H/I ke A–G setelah daftar gedung resmi dikonfirmasi.

> Status implementasi revisi V2 terperinci ada di `REVISION_V2.md`; seluruh butir yang dapat dikerjakan pada lingkungan ini sudah diimplementasikan dan diuji.

---

## Revisi 2.1 — Rencana implementasi katalog & unit fisik

Revisi dokumentasi (fase 16) **selesai** dan implementasi inti 2.1 sudah berjalan. Status di bawah diperbarui 22 Oktober 2026 berdasarkan pengujian aktual.

| Epic | Pekerjaan                                                                                                                                          | Status      | Prasyarat / bukti penyelesaian                                                                |
| ---- | -------------------------------------------------------------------------------------------------------------------------------------------------- | ----------- | --------------------------------------------------------------------------------------------- |
| E1   | Konfirmasi keputusan bisnis: huruf gedung, kapasitas 99, cakupan Ketua Laboran                                                                     | IN PROGRESS | Pemetaan gedung demo masih sementara; keputusan sekolah belum tersedia                        |
| E2   | Normalisasi `rooms` (`building_letter`, `floor`, `room_no`, `is_lab`) + pemetaan data lama                                                         | VERIFIED    | Unique `(building_letter, floor, room_no)`; backfill unit diuji                                |
| E3   | Migration `item_catalogs`, `asset_units`, `asset_unit_codes`, `asset_unit_movements`, `maintenance_records`, `user_room_scopes`, `loan_item_units` | PARTIAL     | Semua tabel inti ada & diuji; `loan_item_units` belum dibuat (lihat `KNOWN_ISSUES.md` #1)      |
| E4   | `InventoryCodeService`: formatter, validator, penerbitan atomik, reissue, lookup kode pensiun                                                      | VERIFIED    | Test format/duplikat/kapasitas 99/kode pensiun lulus                                           |
| E5   | Backfill katalog & unit dari `items`, migrasi `loan_items`/`write_offs`, rekonsiliasi jumlah                                                       | VERIFIED    | `inventory:verify-codes` lulus pada data seeder & mendeteksi ledger tidak sinkron              |
| E6   | Model, policy, dan scope akses ruangan/lab pada katalog, unit, pencarian, ekspor, QR                                                               | VERIFIED    | Test IDOR/scope katalog, unit, URL, laporan, dan arsip lulus                                   |
| E7   | Livewire daftar katalog, detail katalog, detail unit, pencarian kode, filter, sorting, pagination                                                  | VERIFIED    | AC-01…AC-11 lulus test + verifikasi browser                                                    |
| E8   | Penyesuaian pinjaman (penugasan unit), penghapusan per unit, label per unit, laporan/Excel                                                         | PARTIAL     | Label per unit & laporan/Excel selesai; penugasan unit dan penghapusan per unit belum          |
| E9   | Dokumentasi hasil, perbarui `PROJECT_STATUS.md`, `TESTING.md`, `KNOWN_ISSUES.md`                                                                   | VERIFIED    | Status modul diselaraskan dengan hasil tes                                                     |

Catatan: acceptance criteria 2.1 (AC-01…AC-11, kode inventaris, cakupan akses, laporan) sudah dipenuhi dan diuji; lihat `TESTING.md` §9. Pekerjaan lanjutan yang belum dikerjakan adalah penugasan unit fisik pada peminjaman dan penghapusan per unit (`KNOWN_ISSUES.md` bagian A).
