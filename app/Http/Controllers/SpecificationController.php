<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Specification;
use Illuminate\View\View;

class SpecificationController extends Controller
{
    /** Tingkat 3 hierarki katalog: daftar unit fisik satu spesifikasi. */
    public function show(Specification $specification): View
    {
        $user = auth()->user();

        $catalog = Item::query()
            ->approved()
            ->visibleTo($user)
            ->with('category')
            ->find($specification->item_catalog_id);

        abort_if($catalog === null, 404);

        if ($catalog->isArchived() && ! $user->managesInventory()) {
            abort(404);
        }

        $hasVisibleUnits = Specification::query()
            ->visibleTo($user)
            ->whereKey($specification->id)
            ->whereHas('units', fn ($unit) => $unit->visibleTo($user)->where('status', '!=', 'DIHAPUS'))
            ->exists();

        abort_unless($hasVisibleUnits || $user->managesInventory(), 404);

        return view('specifications.show', [
            'catalog' => $catalog,
            'specification' => $specification,
        ]);
    }
}
