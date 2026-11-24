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
     * Versi skema payload QR. Dinaikkan bila struktur berubah; scanner tetap
     * menyelesaikan QR lama karena unit selalu dicari ulang di database.
     */
    public const QR_RELEASE = 2;

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
     * Payload QR unit: URL halaman scan dengan metadata identitas.
     *
     * Isi QR memuat release skema, ID unit stabil, kode inventaris, serta
     * nama gedung/lantai/ruangan/nomor unit sebagai petunjuk. Saat dipindai,
     * server memvalidasi ulang unit dan lokasi aktif dari database — nilai
     * pada QR tidak pernah menjadi sumber kebenaran.
     */
    public function unitQrPayload(AssetUnit $unit): string
    {
        $room = $unit->room;

        return route('scan', [
            // Identitas stabil unit adalah sumber kebenaran QR. Kode, spesifikasi,
            // dan lokasi hanya petunjuk; server selalu membaca ulang database.
            'u' => $unit->id,
            'c' => $unit->inventory_code,
            's' => $unit->specification_id,
            'r' => self::QR_RELEASE,
            'b' => $room?->building?->name ?: ($room?->buildingCode() ?? ''),
            'f' => $room?->floor,
            'rn' => $room?->room_no,
            'un' => $unit->unit_no,
        ]);
    }

    public function unitQrDataUrl(AssetUnit $unit): string
    {
        return 'data:image/png;base64,'.base64_encode($this->renderQrPng($this->unitQrPayload($unit)));
    }

    /**
     * Ukuran halaman label unit (pt). Halaman sama dengan kotak biru label agar
     * hasil cetak pas dengan frame stiker pre-print tanpa sisa margin putih.
     */
    public const UNIT_LABEL_SIZE = [212, 80];

    /**
     * Kandidat ukuran font [nama barang, merek/tipe], dari paling besar ke
     * paling kecil. Pasangan pertama yang membuat label tetap muat di satu
     * halaman dipakai.
     */
    private const UNIT_LABEL_FONT_SIZES = [
        [9, 7],
        [9, 6.5],
        [8.5, 6.5],
        [8.5, 6],
        [8, 6],
        [7.5, 5.8],
        [7, 5.5],
        [6.5, 5.5],
    ];

    /**
     * Batas tinggi kotak label (pt) agar halaman 80 pt tidak meluap.
     * Kotak setinggi 79.5 pt masih menyisakan 0.5 pt di dalam halaman.
     */
    private const UNIT_LABEL_MAX_HEIGHT = 79.5;

    /** Label per unit fisik (revisi 2.1). */
    public function printUnit(AssetUnit $unit): \Barryvdh\DomPDF\PDF
    {
        $setting = InstitutionSetting::current();

        $unit->forceFill(['last_label_printed_at' => now()])->saveQuietly();

        activity('label')
            ->performedOn($unit)
            ->causedBy(auth()->user())
            ->log('Mencetak label unit '.$unit->inventory_code);

        $data = [
            // Label harus membaca identitas dari unit → spesifikasi, bukan dari
            // data pengadaan pertama pada katalog.
            'unit' => $unit->loadMissing(['catalog', 'specification', 'room.building']),
            'setting' => $setting,
            'qr' => $this->unitQrDataUrl($unit),
        ];

        [$nameSize, $identitySize] = $this->unitLabelFontSizes($data);

        return Pdf::loadView('pdf.unit_label', [
            ...$data,
            'nameSize' => $nameSize,
            'identitySize' => $identitySize,
        ])->setPaper([0, 0, ...self::UNIT_LABEL_SIZE], 'pt');
    }

    /**
     * Pilih ukuran font [nama, merek/tipe] terbesar yang masih muat satu halaman.
     *
     * Nama barang bisa sangat panjang dan merek/tipe spesifikasi juga panjang,
     * sehingga pada ukuran terbesar teks membungkus lebih banyak baris dan kotak
     * label melebihi halaman. Kandidat dicoba berurutan sampai ada yang muat.
     *
     * @param  array{unit: AssetUnit, setting: InstitutionSetting, qr: string}  $data
     * @return array{0: float, 1: float}
     */
    protected function unitLabelFontSizes(array $data): array
    {
        $fallback = self::UNIT_LABEL_FONT_SIZES[array_key_last(self::UNIT_LABEL_FONT_SIZES)];

        foreach (self::UNIT_LABEL_FONT_SIZES as [$nameSize, $identitySize]) {
            $height = $this->unitLabelHeight($data + [
                'nameSize' => $nameSize,
                'identitySize' => $identitySize,
            ]);

            if ($height <= self::UNIT_LABEL_MAX_HEIGHT) {
                return [(float) $nameSize, (float) $identitySize];
            }
        }

        return [(float) $fallback[0], (float) $fallback[1]];
    }

    /**
     * Ukur tinggi kotak label (pt) dari hasil render Dompdf.
     *
     * Kotak label tidak punya tinggi tetap, jadi tingginya dibaca dari frame
     * `.label` saat tahap render selesai — cara paling akurat karena mengikuti
     * pembungkusan teks asli.
     *
     * @param  array<string, mixed>  $data
     */
    protected function unitLabelHeight(array $data): float
    {
        // Halaman dibuat jauh lebih tinggi dari label dan tinggi kotak dilepas
        // (`measure`) agar pengukuran mengikuti isi sebenarnya, bukan tinggi
        // tetap label, serta tidak terpotong oleh pemenggalan halaman.
        $probe = Pdf::loadView('pdf.unit_label', $data + ['measure' => true])
            ->setPaper([0, 0, self::UNIT_LABEL_SIZE[0], 400], 'pt');

        $height = 0.0;

        $probe->getDomPDF()->setCallbacks([[
            'event' => 'end_page_render',
            'f' => function ($frame) use (&$height): void {
                $stack = [$frame];

                while ($stack) {
                    $current = array_pop($stack);

                    if (! is_object($current) || ! method_exists($current, 'get_node')) {
                        continue;
                    }

                    $node = $current->get_node();

                    if ($node instanceof \DOMElement && str_contains((string) $node->getAttribute('class'), 'label')) {
                        $height = max($height, $current->get_border_box()['h']);
                    }

                    for ($child = $current->get_first_child(); $child; $child = $child->get_next_sibling()) {
                        $stack[] = $child;
                    }
                }
            },
        ]]);

        $probe->render();

        return $height;
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
            ->with(['catalog.category', 'specification', 'room.building'])
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
