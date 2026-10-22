<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Major;
use App\Models\Room;
use App\Models\User;
use App\Models\UserRoomScope;
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
            'users' => User::with(['major', 'roomScopes.room'])->orderBy('role')->orderBy('name')->paginate(15),
            'majors' => Major::orderBy('name')->get(),
            'roles' => Role::cases(),
            'labRooms' => Room::where('is_lab', true)->orderBy('building_letter')->orderBy('floor')->orderBy('room_no')->get(),
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
            'room_ids' => ['nullable', 'array'],
            'room_ids.*' => ['integer', Rule::exists('rooms', 'id')->where('is_lab', true)],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'major_id' => $data['major_id'] ?? null,
        ]);

        $user->syncRoles([$data['role']]);
        $this->syncRoomScopes($user, $data['room_ids'] ?? [], $request->user()->id);

        activity('pengguna')->performedOn($user)->causedBy($request->user())
            ->withProperties(['akses_ruangan_sesudah' => $this->roomScopeIds($user)])
            ->log("Membuat pengguna {$user->username} ({$user->role})");

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
            'room_ids' => ['nullable', 'array'],
            'room_ids.*' => ['integer', Rule::exists('rooms', 'id')->where('is_lab', true)],
        ]);

        // Cegah diri sendiri menurunkan role sendiri.
        if ($request->user()->id === $user->id && $data['role'] !== Role::SUPER_ADMIN->value) {
            return back()->withErrors(['role' => 'Anda tidak dapat mengubah role akun sendiri.']);
        }

        $scopeBefore = $this->roomScopeIds($user);

        $user->name = $data['name'];
        $user->username = $data['username'];
        $user->role = $data['role'];
        $user->major_id = $data['major_id'] ?? null;
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        $user->syncRoles([$data['role']]);
        $this->syncRoomScopes($user, $data['room_ids'] ?? [], $request->user()->id);

        activity('pengguna')->performedOn($user)->causedBy($request->user())
            ->withProperties([
                'akses_ruangan_sebelum' => $scopeBefore,
                'akses_ruangan_sesudah' => $this->roomScopeIds($user),
            ])
            ->log("Mengubah pengguna {$user->username}");

        return back()->with('success', "Pengguna \"{$user->name}\" diperbarui.");
    }

    /** @param array<int, int|string> $roomIds */
    private function syncRoomScopes(User $user, array $roomIds, int $assignedBy): void
    {
        $user->roomScopes()->delete();

        if ($user->role !== Role::LABORAN->value) {
            return;
        }

        foreach (array_unique(array_map('intval', $roomIds)) as $roomId) {
            UserRoomScope::create([
                'user_id' => $user->id,
                'room_id' => $roomId,
                'assigned_by' => $assignedBy,
            ]);
        }
    }

    /** @return list<int> */
    private function roomScopeIds(User $user): array
    {
        return $user->roomScopes()->orderBy('room_id')->pluck('room_id')->map(fn ($id) => (int) $id)->all();
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
