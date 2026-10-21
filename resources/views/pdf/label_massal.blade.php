<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Helvetica, Arial, sans-serif; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid td { width: 50%; padding: 4pt; vertical-align: top; }
        table.grid tr { page-break-inside: avoid; }

        /* width/height = content box; padding+border (14pt x 10pt) muat di sel 50% A4 */
        .label {
            width: 252pt; height: 90pt;
            border: 2pt solid #000;
            border-radius: 4pt;
            padding: 3pt 5pt;
            page-break-inside: avoid;
        }
        .head { height: 70pt; }
        table.inner { width: 100%; border-collapse: collapse; }
        table.inner td { vertical-align: top; padding: 0; }
        .logo-cell { width: 48pt; text-align: center; vertical-align: middle; }
        .logo-cell img { max-width: 42pt; max-height: 42pt; }
        .logo-fallback { font-size: 6pt; font-weight: bold; color: #333; }
        .codes { padding-left: 4pt !important; }
        .kode-lokasi {
            font-family: 'Courier New', monospace;
            font-size: 13pt; font-weight: bold; line-height: 1.1;
        }
        .kode-barang {
            font-family: 'Courier New', monospace;
            font-size: 9pt; font-weight: bold; line-height: 1.1;
            margin-top: 2pt;
        }
        .qr-cell { width: 46pt; text-align: right; }
        .qr-cell img { width: 40pt; height: 40pt; }
        .footer {
            margin-top: 5pt;
            border-top: 1pt solid #000; padding-top: 2pt;
            font-size: 7pt; font-weight: bold; text-align: center; text-transform: uppercase;
        }
    </style>
</head>
<body>
    <table class="grid">
        @foreach ($items->chunk(2) as $row)
            <tr>
                @foreach ($row as $item)
                    <td>
                        <div class="label">
                            <div class="head">
                                <table class="inner">
                                    <tr>
                                        <td width="48" class="logo-cell">
                                            @if ($setting->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($setting->logo_path))
                                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->path($setting->logo_path) }}" alt="Logo">
                                            @else
                                                <div class="logo-fallback">LOGO<br>SEKOLAH</div>
                                            @endif
                                        </td>
                                        <td class="codes">
                                            <div class="kode-lokasi">{!! implode('<br>', array_map('e', $locationLinesMap[$item->id] ?? [$item->location_code])) !!}</div>
                                            <div class="kode-barang">{{ $item->asset_code }}.{{ $item->register_no }}</div>
                                        </td>
                                        <td width="46" class="qr-cell">
                                            <img src="{{ $qrMap[$item->id] }}" alt="QR">
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
