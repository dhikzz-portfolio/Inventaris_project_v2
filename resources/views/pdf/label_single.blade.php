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

        body {
            margin: 0;
            padding: 10pt;
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #111827;
            background-color: #ffffff;
        }

        /* Label Container */
        .label {
            width: 244pt;
            height: 92pt;
            border: 2pt solid #2563eb;
            border-radius: 6pt;
            padding: 8pt 10pt;
            page-break-inside: avoid;
            position: relative;
        }

        /* Layout Table */
        .inner-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .inner-table td {
            padding: 0;
            vertical-align: middle;
        }

        /* Logo Section */
        .logo-cell {
            width: 50pt;
            text-align: left;
        }

        .logo-cell img {
            max-width: 45pt;
            max-height: 45pt;
            display: block;
        }

        .logo-fallback {
            font-size: 6pt;
            font-weight: bold;
            color: #64748b;
            line-height: 1.2;
            border: 0.5pt dashed #cbd5e1;
            padding: 4pt;
            text-align: center;
        }

        /* Content Section */
        .content-cell {
            padding: 0 6pt;
            text-align: center;
        }

        .item-name {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.1;
            color: #111827;
            margin-bottom: 2pt;
        }

        .inventory-code {
            font-family: 'Courier New', monospace;
            font-size: 12pt;
            font-weight: bold;
            letter-spacing: 0.2pt;
            line-height: 1.2;
            color: #1e40af;
        }

        /* QR Code Section */
        .qr-cell {
            width: 50pt;
            text-align: right;
        }

        .qr-cell img {
            width: 44pt;
            height: 44pt;
            display: block;
            margin-left: auto;
        }

        /* Divider */
        .divider {
            margin-top: 6pt;
            border-top: 1pt solid #93c5fd;
        }

        /* Footer */
        .footer {
            margin-top: 2pt;
            font-size: 8pt;
            font-weight: bold;
            letter-spacing: 0.5pt;
            text-align: center;
            text-transform: uppercase;
            color: #1e40af;
        }
    </style>
</head>
<body>
    <div class="label">
        <table class="inner-table">
            <tr>
                <td class="logo-cell">
                    @if ($setting->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($setting->logo_path))
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->path($setting->logo_path) }}" alt="Logo">
                    @else
                        <div class="logo-fallback">LOGO<br>SEKOLAH</div>
                    @endif
                </td>

                <td class="content-cell">
                    <div class="item-name">{{ $item->name }}</div>
                    <div class="inventory-code">
                        {!! implode('<br>', array_map('e', $locationLines)) !!}
                    </div>
                </td>

                <td class="qr-cell">
                    <img src="{{ $qr }}" alt="QR Code">
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <div class="footer">
            {{ $setting->institution_name }}
        </div>
    </div>
</body>
</html>
