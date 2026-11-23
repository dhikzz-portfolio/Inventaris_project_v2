<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* PDF Page Setup */
        @page {
            margin: 0;
        }

        /* Global Reset */
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            font-size: 0;
        }

        /* Suppress inline whitespace between table cells */
        .label tr, .label td {
            font-size: 0;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #111827;
            background-color: #ffffff;
        }

        /* Label Container — 1 stiker = 1 halaman penuh */
        .label {
            width: 244pt;
            margin: 12pt auto 0;
            border: 2pt solid #2563eb;
            border-radius: 6pt;
            font-size: 0;
            table-layout: fixed;
        }

        .label td {
            font-size: initial;
        }

        /* Logo Section */
        .logo-cell {
            width: 52pt;
            padding: 4pt 0 0;
            text-align: left;
            vertical-align: middle;
        }

        .logo-cell img {
            width: 38pt;
            height: 38pt;
        }

        .logo-fallback {
            width: 38pt;
            font-size: 5pt;
            padding: 3pt;
        }

        /* Content Section */
        .content-cell {
            padding: 2pt 2pt 0;
            text-align: center;
            vertical-align: middle;
        }

        .item-name {
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.1;
            color: #111827;
            padding-bottom: 1pt;
        }

        .codes {
            font-family: 'Courier New', monospace;
            font-size: 10pt;
            font-weight: bold;
            letter-spacing: 0.1pt;
            line-height: 1.2;
            color: #1e40af;
            padding-top: 1pt;
        }

        /* QR Code Section */
        .qr-cell {
            width: 52pt;
            padding: 4pt 0 0;
            text-align: right;
            vertical-align: middle;
        }

        .qr-cell img {
            width: 38pt;
            height: 38pt;
        }

        /* Footer */
        .footer {
            border-top: 1pt solid #93c5fd;
            padding: 3pt 4pt 2pt;
            font-size: 7pt;
            font-weight: bold;
            letter-spacing: 0.3pt;
            text-align: center;
            text-transform: uppercase;
            color: #1e40af;
        }    </style>
</head>
<body>
    <table class="label">
        <tr>
            <td class="logo-cell">
                @if ($setting->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($setting->logo_path))
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->path($setting->logo_path) }}" alt="Logo">
                @else
                    <div class="logo-fallback">LOGO<br>SEKOLAH</div>
                @endif
            </td>

            <td class="content-cell">
                <div class="content-inner">
                    <div class="item-name">{{ $item->name }}</div>
                    <div class="codes">
                        {!! implode('<br>', array_map('e', $locationLines)) !!}
                    </div>
                </div>
            </td>

            <td class="qr-cell">
                <img src="{{ $qr }}" alt="QR Code">
            </td>
        </tr>
        <tr>
            <td colspan="3" class="footer">
                {{ $setting->institution_name }}
            </td>
        </tr>
    </table>
</body>
</html>
