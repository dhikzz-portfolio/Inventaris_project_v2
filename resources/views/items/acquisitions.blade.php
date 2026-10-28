<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Persetujuan Pengadaan</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Periksa pengajuan barang sebelum memberikan keputusan.</p>
            </div>
            <a href="{{ route('items.create') }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 hover:underline dark:text-blue-300 dark:hover:bg-slate-800">+ Pengadaan baru</a>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 flex items-start gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            <span class="material-icons-round text-lg" aria-hidden="true">check_circle</span><span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="mb-6 overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-100 px-5 py-4 text-sm font-bold text-slate-800 dark:border-slate-800 dark:text-slate-100">
            Menunggu Persetujuan ({{ $pending->count() }})
        </div>
        @if ($pending->isEmpty())
            <div class="p-8 text-center text-sm text-slate-500 dark:text-slate-400">Tidak ada pengadaan yang menunggu persetujuan.</div>
        @else
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($pending as $item)
                    <div class="p-5 flex flex-col md:flex-row md:items-center gap-4">
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $item->name }}</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-800">MENUNGGU</span>
                                @if ($item->is_high_value)
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-purple-100 text-purple-800">NILAI TINGGI</span>
                                @endif
                            </div>
                            <div class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                                {{ $item->quantity }} {{ $item->unit }} • {{ $item->category?->name }} • Ruang {{ $item->room?->code }} ({{ $item->room?->name }})
                                • Tahun {{ $item->acquisition_year }} • Dana {{ $item->funding_source }}
                            </div>
                            <div class="mt-1 font-mono text-xs text-slate-500 dark:text-slate-400">
                                {{ $item->internal_id }} • Reg. {{ $item->register_no }} • Diajukan {{ $item->created_at->diffForHumans() }} oleh {{ $item->requester?->name }}
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('items.approve', $item) }}">
                                @csrf
                                <button class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-green-700">
                                    <span class="material-icons-round text-sm">check_circle</span> Setujui
                                </button>
                            </form>
                            <form method="POST" action="{{ route('items.reject', $item) }}" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <input type="text" name="note" placeholder="Alasan penolakan"
                                       class="w-full grow rounded-xl border-slate-300 bg-white text-xs text-slate-800 focus:border-red-500 focus:ring-red-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 sm:w-40 sm:grow-0">
                                <button class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-red-700">
                                    <span class="material-icons-round text-sm">cancel</span> Tolak
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-100 px-5 py-4 text-sm font-bold text-slate-800 dark:border-slate-800 dark:text-slate-100">Riwayat Keputusan Terakhir</div>
        @if ($decided->isEmpty())
            <div class="p-8 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada riwayat.</div>
        @else
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr>
                        <th class="text-left px-5 py-2.5">Barang</th>
                        <th class="text-left px-5 py-2.5">Status</th>
                        <th class="text-left px-5 py-2.5">Catatan</th>
                        <th class="text-left px-5 py-2.5">Keputusan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($decided as $item)
                        <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-5 py-2.5">
                                <a href="{{ route('items.show', $item) }}" class="font-semibold text-slate-800 hover:text-blue-700 dark:text-slate-100 dark:hover:text-blue-300">{{ $item->name }}</a>
                                <div class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $item->internal_id }}</div>
                            </td>
                            <td class="px-5 py-2.5">
                                @if ($item->acquisition_status === 'APPROVED')
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-800">DISETUJUI</span>
                                @else
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-800">DITOLAK</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-xs text-slate-600 dark:text-slate-300">{{ $item->acquisition_note ?: '—' }}</td>
                            <td class="px-5 py-2.5 text-xs text-slate-600 dark:text-slate-300">
                                {{ $item->approver?->name }} • {{ $item->acquisition_decided_at?->format('d M Y H:i') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </div>
</x-app-layout>
