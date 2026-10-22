<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(): View
    {
        return view('catalogs.index');
    }

    public function show(Item $catalog): View
    {
        abort_unless(
            Item::query()->approved()->visibleTo(auth()->user())->whereKey($catalog->id)->exists(),
            404
        );

        $catalog->load('category');

        return view('catalogs.show', ['catalog' => $catalog]);
    }
}
