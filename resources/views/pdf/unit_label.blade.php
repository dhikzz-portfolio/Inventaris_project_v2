<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 2pt; font-family: Helvetica, Arial, sans-serif; }
        .label { width: 242pt; height: 106pt; border: 2pt solid #155dfc; border-radius: 4pt; padding: 3pt 5pt; }
        .head { height: 86pt; }
        table.inner { width: 100%; border-collapse: collapse; }
        table.inner td { vertical-align: top; padding: 0; }
        .logo-cell { width: 44pt; text-align: center; vertical-align: middle; }
        .logo-cell img { max-width: 40pt; max-height: 40pt; }
        .logo-fallback { font-size: 7pt; font-weight: bold; color: #333; }
        .codes { padding-left: 4pt !important; }
        .kode-unit { font-family: 'Courier New', monospace; font-size: 16pt; font-weight: bold; line-height: 1.05; }
        .nama { font-size: 9.5pt; font-weight: bold; text-transform: uppercase; margin-bottom: 1pt; line-height: 1.1; }
        .lokasi { font-size: 7pt; margin-top: 1pt; }
        .qr-cell { width: 46pt; text-align: right; }
        .qr-cell img { width: 42pt; height: 42pt; }
        .footer { margin-top: 5pt; border-top: 1pt solid #8ec5ff; padding-top: 2pt; font-size: 7.5pt; font-weight: bold; text-align: center; text-transform: uppercase; color: #1c398e; }
    </style>
</head>
<body>
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
                        {{-- Nama barang = header pembeda; kode unit tetap satu-satunya identitas --}}
                        <div class="nama">{{ $unit->catalog?->name }}</div>
                        <div class="kode-unit">{{ $unit->inventory_code }}</div>
                        <div class="lokasi">{{ $unit->locationLabel() }}</div>
                    </td>
                    <td width="46" class="qr-cell">
                        <img src="{{ $qr }}" alt="QR">
                    </td>
                </tr>
            </table>
        </div>
        <div class="footer">{{ $setting->institution_name }}</div>
    </div>
</body>
</html>
