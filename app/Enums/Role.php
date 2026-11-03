<?php

namespace App\Enums;

enum Role: string
{
    case SUPER_ADMIN = 'SUPER_ADMIN';
    case ADMIN = 'ADMIN';
    case EDITOR_VALIDATOR = 'EDITOR_VALIDATOR';
    case OPERATOR = 'OPERATOR';
    case MEMBER = 'MEMBER';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Admin',
            self::EDITOR_VALIDATOR => 'Editor/Validator',
            self::OPERATOR => 'Operator',
            self::MEMBER => 'Member',
        };
    }

    /** Label singkat untuk dokumen resmi (kop & blok tanda tangan). */
    public function shortLabel(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Admin',
            self::EDITOR_VALIDATOR => 'Editor/Validator',
            self::OPERATOR => 'Operator',
            self::MEMBER => 'Member',
        };
    }

    /** Role yang boleh dikelola oleh Super Admin (least privilege). */
    public function isAssignableByAccountAdmin(): bool
    {
        return $this !== self::SUPER_ADMIN;
    }

    /** @return list<self> */
    public static function values(): array
    {
        return [self::SUPER_ADMIN, self::ADMIN, self::EDITOR_VALIDATOR, self::OPERATOR, self::MEMBER];
    }
}
