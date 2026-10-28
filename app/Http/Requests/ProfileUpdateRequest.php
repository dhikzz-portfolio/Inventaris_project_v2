<?php

namespace App\Http\Requests;

use App\Enums\BorrowerType;
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
            'borrower_type' => ['nullable', Rule::in(BorrowerType::values())],
            'class_name' => ['nullable', 'string', 'max:50', 'required_if:borrower_type,SISWA'],
            'major_id' => ['nullable', 'exists:majors,id'],
        ];
    }
}
