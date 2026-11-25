<?php

namespace App\Enums;

enum Decision: string
{
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';

    public function label(): string
    {
        return match ($this) {
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
        };
    }
}
