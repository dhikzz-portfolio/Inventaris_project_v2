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
        foreach ($items as $item) {
            $qrMap[$item->id] = $this->qrDataUrl($item);
        }

        activity('label')
            ->log('Mencetak label massal '.count($items).' stiker');

        return Pdf::loadView('pdf.label_massal', [
            'items' => $items,
            'setting' => $setting,
            'qrMap' => $qrMap,
        ])->setPaper('a4');
    }
}
