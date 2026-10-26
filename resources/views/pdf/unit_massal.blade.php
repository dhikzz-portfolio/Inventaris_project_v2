<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"><style>
        @page { margin: 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Helvetica, Arial, sans-serif; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid td { width: 50%; padding: 4pt; vertical-align: top; }
        table.grid tr { page-break-inside: avoid; }

        /* width/height = content box; padding+border (14pt x 10pt) muat di sel 50% A4 */
        .label {
            width: 252pt; height: 90pt;
            border: 2pt solid #155dfc;
            border-radius: 4pt;
            padding: 3pt 5pt;
            page-break-inside: avoid;
        }
        .head { height: 70pt; }
        table.inner { width: 100%; border-collapse: collapse; }
        table.inner td { vertical-align: top; padding: 0; }
        .logo-cell { width: 44pt; text-align: center; vertical-align: middle; }
        .logo-cell img { max-width: 40pt; max-height: 40pt; }
        .logo-fallback { font-size: 6pt; font-weight: bold; color: #333; }
        .codes { padding-left: 4pt !important; }
        .kode-unit {
            font-family: 'Courier New', monospace;
            font-size: 15pt; font-weight: bold; line-height: 1.05;
            color: #1c398e;
        }
        .nama { font-size: 9pt; font-weight: bold; text-transform: uppercase; margin-bottom: 1pt; line-height: 1.1; }
        .lokasi { font-size: 7pt; margin-top: 1pt; }
        .qr-cell { width: 46pt; text-align: right; }
        .qr-cell img { width: 40pt; height: 40pt; }
        .footer {
            margin-top: 4pt;
            border-top: 1pt solid #8ec5ff;
            padding-top: 2pt;
            font-size: 7pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            color: #1c398e;
        }</style>
</head>
<body>
    <table class="grid">
        @foreach ($units->chunk(2) as $row)
            <tr>
                @foreach ($row as $unit)
                    <td>
                        <div class="label">
                            <div class="head">
                                <table class="inner">
                                    <tr>
                                        <td width="44" class="logo-cell">
                                            @if ($setting->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($setting->logo_path))
                                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->path($setting->logo_path) }}" alt="Logo">
                                            @else
                                                <div class="logo-fallback">LOGO<br>SEKOLAH</div>
                                            @endif
                                        </td>
                                        <td class="codes">
                                            {{-- Nama barang sebagai header, kode inventaris unik per stiker --}}
                                            <div class="nama">{{ $unit->catalog?->name }}</div>
                                            <div class="kode-unit">{{ $unit->inventory_code }}</div>
                                            <div class="lokasi">{{ $unit->locationLabel() }}</div>
                                        </td>
                                        <td width="46" class="qr-cell">
                                            {{-- QR menunjuk ke detail unit berbasis ID stabil --}}
                                            <img src="{{ $qrMap[$unit->id] }}" alt="QR">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="footer">{{ $setting->institution_name }}</div>
                        </div>
                    </td>
                @endforeach
                @for ($i = 0; $i < (2 - $row->count()); $i++)
                    <td></td>
                @endfor
            </tr>
        @endforeach
    </table>
</body>
</html>
