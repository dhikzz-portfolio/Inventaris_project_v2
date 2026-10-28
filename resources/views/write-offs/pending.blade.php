<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Persetujuan Penghapusan</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Tinjau dokumen dan permintaan penghapusan barang.</p>
            </div>
            <a href="{{ route('write-offs.index') }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-800">← Barang Dihapus</a>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 flex items-start gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            <span class="material-icons-round text-lg" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="mb-6 overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-100 px-5 py-4 text-sm font-bold text-slate-900 dark:border-slate-800 dark:text-slate-100">Menunggu Persetujuan ({{ $pending->count() }})</div>
        @if ($pending->isEmpty())
            <div class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Tidak ada permohonan penghapusan yang menunggu.</div>
        @else
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($pending as $wo)
                    <article class="grid grid-cols-1 gap-4 p-5 lg:grid-cols-3">
                        <div class="lg:col-span-2">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $wo->item?->name }}</span>
                                <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-bold text-red-700 dark:bg-red-950/50 dark:text-red-300">{{ $wo->quantity }} {{ $wo->item?->unit }}</span>
                            </div>
                            <p class="mt-2 text-sm leading-relaxed text-slate-700 dark:text-slate-200">{{ $wo->reason }}</p>
                            <div class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                {{ $wo->item?->internal_id }} • Ruang {{ $wo->item?->room?->code }} •
                                Diajukan {{ $wo->created_at->diffForHumans() }} oleh {{ $wo->requester?->name }}
                            </div>
                            <a href="{{ route('files.show', ['path' => $wo->berita_acara_path]) }}" target="_blank"
                               class="mt-2 inline-flex min-h-9 items-center gap-1.5 rounded-lg px-2 text-xs font-semibold text-blue-700 transition hover:bg-blue-50 hover:underline dark:text-blue-300 dark:hover:bg-slate-800">
                                <span class="material-icons-round text-sm">description</span> Lihat Berita Acara
                            </a>
                        </div>
                        <div class="flex flex-col gap-2">
                            <form method="POST" action="{{ route('write-offs.approve', $wo) }}">
                                @csrf
                                <button class="inline-flex min-h-11 w-full items-center justify-center gap-1 rounded-xl bg-green-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                                    <span class="material-icons-round text-lg">check_circle</span> Setujui & Hapus Barang
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
        @endif
    </div>

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
                        <th class="text-left px-5 py-2.5">Keputusan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($decided as $wo)
                        <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-5 py-3 font-semibold text-slate-800 dark:text-slate-100">{{ $wo->item?->name }}</td>
                            <td class="px-5 py-2.5 text-center text-xs">{{ $wo->quantity }}</td>
                            <td class="px-5 py-2.5 text-center">
                                @if ($wo->status === 'APPROVED')
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700">DIHAPUS</span>
                                @else
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">DITOLAK</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-xs text-gray-500">{{ $wo->approver?->name }} • {{ $wo->updated_at->format('d M Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </div>
</x-app-layout>
