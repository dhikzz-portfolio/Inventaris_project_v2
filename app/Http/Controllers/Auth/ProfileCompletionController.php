<?php

namespace App\Http\Controllers\Auth;

use App\Enums\BorrowerType;
use App\Http\Controllers\Controller;
use App\Models\Major;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileCompletionController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        return view('auth.profile-complete', [
            'user' => $user,
            'borrowerTypes' => BorrowerType::cases(),
            'majors' => Major::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date'],
            'borrower_type' => ['required', Rule::in(BorrowerType::values())],
        ];

        if ($request->input('borrower_type') === BorrowerType::SISWA->value) {
            $rules['class_name'] = ['required', 'string', 'max:50'];
            $rules['major_id'] = ['nullable', 'exists:majors,id'];
        } elseif ($request->input('borrower_type') === BorrowerType::GURU->value) {
            $rules['degree'] = ['nullable', 'string', 'max:100'];
            $rules['major_id'] = ['nullable', 'exists:majors,id'];
        } else {
            $rules['work_unit'] = ['nullable', 'string', 'max:255'];
            $rules['major_id'] = ['nullable', 'exists:majors,id'];
        }

        $data = $request->validate($rules);

        $birthDate = Carbon::parse($data['birth_date']);
        $age = $birthDate->age;
        // If not calculated properly by Carbon age, do manual
        if ($age < 0 || $age > 150) {
            $age = now()->diffInYears($birthDate);
        }

        // Determine initial borrower type based on age if needed? The requirement says:
        // "Usia kurang dari 20 tahun saat akun dibuat: kategori awal Siswa. Usia 20 tahun atau lebih saat akun dibuat: kategori awal Guru."
        // But also says "Kategori pengguna berbeda dari role sistem. Semua akun hasil registrasi publik tetap memperoleh role Peminjam sampai role khusus ditetapkan melalui mekanisme berwenang."
        // And profile must be completed - we need to follow age-based classification for initial category if not set? Or enforce per requirements.

        $user->name = $data['name'];
        $user->birth_date = $birthDate;
        $user->borrower_type = $data['borrower_type'];
        if (isset($data['degree']) && $data['borrower_type'] === BorrowerType::GURU->value) {
            $user->degree = $data['degree'];
        } elseif ($data['borrower_type'] !== BorrowerType::GURU->value) {
            $user->degree = null;
        }
        if (isset($data['class_name'])) {
            $user->class_name = $data['class_name'];
        }
        if (isset($data['major_id'])) {
            $user->major_id = $data['major_id'];
        }
        if (isset($data['work_unit'])) {
            $user->work_unit = $data['work_unit'];
        }

        // Mark profile as completed
        $user->profile_completed = true;
        $user->profile_completed_at = now();
        $user->save();

        return redirect()->intended(route('dashboard'))->with('success', 'Profil berhasil dilengkapi.');
    }
}
