<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 1pt; font-family: Helvetica, Arial, sans-serif; }
        .label { width: 242pt; height: 106pt; border: 1.5pt solid #1c398e; border-radius: 2pt; padding: 2pt 3pt; }
        .head { height: 88pt; }
        table.inner { width: 100%; border-collapse: collapse; }
        table.inner td { vertical-align: top; padding: 0; }
        .logo-cell { width: 38pt; text-align: center; vertical-align: middle; }
        .logo-cell img { max-width: 34pt; max-height: 34pt; }
        .logo-fallback { font-size: 6pt; font-weight: bold; color: #333; }
        .codes { padding-left: 3pt !important; }
        .kode-unit { font-family: 'Courier New', monospace; font-size: 14pt; font-weight: bold; line-height: 1.0; letter-spacing: 0.5pt; }
        .nama { font-size: 8pt; font-weight: bold; text-transform: uppercase; margin-bottom: 1pt; line-height: 1.05; }
        .qr-cell { width: 50pt; text-align: right; vertical-align: middle; }
        .qr-cell img { width: 46pt; height: 46pt; }
        .footer { margin-top: 2pt; padding-top: 1pt; font-size: 6.5pt; font-weight: bold; text-align: center; text-transform: uppercase; color: #1c398e; }
    </style>
</head>
<body>
    <div class="label">
        <div class="head">
            <table class="inner">
                <tr>
                    <td width="38" class="logo-cell">
                        @if ($setting->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($setting->logo_path))
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->path($setting->logo_path) }}" alt="Logo">
                        @else
                            <div class="logo-fallback">LOGO<br>SEKOLAH</div>
                        @endif
                    </td>
                    <td class="codes">
                        <div class="nama">{{ $unit->catalog?->name }}</div>
                        <div class="kode-unit">{{ $unit->inventory_code }}</div>
                    </td>
                    <td width="50" class="qr-cell">
                        <img src="{{ $qr }}" alt="QR">
                    </td>
                </tr>
            </table>
        </div>
        <div class="footer">{{ $setting->institution_name }}</div>
    </div>
</body>
</html>
