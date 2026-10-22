<nav class="bg-white border-b border-gray-200 sticky top-0 z-20 safe-area-top">
    <div class="px-3 sm:px-6 lg:px-8">
        <div class="flex justify-between h-14 sm:h-16">
            <div class="flex items-center gap-2">
                <button @click="menuOpen = ! menuOpen"
                        class="md:hidden p-2 rounded-md text-gray-500 hover:bg-gray-100 focus:outline-none">
                    <span class="material-icons-round">menu</span>
                </button>
                <span class="hidden md:inline text-sm text-gray-400">{{ now()->translatedFormat('l, d F Y') }}</span>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                {{-- Toggle tema terang/gelap (tersimpan di perangkat) --}}
                <button type="button" x-data="{ dark: document.documentElement.classList.contains('dark') }"
                        @click="dark = !dark; document.documentElement.classList.toggle('dark', dark); localStorage.setItem('theme', dark ? 'dark' : 'light')"
                        class="p-2 rounded-full text-gray-500 hover:bg-gray-100 active:bg-gray-50"
                        :title="dark ? 'Mode terang' : 'Mode gelap'" aria-label="Ganti tema">
                    <span class="material-icons-round" x-text="dark ? 'light_mode' : 'dark_mode'">dark_mode</span>
                </button>

                {{-- Lonceng notifikasi --}}
                @php($unread = auth()->user()?->unreadNotifications()->count() ?? 0)
                <a href="{{ route('notifications.index') }}" class="relative p-2 rounded-full text-gray-500 hover:bg-gray-100 active:bg-gray-50" title="Notifikasi">
                    <span class="material-icons-round">notifications</span>
                    @if ($unread > 0)
                        <span class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold">{{ $unread > 99 ? '99+' : $unread }}</span>
                    @endif
                </a>

                <x-dropdown align="right" width="56">
                    <x-slot name="trigger">
                        <button class="flex items-center gap-2 max-w-xs rounded-full bg-gray-100 p-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-800 text-white text-xs font-bold">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </span>
                            <span class="hidden sm:block text-left leading-tight">
                                <span class="block text-sm font-semibold text-gray-700">{{ auth()->user()->name }}</span>
                                <span class="block text-[11px] text-gray-500">{{ \App\Enums\Role::tryFrom(auth()->user()->role)?->label() }}</span>
                            </span>
                            <span class="material-icons-round text-gray-400 text-xl">expand_more</span>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="px-4 py-2 text-xs text-gray-400 border-b border-gray-100">
                            {{ auth()->user()->username }}
                        </div>
                        <x-dropdown-link :href="route('profile.edit')">
                            <span class="material-icons-round text-lg align-middle me-1">person</span> Profil Saya
                        </x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                <span class="material-icons-round text-lg align-middle me-1">logout</span> Keluar
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </div>
</nav>
