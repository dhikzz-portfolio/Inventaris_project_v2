<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Ekspor laporan bulanan multi-sheet: unit, peminjaman, kerusakan pengembalian,
 * mutasi, penghapusan, pengadaan, dan laporan kerusakan — agar isi Excel
 * setara dengan PDF dan halaman laporan.
 */
class MonthlyReportExport implements Export, WithMultipleSheets
{
    public function __construct(private readonly array $data) {}

    public function sheets(): array
    {
        return [
            new class($this->data) implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
            {
                public function __construct(private readonly array $data) {}

                public function styles(Worksheet $sheet): ?array
                {
                    return [
                        1 => [
                            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '155DFC']],
                        ],
                    ];
                }

                public function title(): string
                {
                    return 'Unit';
                }

                /** @return list<list<mixed>> */
                public function array(): array
                {
                    return $this->data['units']->map(fn ($row) => [
                        $row->inventory_code,
                        $row->catalog?->name,
                        $row->catalog?->category?->name,
                        $row->room?->name,
                        $row->room?->building_letter,
                        $row->room?->floor,
                        $row->room?->room_no,
                        $row->condition->label(),
                        $row->status->label(),
                        $row->acquisition_year,
                        $row->funding_source,
                        $row->register_no,
                        $row->catalog?->is_high_value ? 'Ya' : 'Tidak',
                    ])->all();
                }

                /** @return list<string> */
                public function headings(): array
                {
                    return ['Kode Inventaris', 'Nama Barang', 'Kategori', 'Ruangan', 'Gedung', 'Lantai', 'No. Ruangan', 'Kondisi', 'Status', 'Tahun', 'Sumber Dana', 'No. Register', 'Nilai Tinggi'];
                }
            },
            new class($this->data) implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
            {
                public function __construct(private readonly array $data) {}

                public function styles(Worksheet $sheet): ?array
                {
                    return [
                        1 => [
                            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '155DFC']],
                        ],
                    ];
                }

                public function title(): string
                {
                    return 'Peminjaman';
                }

                public function array(): array
                {
                    return $this->data['loans']->map(fn ($row) => [
                        $row->id,
                        $row->borrower?->name,
                        $row->purpose,
                        $row->event?->name,
                        $row->start_date?->format('d/m/Y H:i'),
                        $row->due_date?->format('d/m/Y H:i'),
                        $row->returned_at?->format('d/m/Y H:i'),
                        $row->status->label(),
                        $row->loanItems->sum('qty_borrowed'),
                        $row->loanItems->sum(fn ($li) => (int) $li->qty_returned),
                    ])->all();
                }

                public function headings(): array
                {
                    return ['ID Pinjam', 'Peminjam', 'Tujuan', 'Kegiatan', 'Mulai', 'Jatuh Tempo', 'Dikembalikan', 'Status', 'Total Dipinjam', 'Total Kembali'];
                }
            },
            new class($this->data) implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
            {
                public function __construct(private readonly array $data) {}

                public function styles(Worksheet $sheet): ?array
                {
                    return [
                        1 => [
                            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '155DFC']],
                        ],
                    ];
                }

                public function title(): string
                {
                    return 'Kerusakan';
                }

                public function array(): array
                {
                    return $this->data['damageRows']->map(fn ($row) => [
                        $row->item?->internal_id,
                        $row->item?->name,
                        $row->condition_in?->label(),
                        $row->qty_damaged,
                        $row->loan?->borrower?->name,
                        $row->loan?->returned_at?->format('d/m/Y H:i'),
                    ])->all();
                }

                public function headings(): array
                {
                    return ['ID Internal', 'Barang', 'Kondisi Akhir', 'Jumlah Rusak', 'Peminjam', 'Tanggal Kembali'];
                }
            },
            new class($this->data) implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
            {
                public function __construct(private readonly array $data) {}

                public function styles(Worksheet $sheet): ?array
                {
                    return [
                        1 => [
                            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '155DFC']],
                        ],
                    ];
                }

                public function title(): string
                {
                    return 'Mutasi Lokasi';
                }

                public function array(): array
                {
                    return $this->data['movements']->map(fn ($row) => [
                        $row->moved_at?->format('d/m/Y H:i'),
                        $row->item?->internal_id,
                        $row->item?->name,
                        $row->fromRoom?->code,
                        $row->toRoom?->code,
                        $row->quantity,
                        $row->mover?->name,
                        $row->note,
                    ])->all();
                }

                public function headings(): array
                {
                    return ['Tanggal', 'ID Internal', 'Barang', 'Dari Ruangan', 'Ke Ruangan', 'Jumlah', 'Petugas', 'Catatan'];
                }
            },
            new class($this->data) implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
            {
                public function __construct(private readonly array $data) {}

                public function styles(Worksheet $sheet): ?array
                {
                    return [
                        1 => [
                            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '155DFC']],
                        ],
                    ];
                }

                public function title(): string
                {
                    return 'Penghapusan';
                }

                public function array(): array
                {
                    return $this->data['writeOffRows']->map(fn ($row) => [
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
                        $row->legacy ? 'Transaksi lama tanpa rincian unit' : null,
                    ])->all();
                }

                public function headings(): array
                {
                    return ['Tanggal', 'ID Transaksi', 'Katalog', 'Kode Unit', 'Merek', 'Tipe/Model', 'Spesifikasi', 'Lokasi', 'Jumlah', 'Alasan', 'Pengaju', 'Penyetuju', 'Catatan'];
                }
            },
            new class($this->data) implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
            {
                public function __construct(private readonly array $data) {}

                public function styles(Worksheet $sheet): ?array
                {
                    return [
                        1 => [
                            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '155DFC']],
                        ],
                    ];
                }

                public function title(): string
                {
                    return 'Pengadaan';
                }

                public function array(): array
                {
                    return $this->data['acquisitionRows']->map(fn ($row) => [
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
                    ])->all();
                }

                public function headings(): array
                {
                    return ['Tanggal', 'Katalog', 'Kategori', 'Lokasi', 'Jumlah Diajukan', 'Jumlah Diterima', 'Status', 'Pengaju', 'Penyetuju', 'Catatan'];
                }
            },
            new class($this->data) implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
            {
                public function __construct(private readonly array $data) {}

                public function styles(Worksheet $sheet): ?array
                {
                    return [
                        1 => [
                            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '155DFC']],
                        ],
                    ];
                }

                public function title(): string
                {
                    return 'Laporan Kerusakan';
                }

                public function array(): array
                {
                    return $this->data['damageReportRows']->map(fn ($row) => [
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
                    ])->all();
                }

                public function headings(): array
                {
                    return ['Tanggal Lapor', 'Kode Unit', 'Barang', 'Lokasi', 'Klasifikasi', 'Status Penanganan', 'Deskripsi', 'Pelapor', 'Penangan', 'Catatan'];
                }
            },
        ];
    }
}
