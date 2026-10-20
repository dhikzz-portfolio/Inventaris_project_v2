<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class PwaController extends Controller
{
    public function manifest(): Response
    {
        abort_unless(is_file(public_path('manifest.json')), 404);

        return response(file_get_contents(public_path('manifest.json')), 200, [
            'Content-Type' => 'application/manifest+json',
        ]);
    }

    public function serviceWorker(): Response
    {
        abort_unless(is_file(public_path('sw.js')), 404);

        return response(file_get_contents(public_path('sw.js')), 200, [
            'Content-Type' => 'application/javascript',
            'Service-Worker-Allowed' => '/',
        ]);
    }
}
