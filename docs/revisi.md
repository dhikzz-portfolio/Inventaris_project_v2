# Dokumen Revisi — Perbaikan Cetak Label & Dokumen

Status: **Menunggu implementasi**
Tanggal audit: 21 Oktober 2026
Ruang lingkup: label satuan, label massal, dokumen BAST/Surat Peminjaman, dan laporan PDF.

Dokumen ini adalah acuan perbaikan yang **akan dan harus** dikerjakan. Semua temuan di bawah sudah diverifikasi dengan render Dompdf langsung (bukan asumsi).

---

## 1. Keputusan yang sudah disepakati

| Topik                    | Keputusan                                                     |
| ------------------------ | ------------------------------------------------------------- |
| Layout label massal      | **2 kolom** per baris A4                                      |
| Dokumen peminjaman rutin | **Surat Peminjaman** dengan nomor `SP/{id}/{m}/{Y}`           |
| Dokumen kegiatan         | Tetap **BAST** dengan nomor `BAST/{id}/{m}/{Y}`               |
| Perbaikan logika         | Disertakan: filter loan batal, hitungan terlambat, page-break |

---

## 2. Ringkasan bug dan prioritas

| ID  | Area                      | Gejala utama                                                                                       | Dampak                                                   | Prioritas |
| --- | ------------------------- | -------------------------------------------------------------------------------------------------- | -------------------------------------------------------- | --------- |
| L1  | Label satuan              | Label `256×116pt` dirender di area efektif `192×52pt` karena margin halaman default Dompdf `1.2cm` | Label terpotong kanan dan bawah                          | P0        |
| L2  | Label satuan              | Kode lokasi 23 karakter @14pt = `204.7pt`, kolom kode hanya ±`116pt`                               | Teks meluber menimpa area QR                             | P0        |
| L3  | Label satuan              | Footer `position:absolute` di dalam `table-cell`                                                   | Footer menabrak border bawah dan nama instansi terpotong | P0        |
| L4  | Label satuan              | QR terdorong ke `x≈321` pada halaman `260pt`                                                       | QR tidak tercetak                                        | P0        |
| M1  | Label massal              | `td width:33.33%` tidak dihormati; kolom ke-3 mulai `x≈619` pada halaman `595pt`                   | Label ke-3 per baris hilang                              | P0        |
| M2  | Label massal              | Footer absolute berada di `y≈0.59`, `-113`, `-227`                                                 | Nama instansi tidak muncul / di luar label               | P0        |
| M3  | Label massal              | Tidak ada margin halaman; label di `x≈4pt`, `y≈4pt`                                                | Terpotong area non-print printer                         | P0        |
| M4  | Label massal              | `page-break-inside: avoid` pada `div` di dalam `td` tidak bekerja                                  | Baris label dapat terbelah antar halaman                 | P1        |
| D1  | BAST, BAST event, laporan | `* { margin:0 }` membuat margin halaman `0`                                                        | Konten menyentuh tepi kertas dan terpotong printer       | P1        |
| D2  | BAST non-event            | Judul "SURAT PEMINJAMAN BARANG" tetapi nomor, paragraf, dan footer memakai BAST                    | Dokumen tidak konsisten                                  | P1        |
| D3  | BAST event                | Loan `DIBATALKAN`/`DITOLAK` yang punya `bast_path` ikut masuk BAST gabungan                        | Dokumen memuat peminjaman yang tidak sah                 | P1        |
| D4  | BAST event                | `h3`, tabel per loan, dan blok TTD tanpa proteksi page-break                                       | Section/TTD terpotong antar halaman                      | P1        |
| R1  | Laporan PDF               | `terlambat` menghitung loan `PENDING`/`DITOLAK`/`DIBATALKAN` yang lewat jatuh tempo                | Angka laporan salah                                      | P2        |

---

## 3. Temuan teknis (bukti audit)

### 3.1 Label satuan

File: `resources/views/pdf/label_single.blade.php`, `app/Services/LabelService.php:79`

