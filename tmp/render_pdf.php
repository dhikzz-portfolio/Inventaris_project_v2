<?php

use App\Models\AssetUnit;
use App\Models\InstitutionSetting;
use App\Services\LabelService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$units = AssetUnit::with(['catalog', 'room'])
    ->whereHas('catalog', fn ($q) => $q->where('name', 'like', '%helm%'))
    ->orderBy('inventory_code')->take(4)->get();
$setting = InstitutionSetting::current();
$labels = app(LabelService::class);

$qrMap = [];
foreach ($units as $u) {
    $qrMap[$u->id] = $labels->unitQrDataUrl($u);
}

$single = Pdf::loadView('pdf.unit_label', [
    'unit' => $units->first(), 'setting' => $setting, 'qr' => $qrMap[$units->first()->id],
])->setPaper([0, 0, 260, 120], 'pt');
file_put_contents('tmp/single.pdf', $single->output());

$massal = Pdf::loadView('pdf.unit_massal', [
    'units' => $units, 'setting' => $setting, 'qrMap' => $qrMap,
])->setPaper('a4');
file_put_contents('tmp/massal.pdf', $massal->output());

echo 'ok '.filesize('tmp/single.pdf').' / '.filesize('tmp/massal.pdf');
