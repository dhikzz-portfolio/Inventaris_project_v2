<?php

namespace App\Services;

use App\Models\InstitutionSetting;
use App\Models\Item;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Barryvdh\DomPDF\Facade\Pdf;

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
     * Cetak label massal: banyak stiker dalam satu lembar A4.
     *
     * @param  list<int>  $itemIds
     */
    public function printMassal(array $itemIds): \Barryvdh\DomPDF\PDF
    {
        $setting = InstitutionSetting::current();

        $items = Item::with(['room', 'category'])
            ->approved()
            ->whereIn('id', $itemIds)
            ->get();

        Item::whereIn('id', $items->pluck('id'))->update(['last_label_printed_at' => now()]);

        $qrMap = [];
        $locationLinesMap = [];
        foreach ($items as $item) {
            $qrMap[$item->id] = $this->qrDataUrl($item);
            $locationLinesMap[$item->id] = $this->splitLocationCode((string) $item->location_code);
        }

        activity('label')
            ->log('Mencetak label massal '.count($items).' stiker');

        return Pdf::loadView('pdf.label_massal', [
            'items' => $items,
            'setting' => $setting,
            'qrMap' => $qrMap,
            'locationLinesMap' => $locationLinesMap,
        ])->setPaper('a4');
    }
}
