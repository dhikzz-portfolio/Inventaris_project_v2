<div wire:poll.10s class="mt-5 overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
    <div class="flex flex-col gap-1 border-b border-slate-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
        <div class="flex flex-wrap items-center gap-2">
            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Riwayat Inventaris Keluar</h3>
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300" title="Riwayat diperbarui otomatis setiap 10 detik">
                <span class="relative flex h-1.5 w-1.5" aria-hidden="true">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-75"></span>
                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                </span>
                Live
            </span>
        </div>
        <span class="text-xs text-slate-500 dark:text-slate-400">
            {{ number_format($history->total(), 0, ',', '.') }} pengajuan
            <span class="text-slate-400 dark:text-slate-500">· diperbarui {{ now()->format('H:i:s') }}</span>
        </span>
    </div>
    @if ($history->isEmpty())
        <div class="px-4 py-10 text-center">
            <span class="material-icons-round text-3xl text-slate-300 dark:text-slate-600" aria-hidden="true">history</span>
            <p class="mt-2 text-sm font-medium text-slate-600 dark:text-slate-300">Belum ada riwayat inventaris keluar.</p>
        </div>
    @else
    <div class="divide-y divide-slate-100 p-3 dark:divide-slate-800 xl:hidden">
        @foreach ($history as $wo)
            @php($catalogNames = $wo->units->pluck('catalog.name')->filter()->unique())
            <article class="py-3 first:pt-0 last:pb-0">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        @if ($catalogNames->isNotEmpty())
                            <h4 class="break-words text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $catalogNames->take(2)->implode(', ') }}</h4>
                            @if ($catalogNames->count() > 2)
                                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">+{{ $catalogNames->count() - 2 }} katalog lain</p>
                            @endif
                        @elseif ($wo->item)
                            <a href="{{ route('items.show', $wo->item) }}" class="break-words text-sm font-semibold text-slate-800 hover:text-brand-700 dark:text-slate-100 dark:hover:text-brand-300">{{ $wo->item->name }}</a>
                            <p class="font-mono text-[11px] text-slate-400 dark:text-slate-500">{{ $wo->item->internal_id }}</p>
                        @else
                            <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Pengajuan inventaris keluar</h4>
                        @endif
                    </div>
                    @if ($wo->status === 'PENDING')
                        <span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-bold text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">MENUNGGU</span>
                    @elseif ($wo->status === 'APPROVED')
                        <span class="shrink-0 rounded-full bg-red-100 px-2.5 py-1 text-[10px] font-bold text-red-800 dark:bg-red-950/50 dark:text-red-300">DIHAPUS</span>
                    @else
                        <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">DITOLAK</span>
                    @endif
                </div>
                <p class="mt-2 text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($wo->reason, 120) }}</p>
                @if ($wo->decision_note)
                    <p class="mt-1 text-xs text-red-600 dark:text-red-300">Catatan: {{ $wo->decision_note }}</p>
                @endif
                <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-slate-100 pt-2 text-[11px] text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    <span class="font-semibold uppercase">{{ $wo->mode ?: 'CATALOG' }}</span>
                    <span>{{ $wo->units_count ?: $wo->quantity }} unit</span>
                    <span class="ms-auto">{{ $wo->requester?->name }} · {{ $wo->created_at->format('d M Y') }}</span>
                </div>
            </article>
        @endforeach
    </div>
    <div class="hidden overflow-x-auto xl:block">
        <table class="w-full min-w-[900px] table-fixed text-sm">
            <colgroup>
                <col class="w-[23%]">
                <col class="w-[12%]">
                <col class="w-[10%]">
                <col class="w-[27%]">
                <col class="w-[14%]">
                <col class="w-[14%]">
            </colgroup>
            <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th scope="col" class="px-3 py-3.5 text-left">Barang</th>
                    <th scope="col" class="px-2 py-3.5 text-center">Mode</th>
                    <th scope="col" class="px-2 py-3.5 text-center">Unit</th>
                    <th scope="col" class="px-3 py-3.5 text-left">Alasan</th>
                    <th scope="col" class="px-2 py-3.5 text-center">Status</th>
                    <th scope="col" class="px-3 py-3.5 text-left">Pengajuan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($history as $wo)
                    <tr class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/60">
                        <td class="px-3 py-4">
                            @php($catalogNames = $wo->units->pluck('catalog.name')->filter()->unique())
                            @if ($catalogNames->isNotEmpty())
                                <span class="font-medium text-slate-800 dark:text-slate-100">{{ $catalogNames->take(2)->implode(', ') }}</span>
                                @if ($catalogNames->count() > 2)
                                    <span class="text-[11px] text-slate-400">+{{ $catalogNames->count() - 2 }} katalog lain</span>
                                @endif
                            @else
                                @if ($wo->item)
                                    <a href="{{ route('items.show', $wo->item) }}" class="font-medium text-slate-800 hover:text-brand-700 dark:text-slate-100 dark:hover:text-brand-300">{{ $wo->item->name }}</a>
                                    <div class="font-mono text-[11px] text-slate-400">{{ $wo->item->internal_id }}</div>
                                @else
                                    <span class="text-slate-500">Pengajuan inventaris keluar</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-2 py-4 text-center">
                            <span class="inline-flex rounded-lg bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $wo->mode ?: 'CATALOG' }}</span>
                        </td>
                        <td class="px-2 py-4 text-center">
                            <span class="inline-flex min-w-9 justify-center rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold tabular-nums text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $wo->units_count ?: $wo->quantity }}</span>
                        </td>
                        <td class="max-w-[220px] px-3 py-4 text-xs text-slate-600 dark:text-slate-300">
                            {{ \Illuminate\Support\Str::limit($wo->reason, 80) }}
                            @if ($wo->decision_note)
                                <div class="mt-0.5 text-red-600 dark:text-red-300">Penolakan: {{ $wo->decision_note }}</div>
                            @endif
                        </td>
                        <td class="px-2 py-4 text-center">
                            @if ($wo->status === 'PENDING')
                                <span class="inline-flex justify-center rounded-lg bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">MENUNGGU</span>
                            @elseif ($wo->status === 'APPROVED')
                                <span class="inline-flex justify-center rounded-lg bg-red-50 px-2.5 py-1 text-[10px] font-bold text-red-700 dark:bg-red-950/50 dark:text-red-300">DIHAPUS</span>
                            @else
                                <span class="inline-flex justify-center rounded-lg bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">DITOLAK</span>
                            @endif
                        </td>
                        <td class="px-3 py-4 text-xs text-slate-600 dark:text-slate-300">
                            {{ $wo->requester?->name }}
                            <div class="mt-0.5 text-[11px] text-slate-400">{{ $wo->created_at->format('d M Y') }}</div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-10 text-center text-sm text-slate-400">Belum ada penghapusan tercatat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif
    <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $history->links() }}</div>
</div>
