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
        .grid tr, .grid td {
            font-size: 0;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #111827;
            background-color: #ffffff;
        }

        /* Label Container — 1 stiker = 1 halaman penuh.
           Dompdf memakai model content-box (box-sizing diabaikan), jadi ukuran
           konten ini belum termasuk border 2pt: 256 + 4 = 260pt lebar dan
           115.5 + 4 = 119.5pt tinggi, menyisakan 0.5pt halaman agar pembulatan
           sub-piksel tidak melahirkan halaman kedua yang kosong. */
        .label {
            width: 256pt;
            height: 115.5pt;
            margin: 0;
            border: 2pt solid #2563eb;
            border-radius: 6pt;
        }

        /* Pembagian tinggi konten: badan label 96.5pt + footer institusi 19pt
           (18pt tinggi + 1pt garis atas).

           Tabel tidak dipakai di sini: Dompdf mengabaikan table-layout, sehingga
           kolom tengah menyempit dan nama barang pecah/overlap. Posisi absolut
           memberi lebar kolom yang pasti di setiap mesin cetak. */
        .label-body {
            position: relative;
            height: 96.5pt;
        }

        /* Logo Section */
        .logo-box {
            position: absolute;
            left: 7pt;
            top: 25.25pt;
            width: 46pt;
            height: 46pt;
        }

        .logo-box img {
            width: 46pt;
            height: 46pt;
        }

        .logo-fallback {
            font-size: 5.5pt;
            line-height: 1.3;
            text-align: center;
            color: #475569;
        }

        /* Content Section — kolom tengah antara logo dan QR. */
        .content-box {
            position: absolute;
            left: 60pt;
            top: 0;
            width: 140pt;
            text-align: center;
        }

        /* Tinggi sel dipakai Dompdf sebagai tinggi baris, sehingga
           vertical-align: middle benar-benar menengahkan isi label. */
        .content-table {
            width: 100%;
            border-collapse: collapse;
        }

        .content-box td {
            height: 96.5pt;
            padding: 0;
            vertical-align: middle;
            text-align: center;
        }

        .item-name {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
            line-height: 1.15;
            color: #111827;
            padding-bottom: 2.5pt;
        }

        .codes {
            font-family: 'Courier New', monospace;
            font-size: 11.5pt;
            font-weight: bold;
            letter-spacing: 0.2pt;
            line-height: 1.3;
            color: #1e40af;
            padding-top: 2pt;
        }

        /* QR Code Section */
        .qr-box {
            position: absolute;
            right: 7pt;
            top: 25.25pt;
            width: 46pt;
            height: 46pt;
        }

        .qr-box img {
            width: 46pt;
            height: 46pt;
        }

        /* Footer */
        .footer {
            height: 18pt;
            line-height: 17pt;
            border-top: 1pt solid #93c5fd;
            padding: 0 4pt;
            font-size: 7.5pt;
            font-weight: bold;
            letter-spacing: 0.6pt;
            text-align: center;
            text-transform: uppercase;
            color: #1e40af;
        }    </style>
</head>
<body>
    <div class="label">
        <div class="label-body">
            <div class="logo-box">
                @if ($setting->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($setting->logo_path))
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->path($setting->logo_path) }}" alt="Logo">
                @else
                    <div class="logo-fallback">LOGO<br>SEKOLAH</div>
                @endif
            </div>

            <div class="content-box">
                <table class="content-table">
                    <tr>
                        <td>
                            <div class="item-name">{{ $item->name }}</div>
                            <div class="codes">
                                {!! implode('<br>', array_map('e', $locationLines)) !!}
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="qr-box">
                <img src="{{ $qr }}" alt="QR Code">
            </div>
        </div>
        <div class="footer">
            {{ $setting->institution_name }}
        </div>
    </div>
</body>
</html>
