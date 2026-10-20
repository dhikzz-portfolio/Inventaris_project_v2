<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use App\Models\Room;
use App\Services\LabelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LabelController extends Controller
{
    public function index(Request $request): View
    {
        $items = Item::approved()
            ->with(['room', 'category'])
            ->where('status', '!=', 'DIHAPUS')
            ->when($request->query('room'), fn ($q, $v) => $q->where('room_id', $v))
            ->when($request->query('category'), fn ($q, $v) => $q->where('category_id', $v))
            ->orderBy('name');

        return view('labels.index', [
            'items' => $items->get(),
            'rooms' => Room::orderBy('code')->get(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function print(Request $request, LabelService $labels)
    {
        $data = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'exists:items,id'],
        ]);

        return $labels->printMassal($data['item_ids'])
            ->stream('label-massal-'.now()->format('Ymd-His').'.pdf');
    }

    public function scan(): View
    {
        return view('labels.scan');
    }

    /** Hasil scan QR: arahkan ke detail barang berdasarkan ID Internal. */
    public function scanResolve(string $internalId): RedirectResponse
    {
        $item = Item::where('internal_id', $internalId)->first();

        if (! $item) {
            return redirect()->route('labels.index')
                ->with('error', "QR/ID \"{$internalId}\" tidak ditemukan di sistem.");
        }

        return redirect()->route('items.show', $item);
    }
}
