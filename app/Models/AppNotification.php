<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    /** Sesuai skema PRD: tabel notifications_app. */
    protected $table = 'notifications_app';

    protected $fillable = ['user_id', 'type', 'message', 'link', 'read_at'];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
