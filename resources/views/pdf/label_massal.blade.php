<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid td { width: 33.33%; padding: 4pt; vertical-align: top; }

        .label {
            width: 100%; height: 104pt;
            border: 2pt solid #000;
            border-radius: 4pt;
            display: table;
            page-break-inside: avoid;
        }
        .logo-cell {
            display: table-cell; width: 54pt;
            border-right: 1pt solid #999;
            text-align: center; vertical-align: middle; padding: 4pt;
        }
        .logo-cell img { max-width: 46pt; max-height: 46pt; }
        .logo-fallback { font-size: 6pt; font-weight: bold; color: #333; }
        .content-cell { display: table-cell; vertical-align: top; padding: 5pt 5pt 0 5pt; position: relative; }
        .inner { display: table; width: 100%; }
        .inner .codes { display: table-cell; vertical-align: top; }
        .inner .qr { display: table-cell; width: 52pt; text-align: right; vertical-align: top; }
        .inner .qr img { width: 48pt; height: 48pt; }
        .kode-lokasi { font-family: 'Courier New', monospace; font-size: 11.5pt; font-weight: bold; line-height: 1.1; }
        .kode-barang { font-family: 'Courier New', monospace; font-size: 9pt; font-weight: bold; margin-top: 2pt; }
        .footer {
            position: absolute; left: 5pt; right: 5pt; bottom: 3pt;
            border-top: 1pt solid #000; padding-top: 2pt;
            font-size: 6.5pt; font-weight: bold; text-align: center; text-transform: uppercase;
        }
    </style>
</head>
<body>
    <table class="grid">
        @foreach ($items->chunk(3) as $row)
            <tr>
                @foreach ($row as $item)
                    <td>
                        <div class="label">
                            <div class="logo-cell">
                                @if ($setting->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($setting->logo_path))
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->path($setting->logo_path) }}" alt="Logo">
                                @else
                                    <div class="logo-fallback">LOGO<br>SEKOLAH</div>
                                @endif
                            </div>
                            <div class="content-cell">
                                <div class="inner">
                                    <div class="codes">
                                        <div class="kode-lokasi">{{ $item->location_code }}</div>
                                        <div class="kode-barang">{{ $item->asset_code }}.{{ $item->register_no }}</div>
                                    </div>
                                    <div class="qr">
                                        <img src="{{ $qrMap[$item->id] }}" alt="QR">
                                    </div>
                                </div>
                                <div class="footer">{{ $setting->institution_name }}</div>
                            </div>
                        </div>
                    </td>
                @endforeach
                @for ($i = 0; $i < (3 - $row->count()); $i++)
                    <td></td>
                @endfor
            </tr>
        @endforeach
    </table>
</body>
</html>
