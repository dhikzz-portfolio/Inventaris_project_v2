<?php

namespace App\Exports;

use App\Models\Item;
use App\Models\Room;
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
 * Sheet "Data Unit" memuat baris header dan dua contoh yang memakai ruangan
 * nyata dari master data. Contoh ditandai dengan akhiran "(contoh)" dan akan
 * ditolak saat pratinjau agar tidak membuat data palsu. Sheet "Petunjuk"
 * menjelaskan kolom, nilai yang diterima, serta ruangan dan katalog yang
 * tersedia.
 */
class UnitImportTemplateExport implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        $rooms = Room::query()
            ->whereNotNull('room_no')
            ->where('room_no', '>', 0)
            ->where('floor', '>', 0)
            ->orderBy('floor')
            ->orderBy('room_no')
            ->limit(15)
            ->get()
            ->map(fn (Room $room): array => [
                'location' => $room->locationCode(),
                'name' => $room->name,
                'building' => $room->buildingCode(),
                'floor' => (int) $room->floor,
                'room_no' => (int) $room->room_no,
            ])
            ->values()
            ->all();

        $catalogs = Item::query()
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (Item $item): array => [
                'name' => $item->name,
                'category' => $item->category?->name ?? '',
            ])
            ->values()
            ->all();

        $sampleRoom = $rooms[0] ?? ['building' => 'G', 'floor' => 1, 'room_no' => 1];
        $sampleCatalog = $catalogs[0] ?? ['name' => 'Contoh Barang', 'category' => 'Elektronik'];

        $sampleRows = [
            ['', $sampleCatalog['name'].' (contoh)', $sampleCatalog['category'], '', '', $sampleRoom['building'], $sampleRoom['floor'], $sampleRoom['room_no'], 'Baik', 'Aktif', now()->year, '', '', ''],
            ['', $sampleCatalog['name'].' (contoh)', $sampleCatalog['category'], '', '', $sampleRoom['building'], $sampleRoom['floor'], $sampleRoom['room_no'], 'Rusak Ringan', 'Aktif', now()->year, '', '', ''],
        ];

        return [
            new class($sampleRows) implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
            {
                /** @param list<list<mixed>> $sampleRows */
                public function __construct(private array $sampleRows) {}

                /** @return list<string> */
                public function headings(): array
                {
                    return [
                        'kode_inventaris', 'nama_barang', 'kategori', 'merek', 'tipe_model', 'gedung', 'lantai', 'no_ruangan',
                        'kondisi', 'status', 'tahun', 'sumber_dana', 'no_register', 'keterangan',
                    ];
                }

                /** @return list<list<mixed>> */
                public function array(): array
                {
                    return $this->sampleRows;
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

                            $table = new Table('A1:N3');
                            $table->setShowHeaderRow(true);
                            $table->setStyle(new TableStyle(TableStyle::TABLE_STYLE_MEDIUM2));

                            $sheet->addTable($table);
                            $sheet->freezePane('A2');
                            $sheet->getRowDimension(1)->setRowHeight(24);
                        },
                    ];
                }
            },
            new class($rooms, $catalogs) implements FromArray, WithColumnWidths, WithStyles, WithTitle
            {
                /**
                 * @param  list<array{location: string, name: string, building: string, floor: int, room_no: int}>  $rooms
                 * @param  list<array{name: string, category: string}>  $catalogs
                 */
                public function __construct(private array $rooms, private array $catalogs) {}

                /** @return array<string, int|float> */
                public function columnWidths(): array
                {
                    return ['A' => 22, 'B' => 110];
                }

                /** @return list<list<mixed>> */
                public function array(): array
                {
                    $availableRooms = collect($this->rooms)
                        ->map(fn (array $room): string => "{$room['location']} ({$room['name']})")
                        ->implode('; ');

                    $availableCatalogs = collect($this->catalogs)
                        ->map(fn (array $catalog): string => $catalog['name'].($catalog['category'] !== '' ? " - {$catalog['category']}" : ''))
                        ->implode('; ');

                    return [
                        ['Panduan Impor Data Unit'],
                        [''],
                        ['Kolom', 'Keterangan'],
                        ['kode_inventaris', 'Opsional saat menambah unit baru. Kosongkan agar sistem membuat kode otomatis dari lokasi dan katalog. Wajib saat memperbarui unit yang sudah ada. Format: Gedung+Lantai-Ruang-Kode Jenis-Nomor Unit, contoh G1-01-001-01.'],
                        ['nama_barang', 'Wajib saat menambah unit baru. Dicocokkan ke katalog yang ada; bila belum terdaftar, katalog baru dibuat otomatis.'],
                        ['kategori', 'Opsional. Dipakai sebagai kategori bila katalog baru dibuat; jika kosong memakai kategori "Tanpa Kategori".'],
                        ['merek', 'Opsional. Merek barang untuk unit ini. Bila kosong memakai merek katalog, atau "Tanpa Merek" bila katalog juga tidak punya merek.'],
                        ['tipe_model', 'Opsional. Tipe/model barang untuk unit ini. Bila kosong memakai tipe katalog, atau "Tidak Ditentukan" bila katalog juga tidak punya tipe.'],
                        ['gedung', 'Wajib saat menambah unit baru. Kode huruf gedung, contoh F.'],
                        ['lantai', 'Wajib saat menambah unit baru. Berupa angka, contoh 1.'],
                        ['no_ruangan', 'Wajib saat menambah unit baru. Berupa angka, contoh 2.'],
                        ['kondisi', 'Wajib. Nilai: Baik, Rusak Ringan, atau Rusak Berat.'],
                        ['status', 'Wajib. Nilai: Aktif, Sedang Dipinjam, atau Hilang.'],
                        ['tahun', 'Opsional. Tahun perolehan 4 digit, contoh 2024.'],
                        ['sumber_dana', 'Opsional. Contoh BOS, APBD, atau Komite.'],
                        ['no_register', 'Opsional. Nomor register barang.'],
                        ['keterangan', 'Opsional. Catatan/keterangan unit. Kolom Keterangan pada berkas ekspor laporan bulanan juga dibaca saat impor.'],
                        [''],
                        ['Cara pakai'],
                        ['1. Baris contoh memakai nama barang berakhiran "(contoh)" dan akan ditolak. Ganti dengan data sebenarnya atau hapus baris contoh.'],
                        ['2. Untuk menambah unit baru, kosongkan kode_inventaris dan isi gedung, lantai, serta no_ruangan sesuai daftar di bawah.'],
                        ['3. Jangan mengubah nama kolom pada baris pertama.'],
                        ['4. Unggah kembali file ini pada halaman Impor Data Unit dan periksa pratinjau.'],
                        ['5. Impor hanya berjalan bila seluruh baris valid.'],
                        [''],
                        ['Catatan penting'],
                        ['Sistem membuat kode otomatis berdasarkan gedung, lantai, no. ruangan, kode jenis barang, dan nomor unit berikutnya. Nama barang yang belum ada di katalog juga dibuatkan katalog baru secara otomatis.'],
                        ['File Excel laporan bulanan juga dapat diimpor kembali. Sistem memakai sheet "Unit" (sheet pertama) dan mengabaikan sheet lain seperti Peminjaman, Kerusakan, Mutasi, Penghapusan, Pengadaan, dan Laporan Kerusakan.'],
                        ['Mode "Perbarui data yang sudah ada" hanya mengubah kondisi, status, tahun, sumber dana, nomor register, dan keterangan unit yang sudah terdaftar. Mode "Tambahkan unit baru" membuat unit baru beserta katalog dan kodenya bila diperlukan.'],
                        ['Merek dan tipe per unit disimpan sebagai spesifikasi katalog. Kolom merek/tipe_model juga dikenali dari berkas ekspor laporan bulanan, sehingga unit lama yang belum punya spesifikasi akan dilengkapi saat impor mode perbarui.'],
                        ['Kolom keterangan tersimpan sebagai catatan unit. Pada mode perbarui, keterangan hanya diubah bila kolomnya diisi; keterangan lama tidak dikosongkan oleh sel kosong.'],
                        ['Impor ini bukan pengganti backup database.'],
                        [''],
                        ['Data master saat ini'],
                        ['Ruangan tersedia', $availableRooms !== '' ? $availableRooms : 'Belum ada ruangan dengan identitas lengkap.'],
                        ['Katalog tersedia', $availableCatalogs !== '' ? $availableCatalogs : 'Belum ada katalog.'],
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
                        19 => ['font' => ['bold' => true]],
                        26 => ['font' => ['bold' => true]],
                        33 => ['font' => ['bold' => true]],
                        'B1:B35' => ['alignment' => ['wrap' => true, 'vertical' => 'top']],
                    ];
                }
            },
        ];
    }
}
