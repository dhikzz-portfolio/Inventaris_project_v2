<x-guest-layout>
    <div class="mb-4 text-sm text-slate-600">
        {{ __('Terima kasih sudah mendaftar. Sebelum mulai, silakan verifikasi alamat email Anda dengan mengeklik tautan yang baru saja kami kirim. Bila emailnya tidak masuk, kami bisa kirim ulang.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ __('Tautan verifikasi baru sudah dikirim ke alamat email yang Anda daftarkan.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Kirim ulang email verifikasi') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-slate-600 hover:text-slate-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                {{ __('Keluar') }}
            </button>
        </form>
    </div>
</x-guest-layout>
