<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Major;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MasterController extends Controller
{
    public function index(): View
    {
        return view('masters.index', [
            'majors' => Major::withCount('rooms')->orderBy('name')->get(),
            'rooms' => Room::with('major')->orderBy('building_letter')->orderBy('floor')->orderBy('room_no')->get(),
            'categories' => Category::withCount('items')->orderBy('name')->get(),
        ]);
    }

    public function storeMajor(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:majors,name'],
            'code' => ['nullable', 'string', 'max:20', 'unique:majors,code'],
        ]);

        Major::create($data + ['code' => $data['code'] ?: null]);

        return back()->with('success', 'Jurusan ditambahkan.');
    }

    public function storeRoom(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'major_id' => ['nullable', 'exists:majors,id'],
            'building' => ['required', 'string', 'max:20'],
            'building_letter' => ['nullable', 'string', 'size:1', 'regex:/^[A-Za-z]$/'],
            'floor' => ['required', 'integer', 'min:1', 'max:9'],
            'room_no' => ['nullable', 'integer', 'min:1', 'max:99'],
            'is_lab' => ['nullable', 'boolean'],
            'code' => ['required', 'string', 'max:30', 'unique:rooms,code'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $data = $this->normalizeRoomIdentity($data);
        $this->assertRoomIdentityUnique($data);
        $data['is_lab'] = $request->boolean('is_lab');

        Room::create($data);

        return back()->with('success', "Ruangan {$data['code']} ditambahkan.");
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
            'code' => ['nullable', 'string', 'max:30', 'unique:categories,code'],
        ]);

        Category::create([
            'name' => $data['name'],
            'code' => $data['code'] ?: null,
        ]);

        return back()->with('success', 'Kategori ditambahkan.');
    }

    public function updateMajor(Request $request, Major $major): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('majors', 'name')->ignore($major->id)],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('majors', 'code')->ignore($major->id)],
        ]);

        $major->update(['name' => $data['name'], 'code' => $data['code'] ?: null]);
        activity('master')->performedOn($major)->causedBy($request->user())->log('Mengubah jurusan');

        return back()->with('success', 'Jurusan diperbarui.');
    }

    public function destroyMajor(Request $request, Major $major): RedirectResponse
    {
        abort_if($major->rooms()->exists(), 422, 'Jurusan masih memiliki ruangan — pindahkan/hapus ruangan terlebih dahulu.');
        abort_if($major->users()->exists(), 422, 'Jurusan masih digunakan oleh akun pengguna.');

        activity('master')->performedOn($major)->causedBy($request->user())->log('Menghapus jurusan');
        $major->delete();

        return back()->with('success', 'Jurusan dihapus.');
    }

    public function updateRoom(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'major_id' => ['nullable', 'exists:majors,id'],
            'building' => ['required', 'string', 'max:20'],
            'building_letter' => ['nullable', 'string', 'size:1', 'regex:/^[A-Za-z]$/'],
            'floor' => ['required', 'integer', 'min:1', 'max:9'],
            'room_no' => ['nullable', 'integer', 'min:1', 'max:99'],
            'is_lab' => ['nullable', 'boolean'],
            'code' => ['required', 'string', 'max:30', Rule::unique('rooms', 'code')->ignore($room->id)],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $data = $this->normalizeRoomIdentity($data, $room);
        $this->assertRoomIdentityUnique($data, $room);
        $data['is_lab'] = $request->boolean('is_lab');

        $room->update($data);
        activity('master')->performedOn($room)->causedBy($request->user())->log('Mengubah data ruangan');

        return back()->with('success', 'Ruangan diperbarui.');
    }

    public function destroyRoom(Request $request, Room $room): RedirectResponse
    {
        abort_if($room->items()->exists() || $room->units()->exists(), 422, 'Ruangan masih menyimpan barang — mutasi barang terlebih dahulu.');
        abort_if($room->movementsFrom()->exists() || $room->movementsTo()->exists(), 422, 'Ruangan memiliki riwayat mutasi dan tidak dapat dihapus.');

        activity('master')->performedOn($room)->causedBy($request->user())->log('Menghapus ruangan');
        $room->delete();

        return back()->with('success', 'Ruangan dihapus.');
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalizeRoomIdentity(array $data, ?Room $room = null): array
    {
        $letter = strtoupper((string) ($data['building_letter'] ?? ''));
        if ($letter === '') {
            // Pakai pemetaan gedung yang sudah ada agar konsisten dengan kode unit.
            $letter = (string) (Room::query()
                ->where('building', $data['building'])
                ->whereNotNull('building_letter')
                ->value('building_letter') ?? '');
        }
        if ($letter === '') {
            preg_match('/[A-Za-z]/', (string) $data['building'], $matches);
            $letter = isset($matches[0]) ? strtoupper($matches[0]) : 'G';
        }

        $roomNo = $data['room_no'] ?? null;
        if ($roomNo === null) {
            preg_match('/(\d+)\s*$/', (string) $data['code'], $matches);
            $roomNo = isset($matches[1]) ? max(1, (int) $matches[1]) : (int) ($room?->room_no ?? 1);
        }

        return array_merge($data, [
            'building_letter' => $letter,
            'room_no' => (int) $roomNo,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function assertRoomIdentityUnique(array $data, ?Room $ignore = null): void
    {
        $exists = Room::query()
            ->where('building_letter', $data['building_letter'])
            ->where('floor', $data['floor'])
            ->where('room_no', $data['room_no'])
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'room_no' => 'Kombinasi gedung, lantai, dan nomor ruangan sudah dipakai. Kode inventaris akan bentrok.',
            ]);
        }
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($category->id)],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('categories', 'code')->ignore($category->id)],
        ]);

        $category->update([
            'name' => $data['name'],
            'code' => $data['code'] ?: null,
        ]);
        activity('master')->performedOn($category)->causedBy($request->user())->log('Mengubah kategori');

        return back()->with('success', 'Kategori diperbarui.');
    }

    public function destroyCategory(Request $request, Category $category): RedirectResponse
    {
        abort_if($category->items()->exists(), 422, 'Kategori masih digunakan oleh barang.');

        activity('master')->performedOn($category)->causedBy($request->user())->log('Menghapus kategori');
        $category->delete();

        return back()->with('success', 'Kategori dihapus.');
    }
}
