<section>
    <header>
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">
            Informasi Profil
        </h2>

        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Nama, gelar, email, dan data profil dapat Anda perbarui sendiri. Kategori pengguna, role, dan hak istimewa hanya diatur administrator sekolah.
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Nama Lengkap')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @if ($user->borrower_type !== \App\Enums\BorrowerType::SISWA)
                {{-- Gelar hanya relevan untuk kategori non-Siswa (Guru/Staf/dll). --}}
                <div>
                    <x-input-label for="degree" :value="__('Gelar (khusus guru, opsional)')" />
                    <x-text-input id="degree" name="degree" type="text" class="mt-1 block w-full" :value="old('degree', $user->degree)" placeholder="S.Kom, S.Pd" />
                    <x-input-error class="mt-2" :messages="$errors->get('degree')" />
                </div>
            @endif
            <div>
                <x-input-label for="email" :value="__('Email Gmail')" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" placeholder="nama@gmail.com" />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />
            </div>
        </div>

        {{-- Kategori pengguna: hanya tampil-baca. Perubahan hanya melalui administrasi Super Admin. --}}
        <div>
            <x-input-label :value="__('Kategori pengguna')" />
            <div class="mt-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                {{ $user->borrower_type?->label() ?? 'Belum ditetapkan' }}
            </div>
            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Kategori ditetapkan oleh administrator dan tidak dapat diubah melalui halaman ini.</p>
        </div>

        @if ($user->borrower_type === \App\Enums\BorrowerType::SISWA)
            {{-- Field khusus Siswa: kelas & jurusan. --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="class_name" :value="__('Kelas')" />
                    <x-text-input id="class_name" name="class_name" type="text" class="mt-1 block w-full" :value="old('class_name', $user->class_name)" placeholder="X RPL 1" />
                    <x-input-error class="mt-2" :messages="$errors->get('class_name')" />
                </div>
                <div>
                    <x-input-label :value="__('Jurusan')" />
                    <div class="mt-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        {{ $user->major?->name ?? '-' }}
                    </div>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-input-label :value="__('Role')" />
                <div class="mt-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                    {{ \App\Enums\Role::tryFrom($user->role)?->label() ?? $user->role }}
                </div>
            </div>
            @if ($user->borrower_type !== \App\Enums\BorrowerType::SISWA)
                <div>
                    <x-input-label :value="__('Jurusan')" />
                    <div class="mt-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        {{ $user->major?->name ?? '-' }}
                    </div>
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Simpan') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >Tersimpan.</p>
            @endif
        </div>
    </form>
</section>
