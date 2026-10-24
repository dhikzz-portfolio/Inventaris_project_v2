<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            // Logo & identitas mengikuti unggahan di menu Pengaturan Instansi,
            // dengan cadangan konfigurasi aplikasi agar halaman login tetap tampil.
            try {
                $institution = \App\Models\InstitutionSetting::query()->first();
            } catch (\Throwable $e) {
                $institution = null;
            }

            $appName = config('app.name', 'Inventaris & Aset Sekolah');
            $institutionName = $institution?->institution_name ?: $appName;
            $brandTagline = 'Sistem Manajemen Inventaris & Aset Sekolah';
            $brandSubtitle = $institutionName !== $appName ? $appName : $brandTagline;
            $pageTitle = $attributes['pageTitle'] ?? 'Masuk';
            $heading = $attributes['heading'] ?? 'Selamat datang kembali';
            $subheading = $attributes['subheading'] ?? null;

            // PERUBAHAN: Gunakan ikon aplikasi yang tersedia jika logo instansi belum diunggah.
            $loginLogo = $institution?->logo_path
                ? asset('storage/'.$institution->logo_path)
                : asset('icons/icon-192.png');
            $loginLogoAlt = 'Logo '.$institutionName;
        @endphp

        <title>{{ $pageTitle }} — {{ $institutionName }}</title>
        <meta name="description" content="Masuk ke sistem manajemen inventaris dan aset sekolah.">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <meta name="theme-color" content="#1f6fba">
    </head>

    <body class="min-h-dvh overflow-x-hidden bg-slate-50 font-sans text-slate-900 antialiased">

    <main class="safe-area-bottom min-h-dvh">

        {{-- Mobile Branding Header (Hidden on lg+) --}}
        <div class="safe-area-top border-b border-brand-700/60 bg-brand-600 lg:hidden">
            <div class="flex w-full items-center gap-3.5 px-5 py-3">
                <div class="inline-flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-600 shadow-lg shadow-brand-900/20 ring-1 ring-white/20">
                    @if ($loginLogo)
                        <img src="{{ $loginLogo }}" alt="{{ $loginLogoAlt }}" class="h-15 w-15 object-contain" />
                    @else
                        <span class="material-icons-round text-3xl text-white" aria-hidden="true">school</span>
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <p class="line-clamp-2 break-words text-base font-bold leading-tight tracking-tight text-white">{{ $institutionName }}</p>
                    <p class="mt-0.5 truncate text-xs text-white/80">{{ $brandTagline }}</p>
                </div>
            </div>
        </div>

        {{-- Desktop Branding Sidebar --}}
        <aside class="hidden lg:block lg:fixed lg:inset-y-0 lg:left-0 lg:w-1/2 lg:max-w-[480px]">
            <div class="relative h-full overflow-hidden bg-gradient-to-br from-brand-600 via-brand-700 to-brand-900">
                {{-- Decorative blobs --}}
                <div class="pointer-events-none absolute -right-32 top-20 h-72 w-72 rounded-full bg-white/10 blur-3xl" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -bottom-40 -left-40 h-96 w-96 rounded-full bg-white/5" aria-hidden="true"></div>
                <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-white/20" aria-hidden="true"></div>

                {{-- Brand Content --}}
                <div class="relative z-10 flex h-full flex-col px-8 py-10 sm:px-12 sm:py-14 lg:px-14 lg:py-14">
                    <div class="flex items-center gap-4">
                        <div class="inline-flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-white/25 bg-white/15 shadow-lg backdrop-blur-sm">
                            @if ($loginLogo)
                                <img src="{{ $loginLogo }}" alt="{{ $loginLogoAlt }}" class="h-15 w-15 object-contain" />
                            @else
                                <span class="material-icons-round text-3xl text-white" aria-hidden="true">school</span>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="line-clamp-2 break-words text-base font-bold leading-tight text-white sm:text-lg">{{ $institutionName }}</p>
                            <p class="truncate text-xs text-white/70">{{ $brandSubtitle }}</p>
                        </div>
                    </div>

                    <div class="mt-10 flex flex-1 flex-col justify-center">
                        <span class="mb-5 inline-flex w-fit items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-white/80 ring-1 ring-white/15">
                            <span class="material-icons-round text-sm" aria-hidden="true">verified_user</span>
                            Portal Inventaris Sekolah
                        </span>

                        <h2 class="text-balance text-3xl font-bold leading-[1.15] tracking-tight text-white sm:text-4xl lg:text-[2.65rem]">
                            Kelola aset sekolah
                            <span class="block text-brand-200">dengan rapi dan terlacak.</span>
                        </h2>

                        <p class="mt-5 max-w-lg text-pretty text-base leading-relaxed text-white/85">
                            Dari pengadaan, peminjaman, pengembalian, hingga penghapusan.
                            Semua tercatat dalam satu sistem terpusat.
                        </p>

                        <ul class="mt-9 space-y-4" role="list">
                            <li class="flex items-start gap-3.5 text-[15px] leading-relaxed text-white/95">
                                <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/15 text-white ring-1 ring-white/15" aria-hidden="true">
                                    <span class="material-icons-round text-[15px]">check</span>
                                </span>
                                <span>Peminjaman multi-item dengan kalender ketersediaan</span>
                            </li>
                            <li class="flex items-start gap-3.5 text-[15px] leading-relaxed text-white/95">
                                <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/15 text-white ring-1 ring-white/15" aria-hidden="true">
                                    <span class="material-icons-round text-[15px]">check</span>
                                </span>
                                <span>Label stiker &amp; QR Code siap cetak</span>
                            </li>
                            <li class="flex items-start gap-3.5 text-[15px] leading-relaxed text-white/95">
                                <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/15 text-white ring-1 ring-white/15" aria-hidden="true">
                                    <span class="material-icons-round text-[15px]">check</span>
                                </span>
                                <span>Laporan bulanan otomatis ke PDF &amp; Excel</span>
                            </li>
                        </ul>
                    </div>

                    <p class="mt-12 text-sm text-white/50">
                        &copy; {{ date('Y') }} {{ $institutionName }}
                    </p>
                </div>
            </div>
        </aside>

        {{-- Login Form Column --}}
        <section class="relative z-10 flex items-start justify-center px-4 pb-8 pt-5 sm:px-6 sm:pb-10 sm:pt-10 lg:ml-[480px] lg:min-h-dvh lg:items-center lg:px-8 lg:py-12">
            <div class="w-full max-w-md animate-slide-up">
                <div class="mb-5 sm:mb-8 w-full flex flex-col items-center justify-center text-center">
                    <h1 class="text-balance text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $heading }}</h1>
                    @if (! empty($subheading))
                        <p class="mt-2 text-pretty text-sm leading-relaxed text-slate-500">{{ $subheading }}</p>
                    @endif
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xl shadow-slate-900/5 ring-1 ring-slate-200/60 sm:p-8 lg:shadow-2xl">
                    {{ $slot }}
                </div>

                @isset($footer)
                    <div class="mt-6 sm:mt-8">{{ $footer }}</div>
                @endisset

                <p class="mt-5 text-center text-xs text-slate-400 lg:hidden">
                    &copy; {{ date('Y') }} {{ $institutionName }}
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
