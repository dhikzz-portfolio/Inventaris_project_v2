<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use App\Services\LabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelAndQrTest extends TestCase
{
    use RefreshDatabase;

    private User $laboran;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->laboran = User::where('username', 'laboran.tkj')->firstOrFail();
        $this->item = Item::firstOrFail();
    }

    public function test_qr_data_url_tergenerate_sebagai_base64_png(): void
    {
        $service = app(LabelService::class);
        $dataUrl = $service->qrDataUrl($this->item);

        $this->assertStringStartsWith('data:image/png;base64,', $dataUrl);
        $base64 = substr($dataUrl, strlen('data:image/png;base64,'));
        $binary = base64_decode($base64);

        $this->assertNotEmpty($binary);
        // Signature PNG: 89 50 4E 47 0D 0A 1A 0A
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($binary, 0, 8));
    }

    public function test_split_location_code_memecah_kode_panjang_menjadi_dua_baris(): void
    {
        $service = app(LabelService::class);

        $this->assertSame(
            ['12.24.13.08', '01.07.01.01'],
            $service->splitLocationCode('12.24.13.08.01.07.01.01')
        );
        $this->assertSame(['12.24', '13.08'], $service->splitLocationCode('12.24.13.08'));
        $this->assertSame(['LOKASI-A'], $service->splitLocationCode('LOKASI-A'));
    }

    public function test_cetak_label_satuan_menghasilkan_pdf(): void
    {
        $response = $this->actingAs($this->laboran)
            ->get(route('items.label', $this->item));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        // Pastikan last_label_printed_at tercatat
        $this->assertNotNull($this->item->refresh()->last_label_printed_at);
    }

    public function test_cetak_label_massal_menghasilkan_pdf(): void
    {
        $items = Item::approved()->limit(3)->pluck('id')->all();

        $response = $this->actingAs($this->laboran)
            ->post(route('labels.print'), [
                'item_ids' => $items,
            ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_scan_resolve_mengarahkan_ke_halaman_detail_barang(): void
    {
        $response = $this->actingAs($this->laboran)
            ->get(route('scan.resolve', ['internalId' => $this->item->internal_id]));

        $response->assertRedirect(route('items.show', $this->item));
    }

    public function test_scan_resolve_qr_tidak_dikenal_memberikan_pesan_error(): void
    {
        $response = $this->actingAs($this->laboran)
            ->get(route('scan.resolve', ['internalId' => 'BARANG-TIDAK-ADA-999']));

        $response->assertRedirect(route('labels.index'));
        $response->assertSessionHas('error');
    }
}
