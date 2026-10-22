<?php

namespace App\Services;

use App\Enums\UnitStatus;
use App\Models\AssetUnit;
use App\Models\InstitutionSetting;
use App\Models\Item;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;

class LabelService
{
    /**
     * QR PNG base64 berisi URL detail barang yang tervalidasi.
     * Kamera bawaan ponsel akan membuka halaman detail (login bila belum),
     * sementara scanner internal juga menerima URL maupun ID internal.
     */
    public function qrDataUrl(Item $item): string
    {
        return 'data:image/png;base64,'.base64_encode($this->renderQrPng(route('items.show', $item)));
    }

    public function unitQrDataUrl(AssetUnit $unit): string
    {
        return 'data:image/png;base64,'.base64_encode($this->renderQrPng(route('units.show', $unit)));
    }

    /** Label per unit fisik (revisi 2.1). */
    public function printUnit(AssetUnit $unit): \Barryvdh\DomPDF\PDF
    {
        $setting = InstitutionSetting::current();

        $unit->forceFill(['last_label_printed_at' => now()])->saveQuietly();

        activity('label')
            ->performedOn($unit)
            ->causedBy(auth()->user())
            ->log('Mencetak label unit '.$unit->inventory_code);

        return Pdf::loadView('pdf.unit_label', [
            'unit' => $unit->loadMissing(['catalog', 'room']),
            'setting' => $setting,
            'qr' => $this->unitQrDataUrl($unit),
        ])->setPaper([0, 0, 260, 120], 'pt');
    }

    /**
     * Render QR ke PNG memakai ekstensi GD (server tanpa imagick tetap bisa cetak label).
     */
    protected function renderQrPng(string $text, int $moduleSize = 5): string
    {
        $matrix = Encoder::encode($text, ErrorCorrectionLevel::M())->getMatrix();
        $modules = $matrix->getWidth();
        $margin = 1;
        $size = ($modules + $margin * 2) * $moduleSize;

        $image = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $size, $size, $white);

        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    imagefilledrectangle(
                        $image,
                        ($x + $margin) * $moduleSize,
                        ($y + $margin) * $moduleSize,
                        ($x + $margin + 1) * $moduleSize - 1,
                        ($y + $margin + 1) * $moduleSize - 1,
                        $black
                    );
                }
            }
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    /**
     * Pecah kode lokasi panjang menjadi (maksimal) dua baris agar muat di label.
     *
     * Pemecahan dilakukan pada titik terdekat dari tengah kode, misalnya
     * `12.24.13.08.01.07.01.01` menjadi `['12.24.13.08', '01.07.01.01']`.
     *
     * @return list<string>
     */
    public function splitLocationCode(string $code): array
    {
        $code = trim($code);

        if ($code === '' || ! str_contains($code, '.')) {
            return [$code];
        }

        $groups = explode('.', $code);
        $groupCount = count($groups);

        if ($groupCount < 2) {
            return [$code];
        }

        $splitAt = max(1, min($groupCount - 1, (int) round($groupCount / 2)));

        return [
            implode('.', array_slice($groups, 0, $splitAt)),
            implode('.', array_slice($groups, $splitAt)),
        ];
    }

    /**
     * Cetak label satuan — dipanggil ulang (re-print) dari halaman detail barang.
     */
    public function printSingle(Item $item): \Barryvdh\DomPDF\PDF
    {
        $setting = InstitutionSetting::current();

        $item->forceFill(['last_label_printed_at' => now()])->saveQuietly();

        activity('label')
            ->performedOn($item)
            ->causedBy(auth()->user())
            ->log('Mencetak label (re-print)'/* re-print maupun cetak awal */);

        return Pdf::loadView('pdf.label_single', [
            'item' => $item,
            'setting' => $setting,
            'qr' => $this->qrDataUrl($item),
            'locationLines' => $this->splitLocationCode((string) $item->location_code),
        ])->setPaper([0, 0, 260, 120], 'pt');
    }

    /**
     * Cetak label massal per unit fisik: satu stiker untuk setiap unit yang
     * dipilih, berisi kode inventaris unit tersebut (revisi 2.1 — keputusan 8).
     *
     * Hanya unit yang terlihat oleh pengguna aktif, bukan unit yang sudah
     * dihapus, dan berasal dari katalog yang sudah disetujui yang dicetak.
     * Unit di luar kewenangan pengguna tidak menghasilkan label apa pun.
     *
     * @param  list<int>  $unitIds
     * @return array{0: \Barryvdh\DomPDF\PDF, 1: Collection} PDF dan unit yang benar-benar dicetak.
     */
    public function printUnits(array $unitIds): array
    {
        $setting = InstitutionSetting::current();

        $units = AssetUnit::query()
            ->visibleTo(auth()->user())
            ->whereHas('catalog', fn ($query) => $query->approved())
            ->where('status', '!=', UnitStatus::DIHAPUS->value)
            ->with(['catalog.category', 'room'])
            ->whereIn('id', $unitIds)
            ->orderBy('inventory_code')
            ->get();

        if ($units->isEmpty()) {
            abort(422, 'Tidak ada unit yang dapat dicetak dalam cakupan akses Anda.');
        }

        AssetUnit::whereIn('id', $units->pluck('id'))->update(['last_label_printed_at' => now()]);

        $qrMap = [];
        foreach ($units as $unit) {
            $qrMap[$unit->id] = $this->unitQrDataUrl($unit);
        }

        activity('label')
            ->log('Mencetak label massal '.count($units).' unit');

        $pdf = Pdf::loadView('pdf.unit_massal', [
            'units' => $units,
            'setting' => $setting,
            'qrMap' => $qrMap,
        ])->setPaper('a4');

        return [$pdf, $units];
    }
}
