<x-app-layout>
    @php
    // Tombol tindakan mengikuti permission granular, bukan nama role.
    $canJurusan = auth()->user()->can('master_jurusan.edit');
    $canGedung = auth()->user()->can('master_gedung.edit');
    $canRuangan = auth()->user()->can('master_ruangan.edit');
    $canKategori = auth()->user()->can('master_kategori.edit');
    $canEdit = $canJurusan || $canGedung || $canRuangan || $canKategori;
    $masterInputClass = 'h-10 min-w-0 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';
    $masterCompactInputClass = 'h-9 min-w-0 rounded-lg border-slate-300 bg-white text-xs text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';
    $masterPrimaryButtonClass = 'inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900';
    $masterSaveButtonClass = 'inline-flex min-h-9 shrink-0 items-center justify-center rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900';
    $masterDangerButtonClass = 'inline-flex min-h-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-[11px] font-semibold text-red-700 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300 dark:hover:bg-red-950/60';
    @endphp

    <x-slot name="header">
        <div>
            <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Data Sekolah</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Gedung, ruangan, jurusan, dan kategori barang</p>
        </div>
    </x-slot>

    @unless ($canEdit)
    <div class="mb-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200">
        <strong>Akses lihat saja.</strong> Perubahan master data hanya tersedia bagi role yang memiliki
        permission tambah/ubah/hapus pada fitur terkait. Hubungi Super Admin untuk menyesuaikan permission.
    </div>
    @endunless

    @if (session('success'))
    <div role="status" class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
    <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 items-stretch gap-5 lg:grid-cols-2">
        {{-- Master Gedung --}}
        <section class="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                    <span class="material-icons-round">apartment</span>
                </span>
                <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Master Gedung</h3>
            </div>
            <p class="mt-3 min-h-10 text-xs leading-5 text-slate-500 dark:text-slate-400">
                Identitas kode gedung dipakai generator kode lokasi &amp; kode inventaris (mis. Gedung C lantai 1 ruang 03 → <span class="font-mono">C1-03-21</span>).
            </p>

            <ul class="mb-4 max-h-72 flex-1 divide-y divide-slate-100 overflow-auto rounded-xl border border-slate-200 text-sm dark:divide-slate-800 dark:border-slate-700">
                @forelse ($buildings as $building)
                <li class="px-3 py-3 transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                    @if ($canGedung)
                    <details class="group">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg text-slate-800 dark:text-slate-100">
                            <span class="flex min-w-0 flex-wrap items-center gap-2 font-semibold">
                                <span class="truncate">{{ $building->name }}</span>
                                @unless ($building->is_active)
                                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">NONAKTIF</span>
                                @endunless
                            </span>
                            <span class="flex shrink-0 items-center gap-2">
                                <span class="rounded-md bg-slate-100 px-2 py-1 font-mono text-[10px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $building->code }} · {{ $building->rooms_count }} ruang</span>
                                <span class="inline-flex min-h-7 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 text-[10px] font-semibold text-brand-700 dark:border-slate-700 dark:bg-slate-900 dark:text-brand-300">
                                    Ubah <span class="material-icons-round text-sm transition-transform group-open:rotate-180" aria-hidden="true">expand_more</span>
                                </span>
                            </span>
                        </summary>
                        <form method="POST" action="{{ route('masters.update-building', $building) }}" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_5rem_auto]">
                            @csrf @method('PUT')
                            <input type="text" name="name" value="{{ $building->name }}" aria-label="Nama gedung" class="{{ $masterCompactInputClass }}" required>
                            <input type="text" name="code" value="{{ $building->code }}" maxlength="1" aria-label="Kode gedung" class="{{ $masterCompactInputClass }} font-mono uppercase" required title="Identitas kode gedung (A-Z)">
                            <button class="{{ $masterSaveButtonClass }}">Simpan</button>
                        </form>
                        <p class="mt-2 text-[11px] leading-4 text-slate-500 dark:text-slate-400">
                            Kode gedung tidak dapat diubah bila sudah ada unit inventaris yang memakainya.
                        </p>
                        <form method="POST" action="{{ route('masters.destroy-building', $building) }}" class="mt-2" onsubmit="return confirm('Hapus gedung ini?')">
                            @csrf @method('DELETE')
                            <button class="{{ $masterDangerButtonClass }}">Hapus gedung</button>
                        </form>
                    </details>
                    @else
                    <div class="flex items-center justify-between gap-3">
                        <span class="min-w-0 truncate font-semibold text-slate-800 dark:text-slate-100">{{ $building->name }}</span>
                        <span class="shrink-0 rounded-md bg-slate-100 px-2 py-1 font-mono text-[10px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $building->code }} · {{ $building->rooms_count }} ruang</span>
                    </div>
                    @endif
                </li>
                @empty
                <li class="px-3 py-8 text-center text-xs text-slate-500 dark:text-slate-400">Belum ada data gedung.</li>
                @endforelse
            </ul>

            @if ($canGedung)
            <form method="POST" action="{{ route('masters.store-building') }}" class="mt-auto grid grid-cols-1 gap-2 border-t border-slate-100 pt-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,0.7fr)_auto] dark:border-slate-800">
                @csrf
                <input type="text" name="name" placeholder="Nama gedung *" aria-label="Nama gedung baru" class="{{ $masterInputClass }}" required>
                <input type="text" name="code" placeholder="Kode * (C)" maxlength="1" aria-label="Kode gedung baru" class="{{ $masterInputClass }} font-mono uppercase" required>
                <button class="{{ $masterPrimaryButtonClass }}">Tambah Gedung</button>
            </form>
            @endif
        </section>

        {{-- Ruangan --}}
        <section class="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                    <span class="material-icons-round">domain</span>
                </span>
                <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Ruangan <span class="ms-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $rooms->count() }}</span></h3>
            </div>
            <p class="mt-3 min-h-10 text-xs leading-5 text-slate-500 dark:text-slate-400">
                Kode lokasi dibentuk otomatis dari gedung + lantai + nomor ruangan.
            </p>

            <div class="mb-4 max-h-72 flex-1 overflow-auto rounded-xl border border-slate-200 dark:border-slate-700">
                <table class="w-full min-w-[640px] table-fixed text-sm">
                    <colgroup>
                        <col class="w-[17%]">
                        <col class="w-[28%]">
                        <col class="w-[30%]">
                        <col class="w-[25%]">
                    </colgroup>
                    <thead class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-3 py-2.5 text-left">Kode</th>
                            <th scope="col" class="px-3 py-2.5 text-left">Ruangan</th>
                            <th scope="col" class="px-3 py-2.5 text-left">Lokasi</th>
                            <th scope="col" class="px-3 py-2.5 text-right">Jurusan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($rooms as $room)
                        <tr class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                            <td class="whitespace-nowrap px-3 py-3 font-mono text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $room->code }}</td>
                            <td class="px-3 py-3 text-xs text-slate-700 dark:text-slate-200">
                                <div class="font-semibold">{{ $room->name ?: '—' }}</div>
                                @if ($canRuangan)<details class="mt-1.5">
                                    <summary class="inline-flex min-h-7 cursor-pointer list-none items-center rounded-md border border-slate-200 bg-white px-2 text-[10px] font-semibold text-brand-700 transition hover:bg-brand-50 dark:border-slate-700 dark:bg-slate-900 dark:text-brand-300 dark:hover:bg-slate-800">Ubah</summary>
                                    <form method="POST" action="{{ route('masters.update-room', $room) }}" class="mt-1 space-y-1">
                                        @csrf @method('PUT')
                                        <select name="major_id" aria-label="Jurusan ruangan" class="{{ $masterCompactInputClass }} w-full">
                                            <option value="">— jurusan —</option>
                                            @foreach ($majors as $major)
                                            <option value="{{ $major->id }}" @selected($room->major_id === $major->id)>{{ $major->name }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" name="name" value="{{ $room->name }}" aria-label="Nama ruangan" class="{{ $masterCompactInputClass }} w-full">
                                        <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-[minmax(0,1fr)_4.5rem_4.5rem_auto]">
                                            <select name="building_id" aria-label="Gedung ruangan" class="{{ $masterCompactInputClass }} col-span-2 sm:col-span-1" required>
                                                @foreach ($buildings as $building)
                                                <option value="{{ $building->id }}" @selected($room->building_id === $building->id)>
                                                    {{ $building->name }} ({{ $building->code }})
                                                </option>
                                                @endforeach
                                            </select>
                                            <input type="number" name="floor" value="{{ $room->floor }}" min="1" max="9" aria-label="Lantai" class="{{ $masterCompactInputClass }} px-2" required>
                                            <input type="number" name="room_no" value="{{ $room->room_no }}" min="1" max="99" title="Nomor ruangan" aria-label="Nomor ruangan" class="{{ $masterCompactInputClass }} px-2" required>
                                            <label class="col-span-2 flex min-h-9 items-center gap-1.5 text-[11px] text-slate-600 dark:text-slate-300 sm:col-span-1">
                                                <input type="checkbox" name="is_lab" value="1" @checked($room->is_lab)> Lab
                                            </label>
                                            <button class="{{ $masterSaveButtonClass }} col-span-2 sm:col-span-1">Simpan</button>
                                        </div>
                                    </form>
                                    <form method="POST" action="{{ route('masters.destroy-room', $room) }}" class="mt-1" onsubmit="return confirm('Hapus ruangan ini?')">
                                        @csrf @method('DELETE')
                                        <button class="{{ $masterDangerButtonClass }}">Hapus</button>
                                    </form>
                                </details>@endif
                            </td>
                            <td class="px-3 py-3 text-xs text-slate-600 dark:text-slate-300">
                                <span class="inline-flex flex-wrap items-center gap-1">
                                    <span>{{ $room->building?->name ?? $room->building_letter }} · Lt. {{ $room->floor }} · {{ str_pad((string) $room->room_no, 2, '0', STR_PAD_LEFT) }}</span>
                                    @if ($room->is_lab)<span class="rounded-md bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">LAB</span>@endif
                                </span>
                            </td>
                            <td class="px-3 py-3 text-right text-xs text-slate-600 dark:text-slate-300">{{ $room->major?->code ?: $room->major?->name ?: '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-3 py-8 text-center text-xs text-slate-500 dark:text-slate-400">Belum ada data ruangan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($canRuangan)
            <form method="POST" action="{{ route('masters.store-room') }}" class="mt-auto space-y-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                @csrf
                <select name="major_id" aria-label="Jurusan ruangan baru" class="{{ $masterInputClass }}">
                    <option value="">— jurusan (opsional) —</option>
                    @foreach ($majors as $major)
                    <option value="{{ $major->id }}">{{ $major->name }}</option>
                    @endforeach
                </select>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <select name="building_id" aria-label="Gedung ruangan baru" class="{{ $masterInputClass }} col-span-2 sm:col-span-1" required>
                        <option value="">— gedung —</option>
                        @foreach ($buildings as $building)
                        <option value="{{ $building->id }}" @disabled(! $building->is_active)>
                            {{ $building->name }} ({{ $building->code }}){{ $building->is_active ? '' : ' — nonaktif' }}
                        </option>
                        @endforeach
                    </select>
                    <input type="number" name="floor" placeholder="Lantai *" min="1" max="9" aria-label="Lantai ruangan baru" class="{{ $masterInputClass }}" required>
                    <input type="number" name="room_no" placeholder="No. ruang *" min="1" max="99" aria-label="Nomor ruangan baru" class="{{ $masterInputClass }}" required>
                </div>
                <div class="grid grid-cols-1 items-center gap-2 sm:grid-cols-3">
                    <input type="text" name="name" placeholder="Nama ruangan (opsional)" aria-label="Nama ruangan baru" class="{{ $masterInputClass }} sm:col-span-2">
                    <label class="flex min-h-10 items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                        <input type="checkbox" name="is_lab" value="1"> Ruangan lab
                    </label>
                </div>
                <button class="{{ $masterPrimaryButtonClass }} w-full">Tambah Ruangan</button>
            </form>
            @endif
        </section>

        {{-- Jurusan --}}
        <section class="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                    <span class="material-icons-round">school</span>
                </span>
                <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Jurusan / Kompetensi Keahlian</h3>
            </div>
            <p class="mt-3 min-h-10 text-xs leading-5 text-slate-500 dark:text-slate-400">Kelola jurusan yang digunakan untuk mengelompokkan ruangan sekolah.</p>
            <ul class="mb-4 max-h-72 flex-1 divide-y divide-slate-100 overflow-auto rounded-xl border border-slate-200 text-sm dark:divide-slate-800 dark:border-slate-700">
                @forelse ($majors as $major)
                <li class="px-3 py-3 transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                    <details class="group">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-slate-800 dark:text-slate-100">
                            <span class="min-w-0 truncate font-semibold">{{ $major->name }}</span>
                            <span class="flex shrink-0 items-center gap-2">
                                <span class="rounded-md bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $major->code ?: 'Tanpa kode' }} · {{ $major->rooms_count }} ruang</span>
                                @if ($canJurusan)
                                    <span class="inline-flex min-h-7 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 text-[10px] font-semibold text-brand-700 dark:border-slate-700 dark:bg-slate-900 dark:text-brand-300">
                                        Ubah <span class="material-icons-round text-sm transition-transform group-open:rotate-180" aria-hidden="true">expand_more</span>
                                    </span>
                                @endif
                            </span>
                        </summary>
                        @if ($canJurusan)
                        <form method="POST" action="{{ route('masters.update-major', $major) }}" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_6rem_auto]">
                            @csrf @method('PUT')
                            <input type="text" name="name" value="{{ $major->name }}" aria-label="Nama jurusan" class="{{ $masterCompactInputClass }}" required>
                            <input type="text" name="code" value="{{ $major->code }}" aria-label="Kode jurusan" class="{{ $masterCompactInputClass }}" placeholder="Kode">
                            <button class="{{ $masterSaveButtonClass }}">Simpan</button>
                        </form>
                        <form method="POST" action="{{ route('masters.destroy-major', $major) }}" class="mt-2" onsubmit="return confirm('Hapus jurusan ini?')">
                            @csrf @method('DELETE')
                            <button class="{{ $masterDangerButtonClass }}">Hapus jurusan</button>
                        </form>
                        @endif
                    </details>
                </li>
                @empty
                <li class="px-3 py-8 text-center text-xs text-slate-500 dark:text-slate-400">Belum ada data jurusan.</li>
                @endforelse
            </ul>
            @if ($canJurusan)
            <form method="POST" action="{{ route('masters.store-major') }}" class="mt-auto grid grid-cols-1 gap-2 border-t border-slate-100 pt-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,0.7fr)_auto] dark:border-slate-800">
                @csrf
                <input type="text" name="name" placeholder="Nama jurusan *" aria-label="Nama jurusan baru" class="{{ $masterInputClass }}" required>
                <input type="text" name="code" placeholder="Kode (opsional)" aria-label="Kode jurusan baru" class="{{ $masterInputClass }}">
                <button class="{{ $masterPrimaryButtonClass }}">Tambah</button>
            </form>
            @endif
        </section>

        {{-- Kategori --}}
        <section class="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                    <span class="material-icons-round">category</span>
                </span>
                <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Kategori Barang</h3>
            </div>
            <p class="mt-3 min-h-10 text-xs leading-5 text-slate-500 dark:text-slate-400">Atur kategori untuk membantu pencarian dan pengelompokan inventaris.</p>
            <ul class="mb-4 max-h-72 flex-1 divide-y divide-slate-100 overflow-auto rounded-xl border border-slate-200 text-sm dark:divide-slate-800 dark:border-slate-700">
                @forelse ($categories as $category)
                <li class="px-3 py-3 transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                    <details class="group">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-slate-800 dark:text-slate-100">
                            <span class="min-w-0 truncate font-semibold">{{ $category->name }}</span>
                            <span class="flex shrink-0 items-center gap-2">
                                <span class="rounded-md bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $category->items_count }} barang</span>
                                @if ($canKategori)
                                    <span class="inline-flex min-h-7 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 text-[10px] font-semibold text-brand-700 dark:border-slate-700 dark:bg-slate-900 dark:text-brand-300">
                                        Ubah <span class="material-icons-round text-sm transition-transform group-open:rotate-180" aria-hidden="true">expand_more</span>
                                    </span>
                                @endif
                            </span>
                        </summary>
                        @if ($canKategori)
                        <form method="POST" action="{{ route('masters.update-category', $category) }}" class="mt-3 space-y-2">
                            @csrf @method('PUT')
                            <input type="text" name="name" value="{{ $category->name }}" aria-label="Nama kategori" class="{{ $masterCompactInputClass }} w-full" required>
                            <button class="{{ $masterSaveButtonClass }}">Simpan</button>
                        </form>
                        <form method="POST" action="{{ route('masters.destroy-category', $category) }}" class="mt-2" onsubmit="return confirm('Hapus kategori ini?')">
                            @csrf @method('DELETE')
                            <button class="{{ $masterDangerButtonClass }}">Hapus kategori</button>
                        </form>
                        @endif
                    </details>
                </li>
                @empty
                <li class="px-3 py-8 text-center text-xs text-slate-500 dark:text-slate-400">Belum ada data kategori.</li>
                @endforelse
            </ul>
            @if ($canKategori)
            <form method="POST" action="{{ route('masters.store-category') }}" class="mt-auto space-y-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                @csrf
                <input type="text" name="name" placeholder="Nama kategori *" aria-label="Nama kategori baru" class="{{ $masterInputClass }}" required>
                <button class="{{ $masterPrimaryButtonClass }} w-full">Tambah Kategori</button>
            </form>
            @endif
        </section>
    </div>
</x-app-layout>
