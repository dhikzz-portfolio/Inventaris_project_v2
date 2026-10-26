<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30pt 36pt; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #111; }
        .kop { width: 100%; border-bottom: 3px solid #155dfc; padding-bottom: 8px; margin-bottom: 14px; }
        .kop img { max-width: 64px; max-height: 64px; }
        .kop .instansi { font-size: 13pt; font-weight: bold; text-transform: uppercase; }
        .kop .alamat { font-size: 9pt; }
        .judul { text-align: center; font-size: 12pt; font-weight: bold; text-transform: uppercase; text-decoration: underline; margin: 8px 0 3px; }
        .nomor { text-align: center; font-size: 9pt; margin-bottom: 12px; }
        table.info td { padding: 2.5px 0; vertical-align: top; }
        table.info td:first-child { width: 155px; }
        table.data { width: 100%; border-collapse: collapse; margin: 12px 0; }
        table.data th, table.data td { border: 1px solid #444; padding: 4px 6px; font-size: 8.5pt; }
        table.data th { background: #dbeafe; color: #1c398e; text-align: left; }
        h3.bagian { font-size: 10pt; margin: 12px 0 4px; background: #eff6ff; padding: 4px 6px; border-left: 4px solid #155dfc; }
        .ttd { width: 100%; margin-top: 30px; }
        .ttd td { width: 33.33%; text-align: center; vertical-align: top; font-size: 9.5pt; }
        .ttd .role { font-weight: bold; text-decoration: underline; }
        .ttd .space { height: 64px; }
        .ttd img { max-height: 50px; max-width: 140px; }
        .ttd .nama { font-weight: bold; margin-top: 6px; }
        .footer { margin-top: 16px; font-size: 8pt; color: #555; border-top: 1px solid #ccc; padding-top: 5px; }
        .loan-section { page-break-inside: avoid; }
        table.data tr { page-break-inside: avoid; }
        .ttd { page-break-inside: avoid; }
    </style>
</head>
<body>
    <table class="kop">
        <tr>
            <td width="72">
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

    <div class="judul">Berita Acara Serah Terima Barang — Kegiatan</div>
    <div class="nomor">Nomor: BAST/KEG/{{ $event->id }}/{{ now()->format('m/Y') }}</div>

    <table class="info">
        <tr><td>Nama Kegiatan</td><td>: {{ $event->name }}</td></tr>
        <tr><td>Penanggung Jawab</td><td>: {{ $event->pic_name }}</td></tr>
        <tr><td>Tanggal Kegiatan</td><td>: {{ $event->start_date->format('d M Y') }} s.d. {{ $event->end_date->format('d M Y') }}</td></tr>
        <tr><td>Jumlah Peminjaman</td><td>: {{ $loans->count() }} request / {{ $loans->sum(fn ($l) => $l->loanItems->count()) }} baris barang</td></tr>
        @if ($event->description)
            <tr><td>Keterangan</td><td>: {{ $event->description }}</td></tr>
        @endif
    </table>

    @foreach ($loans as $loan)
    <div class="loan-section">
        <h3 class="bagian">Peminjaman #{{ $loan->id }} — {{ $loan->borrower?->name }} ({{ $loan->borrower?->username }})</h3>
        <table class="data">
            <thead>
                <tr>
                    <th width="22">No</th>
                    <th width="105">ID Internal</th>
                    <th>Nama Barang</th>
                    <th width="44">Jumlah</th>
                    <th width="70">Kondisi Keluar</th>
                    <th width="70">Kondisi Kembali</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($loan->loanItems as $i => $li)
                    <tr>
                        <td align="center">{{ $i + 1 }}</td>
                        <td style="font-family: monospace;">{{ $li->item?->internal_id }}</td>
                        <td>{{ $li->item?->name }}</td>
                        <td align="center">{{ $li->qty_borrowed }} {{ $li->item?->unit }}</td>
                        <td align="center">{{ $li->condition_out->label() }}</td>
                        <td align="center">{{ $li->condition_in?->label() ?: '________' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endforeach

    <table class="ttd">
        <tr>
            <td>
                <div class="role">Peminjam / Penanggung Jawab</div>
                <div class="space"></div>
                <div class="nama">{{ $event->pic_name }}</div>
            </td>
            <td>
                <div class="role">Laboran / Petugas</div>
                <div class="space"></div>
                <div class="nama">..............................</div>
            </td>
            <td>
                <div class="role">Wakasek Sarpras</div>
                <div class="space"></div>
                <div class="nama">..............................</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Dokumen digenerate otomatis oleh Sistem Manajemen Inventaris &amp; Aset Sekolah — {{ $setting->institution_name }} • {{ now()->format('d M Y H:i') }}
    </div>
</body>
</html>
