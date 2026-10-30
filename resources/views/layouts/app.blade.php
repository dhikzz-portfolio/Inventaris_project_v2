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
    <body class="bg-brand-50/30 font-sans antialiased text-slate-800 dark:bg-slate-950 dark:text-slate-100">
        <div class="min-h-screen flex" x-data="{ menuOpen: false }">
            <aside id="main-menu"
                   class="fixed inset-y-0 left-0 z-40 flex w-64 max-w-[85vw] -translate-x-full flex-col border-r border-slate-200 bg-white text-slate-700 shadow-xl transition-transform duration-200 ease-in-out dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 sm:static sm:w-44 sm:shrink-0 sm:translate-x-0 sm:shadow-none lg:w-64"
                   :class="menuOpen ? 'translate-x-0' : '-translate-x-full'"
                   @keydown.escape.window="menuOpen = false">
                <div class="flex min-h-14 items-center justify-between gap-2 border-b border-slate-100 px-3 dark:border-slate-800">
                    <div class="flex min-w-0 items-center gap-2">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-50 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
                            @if ($institution->logo_path)
                                <img src="{{ asset('storage/'.$institution->logo_path) }}" alt="Logo {{ $institution->institution_name }}" class="h-full w-full object-contain p-0.5">
                            @else
                                <span class="material-icons-round text-lg text-brand-600" aria-hidden="true">school</span>
                            @endif
                        </div>
                        <div class="min-w-0 leading-tight">
                            <div class="truncate text-[10px] font-bold text-slate-900 dark:text-white">{{ $institution->institution_name }}</div>
                            <div class="mt-0.5 truncate text-[9px] text-slate-500 dark:text-slate-400">Inventaris Sekolah</div>
                        </div>
                    </div>
                    <button type="button" @click="menuOpen = false" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-brand-500 sm:hidden dark:hover:bg-slate-800" aria-label="Tutup menu">
                        <span class="material-icons-round" aria-hidden="true">close</span>
                    </button>
                </div>

                <nav class="flex-1 space-y-1 overflow-y-auto px-2 py-3 pb-24 sm:pb-4" aria-label="Menu utama">
                    @include('layouts.menu')
                </nav>
            </aside>

            <div x-cloak x-show="menuOpen" x-transition.opacity @click="menuOpen = false"
                 class="fixed inset-0 z-30 bg-slate-950/40 backdrop-blur-[1px] sm:hidden" aria-hidden="true"></div>

            <div class="flex-1 flex flex-col min-w-0">
                @include('layouts.navigation')

                @isset($header)
                    <header @class([
                        'border-b border-slate-200 dark:border-slate-800',
                        'bg-sky-50/80 dark:bg-slate-950/30' => request()->routeIs('dashboard'),
                        'bg-white dark:bg-slate-900' => ! request()->routeIs('dashboard'),
                    ])>
                        <div class="mx-auto max-w-full px-4 py-3 sm:px-5 sm:py-2 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="flex-1 bg-brand-50/30 p-4 pb-24 sm:p-5 sm:pb-24 md:p-6 dark:bg-slate-950">
                    {{ $slot }}
                </main>
            </div>

            @if (auth()->check() && auth()->user()->canApproveLoans())
                <!-- Tombol kamera mengambang untuk Laboran / pengelola (Bagian 5.9) -->
                <a href="{{ route('scan') }}"
                   class="fixed right-4 bottom-20 z-30 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-600 text-white shadow-lg shadow-brand-900/20 transition hover:scale-105 hover:bg-brand-700 active:scale-95 md:hidden"
                   title="Scan QR Inventaris" aria-label="Scan QR Inventaris">
                    <span class="material-icons-round text-2xl">qr_code_scanner</span>
                </a>
            @endif

            @include('layouts.bottom-nav')
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
