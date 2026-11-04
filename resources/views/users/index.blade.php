<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Akun Pengguna</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Kelola akun dan hak akses. Super Admin tidak memiliki akses operasional inventaris.</p>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <ul class="list-disc ms-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Total akun</p>
            <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $stats['total'] }}</p>
        </div>
        <div class="rounded-xl border border-green-200 bg-green-50 p-3 dark:border-green-900 dark:bg-green-950/40">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-green-700 dark:text-green-300">Aktif</p>
            <p class="mt-1 text-2xl font-bold text-green-800 dark:text-green-200">{{ $stats['active'] }}</p>
        </div>
        <div class="rounded-xl border border-red-200 bg-red-50 p-3 dark:border-red-900 dark:bg-red-950/40">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-red-700 dark:text-red-300">Nonaktif</p>
            <p class="mt-1 text-2xl font-bold text-red-800 dark:text-red-200">{{ $stats['inactive'] }}</p>
        </div>
        <div class="rounded-xl border border-fuchsia-200 bg-fuchsia-50 p-3 dark:border-fuchsia-900 dark:bg-fuchsia-950/40">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-fuchsia-700 dark:text-fuchsia-300">Super Admin</p>
            <p class="mt-1 text-2xl font-bold text-fuchsia-800 dark:text-fuchsia-200">{{ $stats['account_admins'] }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('users.index') }}" class="mb-4 grid grid-cols-1 gap-2 rounded-2xl border border-sky-100 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:grid-cols-4">
        <div class="sm:col-span-2">
            <label for="user-search" class="sr-only">Cari akun</label>
            <input id="user-search" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Cari nama, email, kelas, atau unit kerja"
                   class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
        </div>
        <div>
            <label for="user-role" class="sr-only">Filter role</label>
            <select id="user-role" name="role" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua role</option>
                @foreach (\App\Enums\Role::cases() as $roleOption)
                    <option value="{{ $roleOption->value }}" @selected($filters['role'] === $roleOption->value)>{{ $roleOption->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <select name="status" aria-label="Filter status akun" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua status</option>
                <option value="active" @selected($filters['status'] === 'active')>Aktif</option>
                <option value="inactive" @selected($filters['status'] === 'inactive')>Nonaktif</option>
            </select>
            <button class="inline-flex min-h-10 shrink-0 items-center rounded-xl bg-blue-600 px-3 text-xs font-bold text-white hover:bg-blue-700">Filter</button>
        </div>
    </form>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-1">
            @if ($editUser)
                @include('users._form', [
                    'user' => $editUser,
                    'action' => route('users.update', $editUser),
                    'method' => 'PUT',
                    'submitLabel' => 'Simpan Perubahan',
                ])
            @else
                @include('users._form', [
                    'user' => null,
                    'action' => route('users.store'),
                    'method' => 'POST',
                    'submitLabel' => 'Buat Pengguna',
                ])
            @endif
        </div>

        <div class="lg:col-span-2 self-start overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="border-b border-slate-100 px-4 py-3 text-sm font-semibold text-slate-800 dark:border-slate-800 dark:text-slate-100">Daftar Pengguna</div>
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <tr>
                            <th class="text-left px-4 py-3">Pengguna</th>
                            <th class="text-left px-4 py-3">Role</th>
                            <th class="text-left px-4 py-3">Kategori / Cakupan</th>
                            <th class="text-center px-4 py-3">Login Terakhir</th>
                            <th class="text-center px-4 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($users as $user)
                            <tr class="{{ $editUser && $editUser->id === $user->id ? 'bg-blue-50 dark:bg-blue-950/30' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60' }}">
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-1.5 font-medium text-slate-800 dark:text-slate-100">
                                        {{ $user->displayName() }}
                                        @unless ($user->isActive())
                                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700">NONAKTIF</span>
                                        @endunless
                                        @if ($user->mustChangePassword())
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">WAJIB GANTI PASSWORD</span>
                                        @endif
                                    </div>
                                    <div class="font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $user->email }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'text-[10px] font-bold px-2 py-1 rounded-full',
                                        'bg-purple-100 text-purple-700' => $user->role === 'SUPER_ADMIN',
                                        'bg-blue-100 text-blue-700' => $user->role === 'ADMIN',
                                        'bg-teal-100 text-teal-700' => $user->role === 'EDITOR_VALIDATOR',
                                        'bg-amber-100 text-amber-700' => $user->role === 'OPERATOR',
                                        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $user->role === 'MEMBER',
                                    ])>{{ \App\Enums\Role::tryFrom($user->role)?->label() }}</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">
                                    {{ $user->major?->name ?: '—' }}
                                    @if ($user->borrower_type)
                                        <div class="text-[10px] text-gray-500 mt-0.5">Kategori: {{ $user->borrower_type->label() }}@if($user->class_name) • {{ $user->class_name }}@endif</div>
                                    @endif
                                    @if ($user->roomScopes->isNotEmpty())
                                        <div class="text-[10px] text-blue-700 mt-0.5">{{ $user->roomScopes->pluck('room.name')->implode(', ') }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center text-xs text-gray-400">{{ $user->last_login_at?->format('d M Y H:i') ?: 'belum pernah' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex flex-wrap items-center justify-center gap-2">
                                        <a href="{{ route('users.show', $user) }}"
                                           class="text-xs font-semibold text-slate-600 hover:underline dark:text-slate-300">Detail</a>
                                        @if ($user->isSuperAdmin())
                                            <span class="text-[11px] text-slate-400" title="Akun Super Admin tidak dapat diubah Admin">terkunci</span>
                                        @else
                                            <a href="{{ route('users.index', ['edit' => $user->id]) }}"
                                               class="text-xs font-semibold text-blue-700 hover:underline dark:text-blue-300">Ubah</a>
                                            @if ($user->id !== auth()->id())
                                                <form method="POST" action="{{ route('users.status', $user) }}" onsubmit="return confirm('Ubah status akun {{ $user->name }}?')">
                                                    @csrf
                                                    <button class="text-xs font-semibold {{ $user->isActive() ? 'text-red-600' : 'text-green-700' }} hover:underline">
                                                        {{ $user->isActive() ? 'Nonaktifkan' : 'Aktifkan' }}
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="divide-y divide-slate-100 md:hidden dark:divide-slate-800">
                @foreach ($users as $user)
                    <article @class([
                        'space-y-3 p-4',
                        'bg-blue-50 dark:bg-blue-950/30' => $editUser && $editUser->id === $user->id,
                    ])>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $user->displayName() }}</p>
                                @if ($user->email)
                                    <p class="truncate font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
                                @endif
                            </div>                                    <span @class([
                                'shrink-0 rounded-full px-2 py-1 text-[10px] font-bold',
                                'bg-purple-100 text-purple-700' => $user->role === 'SUPER_ADMIN',
                                'bg-blue-100 text-blue-700' => $user->role === 'ADMIN',
                                'bg-teal-100 text-teal-700' => $user->role === 'EDITOR_VALIDATOR',
                                'bg-amber-100 text-amber-700' => $user->role === 'OPERATOR',
                                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $user->role === 'MEMBER',
                            ])>{{ \App\Enums\Role::tryFrom($user->role)?->label() }}</span>
                        </div>
                        <div class="space-y-0.5 text-xs text-slate-500 dark:text-slate-400">
                            <p>{{ $user->major?->name ?: '—' }}</p>
                            @if ($user->borrower_type)
                                <p class="text-[11px]">Kategori: {{ $user->borrower_type->label() }}@if($user->class_name) • {{ $user->class_name }}@endif</p>
                            @endif
                            @if ($user->roomScopes->isNotEmpty())
                                <p class="text-[11px] text-blue-700 dark:text-blue-300">{{ $user->roomScopes->pluck('room.name')->implode(', ') }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-400 dark:text-slate-500">
                            <p>Login terakhir: {{ $user->last_login_at?->format('d M Y H:i') ?: 'belum pernah' }}</p>
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('users.show', $user) }}"
                                   class="inline-flex min-h-9 items-center rounded-lg px-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Detail</a>
                                @unless ($user->isSuperAdmin())
                                    <a href="{{ route('users.index', ['edit' => $user->id]) }}"
                                       class="inline-flex min-h-9 items-center rounded-lg px-2.5 text-xs font-semibold text-brand-700 transition hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-slate-800">Ubah</a>
                                @endunless
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="px-4 py-3">{{ $users->links() }}</div>
        </div>
    </div>
</x-app-layout>
