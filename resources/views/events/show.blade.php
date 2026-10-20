<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $event->name }}</h2>
                    @if ($event->end_date->isPast())
                        <span class="text-[11px] font-bold px-2 py-1 rounded-full bg-gray-200 text-gray-600">SELESAI</span>
                    @elseif ($event->start_date->isFuture())
                        <span class="text-[11px] font-bold px-2 py-1 rounded-full bg-blue-100 text-blue-700">MENDATANG</span>
                    @else
                        <span class="text-[11px] font-bold px-2 py-1 rounded-full bg-green-100 text-green-700">BERLANGSUNG</span>
                    @endif
                </div>
                <p class="text-sm text-gray-500">
                    PJ: {{ $event->pic_name }} • {{ $event->start_date->format('d M Y') }} — {{ $event->end_date->format('d M Y') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if (auth()->user()->managesInventory())
                    <a href="{{ route('events.bast', $event) }}" target="_blank"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
                        <span class="material-icons-round text-lg">description</span> BAST Gabungan (PDF)
                    </a>
                @endif
                <a href="{{ route('events.index') }}" class="text-sm text-gray-500 hover:text-gray-800">← Semua kegiatan</a>
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    @if ($event->description)
        <div class="mb-5 rounded-xl bg-white border border-gray-200 p-4 text-sm text-gray-600">{{ $event->description }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 text-sm mb-4 flex items-center gap-1.5">
                <span class="material-icons-round text-amber-600">receipt_long</span> Peminjaman Terikat Kegiatan ({{ $event->loans->count() }})
            </h3>

            @if ($event->loans->isEmpty())
                <p class="text-sm text-gray-400">Belum ada peminjaman terikat. Pilih kegiatan ini saat mengajukan peminjaman.</p>
            @else
                <div class="space-y-4">
                    @foreach ($event->loans as $loan)
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <div>
                                    <a href="{{ route('loans.show', $loan) }}" class="font-semibold text-gray-800 hover:text-amber-700">#{{ $loan->id }} — {{ $loan->borrower?->name }}</a>
                                    <div class="text-xs text-gray-400">{{ $loan->start_date->format('d/m/Y H:i') }} → {{ $loan->due_date->format('d/m/Y H:i') }}</div>
                                </div>
                                <span class="text-[10px] font-bold px-2 py-1 rounded-full {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                            </div>
                            <ul class="mt-2 space-y-1">
                                @foreach ($loan->loanItems as $li)
                                    <li class="flex items-center justify-between text-xs text-gray-600 border-b border-dashed border-gray-100 pb-1">
                                        <span>{{ $li->item?->name }} <span class="font-mono text-gray-400">({{ $li->item?->internal_id }})</span></span>
                                        <span>×{{ $li->qty_borrowed }}
                                            @if ($li->qty_returned !== null)
                                                • kembali {{ $li->qty_returned }}
                                                @if ($li->hasShortage())<span class="text-red-600 font-bold"> (HILANG {{ $li->missingQuantity() }})</span>@endif
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                            @if ($loan->bast_path)
                                <a href="{{ route('loans.bast', $loan) }}" target="_blank" class="mt-2 inline-flex items-center gap-1 text-xs text-indigo-600 font-semibold hover:underline">
                                    <span class="material-icons-round text-sm">description</span> BAST/Surat pinjaman ini
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="space-y-5">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
                    <span class="material-icons-round text-amber-600">inventory_2</span> ID Barang Dibooking ({{ $bookedItems->count() }})
                </h3>
                @if ($bookedItems->isEmpty())
                    <p class="text-sm text-gray-400">Belum ada.</p>
                @else
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($bookedItems as $id)
                            @php($linked = \App\Models\Item::where('internal_id', $id)->first())
                            @if ($linked)
                                <a href="{{ route('items.show', $linked) }}"
                                   class="text-[11px] font-mono bg-slate-100 hover:bg-slate-200 text-slate-700 rounded px-2 py-1">{{ $id }}</a>
                            @else
                                <span class="text-[11px] font-mono bg-slate-100 text-slate-500 rounded px-2 py-1">{{ $id }}</span>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 text-sm">
                <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
                    <span class="material-icons-round text-amber-600">how_to_reg</span> Alur Kegiatan
                </h3>
                <ol class="list-decimal ms-4 space-y-1.5 text-gray-600 text-xs">
                    <li>Peminjam mengajukan peminjaman <strong>dengan memilih kegiatan ini</strong>.</li>
                    <li>Laboran menyetujui (Wakasek untuk barang bernilai tinggi).</li>
                    <li>BAST/Surat Peminjaman Resmi digenerate otomatis (PDF siap tanda tangan).</li>
                    <li>Seluruh barang dikembalikan & dilaporkan sebagai <strong>satu paket</strong>.</li>
                </ol>
            </div>
        </div>
    </div>
</x-app-layout>
