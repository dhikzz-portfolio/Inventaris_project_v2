<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"><style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 2pt; font-family: Helvetica, Arial, sans-serif; }
        /* width/height = content box; padding+border (14pt x 10pt) melengkapi 256x116pt */
        .label {
            width: 242pt; height: 106pt;
            border: 2pt solid #155dfc;
            border-radius: 4pt;
            padding: 3pt 5pt;
        }
        .head { height: 86pt; }
        table.inner { width: 100%; border-collapse: collapse; }
        table.inner td { vertical-align: top; padding: 0; }
        .logo-cell { width: 48pt; text-align: center; vertical-align: middle; }
        .logo-cell img { max-width: 44pt; max-height: 44pt; }
        .logo-fallback { font-size: 7pt; font-weight: bold; color: #333; }
        .codes { padding-left: 4pt !important; }
        .kode-lokasi {
            font-family: 'Courier New', monospace;
            font-size: 14pt; font-weight: bold; line-height: 1.1;
            color: #1c398e;
        }
        .kode-barang {
            font-family: 'Courier New', monospace;
            font-size: 11pt; font-weight: bold; line-height: 1.1;
            margin-top: 3pt;
            color: #1c398e;
        }
        .qr-cell { width: 46pt; text-align: right; }
        .qr-cell img { width: 42pt; height: 42pt; }
        .footer {
            margin-top: 5pt;
            border-top: 1pt solid #8ec5ff;
            padding-top: 2pt;
            font-size: 7.5pt; font-weight: bold; text-align: center;
            text-transform: uppercase;
            color: #1c398e;
        }</style>
</head>
<body>
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
                        {{-- Kanan Atas: deret angka Kode Lokasi/Instansi (maks. 2 baris) --}}
                        <div class="kode-lokasi">{!! implode('<br>', array_map('e', $locationLines)) !!}</div>
                        {{-- Kanan Bawah: Kode Klasifikasi Barang + Nomor Urut Registrasi --}}
                        <div class="kode-barang">{{ $item->asset_code }}.{{ $item->register_no }}</div>
                    </td>
                    <td width="46" class="qr-cell">
                        <img src="{{ $qr }}" alt="QR">
                    </td>
                </tr>
            </table>
        </div>
        {{-- Footer: nama instansi, mengalir normal agar tidak menabrak border --}}
        <div class="footer">{{ $setting->institution_name }}</div>
    </div>
</body>
</html>
