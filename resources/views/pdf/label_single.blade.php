<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; }
        .label {
            width: 256pt; height: 116pt;
            border: 2pt solid #000;
            border-radius: 4pt;
            display: table;
        }
        .logo-cell {
            display: table-cell;
            width: 62pt;
            border-right: 1pt solid #999;
            text-align: center;
            vertical-align: middle;
            padding: 4pt;
        }
        .logo-cell img { max-width: 52pt; max-height: 52pt; }
        .logo-fallback { font-size: 7pt; font-weight: bold; color: #333; }
        .content-cell { display: table-cell; vertical-align: top; padding: 5pt 6pt 0 6pt; position: relative; }
        .inner { display: table; width: 100%; }
        .inner .codes { display: table-cell; vertical-align: top; }
        .inner .qr { display: table-cell; width: 62pt; text-align: right; vertical-align: top; }
        .inner .qr img { width: 56pt; height: 56pt; }
        .kode-lokasi {
            font-family: 'Courier New', monospace;
            font-size: 14pt; font-weight: bold; letter-spacing: 0.5pt;
            line-height: 1.1;
        }
        .kode-barang {
            font-family: 'Courier New', monospace;
            font-size: 11pt; font-weight: bold; letter-spacing: 0.4pt;
            margin-top: 3pt;
        }
        .footer {
            position: absolute;
            left: 6pt; right: 6pt; bottom: 4pt;
            border-top: 1pt solid #000;
            padding-top: 2pt;
            font-size: 7.5pt; font-weight: bold; text-align: center;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
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
                    {{-- Kanan Atas: deret angka Kode Lokasi/Instansi --}}
                    <div class="kode-lokasi">{{ $item->location_code }}</div>
                    {{-- Kanan Bawah: Kode Klasifikasi Barang + Nomor Urut Registrasi --}}
                    <div class="kode-barang">{{ $item->asset_code }}.{{ $item->register_no }}</div>
                </div>
                <div class="qr">
                    <img src="{{ $qr }}" alt="QR">
                </div>
            </div>
            {{-- Footer: nama instansi --}}
            <div class="footer">{{ $setting->institution_name }}</div>
        </div>
    </div>
</body>
</html>
