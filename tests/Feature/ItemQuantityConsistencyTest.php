<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\Decision;
use App\Enums\ItemStatus;
use App\Models\Category;
use App\Models\Item;
use App\Models\Major;
use App\Models\Room;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Konsistensi kuantitas katalog: query yang dirujuk harus memakai kolom yang
 * benar-benar ada, dan selisih sementara tidak boleh dihitung dua kali.
 */
class ItemQuantityConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $laboranTkj;

    private User $guruBudi;

    private Room $roomTkj;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->laboranTkj = User::where('email', 'laboran.tp@gmail.com')->firstOrFail();
        $this->guruBudi = User::where('email', 'guru.budi@gmail.com')->firstOrFail();
        $this->roomTkj = Room::where('major_id', Major::where('code', 'TP')->value('id'))->firstOrFail();
        $this->category = Category::firstOrFail();
    }

    private function makeItem(string $name, int $qty): Item
    {
        static $seq = 0;
        $seq++;

        return Item::create([
            'internal_id' => 'KUAN-'.strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 5)).'-'.$seq,
            'location_code' => '12.24.13.08.01.07.01.01',
            'register_no' => (string) (7000 + $seq),
            'name' => $name,
            'category_id' => $this->category->id,
            'room_id' => $this->roomTkj->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => $qty,
            'unit' => 'unit',
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);
    }

    /**
     * SQLite menerima kolom yang tidak dikenal sebagai string literal, sehingga
     * salah ketik nama kolom lolos dari test dan baru meledak di MySQL.
     * Test ini memaksa setiap kolom yang dirujuk query outer benar-benar ada.
     */
    public function test_query_selisih_hanya_memakai_kolom_yang_ada_di_loan_items(): void
    {
        $item = $this->makeItem('Kuantitas Uji', 2);

        $sql = $item->pendingLostQuery()->toSql();
        $outer = Str::before($sql, 'in (select');

        preg_match_all('/[`"](\w+)[`"]/', $outer, $matches);

        $columns = Schema::getColumnListing('loan_items');

        foreach (array_unique($matches[1]) as $referenced) {
            if ($referenced === 'loan_items') {
                continue; // nama tabel, bukan kolom
            }

            $this->assertContains(
                $referenced,
                $columns,
                "Query kuantitas memakai kolom `{$referenced}` yang tidak ada di tabel loan_items."
            );
        }
    }

    public function test_selisih_sementara_tidak_menghitung_stok_tersedia_ganda(): void
    {
        $item = $this->makeItem('Radio Selisih', 2);
        $service = app(LoanService::class);

        $loan = $service->requestLoan($this->guruBudi, [['item_id' => $item->id, 'qty' => 2]], 'Uji selisih', '2027-09-01 08:00', '2027-09-03 16:00');
        $service->decide($loan, $this->laboranTkj, Decision::APPROVED);
        $service->handover($loan->refresh(), $this->laboranTkj);

        $loanItem = $loan->loanItems()->firstOrFail();

        // Satu unit kembali, satu unit hilang (selisih) pada pinjaman aktif.
        $service->processReturn($loan->refresh(), $this->laboranTkj, [[
            'loan_item_id' => $loanItem->id,
            'qty_returned' => 1,
            'condition_in' => Condition::BAIK->value,
            'returned_room_id' => $item->room_id,
        ]]);

        $item->refresh();

        $this->assertSame(1, $loanItem->refresh()->qty_lost);
        $this->assertSame(1, $item->pendingLostQuantity(), 'Selisih yang belum ditutup harus tercatat satu unit.');

        // 2 unit dipinjam, 1 kembali → 1 unit masih di luar (termasuk yang hilang).
        // Stok tersedia = 2 - 1 unit di luar = 1, bukan 0 (selisih tidak dihitung dua kali).
        $this->assertSame(1, $item->activeLoanRemainingQuantity());
        $this->assertSame(1, $item->availableQuantity());

        // Setelah selisih ditutup Wakasek, kehilangan menjadi permanen.
        $wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();
        $this->actingAs($wakasek)->post(route('loans.close-shortage', $loan))->assertRedirect();

        $item->refresh();
        $this->assertSame(1, $item->qty_lost);
        $this->assertSame(1, $item->availableQuantity());
        $this->assertSame(0, $item->pendingLostQuantity());
    }
}
