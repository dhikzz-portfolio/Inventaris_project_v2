<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 30pt 36pt 40pt; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { margin: 0; font-family: Helvetica, Arial, sans-serif; font-size: 9pt; color: #111; }
        .kop { width: 100%; border-bottom: 3px solid #155dfc; padding-bottom: 6px; margin-bottom: 12px; }
        .kop img { max-width: 56px; max-height: 56px; }
        .kop .instansi { font-size: 12pt; font-weight: bold; text-transform: uppercase; }
        .kop .alamat { font-size: 8.5pt; }
        .judul { text-align: center; font-size: 11.5pt; font-weight: bold; text-transform: uppercase; margin: 8px 0 2px; }
        .periode { text-align: center; font-size: 9pt; margin-bottom: 12px; color: #444; }
        h3 { font-size: 9.5pt; margin: 12px 0 4px; background: #eff6ff; padding: 3px 5px; border-left: 4px solid #155dfc; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #666; padding: 3px 5px; font-size: 7.5pt; vertical-align: top; }
        th { background: #dbeafe; color: #1c398e; text-align: left; }
        .num { text-align: center; }
        .summary { margin-bottom: 10px; }
        .summary td { width: 16.66%; text-align: center; padding: 5px; }
        .summary .val { font-size: 13pt; font-weight: bold; }
        .summary .lbl { font-size: 7.5pt; color: #555; }
        .footer { margin-top: 14px; font-size: 7.5pt; color: #666; border-top: 1px solid #ccc; padding-top: 4px; }
        .empty { padding: 8px; text-align: center; color: #666; }
    </style>
</head>
<body>
    <table class="kop">
        <tr>
            <td width="64">
                @if ($setting->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($setting->logo_path))
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->path($setting->logo_path) }}">
                @endif
            </td>
            <td>
                <div class="instansi">{{ $setting->institution_name }}</div>
                <div class="alamat">{{ $setting->address ?: '' }}</div>
                <div class="alamat">Kode Lokasi/Instansi: {{ $setting->location_code }}</div>
            </td>
        </tr>
    </table>

    <div class="judul">Laporan Inventaris &amp; Aset Bulanan</div>
    <div class="periode">Periode: {{ $periodLabel }} • Cakupan: {{ $scopeLabel }} • dicetak {{ now()->format('d M Y H:i') }}</div>

    <table class="summary">
        <tr>
            <td><div class="val">{{ $summary['total_barang'] }}</div><div class="lbl">Katalog Barang</div></td>
            <td><div class="val">{{ $summary['total_unit'] }}</div><div class="lbl">Total Unit</div></td>
            <td><div class="val">{{ $summary['pengajuan_pinjam'] }}</div><div class="lbl">Pengajuan Pinjam</div></td>
            <td><div class="val">{{ $summary['kerusakan'] }}</div><div class="lbl">Kerusakan Dilaporkan</div></td>
            <td><div class="val">{{ $summary['mutasi'] }}</div><div class="lbl">Mutasi Lokasi</div></td>
            <td><div class="val">{{ $summary['penghapusan'] }}</div><div class="lbl">Penghapusan</div></td>
        </tr>
        <tr>
            <td><div class="val" style="color:green;">{{ $summary['baik'] }}</div><div class="lbl">Unit Baik</div></td>
            <td><div class="val" style="color:#ca8a04;">{{ $summary['rusak_ringan'] }}</div><div class="lbl">Unit Rusak Ringan</div></td>
            <td><div class="val" style="color:#ea580c;">{{ $summary['rusak_berat'] }}</div><div class="lbl">Unit Rusak Berat</div></td>
            <td><div class="val" style="color:red;">{{ $summary['hilang'] }}</div><div class="lbl">Unit Hilang</div></td>
            <td><div class="val">{{ $summary['selesai'] }}</div><div class="lbl">Pinjam Selesai</div></td>
            <td><div class="val" style="color:red;">{{ $summary['terlambat'] }}</div><div class="lbl">Terlambat</div></td>
        </tr>
    </table>

    @php($reportTables = $tables ?? \App\Exports\MonthlyReportExport::tables(get_defined_vars()))

    {{-- Tabel yang sama persis dengan sheet Excel: rincian setiap unit fisik,
         transaksi peminjaman, kerusakan, mutasi, penghapusan, pengadaan, dan
         laporan kerusakan. Tidak ada ringkasan yang menggantikan detail. --}}
    @foreach ($reportTables as $table)
        <h3>{{ $table['title'] }} ({{ count($table['rows']) }})</h3>
        <table>
            <thead>
                <tr>
                    @foreach ($table['headings'] as $heading)
                        <th>{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($table['rows'] as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell === null || $cell === '' ? '—' : $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($table['headings']) }}" class="empty">Tidak ada data {{ strtolower($table['title']) }} pada cakupan ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endforeach

    <div class="footer">
        Laporan dibuat otomatis oleh Sistem Manajemen Inventaris &amp; Aset Sekolah — {{ $setting->institution_name }}
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_text(680, 560, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', null, 7, [0.4, 0.4, 0.4]);
        }
    </script>
</body>
</html>
