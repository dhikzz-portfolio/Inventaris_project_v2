<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template impor data unit dalam format XLSX berformat tabel.
 *
 * Sheet "Data Unit" hanya memuat baris header dan dua contoh yang wajib
 * diganti. Sheet "Petunjuk" menjelaskan kolom, nilai yang diterima, dan
 * hubungan dengan file ekspor laporan bulanan.
 */
class UnitImportTemplateExport implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new class implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
            {
                /** @return list<string> */
                public function headings(): array
                {
                    return [
                        'kode_inventaris', 'nama_barang', 'kategori', 'gedung', 'lantai', 'no_ruangan',
                        'kondisi', 'status', 'tahun', 'sumber_dana', 'no_register',
                    ];
                }

                /** @return list<list<mixed>> */
                public function array(): array
                {
                    return [
                        ['G1-01-001-01', 'Komputer Lab (contoh)', 'Elektronik', 'G', 1, 1, 'Baik', 'Aktif', 2024, 'BOS', 'REG-001'],
                        ['G1-01-001-02', 'Komputer Lab (contoh)', 'Elektronik', 'G', 1, 1, 'Rusak Ringan', 'Aktif', 2024, 'BOS', 'REG-002'],
                    ];
                }

                public function title(): string
                {
                    return 'Data Unit';
                }

                public function styles(Worksheet $sheet): array
                {
                    return [
                        1 => [
                            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        ],
                    ];
                }

                public function registerEvents(): array
                {
                    return [
                        AfterSheet::class => function (AfterSheet $event): void {
                            $sheet = $event->sheet->getDelegate();

                            $table = new Table('A1:K3');
                            $table->setShowHeaderRow(true);
                            $table->setStyle(new TableStyle(TableStyle::TABLE_STYLE_MEDIUM2));

                            $sheet->addTable($table);
                            $sheet->freezePane('A2');
                            $sheet->getRowDimension(1)->setRowHeight(24);
                        },
                    ];
                }
            },
            new class implements FromArray, WithColumnWidths, WithStyles, WithTitle
            {
                /** @return array<string, int|float> */
                public function columnWidths(): array
                {
                    return ['A' => 22, 'B' => 110];
                }

                /** @return list<list<mixed>> */
                public function array(): array
                {
                    return [
                        ['Panduan Impor Data Unit'],
                        [''],
                        ['Kolom', 'Keterangan'],
                        ['kode_inventaris', 'Wajib. Kode unik unit, contoh G1-01-001-01. Format: Gedung+Lantai-Ruang-Kode Jenis-Nomor Unit.'],
                        ['nama_barang', 'Wajib saat menambah unit baru. Harus sama dengan nama katalog yang sudah ada.'],
                        ['kategori', 'Opsional. Hanya informasi; penentuan katalog memakai nama_barang.'],
                        ['gedung', 'Wajib saat menambah unit baru. Kode huruf gedung, contoh G.'],
                        ['lantai', 'Wajib saat menambah unit baru. Berupa angka, contoh 1.'],
                        ['no_ruangan', 'Wajib saat menambah unit baru. Berupa angka, contoh 1 atau 02.'],
                        ['kondisi', 'Wajib. Nilai: Baik, Rusak Ringan, atau Rusak Berat.'],
                        ['status', 'Wajib. Nilai: Aktif, Sedang Dipinjam, atau Hilang.'],
                        ['tahun', 'Opsional. Tahun perolehan 4 digit, contoh 2024.'],
                        ['sumber_dana', 'Opsional. Contoh BOS, APBD, atau Komite.'],
                        ['no_register', 'Opsional. Nomor register barang.'],
                        [''],
                        ['Cara pakai'],
                        ['1. Ganti dua baris contoh dengan data sebenarnya, atau hapus baris contoh.'],
                        ['2. Jangan mengubah nama kolom pada baris pertama.'],
                        ['3. Unggah kembali file ini pada halaman Impor Data Unit dan periksa pratinjau.'],
                        ['4. Impor hanya berjalan bila seluruh baris valid.'],
                        [''],
                        ['Catatan penting'],
                        ['File Excel laporan bulanan juga dapat diimpor kembali. Sistem memakai sheet "Unit" (sheet pertama) dan mengabaikan sheet lain seperti Peminjaman, Kerusakan, Mutasi, Penghapusan, Pengadaan, dan Laporan Kerusakan.'],
                        ['Impor hanya memperbarui kondisi, status, tahun, sumber dana, dan nomor register unit yang sudah ada. Gunakan mode "Tambahkan unit baru" hanya saat memulihkan data ke database kosong.'],
                        ['Impor ini bukan pengganti backup database.'],
                    ];
                }

                public function title(): string
                {
                    return 'Petunjuk';
                }

                public function styles(Worksheet $sheet): array
                {
                    return [
                        1 => ['font' => ['bold' => true, 'size' => 14]],
                        3 => ['font' => ['bold' => true]],
                        15 => ['font' => ['bold' => true]],
                        21 => ['font' => ['bold' => true]],
                        'B1:B30' => ['alignment' => ['wrap' => true, 'vertical' => 'top']],
                    ];
                }
            },
        ];
    }
}
