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
     * kategori pengguna (borrower_type) sengaja tidak termasuk: perubahan
     * hak istimewa dan kategori hanya lewat halaman administrasi pengguna
     * oleh Super Admin / Wakasek.
     *
     * Validasi lapangan mengikuti kategori pengguna yang tersimpan di
     * database, bukan kategori yang dikirim dari browser.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $isSiswa = $user?->borrower_type === BorrowerType::SISWA;

        $rules = [
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
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
            'work_unit' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'major_id' => ['nullable', 'exists:majors,id'],
            'class_name' => ['nullable', 'string', 'max:50'],
        ];

        if ($isSiswa) {
            // Siswa: kelas & jurusan wajib bila belum terisi; gelar tidak
            // relevan dan ditolak bila dikirim paksa lewat request manual.
            $rules['class_name'] = $user?->class_name === null
                ? ['required', 'string', 'max:50']
                : ['nullable', 'string', 'max:50'];
            $rules['major_id'] = $user?->major_id === null
                ? ['required', 'exists:majors,id']
                : ['nullable', 'exists:majors,id'];
            $rules['degree'] = ['prohibited'];
        } else {
            // Guru/Staf/kategori non-Siswa: kelas & jurusan tidak berlaku.
            $rules['class_name'] = ['prohibited'];
        }

        // Kategori pengguna tidak pernah bisa diubah lewat edit profil.
        $rules['borrower_type'] = ['prohibited'];

        return $rules;
    }

    /**
     * Pesan galat khusus untuk aturan kondisional.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'borrower_type.prohibited' => 'Kategori pengguna tidak dapat diubah melalui edit profil. Hubungi administrator sekolah.',
            'degree.prohibited' => 'Gelar tidak berlaku untuk kategori Siswa.',
            'class_name.prohibited' => 'Kelas hanya berlaku untuk kategori Siswa.',
        ];
    }
}
