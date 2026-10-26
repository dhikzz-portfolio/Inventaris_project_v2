<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            Informasi Profil
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Nama, gelar, email, dan data kategori dapat Anda perbarui sendiri. Username, role, dan hak istimewa hanya diatur administrator sekolah.
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

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-input-label for="degree" :value="__('Gelar (khusus guru, opsional)')" />
                <x-text-input id="degree" name="degree" type="text" class="mt-1 block w-full" :value="old('degree', $user->degree)" placeholder="S.Kom, S.Pd" />
                <x-input-error class="mt-2" :messages="$errors->get('degree')" />
            </div>
            <div>
                <x-input-label for="email" :value="__('Email Gmail')" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" placeholder="nama@gmail.com" />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />
            </div>
        </div>

        @if ($user->isPeminjam())
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="borrower_type" :value="__('Kategori pengguna')" />
                    <select id="borrower_type" name="borrower_type" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                        <option value="">— pilih kategori —</option>
                        @foreach (\App\Enums\BorrowerType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(old('borrower_type', $user->borrower_type?->value) === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-gray-400">Perubahan kategori tidak menambah role atau hak istimewa.</p>
                    <x-input-error class="mt-2" :messages="$errors->get('borrower_type')" />
                </div>
                <div>
                    <x-input-label for="class_name" :value="__('Kelas (siswa)')" />
                    <x-text-input id="class_name" name="class_name" type="text" class="mt-1 block w-full" :value="old('class_name', $user->class_name)" placeholder="X RPL 1" />
                    <x-input-error class="mt-2" :messages="$errors->get('class_name')" />
                </div>
            </div>
        @endif

        <div>
            <x-input-label for="username" :value="__('Username')" />
            <x-text-input id="username" name="username" type="text" class="mt-1 block w-full bg-gray-50" :value="$user->username" readonly />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-input-label :value="__('Role')" />
                <div class="mt-1 px-3 py-2 rounded-lg bg-gray-50 border border-gray-200 text-sm text-gray-700">
                    {{ \App\Enums\Role::tryFrom($user->role)?->label() ?? $user->role }}
                </div>
            </div>
            <div>
                <x-input-label :value="__('Jurusan')" />
                <div class="mt-1 px-3 py-2 rounded-lg bg-gray-50 border border-gray-200 text-sm text-gray-700">
                    {{ $user->major?->name ?? '-' }}
                </div>
            </div>
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
