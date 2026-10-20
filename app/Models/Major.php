<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Major extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code'];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function laborans(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'LABORAN');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
