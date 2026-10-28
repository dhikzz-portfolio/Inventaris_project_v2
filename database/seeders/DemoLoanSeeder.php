<?php

namespace Database\Seeders;

use App\Enums\Condition;
use App\Enums\Decision;
use App\Enums\LoanStatus;
use App\Models\Event;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoLoanSeeder extends Seeder
{
    public function run(): void
    {
        $wakasek = User::where('email', 'wakasek@gmail.com')->first();
        $ketua = User::where('email', 'ketualaboran@gmail.com')->first();
        $laboran = User::where('email', 'laboran.tp@gmail.com')->first();
        $guruBudi = User::where('email', 'guru.budi@gmail.com')->first();
        $guruSari = User::where('email', 'guru.sari@gmail.com')->first();
        $siswa = User::where('email', 'siswa.raka@gmail.com')->first();

        if ($wakasek === null || $guruBudi === null || $guruSari === null) {
            return;
        }

        $eventUjian = Event::firstOrCreate(
            ['name' => 'Ujian Akhir Semester Ganjil 2026/2027'],
            [
                'pic_name' => 'Panitia Ujian Sekolah (Demo)',
                'start_date' => now()->addDays(10),
                'end_date' => now()->addDays(15),
                'description' => 'Ujian praktik kejuruan seluruh jurusan.',
                'created_by' => $wakasek->id,
            ]
        );

        Event::firstOrCreate(
            ['name' => 'Pelatihan Guru Coding Dasar'],
            [
                'pic_name' => 'Sari Wijayanti, S.Pd (Demo)',
                'start_date' => now()->addDays(3),
                'end_date' => now()->addDays(4),
                'description' => 'Pelatihan internal guru.',
                'created_by' => $wakasek->id,
            ]
        );

        if (Loan::count() > 0) {
            return;
        }

        $laptop = Item::where('name', 'Laptop Acer Nitro')->first();
        $pcs = Item::where('name', 'Komputer PC')->first();
        $kamera = Item::where('name', 'Kamera DSLR Canon')->first();
        $proyektor = Item::where('name', 'Proyektor Epson')->first();

        if ($laptop === null || $pcs === null || $kamera === null || $proyektor === null) {
            return;
        }

        // 1) Peminjaman SELESAI (sudah dikembalikan, satu rusak).
        $done = Loan::create([
            'borrower_id' => $guruBudi->id,
            'purpose' => 'Praktikum jaringan kelas XI',
            'start_date' => now()->subDays(20),
            'due_date' => now()->subDays(17),
            'returned_at' => now()->subDays(17),
            'returned_by' => $laboran?->id ?? $wakasek->id,
            'status' => LoanStatus::SELESAI,
        ]);
        LoanItem::create([
            'loan_id' => $done->id,
            'item_id' => $pcs->id,
            'qty_borrowed' => 3,
            'qty_returned' => 3,
            'condition_out' => Condition::BAIK->value,
            'condition_in' => Condition::RUSAK_RINGAN->value,
            'returned_room_id' => $pcs->room_id,
        ]);
        $done->approvals()->create([
            'approver_id' => $laboran?->id ?? $wakasek->id,
            'level' => 1,
            'decision' => Decision::APPROVED->value,
            'decided_at' => now()->subDays(21),
        ]);

        // 2) Peminjaman DIPINJAM (berjalan, aktif).
        $active = Loan::create([
            'borrower_id' => $guruSari->id,
            'event_id' => $eventUjian->id,
            'purpose' => 'Persiapan Ujian Praktik',
            'start_date' => now()->subDay(),
            'due_date' => now()->addDays(7),
            'status' => LoanStatus::DIPINJAM,
            'handed_over_at' => now()->subDay(),
            'handed_over_by' => $laboran?->id ?? $wakasek->id,
            'needs_high_value_approval' => true,
        ]);
        LoanItem::create([
            'loan_id' => $active->id,
            'item_id' => $laptop->id,
            'qty_borrowed' => 2,
            'qty_returned' => null,
            'condition_out' => Condition::BAIK->value,
        ]);
        $active->approvals()->create([
            'approver_id' => $laboran?->id ?? $wakasek->id,
            'level' => 1,
            'decision' => Decision::APPROVED->value,
            'decided_at' => now()->subDays(2),
        ]);
        $active->approvals()->create([
            'approver_id' => $wakasek->id,
            'level' => 2,
            'decision' => Decision::APPROVED->value,
            'decided_at' => now()->subDay(),
        ]);

        // 3) Peminjaman PENDING (barang bernilai tinggi menunggu Wakasek).
        $pending = Loan::create([
            'borrower_id' => $siswa?->id ?? $guruBudi->id,
            'purpose' => 'Demo project kelas RPL',
            'start_date' => now()->addDays(2),
            'due_date' => now()->addDays(4),
            'status' => LoanStatus::PENDING,
            'needs_high_value_approval' => true,
        ]);
        LoanItem::create([
            'loan_id' => $pending->id,
            'item_id' => $kamera->id,
            'qty_borrowed' => 1,
            'qty_returned' => null,
            'condition_out' => Condition::BAIK->value,
        ]);
        $pending->approvals()->create([
            'approver_id' => $ketua?->id ?? $wakasek->id,
            'level' => 1,
            'decision' => Decision::APPROVED->value,
            'decided_at' => now()->subHour(),
        ]);

        // 4) Peminjaman TERLAMBAT (untuk alert scheduler).
        $overdue = Loan::create([
            'borrower_id' => $guruSari->id,
            'purpose' => 'Pinjaman proyektor kegiatan sekolah (belum kembali)',
            'start_date' => now()->subDays(10),
            'due_date' => now()->subDays(3),
            'status' => LoanStatus::DIPINJAM,
            'handed_over_at' => now()->subDays(10),
            'handed_over_by' => $laboran?->id ?? $wakasek->id,
        ]);
        LoanItem::create([
            'loan_id' => $overdue->id,
            'item_id' => $proyektor->id,
            'qty_borrowed' => 1,
            'qty_returned' => null,
            'condition_out' => Condition::BAIK->value,
        ]);
        $overdue->approvals()->create([
            'approver_id' => $laboran?->id ?? $wakasek->id,
            'level' => 1,
            'decision' => Decision::APPROVED->value,
            'decided_at' => now()->subDays(11),
        ]);
    }
}
