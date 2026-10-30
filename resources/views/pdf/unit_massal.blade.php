<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 1pt; font-family: Helvetica, Arial, sans-serif; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid td { width: 50%; padding: 5pt; vertical-align: top; }
        table.grid tr { page-break-inside: avoid; }

        .label {
            width: 240pt; height: 62pt;
            border: 2pt solid #1c398e;
            border-radius: 6pt;
            padding: 3pt 7pt;
            page-break-inside: avoid;
        }
        .head { height: 38pt; position: relative; top: 1pt; }
        table.inner { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.inner td { vertical-align: middle; padding: 0; }
        .logo-cell { width: 44pt; text-align: left; vertical-align: middle; }
        .logo-cell img { max-width: 40pt; max-height: 40pt; margin-left: 2pt; }
        .logo-wrap { text-align: left; }
        .logo-fallback { font-size: 7pt; font-weight: bold; color: #333; line-height: 1.2; }
        .codes { padding-left: 6pt !important; padding-right: 6pt !important; text-align: center; word-break: normal; overflow-wrap: normal; white-space: nowrap; vertical-align: middle; }
        .codes .nama,
        .codes .kode-unit { white-space: nowrap; }
        .nama {
            font-size: 11pt; font-weight: bold; text-transform: uppercase;
            color: #1c398e; line-height: 1.05; margin-bottom: 1pt;
            text-align: center;
        }
        .kode-unit {
            font-size: 13pt; font-weight: bold; line-height: 1.05;
            text-align: center; white-space: nowrap;
            letter-spacing: 0.4pt; color: #1c398e;
        }
        .qr-cell { width: 36pt; text-align: right; padding-right: 0pt !important; vertical-align: top; position: relative; top: -1pt; }
        .qr-cell img { width: 36pt; height: 36pt; }
        .footer {
            margin-top: 7pt;
            border-top: 1pt solid #8ec5ff;
            padding-top: 4pt;
            font-size: 8pt; font-weight: bold;
            text-align: center; text-transform: uppercase;
            letter-spacing: 0.4pt; color: #1c398e;
        }
    </style>
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
                                            <div class="logo-wrap">
                                                @if ($setting->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($setting->logo_path))
                                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->path($setting->logo_path) }}" alt="Logo">
                                                @else
                                                    <div class="logo-fallback">LOGO<br>SEKOLAH</div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="codes">
                                            <div class="nama">{{ $unit->catalog?->name }}</div>
                                            <div class="kode-unit">{{ $unit->inventory_code }}</div>
                                        </td>
                                        <td width="50" class="qr-cell">
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
