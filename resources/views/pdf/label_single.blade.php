<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            font-family: Helvetica, Arial, sans-serif;
            color: #111827;
            background-color: #ffffff;
        }

        /* Satu stiker = satu halaman 260 x 120pt.
           Dompdf memakai model content-box, jadi border 2pt belum termasuk di sini:
           256 + 4 = 260pt lebar dan 115.5 + 4 = 119.5pt tinggi. Sisa 0.5pt halaman
           mencegah pembulatan sub-piksel melahirkan halaman kedua yang kosong. */
        .label {
            position: relative;
            width: 256pt;
            height: 115.5pt;
            border: 2pt solid #2563eb;
            border-radius: 6pt;
        }

        /* Nama barang menjadi judul kecil di atas, mengikuti posisi nama katalog
           pada label unit, sehingga teks besar di tengah bisa dipakai kode. */
        .item-name {
            padding: 6pt 10pt 0;
            font-size: 9.5pt;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            line-height: 1.1;
            color: #111827;
        }

        /* Badan tengah: logo | kode | QR. Tinggi sel dipakai Dompdf sebagai tinggi
           baris sekaligus membuat vertical-align: middle benar-benar menengahkan
           isi, jadi divider dan footer di bawahnya tidak bertabrakan. */
        table.inner {
            width: 100%;
            border-collapse: collapse;
        }

        table.inner td {
            height: 78pt;
            padding: 0;
            vertical-align: middle;
        }

        .logo-cell {
            width: 54pt;
            padding-left: 8pt;
            text-align: left;
        }

        .logo-cell img {
            width: 46pt;
            height: 46pt;
        }

        .logo-fallback {
            font-size: 6pt;
            font-weight: bold;
            line-height: 1.2;
            text-align: center;
            color: #475569;
        }

        .codes-cell {
            text-align: center;
        }

        /* Merek/tipe hanya petunjuk tambahan; disembunyikan bila data kosong. */
        .identity {
            font-size: 7pt;
            line-height: 1.2;
            color: #64748b;
        }

        .location {
            font-family: 'Courier New', monospace;
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 0.5pt;
            line-height: 1.15;
            white-space: nowrap;
            color: #1c398e;
        }

        .qr-cell {
            width: 54pt;
            padding-right: 8pt;
            text-align: right;
        }

        .qr-cell img {
            width: 46pt;
            height: 46pt;
        }

        /* Garis pemisah dan footer ditempel ke dasar kotak label. */
        .divider {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 18pt;
            border-top: 1pt solid #2563eb;
        }

        .footer {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 4.5pt;
            font-size: 7.5pt;
            font-weight: bold;
            letter-spacing: 0.6pt;
            text-align: center;
            text-transform: uppercase;
            line-height: 1.1;
            color: #1c398e;
        }
    </style>
</head>
<body>
    <div class="label">
        <div class="item-name">{{ $item->name }}</div>

        <table class="inner">
            <tr>
                <td class="logo-cell">
                    @if ($setting->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($setting->logo_path))
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->path($setting->logo_path) }}" alt="Logo">
                    @else
                        <div class="logo-fallback">LOGO<br>SEKOLAH</div>
                    @endif
                </td>
                <td class="codes-cell">
                    @php
                        $identity = trim(implode(' - ', array_filter([(string) $item->brand, (string) $item->model_type])));
                    @endphp
                    @if ($identity !== '')
                        <div class="identity">{{ $identity }}</div>
                    @endif
                    <div class="location">{!! implode('<br>', array_map('e', $locationLines)) !!}</div>
                </td>
                <td class="qr-cell">
                    <img src="{{ $qr }}" alt="QR">
                </td>
            </tr>
        </table>

        <div class="divider"></div>
        <div class="footer">{{ $setting->institution_name }}</div>
    </div>
</body>
</html>
