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
use Spatie\Activitylog\Models\Activity;

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

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'role' => trim((string) $request->query('role', '')),
            'status' => trim((string) $request->query('status', '')),
        ];

        $users = User::with(['major', 'roomScopes.room'])
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where(fn ($q) => $q
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('class_name', 'like', $term)
                    ->orWhere('work_unit', 'like', $term));
            })
            ->when(in_array($filters['role'], Role::values(), true), fn ($query) => $query->where('role', $filters['role']))
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'filters' => $filters,
            'stats' => [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'inactive' => User::where('is_active', false)->count(),
                'account_admins' => User::where('role', Role::SUPER_ADMIN->value)->count(),
            ],
            'majors' => Major::orderBy('name')->get(),
            'roles' => $this->assignableRoles($actor),
            'borrowerTypes' => BorrowerType::cases(),
            'labRooms' => Room::where('is_lab', true)->orderBy('building_letter')->orderBy('floor')->orderBy('room_no')->get(),
            'editUser' => $editId !== null
                ? User::with(['major', 'roomScopes'])->find($editId)
                : null,
            'canManageSuperAdmin' => false,
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
            'must_change_password' => ['nullable', 'boolean'],
        ], $this->categoryMessages($actor));

        $this->assertMayAssignRole($actor, $data['role']);

        $user = User::create([
            'name' => $data['name'],
            'degree' => $category['is_siswa'] ? null : ($data['degree'] ?? null),
            'email' => $data['email'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => $request->boolean('is_active', true),
            'must_change_password' => $request->boolean('must_change_password'),
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
            'role' => ['required', Rule::in(array_column($this->assignableRoles($actor), 'value'))],
            'borrower_type' => ['nullable', Rule::in(BorrowerType::values())],
            'class_name' => $category['is_teacher_or_staff'] ? ['prohibited'] : ['nullable', 'string', 'max:50'],
            'major_id' => $category['is_teacher_or_staff'] ? ['prohibited'] : ['nullable', 'exists:majors,id'],
            'room_ids' => ['nullable', 'array'],
            'room_ids.*' => ['integer', Rule::exists('rooms', 'id')->where('is_lab', true)],
            'is_active' => ['nullable', 'boolean'],
            'must_change_password' => ['nullable', 'boolean'],
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
            $user->must_change_password = true;
            $user->password_changed_at = null;
        } elseif ($request->has('must_change_password')) {
            $user->must_change_password = $request->boolean('must_change_password');
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

    /** Detail akun + riwayat perubahan (audit trail tanpa password). */
    public function show(Request $request, User $user): View
    {
        $this->assertCanManageTarget($request->user(), $user);

        $user->load(['major', 'roomScopes.room']);

        $history = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->with('causer')
            ->latest()
            ->limit(50)
            ->get();

        return view('users.show', [
            'user' => $user,
            'history' => $history,
            'loanCount' => $user->loans()->count(),
            'isLastActiveSuperAdmin' => $user->isSuperAdmin() && $this->activeSuperAdminCount() <= 1,
        ]);
    }

    /** Aktifkan/nonaktifkan akun tanpa menghapus riwayatnya. */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $this->assertCanManageTarget($actor, $user);

        if ((int) $actor->id === (int) $user->id) {
            return back()->withErrors(['status' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
        }

        if ($user->isSuperAdmin() && $user->isActive() && $this->activeSuperAdminCount() <= 1) {
            return back()->withErrors(['status' => 'Akun Super Admin utama terakhir yang aktif tidak boleh dinonaktifkan.']);
        }

        $active = ! $user->isActive();
        $user->forceFill(['is_active' => $active])->save();

        activity('pengguna')
            ->performedOn($user)
            ->causedBy($actor)
            ->withProperties([
                'aksi' => $active ? 'AKTIFKAN' : 'NONAKTIFKAN',
                'akun_target' => $user->id,
                'aktif_sesudah' => $active,
            ])
            ->log(($active ? 'Mengaktifkan' : 'Menonaktifkan')." pengguna {$user->name}");

        NotifyApp::toAccountAdmins(NotificationType::AKUN,
            "Status akun {$user->displayName()} diubah menjadi ".($active ? 'AKTIF' : 'NONAKTIF')." oleh {$actor->displayName()}.",
            route('users.index'), $actor->id);

        return back()->with('success', "Akun \"{$user->displayName()}\" ".($active ? 'diaktifkan' : 'dinonaktifkan').'.');
    }

    /**
     * Role yang boleh ditetapkan oleh aktor. Admin tidak boleh
     * membuat atau mengubah akun menjadi Super Admin (PENDING DECISION 2).
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
                'role' => 'Hanya Super Admin yang dapat menetapkan role Super Admin.',
            ]);
        }
    }

    private function assertCanManageTarget(User $actor, User $target): void
    {
        abort_unless($actor->canManageUsers(), 403);

        // Akun Super Admin hanya boleh diubah/dihapus oleh Super Admin.
        if ($target->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            abort(403, 'Akun Super Admin tidak dapat diubah oleh Admin.');
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
        $isPeminjam = (string) $request->input('role') === Role::MEMBER->value;
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
                : 'Admin tidak dapat menetapkan role Super Admin.',
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

        if ($user->role !== Role::OPERATOR->value) {
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
