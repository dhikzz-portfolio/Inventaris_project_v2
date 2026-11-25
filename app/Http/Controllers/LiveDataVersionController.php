<?php

namespace App\Http\Controllers;

use App\Support\LiveDataVersion;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint ringan yang dipanggil halaman untuk mengetahui apakah data berubah.
 */
class LiveDataVersionController extends Controller
{
    public function __invoke(LiveDataVersion $version): JsonResponse
    {
        return response()
            ->json(['version' => $version->current()])
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
