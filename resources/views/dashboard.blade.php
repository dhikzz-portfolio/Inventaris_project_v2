@php
    $dashboardUser = auth()->user();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand-600 dark:text-brand-400">
                    @if ($mode !== 'operational')
                        Ringkasan peminjaman
                    @elseif ($dashboardUser->isSuperAdmin())
                        Ringkasan sekolah
                    @else
                        Ringkasan laboratorium
                    @endif
                </p>
                <h2 class="mt-1 text-xl font-bold leading-tight text-slate-900 dark:text-white">Ringkasan</h2>
                <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                    @if ($mode === 'operational')
                        Ringkasan barang dan peminjaman untuk {{ $scopeLabel }}.
                    @else
                        Ringkasan pengajuan dan pinjaman barang Anda.
                    @endif
                </p>
            </div>
            <div class="hidden shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600 sm:inline-flex dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                <span class="material-icons-round text-base text-brand-600 dark:text-brand-400" aria-hidden="true">calendar_today</span>
                {{ now()->translatedFormat('l, d F Y') }}
            </div>
        </div>
    </x-slot>

    <div class="min-h-full -m-4 bg-sky-50/80 p-4 sm:-m-5 sm:p-5 md:-m-6 md:p-6 dark:bg-slate-950/30">
        <section class="relative isolate mb-4 overflow-hidden rounded-2xl bg-gradient-to-r from-sky-200 via-sky-100 to-cyan-100 p-5 text-slate-800 shadow-lg shadow-sky-900/5 sm:p-6 dark:from-slate-800 dark:via-slate-800 dark:to-slate-700 dark:text-white">
            <div class="pointer-events-none absolute -right-2 -top-8 z-0 hidden h-44 w-56 opacity-90 sm:block" aria-hidden="true">
                <span class="absolute right-7 top-0 h-24 w-24 rounded-full bg-amber-100/90 blur-sm dark:bg-amber-500/10"></span>
                <span class="absolute right-14 top-14 h-20 w-24 rounded-full bg-white/90 dark:bg-slate-600/30"></span>
                <span class="absolute right-2 top-20 h-16 w-24 rounded-full bg-white/90 dark:bg-slate-600/30"></span>
                <span class="absolute right-20 top-24 h-14 w-20 rounded-full bg-white/90 dark:bg-slate-600/30"></span>
            </div>
            <div class="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-sky-800 dark:text-sky-200">Selamat datang, {{ $dashboardUser->greetingName() }}</p>
                    <h3 class="mt-1 text-xl font-bold tracking-tight sm:text-2xl">
                        @if ($mode !== 'operational')
                            Ajukan peminjaman barang
                        @elseif ($dashboardUser->isSuperAdmin())
                            Pantau barang sekolah                         @elseif ($dashboardUser->isEditorValidator())
                            Kelola barang laboratorium
                        @else
                            Barang laboratorium Anda
                        @endif
                    </h3>
                    <p class="mt-2 max-w-2xl text-xs leading-relaxed text-sky-800/80 sm:text-sm dark:text-slate-300">
                        @if ($mode !== 'operational')
                            Ajukan peminjaman dan pantau statusnya di sini.
                        @elseif ($dashboardUser->isSuperAdmin())
                            Lihat kondisi barang, permintaan pinjam, dan kegiatan terbaru di {{ $scopeLabel }}.                         @elseif ($dashboardUser->isEditorValidator())
                            Kelola barang dan permintaan pinjam dari {{ $scopeLabel }}.
                        @else
                            Tinjau barang dan permintaan pinjam di {{ $scopeLabel }}.
                        @endif
                    </p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row lg:shrink-0">
                    @if ($mode !== 'operational')
                        <a href="{{ route('loans.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <span class="material-icons-round text-lg" aria-hidden="true">add_shopping_cart</span>
                            Ajukan peminjaman
                        </a>
                        <a href="{{ route('catalogs.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-brand-200 bg-white/50 px-4 py-2.5 text-sm font-semibold text-brand-800 transition hover:bg-white/80 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:hover:bg-slate-600">
                            <span class="material-icons-round text-lg" aria-hidden="true">inventory_2</span>
                            Lihat katalog
                        </a>
                    @else
                        @if ($dashboardUser->managesInventory())
                            <a href="{{ route('items.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
                                <span class="material-icons-round text-lg" aria-hidden="true">add_box</span>
                                Tambah barang
                            </a>
                        @else
                            <a href="{{ route('scan') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
                                <span class="material-icons-round text-lg" aria-hidden="true">qr_code_scanner</span>
                                Pindai kode barang
                            </a>
                        @endif
                        <a href="{{ route('loans.pending') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-brand-200 bg-white/80 px-4 py-2.5 text-sm font-semibold text-brand-800 transition hover:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:hover:bg-slate-600">
                            <span class="material-icons-round text-lg" aria-hidden="true">how_to_reg</span>
                            Permintaan pinjam
                            @if ($stats[3]['value'] > 0)
                                <span class="rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-700 dark:bg-red-900 dark:text-red-100">{{ $stats[3]['value'] }}</span>
                            @endif
                        </a>
                        <a href="{{ route('catalogs.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-brand-200 bg-white/50 px-4 py-2.5 text-sm font-semibold text-brand-800 transition hover:bg-white/80 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:hover:bg-slate-600">
                            <span class="material-icons-round text-lg" aria-hidden="true">inventory_2</span>
                            Lihat barang
                        </a>
                    @endif
                </div>
            </div>
        </section>

        <div @class([
            'mb-4 grid grid-cols-2 gap-3',
            'sm:grid-cols-3 xl:grid-cols-6' => $mode === 'operational',
            'sm:grid-cols-2 xl:grid-cols-4' => $mode !== 'operational',
        ])>
            @foreach ($stats as $stat)
                <x-dashboard.stat-card :stat="$stat" operational />
            @endforeach
        </div>

        @php
            $borrowChartColors = ['#0875b9', '#169fe0', '#43b9ef', '#83d4f5', '#beeafb'];
            $statusChartColors = ['#0875b9', '#38bdf8', '#f59e0b', '#ef4444', '#64748b'];
            $emptyStateClass = 'flex min-h-32 flex-col items-center justify-center rounded-xl border border-dashed px-4 text-center';
        @endphp

        @if ($mode === 'operational')
            {{-- Grid dua kolom setara: setiap baris berisi dua kartu dengan tinggi sama. --}}
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                <x-dashboard.panel title="Barang paling sering dipinjam" description="Bulan {{ now()->translatedFormat('F Y') }} · berdasarkan jumlah barang">
                    @if ($topBorrowed->isEmpty())
                        <div class="{{ $emptyStateClass }} border-slate-200 bg-slate-50/70 dark:border-slate-700 dark:bg-slate-800/50">
                            <span class="material-icons-round text-3xl text-slate-300 dark:text-slate-600" aria-hidden="true">bar_chart</span>
                            <p class="mt-2 text-sm font-medium text-slate-600 dark:text-slate-300">Belum ada data peminjaman</p>
                            <p class="mt-1 text-xs text-slate-400">Data bulan ini akan muncul di sini.</p>
                        </div>
                    @else
                        <div class="h-44"><canvas id="chartTopBorrowed" aria-label="Grafik barang paling sering dipinjam bulan ini"></canvas></div>
                    @endif
                </x-dashboard.panel>

                <x-dashboard.panel title="Barang yang sering rusak" description="Bulan {{ now()->translatedFormat('F Y') }} · barang yang dilaporkan rusak">
                    @if ($topDamaged->isEmpty())
                        <div class="{{ $emptyStateClass }} border-green-200 bg-green-50/60 dark:border-green-900 dark:bg-green-950/20">
                            <span class="material-icons-round text-3xl text-green-500" aria-hidden="true">task_alt</span>
                            <p class="mt-2 text-sm font-medium text-slate-700 dark:text-slate-200">Tidak ada laporan kerusakan</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Kondisi barang tercatat baik bulan ini.</p>
                        </div>
                    @else
                        <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach ($topDamaged as $row)
                                <li class="flex items-center justify-between gap-3 py-2.5 text-sm first:pt-0">
                                    <span class="min-w-0 truncate font-medium text-slate-700 dark:text-slate-200">{{ $row->item?->name }}</span>
                                    <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-red-50 px-2.5 py-1 text-[11px] font-bold text-red-700 dark:bg-red-950/40 dark:text-red-300">
                                        <span class="material-icons-round text-sm" aria-hidden="true">build</span>
                                        {{ $row->total }}× rusak
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-dashboard.panel>

                <x-dashboard.panel title="Kondisi barang" description="Kondisi barang yang berada di {{ $scopeLabel }}.">
                    @php($statusTotal = $statusDistribution->sum('value'))
                    <div class="flex h-full flex-col items-center justify-center gap-4">
                        <div class="relative h-40 w-40 shrink-0 sm:h-44 sm:w-44">
                            <canvas id="chartStatus" aria-label="Grafik kondisi barang"></canvas>
                            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                                <span class="text-xl font-bold tabular-nums text-slate-900 dark:text-slate-100">{{ $statusTotal }}</span>
                                <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">unit</span>
                            </div>
                        </div>
                        <ul class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-[11px]">
                            @foreach ($statusDistribution as $index => $status)
                                <li class="inline-flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $statusChartColors[$index] ?? '#94a3b8' }}"></span>
                                    <span>{{ $status['label'] }}</span>
                                    <span class="font-bold tabular-nums text-slate-800 dark:text-slate-100">{{ $status['value'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </x-dashboard.panel>

                <x-dashboard.panel title="Peminjaman enam bulan terakhir" description="Jumlah peminjaman setiap bulan.">
                    <div class="h-52 sm:h-56"><canvas id="chartTrend" aria-label="Grafik tren peminjaman enam bulan terakhir"></canvas></div>
                </x-dashboard.panel>

                <x-dashboard.panel title="Terlambat dikembalikan" description="Pinjaman yang telah melewati tanggal jatuh tempo.">
                    @if ($overdue->isEmpty())
                        <div class="{{ $emptyStateClass }} border-green-200 bg-green-50/60 dark:border-green-900 dark:bg-green-950/20">
                            <span class="material-icons-round text-3xl text-green-500" aria-hidden="true">check_circle</span>
                            <p class="mt-2 text-sm font-medium text-slate-700 dark:text-slate-200">Semua pinjaman tepat waktu</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Tidak ada keterlambatan saat ini.</p>
                        </div>
                    @else
                        <ul class="h-52 divide-y divide-slate-100 overflow-y-auto pe-1 dark:divide-slate-700">
                            @foreach ($overdue as $loan)
                                <li class="py-2.5 first:pt-0">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <a href="{{ route('loans.show', $loan) }}" class="min-w-0 truncate text-sm font-semibold text-slate-800 hover:text-brand-700 dark:text-slate-100 dark:hover:text-brand-300">{{ $loan->borrower?->name }}</a>
                                        <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-red-50 px-2.5 py-1 text-[11px] font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-300">
                                            Jatuh tempo {{ $loan->due_date->format('d/m/Y') }} • terlambat {{ $loan->due_date->diffForHumans(now(), ['parts' => 1]) }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">{{ \Illuminate\Support\Str::limit($loan->purpose, 60) }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-dashboard.panel>

                <x-dashboard.panel title="Kegiatan terbaru" description="Catatan terbaru tentang barang dan peminjaman.">
                    <ul class="h-52 divide-y divide-slate-100 overflow-y-auto pe-1 dark:divide-slate-700">
                        @forelse ($recentActivity as $activity)
                            <li class="flex items-center gap-3 py-2.5 first:pt-0">
                                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-amber-200 text-amber-500 dark:border-amber-900 dark:text-amber-300">
                                    <span class="material-icons-round text-sm" aria-hidden="true">history</span>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-medium leading-relaxed text-slate-700 dark:text-slate-200">{{ $activity->description }}</p>
                                    <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-slate-400 dark:text-slate-500">
                                        <span class="inline-flex items-center gap-1">
                                            <span class="material-icons-round text-[13px]" aria-hidden="true">person</span>
                                            {{ $activity->causer?->name ?: 'Sistem' }}
                                        </span>
                                        <span class="inline-flex items-center gap-1">
                                            <span class="material-icons-round text-[13px]" aria-hidden="true">schedule</span>
                                            {{ $activity->created_at->diffForHumans() }}
                                        </span>
                                    </p>
                                </div>
                            </li>
                        @empty
                            <li class="{{ $emptyStateClass }} border-slate-200 bg-slate-50/70 dark:border-slate-700 dark:bg-slate-800/50">
                                <span class="material-icons-round text-3xl text-slate-300 dark:text-slate-600" aria-hidden="true">history</span>
                                <p class="mt-2 text-sm font-medium text-slate-600 dark:text-slate-300">Belum ada kegiatan</p>
                                <p class="mt-1 text-xs text-slate-400">Kegiatan terbaru akan tampil di sini.</p>
                            </li>
                        @endforelse
                    </ul>
                </x-dashboard.panel>
            </div>
        @else
            {{-- Mode peminjam: grid dua kolom yang sama dengan dashboard operasional. --}}
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                <x-dashboard.panel
                    title="Permintaan dan pinjaman saya"
                    description="Lihat status dan jadwal barang yang Anda pinjam."
                >
                    <x-slot name="actions">
                        <a href="{{ route('loans.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:text-brand-800 dark:text-brand-300">
                            Lihat riwayat
                            <span class="material-icons-round text-base" aria-hidden="true">arrow_forward</span>
                        </a>
                    </x-slot>

                    @if ($myActive->isEmpty())
                        <div class="{{ $emptyStateClass }} border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/70">
                            <span class="material-icons-round text-3xl text-slate-400" aria-hidden="true">inventory_2</span>
                            <p class="mt-2 text-sm font-medium text-slate-700 dark:text-slate-200">Belum ada pengajuan peminjaman.</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Pilih barang untuk mulai mengajukan peminjaman.</p>
                            <a href="{{ route('loans.create') }}" class="mt-4 inline-flex items-center gap-1 rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-brand-700">
                                Ajukan peminjaman
                                <span class="material-icons-round text-sm" aria-hidden="true">arrow_forward</span>
                            </a>
                        </div>
                    @else
                        <ul class="max-h-60 space-y-2 overflow-y-auto">
                            @foreach ($myActive as $loan)
                                <li class="rounded-xl border border-slate-200 p-3 transition hover:border-brand-200 hover:shadow-sm dark:border-slate-700">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <a href="{{ route('loans.show', $loan) }}" class="block truncate text-sm font-semibold text-slate-800 hover:text-brand-700 dark:text-slate-100 dark:hover:text-brand-300">{{ $loan->purpose }}</a>
                                            <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                                {{ $loan->loanItems->count() }} jenis barang • {{ $loan->start_date->format('d/m/Y') }} – {{ $loan->due_date->format('d/m/Y') }}
                                            </p>
                                        </div>
                                        <span class="inline-flex w-fit shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-bold {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                                    </div>
                                    @if ($loan->isOverdue())
                                        <p class="mt-1.5 inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-300">
                                            <span class="material-icons-round text-sm" aria-hidden="true">warning</span>
                                            Terlambat dikembalikan
                                        </p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-dashboard.panel>

                <x-dashboard.panel title="Jumlah permintaan pinjam" description="Jumlah permintaan setiap bulan selama enam bulan terakhir.">
                    <div class="h-52 sm:h-56">
                        <canvas id="chartTrend" aria-label="Grafik jumlah permintaan pinjam enam bulan terakhir"></canvas>
                    </div>
                </x-dashboard.panel>

                <x-dashboard.panel title="Katalog barang" description="Temukan barang yang dapat Anda pinjam.">
                    <div class="flex h-full flex-col justify-center">
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <p class="text-3xl font-bold tabular-nums text-slate-900 dark:text-slate-100">{{ $catalogCount }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">jenis barang tersedia di katalog</p>
                            </div>
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                                <span class="material-icons-round" aria-hidden="true">inventory_2</span>
                            </span>
                        </div>
                        <a href="{{ route('catalogs.index') }}" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700">
                            Buka katalog
                            <span class="material-icons-round text-lg" aria-hidden="true">arrow_forward</span>
                        </a>
                    </div>
                </x-dashboard.panel>

                <x-dashboard.panel title="Aktivitas terbaru" description="Catatan terbaru terkait akun dan peminjaman Anda.">
                    <ul class="h-52 divide-y divide-slate-100 overflow-y-auto pe-1 dark:divide-slate-700">
                        @forelse ($recentActivity as $activity)
                            <li class="flex items-center gap-3 py-2.5 first:pt-0">
                                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-amber-200 text-amber-500 dark:border-amber-900 dark:text-amber-300">
                                    <span class="material-icons-round text-sm" aria-hidden="true">history</span>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-medium leading-relaxed text-slate-700 dark:text-slate-200">{{ $activity->description }}</p>
                                    <p class="mt-0.5 flex items-center gap-1 text-[11px] text-slate-400 dark:text-slate-500">
                                        <span class="material-icons-round text-[13px]" aria-hidden="true">schedule</span>
                                        {{ $activity->created_at->diffForHumans() }}
                                    </p>
                                </div>
                            </li>
                        @empty
                            <li class="{{ $emptyStateClass }} border-slate-200 bg-slate-50/70 dark:border-slate-700 dark:bg-slate-800/50">
                                <span class="material-icons-round text-3xl text-slate-300 dark:text-slate-600" aria-hidden="true">history</span>
                                <p class="mt-2 text-sm font-medium text-slate-600 dark:text-slate-300">Belum ada kegiatan</p>
                                <p class="mt-1 text-xs text-slate-400">Kegiatan terbaru akan tampil di sini.</p>
                            </li>
                        @endforelse
                    </ul>
                </x-dashboard.panel>
            </div>
        @endif

        @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (!window.Chart) {
                    return;
                }

                Chart.defaults.font.family = "'Figtree', ui-sans-serif, system-ui, sans-serif";
                Chart.defaults.color = '#64748b';

                const trendLabels = @json(collect($loansPerMonth)->pluck('label'));
                const trendData = @json(collect($loansPerMonth)->pluck('count'));

                const trendCanvas = document.getElementById('chartTrend');
                if (trendCanvas) {
                    new Chart(trendCanvas, {
                        type: 'line',
                        data: {
                            labels: trendLabels,
                            datasets: [{
                                label: 'Peminjaman',
                                data: trendData,
                                borderColor: '#0875b9',
                                backgroundColor: 'rgba(8,117,185,0.12)',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.35,
                                pointRadius: 3,
                                pointBackgroundColor: '#0875b9',
                            }]
                        },
                        options: {
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { grid: { display: false }, border: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 12 } },
                                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(148,163,184,0.18)' }, border: { display: false } },
                            },
                            maintainAspectRatio: false,
                        }
                    });
                }

                @if ($mode === 'operational')
                    const borrowCanvas = document.getElementById('chartTopBorrowed');
                    if (borrowCanvas) {
                        new Chart(borrowCanvas, {
                            type: 'bar',
                            data: {
                                labels: @json($topBorrowed->map(fn ($r) => \Illuminate\Support\Str::limit($r->item?->name ?? '-', 18))->values()),
                                datasets: [{
                                    label: 'Jumlah dipinjam',
                                    data: @json($topBorrowed->pluck('total')->values()),
                                    backgroundColor: @json($borrowChartColors),
                                    borderRadius: 6,
                                    barPercentage: 0.7,
                                }]
                            },
                            options: {
                                indexAxis: 'y',
                                plugins: { legend: { display: false } },
                                scales: {
                                    x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(148,163,184,0.18)' }, border: { display: false } },
                                    y: { grid: { display: false }, border: { display: false } },
                                },
                                maintainAspectRatio: false,
                            }
                        });
                    }

                    const statusCanvas = document.getElementById('chartStatus');
                    if (statusCanvas) {
                        new Chart(statusCanvas, {
                            type: 'doughnut',
                            data: {
                                labels: @json($statusDistribution->pluck('label')),
                                datasets: [{
                                    data: @json($statusDistribution->pluck('value')),
                                    backgroundColor: @json($statusChartColors),
                                    borderWidth: 0,
                                    hoverOffset: 4,
                                }]
                            },
                            options: {
                                cutout: '70%',
                                plugins: { legend: { display: false } },
                                maintainAspectRatio: false,
                            }
                        });
                    }
                @endif
            });
        </script>
        @endpush
    </div>
</x-app-layout>
