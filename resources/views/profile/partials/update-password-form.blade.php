<section>
    <header class="space-y-1">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
            Ubah Kata Sandi
        </h2>

        <p class="text-sm leading-relaxed text-slate-500 dark:text-slate-400">
            Gunakan kata sandi yang kuat dan jangan bagikan kepada orang lain.
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" value="Kata Sandi Saat Ini" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" value="Kata Sandi Baru" />
            <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" value="Konfirmasi Kata Sandi Baru" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Simpan Kata Sandi</x-primary-button>

            @if (session('status') === 'password-updated')
                <p role="status"
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-green-700 dark:text-green-300"
                >
                    <span class="material-icons-round text-base" aria-hidden="true">check_circle</span>
                    Kata sandi berhasil diperbarui.
                </p>
            @endif
        </div>
    </form>
</section>
