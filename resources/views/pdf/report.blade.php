<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30pt 36pt; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Helvetica, Arial, sans-serif; font-size: 9pt; color: #111; }
        .kop { width: 100%; border-bottom: 3px solid #155dfc; padding-bottom: 6px; margin-bottom: 12px; }
        .kop img { max-width: 56px; max-height: 56px; }
        .kop .instansi { font-size: 12pt; font-weight: bold; text-transform: uppercase; }
        .kop .alamat { font-size: 8.5pt; }
        .judul { text-align: center; font-size: 11.5pt; font-weight: bold; text-transform: uppercase; margin: 8px 0 2px; }
        .periode { text-align: center; font-size: 9pt; margin-bottom: 12px; color: #444; }
        h3 { font-size: 9.5pt; margin: 12px 0 4px; background: #eff6ff; padding: 3px 5px; border-left: 4px solid #155dfc; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #666; padding: 3px 5px; font-size: 8pt; }
        th { background: #dbeafe; color: #1c398e; text-align: left; }
        .num { text-align: center; }
        .summary td { width: 16.66%; text-align: center; padding: 5px; }
        .summary .val { font-size: 13pt; font-weight: bold; }
        .summary .lbl { font-size: 7.5pt; color: #555; }
        .footer { margin-top: 14px; font-size: 7.5pt; color: #666; border-top: 1px solid #ccc; padding-top: 4px; }
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

    <h3>Rincian Barang ({{ $items->count() }})</h3>
    <table>
        <thead>
            <tr>
                <th width="90">ID Internal</th>
                <th>Nama</th>
                <th width="95">Kategori</th>
                <th width="110">Ruangan</th>
                <th width="36" class="num">Qty</th>
                <th width="42">Satuan</th>
                <th width="70">Status</th>
                <th width="46" class="num">Thn</th>
                <th width="52">Dana</th>
                <th width="105">Nomor Register</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td style="font-family: monospace;">{{ $item->internal_id }}</td>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->category?->name }}</td>
                    <td>{{ $item->room?->code }} — {{ $item->room?->name }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td>{{ $item->unit }}</td>
                    <td>{{ $item->status->label() }}</td>
                    <td class="num">{{ $item->acquisition_year }}</td>
                    <td>{{ $item->funding_source }}</td>
                    <td style="font-family: monospace;">{{ $item->register_no ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="num">Tidak ada barang pada cakupan ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($damageRows->isNotEmpty())
        <h3>Kerusakan Selama Periode</h3>
        <table>
            <thead><tr><th width="100">ID Internal</th><th>Barang</th><th>Kondisi</th><th>Peminjam Terakhir</th><th>Tanggal</th></tr></thead>
            <tbody>
                @foreach ($damageRows as $row)
                    <tr>
                        <td style="font-family: monospace;">{{ $row->item?->internal_id }}</td>
                        <td>{{ $row->item?->name }}</td>
                        <td>{{ $row->condition_in instanceof \App\Enums\Condition ? $row->condition_in->label() : (\App\Enums\Condition::tryFrom((string) $row->condition_in)?->label() ?? $row->condition_in) }}</td>
                        <td>{{ $row->loan?->borrower?->name }}</td>
                        <td>{{ $row->loan?->returned_at?->format('d/m/Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($movements->isNotEmpty())
        <h3>Mutasi Lokasi Selama Periode</h3>
        <table>
            <thead><tr><th width="100">ID Internal</th><th>Barang</th><th>Asal</th><th>Tujuan</th><th>Petugas</th><th>Tanggal</th></tr></thead>
            <tbody>
                @foreach ($movements as $m)
                    <tr>
                        <td style="font-family: monospace;">{{ $m->item?->internal_id }}</td>
                        <td>{{ $m->item?->name }}</td>
                        <td>{{ $m->fromRoom?->code }}</td>
                        <td>{{ $m->toRoom?->code }}</td>
                        <td>{{ $m->mover?->name }}</td>
                        <td>{{ $m->moved_at?->format('d/m/Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($writeOffs->isNotEmpty())
        <h3>Penghapusan (Inventaris Keluar) Selama Periode</h3>
        <table>
            <thead><tr><th>Barang</th><th width="40" class="num">Qty</th><th>Alasan</th><th>Disetujui Oleh</th><th>Tanggal</th></tr></thead>
            <tbody>
                @foreach ($writeOffs as $wo)
                    <tr>
                        <td>{{ $wo->item?->name }} ({{ $wo->item?->internal_id }})</td>
                        <td class="num">{{ $wo->quantity }}</td>
                        <td>{{ $wo->reason }}</td>
                        <td>{{ $wo->approver?->name }}</td>
                        <td>{{ $wo->updated_at->format('d/m/Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Laporan dibuat otomatis oleh Sistem Manajemen Inventaris &amp; Aset Sekolah — {{ $setting->institution_name }}
    </div>
</body>
</html>
