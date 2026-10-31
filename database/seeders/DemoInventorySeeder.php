<?php

namespace Database\Seeders;

use App\Enums\Condition;
use App\Enums\ItemStatus;
use App\Enums\UnitStatus;
use App\Models\Category;
use App\Models\InstitutionSetting;
use App\Models\Item;
use App\Models\Room;
use App\Models\Specification;
use App\Services\AssetUnitService;
use App\Services\IdGenerator;
use Illuminate\Database\Seeder;

class DemoInventorySeeder extends Seeder
{
    /**
     * Data demo: katalog mengelompokkan jenis barang, spesifikasi memisahkan
     * merek + tipe/model, dan setiap unit fisik mendapat kode inventaris
     * sendiri. Jumlah katalog diturunkan dari unit, bukan diisi manual.
     *
     * Seeder aman dijalankan berulang: katalog dicari berdasarkan nama,
     * spesifikasi berdasarkan merek + tipe, dan unit yang sudah ada dilewati.
     */
    public function run(): void
    {
        $setting = InstitutionSetting::first();
        $categories = Category::query()->pluck('id', 'name');
        $rooms = Room::query()->pluck('id', 'code');
        $units = app(AssetUnitService::class);

        foreach ($this->definitions() as $def) {
            $categoryId = $categories[$def['category']] ?? null;
            $roomId = $rooms[$def['room']] ?? null;

            if ($categoryId === null || $roomId === null) {
                continue;
            }

            $room = Room::find($roomId);

            /** @var Item $item */
            $item = Item::firstOrCreate(
                ['name' => $def['name']],
                [
                    'internal_id' => IdGenerator::internalId($room),
                    'location_code' => $setting?->location_code ?? '12.24.13.08.01.07.01.01',
                    'register_no' => IdGenerator::register(null)['register_no'],
                    'name' => $def['name'],
                    'brand' => '',
                    'model_type' => '',
                    'category_id' => $categoryId,
                    'room_id' => $room->id,
                    'specification' => $def['spec'] ?? null,
                    'acquisition_year' => $def['year'],
                    'funding_source' => $def['funding'],
                    'quantity' => 0,
                    'qty_damaged' => 0,
                    'qty_lost' => 0,
                    'unit' => 'unit',
                    'is_high_value' => $def['high'] ?? false,
                    'status' => ItemStatus::BAIK,
                    'acquisition_status' => 'APPROVED',
                ]
            );

            // Katalog yang sudah berisi unit tidak ditambah ulang (idempoten).
            if ($item->units()->exists()) {
                continue;
            }

            // Definisi dengan beberapa merek dalam satu katalog.
            if (! empty($def['specs'])) {
                foreach ($def['specs'] as $specDef) {
                    $specRoom = Room::find($rooms[$specDef['room']] ?? $room->id);

                    if ($specRoom === null) {
                        continue;
                    }

                    $this->seedSpecificationUnits(
                        $units,
                        $item,
                        $specRoom,
                        Specification::resolveFor($item, $specDef['brand'] ?? null, $specDef['model'] ?? null, $specDef['description'] ?? null),
                        (int) $specDef['qty'],
                        (int) ($specDef['damaged'] ?? 0),
                        $item->acquisition_year,
                    );
                }

                continue;
            }

            $this->seedSpecificationUnits(
                $units,
                $item,
                $room,
                Specification::resolveFor($item, $def['brand'] ?? null, $def['model'] ?? null, $def['spec'] ?? null),
                (int) $def['qty'],
                (int) ($def['damaged'] ?? 0),
                $def['year'],
            );
        }

        // Lengkapi katalog lama yang belum memiliki unit tanpa membuat duplikat.
        $units->provisionAll();
    }

