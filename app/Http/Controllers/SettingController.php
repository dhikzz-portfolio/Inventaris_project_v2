<?php

namespace App\Http\Controllers;

use App\Models\InstitutionSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', ['setting' => InstitutionSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = InstitutionSetting::current();

        $data = $request->validate([
            'institution_name' => ['required', 'string', 'max:255'],
            'location_code' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'headmaster_name' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('institution', 'public');
        }

        $setting->fill(Arr::except($data, ['logo']));
        $setting->save();

        activity('pengaturan')->performedOn($setting)->causedBy($request->user())->log('Mengubah pengaturan instansi');

        return back()->with('success', 'Pengaturan instansi diperbarui (berlaku untuk label, BAST, dan laporan).');
    }
}
