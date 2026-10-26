<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengembalian Barang</h2>
                <p class="text-sm text-gray-500">Validasi kuantitas & kondisi akhir setiap barang sebelum pinjaman diselesaikan</p>
            </div>
            <a href="{{ route('loans.pending') }}" class="text-sm text-gray-500 hover:text-gray-800">← Persetujuan</a>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    @if ($loans->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center text-gray-400">
            <span class="material-icons-round text-6xl">assignment_return</span>
            <p class="mt-3 text-sm">Tidak ada peminjaman aktif yang menunggu pengembalian.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($loans as $loan)
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 flex flex-col md:flex-row md:items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-semibold text-gray-800">{{ $loan->borrower?->name }}</span>
                            <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                            @if ($loan->event)
                                <span class="text-[11px] text-blue-600">🎯 {{ $loan->event->name }}</span>
                            @endif
                        </div>
                        <div class="text-sm text-gray-600 truncate">{{ $loan->purpose }}</div>
                        <div class="text-xs text-gray-400">
                            {{ $loan->loanItems->count() }} barang •
                            Kembali {{ $loan->due_date->format('d M Y H:i') }}
                            @if ($loan->isOverdue())
                                <span class="font-bold text-red-600">⚠ TERLAMBAT {{ now()->diffForHumans($loan->due_date) }}</span>
                            @endif
                        </div>
                        <div class="mt-1.5 flex flex-wrap gap-1">
                            @foreach ($loan->loanItems as $li)
                                <span class="text-[10px] bg-gray-100 rounded px-1.5 py-0.5 text-gray-600">
                                    {{ $li->item?->name }} ×{{ $li->qty_borrowed }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                    <a href="{{ route('loans.return-form', $loan) }}"
                       class="w-full md:w-auto shrink-0 inline-flex items-center justify-center gap-1.5 px-4 py-3 md:py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold active:bg-blue-800">
                        <span class="material-icons-round text-lg">fact_check</span> Validasi Pengembalian
                    </a>
                </div>
            @endforeach
        </div>
        <div class="mt-5">{{ $loans->links() }}</div>
    @endif
</x-app-layout>
