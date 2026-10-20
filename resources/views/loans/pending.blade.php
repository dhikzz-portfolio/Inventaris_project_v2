<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Persetujuan Peminjaman</h2>
                    <span class="text-[11px] font-bold px-2 py-1 rounded-full bg-yellow-100 text-yellow-800">{{ $loans->count() }} MENUNGGU</span>
                </div>
                <p class="text-sm text-gray-500">Laboran menyetujui lab jurusannya • barang bernilai tinggi lanjut ke Wakasek</p>
            </div>
            <a href="{{ route('loans.returns') }}" class="text-sm text-gray-500 hover:text-gray-800">Pengembalian →</a>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    @if ($loans->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center text-gray-400">
            <span class="material-icons-round text-6xl">how_to_reg</span>
            <p class="mt-3 text-sm">Tidak ada request peminjaman yang menunggu persetujuan. 🎉</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($loans as $loan)
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold text-gray-800">{{ $loan->borrower?->name }}</span>
                                <span class="text-[11px] text-gray-400">{{ $loan->borrower?->major?->name ?: $loan->borrower?->role }}</span>
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                                @if ($loan->needs_high_value_approval)
                                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-purple-100 text-purple-800">NILAI TINGGI — L1 → L2 + TTD</span>
                                @endif
                            </div>
                            <div class="text-sm text-gray-600 mt-1">{{ $loan->purpose }}</div>
                            <div class="text-xs text-gray-400 mt-0.5">
                                {{ $loan->start_date->format('d M Y H:i') }} → {{ $loan->due_date->format('d M Y H:i') }}
                                @if ($loan->event) • Kegiatan: <strong>{{ $loan->event->name }}</strong> (PJ: {{ $loan->event->pic_name }}) @endif
                                @if ($loan->isOverdue()) <span class="text-red-600 font-bold">⚠ TERLAMBAT</span> @endif
                            </div>

                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($loan->loanItems as $li)
                                    <span class="inline-flex items-center gap-1 text-[11px] bg-gray-100 rounded px-2 py-1 text-gray-600">
                                        {{ $li->item?->name }}
                                        <strong>×{{ $li->qty_borrowed }}</strong>
                                        @if ($li->item?->is_high_value)
                                            <span class="material-icons-round text-xs text-purple-500">workspace_premium</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        </div>

                        <div class="shrink-0">
                            <a href="{{ route('loans.show', $loan) }}"
                               class="w-full lg:w-auto inline-flex items-center justify-center gap-1.5 px-4 py-3 lg:py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold active:bg-slate-950">
                                <span class="material-icons-round text-lg">gavel</span> Proses Persetujuan
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
