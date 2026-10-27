<x-guest-layout
    heading="Lengkapi Profil"
    subheading="Anda wajib melengkapi profil sebelum dapat mengakses dashboard."
    pageTitle="Lengkapi Profil"
>
    <x-auth-session-status class="mb-5" :status="session('status')" />

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc space-y-1 ps-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('profile.complete.store') }}" class="space-y-4 sm:space-y-5" id="profile-complete-form">
        @csrf

        <x-auth-input id="name" label="Nama lengkap *" :value="old('name', $user->name)" placeholder="Nama sesuai identitas" required />

        <div>
            <label for="birth_date" class="mb-2 block text-sm font-semibold text-slate-700">Tanggal lahir *</label>
            <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date', $user->birth_date?->format('Y-m-d')) }}" required
                   class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3.5 text-base shadow-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
        </div>

        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700">Kategori pengguna *</label>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                @foreach ($borrowerTypes as $type)
                    <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm font-medium text-slate-700 transition has-[:checked]:border-brand-500 has-[:checked]:ring-2 has-[:checked]:ring-brand-500/20">
                        <input type="radio" name="borrower_type" value="{{ $type->value }}"
                               class="text-brand-600 focus:ring-brand-500"
                               data-borrower-type
                               @checked(old('borrower_type', $user->borrower_type?->value) === $type->value) required>
                        <span>{{ $type->label() }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div id="degree-field">
            <x-auth-input id="degree" label="Gelar (jika ada)" :value="old('degree', $user->degree)" placeholder="Contoh: S.Pd, M.Pd" />
        </div>

        <div id="class-field" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="class_name" class="mb-2 block text-sm font-semibold text-slate-700">Kelas *</label>
                <input id="class_name" name="class_name" type="text" value="{{ old('class_name', $user->class_name) }}"
                       placeholder="Contoh: X RPL 1"
                       class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3.5 text-base shadow-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
            </div>
            <div>
                <label for="major_id" class="mb-2 block text-sm font-semibold text-slate-700">Jurusan</label>
                <select id="major_id" name="major_id" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3.5 text-base shadow-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
                    <option value="">— pilih jurusan —</option>
                    @foreach ($majors as $major)
                        <option value="{{ $major->id }}" @selected(old('major_id', $user->major_id) == $major->id)>{{ $major->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div id="work-unit-field">
            <x-auth-input id="work_unit" label="Unit Kerja / Informasi terkait" :value="old('work_unit', $user->work_unit)" placeholder="Masukkan informasi yang relevan" />
        </div>

        <x-auth-button label="Simpan dan Lanjutkan" />
    </form>

    <script>
        (function () {
            const form = document.getElementById('profile-complete-form');
            if (!form) return;

            const degree = document.getElementById('degree-field');
            const classField = document.getElementById('class-field');
            const classInput = document.getElementById('class_name');
            const workUnit = document.getElementById('work-unit-field');

            function sync() {
                const type = form.querySelector('[data-borrower-type]:checked')?.value;
                const isGuru = type === 'GURU';
                const isSiswa = type === 'SISWA';
                const isStaf = type === 'STAF';

                degree.style.display = (isGuru) ? '' : 'none';
                classField.style.display = (isSiswa) ? '' : 'none';
                if (classInput) classInput.required = isSiswa;
                workUnit.style.display = (isStaf) ? '' : 'none';
            }

            form.querySelectorAll('[data-borrower-type]').forEach((input) => {
                input.addEventListener('change', sync);
            });

            sync();
        })();
    </script>
</x-guest-layout>
