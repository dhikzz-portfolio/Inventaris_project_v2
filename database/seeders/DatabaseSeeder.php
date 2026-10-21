<?php

namespace Database\Seeders;

use App\Enums\Condition;
use App\Enums\LoanStatus;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Event;
use App\Models\InstitutionSetting;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Major;
use App\Models\Room;
use App\Models\User;
use App\Services\IdGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as SpatieRole;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- Role (spatie) ----------
        foreach (Role::cases() as $role) {
            SpatieRole::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
        }

        // ---------- Pengaturan instansi ----------
        $setting = InstitutionSetting::updateOrCreate(['id' => 1], [
            'institution_name' => 'SMK BAGIMU NEGERIKU SEMARANG',
            'location_code' => '12.24.13.08.01.07.01.01',
            'address' => 'Jl. Palir Raya, Ngaliyan, Semarang, Jawa Tengah',
            'headmaster_name' => 'Daniel Setyo Wibowo',
        ]);

        // ---------- Jurusan ----------
        $tkj = Major::firstOrCreate(['name' => 'Teknik Komputer dan Jaringan'], ['code' => 'TKJ']);
        $rpl = Major::firstOrCreate(['name' => 'Rekayasa Perangkat Lunak'], ['code' => 'RPL']);
        $mm = Major::firstOrCreate(['name' => 'Multimedia'], ['code' => 'MM']);

        // ---------- Ruangan ----------
        $rooms = collect([
            ['major_id' => $tkj->id, 'building' => 'G1', 'floor' => 1, 'code' => 'G1-01', 'name' => 'Lab Komputer 1'],
            ['major_id' => $tkj->id, 'building' => 'G1', 'floor' => 2, 'code' => 'G1-02', 'name' => 'Lab Jaringan'],
            ['major_id' => $rpl->id, 'building' => 'G2', 'floor' => 1, 'code' => 'G2-01', 'name' => 'Lab RPL'],
            ['major_id' => $mm->id, 'building' => 'G2', 'floor' => 2, 'code' => 'G2-02', 'name' => 'Studio Multimedia'],
            ['major_id' => null, 'building' => 'G1', 'floor' => 1, 'code' => 'G1-03', 'name' => 'Ruang Guru'],
            ['major_id' => null, 'building' => 'G3', 'floor' => 1, 'code' => 'G3-01', 'name' => 'Gudang Sarana Prasarana'],
        ])->map(fn ($r) => Room::firstOrCreate(['code' => $r['code']], $r));

        // ---------- Kategori ----------
        $categories = collect([
            ['name' => 'Furnitur', 'code' => '01.01.09.01.01'],
            ['name' => 'Elektronik & Komputer', 'code' => '01.01.11.04.01'],
            ['name' => 'Alat Laboratorium', 'code' => '01.01.15.02.01'],
            ['name' => 'Alat Peraga / Kamera', 'code' => '01.01.16.03.01'],
        ])->map(fn ($c) => Category::firstOrCreate(['name' => $c['name']], $c));

        $furnitur = $categories[0];
        $elektronik = $categories[1];
        $lab = $categories[2];
        $peraga = $categories[3];

        // ---------- Pengguna (4 role) ----------
        $users = [
            ['username' => 'wakasek', 'name' => 'Drs. H. Wakasek Sarpras', 'role' => Role::SUPER_ADMIN->value, 'major_id' => null, 'password' => 'wakasek123'],
            ['username' => 'admin.sarpras', 'name' => 'Bagas Admin Sarpras', 'role' => Role::ADMIN_SARPRAS->value, 'major_id' => null, 'password' => 'admin123'],
            ['username' => 'laboran.tkj', 'name' => 'Rina Laboran TKJ', 'role' => Role::LABORAN->value, 'major_id' => $tkj->id, 'password' => 'laboran123'],
            ['username' => 'laboran.rpl', 'name' => 'Dedi Laboran RPL', 'role' => Role::LABORAN->value, 'major_id' => $rpl->id, 'password' => 'laboran123'],
            ['username' => 'guru.budi', 'name' => 'Budi Santoso, S.Kom', 'role' => Role::PEMINJAM->value, 'major_id' => $rpl->id, 'password' => 'guru123'],
            ['username' => 'guru.sari', 'name' => 'Sari Wijayanti, S.Pd', 'role' => Role::PEMINJAM->value, 'major_id' => $tkj->id, 'password' => 'guru123'],
        ];

        foreach ($users as $u) {
            $user = User::firstOrCreate(
                ['username' => $u['username']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make($u['password']),
                    'role' => $u['role'],
                    'major_id' => $u['major_id'],
                ]
            );
            $user->syncRoles([$u['role']]);
        }

        $wakasek = User::where('username', 'wakasek')->first();
        $admin = User::where('username', 'admin.sarpras')->first();
        $laboranTkj = User::where('username', 'laboran.tkj')->first();
        $laboranRpl = User::where('username', 'laboran.rpl')->first();
        $guruBudi = User::where('username', 'guru.budi')->first();
        $guruSari = User::where('username', 'guru.sari')->first();

        // ---------- Barang ----------
        if (Item::count() === 0) {
            $this->seedItems($setting, $rooms, $furnitur, $elektronik, $lab, $peraga);
        }

        // ---------- Kegiatan ----------
        $eventUjian = Event::firstOrCreate(
            ['name' => 'Ujian Akhir Semester Ganjil 2026/2027'],
            ['pic_name' => 'Drs. Ahmad Guru Teknologi', 'start_date' => now()->addDays(10), 'end_date' => now()->addDays(15), 'description' => 'Ujian praktik kejuruan seluruh jurusan.', 'created_by' => $admin->id]
        );
        Event::firstOrCreate(
            ['name' => 'Pelatihan Guru Coding Dasar'],
            ['pic_name' => 'Sari Wijayanti, S.Pd', 'start_date' => now()->addDays(3), 'end_date' => now()->addDays(4), 'description' => 'Pelatihan internal guru.', 'created_by' => $admin->id]
        );

        // ---------- Contoh peminjaman ----------
        if (Loan::count() === 0) {
            $this->seedLoans($guruBudi, $guruSari, $laboranTkj, $eventUjian);
        }
    }

    private function seedItems($setting, $rooms, $furnitur, $elektronik, $lab, $peraga): void
    {
        $defs = [
            // name, category, room idx, qty, unit, year, funding, high_value, spec, status
            ['Meja Kerja', $furnitur, 0, 1, 'unit', 2022, 'BOS', false, 'Meja kayu 120x60cm', 'BAIK'],
            ['Kursi Kantor', $furnitur, 0, 2, 'unit', 2022, 'BOS', false, 'Kursi ergonomis hitam', 'BAIK'],
            ['Komputer PC', $elektronik, 0, 12, 'unit', 2023, 'BOS', false, 'Core i5, RAM 8GB, SSD 256GB', 'BAIK'],
            ['Laptop Acer Nitro', $elektronik, 0, 3, 'unit', 2024, 'Komite Sekolah', true, 'Core i7, RAM 16GB, RTX 3050', 'BAIK'],
            ['Switch Jaringan 24 Port', $elektronik, 1, 2, 'unit', 2023, 'BOS', false, 'Managed switch gigabit', 'BAIK'],
            ['Router Mikrotik', $elektronik, 1, 4, 'unit', 2023, 'hibah', false, 'RB951G-2HnD', 'RUSAK_RINGAN'],
            ['PC Rakitan RPL', $elektronik, 2, 10, 'unit', 2024, 'BOS', false, 'Ryzen 5, RAM 16GB', 'BAIK'],
            ['Oscilloscope', $lab, 1, 1, 'unit', 2021, 'BOS', true, 'Digital 100MHz', 'BAIK'],
            ['Kamera DSLR Canon', $peraga, 3, 2, 'unit', 2024, 'Dana DAU', true, 'EOS 90D + lensa 18-135mm', 'BAIK'],
            ['Proyektor Epson', $elektronik, 4, 3, 'unit', 2022, 'BOS', false, '3500 lumen XGA', 'RUSAK_BERAT'],
        ];

        foreach ($defs as [$name, $category, $roomIdx, $qty, $unit, $year, $funding, $high, $spec, $status]) {
            $room = $rooms[$roomIdx];
            $register = IdGenerator::register($category->code);

            Item::create([
                'internal_id' => IdGenerator::internalId($room),
                'location_code' => $setting->location_code,
                'asset_code' => $register['asset_code'],
                'register_no' => $register['register_no'],
                'name' => $name,
                'category_id' => $category->id,
                'room_id' => $room->id,
                'specification' => $spec,
                'acquisition_year' => $year,
                'funding_source' => $funding,
                'quantity' => $qty,
                'qty_damaged' => $status === 'RUSAK_RINGAN' ? min(1, $qty) : ($status === 'RUSAK_BERAT' ? $qty : 0),
                'qty_lost' => $status === 'HILANG' ? $qty : 0,
                'unit' => $unit,
                'is_high_value' => $high,
                'status' => $status,
                'acquisition_status' => 'APPROVED',
            ]);
        }
    }

    private function seedLoans($guruBudi, $guruSari, $laboran, $event): void
    {
        $laptop = Item::where('name', 'Laptop Acer Nitro')->first();
        $pcs = Item::where('name', 'Komputer PC')->first();
        $kamera = Item::where('name', 'Kamera DSLR Canon')->first();

        if (! $laptop || ! $pcs) {
            return;
        }

        // 1) Peminjaman SELESAI (sudah dikembalikan, satu rusak).
        $done = Loan::create([
            'borrower_id' => $guruBudi->id,
            'purpose' => 'Praktikum jaringan kelas XI',
            'start_date' => now()->subDays(20),
            'due_date' => now()->subDays(17),
            'returned_at' => now()->subDays(17),
            'returned_by' => $laboran->id,
            'status' => LoanStatus::SELESAI,
        ]);
        LoanItem::create([
            'loan_id' => $done->id, 'item_id' => $pcs->id, 'qty_borrowed' => 3, 'qty_returned' => 3,
            'condition_out' => Condition::BAIK->value, 'condition_in' => Condition::RUSAK_RINGAN->value,
            'returned_room_id' => $pcs->room_id,
        ]);
        $done->approvals()->create([
            'approver_id' => $laboran->id, 'level' => 1, 'decision' => 'APPROVED', 'decided_at' => now()->subDays(21),
        ]);

        // 2) Peminjaman DIPINJAM (berjalan, aktif).
        $active = Loan::create([
            'borrower_id' => $guruSari->id,
            'event_id' => $event->id,
            'purpose' => 'Persiapan Ujian Praktik',
            'start_date' => now()->subDay(),
            'due_date' => now()->addDays(7),
            'status' => LoanStatus::DIPINJAM,
            'handed_over_at' => now()->subDay(),
            'handed_over_by' => $laboran->id,
            'needs_high_value_approval' => true,
        ]);
        LoanItem::create([
            'loan_id' => $active->id, 'item_id' => $laptop->id, 'qty_borrowed' => 2, 'qty_returned' => null,
            'condition_out' => Condition::BAIK->value,
        ]);
        $active->approvals()->create(['approver_id' => $laboran->id, 'level' => 1, 'decision' => 'APPROVED', 'decided_at' => now()->subDays(2)]);
        $active->approvals()->create([
            'approver_id' => User::where('username', 'wakasek')->first()->id, 'level' => 2, 'decision' => 'APPROVED',
            'decided_at' => now()->subDay(),
        ]);

        // 3) Peminjaman PENDING (menunggu persetujuan laboran).
        $pending = Loan::create([
            'borrower_id' => $guruBudi->id,
            'purpose' => 'Demo project kelas RPL',
            'start_date' => now()->addDays(2),
            'due_date' => now()->addDays(4),
            'status' => LoanStatus::PENDING,
            'needs_high_value_approval' => true,
        ]);
        LoanItem::create([
            'loan_id' => $pending->id, 'item_id' => $kamera->id, 'qty_borrowed' => 1, 'qty_returned' => null,
            'condition_out' => Condition::BAIK->value,
        ]);

        // 4) Peminjaman TERLAMBAT (untuk alert scheduler).
        $overdue = Loan::create([
            'borrower_id' => $guruSari->id,
            'purpose' => 'Pinjaman proyektor luar sekolah (belum kembali)',
            'start_date' => now()->subDays(10),
            'due_date' => now()->subDays(3),
            'status' => LoanStatus::DIPINJAM,
            'handed_over_at' => now()->subDays(10),
            'handed_over_by' => $laboran->id,
        ]);
        LoanItem::create([
            'loan_id' => $overdue->id, 'item_id' => Item::where('name', 'Proyektor Epson')->first()->id,
            'qty_borrowed' => 1, 'qty_returned' => null, 'condition_out' => Condition::BAIK->value,
        ]);
        $overdue->approvals()->create(['approver_id' => $laboran->id, 'level' => 1, 'decision' => 'APPROVED', 'decided_at' => now()->subDays(11)]);
    }
}
