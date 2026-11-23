<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Persetujuan Penghapusan</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Tinjau dokumen dan permintaan penghapusan barang.</p>
            </div>
            <a href="{{ route('write-offs.index') }}" class="inline-flex min-h-10 items-center gap-2 self-start rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 transition hover:border-brand-300 hover:bg-brand-50 sm:self-auto dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-brand-700 dark:hover:bg-slate-800">
                <span class="material-icons-round text-base" aria-hidden="true">arrow_back</span> Inventaris Keluar
            </a>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 flex items-start gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            <span class="material-icons-round text-lg" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <span class="material-icons-round text-lg" aria-hidden="true">error_outline</span>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    @if ($pending->isEmpty())
        <div class="mb-6 rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center dark:border-slate-700 dark:bg-slate-900">
            <span class="material-icons-round text-5xl text-green-500" aria-hidden="true">task_alt</span>
            <p class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-100">Tidak ada permohonan penghapusan yang menunggu.</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Permintaan baru akan muncul di sini.</p>
        </div>
    @else
        <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-4 sm:px-5 dark:border-slate-800">
                <span class="text-base font-bold text-slate-900 dark:text-slate-100">Menunggu Persetujuan</span>
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">{{ $pending->count() }} menunggu</span>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($pending as $wo)
                    <article class="grid grid-cols-1 gap-4 p-4 sm:p-5 xl:grid-cols-3">
                        <div class="min-w-0 xl:col-span-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('items.show', $wo->item) }}" class="font-semibold text-slate-900 hover:text-brand-700 dark:text-slate-100 dark:hover:text-brand-300">{{ $wo->item?->name }}</a>
                                <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-bold text-red-700 dark:bg-red-950/50 dark:text-red-300">{{ $wo->quantity }} unit</span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $wo->mode ?: 'CATALOG' }}</span>
                            </div>
                            <p class="mt-2 text-sm leading-relaxed text-slate-700 dark:text-slate-200">{{ $wo->reason }}</p>

                            @if ($wo->units->isNotEmpty())
                                <div class="mt-2 rounded-lg border border-slate-200 bg-slate-50 p-2 text-[11px] text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                                    <div class="font-semibold text-slate-700 dark:text-slate-200">Unit yang dihapus:</div>
                                    <ul class="mt-1 grid grid-cols-1 gap-0.5 sm:grid-cols-2">
                                        @foreach ($wo->units as $row)
                                            <li class="break-words font-mono">{{ $row->inventory_code }} — {{ $row->identityLabel() }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            <div class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                <span class="break-all font-mono">{{ $wo->item?->internal_id }}</span> • Ruang {{ $wo->item?->room?->code }} •
                                Diajukan {{ $wo->created_at->diffForHumans() }} oleh {{ $wo->requester?->name }}
                            </div>
                            @if ($wo->berita_acara_path)
                                <a href="{{ route('files.show', ['path' => $wo->berita_acara_path]) }}" target="_blank"
                                   class="mt-2 inline-flex min-h-9 items-center gap-1.5 rounded-lg px-2 text-xs font-semibold text-brand-700 transition hover:bg-brand-50 hover:underline dark:text-brand-300 dark:hover:bg-slate-800">
                                    <span class="material-icons-round text-sm" aria-hidden="true">description</span> Lihat Berita Acara
                                </a>
                            @else
                                <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">Tanpa berita acara (opsional).</p>
                            @endif
                        </div>
                        <div class="flex min-w-0 flex-col gap-2">
                            <form method="POST" action="{{ route('write-offs.approve', $wo) }}" onsubmit="return confirm('Setujui penghapusan barang ini? Barang akan diarsipkan dan tidak dapat dipinjam lagi.')">
                                @csrf
                                <button class="inline-flex min-h-11 w-full items-center justify-center gap-1 rounded-xl bg-green-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                                    <span class="material-icons-round text-lg" aria-hidden="true">check_circle</span> Setujui & Hapus Barang
                                </button>
                            </form>
                            <form method="POST" action="{{ route('write-offs.reject', $wo) }}" class="space-y-2">
                                @csrf
                                <input type="text" name="decision_note" placeholder="Alasan penolakan (opsional)"
                                       class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-red-500 focus:ring-red-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                <button class="inline-flex min-h-11 w-full items-center justify-center gap-1 rounded-xl bg-red-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                                    <span class="material-icons-round text-lg">cancel</span> Tolak
                                </button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <p class="text-sm font-bold text-slate-900 dark:text-slate-100">Riwayat Keputusan</p>
        </div>
        @if ($decided->isEmpty())
            <div class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada keputusan sebelumnya.</div>
        @else
            <div class="space-y-3 p-4 xl:hidden">
                @foreach ($decided as $wo)
                    <article class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                @if ($wo->item)
                                    <a href="{{ route('items.show', $wo->item) }}" class="break-words text-sm font-semibold text-slate-800 hover:text-brand-700 dark:text-slate-100 dark:hover:text-brand-300">{{ $wo->item->name }}</a>
                                    <p class="font-mono text-[11px] text-slate-400 dark:text-slate-500">{{ $wo->item->internal_id }}</p>
                                @else
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Pengajuan inventaris keluar</p>
                                @endif
                            </div>
                            @if ($wo->status === 'APPROVED')
                                <span class="shrink-0 rounded-full bg-red-100 px-2.5 py-1 text-[10px] font-bold text-red-800 dark:bg-red-950/50 dark:text-red-300">DIHAPUS</span>
                            @else
                                <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">DITOLAK</span>
                            @endif
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-3 border-t border-slate-100 pt-3 text-xs dark:border-slate-800">
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Jumlah</p>
                                <p class="mt-0.5 text-slate-700 dark:text-slate-300">{{ $wo->quantity }} unit</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Diputuskan oleh</p>
                                <p class="mt-0.5 break-words text-slate-700 dark:text-slate-300">{{ $wo->approver?->name ?: '—' }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Catatan</p>
                                <p class="mt-0.5 break-words text-slate-600 dark:text-slate-400">{{ $wo->decision_note ?: '—' }}</p>
                            </div>
                            <p class="col-span-2 text-[11px] text-slate-400 dark:text-slate-500">{{ $wo->updated_at->format('d M Y H:i') }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="hidden overflow-x-auto xl:block">
            <table class="w-full min-w-[860px] table-fixed text-sm">
                <colgroup>
                    <col class="w-[25%]">
                    <col class="w-[10%]">
                    <col class="w-[14%]">
                    <col class="w-[25%]">
                    <col class="w-[26%]">
                </colgroup>
                <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-3 py-3.5 text-left">Barang</th>
                        <th scope="col" class="px-2 py-3.5 text-center">Qty</th>
                        <th scope="col" class="px-2 py-3.5 text-center">Status</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Catatan</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Keputusan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($decided as $wo)
                        <tr class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/60">
                            <td class="px-3 py-4">
                                @if ($wo->item)
                                    <a href="{{ route('items.show', $wo->item) }}" class="font-semibold text-slate-800 hover:text-brand-700 dark:text-slate-100 dark:hover:text-brand-300">{{ $wo->item->name }}</a>
                                    <div class="font-mono text-[11px] text-slate-400 dark:text-slate-500">{{ $wo->item->internal_id }}</div>
                                @else
                                    <span class="text-slate-500">Pengajuan inventaris keluar</span>
                                @endif
                            </td>
                            <td class="px-2 py-4 text-center">
                                <span class="inline-flex min-w-9 justify-center rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold tabular-nums text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $wo->quantity }}</span>
                            </td>
                            <td class="px-2 py-4 text-center">
                                @if ($wo->status === 'APPROVED')
                                    <span class="inline-flex justify-center rounded-lg bg-red-50 px-2.5 py-1 text-[10px] font-bold text-red-700 dark:bg-red-950/50 dark:text-red-300">DIHAPUS</span>
                                @else
                                    <span class="inline-flex justify-center rounded-lg bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">DITOLAK</span>
                                @endif
                            </td>
                            <td class="max-w-[220px] px-3 py-4 text-xs text-slate-600 dark:text-slate-300">{{ $wo->decision_note ?: '—' }}</td>
                            <td class="px-3 py-4 text-xs text-slate-600 dark:text-slate-300">
                                {{ $wo->approver?->name }}
                                <div class="mt-0.5 text-[11px] text-slate-400 dark:text-slate-500">{{ $wo->updated_at->format('d M Y H:i') }}</div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </div>
</x-app-layout>
