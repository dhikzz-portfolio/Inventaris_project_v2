<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamageReportPhoto extends Model
{
    protected $fillable = ['damage_report_id', 'path', 'original_name'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(DamageReport::class, 'damage_report_id');
    }
}
