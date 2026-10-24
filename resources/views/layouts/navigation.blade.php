<nav class="sticky top-0 z-20 border-b border-slate-200/80 bg-white/95 safe-area-top backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
    <div class="px-3 sm:px-6 lg:px-8">
        <div class="flex h-14 items-center justify-between sm:h-16">
            <div class="flex min-w-0 items-center gap-2">
                <button @click="menuOpen = ! menuOpen"
                        :aria-expanded="menuOpen.toString()"
                        aria-controls="main-menu"
                        aria-label="Buka menu utama"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-slate-600 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 md:hidden dark:text-slate-300 dark:hover:bg-slate-800">
                    <span class="material-icons-round" aria-hidden="true">menu</span>
                </button>
                <div class="flex min-w-0 items-center gap-2 md:hidden">
                    @if ($institution->logo_path)
                        <img src="{{ asset('storage/'.$institution->logo_path) }}" alt="" class="h-7 w-7 shrink-0 rounded-md bg-white object-contain">
                    @else
                        <span class="material-icons-round shrink-0 text-xl text-blue-600" aria-hidden="true">school</span>
                    @endif
                    <span class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $institution->institution_name }}</span>
                </div>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                <button type="button" x-data="{ dark: document.documentElement.classList.contains('dark') }"
                        @click="dark = !dark; document.documentElement.classList.toggle('dark', dark); localStorage.setItem('theme', dark ? 'dark' : 'light')"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-slate-300 dark:hover:bg-slate-800"
                        :title="dark ? 'Mode terang' : 'Mode gelap'" aria-label="Ganti tema">
                    <span class="material-icons-round" aria-hidden="true" x-text="dark ? 'light_mode' : 'dark_mode'">dark_mode</span>
                </button>

                @php($unread = auth()->user()?->unreadNotifications()->count() ?? 0)
                <a href="{{ route('notifications.index') }}" class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-slate-300 dark:hover:bg-slate-800" aria-label="Notifikasi" title="Notifikasi">
                    <span class="material-icons-round" aria-hidden="true">notifications</span>
                    @if ($unread > 0)
                        <span class="absolute right-1 top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[9px] font-bold text-white">{{ $unread > 99 ? '99+' : $unread }}</span>
                    @endif
                </a>

                <x-dropdown align="right" width="56">
                    <x-slot name="trigger">
                        <button class="flex max-w-xs items-center gap-2 rounded-xl p-1.5 text-sm transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:hover:bg-slate-800">
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-xs font-bold text-white">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </span>
                            <span class="hidden sm:block text-left leading-tight">
                                <span class="block max-w-40 truncate text-sm font-semibold text-slate-700 dark:text-slate-100">{{ auth()->user()->name }}</span>
                            </span>
                            <span class="material-icons-round text-xl text-slate-400" aria-hidden="true">expand_more</span>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="border-b border-slate-100 px-4 py-2 text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            {{ auth()->user()->username }}
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
