<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengguna &amp; Hak Akses</h2>
            <p class="text-sm text-gray-500">Empat role: Wakasek Sarpras, Ketua Laboran, Laboran/K3, Peminjam</p>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
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

        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden self-start">
            <div class="px-4 py-3 border-b border-gray-100 text-sm font-semibold text-gray-700">Daftar Pengguna</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="text-left px-4 py-3">Pengguna</th>
                            <th class="text-left px-4 py-3">Role</th>
                            <th class="text-left px-4 py-3">Kategori / Cakupan</th>
                            <th class="text-center px-4 py-3">Login Terakhir</th>
                            <th class="text-center px-4 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($users as $user)
                            <tr class="{{ $editUser && $editUser->id === $user->id ? 'bg-amber-50/60' : 'hover:bg-gray-50' }}">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-800">{{ $user->displayName() }}</div>
                                    <div class="text-[11px] font-mono text-gray-400">{{ $user->username }}</div>
                                    @if ($user->email)
                                        <div class="text-[10px] text-gray-400">{{ $user->email }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'text-[10px] font-bold px-2 py-1 rounded-full',
                                        'bg-purple-100 text-purple-700' => $user->role === 'SUPER_ADMIN',
                                        'bg-blue-100 text-blue-700' => $user->role === 'KETUA_LABORAN',
                                        'bg-teal-100 text-teal-700' => $user->role === 'LABORAN',
                                        'bg-gray-100 text-gray-600' => $user->role === 'PEMINJAM',
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
                                    <a href="{{ route('users.index', ['edit' => $user->id]) }}"
                                       class="text-xs font-semibold text-amber-700 hover:underline">Ubah</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3">{{ $users->links() }}</div>
        </div>
    </div>
</x-app-layout>
