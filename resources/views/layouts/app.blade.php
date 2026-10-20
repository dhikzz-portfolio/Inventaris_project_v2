<!DOCTYPE html>
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
        <meta name="theme-color" content="#d97706">

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
    <body class="font-sans antialiased bg-gray-100 text-gray-800">
        <div class="min-h-screen flex" x-data="{ menuOpen: false }">
            <!-- Sidebar (desktop: statis, mobile: drawer) -->
            <aside class="fixed inset-y-0 left-0 z-40 w-64 max-w-[85vw] bg-slate-900 text-slate-200 transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-in-out md:static md:shrink-0 overflow-y-auto overscroll-contain"
                   :class="menuOpen ? 'translate-x-0' : '-translate-x-full'">
                <div class="h-16 flex items-center gap-2 px-4 border-b border-slate-800">
                    @if (\App\Models\InstitutionSetting::current()->logo_path)
                        <img src="{{ asset('storage/'.\App\Models\InstitutionSetting::current()->logo_path) }}" alt="Logo" class="h-9 w-9 object-contain bg-white rounded">
                    @else
                        <span class="material-icons-round text-amber-400 text-3xl">school</span>
                    @endif
                    <div class="leading-tight">
                        <div class="font-bold text-white text-sm">{{ \App\Models\InstitutionSetting::current()->institution_name }}</div>
                        <div class="text-xs text-slate-400">Manajemen Inventaris &amp; Aset</div>
                    </div>
                </div>

                <nav class="mt-3 px-3 pb-24 space-y-1 overflow-y-auto" style="max-height: calc(100vh - 4rem);">
                    @include('layouts.menu')
                </nav>
            </aside>

            <!-- Overlay mobile -->
            <div x-show="menuOpen" x-transition.opacity @click="menuOpen = false"
                 class="fixed inset-0 bg-black/50 z-30 md:hidden" aria-hidden="true"></div>

            <!-- Konten -->
            <div class="flex-1 flex flex-col min-w-0">
                @include('layouts.navigation')

                @isset($header)
                    <header class="bg-white border-b border-gray-200">
                        <div class="max-w-full mx-auto py-3 sm:py-4 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="flex-1 p-3 sm:p-4 md:p-6 pb-24 md:pb-6">
                    {{ $slot }}
                </main>
            </div>

            @if (auth()->check() && auth()->user()->canApproveLoans())
                <!-- Tombol kamera mengambang untuk Laboran / pengelola (Bagian 5.9) -->
                <a href="{{ route('scan') }}"
                   class="fixed right-4 bottom-20 md:bottom-6 z-30 inline-flex items-center justify-center w-13 h-13 sm:w-14 sm:h-14 rounded-full bg-amber-600 hover:bg-amber-700 text-white shadow-lg hover:shadow-xl transition transform hover:scale-105 active:scale-95"
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
