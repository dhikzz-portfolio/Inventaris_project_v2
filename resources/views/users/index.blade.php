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

    @php($selectedFilterCount = collect([$filters['role'], $filters['category'], $filters['status']])->filter(fn ($value) => $value !== '')->count())
    <form method="GET" action="{{ route('users.index') }}"
          x-data="{ filtersOpen: {{ $selectedFilterCount > 0 ? 'true' : 'false' }} }"
          class="mb-4 rounded-2xl border border-sky-100 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="flex flex-col gap-2 sm:flex-row">
          <div class="min-w-0 flex-1">
            <label for="user-search" class="sr-only">Cari akun</label>
            <input id="user-search" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Cari nama, email, kelas, atau unit kerja"
                   class="min-h-10 w-full rounded-xl border-slate-300 bg-white px-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
          </div>
          <button type="button" x-on:click="filtersOpen = !filtersOpen" x-bind:aria-expanded="filtersOpen" aria-controls="user-filter-panel"
                  class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-blue-200 bg-white px-4 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-blue-800 dark:bg-slate-900 dark:text-blue-300 dark:hover:bg-blue-900/30">
              <span class="material-icons-round text-base" aria-hidden="true">filter_list</span>
              Filter
              @if ($selectedFilterCount > 0)
                  <span class="rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-bold text-blue-800 dark:bg-blue-900 dark:text-blue-200">{{ $selectedFilterCount }}</span>
              @endif
          </button>
        </div>

        <div id="user-filter-panel" x-show="filtersOpen" x-cloak x-transition class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-800">
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <div>
            <label for="user-role" class="sr-only">Filter role</label>
            <select id="user-role" name="role" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua role</option>
                @foreach (\App\Enums\Role::cases() as $roleOption)
                    <option value="{{ $roleOption->value }}" @selected($filters['role'] === $roleOption->value)>{{ $roleOption->label() }}</option>
                @endforeach
            </select>
            </div>
            <div>
            <label for="user-category" class="sr-only">Filter kategori pengguna</label>
            <select id="user-category" name="category" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua kategori</option>
                <option value="SISWA" @selected($filters['category'] === 'SISWA')>Siswa</option>
                <option value="GURU" @selected($filters['category'] === 'GURU')>Guru</option>
                <option value="STAF" @selected($filters['category'] === 'STAF')>Staf / Tenaga Kependidikan</option>
                <option value="LABORAN" @selected($filters['category'] === 'LABORAN')>Operator — Laboran</option>
                <option value="K3" @selected($filters['category'] === 'K3')>Operator — K3</option>
                <option value="LAINNYA" @selected($filters['category'] === 'LAINNYA')>Lainnya (tanpa kategori)</option>
            </select>
            </div>
            <div>
            <select name="status" aria-label="Filter status akun" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua status</option>
                <option value="active" @selected($filters['status'] === 'active')>Aktif</option>
                <option value="inactive" @selected($filters['status'] === 'inactive')>Nonaktif</option>
            </select>
            </div>
          </div>
          <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            @if ($selectedFilterCount > 0 || $filters['search'] !== '')
                <a href="{{ route('users.index') }}" class="text-xs font-semibold text-slate-500 hover:underline dark:text-slate-400">Reset filter</a>
            @else
                <span></span>
            @endif
            <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                Terapkan filter
            </button>
          </div>
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
                <table class="w-full min-w-[820px] table-fixed text-sm">
                    <colgroup>
                        <col class="w-[25%]">
                        <col class="w-[18%]">
                        <col class="w-[25%]">
                        <col class="w-[16%]">
                        <col class="w-[16%]">
                    </colgroup>
                    <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-3 py-3.5 text-left">Pengguna</th>
                            <th scope="col" class="px-3 py-3.5 text-left">Role</th>
                            <th scope="col" class="px-3 py-3.5 text-left">Kategori / Cakupan</th>
                            <th scope="col" class="px-2 py-3.5 text-center">Login Terakhir</th>
                            <th scope="col" class="px-2 py-3.5 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($users as $user)
                            <tr class="{{ $editUser && $editUser->id === $user->id ? 'bg-blue-50 dark:bg-blue-950/30' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60' }}">
                                <td class="px-3 py-4">
                                    <div class="flex flex-wrap items-center gap-1.5 font-medium text-slate-800 dark:text-slate-100">
                                        {{ $user->displayName() }}
                                        @unless ($user->isActive())
                                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700">NONAKTIF</span>
                                        @endunless
                                    </div>
                                    <div class="font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $user->email }}</div>
                                </td>
                                <td class="px-3 py-4">
                                    <span @class([
                                        'inline-flex rounded-lg px-2.5 py-1 text-[10px] font-bold',
                                        'bg-purple-100 text-purple-700' => $user->role === 'SUPER_ADMIN',
                                        'bg-blue-100 text-blue-700' => $user->role === 'ADMIN',
                                        'bg-teal-100 text-teal-700' => $user->role === 'EDITOR_VALIDATOR',
                                        'bg-amber-100 text-amber-700' => $user->role === 'OPERATOR',
                                        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $user->role === 'MEMBER',
                                    ])>{{ \App\Enums\Role::tryFrom($user->role)?->label() }}</span>
                                </td>
                                <td class="px-3 py-4 text-xs text-slate-600 dark:text-slate-300">
                                    {{ $user->major?->name ?: '—' }}
                                    @if ($user->borrower_type)
                                        <div class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">Kategori: {{ $user->borrower_type->label() }}@if($user->class_name) • {{ $user->class_name }}@endif</div>
                                    @endif
                                    @if ($user->roomScopes->isNotEmpty())
                                        <div class="mt-1 truncate text-[10px] text-blue-700 dark:text-blue-300" title="{{ $user->roomScopes->pluck('room.name')->implode(', ') }}">{{ $user->roomScopes->pluck('room.name')->implode(', ') }}</div>
                                    @endif
                                </td>
                                <td class="px-2 py-4 text-center text-xs text-slate-500 dark:text-slate-400">{{ $user->last_login_at?->format('d M Y H:i') ?: 'belum pernah' }}</td>
                                <td class="px-2 py-4 text-center">
                                    <div class="flex flex-wrap items-center justify-center gap-2">
                                        <a href="{{ route('users.show', $user) }}"
                                           class="inline-flex min-h-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 text-[11px] font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Detail</a>
                                        @if ($user->isSuperAdmin())
                                            <span class="inline-flex min-h-8 items-center rounded-lg bg-slate-100 px-2 text-[10px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400" title="Akun Super Admin utama tidak dapat diubah Super Admin Pengguna">Terkunci</span>
                                        @else
                                            <a href="{{ route('users.index', ['edit' => $user->id]) }}"
                                               class="inline-flex min-h-8 items-center justify-center rounded-lg border border-brand-600 bg-brand-600 px-2.5 text-[11px] font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-brand-500 dark:bg-brand-500 dark:hover:bg-brand-400">Ubah</a>
                                            @if ($user->id !== auth()->id())
                                                <form method="POST" action="{{ route('users.status', $user) }}" onsubmit="return confirm('Ubah status akun {{ $user->name }}?')">
                                                    @csrf
                                                    <button class="inline-flex min-h-8 items-center justify-center rounded-lg border px-2.5 text-[11px] font-semibold transition focus:outline-none focus:ring-2 {{ $user->isActive() ? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100 focus:ring-red-500 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300 dark:hover:bg-red-950/60' : 'border-green-200 bg-green-50 text-green-700 hover:bg-green-100 focus:ring-green-500 dark:border-green-900 dark:bg-green-950/40 dark:text-green-300 dark:hover:bg-green-950/60' }}">
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
