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
 * mutasi, penghapusan, pengadaan, dan laporan kerusakan.
 *
 * `tables()` adalah SATU-SATUNYA sumber bentuk tabel. PDF dan pratinjau Excel
 * memakai definisi yang sama sehingga jumlah baris, kode unit, spesifikasi,
 * status, dan lokasi tidak mungkin berbeda antar format.
 */
class MonthlyReportExport implements Export, WithMultipleSheets
{
    public function __construct(private readonly array $data) {}

    /**
     * @return list<array{title: string, headings: list<string>, rows: list<list<mixed>>}>
     */
    public static function tables(array $data): array
    {
        $specificationName = fn ($row): string => trim((string) (
            $row->specification?->displayName()
            ?: collect([$row->catalog?->brand, $row->catalog?->model_type])->filter()->implode(' / ')
        )) ?: '—';

        return [
            [
                'title' => 'Unit',
                'headings' => [
                    'Kode Inventaris', 'Nama Barang', 'Kategori', 'Merek', 'Tipe/Model', 'Spesifikasi',
                    'Ruangan', 'Gedung', 'Lantai', 'No. Ruangan', 'Kondisi', 'Status', 'Tahun',
                    'Sumber Dana', 'No. Register', 'Nilai Tinggi',
                ],
                'rows' => collect($data['units'] ?? [])->map(fn ($row) => [
                    $row->inventory_code,
                    $row->catalog?->name,
                    $row->catalog?->category?->name,
                    $row->specification?->brandLabel() ?: $row->catalog?->brand,
                    $row->specification?->modelLabel() ?: $row->catalog?->model_type,
                    $specificationName($row),
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
        return collect(self::tables($this->data))
            ->map(fn (array $table) => new class($table) implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
            {
                public function __construct(private readonly array $table) {}

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
                    return $this->table['title'];
                }

                public function array(): array
                {
                    return $this->table['rows'];
                }

                /** @return list<string> */
                public function headings(): array
                {
                    return $this->table['headings'];
                }
            })
            ->all();
    }
}
