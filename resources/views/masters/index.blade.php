<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Master Data</h2>
            <p class="text-sm text-gray-500">Gedung, ruangan, jurusan, dan kategori barang</p>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- Master Gedung --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 text-sm mb-1 flex items-center gap-1.5">
                <span class="material-icons-round text-amber-600">apartment</span> Master Gedung
            </h3>
            <p class="text-[11px] text-gray-400 mb-4">
                Identitas kode gedung dipakai generator kode lokasi &amp; kode inventaris (mis. Gedung C lantai 1 ruang 03 → <span class="font-mono">C1-03-21</span>).
            </p>

            <ul class="space-y-1.5 text-sm mb-4">
                @forelse ($buildings as $building)
                    <li class="border-b border-dashed border-gray-100 pb-1.5">
                        <details>
                            <summary class="flex items-center justify-between cursor-pointer list-none">
                                <span class="text-gray-700">
                                    {{ $building->name }}
                                    @unless ($building->is_active)
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">NONAKTIF</span>
                                    @endunless
                                </span>
                                <span class="text-[11px] text-gray-400 font-mono">kode {{ $building->code }} • {{ $building->rooms_count }} ruang • ubah</span>
                            </summary>
                            <form method="POST" action="{{ route('masters.update-building', $building) }}" class="mt-2 flex gap-1.5">
                                @csrf @method('PUT')
                                <input type="text" name="name" value="{{ $building->name }}" class="flex-1 rounded-lg border-gray-300 text-xs" required>
                                <input type="text" name="code" value="{{ $building->code }}" maxlength="1"
                                       class="w-14 rounded-lg border-gray-300 text-xs uppercase font-mono" required
                                       title="Identitas kode gedung (A-Z)">
                                <button class="px-2 rounded-lg bg-slate-800 text-white text-xs font-semibold">Simpan</button>
                            </form>
                            <p class="text-[10px] text-gray-400 mt-1">
                                Kode gedung tidak dapat diubah bila sudah ada unit inventaris yang memakainya.
                            </p>
                            <form method="POST" action="{{ route('masters.destroy-building', $building) }}" class="mt-1" onsubmit="return confirm('Hapus gedung ini?')">
                                @csrf @method('DELETE')
                                <button class="text-[11px] text-red-600 hover:underline">Hapus gedung</button>
                            </form>
                        </details>
                    </li>
                @empty
                    <li class="text-sm text-gray-400">Belum ada data gedung.</li>
                @endforelse
            </ul>

            <form method="POST" action="{{ route('masters.store-building') }}" class="grid grid-cols-2 gap-2">
                @csrf
                <input type="text" name="name" placeholder="Nama gedung * (Gedung C)" class="rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500" required>
                <input type="text" name="code" placeholder="Kode * (C)" maxlength="1" class="rounded-lg border-gray-300 text-sm uppercase font-mono focus:ring-amber-500 focus:border-amber-500" required>
                <button class="rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold">Tambah Gedung</button>
            </form>
        </div>

        {{-- Ruangan --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 text-sm mb-1 flex items-center gap-1.5">
                <span class="material-icons-round text-amber-600">domain</span> Ruangan ({{ $rooms->count() }})
            </h3>
            <p class="text-[11px] text-gray-400 mb-4">
                Kode lokasi dibentuk otomatis dari gedung + lantai + nomor ruangan.
            </p>

            <div class="max-h-72 overflow-auto mb-4">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($rooms as $room)
                            <tr>
                                <td class="py-1.5 font-mono text-xs font-semibold text-gray-700 w-16">{{ $room->code }}</td>
                                <td class="py-1.5 text-xs text-gray-600">
                                    {{ $room->name }}
                                    <details>
                                        <summary class="text-[10px] text-amber-700 cursor-pointer">ubah</summary>
                                        <form method="POST" action="{{ route('masters.update-room', $room) }}" class="mt-1 space-y-1">
                                            @csrf @method('PUT')
                                            <select name="major_id" class="w-full rounded border-gray-300 text-[11px]">
                                                <option value="">— jurusan —</option>
                                                @foreach ($majors as $major)
                                                    <option value="{{ $major->id }}" @selected($room->major_id === $major->id)>{{ $major->name }}</option>
                                                @endforeach
                                            </select>
                                            <input type="text" name="name" value="{{ $room->name }}" class="w-full rounded border-gray-300 text-[11px]">
                                            <div class="flex flex-wrap gap-1">
                                                <select name="building_id" class="rounded border-gray-300 text-[11px]" required>
                                                    @foreach ($buildings as $building)
                                                        <option value="{{ $building->id }}" @selected($room->building_id === $building->id)>
                                                            {{ $building->name }} ({{ $building->code }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <input type="number" name="floor" value="{{ $room->floor }}" min="1" max="9" class="w-12 rounded border-gray-300 text-[11px]" required>
                                                <input type="number" name="room_no" value="{{ $room->room_no }}" min="1" max="99" title="Nomor ruangan" class="w-12 rounded border-gray-300 text-[11px]" required>
                                                <label class="flex items-center gap-1 text-[10px] text-gray-500">
                                                    <input type="checkbox" name="is_lab" value="1" @checked($room->is_lab)> Lab
                                                </label>
                                                <button class="px-2 rounded bg-slate-800 text-white text-[10px] font-semibold">Simpan</button>
                                            </div>
                                        </form>
                                        <form method="POST" action="{{ route('masters.destroy-room', $room) }}" class="mt-1" onsubmit="return confirm('Hapus ruangan ini?')">
                                            @csrf @method('DELETE')
                                            <button class="text-[10px] text-red-600 hover:underline">Hapus</button>
                                        </form>
                                    </details>
                                </td>
                                <td class="py-1.5 text-[11px] text-gray-400 text-right">
                                    {{ $room->building?->name ?? $room->building_letter }} • Lt. {{ $room->floor }} • {{ str_pad((string) $room->room_no, 2, '0', STR_PAD_LEFT) }}
                                    @if ($room->is_lab)<span class="ms-1 rounded bg-blue-50 text-blue-700 px-1">lab</span>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <form method="POST" action="{{ route('masters.store-room') }}" class="space-y-2">
                @csrf
                <select name="major_id" class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                    <option value="">— jurusan (opsional) —</option>
                    @foreach ($majors as $major)
                        <option value="{{ $major->id }}">{{ $major->name }}</option>
                    @endforeach
                </select>
                <div class="grid grid-cols-3 gap-2">
                    <select name="building_id" class="col-span-1 rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500" required>
                        <option value="">— gedung —</option>
                        @foreach ($buildings as $building)
                            <option value="{{ $building->id }}" @disabled(! $building->is_active)>
                                {{ $building->name }} ({{ $building->code }}){{ $building->is_active ? '' : ' — nonaktif' }}
                            </option>
                        @endforeach
                    </select>
                    <input type="number" name="floor" placeholder="Lantai *" min="1" max="9" class="rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500" required>
                    <input type="number" name="room_no" placeholder="No. ruang *" min="1" max="99" class="rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500" required>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <input type="text" name="name" placeholder="Nama ruangan (opsional)" class="sm:col-span-2 rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                    <label class="flex items-center gap-2 text-xs text-gray-600">
                        <input type="checkbox" name="is_lab" value="1"> Ruangan lab
                    </label>
                </div>
                <button class="w-full rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold">Tambah Ruangan</button>
            </form>
        </div>

        {{-- Jurusan --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 text-sm mb-4 flex items-center gap-1.5">
                <span class="material-icons-round text-amber-600">school</span> Jurusan / Kompetensi Keahlian
            </h3>
            <ul class="space-y-1.5 text-sm mb-4">
                @foreach ($majors as $major)
                    <li class="border-b border-dashed border-gray-100 pb-1.5">
                        <details>
                            <summary class="flex items-center justify-between cursor-pointer list-none">
                                <span class="text-gray-700">{{ $major->name }}</span>
                                <span class="text-[11px] text-gray-400">{{ $major->code ?: '-' }} • {{ $major->rooms_count }} ruang • ubah</span>
                            </summary>
                            <form method="POST" action="{{ route('masters.update-major', $major) }}" class="mt-2 flex gap-1.5">
                                @csrf @method('PUT')
                                <input type="text" name="name" value="{{ $major->name }}" class="flex-1 rounded-lg border-gray-300 text-xs" required>
                                <input type="text" name="code" value="{{ $major->code }}" class="w-20 rounded-lg border-gray-300 text-xs" placeholder="Kode">
                                <button class="px-2 rounded-lg bg-slate-800 text-white text-xs font-semibold">Simpan</button>
                            </form>
                            <form method="POST" action="{{ route('masters.destroy-major', $major) }}" class="mt-1" onsubmit="return confirm('Hapus jurusan ini?')">
                                @csrf @method('DELETE')
                                <button class="text-[11px] text-red-600 hover:underline">Hapus jurusan</button>
                            </form>
                        </details>
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('masters.store-major') }}" class="grid grid-cols-2 gap-2">
                @csrf
                <input type="text" name="name" placeholder="Nama jurusan *" class="col-span-2 rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500" required>
                <input type="text" name="code" placeholder="Kode (opsional)" class="rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                <button class="rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold">Tambah</button>
            </form>
        </div>

        {{-- Kategori --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 text-sm mb-4 flex items-center gap-1.5">
                <span class="material-icons-round text-amber-600">category</span> Kategori Barang
            </h3>
            <ul class="space-y-1.5 text-sm mb-4">
                @foreach ($categories as $category)
                    <li class="border-b border-dashed border-gray-100 pb-1.5">
                        <details>
                            <summary class="flex items-center justify-between cursor-pointer list-none">
                                <span class="text-gray-700">{{ $category->name }}</span>
                                <span class="text-[11px] text-gray-400 font-mono">{{ $category->code ?: '—' }} • {{ $category->items_count }} brg</span>
                            </summary>
                            <form method="POST" action="{{ route('masters.update-category', $category) }}" class="mt-2 space-y-1">
                                @csrf @method('PUT')
                                <input type="text" name="name" value="{{ $category->name }}" class="w-full rounded border-gray-300 text-xs" required>
                                <div class="flex gap-1 items-center">
                                    <input type="text" name="code" value="{{ $category->code }}" class="flex-1 rounded border-gray-300 text-xs" placeholder="Kode">
                                    <button class="px-2 py-1 rounded bg-slate-800 text-white text-xs font-semibold">Simpan</button>
                                </div>
                            </form>
                            <form method="POST" action="{{ route('masters.destroy-category', $category) }}" class="mt-1" onsubmit="return confirm('Hapus kategori ini?')">
                                @csrf @method('DELETE')
                                <button class="text-[11px] text-red-600 hover:underline">Hapus kategori</button>
                            </form>
                        </details>
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('masters.store-category') }}" class="space-y-2">
                @csrf
                <input type="text" name="name" placeholder="Nama kategori *" class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500" required>
                <input type="text" name="code" placeholder="Kode klasifikasi aset (01.01.11.04.01)" class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                <button class="w-full rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold">Tambah Kategori</button>
            </form>
        </div>
    </div>
</x-app-layout>
