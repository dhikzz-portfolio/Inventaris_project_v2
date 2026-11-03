<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamageReportLog extends Model
{
    protected $fillable = [
        'damage_report_id', 'user_id', 'action',
        'status_before', 'status_after', 'note',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(DamageReport::class, 'damage_report_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
