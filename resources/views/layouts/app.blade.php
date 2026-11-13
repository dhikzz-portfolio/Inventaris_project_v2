<!DOCTYPE html>
@php($institution = \App\Models\InstitutionSetting::current())
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'Inventaris Sekolah') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet" />

        <link rel="manifest" href="/manifest.json">
        <link rel="icon" href="/icons/icon-192.png" type="image/png">
        <link rel="apple-touch-icon" href="/icons/icon-192.png">
        <meta name="theme-color" content="#155dfc">

        {{-- Terapkan preferensi tema sebelum paint untuk mencegah flash. --}}
        <script>
            (function () {
                try {
                    var saved = localStorage.getItem('theme');
                    var dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                    if (dark) document.documentElement.classList.add('dark');
                } catch (e) {}
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        @stack('styles')
    </head>
    <body class="min-h-screen bg-slate-50 font-sans antialiased text-slate-800 dark:bg-slate-950 dark:text-slate-100">
        <div class="min-h-screen" x-data="{ menuOpen: false }">
            <aside id="main-menu"
                   class="fixed inset-y-0 left-0 z-40 flex w-64 max-w-[85vw] -translate-x-full flex-col border-r border-slate-200 bg-white text-slate-700 shadow-xl transition-transform duration-200 ease-in-out dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 md:w-60 md:translate-x-0 md:shadow-none lg:w-64"
                   :class="menuOpen ? 'translate-x-0' : '-translate-x-full'"
                   @keydown.escape.window="menuOpen = false">
                <div class="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-slate-100 px-4 dark:border-slate-800">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-50 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
                            @if ($institution->logoUrl())
                                <img src="{{ $institution->logoUrl() }}" alt="Logo {{ $institution->institution_name }}" class="h-full w-full object-contain p-1">
                            @else
                                <span class="material-icons-round text-xl text-brand-600" aria-hidden="true">school</span>
                            @endif
                        </div>
                        <div class="min-w-0 leading-tight">
                            <div class="truncate text-xs font-bold text-slate-900 dark:text-white">{{ $institution->institution_name }}</div>
                            <div class="mt-1 truncate text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Inventaris Sekolah</div>
                        </div>
                    </div>
                    <button type="button" @click="menuOpen = false" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-brand-500 md:hidden dark:hover:bg-slate-800" aria-label="Tutup menu">
                        <span class="material-icons-round" aria-hidden="true">close</span>
                    </button>
                </div>

                <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4 pb-24 md:pb-5" aria-label="Menu utama">
                    @include('layouts.menu')
                </nav>
            </aside>

            <div x-cloak x-show="menuOpen" x-transition.opacity @click="menuOpen = false"
                 class="fixed inset-0 z-30 bg-slate-950/40 backdrop-blur-[1px] md:hidden" aria-hidden="true"></div>

            <div class="flex min-h-screen w-full min-w-0 flex-col md:pl-60 lg:pl-64">
                @include('layouts.navigation')

                @isset($header)
                    <header @class([
                        'border-b border-slate-200 dark:border-slate-800',
                        'bg-slate-50 dark:bg-slate-950' => request()->routeIs('dashboard'),
                        'bg-white dark:bg-slate-900' => ! request()->routeIs('dashboard'),
                    ])>
                        <div class="mx-auto w-full max-w-7xl px-4 py-4 sm:px-6 md:py-5 lg:px-8 2xl:max-w-[1600px]">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="flex-1 bg-slate-50 dark:bg-slate-950">
                    <div class="mx-auto w-full max-w-7xl p-4 pb-24 sm:p-6 sm:pb-24 lg:p-8 lg:pb-24 2xl:max-w-[1600px]">
                        {{ $slot }}
                    </div>
                </main>
            </div>

            @if (auth()->check() && auth()->user()->can('inventory.view'))
                <!-- Tombol kamera mengambang untuk Laboran / pengelola (Bagian 5.9) -->
                <a href="{{ route('scan') }}"
                   class="fixed bottom-20 right-4 z-30 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-600 text-white shadow-lg shadow-brand-900/20 transition hover:bg-brand-700 active:scale-95 md:hidden"
                   title="Scan QR Inventaris" aria-label="Scan QR Inventaris">
                    <span class="material-icons-round text-2xl">qr_code_scanner</span>
                </a>
            @endif

            @include('layouts.bottom-nav')

            @auth
                {{-- Status refresh otomatis: halaman memuat ulang dirinya saat data berubah. --}}
                <div data-live-refresh
                     data-version-url="{{ route('live.version') }}"
                     data-interval="15000"
                     class="fixed bottom-20 left-4 z-20 flex items-center gap-1.5 rounded-full border border-slate-200 bg-white/95 py-1 ps-2.5 pe-1 text-[11px] font-semibold text-slate-600 shadow-lg backdrop-blur transition-colors md:bottom-5 dark:border-slate-700 dark:bg-slate-900/95 dark:text-slate-300">
                    <span class="relative flex h-2 w-2" aria-hidden="true">
                        <span data-live-ping class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-75"></span>
                        <span data-live-core class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                    </span>
                    <span data-live-label aria-live="polite">Live</span>
                    <button type="button" data-live-reload class="hidden items-center rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-bold text-white transition hover:bg-brand-700">Muat ulang</button>
                    <button type="button" data-live-toggle class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800" title="Jeda pembaruan otomatis">Jeda</button>
                </div>
            @endauth
        </div>

        @livewireScripts
        @stack('scripts')
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js').catch(() => {});
                });
            }
        </script>
    </body>
</html>
