<?php

namespace App\Http\Controllers\Auth;

use App\Enums\BorrowerType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Major;
use App\Models\User;
use App\Rules\GmailAddress;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role as SpatieRole;

class RegisteredUserController extends Controller
{
    /**
     * Tampilkan formulir registrasi mandiri.
     */
    public function create(): View
    {
        return view('auth.register', [
            'borrowerTypes' => BorrowerType::cases(),
            'majors' => Major::orderBy('name')->get(),
        ]);
    }

    /**
     * Registrasi publik.
     *
     * Role tidak pernah dibaca dari request: setiap akun baru otomatis
     * memperoleh role PEMINJAM dengan permission minimum. Parameter seperti
     * `role=SUPER_ADMIN` atau `is_admin=1` diabaikan sepenuhnya.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Domain Gmail diperiksa tanpa membedakan huruf besar/kecil; simpan
        // bentuk normal agar cek duplikat konsisten di semua database.
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'degree' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', new GmailAddress, 'unique:users,email'],
            'username' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'borrower_type' => ['required', Rule::in(BorrowerType::values())],
            'class_name' => ['nullable', 'string', 'max:50', 'required_if:borrower_type,SISWA'],
            'major_id' => ['nullable', 'exists:majors,id'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'email.lowercase' => 'Email harus ditulis dengan huruf kecil.',
            'borrower_type.required' => 'Pilih kategori pengguna (siswa, guru, atau staf).',
            'class_name.required_if' => 'Kelas wajib diisi untuk pengguna siswa.',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'degree' => $data['borrower_type'] === BorrowerType::GURU->value
                    ? ($data['degree'] ?? null)
                    : null,
                'email' => $data['email'],
                'username' => $data['username'],
                'password' => $data['password'],
                // Ditentukan backend — bukan dari input pengguna.
                'role' => Role::PEMINJAM->value,
                'borrower_type' => $data['borrower_type'],
                'class_name' => $data['borrower_type'] === BorrowerType::SISWA->value
                    ? ($data['class_name'] ?? null)
                    : null,
                'major_id' => $data['major_id'] ?? null,
            ]);

            // Pastikan role dasar tersedia (mis. instalasi baru sebelum seeder)
            // lalu tetapkan dari backend — bukan dari input pengguna.
            SpatieRole::findOrCreate(Role::PEMINJAM->value, 'web');
            $user->syncRoles([Role::PEMINJAM->value]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false))
            ->with('success', 'Akun berhasil dibuat. Selamat datang, '.$user->greetingName().'!');
    }
}
