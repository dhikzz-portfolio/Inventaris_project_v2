<nav class="sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 shadow-sm shadow-slate-900/[0.02] backdrop-blur-xl safe-area-top dark:border-slate-800 dark:bg-slate-900/90 dark:shadow-black/10">
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-5 md:px-6 2xl:max-w-[1600px]">
        <div class="flex h-16 items-center justify-between gap-3">
            <div class="flex min-w-0 flex-1 items-center gap-3">
                <button @click="menuOpen = ! menuOpen"
                        :aria-expanded="menuOpen.toString()"
                        aria-controls="main-menu"
                        aria-label="Buka menu utama"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 md:hidden dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    <span class="material-icons-round" aria-hidden="true">menu</span>
                </button>
                <div class="flex min-w-0 items-center gap-2 md:hidden">
                    @if ($institution->logo_path)
                        <img src="{{ asset('storage/'.$institution->logo_path) }}" alt="" class="h-8 w-8 shrink-0 rounded-lg bg-white object-contain p-0.5 ring-1 ring-slate-200 dark:ring-slate-700">
                    @else
                        <span class="material-icons-round shrink-0 text-xl text-brand-600" aria-hidden="true">school</span>
                    @endif
                    <span class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $institution->institution_name }}</span>
                </div>
                <form action="{{ route('catalogs.index') }}" method="GET" role="search"
                      class="hidden h-10 w-full max-w-sm items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3 text-slate-500 transition focus-within:border-brand-300 focus-within:bg-white focus-within:ring-2 focus-within:ring-brand-100 md:flex dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:focus-within:border-brand-700 dark:focus-within:bg-slate-950 dark:focus-within:ring-brand-900/50">
                    <label for="global-catalog-search" class="material-icons-round text-lg text-slate-400" aria-hidden="true">search</label>
                    <input id="global-catalog-search" type="search" name="search" value="{{ request()->query('search') }}"
                           placeholder="Cari barang atau kode" aria-label="Cari barang atau kode"
                           class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-700 outline-none placeholder:text-slate-400 focus:ring-0 dark:text-slate-100">
                </form>
            </div>

            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                <button type="button" x-data="{ dark: document.documentElement.classList.contains('dark') }"
                        @click="dark = !dark; document.documentElement.classList.toggle('dark', dark); localStorage.setItem('theme', dark ? 'dark' : 'light')"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                        :title="dark ? 'Mode terang' : 'Mode gelap'" aria-label="Ganti tema">
                    <span class="material-icons-round" aria-hidden="true" x-text="dark ? 'light_mode' : 'dark_mode'">dark_mode</span>
                </button>

                @php($unread = auth()->user()?->unreadNotifications()->count() ?? 0)
                <a href="{{ route('notifications.index') }}" class="relative inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800" aria-label="Notifikasi" title="Notifikasi">
                    <span class="material-icons-round" aria-hidden="true">notifications</span>
                    @if ($unread > 0)
                        <span class="absolute right-1 top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[9px] font-bold text-white">{{ $unread > 99 ? '99+' : $unread }}</span>
                    @endif
                </a>

                <x-dropdown align="right" width="56">
                    <x-slot name="trigger">
                        <button class="flex max-w-[12rem] items-center gap-2 rounded-xl border border-slate-200 bg-white px-1.5 py-1.5 text-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800">
                            <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-xs font-bold text-white">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </span>
                            <span class="hidden min-w-0 text-left leading-tight sm:block">
                                <span class="block truncate text-xs font-semibold text-slate-700 dark:text-slate-100">{{ auth()->user()->name }}</span>
                            </span>
                            <span class="material-icons-round text-base text-slate-400" aria-hidden="true">expand_more</span>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="border-b border-slate-100 px-4 py-2 text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            {{ auth()->user()->email }}
                        </div>
                        <x-dropdown-link :href="route('profile.edit')">
                            <span class="material-icons-round me-1 align-middle text-lg" aria-hidden="true">person</span> Profil Saya
                        </x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                <span class="material-icons-round me-1 align-middle text-lg" aria-hidden="true">logout</span> Keluar
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </div>
</nav>
