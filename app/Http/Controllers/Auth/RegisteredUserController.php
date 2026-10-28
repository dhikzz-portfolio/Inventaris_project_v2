<?php

namespace App\Http\Controllers\Auth;

use App\Enums\BorrowerType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\GmailAddress;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role as SpatieRole;

class RegisteredUserController extends Controller
{
    /**
     * Tampilkan formulir registrasi mandiri.
     */
    public function create(Request $request): View|RedirectResponse
    {
        // Only allow via registration token from login button
        $token = $request->session()->get('registration_token');
        $expiresAt = $request->session()->get('registration_token_expires_at');

        if (! $token || ! $expiresAt || now()->timestamp > $expiresAt) {
            abort(403, 'Akses registrasi tidak diizinkan.');
        }

        return view('auth.register-new');
    }

    /**
     * Registrasi publik.
     */
    public function store(Request $request): RedirectResponse
    {
        $token = $request->session()->get('registration_token');
        $expiresAt = $request->session()->get('registration_token_expires_at');

        if (! $token || ! $expiresAt || now()->timestamp > $expiresAt) {
            abort(403, 'Akses registrasi tidak diizinkan.');
        }

        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date'],
            'email' => ['required', 'string', 'email', 'max:255', new GmailAddress, 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $birthDate = Carbon::parse($data['birth_date']);
        $age = $birthDate->diffInYears(now());
        if ($age < 0) {
            throw ValidationException::withMessages(['birth_date' => 'Tanggal lahir tidak valid.']);
        }

        // Determine initial category based on age
        $initialBorrowerType = $age < 20 ? BorrowerType::SISWA->value : BorrowerType::GURU->value;

        // Generate username from email local part if needed
        $username = explode('@', $data['email'])[0] . '_' . str()->random(4);

        $user = DB::transaction(function () use ($data, $birthDate, $initialBorrowerType, $username) {
            $user = User::create([
                'name' => $data['name'],
                'birth_date' => $birthDate,
                'email' => $data['email'],
                'username' => $username,
                'password' => $data['password'],
                'role' => Role::PEMINJAM->value,
                'borrower_type' => $initialBorrowerType,
                'profile_completed' => false,
            ]);

            SpatieRole::findOrCreate(Role::PEMINJAM->value, 'web');
            $user->syncRoles([Role::PEMINJAM->value]);

            return $user;
        });

        // Clear registration token
        $request->session()->forget(['registration_token', 'registration_token_expires_at']);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('profile.complete')
            ->with('success', 'Akun berhasil dibuat. Silakan lengkapi profil Anda.');
    }
}
