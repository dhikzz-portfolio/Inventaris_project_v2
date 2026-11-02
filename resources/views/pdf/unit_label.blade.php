@php
    // Identitas label selalu dari relasi unit → spesifikasi. Kolom merek pada
    // katalog hanya cadangan untuk unit lama yang belum memiliki spesifikasi.
    $specification = $unit->specification;
    $brand = $specification?->brandLabel() ?: ($unit->catalog?->brand ?? '');
    $model = $specification?->modelLabel() ?: ($unit->catalog?->model_type ?? '');
    // Sembunyikan nilai placeholder dari data lama agar label tetap bersih.
    $identity = collect([$brand, $model])
        ->map(fn ($value) => trim((string) $value))
        ->reject(fn ($value) => $value === '' || in_array($value, [\App\Models\Specification::BRAND_UNSPECIFIED, \App\Models\Specification::MODEL_UNSPECIFIED, '-'], true))
        ->implode(' — ');
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 1pt; font-family: Helvetica, Arial, sans-serif; color: #111827; }

        .label {
            width: 252pt;
            height: 102pt;
            border: 2.5pt solid #2563eb;
            border-radius: 7pt;
            padding: 9pt 10pt 6pt;
        }

        table.inner { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.inner td { padding: 0; vertical-align: middle; }

        .logo-cell { width: 50pt; text-align: left; }
        .logo-cell img { max-width: 46pt; max-height: 46pt; }
        .logo-fallback { font-size: 7pt; font-weight: bold; color: #475569; line-height: 1.2; }

        .codes { padding-left: 9pt; text-align: center; }

        .nama {
            font-size: 11.5pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.1;
            color: #111827;
        }

        .spesifikasi {
            margin-top: 1.5pt;
            font-size: 7pt;
            color: #64748b;
            line-height: 1.2;
        }

        .kode-unit {
            margin-top: 2.5pt;
            font-family: 'Courier New', monospace;
            font-size: 17pt;
            font-weight: bold;
            letter-spacing: 0.8pt;
            line-height: 1.1;
            white-space: nowrap;
            color: #1c398e;
        }

        .qr-cell { width: 44pt; text-align: right; }
        .qr-cell img { width: 42pt; height: 42pt; }

        .divider {
            margin-top: 13pt;
            border-top: 1pt solid #a9c4ea;
        }

        .footer {
            margin-top: 6pt;
            font-size: 8.5pt;
            font-weight: bold;
            letter-spacing: 0.7pt;
            text-align: center;
            text-transform: uppercase;
            color: #1c398e;
        }
    </style>
</head>
<body>
    <div class="label">
        <table class="inner">
            <tr>
                <td class="logo-cell">
                    @if ($setting->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($setting->logo_path))
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->path($setting->logo_path) }}" alt="Logo">
                    @else
                        <div class="logo-fallback">LOGO<br>SEKOLAH</div>
                    @endif
                </td>
                <td class="codes">
                    <div class="nama">{{ $unit->catalog?->name }}</div>
                    @if ($identity !== '')
                        <div class="spesifikasi">{{ $identity }}</div>
                    @endif
                    <div class="kode-unit">{{ $unit->inventory_code }}</div>
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
