<?php

namespace App\Models;

use App\Enums\Condition;
use App\Enums\DamageClassification;
use App\Enums\DamageReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DamageReport extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'asset_unit_id', 'acquisition_allocation_id', 'reported_by', 'classification', 'previous_condition',
        'reported_condition', 'description', 'photo_path', 'status',
        'handled_by', 'handled_at', 'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'classification' => DamageClassification::class,
            'previous_condition' => ConditionCast::class,
            'reported_condition' => ConditionCast::class,
            'status' => DamageReportStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['classification', 'status', 'description', 'resolution_note'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(AssetUnit::class, 'asset_unit_id');
    }

    /** Alokasi pengadaan sumber unit rusak (bila laporan dibuat otomatis). */
    public function acquisitionAllocation(): BelongsTo
    {
        return $this->belongsTo(AcquisitionAllocation::class, 'acquisition_allocation_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DamageReportLog::class)->latest();
    }

    /** Beberapa foto bukti per laporan; laporan lama tetap memakai photo_path. */
    public function photos(): HasMany
    {
        return $this->hasMany(DamageReportPhoto::class)->orderBy('id');
    }

    /**
     * Cakupan laporan mengikuti cakupan unit: Super Admin seluruh sekolah,
     * Ketua Laboran seluruh laboratorium, Laboran/K3 ruangan yang ditugaskan.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        // Super Admin memantau seluruh data; Admin mengelola seluruh sekolah.
        if ($user === null || $user->isSuperAdmin() || $user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('unit', fn (Builder $unit) => $unit->visibleTo($user));
    }

    public function scopeOfClassification(Builder $query, ?string $classification): Builder
    {
        return $classification === null || $classification === ''
            ? $query
            : $query->where('classification', $classification);
    }

    public function isHeavy(): bool
    {
        return $this->classification === DamageClassification::RUSAK_BERAT;
    }
}
