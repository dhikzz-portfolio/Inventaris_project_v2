# Keputusan yang Menunggu Konfirmasi (PENDING_DECISIONS.md)

Dokumen ini mencatat keputusan bisnis yang **belum ditetapkan** oleh pengguna. Rekomendasi di bawah adalah usulan teknis, **bukan keputusan**. Implementasi yang sudah berjalan sengaja memakai opsi paling aman dan tidak mengaktifkan alur persetujuan yang belum disepakati.

Tanggal audit: 2 November 2026. Status dokumen: **PENDING CLARIFICATION**.

---

## 1. Master Data — menu yang boleh diakses Ketua Laboran

### Kondisi saat ini

Halaman Master Data (`/master`, `masters.index`) berisi empat kelompok data yang benar-benar ada di repository:

| Menu Master Data | Kondisi saat ini | Kebutuhan Ketua Laboran | Rekomendasi teknis | Status keputusan |
|---|---|---|---|---|
| Master Gedung | CRUD oleh Super Admin & Ketua Laboran | Identitas gedung menentukan kode lokasi/unit yang dipantau | **Lihat saja** | PENDING |
| Ruangan (lab/umum, jurusan, lantai) | CRUD oleh Super Admin & Ketua Laboran | Perlu untuk memetakan laboratorium lima jurusan | **Lihat saja** | PENDING |
| Jurusan / Kompetensi Keahlian | CRUD oleh Super Admin & Ketua Laboran | Perlu untuk membaca cakupan laboratorium | **Lihat saja** | PENDING |
| Kategori Barang | CRUD oleh Super Admin & Ketua Laboran | Perlu untuk membaca katalog & laporan | **Lihat saja** | PENDING |

### Pilihan yang tersedia

| Opsi | Dampak |
|---|---|
| A. Ketua Laboran tetap CRUD penuh | Melanggar prinsip pemisahan kewenangan administratif; risiko perubahan identitas lokasi yang memengaruhi kode inventaris. |
| B. Ketua Laboran hanya melihat (diterapkan sekarang) | Aman, tidak memblokir pemantauan; perubahan data tetap terkonsentrasi pada Wakasek/Super Admin. |
| C. Sebagian boleh dikelola (mis. ruangan laboratorium) | Perlu daftar eksplisit; belum ada keputusan. Jika dipilih, backend harus memvalidasi ruangan mana yang boleh diubah. |
| D. Sembunyikan seluruh menu | Bertentangan dengan kebutuhan pemantauan; juga menyembunyikan informasi yang sah. |

### Yang sudah diimplementasikan (opsi B)

- `GET /master` tetap dapat diakses Ketua Laboran dan Super Admin.
- Semua endpoint mutasi (`masters.store-*`, `masters.update-*`, `masters.destroy-*`) dipindahkan ke grup `role:SUPER_ADMIN`.
- Halaman menampilkan penanda "Akses lihat saja" dan menyembunyikan form bagi Ketua Laboran.
- Pembatasan diterapkan di route + controller, bukan hanya penyembunyian menu.

**Menunggu keputusan:** apakah ada menu yang harus tetap dapat dikelola Ketua Laboran (dan batasnya).

---

## 2. Super Admin Pengguna — batas kewenangan atas Super Admin utama

### Kondisi saat ini

Role `SUPER_ADMIN_PENGGUNA` ditambahkan terpisah dari `SUPER_ADMIN`. Role ini dapat membuka `/pengguna` dan mengelola akun, tetapi tidak memiliki akses operasional inventaris (katalog, pengadaan, mutasi, peminjaman, pengembalian, penghapusan, laporan, kegiatan).

### Matriks permission (diusulkan, status PENDING)

| Tindakan | Super Admin utama | Super Admin Pengguna | Status |
|---|---|---|---|
| Melihat daftar & detail akun | Ya | Ya | Disetujui implisit (fitur diminta) |
| Menambah akun non-Super Admin | Ya | Ya | Disetujui implisit |
| Mengubah akun non-Super Admin | Ya | Ya | Disetujui implisit |
| Menonaktifkan/mengaktifkan akun non-Super Admin | Ya | Ya | Disetujui implisit |
| Mengelola role & cakupan ruangan | Ya | Ya (kecuali jadi Super Admin) | Disetujui implisit |
| Membuat akun Super Admin utama | Ya | **Tidak** | PENDING |
| Mengubah role menjadi Super Admin utama | Ya | **Tidak** | PENDING |
| Mengubah akun Super Admin utama | Ya | **Tidak** | PENDING |
| Menonaktifkan/menghapus Super Admin utama | Ya (bukan akun terakhir) | **Tidak** | PENDING |

