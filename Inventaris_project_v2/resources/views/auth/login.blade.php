@php
    $emailErrors = collect($errors->get('email'))
        ->map(fn (string $message) => $message === 'auth.failed'
            ? 'Email atau kata sandi tidak sesuai.'
            : $message)
        ->all();
@endphp

<x-guest-layout
    heading="Selamat datang kembali"
    subheading="Masuk untuk mengelola inventaris dan aset sekolah."
    pageTitle="Masuk"
>
    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4 sm:space-y-5" data-auth-form>
        @csrf

        <x-auth-input
            id="email"
            label="Email"
            type="email"
            :value="old('email')"
            placeholder="Masukkan email"
            autocomplete="email"
            autofocus
            icon="mail"
            :error="$emailErrors[0] ?? null"
        />

        <x-auth-input
            id="password"
            label="Kata sandi"
            type="password"
            placeholder="Masukkan kata sandi"
            autocomplete="current-password"
            toggleable
            icon="lock"
        />

        <label for="remember_me" class="inline-flex items-center gap-2.5 text-sm text-slate-600 dark:text-slate-300 sm:gap-3 sm:text-base">
            <input
                id="remember_me"
                name="remember"
                type="checkbox"
                class="h-5 w-5 rounded border-slate-300 text-brand-600 shadow-sm focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:border-slate-600 dark:focus:ring-offset-slate-900"
            >
            <span class="select-none">Ingat saya</span>
        </label>

        <x-auth-button label="Masuk" />
    </form>

    <p class="mt-5 text-center text-xs leading-relaxed text-slate-400 dark:text-slate-500">
        Gunakan akun sekolah Anda. Akun dibuat oleh Super Admin; hubungi administrator jika mengalami kendala masuk.
    </p>
</x-guest-layout>
