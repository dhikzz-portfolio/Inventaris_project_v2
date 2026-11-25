<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Email registrasi harus memakai domain gmail.com.
 *
 * Validasi ini hanya memeriksa format dan domain — bukan bukti bahwa alamat
 * benar-benar dimiliki pengguna. Pembuktian kepemilikan memerlukan verifikasi
 * email melalui SMTP/tautan verifikasi yang dikonfigurasi terpisah.
 */
class GmailAddress implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! str_contains($value, '@')) {
            $fail('Alamat email wajib menggunakan format nama@gmail.com.');

            return;
        }

        [$local, $domain] = explode('@', $value, 2);

        if ($local === '' || strcasecmp($domain, 'gmail.com') !== 0) {
            $fail('Email wajib menggunakan domain gmail.com (contoh: pengguna@gmail.com).');
        }
    }
}
