<?php

use App\Models\AssetUnit;
use App\Models\InstitutionSetting;
use App\Services\LabelService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$labels = app(LabelService::class);
$setting = InstitutionSetting::current();

foreach (AssetUnit::query()->orderBy('id')->take(1)->get() as $unit) {
    $unit->loadMissing(['catalog', 'specification', 'room.building']);
    $data = ['unit' => $unit, 'setting' => $setting, 'qr' => $labels->unitQrDataUrl($unit)];

    $ref = new ReflectionClass($labels);
    $method = $ref->getMethod('unitLabelFontSizes');
    $method->setAccessible(true);
    [$nameSize, $identitySize] = $method->invoke($labels, $data);

    $pdf = Pdf::loadView('pdf.unit_label', $data + ['nameSize' => $nameSize, 'identitySize' => $identitySize])
        ->setPaper([0, 0, ...LabelService::UNIT_LABEL_SIZE], 'pt');
    file_put_contents('tmp/unit1.pdf', $pdf->output());
    echo "unit {$unit->id} {$unit->inventory_code} name={$nameSize} identity={$identitySize} size=".implode('x', LabelService::UNIT_LABEL_SIZE)." bytes=".filesize('tmp/unit1.pdf')."\n";
}
