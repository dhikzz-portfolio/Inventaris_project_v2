<?php

namespace App\Exports;

use App\Models\InstitutionSetting;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Ekspor laporan bulanan multi-sheet: unit, peminjaman, kerusakan pengembalian,
 * mutasi, penghapusan, pengadaan, dan laporan kerusakan.
 *
 * `tables()` adalah SATU-SATUNYA sumber bentuk tabel. PDF dan pratinjau Excel
 * memakai definisi yang sama sehingga jumlah baris, kode unit, keterangan,
 * status, dan lokasi tidak mungkin berbeda antar format.
 *
 * Setiap sheet diawali kop surat dan diakhiri blok tanda tangan. Baris header
 * tabel berada di baris ke-8 dan blok tanda tangan dibuka penanda
 * self::SIGNATURE_MARKER sehingga impor dapat melewati kop dan berhenti
 * sebelum tanda tangan.
 */
class MonthlyReportExport implements Export, WithMultipleSheets
{
    /** Penanda awal blok tanda tangan; dipakai ReportImportService. */
    public const SIGNATURE_MARKER = 'Tanda Tangan';

    /** Baris header tabel (1-based) setelah kop surat. */
    public const HEADING_ROW = 8;

    public function __construct(private readonly array $data) {}

    /**
     * @return list<array{title: string, headings: list<string>, rows: list<list<mixed>>}>
     */
    public static function tables(array $data): array
    {
        return [
            [
                'title' => 'Unit',
                'headings' => [
                    'Kode Inventaris', 'Nama Barang', 'Kategori', 'Merek', 'Tipe/Model', 'Keterangan',
                    'Ruangan', 'Gedung', 'Lantai', 'No. Ruangan', 'Kondisi', 'Status', 'Tahun',
                    'Sumber Dana', 'No. Register', 'Nilai Tinggi',
                ],
                'rows' => collect($data['units'] ?? [])->map(fn ($row) => [
                    $row->inventory_code,
                    $row->catalog?->name,
                    $row->catalog?->category?->name,
                    $row->specification?->brandLabel() ?: $row->catalog?->brand,
                    $row->specification?->modelLabel() ?: $row->catalog?->model_type,
                    $row->notes,
                    $row->room?->name,
                    $row->room?->buildingCode(),
                    $row->room?->floor,
                    $row->room?->room_no,
                    $row->condition->label(),
                    $row->status->label(),
                    $row->acquisition_year,
                    $row->funding_source,
                    $row->register_no,
                    $row->catalog?->is_high_value ? 'Ya' : 'Tidak',
                ])->all(),
            ],
            [
                'title' => 'Peminjaman',
                'headings' => [
                    'ID Pinjam', 'Dibuat Oleh', 'Dipinjam Oleh', 'Identitas Peminjam', 'Tujuan',
                    'Kegiatan', 'Mulai', 'Jatuh Tempo', 'Dikembalikan', 'Status',
                    'Total Dipinjam', 'Total Kembali',
                ],
                'rows' => collect($data['loans'] ?? [])->map(fn ($row) => [
                    $row->id,
                    $row->requesterDisplayName(),
                    $row->borrowerDisplayName(),
                    $row->borrowerIdentityLabel() ?: null,
                    $row->purpose,
                    $row->event?->name,
                    $row->start_date?->format('d/m/Y H:i'),
                    $row->due_date?->format('d/m/Y H:i'),
                    $row->returned_at?->format('d/m/Y H:i'),
                    $row->status->label(),
                    $row->loanItems->sum('qty_borrowed'),
                    $row->loanItems->sum(fn ($li) => (int) $li->qty_returned),
                ])->all(),
            ],
            [
                'title' => 'Kerusakan',
                'headings' => ['ID Internal', 'Barang', 'Kondisi Akhir', 'Jumlah Rusak', 'Peminjam', 'Tanggal Kembali'],
                'rows' => collect($data['damageRows'] ?? [])->map(fn ($row) => [
                    $row->item?->internal_id,
                    $row->item?->name,
                    $row->condition_in?->label(),
                    $row->qty_damaged,
                    $row->loan?->borrowerDisplayName(),
                    $row->loan?->returned_at?->format('d/m/Y H:i'),
                ])->all(),
            ],
            [
                'title' => 'Mutasi Lokasi',
                'headings' => ['Tanggal', 'ID Internal', 'Barang', 'Dari Ruangan', 'Ke Ruangan', 'Jumlah', 'Petugas', 'Catatan'],
                'rows' => collect($data['movements'] ?? [])->map(fn ($row) => [
                    $row->moved_at?->format('d/m/Y H:i'),
                    $row->item?->internal_id,
                    $row->item?->name,
                    $row->fromRoom?->code,
                    $row->toRoom?->code,
                    $row->quantity,
                    $row->mover?->name,
                    $row->note,
                ])->all(),
            ],
            [
                'title' => 'Penghapusan',
                'headings' => ['Tanggal', 'ID Transaksi', 'Katalog', 'Kode Unit', 'Merek', 'Tipe/Model', 'Spesifikasi', 'Lokasi', 'Jumlah', 'Alasan', 'Pengaju', 'Penyetuju', 'Catatan'],
                'rows' => collect($data['writeOffRows'] ?? [])->map(fn ($row) => [
                    $row->date?->format('d/m/Y H:i'),
                    $row->write_off_id,
                    $row->catalog,
                    $row->code ?: '—',
                    $row->brand,
                    $row->model_type,
                    $row->specification,
                    $row->room,
                    $row->quantity,
                    $row->reason,
                    $row->requester,
                    $row->approver,
                    $row->legacy ? 'Transaksi lama — data lama tanpa rincian unit' : null,
                ])->all(),
            ],
            [
                'title' => 'Pengadaan',
                'headings' => ['Tanggal', 'Katalog', 'Kategori', 'Lokasi', 'Jumlah Diajukan', 'Jumlah Diterima', 'Status', 'Pengaju', 'Penyetuju', 'Catatan'],
                'rows' => collect($data['acquisitionRows'] ?? [])->map(fn ($row) => [
                    $row->created_at?->format('d/m/Y H:i'),
                    $row->name,
                    $row->category?->name,
                    $row->room?->code,
                    $row->quantity,
                    $row->acquisition_received_quantity,
                    $row->acquisitionStatusEnum()?->label() ?? $row->acquisition_status,
                    $row->requester?->name,
                    $row->approver?->name,
                    $row->acquisition_note,
                ])->all(),
            ],
            [
                'title' => 'Laporan Kerusakan',
                'headings' => ['Tanggal Lapor', 'Kode Unit', 'Barang', 'Lokasi', 'Klasifikasi', 'Status Penanganan', 'Deskripsi', 'Pelapor', 'Penangan', 'Catatan'],
                'rows' => collect($data['damageReportRows'] ?? [])->map(fn ($row) => [
                    $row->created_at?->format('d/m/Y H:i'),
                    $row->unit?->inventory_code,
                    $row->unit?->catalog?->name,
                    $row->unit?->room?->code,
                    $row->classification->label(),
                    $row->status->label(),
                    $row->description,
                    $row->reporter?->name,
                    $row->handler?->name,
                    $row->resolution_note,
                ])->all(),
            ],
        ];
    }