- Halaman diset `setPaper([0, 0, 260, 120], 'pt')`.
- Dompdf memakai `@page { margin: 1.2cm }` dari stylesheet bawaan, sehingga area konten hanya ±`192×52pt`.
- Label `256×116pt` dirender pada `x=36..292`, `y=85..-31`:
    - sisi kanan keluar halaman `32pt`;
    - sisi bawah keluar halaman `31pt`.
- Kolom kode hanya ±`116pt`; kebutuhan:
    - kode lokasi `12.24.13.08.01.07.01.01` @14pt = `193.2pt` + letter-spacing `11.5pt` = `204.7pt`;
    - kode barang `01.01.16.03.01.0009` @11pt = `133.4pt`.
- Tabel melebar karena kode panjang; QR digambar di `x≈321` (halaman `260pt`) sehingga tidak terlihat.
- Footer absolute dihitung relatif ke halaman/sel yang melebar: `x=79..345.7`, `y=2..14.2`; border bawah label di `y=4` sehingga footer menabrak border dan terpotong.

### 3.2 Label massal

File: `resources/views/pdf/label_massal.blade.php`

- `td { width:33.33% }` tidak dihormati karena auto table layout ditarik min-content kode panjang.
- Lebar minimum tiap sel ±`295pt`; kolom ke-3 mulai `x≈619` pada A4 `595.28pt`.
- Footer absolute:
    - baris 1: `y≈0.59` (dasar halaman, di luar label);
    - baris 2: `y≈-113.4`;
    - baris 3: `y≈-227.4`.
- Kolom kode efektif hanya ±`70.4pt`; kebutuhan kode lokasi `158.7pt` dan kode barang `102.6pt`.
- Tidak ada `@page` margin; label paling kiri/atas berada di `x≈4pt`, `y≈4pt`.
- `page-break-inside: avoid` pada `div` di dalam `td` tidak mencegah pemisahan baris.

### 3.3 BAST, BAST event, laporan

File: `resources/views/pdf/bast.blade.php`, `bast_event.blade.php`, `report.blade.php`

- Selector `* { margin: 0; padding: 0; }` menimpa margin halaman Dompdf menjadi `0`.
- Hasil render BAST:
    - info table di `x=1.5`;
    - footer di `x=0`;
    - tabel di `x=0.75`.
- Konten tersebut berada di area non-print printer (umumnya 3–5 mm pertama), sehingga terpotong saat dicetak.

### 3.4 Ketidakkonsistenan dokumen non-event

File: `resources/views/pdf/bast.blade.php:47`, `:84-87`, `:127-130`

- Judul: `SURAT PEMINJAMAN BARANG`.
- Nomor: `BAST/{id}/{m}/{Y}`.
- Paragraf: "Demikian Berita Acara Serah Terima barang ini...".
- Footer: `Ref: BAST/{id}/{m}/{Y}`.

### 3.5 BAST event memuat loan batal

File: `app/Services/BastService.php:53-55`

```php
$activeLoans = $event->loans->filter(
    fn (Loan $loan) => $loan->bast_path !== null
        || in_array($loan->status->value, ['APPROVED', 'DIPINJAM', 'SELESAI'], true)
);
```

- Filter pertama (`bast_path !== null`) tidak memandang status.
- Loan yang pernah dibuatkan BAST lalu `DIBATALKAN`/`DITOLAK` tetap ikut.

### 3.6 Hitungan terlambat

File: `app/Services/ReportService.php:111`

```php
'terlambat' => $loansInPeriod->filter(
    fn ($l) => $l->isOverdue() || $l->due_date->lt($l->returned_at ?? now())
)->count(),
```

- `isOverdue()` hanya untuk `APPROVED`/`DIPINJAM`.
- Klausa kedua menghitung semua status, termasuk `PENDING`, `DITOLAK`, dan `DIBATALKAN`.

---

## 4. Perbaikan yang harus dilakukan

