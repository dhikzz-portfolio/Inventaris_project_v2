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
        // Pemisah harus karakter ASCII: Dompdf tidak selalu bisa memetakan
        // em dash dan mencetaknya sebagai karakter rusak.
        ->implode(' - ');
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; font-family: Helvetica, Arial, sans-serif; color: #111827; }

        /* Dompdf memakai model content-box (box-sizing diabaikan), jadi ukuran ini
           belum termasuk padding 9pt kiri/kanan serta 6pt/4pt atas-bawah dan border
           2.5pt: 189 + 18 + 5 = 212pt dan 65 + 10 + 5 = 80pt, tepat seukuran
           halaman. Kotak biru menutup seluruh halaman seperti frame pada stiker
           pre-print, tanpa sisa margin putih. LabelService memilih ukuran font
           yang menjaga isi tetap di dalam tinggi 65pt (satu halaman). */
        .label {
            width: 189pt;
            /* Saat LabelService mengukur tinggi isi, tinggi tetap dilepas agar
               hasil ukur mengikuti pembungkusan teks sebenarnya. */
            height: {{ ($measure ?? false) ? 'auto' : '65pt' }};
            border: 2.5pt solid #2563eb;
            border-radius: 7pt;
            padding: 6pt 9pt 4pt;
        }

        /* Nama barang memakai lebar penuh label. Di kolom sempit nama panjang
           butuh 4-5 baris sehingga label pecah ke halaman lain. */
        .nama {
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            line-height: 1.05;
            color: #111827;
        }

        table.inner { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 2pt; }
        table.inner td { padding: 0; vertical-align: middle; }

        .logo-cell { width: 30pt; text-align: left; }
        .logo-cell img { max-width: 29pt; max-height: 29pt; }
        .logo-fallback { font-size: 6pt; font-weight: bold; color: #475569; line-height: 1.2; }

        .codes { text-align: center; }

        .spesifikasi {
            color: #64748b;
            line-height: 1.2;
        }

        .kode-unit {
            margin-top: 1.5pt;
            font-family: 'Courier New', monospace;
            font-size: 12pt;
            font-weight: bold;
            letter-spacing: 0.5pt;
            line-height: 1.1;
            white-space: nowrap;
            color: #1c398e;
        }

        .qr-cell { width: 29pt; text-align: right; }
        .qr-cell img { width: 28pt; height: 28pt; }

        .divider {
            margin-top: 3.5pt;
            border-top: 1pt solid #a9c4ea;
        }

        .footer {
            margin-top: 0.5pt;
            font-size: 7.5pt;
            font-weight: bold;
            letter-spacing: 0.7pt;
            text-align: center;
            text-transform: uppercase;
            line-height: 1.1;
            color: #1c398e;
        }
    </style>
</head>
<body>
    <div class="label">
        {{-- Ukuran font nama dipilih LabelService agar label tetap satu halaman. --}}
        <div class="nama" style="font-size: {{ $nameSize ?? 9 }}pt">{{ $unit->catalog?->name }}</div>
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
                    @if ($identity !== '')
                        <div class="spesifikasi" style="font-size: {{ $identitySize ?? 7 }}pt">{{ $identity }}</div>
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
