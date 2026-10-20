<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengguna &amp; Hak Akses</h2>
            <p class="text-sm text-gray-500">Empat role: Wakasek Sarpras, Admin Sarpras, Laboran, Peminjam</p>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 text-sm mb-4 flex items-center gap-1.5">
                <span class="material-icons-round text-amber-600">person_add</span> Tambah Pengguna
            </h3>
            <form method="POST" action="{{ route('users.store') }}" class="space-y-3">
                @csrf
                <div>
                    <x-input-label value="Nama Lengkap *" />
                    <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                </div>
                <div>
                    <x-input-label value="Username *" />
                    <input type="text" name="username" value="{{ old('username') }}" required class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500" placeholder="huruf/angka/titik/dash">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <x-input-label value="Password *" />
                        <input type="password" name="password" required class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <x-input-label value="Ulangi Password *" />
                        <input type="password" name="password_confirmation" required class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                    </div>
                </div>
                <div>
                    <x-input-label value="Role *" />
                    <select name="role" required class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label value="Jurusan (khusus Laboran)" />
                    <select name="major_id" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                        <option value="">— tanpa jurusan —</option>
                        @foreach ($majors as $major)
                            <option value="{{ $major->id }}" @selected(old('major_id') == $major->id)>{{ $major->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button class="w-full justify-center bg-amber-600 hover:bg-amber-700 focus:ring-amber-500">Buat Pengguna</x-primary-button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="text-left px-4 py-3">Pengguna</th>
                            <th class="text-left px-4 py-3">Role</th>
                            <th class="text-left px-4 py-3">Jurusan</th>
                            <th class="text-center px-4 py-3">Login Terakhir</th>
                            <th class="text-center px-4 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($users as $user)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-800">{{ $user->name }}</div>
                                    <div class="text-[11px] font-mono text-gray-400">{{ $user->username }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'text-[10px] font-bold px-2 py-1 rounded-full',
                                        'bg-purple-100 text-purple-700' => $user->role === 'SUPER_ADMIN',
                                        'bg-blue-100 text-blue-700' => $user->role === 'ADMIN_SARPRAS',
                                        'bg-teal-100 text-teal-700' => $user->role === 'LABORAN',
                                        'bg-gray-100 text-gray-600' => $user->role === 'PEMINJAM',
                                    ])>{{ \App\Enums\Role::tryFrom($user->role)?->label() }}</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $user->major?->name ?: '—' }}</td>
                                <td class="px-4 py-3 text-center text-xs text-gray-400">{{ $user->last_login_at?->format('d M Y H:i') ?: 'belum pernah' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <div x-data="{ open: false }" class="relative inline-block">
                                        <button @click="open = ! open" class="text-xs font-semibold text-amber-700 hover:underline">Ubah</button>
                                        <div x-show="open" @click.outside="open = false" x-cloak
                                             class="absolute right-0 z-20 mt-1 w-72 bg-white border border-gray-200 rounded-xl shadow-lg p-4 text-left">
                                            <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-2">
                                                @csrf
                                                @method('PUT')
                                                <input type="text" name="name" value="{{ old('name', $user->name) }}" class="w-full rounded-lg border-gray-300 text-xs" placeholder="Nama" required>
                                                <input type="text" name="username" value="{{ $user->username }}" class="w-full rounded-lg border-gray-300 text-xs font-mono" placeholder="Username" required>
                                                <select name="role" class="w-full rounded-lg border-gray-300 text-xs">
                                                    @foreach ($roles as $role)
                                                        <option value="{{ $role->value }}" @selected($user->role === $role->value)>{{ $role->label() }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="major_id" class="w-full rounded-lg border-gray-300 text-xs">
                                                    <option value="">— tanpa jurusan —</option>
                                                    @foreach ($majors as $major)
                                                        <option value="{{ $major->id }}" @selected($user->major_id == $major->id)>{{ $major->name }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="password" name="password" class="w-full rounded-lg border-gray-300 text-xs" placeholder="Password baru (kosongkan bila tetap)">
                                                <input type="password" name="password_confirmation" class="w-full rounded-lg border-gray-300 text-xs" placeholder="Ulangi password">
                                                <div class="flex gap-2 pt-1">
                                                    <button class="flex-1 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold py-1.5">Simpan</button>
                                                    <button type="button" @click="open = false" class="rounded-lg border border-gray-300 text-xs px-3">Batal</button>
                                                </div>
                                            </form>
                                            @if ($user->id !== auth()->id() && ! $user->loans()->exists())
                                                <form method="POST" action="{{ route('users.destroy', $user) }}" class="mt-2"
                                                      onsubmit="return confirm('Hapus pengguna ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="w-full rounded-lg bg-red-50 hover:bg-red-100 text-red-700 text-xs font-semibold py-1.5">Hapus Pengguna</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
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