### 4.1 `app/Services/LabelService.php`

- [ ] Tambah method `splitLocationCode(string $code): array`.
    - Pecah kode pada titik terdekat dari tengah (contoh 8 grup → 4 + 4).
    - Contoh hasil: `['12.24.13.08', '01.07.01.01']`.
    - Jika kode tidak memiliki titik, kembalikan satu baris.
- [ ] `printSingle()` mengirim `locationLines` ke view.
- [ ] `printMassal()` mengirim `locationLinesMap` (key: `item->id`) ke view.
- [ ] QR tetap memakai `route('items.show', $item)`.

**Kriteria terima:** kode lokasi 23 karakter tampil 2 baris; tidak ada perubahan pada isi QR.

### 4.2 `resources/views/pdf/label_single.blade.php`

- [ ] Hapus `margin: 0; padding: 0` dari selector `*`; sisakan `box-sizing: border-box`.
- [ ] Tambah `@page { margin: 0; }`.
- [ ] Struktur baru:
    - `.label` block `256×116pt`, border `2pt`, radius `4pt`.
    - `.head` tinggi ±`92pt`.
    - Tabel: logo `<td width="48">`, kode `<td>` (auto), QR `<td width="46">`.
    - Footer **normal flow** setelah `.head`, bukan `position:absolute`.
- [ ] Ukuran teks:
    - kode lokasi `14pt`, 2 baris;
    - kode barang `11pt`;
    - footer `7.5pt`;
    - QR image `42×42pt` di dalam sel `46pt`.
- [ ] Hapus `position: absolute`, `left`, `right`, `bottom` pada `.footer`.
- [ ] Hapus `letter-spacing` pada kode lokasi.

**Kriteria terima (hasil render):**

- Border label berada di `x=1..259`, `y=1..119` pada halaman `260×120pt`.
- Semua teks berada di dalam `0..260` dan `0..120`.
- QR berada di `x≈215..257` dan tidak terpotong.
- Footer berada di atas border bawah (`y > 4`) dan tidak menabrak border.
- Kode lokasi tampil 2 baris.

### 4.3 `resources/views/pdf/label_massal.blade.php`

- [ ] Hapus `margin: 0; padding: 0` dari selector `*`; sisakan `box-sizing`.
- [ ] Tambah `@page { margin: 8mm; }`.
- [ ] Ubah grid menjadi **2 kolom**:
    - `@foreach ($items->chunk(2) as $row)`;
    - `table.grid td { width: 50%; padding: 4pt; }`;
    - sel kosong: `@for ($i = 0; $i < (2 - $row->count()); $i++)`.
- [ ] Struktur label sama dengan label satuan:
    - `.label` tinggi `100pt`;
    - `.head` tinggi `78pt`;
    - logo `48pt`, QR `46pt`, kode auto;
    - footer normal flow.
- [ ] Ukuran teks:
    - kode lokasi `13pt`, 2 baris;
    - kode barang `9pt`;
    - footer `7pt`.
- [ ] Tambah `table.grid tr { page-break-inside: avoid; }`.
- [ ] Hapus footer absolute.

**Kriteria terima (hasil render):**

- 2 label per baris, 7 baris per A4 = **14 label/A4**.
- Semua border label berada di dalam margin `8mm`:
    - `x ≥ 22.68pt` dan `x ≤ 572.6pt`;
    - `y ≥ 22.68pt` dan `y ≤ 819.21pt`.
- Footer setiap label berada di dalam label.
- Tidak ada label yang terbelah antar halaman.

### 4.4 `app/Services/BastService.php`

- [ ] Tambah `documentNumber(Loan $loan): string`.
    - Non-event: `SP/{id}/{m/Y}`.
    - Event: `BAST/{id}/{m/Y}`.
- [ ] Tambah `documentTitle(Loan $loan): string`.
    - Non-event: `SURAT PEMINJAMAN BARANG`.
    - Event: `BERITA ACARA SERAH TERIMA (BAST)`.
