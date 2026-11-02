<?php

use App\Models\AssetUnit;
use App\Models\User;
use App\Models\WriteOff;
use App\Models\WriteOffUnit;
use App\Services\AssetUnitService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$barrier = $argv[1] ?? null;
$unitId = (int) ($argv[2] ?? 0);

for ($i = 0; $i < 400; $i++) {
    if ($barrier !== null && file_exists($barrier)) {
        break;
    }

    usleep(25000);
}

try {
    $actor = User::query()->where('email', 'wakasek@gmail.com')->firstOrFail();

    $result = DB::transaction(function () use ($unitId, $actor) {
        $unit = AssetUnit::query()->whereKey($unitId)->lockForUpdate()->firstOrFail();

        if ($unit->status->value === 'DIHAPUS' || $unit->archived_at !== null) {
            throw new RuntimeException('Unit sudah dihapus.');
        }

        $writeOff = WriteOff::create([
            'item_id' => $unit->item_catalog_id,
            'quantity' => 1,
            'mode' => 'UNIT',
            'reason' => 'Uji konkurensi penghapusan',
            'berita_acara_path' => 'write-offs/concurrency.pdf',
            'status' => 'APPROVED',
            'requested_by' => $actor->id,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);

        WriteOffUnit::create([
            'write_off_id' => $writeOff->id,
            'asset_unit_id' => $unit->id,
            'is_active' => 1,
            'item_catalog_id' => $unit->item_catalog_id,
            'specification_id' => $unit->specification_id,
            'inventory_code' => $unit->inventory_code,
            'room_id' => $unit->room_id,
            'condition' => $unit->condition?->value,
            'status_before' => $unit->status?->value,
        ]);

        app(AssetUnitService::class)->archive($unit, $actor);

        return [
            'write_off_id' => $writeOff->id,
            'catalog_id' => $unit->item_catalog_id,
            'code' => $unit->inventory_code,
        ];
    }, 3);

    echo json_encode(['ok' => true] + $result).PHP_EOL;
} catch (Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'error' => $exception::class,
        'code' => (string) $exception->getCode(),
        'message' => $exception->getMessage(),
    ]).PHP_EOL;
}