### Pilihan yang tersedia

| Opsi | Dampak |
|---|---|
| A. Super Admin Pengguna boleh mengelola Super Admin utama | Risiko privilege escalation; satu akun admin dapat mengambil alih kendali sistem. |
| B. Tidak boleh (diterapkan sekarang) | Aman; perlu eskalasi ke Super Admin utama untuk perubahan akun inti. |
| C. Boleh membuat, tetapi tidak boleh mengubah/menonaktifkan | Perlu aturan tambahan; belum ada keputusan. |

### Perlindungan yang sudah berjalan di backend

- Role Super Admin hanya dapat ditetapkan oleh Super Admin utama.
- Akun Super Admin utama tidak dapat diubah/dihapus oleh Super Admin Pengguna (403).
- Super Admin tidak dapat menurunkan role atau menonaktifkan akunnya sendiri.
- Sistem menolak menurunkan/menonaktifkan/menghapus satu-satunya Super Admin aktif.
- Setiap perubahan mencatat aktor, akun target, role/status sebelum–sesudah, dan cakupan ruangan; password tidak dicatat.

**Menunggu keputusan:** apakah Super Admin Pengguna boleh membuat / mengubah / menonaktifkan akun Super Admin utama.

---

## 3. Persetujuan perbaikan rusak ringan

### Kondisi saat ini

- Laporan rusak ringan disimpan sebagai catatan pemantauan dengan status `DILAPORKAN` → `DIPERIKSA` → `DALAM_PERBAIKAN` → `SELESAI`.
- Tidak ada tahap persetujuan perbaikan yang diaktifkan.
- Kondisi unit diperbarui ke `RUSAK_RINGAN` dan kondisi sebelumnya disimpan pada laporan + riwayat.

### Pilihan yang tersedia

| Opsi | Dampak |
|---|---|
| A. Langsung menjadi catatan pemantauan (diterapkan sekarang) | Cepat, sederhana, tidak ada risiko alur persetujuan yang salah. |
| B. Wajib persetujuan sebelum perbaikan | Perlu keputusan siapa penyetuju (Ketua Laboran? Wakasek?), notifikasi, dan status baru. Belum diimplementasikan. |

**Menunggu keputusan:** opsi A atau B, dan bila B: siapa penyetuju serta batas nominal/kategori.

---

## 4. Konfirmasi Ketua Laboran untuk laporan rusak berat

### Kondisi saat ini

- Laporan rusak berat dapat dibuat Laboran/K3 pada ruang lingkupnya dan langsung terlihat oleh Ketua Laboran serta Wakasek.
- Tidak ada transisi konfirmasi/persetujuan oleh Ketua Laboran.
- Penghapusan tetap melalui menu Inventaris Keluar (`write_offs`) dengan persetujuan Wakasek.

### Pilihan yang tersedia

| Opsi | Dampak |
|---|---|
| A. Ketua Laboran hanya menerima pemberitahuan/visibilitas (diterapkan sekarang) | Tidak menambah hambatan; laporan segera terlihat. |
| B. Laporan harus dikonfirmasi Ketua Laboran sebelum diteruskan ke Wakasek | Perlu status baru (mis. `MENUNGGU_KONFIRMASI_KETUA`), notifikasi, dan aturan transisi. Belum diimplementasikan. |

**Menunggu keputusan:** opsi A atau B. Bila B, perlu definisi apakah konfirmasi juga berlaku untuk laporan rusak ringan.

---

## Catatan implementasi

- Semua keputusan di atas ditandai **PENDING**; tidak ada alur persetujuan yang dikarang.
- Perubahan status pengadaan, kerusakan, dan akun divalidasi di backend berdasarkan status sebelumnya, role, dan kepemilikan/ruang lingkup.
- Setiap transisi penting dicatat pada activity log (`pengadaan`, `kerusakan`, `pengguna`) dan/atau `damage_report_logs`.
- **V6 (3 November 2026)**: penerima notifikasi ditentukan backend berdasarkan role/cakupan dan tautan diperiksa sebelum diarahkan; ini perbaikan navigasi, **bukan** perubahan keputusan bisnis di atas. Notifikasi kerusakan dan perubahan akun sudah dikirim ke pihak yang berwenang tanpa mengaktifkan alur persetujuan yang masih PENDING.