- [ ] `generateForLoan()` memakai kedua method tersebut dan mengirim `documentNumber` ke view.
- [ ] Tambah `loansForEvent(Event $event): Collection` untuk filter loan.
- [ ] Filter event:
    - selalu kecualikan `DIBATALKAN` dan `DITOLAK`;
    - sertakan `APPROVED`, `DIPINJAM`, `SELESAI`, atau loan yang punya `bast_path`.
- [ ] `generateForEvent()` memakai `loansForEvent()`.

**Kriteria terima:**

- Dokumen rutin memakai nomor `SP/...` dan judul Surat Peminjaman.
- Dokumen event memakai nomor `BAST/...`.
- Loan `DIBATALKAN` dengan `bast_path` tidak muncul di BAST gabungan.

### 4.5 `resources/views/pdf/bast.blade.php`

- [ ] Hapus `margin: 0; padding: 0` dari selector `*`.
- [ ] Tambah `@page { margin: 36pt 45pt; }`.
- [ ] Ganti nomor hardcoded menjadi `Nomor: {{ $documentNumber }}`.
- [ ] Paragraf penutup kondisional:
    - event: "Demikian Berita Acara Serah Terima barang ini...";
    - non-event: "Demikian Surat Peminjaman barang ini...".
- [ ] Footer memakai `Ref: {{ $documentNumber }}`, bukan `Ref: BAST/...`.

**Kriteria terima:**

- Semua konten berada di dalam margin halaman (`x ≥ 45pt`, `x ≤ 550.28pt`).
- Footer tidak berada di `x=0`.
- Tidak ada teks BAST pada dokumen non-event selain label fitur internal.

### 4.6 `resources/views/pdf/bast_event.blade.php`

- [ ] Hapus `margin: 0; padding: 0` dari selector `*`.
- [ ] Tambah `@page { margin: 30pt 36pt; }`.
- [ ] Bungkus tiap loan dalam `<div class="loan-section">`.
- [ ] Tambah:
    - `.loan-section { page-break-inside: avoid; }`;
    - `table.data tr { page-break-inside: avoid; }`;
    - `.ttd { page-break-inside: avoid; }`.

**Kriteria terima:**

- Konten berada di dalam margin halaman.
- Section loan dan blok TTD tidak terbelah di tengah.

### 4.7 `resources/views/pdf/report.blade.php`

- [ ] Hapus `margin: 0; padding: 0` dari selector `*`.
- [ ] Tambah `@page { margin: 30pt 36pt; }` (A4 landscape).

**Kriteria terima:**

- Footer dan tabel tidak menyentuh tepi kertas.
- Konten berada di area aman printer (minimal 30pt dari tepi).

### 4.8 `app/Services/ReportService.php`

- [ ] Ubah hitungan `terlambat`:
    - `SELESAI`: hitung jika `returned_at !== null` dan `due_date < returned_at`;
    - `APPROVED`/`DIPINJAM`: hitung jika `isOverdue()`;
    - status lain: tidak dihitung.
- [ ] Tambah import `App\Enums\LoanStatus`.

**Kriteria terima:**

- Loan `PENDING`/`DITOLAK`/`DIBATALKAN` yang lewat jatuh tempo tidak menambah angka terlambat.
- Loan `SELESAI` yang dikembalikan terlambat tetap dihitung.

### 4.9 `app/Http/Controllers/LoanController.php` (minor)

- [ ] Nama file unduhan menyesuaikan jenis dokumen:
    - event: `BAST-{id}.pdf`;
    - non-event: `Surat-Peminjaman-{id}.pdf`.

---

## 5. Catatan teknis Dompdf (wajib dipatuhi saat implementasi)

1. **Jangan** memakai `* { margin: 0; padding: 0; }` pada template PDF.
    - Selector tersebut menimpa `@page` margin dan membuat konten menyentuh tepi kertas.
    - Gunakan `* { box-sizing: border-box; }` saja.
