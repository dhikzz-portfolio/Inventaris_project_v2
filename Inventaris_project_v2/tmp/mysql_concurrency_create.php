<?php

use App\Models\Item;
use App\Models\Room;
use App\Services\AssetUnitService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$barrier = $argv[1] ?? null;

for ($i = 0; $i < 200; $i++) {
    if ($barrier !== null && file_exists($barrier)) {
        break;
    }

    usleep(25000);
}

try {
    $catalog = Item::query()
        ->where('acquisition_status', 'APPROVED')
        ->where('status', '!=', 'DIHAPUS')
        ->orderBy('id')
        ->firstOrFail();

    $room = Room::query()->where('is_lab', true)->orderBy('id')->firstOrFail();

    $unit = app(AssetUnitService::class)->create($catalog, $room);

    echo json_encode([
        'ok' => true,
        'unit_id' => $unit->id,
        'code' => $unit->inventory_code,
        'room_id' => $room->id,
        'unit_no' => $unit->unit_no,
    ]).PHP_EOL;
} catch (Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'error' => $exception::class,
        'message' => $exception->getMessage(),
    ]).PHP_EOL;
}
