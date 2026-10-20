<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Major;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::with('major')->orderBy('role')->orderBy('name')->paginate(15),
            'majors' => Major::orderBy('name')->get(),
            'roles' => Role::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'role' => ['required', Rule::in(Role::values())],
            'major_id' => ['nullable', 'exists:majors,id'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'major_id' => $data['major_id'] ?? null,
        ]);

        $user->syncRoles([$data['role']]);

        activity('pengguna')->performedOn($user)->causedBy($request->user())->log("Membuat pengguna {$user->username} ({$user->role})");

        return back()->with('success', "Pengguna \"{$user->name}\" berhasil dibuat.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'role' => ['required', Rule::in(Role::values())],
            'major_id' => ['nullable', 'exists:majors,id'],
        ]);

        // Cegah diri sendiri menurunkan role sendiri.
        if ($request->user()->id === $user->id && $data['role'] !== Role::SUPER_ADMIN->value) {
            return back()->withErrors(['role' => 'Anda tidak dapat mengubah role akun sendiri.']);
        }

        $user->name = $data['name'];
        $user->username = $data['username'];
        $user->role = $data['role'];
        $user->major_id = $data['major_id'] ?? null;
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        $user->syncRoles([$data['role']]);

        activity('pengguna')->performedOn($user)->causedBy($request->user())->log("Mengubah pengguna {$user->username}");

        return back()->with('success', "Pengguna \"{$user->name}\" diperbarui.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'Tidak dapat menghapus akun sendiri.');
        abort_if($user->loans()->exists(), 422, 'Pengguna memiliki riwayat peminjaman — nonaktifkan role-nya saja.');

        activity('pengguna')->performedOn($user)->causedBy($request->user())->log("Menghapus pengguna {$user->username}");
        $user->delete();

        return back()->with('success', 'Pengguna dihapus.');
    }
}
