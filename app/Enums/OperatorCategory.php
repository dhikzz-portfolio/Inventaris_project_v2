<?php

namespace App\Enums;

/**
 * Kategori identitas Operator. Bukan dasar penentuan permission: seluruh
 * kategori memakai role OPERATOR dan hak akses yang sama.
 */
enum OperatorCategory: string
{
    case LABORAN = 'LABORAN';
    case K3 = 'K3';

    public function label(): string
    {
        return match ($this) {
            self::LABORAN => 'Laboran',
            self::K3 => 'K3',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
