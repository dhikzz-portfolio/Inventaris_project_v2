<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Master Data</h2>
            <p class="text-sm text-gray-500">Jurusan, ruangan (gedung/lantai), dan kategori barang</p>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
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

        {{-- Ruangan --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 text-sm mb-4 flex items-center gap-1.5">
                <span class="material-icons-round text-amber-600">domain</span> Ruangan ({{ $rooms->count() }})
            </h3>
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
                                            <input type="text" name="name" value="{{ $room->name }}" class="w-full rounded border-gray-300 text-[11px]" required>
                                            <div class="flex gap-1">
                                                <input type="text" name="code" value="{{ $room->code }}" class="w-16 rounded border-gray-300 text-[11px]" required>
                                                <input type="text" name="building" value="{{ $room->building }}" class="w-12 rounded border-gray-300 text-[11px]" required>
                                                <input type="number" name="floor" value="{{ $room->floor }}" class="w-12 rounded border-gray-300 text-[11px]" required>
                                                <button class="px-2 rounded bg-slate-800 text-white text-[10px] font-semibold">Simpan</button>
                                            </div>
                                        </form>
                                        <form method="POST" action="{{ route('masters.destroy-room', $room) }}" class="mt-1" onsubmit="return confirm('Hapus ruangan ini?')">
                                            @csrf @method('DELETE')
                                            <button class="text-[10px] text-red-600 hover:underline">Hapus</button>
                                        </form>
                                    </details>
                                </td>
                                <td class="py-1.5 text-[11px] text-gray-400 text-right">{{ $room->building }} Lt.{{ $room->floor }}</td>
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
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <input type="text" name="building" placeholder="Gedung * (G1)" class="rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500" required>
                    <input type="number" name="floor" placeholder="Lantai *" min="0" class="rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500" required>
                    <input type="text" name="code" placeholder="Kode * (G1-01)" class="rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500" required>
                </div>
                <input type="text" name="name" placeholder="Nama ruangan *" class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500" required>
                <button class="w-full rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold">Tambah Ruangan</button>
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
                                <span class="text-gray-700">{{ $category->name }}
                                    @if ($category->is_consumable)
                                        <span class="text-[10px] bg-teal-50 text-teal-700 rounded px-1.5 py-0.5 font-bold">HABIS PAKAI</span>
                                    @endif
                                </span>
                                <span class="text-[11px] text-gray-400 font-mono">{{ $category->code ?: '—' }} • {{ $category->items_count }} brg</span>
                            </summary>
                            <form method="POST" action="{{ route('masters.update-category', $category) }}" class="mt-2 space-y-1">
                                @csrf @method('PUT')
                                <input type="text" name="name" value="{{ $category->name }}" class="w-full rounded border-gray-300 text-xs" required>
                                <div class="flex gap-1 items-center">
                                    <input type="text" name="code" value="{{ $category->code }}" class="flex-1 rounded border-gray-300 text-xs" placeholder="Kode">
                                    <label class="flex items-center gap-1 text-[10px] text-gray-600">
                                        <input type="checkbox" name="is_consumable" value="1" @checked($category->is_consumable) class="rounded border-gray-300 text-amber-600"> Habis pakai
                                    </label>
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
                <label class="flex items-center gap-2 text-xs text-gray-600">
                    <input type="checkbox" name="is_consumable" value="1" class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"> Barang habis pakai (stok menipis)
                </label>
                <button class="w-full rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold">Tambah Kategori</button>
            </form>
        </div>
    </div>
</x-app-layout>
