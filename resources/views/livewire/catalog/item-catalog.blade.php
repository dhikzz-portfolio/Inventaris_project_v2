<div>
    {{-- Toolbar pencarian & filter --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-5">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div class="md:col-span-2 relative">
                <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                <input type="text" wire:model.live.debounce.400ms="search"
                       placeholder="Cari nama, ID internal, kode aset, register..."
                       class="w-full pl-10 pr-3 py-2.5 rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
            </div>
            <select wire:model.live="status" class="rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                <option value="">Semua Status</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </select>
            <div class="flex flex-col sm:flex-row gap-2">
                <select wire:model.live="category" class="flex-1 rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                    <option value="">Semua Kategori</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="room" class="flex-1 rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                    <option value="">Semua Ruangan</option>
                    @foreach ($rooms as $r)
                        <option value="{{ $r->id }}">{{ $r->code }} — {{ $r->name }}</option>
                    @endforeach
                </select>
                @if ($activeFilters > 0)
                    <button wire:click="resetFilters" title="Reset filter"
                            class="w-full sm:w-auto px-3 py-2.5 sm:py-0 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600">
                        <span class="material-icons-round text-lg">filter_alt_off</span>
                    </button>
                @endif
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
            <span>{{ $items->total() }} barang ditemukan</span>
            @if (\Illuminate\Support\Facades\Auth::user()->managesInventory())
                <a href="{{ route('items.create') }}" class="inline-flex items-center gap-1 text-amber-700 font-semibold hover:underline">
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
               class="group bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition overflow-hidden flex flex-col">
                <div class="h-32 bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center overflow-hidden">
                    @if ($item->photo_path)
                        <img src="{{ asset('storage/'.$item->photo_path) }}" alt="{{ $item->name }}" class="h-full w-full object-cover">
                    @else
                        <span class="material-icons-round text-5xl text-slate-400">inventory_2</span>
                    @endif
                </div>
                <div class="p-3 flex-1 flex flex-col">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="font-semibold text-sm text-gray-800 group-hover:text-amber-700 leading-snug">{{ $item->name }}</h3>
                        <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full {{ $effective->badgeClass() }}">{{ $effective->label() }}</span>
                    </div>
                    <div class="mt-1 text-[11px] text-gray-500 space-y-0.5">
                        <div class="font-mono">{{ $item->internal_id }}</div>
                        <div>{{ $item->category?->name }} • {{ $item->room?->code }} ({{ $item->room?->name }})</div>
                        <div>
                            Tersedia: <strong>{{ $item->availableQuantity() }}</strong> / {{ $item->quantity }} {{ $item->unit }}
                            @if ($item->min_stock !== null && $item->quantity <= $item->min_stock)
                                — ⚠ menipis
                            @endif
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
                        <span class="text-gray-400 group-hover:text-amber-600">Detail →</span>
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
