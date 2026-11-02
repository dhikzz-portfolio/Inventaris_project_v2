<?php

use App\Models\AssetUnit;
use App\Models\Room;
use App\Models\User;
use App\Services\InventoryCodeService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$barrier = $argv[1] ?? null;
$roomIndex = (int) ($argv[2] ?? 0);

for ($i = 0; $i < 200; $i++) {
    if ($barrier !== null && file_exists($barrier)) {
        break;
    }

    usleep(25000);
}

try {
    $unit = AssetUnit::query()
        ->where('status', 'AKTIF')
        ->whereDoesntHave('openLoanItemUnit')
        ->orderBy('id')
        ->firstOrFail();

    $rooms = Room::query()->whereKeyNot($unit->room_id)->orderBy('id')->limit(4)->get();
    $destination = $rooms[$roomIndex % max(1, $rooms->count())] ?? null;

    if ($destination === null) {
        throw new RuntimeException('Tidak ada ruangan tujuan.');
    }

    $actor = User::query()->where('email', 'wakasek@gmail.com')->firstOrFail();
    $moved = app(InventoryCodeService::class)->move($unit, $destination, $actor, 'Uji konkuren');

    echo json_encode([
        'ok' => true,
        'unit_id' => $moved->id,
        'new_code' => $moved->inventory_code,
        'to_room_id' => $destination->id,
    ]).PHP_EOL;
} catch (Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'error' => $exception::class,
        'message' => $exception->getMessage(),
    ]).PHP_EOL;
}
