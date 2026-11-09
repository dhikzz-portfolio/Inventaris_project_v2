<?php

namespace App\Casts;

use App\Enums\Condition;

/**
 * Cast kondisi unit/barang (Condition) dengan normalisasi nilai.
 */
class ConditionCast extends NormalizesBackedEnum
{
    /**
     * @return class-string<BackedEnum>
     */
    protected function enumClass(): string
    {
        return Condition::class;
    }

    protected function attribute(): string
    {
        return 'condition';
    }
}
