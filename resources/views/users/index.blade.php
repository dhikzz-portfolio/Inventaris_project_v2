<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Akun Pengguna</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Kelola akun dan hak akses. Super Admin Pengguna tidak memiliki akses operasional inventaris.</p>
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
                                    </div>
                                    <div class="font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $user->email }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'text-[10px] font-bold px-2 py-1 rounded-full',
                                        'bg-purple-100 text-purple-700' => $user->role === 'SUPER_ADMIN',
                                        'bg-fuchsia-100 text-fuchsia-700' => $user->role === 'SUPER_ADMIN_PENGGUNA',
                                        'bg-blue-100 text-blue-700' => $user->role === 'KETUA_LABORAN',
                                        'bg-teal-100 text-teal-700' => $user->role === 'LABORAN',
                                        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $user->role === 'PEMINJAM',
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
                                    @if ($user->isSuperAdmin() && ! auth()->user()->isSuperAdmin())
                                        <span class="text-[11px] text-slate-400" title="Akun Super Admin utama tidak dapat diubah Super Admin Pengguna">terkunci</span>
                                    @else
                                        <a href="{{ route('users.index', ['edit' => $user->id]) }}"
                                           class="text-xs font-semibold text-blue-700 hover:underline dark:text-blue-300">Ubah</a>
                                    @endif
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
                            </div>
                            <span @class([
                                'shrink-0 rounded-full px-2 py-1 text-[10px] font-bold',
                                'bg-purple-100 text-purple-700' => $user->role === 'SUPER_ADMIN',
                                'bg-blue-100 text-blue-700' => $user->role === 'KETUA_LABORAN',
                                'bg-teal-100 text-teal-700' => $user->role === 'LABORAN',
                                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $user->role === 'PEMINJAM',
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
                        <div class="flex items-center justify-between gap-2 text-[11px] text-slate-400 dark:text-slate-500">
                            <p>Login terakhir: {{ $user->last_login_at?->format('d M Y H:i') ?: 'belum pernah' }}</p>
                            <a href="{{ route('users.index', ['edit' => $user->id]) }}"
                               class="inline-flex min-h-9 items-center rounded-lg px-2.5 text-xs font-semibold text-brand-700 transition hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-slate-800">Ubah</a>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="px-4 py-3">{{ $users->links() }}</div>
        </div>
    </div>
</x-app-layout>
