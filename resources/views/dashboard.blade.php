<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
            <p class="text-sm text-gray-500">
                @if ($mode === 'operational')
                    Analitik inventaris sekolah — {{ \App\Enums\Role::tryFrom(auth()->user()->role)?->label() }}
                @else
                    Ringkasan peminjaman saya
                @endif
            </p>
        </div>
    </x-slot>

    {{-- Kartu statistik --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 md:gap-4 mb-5 md:mb-6">
        @foreach ($stats as $stat)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-3.5 md:p-4">
                <div class="flex items-center justify-between">
                    <span @class([
                        'inline-flex h-9 w-9 items-center justify-center rounded-lg',
                        'bg-slate-100 text-slate-600' => $stat['color'] === 'slate',
                        'bg-blue-100 text-blue-600' => $stat['color'] === 'blue',
                        'bg-amber-100 text-amber-600' => $stat['color'] === 'amber',
                        'bg-red-100 text-red-600' => $stat['color'] === 'red',
                        'bg-orange-100 text-orange-600' => $stat['color'] === 'orange',
                        'bg-purple-100 text-purple-600' => $stat['color'] === 'purple',
                        'bg-green-100 text-green-600' => $stat['color'] === 'green',
                    ])>
                        <span class="material-icons-round text-xl">{{ $stat['icon'] }}</span>
                    </span>
                    <span class="text-[10px] text-gray-400 uppercase tracking-wide">{{ $stat['unit'] }}</span>
                </div>
                <div class="mt-2 text-xl md:text-2xl font-bold text-gray-800 tabular-nums">{{ $stat['value'] }}</div>
                <div class="text-[11px] md:text-xs text-gray-500 leading-tight">{{ $stat['label'] }}</div>
            </div>
        @endforeach
    </div>

    @if ($mode === 'operational')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
            {{-- Grafik paling sering dipinjam --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm mb-1">Barang Paling Sering Dipinjam</h3>
                <p class="text-[11px] text-gray-400 mb-3">Bulan {{ now()->translatedFormat('F Y') }} (kuantitas)</p>
                @if ($topBorrowed->isEmpty())
                    <p class="text-sm text-gray-400 py-6 text-center">Belum ada peminjaman bulan ini.</p>
                @else
                    <div style="height: 240px"><canvas id="chartTopBorrowed"></canvas></div>
                @endif
            </div>

            {{-- Grafik paling sering rusak --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm mb-1">Barang Paling Sering Rusak</h3>
                <p class="text-[11px] text-gray-400 mb-3">Bulan {{ now()->translatedFormat('F Y') }} (laporan kerusakan)</p>
                @if ($topDamaged->isEmpty())
                    <p class="text-sm text-gray-400 py-6 text-center">Tidak ada laporan kerusakan bulan ini. 🎉</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($topDamaged as $row)
                            <li class="flex items-center justify-between text-sm border-b border-dashed border-gray-100 pb-1.5">
                                <span class="text-gray-700">{{ $row->item?->name }}</span>
                                <span class="text-xs font-bold text-red-600 bg-red-50 rounded px-2 py-0.5">{{ $row->total }}× rusak</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Distribusi kondisi --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm mb-3">Distribusi Kondisi Barang</h3>
                <div style="height: 240px"><canvas id="chartStatus"></canvas></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            {{-- Tren peminjaman --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm mb-3">Tren Peminjaman (6 Bulan)</h3>
                <div style="height: 220px"><canvas id="chartTrend"></canvas></div>
            </div>

            {{-- Alert terlambat --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
                    <span class="material-icons-round text-red-500">alarm</span> Terlambat Dikembalikan
                </h3>
                @if ($overdue->isEmpty())
                    <p class="text-sm text-gray-400">Tidak ada keterlambatan. ✅</p>
                @else
                    <ul class="space-y-2.5 text-sm">
                        @foreach ($overdue as $loan)
                            <li class="border-b border-dashed border-gray-100 pb-2">
                                <a href="{{ route('loans.show', $loan) }}" class="font-medium text-gray-800 hover:text-amber-700">{{ $loan->borrower?->name }}</a>
                                <span class="text-[11px] block text-gray-400">{{ \Illuminate\Support\Str::limit($loan->purpose, 45) }}</span>
                                <span class="text-[11px] font-bold text-red-600">Jatuh tempo {{ $loan->due_date->format('d/m/Y') }} (terlambat {{ $loan->due_date->diffForHumans(now()) }})</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Alert stok + aktivitas --}}
            <div class="space-y-5">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
                        <span class="material-icons-round text-orange-500">low_priority</span> Stok Menipis
                    </h3>
                    @if ($lowStock->isEmpty())
                        <p class="text-sm text-gray-400">Semua stok aman. ✅</p>
                    @else
                        <ul class="space-y-2 text-sm">
                            @foreach ($lowStock as $item)
                                <li class="flex items-center justify-between border-b border-dashed border-gray-100 pb-1.5">
                                    <a href="{{ route('items.show', $item) }}" class="text-gray-700 hover:text-amber-700">{{ $item->name }}</a>
                                    <span class="text-xs font-bold text-orange-600">{{ $item->quantity }}/{{ $item->min_stock }} {{ $item->unit }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
                        <span class="material-icons-round text-amber-600">history</span> Aktivitas Terakhir
                    </h3>
                    <ul class="space-y-2 text-xs">
                        @forelse ($recentActivity as $activity)
                            <li class="flex items-start gap-2 border-b border-dashed border-gray-100 pb-1.5">
                                <span class="material-icons-round text-sm text-gray-400 mt-0.5">dot</span>
                                <div>
                                    <span class="text-gray-700">{{ $activity->description }}</span>
                                    <span class="block text-gray-400">{{ $activity->causer?->name ?: 'Sistem' }} • {{ $activity->created_at->diffForHumans() }}</span>
                                </div>
                            </li>
                        @empty
                            <li class="text-gray-400">Belum ada aktivitas.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    @else
        {{-- Mode peminjam --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-800 text-sm">Pinjaman Saya Berjalan</h3>
                    <a href="{{ route('loans.create') }}" class="text-xs font-semibold text-amber-700 hover:underline">+ Ajukan Peminjaman</a>
                </div>
                @if ($myActive->isEmpty())
                    <p class="text-sm text-gray-400 py-6 text-center">Tidak ada pinjaman aktif.
                        <a href="{{ route('loans.create') }}" class="text-amber-700 hover:underline">Pinjam barang sekarang →</a>
                    </p>
                @else
                    <ul class="space-y-3">
                        @foreach ($myActive as $loan)
                            <li class="border border-gray-200 rounded-lg p-4 flex items-center justify-between gap-3 flex-wrap">
                                <div>
                                    <a href="{{ route('loans.show', $loan) }}" class="font-medium text-gray-800 hover:text-amber-700">{{ $loan->purpose }}</a>
                                    <div class="text-xs text-gray-400">
                                        {{ $loan->loanItems->count() }} barang • {{ $loan->start_date->format('d/m/Y H:i') }} → {{ $loan->due_date->format('d/m/Y H:i') }}
                                        @if ($loan->isOverdue()) <span class="font-bold text-red-600">⚠ TERLAMBAT</span> @endif
                                    </div>
                                </div>
                                <span class="text-[10px] font-bold px-2 py-1 rounded-full {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="mt-5 pt-4 border-t border-gray-100">
                    <h3 class="font-semibold text-gray-800 text-sm mb-3">Riwayat Aktivitas Saya</h3>
                    <ul class="space-y-1.5 text-xs">
                        @forelse ($recentActivity as $activity)
                            <li class="text-gray-600">{{ $activity->description }} <span class="text-gray-400">• {{ $activity->created_at->diffForHumans() }}</span></li>
                        @empty
                            <li class="text-gray-400">Belum ada aktivitas.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="space-y-5">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <h3 class="font-semibold text-gray-800 text-sm mb-3">Frekuensi Pengajuan (6 Bulan)</h3>
                    <div style="height: 220px"><canvas id="chartTrend"></canvas></div>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 text-sm">
                    <h3 class="font-semibold text-gray-800 text-sm mb-2">Jelajahi</h3>
                    <p class="text-gray-500 text-xs mb-3">Terdapat {{ $catalogCount }} barang terdaftar di katalog sekolah.</p>
                    <a href="{{ route('items.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold">
                        <span class="material-icons-round text-sm">inventory_2</span> Buka Katalog
                    </a>
                </div>
            </div>
        </div>
    @endif

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const trendLabels = @json(collect($loansPerMonth)->pluck('label'));
            const trendData = @json(collect($loansPerMonth)->pluck('count'));

            const trendCanvas = document.getElementById('chartTrend');
            if (trendCanvas && window.Chart) {
                new Chart(trendCanvas, {
                    type: 'line',
                    data: {
                        labels: trendLabels,
                        datasets: [{
                            label: 'Peminjaman',
                            data: trendData,
                            borderColor: '#d97706',
                            backgroundColor: 'rgba(217,119,6,0.12)',
                            fill: true,
                            tension: 0.35,
                            pointRadius: 4,
                        }]
                    },
                    options: {
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                        maintainAspectRatio: false,
                    }
                });
            }

            @if ($mode === 'operational')
                const borrowCanvas = document.getElementById('chartTopBorrowed');
                if (borrowCanvas && window.Chart) {
                    new Chart(borrowCanvas, {
                        type: 'bar',
                        data: {
                            labels: @json($topBorrowed->map(fn ($r) => \Illuminate\Support\Str::limit($r->item?->name ?? '-', 18))->values()),
                            datasets: [{
                                label: 'Jumlah dipinjam',
                                data: @json($topBorrowed->pluck('total')->values()),
                                backgroundColor: ['#f59e0b', '#fbbf24', '#fcd34d', '#fde68a', '#fef3c7'],
                                borderRadius: 6,
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            plugins: { legend: { display: false } },
                            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
                            maintainAspectRatio: false,
                        }
                    });
                }

                const statusCanvas = document.getElementById('chartStatus');
                if (statusCanvas && window.Chart) {
                    new Chart(statusCanvas, {
                        type: 'doughnut',
                        data: {
                            labels: @json($statusDistribution->pluck('label')),
                            datasets: [{
                                data: @json($statusDistribution->pluck('value')),
                                backgroundColor: ['#22c55e', '#eab308', '#f97316', '#ef4444', '#64748b'],
                                borderWidth: 2,
                            }]
                        },
                        options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }, maintainAspectRatio: false }
                    });
                }
            @endif
        });
    </script>
    @endpush
</x-app-layout>
