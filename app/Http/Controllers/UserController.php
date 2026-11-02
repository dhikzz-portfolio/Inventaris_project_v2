<?php

namespace App\Http\Controllers;

use App\Enums\BorrowerType;
use App\Enums\Role;
use App\Models\Event;
use App\Models\Major;
use App\Models\Room;
use App\Models\User;
use App\Models\UserRoomScope;
use App\Rules\GmailAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $editId = $request->query('edit');

        return view('users.index', [
            'users' => User::with(['major', 'roomScopes.room'])->orderBy('role')->orderBy('name')->paginate(15)->withQueryString(),
            'majors' => Major::orderBy('name')->get(),
            'roles' => Role::cases(),
            'borrowerTypes' => BorrowerType::cases(),
            'labRooms' => Room::where('is_lab', true)->orderBy('building_letter')->orderBy('floor')->orderBy('room_no')->get(),
            'editUser' => $editId !== null
                ? User::with(['major', 'roomScopes'])->find($editId)
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeEmail($request);
        $category = $this->borrowerContext($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'degree' => $category['is_siswa'] ? ['prohibited'] : ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'email', 'max:255', new GmailAddress, 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(Role::values())],
            'borrower_type' => ['nullable', Rule::in(BorrowerType::values())],
            'class_name' => $category['is_teacher_or_staff'] ? ['prohibited'] : ['nullable', 'string', 'max:50'],
            'major_id' => $category['is_teacher_or_staff'] ? ['prohibited'] : ['nullable', 'exists:majors,id'],
            'room_ids' => ['nullable', 'array'],
            'room_ids.*' => ['integer', Rule::exists('rooms', 'id')->where('is_lab', true)],
        ], $this->categoryMessages());

        $user = User::create([
            'name' => $data['name'],
            'degree' => $category['is_siswa'] ? null : ($data['degree'] ?? null),
            'email' => $data['email'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'borrower_type' => $category['is_peminjam'] ? ($data['borrower_type'] ?? null) : null,
            'class_name' => $category['is_siswa'] ? ($data['class_name'] ?? null) : null,
            'major_id' => $category['is_teacher_or_staff'] ? null : ($data['major_id'] ?? null),
        ]);

        $user->syncRoles([$data['role']]);
        $this->syncRoomScopes($user, $data['room_ids'] ?? [], $request->user()->id);

        activity('pengguna')->performedOn($user)->causedBy($request->user())
            ->withProperties(['akses_ruangan_sesudah' => $this->roomScopeIds($user)])
            ->log("Membuat pengguna {$user->name} ({$user->role})");

        return back()->with('success', "Pengguna \"{$user->name}\" berhasil dibuat.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
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
        ], $this->categoryMessages());

        // Cegah diri sendiri menurunkan role sendiri.
        if ($request->user()->id === $user->id && $data['role'] !== Role::SUPER_ADMIN->value) {
            return back()->withErrors(['role' => 'Anda tidak dapat mengubah role akun sendiri.']);
        }

        $scopeBefore = $this->roomScopeIds($user);

        $user->name = $data['name'];
        $user->degree = $category['is_siswa'] ? null : ($data['degree'] ?? null);
        $user->email = $data['email'] ?? null;
        $user->role = $data['role'];
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
        $this->syncRoomScopes($user, $data['room_ids'] ?? [], $request->user()->id);

        activity('pengguna')->performedOn($user)->causedBy($request->user())
            ->withProperties([
                'akses_ruangan_sebelum' => $scopeBefore,
                'akses_ruangan_sesudah' => $this->roomScopeIds($user),
            ])
            ->log("Mengubah pengguna {$user->name}");

        return back()->with('success', "Pengguna \"{$user->name}\" diperbarui.");
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
    private function categoryMessages(): array
    {
        return [
            'degree.prohibited' => 'Gelar tidak berlaku untuk peminjam kategori Siswa.',
            'major_id.prohibited' => 'Jurusan tidak berlaku untuk peminjam kategori Guru/Staf.',
            'class_name.prohibited' => 'Kelas hanya berlaku untuk peminjam kategori Siswa.',
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

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'Tidak dapat menghapus akun sendiri.');
        abort_if($user->loans()->exists(), 422, 'Pengguna memiliki riwayat peminjaman — nonaktifkan role-nya saja.');
        abort_if(
            Event::where('created_by', $user->id)->exists(),
            422,
            'Pengguna tercatat sebagai pembuat kegiatan. Pertahankan akun agar relasi kegiatan tidak yatim.'
        );

        activity('pengguna')->performedOn($user)->causedBy($request->user())->log("Menghapus pengguna {$user->name}");
        $user->delete();

        return back()->with('success', 'Pengguna dihapus.');
    }
}
