<?php

namespace App\Http\Controllers;

use App\Models\InstitutionSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', ['setting' => InstitutionSetting::current()]);
    }

    /**
     * Menyajikan logo instansi dari disk public sehingga kop pratinjau, Excel,
     * dan PDF tetap memakai berkas yang sama tanpa bergantung pada symlink
     * public/storage.
     */
    public function logo(): StreamedResponse
    {
        $path = (string) InstitutionSetting::current()->logo_path;

        abort_if($path === '' || str_contains($path, '..'), 404);

        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, basename($path), [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