    public function sheets(): array
    {
        $setting = $this->data['setting'] ?? InstitutionSetting::current();
        $periodLabel = (string) ($this->data['periodLabel'] ?? '');
        $scopeLabel = (string) ($this->data['scopeLabel'] ?? '');

        // Logo hanya dipakai bila berkasnya ada dan formatnya dapat disisipkan
        // PhpSpreadsheet; selain itu kop tetap memakai seluruh lebar tabel.
        $logoPath = null;

        if (filled($setting->logo_path) && Storage::disk('public')->exists($setting->logo_path)) {
            $candidate = Storage::disk('public')->path($setting->logo_path);
            $size = @getimagesize($candidate);

            if ($size !== false && in_array($size[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_BMP], true)) {
                $logoPath = $candidate;
            }
        }

        return collect(self::tables($this->data))
            ->map(fn (array $table) => new class($table, $setting, $periodLabel, $scopeLabel, $logoPath) implements FromArray, ShouldAutoSize, WithEvents, WithStyles, WithTitle
            {
                public function __construct(
                    private readonly array $table,
                    private readonly InstitutionSetting $setting,
                    private readonly string $periodLabel,
                    private readonly string $scopeLabel,
                    private readonly ?string $logoPath,
                ) {}

                public function title(): string
                {
                    return $this->table['title'];
                }

                /**
                 * Kop surat di baris 1-6, header tabel di baris 8, lalu data.
                 * Blok tanda tangan dibuka penanda SIGNATURE_MARKER agar impor
                 * tahu di mana data berakhir.
                 *
                 * @return list<list<string|int|float|null>>
                 */
                public function array(): array
                {
                    $width = count($this->table['headings']);
                    $blank = array_fill(0, $width, '');

                    // Teks kop selalu di kolom A dan di-merge selebar tabel, lalu
                    // dipusatkan. Logo digambar di atas kolom A sehingga tidak
                    // menggeser titik tengah kop.
                    $rows = [
                        $this->cell($width, 0, (string) $this->setting->institution_name),
                        $this->cell($width, 0, (string) $this->setting->address),
                        $this->cell($width, 0, 'Kode Lokasi/Instansi: '.$this->setting->location_code),
                        $blank,
                        $this->cell($width, 0, 'Laporan Inventaris & Aset Bulanan — '.$this->table['title']),
                        $this->cell($width, 0, trim('Periode: '.$this->periodLabel.' • Cakupan: '.$this->scopeLabel)),
                        $blank,
                        $this->table['headings'],
                    ];

                    foreach ($this->table['rows'] as $row) {
                        $rows[] = $row;
                    }

                    $rows[] = $blank;
                    $rows[] = $this->cell($width, 0, MonthlyReportExport::SIGNATURE_MARKER);
                    $rows[] = $this->cell($width, 0, 'Mengetahui,', 3, 'Petugas Inventaris,');
                    $rows[] = $blank;
                    $rows[] = $this->cell($width, 0, (string) $this->setting->headmaster_name, 3, '');
                    $rows[] = $this->cell($width, 0, 'NIP. ..............................', 3, '');

                    return $rows;
                }

                /**
                 * @return list<string>
                 */
                private function cell(int $width, int $column, string $value, int $secondColumn = -1, string $second = ''): array
                {
                    $row = array_fill(0, $width, '');
                    $row[$column] = $value;

                    if ($secondColumn >= 0 && $secondColumn < $width) {
                        $row[$secondColumn] = $second;
                    }

                    return $row;
                }

                public function styles(Worksheet $sheet): ?array
                {
                    $signatureRow = MonthlyReportExport::HEADING_ROW + count($this->table['rows']) + 2;

                    // Kop surat (dan judul/periode) rata tengah di seluruh lebar tabel.
                    return [
                        1 => [
                            'font' => ['bold' => true, 'size' => 14],
                            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                        ],
                        2 => [
                            'font' => ['size' => 9],
                            'alignment' => ['horizontal' => 'center'],
                        ],
                        // Garis biru penutup kop, sama dengan pratinjau Excel.
                        3 => [
                            'font' => ['size' => 9],
                            'alignment' => ['horizontal' => 'center'],
                            'borders' => [
                                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '155DFC']],
                            ],
                        ],
                        5 => [
                            'font' => ['bold' => true, 'size' => 12],
                            'alignment' => ['horizontal' => 'center'],
                        ],
                        6 => [
                            'font' => ['size' => 9],
                            'alignment' => ['horizontal' => 'center'],
                        ],
                        MonthlyReportExport::HEADING_ROW => [
                            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '155DFC']],
                        ],
                        $signatureRow => ['font' => ['italic' => true, 'color' => ['rgb' => '6B7280']]],
                    ];
                }

                public function registerEvents(): array
                {
                    return [
                        AfterSheet::class => function (AfterSheet $event): void {
                            $sheet = $event->sheet->getDelegate();
                            $lastColumn = Coordinate::stringFromColumnIndex(count($this->table['headings']));

                            foreach ([1, 2, 3, 5, 6] as $row) {
                                $sheet->mergeCells('A'.$row.':'.$lastColumn.$row);
                            }

                            if ($this->logoPath !== null) {
                                // Tinggi tiga baris kop menampung logo 64 px agar
                                // ukurannya sebanding dengan kop pratinjau/PDF.
                                $sheet->getRowDimension(1)->setRowHeight(34);
                                $sheet->getRowDimension(2)->setRowHeight(18);
                                $sheet->getRowDimension(3)->setRowHeight(18);

                                $drawing = new Drawing;
                                $drawing->setPath($this->logoPath);
                                $drawing->setResizeProportional(true);
                                $drawing->setHeight(64);
                                $drawing->setCoordinates('A1');
                                $drawing->setOffsetX(4);
                                $drawing->setOffsetY(4);
                                $drawing->setWorksheet($sheet);
                            } else {
                                $sheet->getRowDimension(1)->setRowHeight(24);
                            }

                            $sheet->getRowDimension(MonthlyReportExport::HEADING_ROW)->setRowHeight(20);
                            $sheet->freezePane('A'.(MonthlyReportExport::HEADING_ROW + 1));

                            // Cetak landscape selebar satu halaman dan dipusatkan
                            // horizontal agar kop tetap di tengah kertas.
                            $sheet->getPageSetup()
                                ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                                ->setPaperSize(PageSetup::PAPERSIZE_A4)
                                ->setFitToWidth(1)
                                ->setFitToHeight(0);
                            $sheet->getPageSetup()->setHorizontalCentered(true);
                        },
                    ];
                }
            })
            ->all();
    }
}
