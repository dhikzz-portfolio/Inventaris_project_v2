<x-guest-layout
    heading="Buat akun peminjam"
    subheading="Registrasi mandiri hanya untuk akun peminjam. Role khusus ditetapkan administrator."
    pageTitle="Daftar"
>
    <x-auth-session-status class="mb-5" :status="session('status')" />

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
            <ul class="list-disc space-y-1 ps-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" class="space-y-4 sm:space-y-5" id="register-form">
        @csrf

        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700">Kategori pengguna <span class="text-red-600">*</span></label>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                @foreach ($borrowerTypes as $type)
                    <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm font-medium text-slate-700 transition has-[:checked]:border-brand-500 has-[:checked]:ring-2 has-[:checked]:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                        <input type="radio" name="borrower_type" value="{{ $type->value }}"
                               class="text-brand-600 focus:ring-brand-500"
                               data-borrower-type
                               @checked(old('borrower_type', \App\Enums\BorrowerType::SISWA->value) === $type->value) required>
                        <span>{{ $type->label() }}</span>
                    </label>
                @endforeach
            </div>
            <p class="mt-1.5 text-xs text-slate-400 dark:text-slate-500">Semua akun registrasi memperoleh role Peminjam dengan hak minimum.</p>
        </div>

        <x-auth-input id="name" label="Nama lengkap *" :value="old('name')" placeholder="Nama sesuai identitas" autocomplete="name" icon="badge" autofocus />

        <div id="degree-field">
            <x-auth-input id="degree" label="Gelar (opsional, khusus guru)" :value="old('degree')" :required="false" placeholder="Contoh: S.Kom, S.Pd" icon="school" />
        </div>

        <x-auth-input id="email" label="Email Gmail *" type="email" :value="old('email')" placeholder="nama@gmail.com" autocomplete="email" icon="mail"
                      pattern="[A-Za-z0-9._%+\-]+@[Gg][Mm][Aa][Ii][Ll]\.[Cc][Oo][Mm]" />
        <p class="-mt-2 text-xs text-slate-400 dark:text-slate-500">Wajib domain <span class="font-mono">gmail.com</span>. Verifikasi email mengikuti konfigurasi SMTP sekolah.</p>

        <x-auth-input id="username" label="Username *" :value="old('username')" placeholder="huruf/angka/titik/dash" autocomplete="username" icon="person" />

        <div id="class-field" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="class_name" class="mb-2 block text-sm font-semibold text-slate-700">Kelas <span class="text-red-600">*</span></label>
                <input id="class_name" name="class_name" type="text" value="{{ old('class_name') }}"
                       placeholder="Contoh: X RPL 1"
                       class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3.5 text-base text-slate-900 shadow-sm outline-none placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
            </div>
            <div>
                <label for="major_id" class="mb-2 block text-sm font-semibold text-slate-700">Jurusan</label>
                <select id="major_id" name="major_id" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3.5 text-base text-slate-900 shadow-sm outline-none placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">— pilih jurusan —</option>
                    @foreach ($majors as $major)
                        <option value="{{ $major->id }}" @selected(old('major_id') == $major->id)>{{ $major->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <x-auth-input id="password" label="Kata sandi *" type="password" placeholder="Minimal 8 karakter" autocomplete="new-password" toggleable icon="lock" />
        <x-auth-input id="password_confirmation" label="Konfirmasi kata sandi *" type="password" placeholder="Ulangi kata sandi" autocomplete="new-password" toggleable icon="lock" />

        <x-auth-button label="Daftar" />
    </form>

    <p class="mt-5 text-center text-sm text-slate-500 dark:text-slate-400">
        Sudah punya akun?
        <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline dark:text-brand-300">Masuk di sini</a>
    </p>

    <script>
        (function () {
            const form = document.getElementById('register-form');
            if (!form) return;

            const degree = document.getElementById('degree-field');
            const classField = document.getElementById('class-field');
            const classInput = document.getElementById('class_name');

            function sync() {
                const type = form.querySelector('[data-borrower-type]:checked')?.value;
                const isGuru = type === 'GURU';
                const isSiswa = type === 'SISWA';

                degree.style.display = isGuru ? '' : 'none';
                classField.style.display = isSiswa ? '' : 'none';
                if (classInput) classInput.required = isSiswa;
            }

            form.querySelectorAll('[data-borrower-type]').forEach((input) => {
                input.addEventListener('change', sync);
            });

            sync();
        })();
    </script>
</x-guest-layout>
