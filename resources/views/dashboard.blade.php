@php
    $isWakasek = auth()->user()->isSuperAdmin();
    $dashboardUser = auth()->user();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-blue-600 dark:text-blue-400">
                    {{ $mode === 'operational' ? 'Ringkasan operasional' : 'Ruang kerja peminjam' }}
                </p>
                <h2 class="mt-1 text-xl font-bold leading-tight text-slate-900 dark:text-white">Dashboard</h2>
                <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                @if ($mode === 'operational')
                    Analitik inventaris {{ $scopeLabel }} · {{ \App\Enums\Role::tryFrom(auth()->user()->role)?->label() }}
                @else
                    Ringkasan peminjaman saya
                @endif
                </p>
            </div>
            <div class="hidden shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600 sm:inline-flex dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                <span class="material-icons-round text-base text-blue-600 dark:text-blue-400" aria-hidden="true">calendar_today</span>
                {{ now()->translatedFormat('l, d F Y') }}
            </div>
        </div>
    </x-slot>

    {{-- Sapaan setelah login: nama lengkap + gelar, fallback username. --}}
    <div class="mb-4 flex flex-col gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3.5 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-slate-700 dark:bg-slate-900">
        <div class="flex items-center gap-3">
            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                <span class="material-icons-round text-xl" aria-hidden="true">waving_hand</span>
            </span>
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Selamat datang</p>
                <p class="truncate text-base font-bold text-slate-900 dark:text-white">{{ $dashboardUser->greetingName() }}!</p>
            </div>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400">
            {{ $dashboardUser->isPeminjam() ? 'Ajukan peminjaman dan pantau statusnya di sini.' : 'Pantau inventaris sesuai kewenangan Anda.' }}
        </p>
    </div>

    <div @class([
        'rounded-3xl',
        'bg-sky-50/80 p-3 sm:p-4 dark:bg-slate-950/30' => $isWakasek,
    ])>
    @if ($mode === 'operational')
        <section @class([
            'mb-5 overflow-hidden rounded-2xl p-5 shadow-lg sm:p-6',
            'bg-gradient-to-r from-sky-200 via-sky-100 to-cyan-100 text-slate-800 shadow-sky-900/5 dark:from-slate-800 dark:via-slate-800 dark:to-slate-700 dark:text-white' => $isWakasek,
            'bg-gradient-to-br from-slate-900 via-blue-950 to-blue-900 text-white shadow-blue-950/10' => ! $isWakasek,
        ])>
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <p @class([
                        'text-sm font-medium',
                        'text-sky-800 dark:text-sky-200' => $isWakasek,
                        'text-blue-200' => ! $isWakasek,
                    ])>Selamat datang, {{ $dashboardUser->name }}</p>
                    <h3 class="mt-1 text-xl font-bold tracking-tight sm:text-2xl">
                        @if ($dashboardUser->isSuperAdmin())
                            Kendali inventaris sekolah
                        @elseif ($dashboardUser->isAdminSarpras())
                            Kelola inventaris sekolah
                        @else
                            Pantau inventaris laboratorium
                        @endif
                    </h3>
                    <p @class([
                        'mt-2 max-w-2xl text-xs leading-relaxed sm:text-sm',
                        'text-sky-800/80 dark:text-slate-300' => $isWakasek,
                        'text-blue-100/80' => ! $isWakasek,
                    ])>
                        @if ($dashboardUser->isSuperAdmin())
                            Pantau kondisi aset, persetujuan, dan aktivitas inventaris {{ $scopeLabel }}.
                        @elseif ($dashboardUser->isAdminSarpras())
                            Pantau pengelolaan aset dan permohonan dari {{ $scopeLabel }}.
                        @else
                            Pantau aset dan pengajuan yang berada di {{ $scopeLabel }}.
                        @endif
                    </p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row lg:shrink-0">
                    @if ($dashboardUser->managesInventory())
                        <a href="{{ route('items.create') }}" @class([
                            'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold shadow-sm transition focus:outline-none focus:ring-2',
                            'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500' => $isWakasek,
                            'bg-white text-blue-950 hover:bg-blue-50 focus:ring-white focus:ring-offset-2 focus:ring-offset-blue-900' => ! $isWakasek,
                        ])>
                            <span class="material-icons-round text-lg" aria-hidden="true">add_box</span>
                            Pengadaan barang
                        </a>
                    @else
                        <a href="{{ route('scan') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-blue-950 shadow-sm transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-blue-900">
                            <span class="material-icons-round text-lg" aria-hidden="true">qr_code_scanner</span>
                            Scan inventaris
                        </a>
                    @endif
                    <a href="{{ route('loans.pending') }}" @class([
                        'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-2',
                        'border-blue-200 bg-white/80 text-blue-800 hover:bg-white focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:hover:bg-slate-600' => $isWakasek,
                        'border-white/20 bg-white/10 text-white hover:bg-white/20 focus:ring-white/70' => ! $isWakasek,
                    ])>
                        <span class="material-icons-round text-lg" aria-hidden="true">how_to_reg</span>
                        Persetujuan
                        @if ($stats[3]['value'] > 0)
                            <span @class([
                                'rounded-full px-1.5 py-0.5 text-[10px] font-bold',
                                'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-100' => $isWakasek,
                                'bg-white text-blue-900' => ! $isWakasek,
                            ])>{{ $stats[3]['value'] }}</span>
                        @endif
                    </a>
                    <a href="{{ route('catalogs.index') }}" @class([
                        'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-2',
                        'border-blue-200 bg-white/50 text-blue-800 hover:bg-white/80 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:hover:bg-slate-600' => $isWakasek,
                        'border-white/20 text-white hover:bg-white/10 focus:ring-white/70' => ! $isWakasek,
                    ])>
                        <span class="material-icons-round text-lg" aria-hidden="true">inventory_2</span>
                        Katalog
                    </a>
                </div>
            </div>
        </section>
    @endif

    <div @class([
        'mb-5 grid auto-rows-fr grid-cols-2 gap-3 sm:gap-4 md:mb-6 md:grid-cols-3',
        'xl:grid-cols-6' => $mode === 'operational',
        'xl:grid-cols-4' => $mode !== 'operational',
    ])>
        @foreach ($stats as $stat)
            <x-dashboard.stat-card :stat="$stat" :wakasek="$isWakasek" />
        @endforeach
    </div>

    @if ($mode === 'operational')
        <div class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <x-dashboard.panel title="Barang paling sering dipinjam" description="Bulan {{ now()->translatedFormat('F Y') }} · berdasarkan kuantitas">
                @if ($topBorrowed->isEmpty())
                    <div class="flex min-h-56 flex-col items-center justify-center rounded-xl bg-slate-50 px-4 text-center dark:bg-slate-800/70">
                        <span class="material-icons-round text-3xl text-slate-300 dark:text-slate-600" aria-hidden="true">bar_chart</span>
                        <p class="mt-2 text-sm font-medium text-slate-600 dark:text-slate-300">Belum ada data peminjaman</p>
                        <p class="mt-1 text-xs text-slate-400">Data bulan ini akan muncul di sini.</p>
                    </div>
                @else
                    <div class="h-56 sm:h-64"><canvas id="chartTopBorrowed" aria-label="Grafik barang paling sering dipinjam bulan ini"></canvas></div>
                @endif
            </x-dashboard.panel>

            <x-dashboard.panel title="Barang paling sering rusak" description="Bulan {{ now()->translatedFormat('F Y') }} · laporan kerusakan">
                @if ($topDamaged->isEmpty())
                    <div class="flex min-h-56 flex-col items-center justify-center rounded-xl bg-green-50/70 px-4 text-center dark:bg-green-950/20">
                        <span class="material-icons-round text-3xl text-green-500" aria-hidden="true">task_alt</span>
                        <p class="mt-2 text-sm font-medium text-slate-700 dark:text-slate-200">Tidak ada laporan kerusakan</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Kondisi inventaris tercatat baik bulan ini.</p>
                    </div>
                @else
                    <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach ($topDamaged as $row)
                            <li class="flex items-center justify-between gap-3 py-3 text-sm first:pt-1">
                                <span class="min-w-0 truncate font-medium text-slate-700 dark:text-slate-200">{{ $row->item?->name }}</span>
                                <span class="shrink-0 rounded-lg bg-red-50 px-2.5 py-1 text-xs font-bold text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ $row->total }}× rusak</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-dashboard.panel>

            <x-dashboard.panel title="Kondisi inventaris" description="Ringkasan kondisi unit dalam cakupan akses Anda.">
                <div class="h-56 sm:h-64"><canvas id="chartStatus" aria-label="Grafik distribusi kondisi inventaris"></canvas></div>
            </x-dashboard.panel>
            <x-dashboard.panel title="Tren peminjaman" description="Pergerakan jumlah peminjaman selama enam bulan terakhir.">
                <div class="h-56 sm:h-64"><canvas id="chartTrend" aria-label="Grafik tren peminjaman enam bulan terakhir"></canvas></div>
            </x-dashboard.panel>

            <x-dashboard.panel title="Terlambat dikembalikan" description="Pinjaman yang telah melewati tanggal jatuh tempo.">
                @if ($overdue->isEmpty())
                    <div class="flex min-h-56 flex-col items-center justify-center rounded-xl bg-green-50/70 px-4 text-center dark:bg-green-950/20">
                        <span class="material-icons-round text-3xl text-green-500" aria-hidden="true">check_circle</span>
                        <p class="mt-2 text-sm font-medium text-slate-700 dark:text-slate-200">Semua pinjaman tepat waktu</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Tidak ada keterlambatan saat ini.</p>
                    </div>
                @else
                    <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach ($overdue as $loan)
                            <li class="py-3 first:pt-1">
                                <a href="{{ route('loans.show', $loan) }}" class="text-sm font-semibold text-slate-800 hover:text-blue-700 dark:text-slate-100 dark:hover:text-blue-300">{{ $loan->borrower?->name }}</a>
                                <span class="mt-1 block truncate text-xs text-slate-500 dark:text-slate-400">{{ \Illuminate\Support\Str::limit($loan->purpose, 60) }}</span>
                                <span class="mt-2 inline-flex rounded-lg bg-red-50 px-2.5 py-1 text-[11px] font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-300">Jatuh tempo {{ $loan->due_date->format('d/m/Y') }} · terlambat {{ $loan->due_date->diffForHumans(now()) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-dashboard.panel>

            <x-dashboard.panel title="Aktivitas terbaru" description="Aktivitas inventaris yang tercatat di sistem.">
                <ul class="max-h-72 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-700">
                    @forelse ($recentActivity as $activity)
                        <li class="flex items-start gap-3 py-3 first:pt-1">
                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-300">
                                <span class="material-icons-round text-base" aria-hidden="true">history</span>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs leading-relaxed text-slate-700 dark:text-slate-200">{{ $activity->description }}</p>
                                <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">{{ $activity->causer?->name ?: 'Sistem' }} · {{ $activity->created_at->diffForHumans() }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="flex min-h-56 flex-col items-center justify-center rounded-xl bg-slate-50 px-4 text-center dark:bg-slate-800/70">
                            <span class="material-icons-round text-3xl text-slate-300 dark:text-slate-600" aria-hidden="true">history</span>
                            <p class="mt-2 text-sm font-medium text-slate-600 dark:text-slate-300">Belum ada aktivitas</p>
                            <p class="mt-1 text-xs text-slate-400">Aktivitas terbaru akan tampil di sini.</p>
                        </li>
                    @endforelse
                </ul>
            </x-dashboard.panel>
        </div>
    @else
        {{-- Mode peminjam --}}
        <section class="mb-5 overflow-hidden rounded-2xl bg-gradient-to-br from-blue-700 via-blue-700 to-indigo-800 p-5 text-white shadow-lg shadow-blue-900/10 sm:p-6">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wider text-blue-100">Selamat datang kembali</p>
                    <h3 class="mt-1 truncate text-xl font-bold tracking-tight sm:text-2xl">{{ auth()->user()->name }}</h3>
                    <p class="mt-2 text-xs leading-relaxed text-blue-100/90 sm:text-sm">
                    Pantau pengajuan dan pinjaman barang sekolah Anda.
                    <span class="hidden sm:inline">{{ now()->translatedFormat('l, d F Y') }}</span>
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:flex sm:shrink-0">
                    <a href="{{ route('catalogs.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-3 py-2.5 text-xs font-semibold text-white transition hover:bg-white/20 sm:px-4 sm:text-sm">
                        <span class="material-icons-round text-lg" aria-hidden="true">inventory_2</span>
                        Katalog
                    </a>
                    <a href="{{ route('loans.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-white px-3 py-2.5 text-xs font-semibold text-blue-800 shadow-sm transition hover:bg-blue-50 sm:px-4 sm:text-sm">
                        <span class="material-icons-round text-lg" aria-hidden="true">add</span>
                        Ajukan pinjaman
                    </a>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3 xl:gap-5">
            <x-dashboard.panel
                title="Pengajuan dan pinjaman saya"
                description="Pantau status dan jadwal barang yang Anda ajukan."
                class="xl:col-span-2"
            >
                <x-slot name="actions">
                    <a href="{{ route('loans.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-700 hover:text-blue-800">
                        Lihat semua
                        <span class="material-icons-round text-base" aria-hidden="true">arrow_forward</span>
                    </a>
                </x-slot>

                @if ($myActive->isEmpty())
                    <div class="flex flex-col items-center rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center dark:border-slate-700 dark:bg-slate-800/70">
                        <span class="material-icons-round text-3xl text-slate-400" aria-hidden="true">inventory_2</span>
                        <p class="mt-2 text-sm font-medium text-slate-700 dark:text-slate-200">Belum ada pengajuan atau pinjaman.</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Pilih barang di katalog untuk memulai.</p>
                        <a href="{{ route('catalogs.index') }}" class="mt-4 inline-flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-blue-700">
                            Jelajahi katalog
                            <span class="material-icons-round text-sm" aria-hidden="true">arrow_forward</span>
                        </a>
                    </div>
                @else
                    <ul class="space-y-3">
                        @foreach ($myActive as $loan)
                            <li class="rounded-xl border border-slate-200 p-4 transition hover:border-blue-200 hover:shadow-sm dark:border-slate-700">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <a href="{{ route('loans.show', $loan) }}" class="break-words text-sm font-semibold text-slate-800 hover:text-blue-700">{{ $loan->purpose }}</a>
                                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                            <span class="inline-flex items-center gap-1">
                                                <span class="material-icons-round text-sm" aria-hidden="true">inventory_2</span>
                                                {{ $loan->loanItems->count() }} barang
                                            </span>
                                            <span class="inline-flex items-center gap-1">
                                                <span class="material-icons-round text-sm" aria-hidden="true">event</span>
                                                {{ $loan->start_date->format('d/m/Y') }} – {{ $loan->due_date->format('d/m/Y') }}
                                            </span>
                                        </div>
                                        @if ($loan->event)
                                            <p class="mt-2 text-xs text-slate-500">Kegiatan: {{ $loan->event->name }}</p>
                                        @endif
                                    </div>
                                    <span class="inline-flex w-fit items-center rounded-full px-2.5 py-1 text-[10px] font-bold {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                                </div>
                                @if ($loan->isOverdue())
                                    <p class="mt-3 inline-flex items-center gap-1 rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700">
                                        <span class="material-icons-round text-sm" aria-hidden="true">warning</span>
                                        Terlambat dikembalikan
                                    </p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-dashboard.panel>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-1">
                <x-dashboard.panel title="Frekuensi pengajuan" description="Jumlah pengajuan dalam enam bulan terakhir.">
                    <div class="h-56">
                        <canvas id="chartTrend" aria-label="Grafik frekuensi pengajuan enam bulan terakhir"></canvas>
                    </div>
                </x-dashboard.panel>

                <x-dashboard.panel title="Katalog inventaris" description="Temukan barang yang tersedia untuk diajukan.">
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <p class="text-3xl font-bold tabular-nums text-slate-900 dark:text-slate-100">{{ $catalogCount }}</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">jenis barang tersedia</p>
                        </div>
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                            <span class="material-icons-round" aria-hidden="true">inventory_2</span>
                        </span>
                    </div>
                    <a href="{{ route('catalogs.index') }}" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                        Buka katalog
                        <span class="material-icons-round text-lg" aria-hidden="true">arrow_forward</span>
                    </a>
                </x-dashboard.panel>
            </div>
        </div>

        <x-dashboard.panel title="Riwayat aktivitas saya" description="Aktivitas terbaru terkait akun dan peminjaman Anda." class="mt-4">
            @forelse ($recentActivity as $activity)
                <div class="flex items-start gap-3 border-b border-slate-100 py-3 last:border-b-0 last:pb-0 dark:border-slate-700">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                        <span class="material-icons-round text-base" aria-hidden="true">history</span>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="break-words text-sm text-slate-700 dark:text-slate-200">{{ $activity->description }}</p>
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ $activity->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @empty
                <p class="rounded-xl bg-slate-50 px-4 py-5 text-center text-sm text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">Belum ada aktivitas terbaru.</p>
            @endforelse
        </x-dashboard.panel>
    @endif

    @php
        $borrowChartColors = $isWakasek
            ? ['#0875b9', '#169fe0', '#43b9ef', '#83d4f5', '#beeafb']
            : ['#f59e0b', '#fbbf24', '#fcd34d', '#fde68a', '#fef3c7'];
        $statusChartColors = $isWakasek
            ? ['#0875b9', '#38bdf8', '#f59e0b', '#ef4444', '#64748b']
            : ['#22c55e', '#eab308', '#f97316', '#ef4444', '#64748b'];
    @endphp

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
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37,99,235,0.12)',
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
                                backgroundColor: @json($borrowChartColors),
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
                                backgroundColor: @json($statusChartColors),
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
    </div>
</x-app-layout>
