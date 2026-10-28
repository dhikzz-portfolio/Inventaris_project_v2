<?php

namespace Database\Seeders;

use App\Enums\Condition;
use App\Enums\ItemStatus;
use App\Enums\UnitStatus;
use App\Models\Category;
use App\Models\InstitutionSetting;
use App\Models\Item;
use App\Models\Room;
use App\Services\AssetUnitService;
use App\Services\IdGenerator;
use Illuminate\Database\Seeder;

class DemoInventorySeeder extends Seeder
{
    /**
     * Data demo: katalog mengelompokkan jenis barang, sedangkan setiap unit
     * fisik mendapat kode inventaris sendiri. Jumlah katalog diturunkan dari
     * unit, bukan diisi manual.
     */
    public function run(): void
    {
        $setting = InstitutionSetting::first();
        $categories = Category::query()->pluck('id', 'name');
        $rooms = Room::query()->pluck('id', 'code');
        $units = app(AssetUnitService::class);

        foreach ($this->definitions() as $index => $def) {
            $category = $categories[$def['category']] ?? null;
            $roomId = $rooms[$def['room']] ?? null;

            if ($category === null || $roomId === null) {
                continue;
            }

            $categoryModel = Category::find($category);
            $room = Room::find($roomId);
            $register = IdGenerator::register(null);

            /** @var Item $item */
            $item = Item::firstOrCreate(
                ['name' => $def['name']],
                [
                    'internal_id' => IdGenerator::internalId($room),
                    'location_code' => $setting?->location_code ?? '12.24.13.08.01.07.01.01',
                    'register_no' => $register['register_no'],
                    'name' => $def['name'],
                    'brand' => $def['brand'] ?? '',
                    'model_type' => $def['model'] ?? '',
                    'category_id' => $categoryModel->id,
                    'room_id' => $room->id,
                    'specification' => $def['spec'],
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

            if ($item->units()->exists()) {
                continue;
            }

            for ($i = 0; $i < $def['qty']; $i++) {
                $damaged = $i < ($def['damaged'] ?? 0);

                $units->create($item, $room, [
                    'condition' => $damaged
                        ? (($i % 2 === 0) ? Condition::RUSAK_RINGAN : Condition::RUSAK_BERAT)
                        : Condition::BAIK,
                    'status' => UnitStatus::AKTIF,
                ]);
            }
        }

        // Lengkapi katalog lama yang belum memiliki unit tanpa membuat duplikat.
        $units->provisionAll();
    }

    /**
     * @return list<array{name: string, category: string, room: string, qty: int, spec: string, year: int, funding: string, high?: bool, damaged?: int, brand?: string, model?: string}>
     */
    private function definitions(): array
    {
        return [
            // ---- Inventaris umum nonlaboratorium (dikelola Wakasek) ----
            ['name' => 'Kursi Kelas', 'category' => 'Furnitur', 'room' => 'I1-01', 'qty' => 23, 'spec' => 'Kursi siswa rangka besi, tanpa sandaran lipat', 'year' => 2023, 'funding' => 'BOS'],
            ['name' => 'Meja Kelas', 'category' => 'Furnitur', 'room' => 'I1-01', 'qty' => 10, 'spec' => 'Meja siswa 120x50cm', 'year' => 2023, 'funding' => 'BOS'],
            ['name' => 'Papan Tulis', 'category' => 'Furnitur', 'room' => 'I1-02', 'qty' => 2, 'spec' => 'Whiteboard 240x120cm', 'year' => 2022, 'funding' => 'BOS'],
            ['name' => 'Proyektor Epson', 'category' => 'Elektronik & Komputer', 'room' => 'G1-03', 'qty' => 3, 'spec' => '3500 lumen XGA', 'year' => 2022, 'funding' => 'BOS', 'damaged' => 1],
            ['name' => 'Meja Kerja', 'category' => 'Furnitur', 'room' => 'G1-03', 'qty' => 1, 'spec' => 'Meja kayu 120x60cm', 'year' => 2022, 'funding' => 'BOS'],
            ['name' => 'Kursi Kantor', 'category' => 'Furnitur', 'room' => 'G1-03', 'qty' => 2, 'spec' => 'Kursi ergonomis hitam', 'year' => 2022, 'funding' => 'BOS'],
            ['name' => 'Meja Guru', 'category' => 'Furnitur', 'room' => 'G1-03', 'qty' => 2, 'spec' => 'Meja guru 150x70cm', 'year' => 2022, 'funding' => 'BOS'],
            ['name' => 'Lemari Arsip', 'category' => 'Furnitur', 'room' => 'I1-06', 'qty' => 3, 'spec' => 'Lemari besi dua pintu', 'year' => 2021, 'funding' => 'BOS'],
            ['name' => 'Printer Kantor', 'category' => 'Peralatan Kantor', 'room' => 'I1-06', 'qty' => 2, 'spec' => 'Printer laser monokrom', 'year' => 2024, 'funding' => 'BOS'],
            ['name' => 'Komputer Tata Usaha', 'category' => 'Elektronik & Komputer', 'room' => 'I1-06', 'qty' => 2, 'spec' => 'Core i3, RAM 8GB, SSD 256GB', 'year' => 2023, 'funding' => 'BOS'],

            // ---- Laboratorium RPL (H1-01, H1-02) ----
            ['name' => 'Komputer PC', 'category' => 'Elektronik & Komputer', 'room' => 'H1-01', 'qty' => 8, 'spec' => 'Core i5, RAM 8GB, SSD 256GB', 'year' => 2023, 'funding' => 'BOS'],
            ['name' => 'Laptop Acer Nitro', 'category' => 'Elektronik & Komputer', 'room' => 'H1-01', 'qty' => 2, 'spec' => 'Core i7, RAM 16GB, RTX 3050', 'year' => 2024, 'funding' => 'Komite Sekolah', 'high' => true],
            ['name' => 'PC Rakitan RPL', 'category' => 'Elektronik & Komputer', 'room' => 'H1-01', 'qty' => 6, 'spec' => 'Ryzen 5, RAM 16GB', 'year' => 2024, 'funding' => 'BOS'],
            ['name' => 'Switch Jaringan 24 Port', 'category' => 'Elektronik & Komputer', 'room' => 'H1-01', 'qty' => 2, 'spec' => 'Managed switch gigabit', 'year' => 2023, 'funding' => 'BOS'],
            ['name' => 'Router Mikrotik', 'category' => 'Elektronik & Komputer', 'room' => 'H1-01', 'qty' => 2, 'spec' => 'RB951G-2HnD', 'year' => 2023, 'funding' => 'Hibah', 'damaged' => 1],
            ['name' => 'Proyektor Interaktif', 'category' => 'Elektronik & Komputer', 'room' => 'H1-02', 'qty' => 1, 'spec' => 'Proyektor short throw 1080p', 'year' => 2025, 'funding' => 'Dana DAU'],

            // ---- Laboratorium DKV ----
            ['name' => 'Kamera DSLR Canon', 'category' => 'Alat Peraga / Kamera', 'room' => 'G2-01', 'qty' => 2, 'spec' => 'EOS 90D + lensa 18-135mm', 'year' => 2024, 'funding' => 'Dana DAU', 'high' => true],
            ['name' => 'Kamera Mirrorless Sony', 'category' => 'Alat Peraga / Kamera', 'room' => 'G2-01', 'qty' => 1, 'spec' => 'Alpha A6400 + lensa kit', 'year' => 2025, 'funding' => 'Komite Sekolah', 'high' => true],
            ['name' => 'Tripod Kamera', 'category' => 'Alat Peraga / Kamera', 'room' => 'G2-01', 'qty' => 2, 'spec' => 'Tripod aluminium 160cm', 'year' => 2024, 'funding' => 'BOS'],
            ['name' => 'Lampu Studio', 'category' => 'Alat Laboratorium', 'room' => 'G2-01', 'qty' => 2, 'spec' => 'Softbox LED 2x135W', 'year' => 2024, 'funding' => 'BOS'],
            ['name' => 'Komputer Editing DKV', 'category' => 'Elektronik & Komputer', 'room' => 'G2-02', 'qty' => 4, 'spec' => 'Core i7, RAM 32GB, GPU 8GB', 'year' => 2025, 'funding' => 'Dana DAU'],
            ['name' => 'Pen Tablet', 'category' => 'Elektronik & Komputer', 'room' => 'G2-02', 'qty' => 2, 'spec' => 'Area aktif 10 inci', 'year' => 2024, 'funding' => 'BOS'],
            ['name' => 'Gimbal Kamera', 'category' => 'Alat Peraga / Kamera', 'room' => 'H2-02', 'qty' => 1, 'spec' => '3-axis stabilizer', 'year' => 2024, 'funding' => 'BOS'],

            // ---- Laboratorium Kuliner ----
            ['name' => 'Kulkas Display', 'category' => 'Alat Laboratorium', 'room' => 'H2-01', 'qty' => 1, 'spec' => 'Kulkas kaca 2 pintu 300L', 'year' => 2023, 'funding' => 'BOS'],
            ['name' => 'Oven Listrik', 'category' => 'Alat Laboratorium', 'room' => 'H2-01', 'qty' => 1, 'spec' => 'Oven deck 2 tingkat', 'year' => 2023, 'funding' => 'BOS'],
            ['name' => 'Mixer Industri', 'category' => 'Alat Laboratorium', 'room' => 'H2-01', 'qty' => 2, 'spec' => 'Mixer planetary 5L', 'year' => 2023, 'funding' => 'BOS'],
            ['name' => 'Kompor Gas', 'category' => 'Alat Laboratorium', 'room' => 'H2-01', 'qty' => 2, 'spec' => 'Kompor dua tungku', 'year' => 2023, 'funding' => 'BOS'],
            ['name' => 'Meja Kerja Kuliner', 'category' => 'Furnitur', 'room' => 'H2-01', 'qty' => 2, 'spec' => 'Meja stainless 150x60cm', 'year' => 2023, 'funding' => 'BOS'],
            ['name' => 'Rak Piring', 'category' => 'Furnitur', 'room' => 'H2-01', 'qty' => 2, 'spec' => 'Rak stainless tiga tingkat', 'year' => 2023, 'funding' => 'BOS'],

            // ---- Laboratorium Teknik Pengelasan ----
            ['name' => 'Mesin Las Listrik', 'category' => 'Alat Laboratorium', 'room' => 'G1-01', 'qty' => 4, 'spec' => 'Inverter 120A', 'year' => 2023, 'funding' => 'BOS'],
            ['name' => 'Helm Las', 'category' => 'Alat Laboratorium', 'room' => 'G1-01', 'qty' => 6, 'spec' => 'Helm las auto darkening', 'year' => 2023, 'funding' => 'BOS'],
            ['name' => 'Gerinda Tangan', 'category' => 'Alat Laboratorium', 'room' => 'G1-01', 'qty' => 2, 'spec' => 'Gerinda 4 inci 720W', 'year' => 2023, 'funding' => 'BOS'],
            ['name' => 'Oscilloscope', 'category' => 'Alat Laboratorium', 'room' => 'G1-01', 'qty' => 1, 'spec' => 'Digital 100MHz', 'year' => 2021, 'funding' => 'BOS', 'high' => true],
            ['name' => 'Mesin Las MIG', 'category' => 'Alat Laboratorium', 'room' => 'G1-05', 'qty' => 1, 'spec' => 'MIG/MAG 250A', 'year' => 2024, 'funding' => 'Dana DAU', 'high' => true],
            ['name' => 'Meja Las', 'category' => 'Furnitur', 'room' => 'G1-05', 'qty' => 2, 'spec' => 'Meja las besi 120x80cm', 'year' => 2023, 'funding' => 'BOS'],

            // ---- Laboratorium TKP ----
            ['name' => 'Theodolite', 'category' => 'Alat Laboratorium', 'room' => 'H2-03', 'qty' => 1, 'spec' => 'Theodolite digital', 'year' => 2023, 'funding' => 'Dana DAU', 'high' => true],
            ['name' => 'Waterpass', 'category' => 'Alat Laboratorium', 'room' => 'H2-03', 'qty' => 2, 'spec' => 'Automatic level 32x', 'year' => 2023, 'funding' => 'BOS'],
            ['name' => 'Meteran Laser', 'category' => 'Alat Laboratorium', 'room' => 'H2-03', 'qty' => 3, 'spec' => 'Jarak ukur 50m', 'year' => 2024, 'funding' => 'BOS'],
            ['name' => 'Cetok dan Sekop', 'category' => 'Alat Laboratorium', 'room' => 'H2-03', 'qty' => 5, 'spec' => 'Set alat pasangan bata', 'year' => 2023, 'funding' => 'BOS'],
            ['name' => 'Total Station', 'category' => 'Alat Laboratorium', 'room' => 'H2-04', 'qty' => 1, 'spec' => 'Total station 2 detik', 'year' => 2024, 'funding' => 'Dana DAU', 'high' => true],
            ['name' => 'Mesin Bor Beton', 'category' => 'Alat Laboratorium', 'room' => 'H2-04', 'qty' => 2, 'spec' => 'Rotary hammer 800W', 'year' => 2023, 'funding' => 'BOS'],
        ];
    }
}