    private function seedSpecificationUnits(
        AssetUnitService $units,
        Item $item,
        Room $room,
        Specification $specification,
        int $quantity,
        int $damaged,
        ?int $year,
    ): void {
        for ($i = 0; $i < $quantity; $i++) {
            $isDamaged = $i < $damaged;

            $units->create($item, $room, [
                'specification_id' => $specification->id,
                'condition' => $isDamaged
                    ? (($i % 2 === 0) ? Condition::RUSAK_RINGAN : Condition::RUSAK_BERAT)
                    : Condition::BAIK,
                'status' => UnitStatus::AKTIF,
                'acquisition_year' => $year,
            ]);
        }
    }

    /**
     * @return list<array{name: string, category: string, room: string, qty?: int, spec?: string, year: int, funding: string, high?: bool, damaged?: int, brand?: string, model?: string, specs?: list<array{brand?: string, model?: string, qty: int, room: string, damaged?: int, description?: string}>}>
     */
    private function definitions(): array
    {
        return [
            // ---- Katalog dengan beberapa merek sekaligus (revisi spesifikasi) ----
            [
                'name' => 'Kursi Umum',
                'category' => 'Furnitur',
                'room' => 'I1-01',
                'spec' => 'Kursi plastik untuk kelas dan ruang umum',
                'year' => 2023,
                'funding' => 'BOS',
                'specs' => [
                    // Satu spesifikasi didistribusikan ke dua ruangan.
                    ['brand' => 'Futura', 'model' => 'Kursi Plastik Tipe A', 'qty' => 12, 'room' => 'I1-01', 'description' => 'Kursi plastik tanpa sandaran lipat'],
                    ['brand' => 'Futura', 'model' => 'Kursi Plastik Tipe A', 'qty' => 8, 'room' => 'I1-02'],
                    // Merek berbeda pada katalog yang sama.
                    ['brand' => 'Chitose', 'model' => 'Kursi Susun', 'qty' => 15, 'room' => 'I1-02', 'damaged' => 2],
                    // Barang tanpa merek dan tanpa tipe/model yang ditentukan.
                    ['brand' => '', 'model' => '', 'qty' => 5, 'room' => 'I1-01'],
                ],
            ],

            // ---- Inventaris umum nonlaboratorium (dikelola Wakasek) ----
            ['name' => 'Kursi Kelas', 'category' => 'Furnitur', 'room' => 'I1-01', 'qty' => 23, 'spec' => 'Kursi siswa rangka besi, tanpa sandaran lipat', 'year' => 2023, 'funding' => 'BOS', 'brand' => 'Chitose', 'model' => 'Kursi Siswa Besi'],
            ['name' => 'Meja Kelas', 'category' => 'Furnitur', 'room' => 'I1-01', 'qty' => 10, 'spec' => 'Meja siswa 120x50cm', 'year' => 2023, 'funding' => 'BOS', 'brand' => 'Futura', 'model' => 'Meja Siswa 120'],
            ['name' => 'Papan Tulis', 'category' => 'Furnitur', 'room' => 'I1-02', 'qty' => 2, 'spec' => 'Whiteboard 240x120cm', 'year' => 2022, 'funding' => 'BOS', 'brand' => 'Sakura', 'model' => 'Whiteboard 240'],
            ['name' => 'Proyektor Epson', 'category' => 'Elektronik & Komputer', 'room' => 'G1-03', 'qty' => 3, 'spec' => '3500 lumen XGA', 'year' => 2022, 'funding' => 'BOS', 'damaged' => 1, 'brand' => 'Epson', 'model' => 'EB-X500'],
            ['name' => 'Meja Kerja', 'category' => 'Furnitur', 'room' => 'G1-03', 'qty' => 1, 'spec' => 'Meja kayu 120x60cm', 'year' => 2022, 'funding' => 'BOS', 'brand' => 'Lokal', 'model' => 'Meja Kerja 120'],
            ['name' => 'Kursi Kantor', 'category' => 'Furnitur', 'room' => 'G1-03', 'qty' => 2, 'spec' => 'Kursi ergonomis hitam', 'year' => 2022, 'funding' => 'BOS', 'brand' => 'Futura', 'model' => 'Kursi Ergonomis'],
            ['name' => 'Meja Guru', 'category' => 'Furnitur', 'room' => 'G1-03', 'qty' => 2, 'spec' => 'Meja guru 150x70cm', 'year' => 2022, 'funding' => 'BOS', 'brand' => 'Futura', 'model' => 'Meja Guru 150'],
            ['name' => 'Lemari Arsip', 'category' => 'Furnitur', 'room' => 'I1-06', 'qty' => 3, 'spec' => 'Lemari besi dua pintu', 'year' => 2021, 'funding' => 'BOS', 'brand' => 'Brother', 'model' => 'Lemari Besi Dua Pintu'],
            ['name' => 'Printer Kantor', 'category' => 'Peralatan Kantor', 'room' => 'I1-06', 'qty' => 2, 'spec' => 'Printer laser monokrom', 'year' => 2024, 'funding' => 'BOS', 'brand' => 'HP', 'model' => 'LaserJet Pro M404'],
            ['name' => 'Komputer Tata Usaha', 'category' => 'Elektronik & Komputer', 'room' => 'I1-06', 'qty' => 2, 'spec' => 'Core i3, RAM 8GB, SSD 256GB', 'year' => 2023, 'funding' => 'BOS', 'brand' => 'Lenovo', 'model' => 'ThinkCentre M70q'],

            // ---- Laboratorium RPL (H1-01, H1-02) ----
            ['name' => 'Komputer PC', 'category' => 'Elektronik & Komputer', 'room' => 'H1-01', 'qty' => 8, 'spec' => 'Core i5, RAM 8GB, SSD 256GB', 'year' => 2023, 'funding' => 'BOS', 'brand' => 'HP', 'model' => 'ProDesk 400 G7'],
            ['name' => 'Laptop Acer Nitro', 'category' => 'Elektronik & Komputer', 'room' => 'H1-01', 'qty' => 2, 'spec' => 'Core i7, RAM 16GB, RTX 3050', 'year' => 2024, 'funding' => 'Komite Sekolah', 'high' => true, 'brand' => 'Acer', 'model' => 'Nitro 5 AN515'],
            ['name' => 'PC Rakitan RPL', 'category' => 'Elektronik & Komputer', 'room' => 'H1-01', 'qty' => 6, 'spec' => 'Ryzen 5, RAM 16GB', 'year' => 2024, 'funding' => 'BOS', 'brand' => 'Tanpa Merek', 'model' => 'Rakitan Ryzen 5'],
            ['name' => 'Switch Jaringan 24 Port', 'category' => 'Elektronik & Komputer', 'room' => 'H1-01', 'qty' => 2, 'spec' => 'Managed switch gigabit', 'year' => 2023, 'funding' => 'BOS', 'brand' => 'TP-Link', 'model' => 'TL-SG1024D'],
            ['name' => 'Router Mikrotik', 'category' => 'Elektronik & Komputer', 'room' => 'H1-01', 'qty' => 2, 'spec' => 'RB951G-2HnD', 'year' => 2023, 'funding' => 'Hibah', 'damaged' => 1, 'brand' => 'Mikrotik', 'model' => 'RB951G-2HnD'],
            ['name' => 'Proyektor Interaktif', 'category' => 'Elektronik & Komputer', 'room' => 'H1-02', 'qty' => 1, 'spec' => 'Proyektor short throw 1080p', 'year' => 2025, 'funding' => 'Dana DAU', 'brand' => 'Epson', 'model' => 'EB-685Wi'],

            // ---- Laboratorium DKV ----
            ['name' => 'Kamera DSLR Canon', 'category' => 'Alat Peraga / Kamera', 'room' => 'G2-01', 'qty' => 2, 'spec' => 'EOS 90D + lensa 18-135mm', 'year' => 2024, 'funding' => 'Dana DAU', 'high' => true, 'brand' => 'Canon', 'model' => 'EOS 90D'],
            ['name' => 'Kamera Mirrorless Sony', 'category' => 'Alat Peraga / Kamera', 'room' => 'G2-01', 'qty' => 1, 'spec' => 'Alpha A6400 + lensa kit', 'year' => 2025, 'funding' => 'Komite Sekolah', 'high' => true, 'brand' => 'Sony', 'model' => 'Alpha A6400'],
            ['name' => 'Tripod Kamera', 'category' => 'Alat Peraga / Kamera', 'room' => 'G2-01', 'qty' => 2, 'spec' => 'Tripod aluminium 160cm', 'year' => 2024, 'funding' => 'BOS', 'brand' => 'Manfrotto', 'model' => 'MT PIXI'],
            ['name' => 'Lampu Studio', 'category' => 'Alat Laboratorium', 'room' => 'G2-01', 'qty' => 2, 'spec' => 'Softbox LED 2x135W', 'year' => 2024, 'funding' => 'BOS', 'brand' => 'Godox', 'model' => 'SL-200W'],
            ['name' => 'Komputer Editing DKV', 'category' => 'Elektronik & Komputer', 'room' => 'G2-02', 'qty' => 4, 'spec' => 'Core i7, RAM 32GB, GPU 8GB', 'year' => 2025, 'funding' => 'Dana DAU', 'brand' => 'Asus', 'model' => 'ProArt Station'],
            ['name' => 'Pen Tablet', 'category' => 'Elektronik & Komputer', 'room' => 'G2-02', 'qty' => 2, 'spec' => 'Area aktif 10 inci', 'year' => 2024, 'funding' => 'BOS', 'brand' => 'Wacom', 'model' => 'Intuos M'],
            ['name' => 'Gimbal Kamera', 'category' => 'Alat Peraga / Kamera', 'room' => 'H2-02', 'qty' => 1, 'spec' => '3-axis stabilizer', 'year' => 2024, 'funding' => 'BOS', 'brand' => 'DJI', 'model' => 'RS 3 Mini'],

            // ---- Laboratorium Kuliner ----
            ['name' => 'Kulkas Display', 'category' => 'Alat Laboratorium', 'room' => 'H2-01', 'qty' => 1, 'spec' => 'Kulkas kaca 2 pintu 300L', 'year' => 2023, 'funding' => 'BOS', 'brand' => 'RSA', 'model' => 'Showcase 300L'],
            ['name' => 'Oven Listrik', 'category' => 'Alat Laboratorium', 'room' => 'H2-01', 'qty' => 1, 'spec' => 'Oven deck 2 tingkat', 'year' => 2023, 'funding' => 'BOS', 'brand' => 'Getra', 'model' => 'Deck Oven 2T'],
            ['name' => 'Mixer Industri', 'category' => 'Alat Laboratorium', 'room' => 'H2-01', 'qty' => 2, 'spec' => 'Mixer planetary 5L', 'year' => 2023, 'funding' => 'BOS', 'brand' => 'KitchenAid', 'model' => 'Artisan 5KSM'],
            ['name' => 'Kompor Gas', 'category' => 'Alat Laboratorium', 'room' => 'H2-01', 'qty' => 2, 'spec' => 'Kompor dua tungku', 'year' => 2023, 'funding' => 'BOS', 'brand' => 'Rinnai', 'model' => 'RI-522E'],
            ['name' => 'Meja Kerja Kuliner', 'category' => 'Furnitur', 'room' => 'H2-01', 'qty' => 2, 'spec' => 'Meja stainless 150x60cm', 'year' => 2023, 'funding' => 'BOS', 'brand' => 'Lokal', 'model' => 'Meja Stainless 150'],
            ['name' => 'Rak Piring', 'category' => 'Furnitur', 'room' => 'H2-01', 'qty' => 2, 'spec' => 'Rak stainless tiga tingkat', 'year' => 2023, 'funding' => 'BOS', 'brand' => 'Lokal', 'model' => 'Rak Stainless 3T'],

            // ---- Laboratorium Teknik Pengelasan ----
            ['name' => 'Mesin Las Listrik', 'category' => 'Alat Laboratorium', 'room' => 'G1-01', 'qty' => 4, 'spec' => 'Inverter 120A', 'year' => 2023, 'funding' => 'BOS', 'brand' => 'Lakoni', 'model' => 'Inverter 120A'],
            ['name' => 'Helm Las', 'category' => 'Alat Laboratorium', 'room' => 'G1-01', 'qty' => 6, 'spec' => 'Helm las auto darkening', 'year' => 2023, 'funding' => 'BOS', 'brand' => 'Safetech', 'model' => 'Auto Darkening'],
            ['name' => 'Gerinda Tangan', 'category' => 'Alat Laboratorium', 'room' => 'G1-01', 'qty' => 2, 'spec' => 'Gerinda 4 inci 720W', 'year' => 2023, 'funding' => 'BOS', 'brand' => 'Bosch', 'model' => 'GWS 060'],
            ['name' => 'Oscilloscope', 'category' => 'Alat Laboratorium', 'room' => 'G1-01', 'qty' => 1, 'spec' => 'Digital 100MHz', 'year' => 2021, 'funding' => 'BOS', 'high' => true, 'brand' => 'Hantek', 'model' => 'DSO2D10'],
            ['name' => 'Mesin Las MIG', 'category' => 'Alat Laboratorium', 'room' => 'G1-05', 'qty' => 1, 'spec' => 'MIG/MAG 250A', 'year' => 2024, 'funding' => 'Dana DAU', 'high' => true, 'brand' => 'Lakoni', 'model' => 'MIG 250A'],
            ['name' => 'Meja Las', 'category' => 'Furnitur', 'room' => 'G1-05', 'qty' => 2, 'spec' => 'Meja las besi 120x80cm', 'year' => 2023, 'funding' => 'BOS', 'brand' => 'Lokal', 'model' => 'Meja Las 120'],

            // ---- Laboratorium TKP ----
            ['name' => 'Theodolite', 'category' => 'Alat Laboratorium', 'room' => 'H2-03', 'qty' => 1, 'spec' => 'Theodolite digital', 'year' => 2023, 'funding' => 'Dana DAU', 'high' => true, 'brand' => 'Topcon', 'model' => 'DT-100'],
            ['name' => 'Waterpass', 'category' => 'Alat Laboratorium', 'room' => 'H2-03', 'qty' => 2, 'spec' => 'Automatic level 32x', 'year' => 2023, 'funding' => 'BOS', 'brand' => 'Topcon', 'model' => 'AT-B2'],
            ['name' => 'Meteran Laser', 'category' => 'Alat Laboratorium', 'room' => 'H2-03', 'qty' => 3, 'spec' => 'Jarak ukur 50m', 'year' => 2024, 'funding' => 'BOS', 'brand' => 'Bosch', 'model' => 'GLM 50-27C'],
            ['name' => 'Cetok dan Sekop', 'category' => 'Alat Laboratorium', 'room' => 'H2-03', 'qty' => 5, 'spec' => 'Set alat pasangan bata', 'year' => 2023, 'funding' => 'BOS', 'brand' => '', 'model' => 'Set Alat Pasangan'],
            ['name' => 'Total Station', 'category' => 'Alat Laboratorium', 'room' => 'H2-04', 'qty' => 1, 'spec' => 'Total station 2 detik', 'year' => 2024, 'funding' => 'Dana DAU', 'high' => true, 'brand' => 'Sokkia', 'model' => 'CX-105'],
            ['name' => 'Mesin Bor Beton', 'category' => 'Alat Laboratorium', 'room' => 'H2-04', 'qty' => 2, 'spec' => 'Rotary hammer 800W', 'year' => 2023, 'funding' => 'BOS', 'brand' => 'Bosch', 'model' => 'GBH 2-26'],
        ];
    }
}
