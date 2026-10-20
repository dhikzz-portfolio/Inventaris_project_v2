<?php

namespace App\Models;

use App\Enums\Decision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanApproval extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'loan_id', 'approver_id', 'level', 'decision',
        'note', 'signature_path', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'decision' => Decision::class,
            'decided_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
