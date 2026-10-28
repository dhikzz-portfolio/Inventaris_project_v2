@php
    $editing = $user !== null;
    $currentRole = old('role', $user?->role ?? \App\Enums\Role::PEMINJAM->value);
    $currentType = old('borrower_type', $user?->borrower_type?->value);
    $selectedRooms = old('room_ids', $user?->roomScopes?->pluck('room_id')->all() ?? []);
@endphp

<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5"
     x-data="{ role: '{{ $currentRole }}', type: '{{ $currentType }}' }">
    <div class="flex items-center justify-between gap-2 mb-4">
        <h3 class="font-semibold text-gray-800 text-sm flex items-center gap-1.5">
            <span class="material-icons-round text-amber-600">{{ $editing ? 'manage_accounts' : 'person_add' }}</span>
            {{ $editing ? 'Ubah Pengguna' : 'Tambah Pengguna' }}
        </h3>
        @if ($editing)
            <a href="{{ route('users.index') }}" class="text-xs text-gray-500 hover:text-gray-800">← Batal</a>
        @endif
    </div>

    @if ($editing)
        <div class="mb-4 rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-xs text-slate-600">
            Mengubah: <strong>{{ $user->displayName() }}</strong> (<span class="font-mono">{{ $user->email }}</span>)
        </div>
    @endif

    <form method="POST" action="{{ $action }}" class="space-y-5">
        @csrf
        @if ($method !== 'POST')
            @method($method)
        @endif

        {{-- Identitas --}}
        <fieldset class="space-y-3">
            <legend class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-1">Identitas</legend>

            <div>
                <x-input-label value="Nama Lengkap *" />
                <input type="text" name="name" value="{{ old('name', $user?->name) }}" required
                       class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <x-input-label value="Gelar (khusus guru)" />
                    <input type="text" name="degree" value="{{ old('degree', $user?->degree) }}" placeholder="S.Kom, S.Pd"
                           class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                    @error('degree') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-input-label value="Email Gmail" />
                    <input type="email" name="email" value="{{ old('email', $user?->email) }}" placeholder="nama@gmail.com"
                           class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <x-input-label value="Jurusan" />
                    <select name="major_id" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                        <option value="">— tanpa jurusan —</option>
                        @foreach ($majors as $major)
                            <option value="{{ $major->id }}" @selected(old('major_id', $user?->major_id) == $major->id)>{{ $major->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </fieldset>

        {{-- Keamanan --}}
        <fieldset class="space-y-3 border-t border-gray-100 pt-4">
            <legend class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-1">Keamanan</legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <x-input-label :value="$editing ? 'Password baru (opsional)' : 'Password *'" />
                    <input type="password" name="password" @required(! $editing) class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                    @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-input-label value="Ulangi password" />
                    <input type="password" name="password_confirmation" @required(! $editing) class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                </div>
            </div>
            <p class="text-[11px] text-gray-400">Minimal 8 karakter. Role istimewa hanya dapat ditetapkan dari halaman ini oleh Wakasek.</p>
        </fieldset>

        {{-- Role --}}
        <fieldset class="border-t border-gray-100 pt-4">
            <legend class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-2">Role Sistem *</legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach ($roles as $role)
                    <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-xs transition has-[:checked]:border-amber-500 has-[:checked]:ring-2 has-[:checked]:ring-amber-500/20">
                        <input type="radio" name="role" value="{{ $role->value }}" x-model="role" required
                               class="mt-0.5 text-amber-600 focus:ring-amber-500"
                               @checked($currentRole === $role->value)>
                        <span>
                            <span class="block font-semibold text-gray-800">{{ $role->label() }}</span>
                            <span class="block text-[10px] text-gray-400">{{ $role->value }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </fieldset>

        {{-- Kategori peminjam --}}
        <fieldset class="border-t border-gray-100 pt-4 space-y-3" x-show="role === 'PEMINJAM'" x-cloak>
            <legend class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-2">Kategori Peminjam</legend>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                @foreach ($borrowerTypes as $type)
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs has-[:checked]:border-amber-500 has-[:checked]:ring-2 has-[:checked]:ring-amber-500/20">
                        <input type="radio" name="borrower_type" value="{{ $type->value }}" x-model="type"
                               class="text-amber-600 focus:ring-amber-500"
                               @checked($currentType === $type->value)>
                        <span class="font-medium text-gray-700">{{ $type->label() }}</span>
                    </label>
                @endforeach
            </div>
            @error('borrower_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" x-show="type === 'SISWA'" x-cloak>
                <div>
                    <x-input-label value="Kelas siswa" />
                    <input type="text" name="class_name" value="{{ old('class_name', $user?->class_name) }}" placeholder="X RPL 1"
                           class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                    @error('class_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </fieldset>

        {{-- Cakupan laboratorium --}}
        <fieldset class="border-t border-gray-100 pt-4" x-show="role === 'LABORAN'" x-cloak>
            <legend class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-2">Cakupan Laboratorium</legend>
            <div class="max-h-40 overflow-y-auto rounded-lg border border-gray-200 p-2 space-y-1">
                @forelse ($labRooms as $labRoom)
                    <label class="flex items-center gap-2 text-xs text-gray-700 px-1 py-0.5">
                        <input type="checkbox" name="room_ids[]" value="{{ $labRoom->id }}"
                               class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                               @checked(in_array($labRoom->id, $selectedRooms))>
                        <span>{{ $labRoom->code }} — {{ $labRoom->name }}</span>
                    </label>
                @empty
                    <p class="text-xs text-gray-400 px-1">Belum ada laboratorium pada master ruangan.</p>
                @endforelse
            </div>
            @error('room_ids') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </fieldset>

        <div class="flex items-center gap-2 border-t border-gray-100 pt-4">
            <button type="submit"
                    class="flex-1 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold py-2.5">
                {{ $submitLabel }}
            </button>
            <a href="{{ $editing ? route('users.index') : route('users.index') }}"
               class="px-4 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-600">Batal</a>
        </div>
    </form>

    @if ($editing && $user->id !== auth()->id())
        <form method="POST" action="{{ route('users.destroy', $user) }}" class="mt-3"
              onsubmit="return confirm('Hapus pengguna {{ $user->name }}? Akun dengan riwayat peminjaman tidak dapat dihapus.')">
            @csrf
            @method('DELETE')
            <button class="w-full rounded-lg bg-red-50 hover:bg-red-100 text-red-700 text-xs font-semibold py-2">Hapus Pengguna</button>
        </form>
    @endif
</div>
