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
            <p class="mt-2 min-h-8 text-xs leading-4 text-slate-500 dark:text-slate-400">
                Identitas kode gedung dipakai generator kode lokasi &amp; kode inventaris (mis. Gedung C lantai 1 ruang 03 → <span class="font-mono">C1-03-21</span>).
            </p>

            <ul class="mb-3 max-h-56 flex-1 divide-y divide-slate-100 overflow-auto rounded-xl border border-slate-200 text-sm dark:divide-slate-800 dark:border-slate-700">
                @forelse ($buildings as $building)
                <li class="px-3 py-2 transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                    @if ($canGedung)
                    <details class="group">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg text-slate-800 dark:text-slate-100">
                            <span class="flex min-w-0 flex-wrap items-center gap-2 font-semibold">
                                <span class="truncate">{{ $building->name }} ({{ $building->code }})</span>
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
            <p class="mt-2 min-h-8 text-xs leading-4 text-slate-500 dark:text-slate-400">
                Kode lokasi dibentuk otomatis dari gedung + lantai + nomor ruangan.
            </p>

</x-app-layout>
