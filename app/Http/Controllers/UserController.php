<?php

namespace App\Http\Controllers;

use App\Enums\BorrowerType;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Models\Event;
use App\Models\Major;
use App\Models\Room;
use App\Models\User;
use App\Models\UserRoomScope;
use App\Rules\GmailAddress;
use App\Services\NotifyApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Administrasi akun pengguna.
 *
 * Super Admin utama memiliki kewenangan penuh. Super Admin Pengguna hanya
 * mengelola akun (least privilege) dan tidak otomatis memperoleh akses
 * transaksi inventaris. Pembatasan tersebut diterapkan di backend, bukan
 * sekadar menyembunyikan menu.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();
        $editId = $request->query('edit');

        return view('users.index', [
            'users' => User::with(['major', 'roomScopes.room'])->orderBy('role')->orderBy('name')->paginate(15)->withQueryString(),
            'majors' => Major::orderBy('name')->get(),
            'roles' => $this->assignableRoles($actor),
            'borrowerTypes' => BorrowerType::cases(),
            'labRooms' => Room::where('is_lab', true)->orderBy('building_letter')->orderBy('floor')->orderBy('room_no')->get(),
            'editUser' => $editId !== null
                ? User::with(['major', 'roomScopes'])->find($editId)
                : null,
            'canManageSuperAdmin' => $actor->isSuperAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $this->normalizeEmail($request);
        $category = $this->borrowerContext($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'degree' => $category['is_siswa'] ? ['prohibited'] : ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'email', 'max:255', new GmailAddress, 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(array_column($this->assignableRoles($actor), 'value'))],
            'borrower_type' => ['nullable', Rule::in(BorrowerType::values())],
            'class_name' => $category['is_teacher_or_staff'] ? ['prohibited'] : ['nullable', 'string', 'max:50'],
            'major_id' => $category['is_teacher_or_staff'] ? ['prohibited'] : ['nullable', 'exists:majors,id'],
            'room_ids' => ['nullable', 'array'],
            'room_ids.*' => ['integer', Rule::exists('rooms', 'id')->where('is_lab', true)],
            'is_active' => ['nullable', 'boolean'],
        ], $this->categoryMessages($actor));

        $this->assertMayAssignRole($actor, $data['role']);

        $user = User::create([
            'name' => $data['name'],
            'degree' => $category['is_siswa'] ? null : ($data['degree'] ?? null),
            'email' => $data['email'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => $request->boolean('is_active', true),
            'borrower_type' => $category['is_peminjam'] ? ($data['borrower_type'] ?? null) : null,
            'class_name' => $category['is_siswa'] ? ($data['class_name'] ?? null) : null,
            'major_id' => $category['is_teacher_or_staff'] ? null : ($data['major_id'] ?? null),
        ]);

        $user->syncRoles([$data['role']]);
        $this->syncRoomScopes($user, $data['room_ids'] ?? [], $actor->id);

        activity('pengguna')->performedOn($user)->causedBy($actor)
            ->withProperties([
                'aksi' => 'BUAT',
                'akun_target' => $user->id,
                'role_sesudah' => $user->role,
                'aktif_sesudah' => $user->is_active,
                'akses_ruangan_sesudah' => $this->roomScopeIds($user),
            ])
            ->log("Membuat pengguna {$user->name} ({$user->role})");

        NotifyApp::toAccountAdmins(NotificationType::AKUN,
            "Akun baru dibuat: {$user->displayName()} (".Role::from($user->role)->label().") oleh {$actor->displayName()}.",
            route('users.index'), $actor->id);

        return back()->with('success', "Pengguna \"{$user->name}\" berhasil dibuat.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $this->assertCanManageTarget($actor, $user);

        $this->normalizeEmail($request);
        $category = $this->borrowerContext($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'degree' => $category['is_siswa'] ? ['prohibited'] : ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'email', 'max:255', new GmailAddress, Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(Role::values())],
            'borrower_type' => ['nullable', Rule::in(BorrowerType::values())],
            'class_name' => $category['is_teacher_or_staff'] ? ['prohibited'] : ['nullable', 'string', 'max:50'],
            'major_id' => $category['is_teacher_or_staff'] ? ['prohibited'] : ['nullable', 'exists:majors,id'],
            'room_ids' => ['nullable', 'array'],
            'room_ids.*' => ['integer', Rule::exists('rooms', 'id')->where('is_lab', true)],
            'is_active' => ['nullable', 'boolean'],
        ], $this->categoryMessages($actor));

        $this->assertMayAssignRole($actor, $data['role']);

        // Cegah diri sendiri menurunkan role sendiri.
        if ($actor->id === $user->id && $data['role'] !== Role::SUPER_ADMIN->value) {
            return back()->withErrors(['role' => 'Anda tidak dapat mengubah role akun sendiri.']);
        }

        $isActive = $request->has('is_active')
            ? $request->boolean('is_active')
            : $user->isActive();

        if ($actor->id === $user->id && ! $isActive) {
            return back()->withErrors(['is_active' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
        }

        // Perlindungan akun administratif utama: sistem tidak boleh kehilangan
        // seluruh Super Admin aktif.
        if ($user->isSuperAdmin()
            && ($data['role'] !== Role::SUPER_ADMIN->value || ! $isActive)
            && $this->activeSuperAdminCount() <= 1) {
            return back()->withErrors([
                'role' => 'Tidak dapat menurunkan atau menonaktifkan satu-satunya akun Super Admin utama yang aktif.',
            ]);
        }

        $scopeBefore = $this->roomScopeIds($user);
        $before = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->isActive(),
            'akses_ruangan' => $scopeBefore,
        ];

        $user->name = $data['name'];
        $user->degree = $category['is_siswa'] ? null : ($data['degree'] ?? null);
        $user->email = $data['email'] ?? null;
        $user->role = $data['role'];
        $user->is_active = $isActive;
        $user->borrower_type = $category['is_peminjam']
            ? ($data['borrower_type'] ?? $user->borrower_type)
            : null;
        $user->class_name = $category['is_siswa'] ? ($data['class_name'] ?? null) : null;
        $user->major_id = $category['is_teacher_or_staff'] ? null : ($data['major_id'] ?? null);
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        $user->syncRoles([$data['role']]);
        $this->syncRoomScopes($user, $data['room_ids'] ?? [], $actor->id);

        activity('pengguna')->performedOn($user)->causedBy($actor)
            ->withProperties([
                'aksi' => 'UBAH',
                'akun_target' => $user->id,
                'sebelum' => $before,
                'sesudah' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_active' => $user->isActive(),
                    'akses_ruangan' => $this->roomScopeIds($user),
                ],
                // Kunci lama dipertahankan agar audit trail historis tetap terbaca.
                'akses_ruangan_sebelum' => $scopeBefore,
                'akses_ruangan_sesudah' => $this->roomScopeIds($user),
            ])
            ->log("Mengubah pengguna {$user->name}");

        // Pengguna terdampak menerima pemberitahuan pada halaman profilnya
        // sendiri, bukan tautan pengelolaan akun yang bukan haknya.
        if ((int) $user->id !== (int) $actor->id && $user->isActive()) {
            NotifyApp::to($user, NotificationType::AKUN,
                'Data akun Anda diperbarui oleh administrator.', route('profile.edit'));
        }

        NotifyApp::toAccountAdmins(NotificationType::AKUN,
            "Akun {$user->displayName()} diperbarui oleh {$actor->displayName()}.",
            route('users.index'), $actor->id);

        return back()->with('success', "Pengguna \"{$user->name}\" diperbarui.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $this->assertCanManageTarget($actor, $user);

        abort_if($user->id === $actor->id, 422, 'Tidak dapat menghapus akun sendiri.');

        if ($user->isSuperAdmin() && $this->activeSuperAdminCount() <= 1) {
            abort(422, 'Tidak dapat menghapus satu-satunya akun Super Admin utama yang aktif.');
        }

        abort_if($user->loans()->exists(), 422, 'Pengguna memiliki riwayat peminjaman — nonaktifkan role-nya saja.');
        abort_if(
            Event::where('created_by', $user->id)->exists(),
            422,
            'Pengguna tercatat sebagai pembuat kegiatan. Pertahankan akun agar relasi kegiatan tidak yatim.'
        );

        activity('pengguna')->performedOn($user)->causedBy($actor)
            ->withProperties([
                'aksi' => 'HAPUS',
                'akun_target' => $user->id,
                'role_sebelum' => $user->role,
                'email_sebelum' => $user->email,
            ])
            ->log("Menghapus pengguna {$user->name}");

        NotifyApp::toAccountAdmins(NotificationType::AKUN,
            "Akun {$user->displayName()} dihapus oleh {$actor->displayName()}.",
            route('users.index'), $actor->id);

        $user->delete();

        return back()->with('success', 'Pengguna dihapus.');
    }

    /**
     * Role yang boleh ditetapkan oleh aktor. Super Admin Pengguna tidak boleh
     * membuat atau mengubah akun menjadi Super Admin utama (PENDING DECISION 2).
     *
     * @return list<Role>
     */
    private function assignableRoles(User $actor): array
    {
        if ($actor->isSuperAdmin()) {
            return Role::cases();
        }

        return array_values(array_filter(
            Role::cases(),
            fn (Role $role) => $role->isAssignableByAccountAdmin(),
        ));
    }

    private function assertMayAssignRole(User $actor, string $role): void
    {
        if ($role === Role::SUPER_ADMIN->value && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'role' => 'Hanya Super Admin utama yang dapat menetapkan role Super Admin.',
            ]);
        }
    }

    private function assertCanManageTarget(User $actor, User $target): void
    {
        abort_unless($actor->canManageUsers(), 403);

        // Akun Super Admin utama hanya boleh diubah/dihapus oleh Super Admin utama.
        if ($target->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            abort(403, 'Akun Super Admin utama tidak dapat diubah oleh Super Admin Pengguna.');
        }
    }

    private function activeSuperAdminCount(): int
    {
        return User::query()
            ->where('role', Role::SUPER_ADMIN->value)
            ->where('is_active', true)
            ->count();
    }

    /**
     * Kategori peminjam menentukan field yang sah. Nilai ini dibaca dari
     * request tetapi aturan backend tetap menolak data yang tidak sesuai.
     *
     * @return array{is_peminjam: bool, is_siswa: bool, is_teacher_or_staff: bool}
     */
    private function borrowerContext(Request $request): array
    {
        $isPeminjam = (string) $request->input('role') === Role::PEMINJAM->value;
        $type = (string) $request->input('borrower_type');
        $isSiswa = $isPeminjam && $type === BorrowerType::SISWA->value;
        $isTeacherOrStaff = $isPeminjam && in_array($type, [BorrowerType::GURU->value, BorrowerType::STAF->value], true);

        return [
            'is_peminjam' => $isPeminjam,
            'is_siswa' => $isSiswa,
            'is_teacher_or_staff' => $isTeacherOrStaff,
        ];
    }

    /** @return array<string, string> */
    private function categoryMessages(User $actor): array
    {
        return [
            'degree.prohibited' => 'Gelar tidak berlaku untuk peminjam kategori Siswa.',
            'major_id.prohibited' => 'Jurusan tidak berlaku untuk peminjam kategori Guru/Staf.',
            'class_name.prohibited' => 'Kelas hanya berlaku untuk peminjam kategori Siswa.',
            'role.in' => $actor->isSuperAdmin()
                ? 'Role yang dipilih tidak valid.'
                : 'Super Admin Pengguna tidak dapat menetapkan role Super Admin utama.',
        ];
    }

    private function normalizeEmail(Request $request): void
    {
        if ($request->filled('email')) {
            $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        }
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
}
