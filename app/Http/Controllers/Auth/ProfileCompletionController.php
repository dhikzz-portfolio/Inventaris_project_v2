<?php

namespace App\Http\Controllers\Auth;

use App\Enums\BorrowerType;
use App\Http\Controllers\Controller;
use App\Models\Major;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ProfileCompletionController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasCompletedProfile() || ! $user->isPeminjam()) {
            return redirect()->route('dashboard');
        }

        return view('auth.profile-complete', [
            'user' => $user,
            'majors' => Major::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->isPeminjam(), 403);

        if ($user->hasCompletedProfile()) {
            return redirect()->route('dashboard');
        }

        $borrowerType = $user->borrower_type;
        $rules = [
            'name' => ['required', 'string', 'max:255'],
        ];

        if ($user->birth_date === null) {
            $rules['birth_date'] = ['required', 'date_format:Y-m-d', 'before_or_equal:today'];
        }

        if ($borrowerType === null) {
            $rules['borrower_type'] = ['prohibited'];
        }

        if ($borrowerType === BorrowerType::SISWA || ($borrowerType === null && $user->birth_date === null)) {
            $rules['class_name'] = ['required', 'string', 'max:50'];
            $rules['major_id'] = ['required', 'exists:majors,id'];
        } elseif ($borrowerType === BorrowerType::GURU || ($borrowerType === null && $user->birth_date === null)) {
            $rules['degree'] = ['nullable', 'string', 'max:100'];
        } elseif ($borrowerType === BorrowerType::STAF) {
            $rules['work_unit'] = ['required', 'string', 'max:255'];
        } elseif ($borrowerType !== null) {
            abort(403, 'Kategori profil tidak dikenali. Hubungi pengelola sekolah.');
        }

        $data = $request->validate($rules);

        $user->name = $data['name'];

        if ($user->birth_date === null) {
            $user->birth_date = Carbon::parse($data['birth_date'])->startOfDay();
        }

        if ($borrowerType === BorrowerType::SISWA) {
            $user->class_name = $data['class_name'] ?? null;
            $user->major_id = $data['major_id'] ?? null;
        } elseif ($borrowerType === BorrowerType::GURU) {
            $user->degree = $data['degree'] ?? null;
        } elseif ($borrowerType === BorrowerType::STAF) {
            $user->work_unit = $data['work_unit'];
        }

        if (! $user->profileIsComplete()) {
            return back()->withErrors(['profile' => 'Lengkapi seluruh data wajib sebelum melanjutkan.'])->withInput();
        }

        $user->profile_completed = true;
        $user->profile_completed_at = now();
        $user->save();

        $request->session()->forget('url.intended');

        return redirect()->route('dashboard')->with('success', 'Profil berhasil dilengkapi.');
    }
}
