<?php

namespace App\Http\Requests;

use App\Rules\GmailAddress;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Profil hanya boleh mengubah data identitas. Role, role Spatie, dan
     * username sengaja tidak termasuk: perubahan hak istimewa hanya lewat
     * halaman administrasi pengguna oleh Wakasek.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'degree' => ['nullable', 'string', 'max:100'],
            'email' => [
                'nullable',
                'string',
                'lowercase',
                'email',
                'max:255',
                // Akun lama boleh mempertahankan email non-Gmail; perubahan
                // alamat tetap wajib memakai Gmail.
                function (string $attribute, mixed $value, Closure $fail): void {
                    $current = $this->user()?->email;

                    if ($value === null || $current === null || strcasecmp((string) $value, (string) $current) === 0) {
                        return;
                    }

                    (new GmailAddress)->validate($attribute, $value, $fail);
                },
                Rule::unique('users', 'email')->ignore($this->user()->id),
            ],
            'class_name' => ['nullable', 'string', 'max:50'],
            'work_unit' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'major_id' => ['nullable', 'exists:majors,id'],
        ];
    }
}
