<?php

namespace App\Http\Controllers;

use App\Models\Building;
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
            'buildings' => Building::withCount('rooms')->orderBy('code')->get(),
            'rooms' => Room::with(['major', 'building'])->orderBy('building_letter')->orderBy('floor')->orderBy('room_no')->get(),
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

    // ================= MASTER GEDUNG =================

    public function storeBuilding(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:buildings,name'],
            'code' => ['required', 'string', 'size:1', 'regex:/^[A-Za-z]$/', 'unique:buildings,code'],
        ]);

        $building = Building::create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        activity('master')->performedOn($building)
            ->causedBy($request->user())->log('Menambah master gedung');

        return back()->with('success', 'Gedung ditambahkan.');
    }

    public function updateBuilding(Request $request, Building $building): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('buildings', 'name')->ignore($building->id)],
            'code' => ['required', 'string', 'size:1', 'regex:/^[A-Za-z]$/', Rule::unique('buildings', 'code')->ignore($building->id)],
        ]);

        $newCode = strtoupper($data['code']);
        $originalCode = $building->code;

        // Prosedur perubahan identitas yang aman: kode gedung tidak boleh
        // berubah diam-diam bila sudah ada unit inventaris yang memakainya.
        if ($newCode !== $originalCode && $building->roomsWithUnits()->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Kode gedung tidak dapat diubah karena sudah ada unit inventaris yang memakai kode lama pada gedung ini. Mutasikan unit terlebih dahulu.',
            ]);
        }

        $building->update([
            'name' => $data['name'],
            'code' => $newCode,
        ]);

        if ($newCode !== $originalCode) {
            $this->syncRoomsToBuilding($building);
        }

        activity('master')->performedOn($building)->causedBy($request->user())->log('Mengubah master gedung');

        return back()->with('success', 'Gedung diperbarui.');
    }

    public function destroyBuilding(Request $request, Building $building): RedirectResponse
    {
        abort_if($building->rooms()->exists(), 422, 'Gedung masih memiliki ruangan — pindahkan/hapus ruangan terlebih dahulu.');

        activity('master')->performedOn($building)->causedBy($request->user())->log('Menghapus master gedung');
        $building->delete();

        return back()->with('success', 'Gedung dihapus.');
    }

    /**
     * Selaraskan identitas ruangan dengan master gedung.
     *
     * Dipakai ketika kode gedung berubah dan tidak ada unit inventaris yang
     * bergantung pada kode lama. Kode lokasi ruangan diperbarui, sedangkan
     * kode inventaris yang sudah terbit tidak disentuh.
     */
    private function syncRoomsToBuilding(Building $building): void
    {
        foreach ($building->rooms()->get() as $room) {
            $room->update([
                'building_letter' => $building->code,
                'code' => $this->roomCode($building->code, (int) $room->floor, (int) $room->room_no),
            ]);
        }
    }

    // ================= RUANGAN =================

    public function storeRoom(Request $request): RedirectResponse
    {
        $data = $this->validateRoomRequest($request);

        $building = Building::findOrFail($data['building_id']);
        $this->assertBuildingUsable($building);

        $data = $this->normalizeRoomIdentity($data, $building);
        $this->assertRoomIdentityUnique($data);

        $room = Room::create($data);

        activity('master')->performedOn($room)->causedBy($request->user())->log('Menambah data ruangan');

        return back()->with('success', "Ruangan {$room->code} ditambahkan.");
    }

    public function updateRoom(Request $request, Room $room): RedirectResponse
    {
        $data = $this->validateRoomRequest($request, $room);

        $building = Building::findOrFail($data['building_id']);
        $this->assertBuildingUsable($building);

        $data = $this->normalizeRoomIdentity($data, $building);
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

    /**
     * Validasi formulir ruangan.
     *
     * Formulir hanya meminta gedung (dropdown dari master), lantai, dan nomor
     * ruangan. Nama dan kode lokasi diturunkan sistem.
     *
     * @return array<string, mixed>
     */
    private function validateRoomRequest(Request $request, ?Room $room = null): array
    {
        $data = $request->validate([
            'major_id' => ['nullable', 'exists:majors,id'],
            'building_id' => ['required', 'exists:buildings,id'],
            'floor' => ['required', 'integer', 'min:1', 'max:9'],
            'room_no' => ['required', 'integer', 'min:1', 'max:99'],
            'name' => ['nullable', 'string', 'max:100'],
            'is_lab' => ['nullable', 'boolean'],
        ]);

        $data['is_lab'] = $request->boolean('is_lab');

        return $data;
    }

    /** Gedung nonaktif tidak boleh dipakai untuk lokasi baru. */
    private function assertBuildingUsable(Building $building): void
    {
        if (! $building->is_active) {
            throw ValidationException::withMessages([
                'building_id' => "Gedung \"{$building->name}\" sedang nonaktif dan tidak dapat dipakai untuk lokasi baru.",
            ]);
        }
    }

    /**
     * Turunkan identitas ruangan dari master gedung.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeRoomIdentity(array $data, Building $building): array
    {
        $floor = (int) $data['floor'];
        $roomNo = (int) $data['room_no'];

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $name = sprintf('Ruang %02d', $roomNo);
        }

        return array_merge($data, [
            'building_letter' => $building->code,
            'name' => $name,
            'code' => $this->roomCode($building->code, $floor, $roomNo),
        ]);
    }

    /** Kode lokasi ruangan: huruf gedung + lantai + nomor ruangan, mis. C1-03. */
    private function roomCode(string $buildingCode, int $floor, int $roomNo): string
    {
        return sprintf('%s%d-%02d', strtoupper($buildingCode), $floor, $roomNo);
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

    // ================= JURUSAN & KATEGORI =================

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

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
        ]);

        Category::create([
            'name' => $data['name'],
        ]);

        return back()->with('success', 'Kategori ditambahkan.');
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($category->id)],
        ]);

        $category->update([
            'name' => $data['name'],
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
