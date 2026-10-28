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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
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
        $token = $request->query('token');
        $sessionToken = $request->session()->get('registration_token');
        $expiresAt = $request->session()->get('registration_token_expires_at');

        if (! is_string($token) || ! is_string($sessionToken) || ! hash_equals($sessionToken, $token)
            || ! is_numeric($expiresAt) || now()->timestamp > (int) $expiresAt) {
            abort(403, 'Akses registrasi tidak diizinkan.');
        }

        return view('auth.register-new');
    }

    /**
     * Registrasi publik.
     */
    public function store(Request $request): RedirectResponse
    {
        $token = $request->input('registration_token');
        $sessionToken = $request->session()->get('registration_token');
        $expiresAt = $request->session()->get('registration_token_expires_at');

        if (! is_string($token) || ! is_string($sessionToken) || ! hash_equals($sessionToken, $token)
            || ! is_numeric($expiresAt) || now()->timestamp > (int) $expiresAt) {
            abort(403, 'Akses registrasi tidak diizinkan.');
        }

        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'email' => ['required', 'string', 'email', 'max:255', new GmailAddress, 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $birthDate = Carbon::parse($data['birth_date'])->startOfDay();
        $age = $birthDate->diffInYears(now(config('app.timezone'))->startOfDay());

        // Determine initial category based on age
        $initialBorrowerType = $age < 20 ? BorrowerType::SISWA->value : BorrowerType::GURU->value;

        $user = DB::transaction(function () use ($data, $birthDate, $initialBorrowerType) {
            $user = User::create([
                'name' => $data['name'],
                'birth_date' => $birthDate,
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => Role::PEMINJAM->value,
                'borrower_type' => $initialBorrowerType,
                'profile_completed' => false,
                'profile_completed_at' => null,
            ]);

            SpatieRole::findOrCreate(Role::PEMINJAM->value, 'web');
            $user->syncRoles([Role::PEMINJAM->value]);

            return $user;
        });

        // Clear registration token
        $request->session()->forget(['registration_token', 'registration_token_expires_at']);

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('profile.complete')
            ->with('success', 'Akun berhasil dibuat. Silakan lengkapi profil Anda.');
    }
}
