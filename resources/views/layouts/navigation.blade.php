<nav class="sticky top-0 z-20 border-b border-slate-200/80 bg-white/95 safe-area-top backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
    <div class="px-3 sm:px-6 lg:px-8">
        <div class="flex h-14 items-center justify-between sm:h-16">
            <div class="flex min-w-0 items-center gap-2">
                <button @click="menuOpen = ! menuOpen"
                        :aria-expanded="menuOpen.toString()"
                        aria-controls="main-menu"
                        aria-label="Buka menu utama"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-slate-600 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-brand-500 sm:hidden dark:text-slate-300 dark:hover:bg-slate-800">
                    <span class="material-icons-round" aria-hidden="true">menu</span>
                </button>
                <div class="flex min-w-0 items-center gap-2 sm:hidden">
                    @if ($institution->logo_path)
                        <img src="{{ asset('storage/'.$institution->logo_path) }}" alt="" class="h-7 w-7 shrink-0 rounded-md bg-white object-contain">
                    @else
                        <span class="material-icons-round shrink-0 text-xl text-brand-600" aria-hidden="true">school</span>
                    @endif
                    <span class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $institution->institution_name }}</span>
                </div>
                <form action="{{ route('catalogs.index') }}" method="GET" role="search"
                      class="hidden h-8 w-36 items-center gap-2 rounded-lg border border-sky-200 bg-white px-2.5 text-slate-500 shadow-sm sm:flex lg:w-64 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    <label for="global-catalog-search" class="material-icons-round text-base" aria-hidden="true">search</label>
                    <input id="global-catalog-search" type="search" name="search" value="{{ request()->query('search') }}"
                           placeholder="Cari barang atau kode" aria-label="Cari barang atau kode"
                           class="min-w-0 flex-1 border-0 bg-transparent p-0 text-xs text-slate-700 outline-none placeholder:text-slate-400 focus:ring-0 dark:text-slate-100">
                </form>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                <button type="button" x-data="{ dark: document.documentElement.classList.contains('dark') }"
                        @click="dark = !dark; document.documentElement.classList.toggle('dark', dark); localStorage.setItem('theme', dark ? 'dark' : 'light')"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-sky-200 text-slate-500 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-brand-500 sm:h-9 sm:w-9 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        :title="dark ? 'Mode terang' : 'Mode gelap'" aria-label="Ganti tema">
                    <span class="material-icons-round" aria-hidden="true" x-text="dark ? 'light_mode' : 'dark_mode'">dark_mode</span>
                </button>

                @php($unread = auth()->user()?->unreadNotifications()->count() ?? 0)
                <a href="{{ route('notifications.index') }}" class="relative inline-flex h-8 w-8 items-center justify-center rounded-lg border border-sky-200 text-slate-500 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-brand-500 sm:h-9 sm:w-9 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800" aria-label="Notifikasi" title="Notifikasi">
                    <span class="material-icons-round" aria-hidden="true">notifications</span>
                    @if ($unread > 0)
                        <span class="absolute right-1 top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[9px] font-bold text-white">{{ $unread > 99 ? '99+' : $unread }}</span>
                    @endif
                </a>

                <x-dropdown align="right" width="56">
                    <x-slot name="trigger">
                        <button class="flex max-w-xs items-center gap-1.5 rounded-lg border border-sky-200 px-1.5 py-1 text-sm transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-brand-500 sm:gap-2 dark:border-slate-700 dark:hover:bg-slate-800">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-sky-700 text-xs font-bold text-white">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </span>
                            <span class="hidden sm:block text-left leading-tight">
                                <span class="block max-w-32 truncate text-xs font-semibold text-slate-700 dark:text-slate-100">{{ auth()->user()->name }}</span>
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
