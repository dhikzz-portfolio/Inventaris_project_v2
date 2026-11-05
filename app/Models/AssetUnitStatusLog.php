<?php

namespace App\Models;

use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat status operasional unit. Setiap perubahan manual maupun otomatis
 * menyimpan status sebelum/sesudah, sumber, alasan, pelaku, dan waktu sehingga
 * perubahan manual tidak hilang ketika transaksi berikutnya memperbarui status.
 */
class AssetUnitStatusLog extends Model
{
    public const SOURCE_MANUAL = 'MANUAL';

    public const SOURCE_TRANSACTION = 'TRANSAKSI';

    public const SOURCE_DISTRIBUTION = 'DISTRIBUSI';

    protected $fillable = [
        'asset_unit_id', 'from_status', 'to_status', 'source', 'reason', 'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => UnitStatus::class,
            'to_status' => UnitStatus::class,
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(AssetUnit::class, 'asset_unit_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
