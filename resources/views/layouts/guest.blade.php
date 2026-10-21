<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $attributes['pageTitle'] ?? 'Inventaris & Aset Sekolah' }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <meta name="theme-color" content="#1f6fba">
    </head>

    <body class="min-h-dvh overflow-x-hidden bg-slate-50 font-sans text-slate-900 antialiased">

    @php
        // Logo mengikuti unggahan di menu Pengaturan Instansi, dengan cadangan
        // berkas bawaan agar halaman login tetap tampil bila pengaturan kosong.
        try {
            $institution = \App\Models\InstitutionSetting::query()->first();
        } catch (\Throwable $e) {
            $institution = null;
        }

        $loginLogo = $institution?->logo_path
            ? asset('storage/'.$institution->logo_path)
            : (file_exists(public_path('images/logo.png')) ? asset('images/logo.png') : null);
        $loginLogoAlt = 'Logo '.($institution?->institution_name ?? config('app.name', 'Inventaris Sekolah'));
    @endphp

    <main class="safe-area-bottom min-h-dvh">

        {{-- Mobile Branding (Hidden on lg+) --}}
        <div class="px-5 pt-4 lg:hidden">
            <div class="flex items-center gap-4">
                <div class="inline-flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-brand-600 shadow-lg">
                    @if ($loginLogo)
                        <img src="{{ $loginLogo }}" alt="{{ $loginLogoAlt }}" class="h-14 w-14 object-contain" />
                    @else
                        <span class="material-icons-round text-4xl text-white" aria-hidden="true">school</span>
                    @endif
                </div>
                <div class="min-w-0">
                    <p class="truncate text-lg font-bold leading-tight tracking-tight text-slate-900">Inventaris Sekolah</p>
                    <p class="mt-1 truncate text-xs text-slate-500">Kelola aset dengan rapi &amp; terlacak</p>
                </div>
            </div>
        </div>

        {{-- Desktop Branding Sidebar --}}
        <aside class="hidden lg:block lg:fixed lg:inset-y-0 lg:left-0 lg:w-1/2 lg:max-w-[480px]">
            <div class="relative h-full bg-gradient-to-br from-brand-600 via-brand-700 to-brand-800 overflow-hidden">
                {{-- Decorative blobs --}}
                <div class="pointer-events-none absolute -right-32 top-20 h-72 w-72 rounded-full bg-white/10 blur-3xl" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -bottom-40 -left-40 hidden h-96 w-96 rounded-full bg-white/5 lg:block" aria-hidden="true"></div>

                {{-- Brand Content --}}
                <div class="relative z-10 flex h-full flex-col px-8 py-10 sm:px-12 sm:py-14 lg:px-16 lg:py-16">
                    <div class="flex items-center gap-4">
                        <div class="inline-flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white/20 backdrop-blur-sm border border-white/30 shadow-lg">
                            @if ($loginLogo)
                                <img src="{{ $loginLogo }}" alt="{{ $loginLogoAlt }}" class="h-11 w-11 object-contain" />
                            @else
                                <span class="material-icons-round text-3xl text-white" aria-hidden="true">school</span>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="text-base font-bold leading-tight text-white sm:text-lg">Inventaris Sekolah</p>
                            <p class="text-xs text-white/70 truncate">{{ config('app.name', 'SMK Negeri 1') }}</p>
                        </div>
                    </div>

                    <div class="mt-10 flex-1 flex flex-col justify-center">
                        <h2 class="text-balance text-3xl font-bold leading-[1.2] tracking-tight text-white sm:text-4xl lg:text-5xl">
                            Kelola aset sekolah
                            <br/>dengan rapi dan terlacak.
                        </h2>

                        <p class="mt-5 max-w-lg text-pretty text-base leading-relaxed text-white/85">
                            Dari pengadaan, peminjaman, pengembalian, hingga penghapusan.
                            <br/>Semua tercatat dalam satu sistem terpusat.
                        </p>

                        <ul class="mt-10 space-y-5" role="list">
                            <li class="flex items-start gap-4 text-base text-white/95">
                                <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/20 text-white"><span class="material-icons-round text-[16px]">check</span></span>
                                <span>Peminjaman multi-item dengan kalender ketersediaan</span>
                            </li>
                            <li class="flex items-start gap-4 text-base text-white/95">
                                <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/20 text-white"><span class="material-icons-round text-[16px]">check</span></span>
                                <span>Label stiker & QR Code siap cetak</span>
                            </li>
                            <li class="flex items-start gap-4 text-base text-white/95">
                                <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/20 text-white"><span class="material-icons-round text-[16px]">check</span></span>
                                <span>Laporan bulanan otomatis ke PDF & Excel</span>
                            </li>
                        </ul>
                    </div>

                    <p class="mt-12 text-sm text-white/50">
                        &copy; {{ date('Y') }} {{ config('app.name', 'Inventaris & Aset Sekolah') }}
                    </p>
                </div>
            </div>
        </aside>

        {{-- Login Form Column --}}
        <section class="relative z-10 flex items-start justify-center px-4 pb-6 pt-4 sm:px-6 sm:pb-10 sm:pt-10 lg:ml-[480px] lg:min-h-dvh lg:items-center lg:px-8 lg:py-12">
            <div class="w-full max-w-md animate-slide-up">
                <div class="mb-4 sm:mb-8">
                    <h1 class="text-balance text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $attributes['heading'] ?? 'Selamat datang kembali' }}</h1>
                    @if (! empty($attributes['subheading']))
                        <p class="mt-2 text-pretty text-sm leading-relaxed text-slate-500">{{ $attributes['subheading'] }}</p>
                    @endif
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xl shadow-slate-900/5 ring-1 ring-slate-200/50 sm:p-8 lg:shadow-2xl">
                    {{ $slot }}
                </div>

                @isset($footer)
                    <div class="mt-6 sm:mt-8">{{ $footer }}</div>
                @endisset

                <p class="mt-4 text-center text-xs text-slate-400 sm:mt-10 lg:hidden">
                    &copy; {{ date('Y') }} {{ config('app.name', 'Inventaris & Aset Sekolah') }}
                </p>
            </div>
        </section>

    </main>

    <script>
        document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.getElementById(button.getAttribute('data-password-toggle'));
                var icon = button.querySelector('[data-password-icon]');

                if (! input) {
                    return;
                }

                var showPassword = input.type === 'password';

                input.type = showPassword ? 'text' : 'password';
                button.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
                button.setAttribute('aria-label', showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');

                if (icon) {
                    icon.textContent = showPassword ? 'visibility_off' : 'visibility';
                }
            });
        });

        document.querySelectorAll('[data-auth-form]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var button = form.querySelector('[data-submit-button]');
                var label = form.querySelector('[data-submit-label]');
                var loading = form.querySelector('[data-submit-loading]');

                if (! button) {
                    return;
                }

                button.disabled = true;
                button.classList.add('cursor-wait', 'opacity-75');

                if (label && loading) {
                    label.style.display = 'none';
                    loading.style.display = 'inline-flex';
                }
            });
        });
    </script>
</body>
</html>