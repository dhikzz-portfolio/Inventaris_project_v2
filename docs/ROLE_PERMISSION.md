# Role, Permission, dan Hak Akses

Dokumen ini menggambarkan sistem yang diterapkan pada aplikasi Inventaris Sekolah.

## 1. Lima role utama

| Role | Tanggung jawab |
|---|---|
| `SUPER_ADMIN` | Administrasi sistem: akun pengguna, pengaturan sekolah, role & permission, pemantauan aktivitas. **Tidak** memiliki akses operasional inventaris. |
| `ADMIN` | Seluruh operasional inventaris sekolah: master data, katalog, unit, pengadaan (persetujuan akhir), peminjaman, mutasi, kerusakan, penghapusan, laporan. Tidak memiliki administrasi akun/role/pengaturan sekolah. |
| `EDITOR_VALIDATOR` | Pengawasan seluruh laboratorium dan validasi pengadaan. Tidak membuat pengadaan, tidak menyetujui pengadaan, tidak mengubah master data, dan tidak menghapus inventaris. |
| `OPERATOR` | Operasional laboratorium pada jurusan/ruangan yang ditugaskan. Kategori `LABORAN` atau `K3` hanya membedakan identitas, bukan permission. |
| `MEMBER` | Peminjaman barang dan rekap kegiatan milik sendiri. Tidak memiliki akses administratif inventaris. |

Akun Super Admin bersifat tunggal dan terkunci. Role `SUPER_ADMIN` tidak dapat ditetapkan
melalui halaman administrasi akun dan permission-nya tidak dapat diubah dari halaman role.

## 2. Arsitektur authorization

- Paket: `spatie/laravel-permission` (sudah terpasang; tidak ada sistem otorisasi kedua).
- Sumber kebenaran: tabel `roles`, `permissions`, `role_has_permissions` (konfigurasi aktual)
  dan `app/Support/PermissionCatalog.php` (struktur, baseline, batasan keamanan).
- Identitas role tetap disimpan pada `users.role` dan pivot `model_has_roles` disinkronkan
  otomatis saat role berubah (`User::booted()`).
- Route dilindungi middleware `role:` untuk pemisahan tanggung jawab dan `permission:` untuk
  tindakan granular. Service/domain tetap memeriksa cakupan data (jurusan/laboratorium).
- Menu dan tombol membaca permission dari database. Backend tetap otoritas final.

## 3. Struktur permission

Hierarki: **Role → Modul → Fitur → Tindakan**. Nama permission: `fitur.tindakan`.

Modul: Administrasi Sistem, Master Data, Inventaris, Operasional, Laporan.

Tindakan yang tersedia: `view`, `create`, `edit`, `delete`, `approve`, `reject`,
`validate`, `export`, `print`, `manage`, `handover`, `return`, `receive`, `generate`,
`import`.

## 4. Baseline permission

| Fitur | Super Admin | Admin | Editor/Validator | Operator | Member |
|---|---|---|---|---|---|
| Akun Pengguna | Kelola | – | – | – | – |
| Pengaturan Sekolah | Lihat + Edit | – | – | – | – |
| Role & Permission | Lihat + Kelola | – | – | – | – |
| Pemantauan Aktivitas | Lihat | – | – | – | – |
| Master Jurusan/Gedung/Ruangan/Kategori | – | Kelola | – | – | – |
| Katalog Barang | – | Kelola | Lihat | Lihat | Lihat |
| Data & Kondisi Inventaris | – | Lihat + Edit | Lihat | Lihat | – |
| Label & QR | – | Lihat + Cetak | Lihat + Cetak | Lihat + Cetak | – |
| Distribusi | – | Lihat | Lihat | Lihat | – |
| Mutasi | – | Lihat + Buat | – | Lihat + Buat | – |
| Penghapusan Inventaris | – | Kelola + Setujui/Tolak | – | – | – |
| Pengadaan | – | Lihat, Buat, Edit, Setujui, Tolak, Terima | Lihat + Validasi | Lihat, Buat, Edit, Terima | – |
| Peminjaman | – | Lihat, Buat, Setujui, Tolak, Serah Terima, Kembali | Sama seperti Admin | Sama seperti Admin | Lihat + Buat |
| Laporan Kerusakan | – | Lihat, Buat, Edit | Lihat + Edit | Lihat, Buat, Edit | – |
| Rekap Kegiatan | – | Kelola | Lihat, Buat, Edit | Kelola | Kelola (milik sendiri) |
| Laporan Inventaris | – | Lihat, Ekspor, Cetak, Generate, Hapus, Impor | Lihat, Ekspor, Cetak | Lihat, Ekspor, Cetak | – |

