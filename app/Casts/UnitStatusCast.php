<?php

namespace App\Casts;

use App\Enums\UnitStatus;

/**
 * Cast status unit inventaris (UnitStatus) dengan normalisasi nilai.
 */
class UnitStatusCast extends NormalizesBackedEnum
{
    /**
     * @return class-string<BackedEnum>
     */
    protected function enumClass(): string
    {
        return UnitStatus::class;
    }

    protected function attribute(): string
    {
        return 'status';
    }
}
