<?php

namespace App\Casts;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use InvalidArgumentException;

/**
 * Cast backing-enum kolom agar nilai yang disimpan selalu berasal dari backing
 * value enum yang valid, bukan label tampilan (mis. "Baik", "Aktif") atau nilai
 * arbitrer yang bisa memicu ValueError / error 500 saat dibaca kembali.
 */
abstract class NormalizesBackedEnum implements CastsAttributes
{
    /**
     * Kelas enum yang diharapkan.
     *
     * @return class-string<BackedEnum>
     */
    abstract protected function enumClass(): string;

    /**
     * Nama atribut untuk pesan error.
     */
    abstract protected function attribute(): string;

    /**
     * @param  mixed  $value
     * @return BackedEnum|null
     */
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return null;
        }

        $enumClass = $this->enumClass();

        if ($value instanceof BackedEnum) {
            if (get_class($value) === $enumClass) {
                return $value;
            }

            $this->raiseWrongEnum($value);
        }

        if (is_string($value) || is_int($value)) {
            $backing = (string) $value;
            $enum = $enumClass::tryFrom($backing);

            if ($enum !== null) {
                return $enum;
            }

            $this->raiseInvalidBacking($backing);
        }

        $this->raiseWrongType($value);
    }

    /**
     * @param  mixed  $value
     * @return string|null
     */
    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return null;
        }

        $enumClass = $this->enumClass();

        if ($value instanceof BackedEnum) {
            if (get_class($value) === $enumClass) {
                return $value->value;
            }

            $this->raiseWrongEnum($value);
        }

        if (is_string($value) || is_int($value)) {
            $backing = (string) $value;
            $enum = $enumClass::tryFrom($backing);

            if ($enum !== null) {
                return $enum->value;
            }

            $this->raiseInvalidBacking($backing);
        }

        $this->raiseWrongType($value);
    }

    private function raiseWrongEnum(BackedEnum $value): void
    {
        throw new InvalidArgumentException(
            sprintf('Kolom %s hanya menerima %s, bukan %s.', $this->attribute(), $this->enumClass(), get_class($value))
        );
    }

    private function raiseInvalidBacking(string $backing): void
    {
        $cases = implode(', ', array_map(
            static fn (BackedEnum $c): string => $c->value,
            $this->enumClass()::cases()
        ));

        throw new InvalidArgumentException(
            sprintf('Kolom %s tidak valid: "%s". Yang diizinkan: %s.', $this->attribute(), $backing, $cases)
        );
    }

    private function raiseWrongType(mixed $value): void
    {
        $type = is_object($value) ? get_class($value) : gettype($value);

        throw new InvalidArgumentException(
            sprintf('Kolom %s harus berupa enum %s, label, atau value, bukan %s.', $this->attribute(), $this->enumClass(), $type)
        );
    }
}
