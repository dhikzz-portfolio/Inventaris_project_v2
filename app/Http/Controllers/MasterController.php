<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Major;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MasterController extends Controller
{
    public function index(): View
    {
        return view('masters.index', [
            'majors' => Major::withCount('rooms')->orderBy('name')->get(),
            'rooms' => Room::with('major')->orderBy('building')->orderBy('floor')->orderBy('code')->get(),
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
            'floor' => ['required', 'integer', 'min:0', 'max:50'],
            'code' => ['required', 'string', 'max:30', 'unique:rooms,code'],
            'name' => ['required', 'string', 'max:100'],
        ]);

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
            'floor' => ['required', 'integer', 'min:0', 'max:50'],
            'code' => ['required', 'string', 'max:30', Rule::unique('rooms', 'code')->ignore($room->id)],
            'name' => ['required', 'string', 'max:100'],
        ]);

        // Perubahan kode ruangan akan membuat ID internal lama tidak lagi spasial.
        // Barang yang berada di ruangan ini diperbarui melalui alur mutasi terkontrol.
        $room->update($data);
        activity('master')->performedOn($room)->causedBy($request->user())->log('Mengubah data ruangan');

        return back()->with('success', 'Ruangan diperbarui.');
    }

    public function destroyRoom(Request $request, Room $room): RedirectResponse
    {
        abort_if($room->items()->exists(), 422, 'Ruangan masih menyimpan barang — mutasi barang terlebih dahulu.');
        abort_if($room->movementsFrom()->exists() || $room->movementsTo()->exists(), 422, 'Ruangan memiliki riwayat mutasi dan tidak dapat dihapus.');

        activity('master')->performedOn($room)->causedBy($request->user())->log('Menghapus ruangan');
        $room->delete();

        return back()->with('success', 'Ruangan dihapus.');
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
