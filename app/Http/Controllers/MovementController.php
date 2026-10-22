<?php

namespace App\Http\Controllers;

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\ItemMovement;
use App\Models\Room;
use App\Services\LoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MovementController extends Controller
{
    public function index(Request $request): View
    {
        return view('movements.index', [
            'movements' => ItemMovement::with(['item', 'fromRoom', 'toRoom', 'mover'])
                ->latest('moved_at')
                ->paginate(15),
            'items' => Item::approved()
                ->visibleTo($request->user())
                ->where('status', '!=', 'DIHAPUS')
                ->with('room')
                ->orderBy('name')
                ->get(),
            'rooms' => Room::query()
                ->when(! $request->user()->isSuperAdmin(), fn ($query) => $query->where('is_lab', true))
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function store(Request $request, LoanService $loanService): RedirectResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'exists:item_catalogs,id'],
            'to_room_id' => ['required', 'exists:rooms,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $item = Item::with('room')->findOrFail($data['item_id']);
        $toRoom = Room::findOrFail($data['to_room_id']);

        // Mutasi terbatas pada katalog dalam cakupan; non-Super Admin hanya
        // boleh memindahkan ke ruangan laboratorium.
        abort_unless(
            Item::query()->visibleTo($request->user())->whereKey($item->id)->exists(),
            404
        );
        abort_if(! $request->user()->isSuperAdmin() && ! $toRoom->is_lab, 404);

        abort_if($item->room_id === $toRoom->id, 422, 'Ruangan tujuan sama dengan ruangan asal.');
        abort_if($item->status === ItemStatus::DIHAPUS, 422, 'Barang terhapus tidak dapat dimutasi.');

        DB::transaction(function () use ($item, $toRoom, $data, $request, $loanService) {
            $loanService->recordMovement($item, $toRoom, $request->user(), $data['note'] ?? null);
        });

        return redirect()->route('movements.index')
            ->with('success', "Mutasi tercatat: {$item->name} → {$toRoom->code} (ID Internal diperbarui: {$item->fresh()->internal_id}).");
    }
}
