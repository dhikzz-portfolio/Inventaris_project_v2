<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Persetujuan Penghapusan</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Tinjau dokumen dan permintaan penghapusan barang.</p>
            </div>
            <a href="{{ route('write-offs.index') }}" class="inline-flex min-h-10 items-center gap-1 rounded-lg px-3 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-slate-800"><span aria-hidden="true">←</span> Barang Dihapus</a>
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
        <div class="mb-6 overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <span class="text-sm font-bold text-slate-900 dark:text-slate-100">Menunggu Persetujuan</span>
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">{{ $pending->count() }} MENUNGGU</span>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($pending as $wo)
                    <article class="grid grid-cols-1 gap-4 p-5 lg:grid-cols-3">
                        <div class="lg:col-span-2">
                            <div class="flex items-center gap-2 flex-wrap">
                                <a href="{{ route('items.show', $wo->item) }}" class="font-semibold text-slate-900 hover:text-brand-700 dark:text-slate-100 dark:hover:text-brand-300">{{ $wo->item?->name }}</a>
                                <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-bold text-red-700 dark:bg-red-950/50 dark:text-red-300">{{ $wo->quantity }} {{ $wo->item?->unit }}</span>
                            </div>
                            <p class="mt-2 text-sm leading-relaxed text-slate-700 dark:text-slate-200">{{ $wo->reason }}</p>
                            <div class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                <span class="font-mono">{{ $wo->item?->internal_id }}</span> • Ruang {{ $wo->item?->room?->code }} •
                                Diajukan {{ $wo->created_at->diffForHumans() }} oleh {{ $wo->requester?->name }}
                            </div>
                            <a href="{{ route('files.show', ['path' => $wo->berita_acara_path]) }}" target="_blank"
                               class="mt-2 inline-flex min-h-9 items-center gap-1.5 rounded-lg px-2 text-xs font-semibold text-brand-700 transition hover:bg-brand-50 hover:underline dark:text-brand-300 dark:hover:bg-slate-800">
                                <span class="material-icons-round text-sm" aria-hidden="true">description</span> Lihat Berita Acara
                            </a>
                        </div>
                        <div class="flex flex-col gap-2">
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

    <div class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-100 px-5 py-4 text-sm font-bold text-slate-900 dark:border-slate-800 dark:text-slate-100">Riwayat Keputusan</div>
        @if ($decided->isEmpty())
            <div class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada keputusan sebelumnya.</div>
        @else
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">
                    <tr>
                        <th class="text-left px-5 py-2.5">Barang</th>
                        <th class="text-center px-5 py-2.5">Qty</th>
                        <th class="text-center px-5 py-2.5">Status</th>
                        <th class="text-left px-5 py-2.5">Catatan</th>
                        <th class="text-left px-5 py-2.5">Keputusan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($decided as $wo)
                        <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                            <td class="px-5 py-2.5">
                                <a href="{{ route('items.show', $wo->item) }}" class="font-medium text-slate-800 hover:text-brand-700 dark:text-slate-100 dark:hover:text-brand-300">{{ $wo->item?->name }}</a>
                                <div class="font-mono text-[11px] text-slate-400 dark:text-slate-500">{{ $wo->item?->internal_id }}</div>
                            </td>
                            <td class="px-5 py-2.5 text-center text-xs text-slate-600 dark:text-slate-300">{{ $wo->quantity }}</td>
                            <td class="px-5 py-2.5 text-center">
                                @if ($wo->status === 'APPROVED')
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-300">DIHAPUS</span>
                                @else
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">DITOLAK</span>
                                @endif
                            </td>
                            <td class="max-w-[220px] px-5 py-2.5 text-xs text-slate-500 dark:text-slate-400">{{ $wo->decision_note ?: '—' }}</td>
                            <td class="px-5 py-2.5 text-xs text-slate-500 dark:text-slate-400">
                                {{ $wo->approver?->name }}
                                <div class="text-slate-400 dark:text-slate-500">{{ $wo->updated_at->format('d M Y H:i') }}</div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </div>
</x-app-layout>
