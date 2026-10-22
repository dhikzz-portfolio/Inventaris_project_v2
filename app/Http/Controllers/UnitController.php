<?php

namespace App\Http\Controllers;

use App\Enums\Condition;
use App\Enums\UnitStatus;
use App\Models\AssetUnit;
use App\Models\Room;
use App\Services\InventoryCodeService;
use App\Services\LabelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function show(AssetUnit $unit): View
    {
        abort_unless(
            AssetUnit::query()->visibleTo(auth()->user())->whereKey($unit->id)->exists(),
            404
        );

        $unit->load([
            'catalog.category',
            'room.major',
            'codes' => fn ($query) => $query->orderByDesc('issued_at'),
            'movements.fromRoom',
            'movements.toRoom',
            'movements.mover',
            'maintenanceRecords' => fn ($query) => $query->orderByDesc('performed_at'),
        ]);

        $rooms = Room::query()
            ->when(! auth()->user()->isSuperAdmin(), fn ($query) => $query->where('is_lab', true))
            ->whereKeyNot($unit->room_id)
            ->orderBy('building_letter')->orderBy('floor')->orderBy('room_no')
            ->get();

        return view('units.show', [
            'unit' => $unit,
            'destinationRooms' => $rooms,
            'conditions' => Condition::cases(),
            'statuses' => [UnitStatus::AKTIF, UnitStatus::HILANG],
        ]);
    }

    /** Ubah kondisi/catatan unit (Laboran dalam cakupan) dan status (pengelola). */
    public function update(Request $request, AssetUnit $unit): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            AssetUnit::query()->visibleTo($user)->whereKey($unit->id)->exists(),
            404
        );

        abort_if($unit->status === UnitStatus::DIHAPUS, 422, 'Unit yang sudah dihapus tidak dapat diubah.');

        $data = $request->validate([
            'condition' => ['required', Rule::enum(Condition::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Status hanya boleh diubah pengelola, dan tidak lewat alur hapus/pinjam.
        if ($user->managesInventory()) {
            $status = $request->validate([
                'status' => ['required', Rule::in([UnitStatus::AKTIF->value, UnitStatus::HILANG->value])],
            ])['status'];
        } else {
            $status = $unit->status->value;
        }

        $before = [
            'condition' => $unit->condition->value,
            'status' => $unit->status->value,
            'notes' => $unit->notes,
        ];

        $unit->update([
            'condition' => $data['condition'],
            'status' => $status,
            'notes' => $data['notes'] ?? null,
        ]);

        activity('unit')
            ->performedOn($unit)
            ->causedBy($user)
            ->withProperties([
                'sebelum' => $before,
                'sesudah' => [
                    'condition' => $unit->condition->value,
                    'status' => $unit->status->value,
                    'notes' => $unit->notes,
                ],
            ])
            ->log("Memperbarui kondisi unit {$unit->inventory_code}");

        return back()->with('success', "Kondisi unit {$unit->inventory_code} diperbarui.");
    }

    public function label(AssetUnit $unit, LabelService $labels)
    {
        abort_unless(
            AssetUnit::query()->visibleTo(auth()->user())->whereKey($unit->id)->exists(),
            404
        );

        return $labels->printUnit($unit)->stream('label-'.$unit->inventory_code.'.pdf');
    }

    public function move(Request $request, AssetUnit $unit, InventoryCodeService $codes): RedirectResponse
    {
        abort_unless(
            AssetUnit::query()->visibleTo($request->user())->whereKey($unit->id)->exists(),
            404
        );

        $data = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $destination = Room::findOrFail($data['room_id']);

        if (! $request->user()->isSuperAdmin() && ! $destination->is_lab) {
            abort(404);
        }

        $moved = $codes->move($unit, $destination, $request->user(), $data['note'] ?? null);

        return redirect()->route('units.show', $moved)
            ->with('success', "Unit dipindahkan. Kode baru: {$moved->inventory_code}. Cetak ulang label unit ini.");
    }
}
