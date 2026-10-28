<div>
    {{-- Toolbar pencarian & filter --}}
    <div class="mb-5 rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="relative col-span-full">
                <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                <input type="text" wire:model.live.debounce.400ms="search"
                       placeholder="Cari nama, ID internal, kode unit, register..."
                       class="w-full rounded-xl border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
            </div>
            <select wire:model.live="status" class="w-full min-w-0 rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                <option value="">Semua Status</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </select>
            <select wire:model.live="category" class="w-full min-w-0 rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                <option value="">Semua Kategori</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="room" class="w-full min-w-0 rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                <option value="">Semua Ruangan</option>
                @foreach ($rooms as $r)
                    <option value="{{ $r->id }}">{{ $r->code }} — {{ $r->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600 dark:text-slate-300">
            <div class="flex items-center gap-2">
                <span>{{ $items->total() }} barang ditemukan</span>
                @if ($activeFilters > 0)
                    <button wire:click="resetFilters" title="Reset filter"
                            class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                        <span class="material-icons-round text-sm">filter_alt_off</span> Reset filter
                    </button>
                @endif
            </div>
            @if (\Illuminate\Support\Facades\Auth::user()->managesInventory())
                <a href="{{ route('items.create') }}" class="inline-flex items-center gap-1 font-semibold text-blue-700 hover:underline dark:text-blue-300">
                    <span class="material-icons-round text-base">add_box</span> Tambah Barang (Pengadaan)
                </a>
            @endif
        </div>
    </div>

    {{-- Grid barang --}}
    <div wire:loading.class="opacity-60" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        @forelse ($items as $item)
            @php($effective = $item->effectiveStatus())
            <a href="{{ route('items.show', $item) }}"
               class="group flex flex-col overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm transition hover:shadow-md dark:border-slate-700 dark:bg-slate-900">
                <div class="h-32 bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center overflow-hidden">
                    @if ($item->photo_path)
                        <img src="{{ asset('storage/'.$item->photo_path) }}" alt="{{ $item->name }}" class="h-full w-full object-cover">
                    @else
                        <span class="material-icons-round text-5xl text-slate-400">inventory_2</span>
                    @endif
                </div>
                <div class="p-3 flex-1 flex flex-col">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="text-sm font-semibold leading-snug text-slate-800 group-hover:text-blue-700 dark:text-slate-100 dark:group-hover:text-blue-300">{{ $item->name }}</h3>
                        <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full {{ $effective->badgeClass() }}">{{ $effective->label() }}</span>
                    </div>
                    <div class="mt-1 space-y-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                        <div class="font-mono">{{ $item->internal_id }}</div>
                        <div>{{ $item->category?->name }} • {{ $item->room?->code }} ({{ $item->room?->name }})</div>
                        <div>
                            Tersedia: <strong>{{ $item->availableQuantity() }}</strong> / {{ $item->quantity }} {{ $item->unit }}
                        </div>
                        @if ($item->qty_damaged > 0 || $item->qty_lost > 0)
                            <div class="text-[10px] text-red-600 font-semibold">
                                @if ($item->qty_damaged > 0) rusak {{ $item->qty_damaged }}@endif
                                @if ($item->qty_damaged > 0 && $item->qty_lost > 0) • @endif
                                @if ($item->qty_lost > 0) hilang {{ $item->qty_lost }}@endif
                            </div>
                        @endif
                    </div>
                    <div class="mt-auto pt-2 flex items-center justify-between text-[11px]">
                        @if ($item->is_high_value)
                            <span class="inline-flex items-center gap-1 text-purple-700 bg-purple-50 rounded px-1.5 py-0.5 font-medium">
                                <span class="material-icons-round text-xs">workspace_premium</span> Nilai Tinggi
                            </span>
                        @else
                            <span class="text-gray-400">Reg. {{ $item->register_no }}</span>
                        @endif
                        <span class="text-gray-400 group-hover:text-blue-600">Detail →</span>
                    </div>
                </div>
            </a>
        @empty
            <div class="col-span-full py-16 text-center text-gray-400">
                <span class="material-icons-round text-6xl">search_off</span>
                <p class="mt-2 text-sm">Tidak ada barang yang cocok dengan pencarian/filter.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $items->links() }}
    </div>
</div>