2. **Jangan** mengandalkan `table-layout: fixed`.
    - Dompdf membagi kolom secara merata dan mengabaikan `td width`.
3. **Jangan** memakai `position: absolute` di dalam `table-cell`.
    - Dompdf menghitung posisi relatif ke halaman/sel yang salah.
4. Gunakan `td width="..."` untuk kolom logo/QR dan biarkan kolom kode `auto`.
    - Pola ini menghasilkan lebar logo/QR yang stabil dan kolom kode menyerap sisa ruang.
5. Untuk label satuan, halaman harus memakai `@page { margin: 0; }` agar label `256×116pt` muat di halaman `260×120pt`.
6. Untuk label massal, gunakan `@page { margin: 8mm; }` agar label tidak masuk area non-print printer.

---

## 6. Rencana pengujian

### 6.1 Test otomatis

- [ ] `tests/Feature/EventAndBastTest.php`
    - nomor `SP` untuk loan non-event;
    - nomor `BAST` untuk loan event;
    - loan `DIBATALKAN` dengan `bast_path` tidak ikut `loansForEvent()`.
- [ ] `tests/Feature/LabelAndQrTest.php`
    - `splitLocationCode()` menghasilkan 2 baris untuk kode 23 karakter;
    - label satuan dan massal tetap menghasilkan PDF.
- [ ] `tests/Feature/ReportTest.php` (baru)
    - loan `PENDING` lewat jatuh tempo tidak dihitung `terlambat`;
    - loan `SELESAI` yang dikembalikan terlambat dihitung.

### 6.2 Verifikasi layout PDF

- [ ] Render label satuan, lalu cek:
    - QR image berada di dalam halaman;
    - kode lokasi 2 baris;
    - footer di atas border bawah.
- [ ] Render label massal, lalu cek:
    - 2 label per baris;
    - 7 baris per halaman;
    - footer tiap label berada di dalam label.
- [ ] Render BAST dan laporan, lalu cek:
    - semua teks/gambar berada di dalam margin;
    - footer tidak di `x=0`.

### 6.3 Perintah

```bash
php artisan test --compact --filter=LabelAndQrTest
php artisan test --compact --filter=EventAndBastTest
php artisan test --compact --filter=ReportTest
vendor/bin/pint --dirty --format agent
```

---

## 7. Checklist implementasi

- [ ] `LabelService::splitLocationCode()` dan passing data ke view.
- [ ] Rewrite layout `label_single.blade.php`.
- [ ] Rewrite layout `label_massal.blade.php` menjadi 2 kolom.
- [ ] `@page` margin pada seluruh template PDF.
- [ ] Nomor/title/wording `SP` vs `BAST`.
- [ ] Filter loan event.
- [ ] Page-break BAST event.
- [ ] Perbaikan `terlambat`.
- [ ] Test baru dan test lama lulus.
- [ ] `pint` bersih.
- [ ] Verifikasi koordinat PDF.

---

## 8. Di luar ruang lingkup revisi ini

- Cleanup otomatis file BAST event lama yang menumpuk.
- Fallback logo pada BAST/laporan ketika logo instansi belum diunggah.
- Penambahan metrik `disetujui`/`ditolak` yang saat ini dihitung tetapi tidak ditampilkan di PDF laporan.
- Perubahan pada fitur pemindaian QR atau alur peminjaman.

---

## 9. Referensi file

- `app/Services/LabelService.php`
- `app/Services/BastService.php`
- `app/Services/ReportService.php`
- `app/Http/Controllers/LoanController.php`
- `resources/views/pdf/label_single.blade.php`
- `resources/views/pdf/label_massal.blade.php`
- `resources/views/pdf/bast.blade.php`
- `resources/views/pdf/bast_event.blade.php`
- `resources/views/pdf/report.blade.php`
- `tests/Feature/LabelAndQrTest.php`
- `tests/Feature/EventAndBastTest.php`
