<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionSetting extends Model
{
    protected $fillable = [
        'institution_name', 'location_code', 'logo_path',
        'address', 'headmaster_name',
    ];

    /**
     * URL logo instansi. Disajikan lewat rute aplikasi, bukan langsung dari
     * public/storage, agar tetap tampil ketika symlink storage belum tersedia
     * (misalnya pada instalasi lokal Windows).
     */
    public function logoUrl(): ?string
    {
        if (blank($this->logo_path)) {
            return null;
        }

        return route('settings.logo', ['v' => $this->updated_at?->timestamp]);
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create([
            'institution_name' => config('app.name'),
            'location_code' => '00.00.00.00.00.00.00.00',
        ]);
    }
}
