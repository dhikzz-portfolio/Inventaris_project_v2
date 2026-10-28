<x-guest-layout
    heading="Daftar Akun"
    subheading="Registrasi untuk akun peminjam baru."
    pageTitle="Daftar"
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

    <form method="POST" action="{{ route('register') }}" class="space-y-4 sm:space-y-5">
        @csrf
        <input type="hidden" name="registration_token" value="{{ request()->query('token') }}">

        <x-auth-input id="name" label="Nama lengkap *" :value="old('name')" placeholder="Nama sesuai identitas" autocomplete="name" icon="badge" autofocus required />

        <div>
            <label for="birth_date" class="mb-2 block text-sm font-semibold text-slate-700">Tanggal lahir *</label>
            <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date') }}" required
                   class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3.5 text-base shadow-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
        </div>

        <x-auth-input id="email" label="Email Gmail *" type="email" :value="old('email')" placeholder="nama@gmail.com" autocomplete="email" icon="mail"
                      pattern="[A-Za-z0-9._%+\-]+@[Gg][Mm][Aa][Ii][Ll]\.[Cc][Oo][Mm]" required />
        <p class="-mt-2 text-xs text-slate-400">Wajib menggunakan domain <span class="font-mono">gmail.com</span>.</p>

        <x-auth-input id="password" label="Kata sandi *" type="password" placeholder="Minimal 8 karakter" autocomplete="new-password" toggleable icon="lock" required />
        <x-auth-input id="password_confirmation" label="Konfirmasi kata sandi *" type="password" placeholder="Ulangi kata sandi" autocomplete="new-password" toggleable icon="lock" required />

        <x-auth-button label="Daftar" />
    </form>

    <p class="mt-5 text-center text-sm text-slate-500">
        Sudah punya akun?
        <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline">Masuk di sini</a>
    </p>
</x-guest-layout>
