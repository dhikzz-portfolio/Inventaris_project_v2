<x-app-layout>
    @php
        $common = array_filter([
            'period' => $period,
            'scope' => $scope,
            'scope_id' => $scopeId,
            'condition' => $condition,
        ], fn ($value) => $value !== null && $value !== '');

        $lastPage = max(1, (int) ceil($totalRows / $perPage));
        $firstRow = $totalRows === 0 ? 0 : (($page - 1) * $perPage) + 1;
        $lastRow = min($totalRows, $page * $perPage);
        $activeTable = $tables[$sheetIndex];
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Pratinjau Excel</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ $periodLabel }} • {{ $scopeLabel }}
                    @if ($condition)
                        • Kondisi: {{ \App\Enums\Condition::tryFrom($condition)?->label() ?? $condition }}
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('reports.index') }}"
                   class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                    <span class="material-icons-round text-lg" aria-hidden="true">arrow_back</span> Kembali
                </a>
                <a href="{{ route('reports.export', $common + ['type' => 'excel']) }}"
                   class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-green-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-green-700">
                    <span class="material-icons-round text-lg" aria-hidden="true">download</span> Unduh Excel
                </a>
            </div>
        </div>
    </x-slot>

    <div class="mb-4 flex flex-wrap items-center gap-x-5 gap-y-1 rounded-xl border border-sky-100 bg-white px-4 py-3 text-xs text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
        <span><strong>Periode:</strong> {{ $periodLabel }}</span>
        <span><strong>Cakupan:</strong> {{ $scopeLabel }}</span>
        <span><strong>Total unit:</strong> {{ $summary['total_unit'] }}</span>
        <span><strong>Sheet:</strong> {{ count($tables) }} tabel</span>
    </div>

    {{-- Tab sheet: nama dan urutan sama dengan file Excel. --}}
    <div class="mb-3 flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="Sheet laporan">
        @foreach ($tables as $index => $table)
            <a href="{{ route('reports.preview-excel', $common + ['sheet' => $index]) }}"
               role="tab" aria-selected="{{ $index === $sheetIndex ? 'true' : 'false' }}"
               @class([
                   'inline-flex shrink-0 items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold transition',
                   'bg-blue-600 text-white shadow-sm' => $index === $sheetIndex,
                   'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800' => $index !== $sheetIndex,
               ])>
                {{ $table['title'] }}
                <span @class([
                    'rounded-full px-1.5 py-0.5 text-[10px]',
                    'bg-white/20 text-white' => $index === $sheetIndex,
                    'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300' => $index !== $sheetIndex,
                ])>{{ count($table['rows']) }}</span>
            </a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $activeTable['title'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Menampilkan {{ $firstRow }}–{{ $lastRow }} dari {{ $totalRows }} baris
                @if ($lastPage > 1)
                    • halaman {{ $page }} dari {{ $lastPage }}
                @endif
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-xs">
                <thead class="sticky top-0 bg-slate-50 uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr>
                        <th class="px-3 py-2 text-left">#</th>
                        @foreach ($activeTable['headings'] as $heading)
                            <th class="px-3 py-2 text-left">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($rows as $offset => $row)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-3 py-2 text-slate-400">{{ (($page - 1) * $perPage) + $offset + 1 }}</td>
                            @foreach ($row as $cell)
                                <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ $cell === null || $cell === '' ? '—' : $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($activeTable['headings']) + 1 }}" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                Tidak ada data pada sheet ini untuk periode dan filter yang dipilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($lastPage > 1)
            <div class="flex items-center justify-between gap-2 border-t border-slate-100 px-4 py-3 text-xs dark:border-slate-800">
                @if ($page > 1)
                    <a href="{{ route('reports.preview-excel', $common + ['sheet' => $sheetIndex, 'page' => $page - 1]) }}"
                       class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-slate-200 px-3 font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                        <span class="material-icons-round text-base" aria-hidden="true">chevron_left</span> Sebelumnya
                    </a>
                @else
                    <span></span>
                @endif

                @if ($page < $lastPage)
                    <a href="{{ route('reports.preview-excel', $common + ['sheet' => $sheetIndex, 'page' => $page + 1]) }}"
                       class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-slate-200 px-3 font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                        Berikutnya <span class="material-icons-round text-base" aria-hidden="true">chevron_right</span>
                    </a>
                @else
                    <span></span>
                @endif
            </div>
        @endif
    </div>

    <p class="mt-3 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
        Pratinjau ini memakai definisi tabel yang sama dengan file Excel. Pagination pratinjau tidak mengubah isi file:
        seluruh baris tetap diekspor.
    </p>
</x-app-layout>
