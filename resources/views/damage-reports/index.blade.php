<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Laporan Kerusakan</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pemantauan kondisi barang. Laporan tidak menghapus unit maupun mengurangi stok.</p>
            </div>
            @if (auth()->user()->isLaboran() || auth()->user()->isSuperAdmin())
                <a href="{{ route('damage-reports.create') }}" class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                    <span class="material-icons-round text-lg" aria-hidden="true">report</span> Laporkan Kerusakan
                </a>
            @endif
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">{{ session('success') }}</div>
    @endif

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/30">
            <div class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Rusak Ringan</div>
            <div class="mt-1 text-2xl font-bold text-amber-900 dark:text-amber-100">{{ $summary['ringan'] }}</div>
        </div>
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30">
            <div class="text-xs font-semibold uppercase tracking-wide text-red-700 dark:text-red-300">Rusak Berat</div>
            <div class="mt-1 text-2xl font-bold text-red-900 dark:text-red-100">{{ $summary['berat'] }}</div>
        </div>
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 dark:border-sky-900 dark:bg-sky-950/30">
            <div class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">Belum Selesai</div>
            <div class="mt-1 text-2xl font-bold text-sky-900 dark:text-sky-100">{{ $summary['terbuka'] }}</div>
        </div>
    </div>

    <form method="GET" action="{{ route('damage-reports.index') }}" class="mb-4 rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div>
                <x-input-label for="q" value="Cari kode / nama barang" />
                <input id="q" name="q" type="text" value="{{ $filters['q'] ?? '' }}" placeholder="mis. G1-02 atau Kursi"
                       class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
            </div>
            <div>
                <x-input-label for="classification" value="Klasifikasi" />
                <select id="classification" name="classification" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">Semua</option>
                    @foreach ($classifications as $classification)
                        <option value="{{ $classification->value }}" @selected(($filters['classification'] ?? '') === $classification->value)>{{ $classification->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="status" value="Status Penanganan" />
                <select id="status" name="status" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">Semua</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="room_id" value="Ruangan" />
                <select id="room_id" name="room_id" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">Semua ruangan</option>
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}" @selected((string) ($filters['room_id'] ?? '') === (string) $room->id)>{{ $room->code }} — {{ $room->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-3 flex justify-end gap-2">
            <a href="{{ route('damage-reports.index') }}" class="inline-flex min-h-9 items-center rounded-lg px-3 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Reset</a>
            <button class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-slate-800 px-4 text-xs font-semibold text-white hover:bg-slate-900">Terapkan</button>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-sm">
                <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-2.5 text-left">Unit</th>
                        <th class="px-4 py-2.5 text-left">Barang</th>
                        <th class="px-4 py-2.5 text-left">Lokasi</th>
                        <th class="px-4 py-2.5 text-left">Klasifikasi</th>
                        <th class="px-4 py-2.5 text-left">Status</th>
                        <th class="px-4 py-2.5 text-left">Pelapor</th>
                        <th class="px-4 py-2.5 text-left">Tanggal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($reports as $report)
                        <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                            <td class="px-4 py-2.5 font-mono text-xs text-slate-700 dark:text-slate-200">{{ $report->unit?->inventory_code }}</td>
                            <td class="px-4 py-2.5">
                                <a href="{{ route('damage-reports.show', $report) }}" class="font-semibold text-slate-800 hover:text-blue-700 dark:text-slate-100 dark:hover:text-blue-300">{{ $report->unit?->catalog?->name }}</a>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ $report->unit?->specification?->displayName() }}</div>
                            </td>
                            <td class="px-4 py-2.5 text-xs text-slate-600 dark:text-slate-300">{{ $report->unit?->room?->code }}</td>
                            <td class="px-4 py-2.5">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $report->classification->badgeClass() }}">{{ $report->classification->label() }}</span>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $report->status->badgeClass() }}">{{ $report->status->label() }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-xs text-slate-600 dark:text-slate-300">{{ $report->reporter?->name }}</td>
                            <td class="px-4 py-2.5 text-xs text-slate-500 dark:text-slate-400">{{ $report->created_at->format('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada laporan kerusakan pada ruang lingkup Anda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $reports->links() }}</div>
    </div>
</x-app-layout>
