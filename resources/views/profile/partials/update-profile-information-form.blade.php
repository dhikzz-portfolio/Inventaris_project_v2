<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            Informasi Profil
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Username, role, dan jurusan diatur oleh administrator sekolah.
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
