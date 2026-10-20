<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionSetting extends Model
{
    protected $fillable = [
        'institution_name', 'location_code', 'logo_path',
        'address', 'headmaster_name',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create([
            'institution_name' => config('app.name'),
            'location_code' => '00.00.00.00.00.00.00.00',
        ]);
    }
}
