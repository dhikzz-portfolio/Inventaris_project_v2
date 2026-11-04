<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionChangeLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'role_name',
        'actor_id',
        'reason',
        'before',
        'after',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'before' => 'json',
            'after' => 'json',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Permission yang dicabut dari role sebelum perubahan disimpan.
     *
     * @return list<array{module: string, feature: string, actions: list<string>}>
     */
    public function revokedPermissionRows(): array
    {
        if (! $this->before) {
            return [];
        }

        $rows = [];

        foreach ($this->before as $module => $features) {
            foreach ($features as $feature => $actions) {
                $rows[] = [
                    'module' => $module,
                    'feature' => $feature,
                    'actions' => is_array($actions) ? $actions : [],
                ];
            }
        }

        return $rows;
    }

    /**
     * Permission yang diberikan kepada role setelah perubahan disimpan.
     *
     * @return list<array{module: string, feature: string, actions: list<string>}>
     */
    public function grantedPermissionRows(): array
    {
        if (! $this->after) {
            return [];
        }

        $rows = [];

        foreach ($this->after as $module => $features) {
            foreach ($features as $feature => $actions) {
                $rows[] = [
                    'module' => $module,
                    'feature' => $feature,
                    'actions' => is_array($actions) ? $actions : [],
                ];
            }
        }

        return $rows;
    }
}