Baseline lengkap dan dapat dieksekusi ada di `App\Support\PermissionCatalog::BASELINES`.

## 5. Tiga mode akses fitur

Setiap card fitur memiliki tiga tombol utama:

- **Bisa Edit** (`FULL`): seluruh tindakan fitur tersedia; checkbox tindakan dapat dipakai
  untuk membatasi tindakan tertentu (mode parsial ditampilkan sebagai **Custom**).
- **Hanya Lihat** (`VIEW_ONLY`): hanya `view`; form dan endpoint perubahan ditolak backend.
- **Sembunyikan** (`HIDDEN`): tidak ada permission; menu tidak tampil dan route/endpoint 403.

Data scope (jurusan/laboratorium) tetap terpisah dari mode ini: Bisa Edit tidak memperluas
data yang boleh diakses.

## 6. Pengelolaan oleh Super Admin

Menu **Role & Permission** (`/role-permission`):

1. Pilih role.
2. Atur per fitur: Sembunyikan / Hanya Lihat / Bisa Edit, atau centang tindakan individual.
3. Pratinjau menunjukkan jumlah fitur terlihat dan tindakan diizinkan.
4. Isi alasan (opsional) lalu simpan.
5. Riwayat perubahan mencatat waktu, pelaku, alasan, dan jumlah permission sebelum/sesudah.

Reset ke default tersedia per role. Super Admin sendiri terkunci.

## 7. Persistensi dan cache

- `roles.permissions_initialized_at`: menandai role sudah memperoleh baseline.
- `roles.permissions_customized_at`: menandai konfigurasi diubah Super Admin.
- `PermissionSeeder` idempoten: hanya menginisialisasi role yang belum pernah diinisialisasi;
  konfigurasi custom tidak ditimpa. Super Admin selalu dikembalikan ke baseline agar sistem
  tidak kehilangan kontrol administrasi.
- Setiap perubahan memanggil `PermissionRegistrar::forgetCachedPermissions()`.
- Audit tersimpan di `permission_change_logs` dan activity log (`permission`).

## 8. Batasan keamanan yang ditegakkan

Konfigurasi ditolak (HTTP 422 dengan penjelasan) bila mencoba:

1. Memberi Admin akses akun, pengaturan sekolah, role, atau pemantauan.
2. Memberi Editor/Validator pembuatan/persetujuan pengadaan, perubahan master data,
   penghapusan inventaris, atau perubahan katalog.
3. Memberi Operator validasi/persetujuan pengadaan, perubahan master data, penghapusan,
   atau akses administratif.
4. Memberi Member permission di luar peminjaman, katalog lihat, dan kegiatan.
5. Mengubah permission Super Admin.

Pemisahan tanggung jawab tambahan tetap berlaku di route/service: hanya Admin yang dapat
memberi keputusan akhir pengadaan/penghapusan/barang bernilai tinggi; Operator tetap dibatasi
`user_room_scopes`; pengguna tidak dapat menyetujui transaksi miliknya sendiri.

## 9. Deployment

Setelah `php artisan migrate`, jalankan:

```bash
php artisan db:seed --class=PermissionSeeder
```

Seeder aman dijalankan berulang dan tidak menimpa konfigurasi custom.

## 10. Pengujian

`tests/Feature/RolePermissionTest.php` menguji baseline lima role, view-only/hidden,
persistensi setelah seeder, penolakan eskalasi, audit perubahan, dan pembatasan akun Super Admin.
