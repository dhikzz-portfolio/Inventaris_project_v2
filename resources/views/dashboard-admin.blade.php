<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand-600 dark:text-brand-400">Administrasi sistem</p>
                <h2 class="mt-1 text-xl font-bold leading-tight text-slate-900 dark:text-white">Dashboard Super Admin</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Pemantauan akun, aktivitas, dan konfigurasi permission. Bukan dashboard operasional inventaris.</p>
            </div>
            <div class="hidden shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600 sm:inline-flex dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                <span class="material-icons-round text-base text-brand-600" aria-hidden="true">calendar_today</span>
                {{ now()->translatedFormat('l, d F Y') }}
            </div>
        </div>
    </x-slot>

    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</p>
                    <span class="material-icons-round text-base text-brand-600" aria-hidden="true">{{ $stat['icon'] }}</span>
                </div>
                <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($stat['value'], 0, ',', '.') }}</p>
                <p class="text-[10px] text-slate-400">{{ $stat['unit'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Pengguna per role</h3>
            </div>
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($roles as $row)
                    <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                        <span class="text-slate-700 dark:text-slate-200">{{ $row['role']->label() }}</span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $row['count'] }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-slate-100 p-4 dark:border-slate-800">
                <a href="{{ route('users.index') }}" class="text-xs font-semibold text-brand-700 hover:underline dark:text-brand-300">Kelola akun pengguna →</a>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Aktivitas terbaru</h3>
            </div>
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($recentActivity as $log)
                    <li class="px-4 py-2.5 text-xs">
                        <p class="font-medium text-slate-700 dark:text-slate-200">{{ $log->description }}</p>
                        <p class="mt-0.5 text-[10px] text-slate-400">{{ $log->causer?->name ?? 'Sistem' }} • {{ $log->created_at?->diffForHumans() }}</p>
                    </li>
                @empty
                    <li class="px-4 py-5 text-center text-xs text-slate-400">Belum ada aktivitas.</li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Riwayat login</h3>
            </div>
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($loginHistory as $account)
                    <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-xs">
                        <span class="min-w-0 truncate text-slate-700 dark:text-slate-200">{{ $account->displayName() }}</span>
                        <span class="shrink-0 text-slate-400">{{ $account->last_login_at?->format('d M Y H:i') }}</span>
                    </li>
                @empty
                    <li class="px-4 py-5 text-center text-xs text-slate-400">Belum ada riwayat login.</li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Perubahan permission terbaru</h3>
            </div>
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($permissionChanges as $change)
                    <li class="px-4 py-2.5 text-xs">
                        <p class="font-medium text-slate-700 dark:text-slate-200">{{ \App\Enums\Role::tryFrom($change->role_name)?->label() ?? $change->role_name }}</p>
                        <p class="mt-0.5 text-[10px] text-slate-400">
                            {{ count($change->before ?? []) }} → {{ count($change->after ?? []) }} permission •
                            {{ $change->actor?->name ?? 'Sistem' }} • {{ $change->created_at?->diffForHumans() }}
                        </p>
                    </li>
                @empty
                    <li class="px-4 py-5 text-center text-xs text-slate-400">Belum ada perubahan permission.</li>
                @endforelse
            </ul>
            <div class="border-t border-slate-100 p-4 dark:border-slate-800">
                <a href="{{ route('roles.index') }}" class="text-xs font-semibold text-brand-700 hover:underline dark:text-brand-300">Kelola role &amp; permission →</a>
            </div>
        </section>
    </div>

    <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Keamanan akun</h3>
        <dl class="mt-3 grid grid-cols-1 gap-3 text-xs sm:grid-cols-3">
            <div>
                <dt class="text-slate-400">Login terakhir</dt>
                <dd class="mt-0.5 font-medium text-slate-700 dark:text-slate-200">{{ $security['last_login']?->format('d M Y H:i') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-400">Password terakhir diubah</dt>
                <dd class="mt-0.5 font-medium text-slate-700 dark:text-slate-200">{{ $security['password_changed']?->format('d M Y H:i') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-400">Wajib ganti password</dt>
                <dd class="mt-0.5 font-medium text-slate-700 dark:text-slate-200">{{ $security['must_change_password'] ? 'Ya' : 'Tidak' }}</dd>
            </div>
        </dl>
    </section>
</x-app-layout>
