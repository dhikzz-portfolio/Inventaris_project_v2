<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-sm text-slate-500 dark:text-slate-400">Detail Akun</p>
                <h2 class="truncate text-xl font-bold leading-tight text-slate-900 dark:text-white">{{ $user->displayName() }}</h2>
                <p class="truncate font-mono text-xs text-slate-500 dark:text-slate-400">{{ $user->email ?: 'tanpa email' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('users.index') }}"
                   class="inline-flex min-h-10 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <span class="material-icons-round text-lg" aria-hidden="true">arrow_back</span> Daftar Akun
                </a>
                @unless ($user->isSuperAdmin())
                    <a href="{{ route('users.index', ['edit' => $user->id]) }}"
                       class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700">
                        <span class="material-icons-round text-lg" aria-hidden="true">edit</span> Ubah Akun
                    </a>
                @endunless
            </div>
        </div>
    </x-slot>

    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="mb-3 text-base font-bold text-slate-900 dark:text-slate-100">Informasi Akun</h3>
                <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Role</dt><dd class="font-semibold text-slate-800 dark:text-slate-100">{{ \App\Enums\Role::tryFrom($user->role)?->label() ?? $user->role }}</dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Status</dt><dd class="font-semibold {{ $user->isActive() ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">{{ $user->isActive() ? 'Aktif' : 'Nonaktif' }}</dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Kategori peminjam</dt><dd>{{ $user->borrower_type?->label() ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Kelas / unit kerja</dt><dd>{{ $user->class_name ?: ($user->work_unit ?: '—') }}</dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Jurusan</dt><dd>{{ $user->major?->name ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Login terakhir</dt><dd>{{ $user->last_login_at?->format('d M Y H:i') ?: 'belum pernah' }}</dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Wajib ganti password</dt><dd>{{ $user->mustChangePassword() ? 'Ya' : 'Tidak' }}</dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Password terakhir diubah</dt><dd>{{ $user->password_changed_at?->format('d M Y H:i') ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Riwayat peminjaman</dt><dd>{{ $loanCount }} transaksi</dd></div>
                </dl>
            </div>

            <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="mb-3 text-base font-bold text-slate-900 dark:text-slate-100">Riwayat Perubahan Akun</h3>
                @if ($history->isEmpty())
                    <p class="rounded-xl bg-slate-50 px-3 py-5 text-sm text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">Belum ada perubahan akun yang tercatat.</p>
                @else
                    <ol class="space-y-3">
                        @foreach ($history as $entry)
                            <li class="border-l-2 border-blue-200 pl-3 dark:border-blue-900">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $entry->description }}</p>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                    {{ $entry->causer?->name ?? 'Sistem' }} • {{ $entry->created_at?->format('d M Y H:i') }}
                                </p>
                                @if (! empty($entry->properties['aksi']))
                                    <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">Aksi: {{ $entry->properties['aksi'] }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
                <p class="mt-3 text-[11px] text-slate-500 dark:text-slate-400">Audit tidak pernah menyimpan password.</p>
            </div>
        </div>

        <div class="space-y-5">
            <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="mb-3 text-base font-bold text-slate-900 dark:text-slate-100">Cakupan Akses</h3>
                @if ($user->roomScopes->isEmpty())
                    <p class="text-sm text-slate-500 dark:text-slate-400">Tidak ada cakupan laboratorium khusus.</p>
                @else
                    <ul class="space-y-1.5 text-xs text-slate-700 dark:text-slate-200">
                        @foreach ($user->roomScopes as $scope)
                            <li class="rounded-lg bg-slate-50 px-2.5 py-2 dark:bg-slate-800/70">
                                <span class="font-mono font-semibold">{{ $scope->room?->code }}</span> — {{ $scope->room?->name }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @if (! $user->isSuperAdmin())
                <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="mb-2 text-base font-bold text-slate-900 dark:text-slate-100">Status Akun</h3>
                    <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Nonaktifkan akun untuk memutus akses tanpa menghapus riwayat.</p>
                    <form method="POST" action="{{ route('users.status', $user) }}" onsubmit="return confirm('Ubah status akun ini?')">
                        @csrf
                        <button class="inline-flex min-h-10 w-full items-center justify-center gap-1.5 rounded-xl px-3 text-sm font-bold {{ $user->isActive() ? 'bg-red-50 text-red-700 hover:bg-red-100 dark:bg-red-950/40 dark:text-red-300' : 'bg-green-50 text-green-700 hover:bg-green-100 dark:bg-green-950/40 dark:text-green-300' }}">
                            <span class="material-icons-round text-lg" aria-hidden="true">{{ $user->isActive() ? 'block' : 'check_circle' }}</span>
                            {{ $user->isActive() ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}
                        </button>
                    </form>
                </div>
            @else
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    Akun Super Admin dilindungi. Role ini tidak dapat diubah atau dinonaktifkan dari halaman administrasi akun{{ $isLastActiveSuperAdmin ? ' dan merupakan satu-satunya Super Admin yang aktif' : '' }}.
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
