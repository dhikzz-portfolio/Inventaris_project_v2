<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Data Peminjaman</h2>
                <p class="text-sm text-gray-500">
                    @if (auth()->user()->isPeminjam() && ! auth()->user()->canApproveLoans())
                        Riwayat peminjaman saya
                    @else
                        Seluruh peminjaman sesuai cakupan role Anda
                    @endif
                </p>
            </div>
            <a href="{{ route('loans.create') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold">
                <span class="material-icons-round text-lg">add_shopping_cart</span> Ajukan Peminjaman
            </a>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Tab status --}}
    <div class="flex flex-wrap gap-2 mb-5">
        @php($tabs = [
            '' => ['Semua', $counts['all']],
            'PENDING' => ['Menunggu', $counts['PENDING']],
            'APPROVED' => ['Disetujui', $counts['APPROVED']],
            'DIPINJAM' => ['Dipinjam', $counts['DIPINJAM']],
            'SELESAI' => ['Selesai', $counts['SELESAI']],
            'DIBATALKAN' => ['Dibatalkan', $counts['DIBATALKAN']],
        ])
        @foreach ($tabs as $key => [$label, $count])
            <a href="{{ route('loans.index', array_filter(['status' => $key])) }}"
               @class([
                   'px-4 py-2 rounded-lg text-sm font-medium border',
                   'bg-slate-800 text-white border-slate-800' => ($status ?? '') === $key,
                   'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' => ($status ?? '') !== $key,
               ])>
                {{ $label }} <span class="text-xs opacity-70">({{ $count }})</span>
            </a>
        @endforeach
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="text-left px-5 py-3">#</th>
                        <th class="text-left px-5 py-3">Peminjam</th>
                        <th class="text-left px-5 py-3">Tujuan / Kegiatan</th>
                        <th class="text-left px-5 py-3">Periode</th>
                        <th class="text-center px-5 py-3">Barang</th>
                        <th class="text-center px-5 py-3">Status</th>
                        <th class="text-center px-5 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($loans as $loan)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-mono text-xs text-gray-400">#{{ $loan->id }}</td>
                            <td class="px-5 py-3">
                                <div class="font-medium text-gray-800">{{ $loan->borrower?->name }}</div>
                                <div class="text-[11px] text-gray-400">{{ $loan->borrower?->major?->name ?: $loan->borrower?->role }}</div>
                            </td>
                            <td class="px-5 py-3 max-w-[220px]">
                                <div class="text-gray-700">{{ \Illuminate\Support\Str::limit($loan->purpose, 60) }}</div>
                                @if ($loan->event)
                                    <span class="inline-flex items-center gap-1 text-[11px] text-indigo-600">
                                        <span class="material-icons-round text-sm">event</span> {{ $loan->event->name }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-xs text-gray-500">
                                {{ $loan->start_date->format('d/m/Y H:i') }}<br>
                                {{ $loan->due_date->format('d/m/Y H:i') }}
                                @if ($loan->isOverdue())
                                    <span class="block text-[10px] font-bold text-red-600 uppercase">⚠ Terlambat {{ $loan->due_date->diffForHumans(now()) }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-center text-xs text-gray-600">
                                {{ $loan->loanItems->count() }} item
                                @if ($loan->needs_high_value_approval)
                                    <span class="block text-[10px] font-bold text-purple-600">NILAI TINGGI</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="text-[10px] font-bold px-2 py-1 rounded-full {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                                @if ($loan->status === \App\Enums\LoanStatus::APPROVED)
                                    <span class="block text-[9px] text-gray-400 mt-1">belum diserahkan</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-center">
                                <a href="{{ route('loans.show', $loan) }}"
                                   class="text-xs font-semibold text-amber-700 hover:underline">Detail →</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-gray-400 text-sm">
                                Belum ada data peminjaman.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $loans->links() }}</div>
</x-app-layout>
