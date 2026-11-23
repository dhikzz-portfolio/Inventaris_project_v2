@php
    $hasPasswordErrors = $errors->updatePassword->any();
@endphp

<section x-data="{ confirmOpen: false, showForm: {{ $hasPasswordErrors ? 'true' : 'false' }} }">
    <header class="space-y-1">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
            Ubah Kata Sandi
        </h2>

        <p class="text-sm leading-relaxed text-slate-500 dark:text-slate-400">
            Perubahan kata sandi memerlukan konfirmasi dan verifikasi kata sandi lama.
        </p>
    </header>

    @if (session('status') === 'password-updated')
        <div role="status" class="mt-4 flex items-center gap-1.5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            <span class="material-icons-round text-base" aria-hidden="true">check_circle</span>
            Kata sandi berhasil diperbarui.
        </div>
    @endif

    {{-- Tahap 1: peringatan sebelum formulir dibuka. --}}
    <div x-show="!confirmOpen && !showForm" x-cloak class="mt-6">
        <button type="button" @click="confirmOpen = true"
                class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <span class="material-icons-round text-base" aria-hidden="true">lock_reset</span>
            Ubah Kata Sandi
        </button>
    </div>

    {{-- Modal konfirmasi: bukan pengganti verifikasi kata sandi lama. --}}
    <div x-show="confirmOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" role="dialog" aria-modal="true" aria-labelledby="password-confirm-title">
        <div class="w-full max-w-md rounded-2xl border border-sky-100 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
            <h3 id="password-confirm-title" class="text-base font-bold text-slate-900 dark:text-slate-100">Ubah kata sandi?</h3>
            <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                Anda akan mengganti kata sandi akun ini. Anda wajib memasukkan kata sandi lama sebelum kata sandi baru disimpan.
            </p>
            <div class="mt-4 flex items-center justify-end gap-2">
                <button type="button" @click="confirmOpen = false"
                        class="min-h-10 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">
                    Batal
                </button>
                <button type="button" @click="confirmOpen = false; showForm = true"
                        class="min-h-10 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                    Lanjutkan
                </button>
            </div>
        </div>
    </div>

    {{-- Tahap 2: verifikasi kata sandi lama oleh backend. --}}
    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6" x-show="showForm" x-cloak>
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" value="Kata Sandi Lama *" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" required />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" value="Kata Sandi Baru *" />
            <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" required minlength="8" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" value="Konfirmasi Kata Sandi Baru *" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" required minlength="8" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Simpan Kata Sandi</x-primary-button>
            <button type="button" @click="showForm = false"
                    class="text-sm font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                Batal
            </button>
        </div>
    </form>
</section>
