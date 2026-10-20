<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = [
        'period', 'scope', 'scope_id', 'pdf_path', 'excel_path',
        'generated_by', 'generated_at',
    ];

    protected function casts(): array
    {
        return ['generated_at' => 'datetime'];
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
