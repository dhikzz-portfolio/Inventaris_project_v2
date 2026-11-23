<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Permintaan Pinjam</h2>
                    <span class="shrink-0 rounded-full bg-amber-100 px-2 py-1 text-[10px] font-bold text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">{{ $loans->count() }} MENUNGGU</span>
                </div>
                <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-500 dark:text-slate-400 sm:text-sm sm:leading-relaxed">
                    @if (auth()->user()->isEditorValidator())
                        Tinjau permintaan dari seluruh laboratorium. Barang bernilai tinggi perlu persetujuan Ketua Laboran lalu Wakasek.
                    @elseif (auth()->user()->isSuperAdmin())
                        Tinjau permintaan yang menunggu persetujuan. Barang bernilai tinggi memerlukan persetujuan akhir Wakasek.
                    @else
                        Tinjau permintaan sesuai cakupan laboratorium. Barang bernilai tinggi perlu persetujuan Wakasek.
                    @endif
                </p>
            </div>
            <a href="{{ route('loans.returns') }}"
               aria-label="Lihat pengembalian"
               class="inline-flex h-9 w-9 shrink-0 items-center justify-center gap-1 rounded-xl border border-slate-200 text-brand-700 transition hover:bg-brand-50 sm:w-auto sm:px-3 dark:border-slate-700 dark:text-brand-300 dark:hover:bg-slate-800">
                <span class="material-icons-round text-lg" aria-hidden="true">assignment_return</span>
                <span class="hidden text-sm font-semibold sm:inline">Pengembalian</span>
                <span class="hidden sm:inline" aria-hidden="true">→</span>
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

    @if ($loans->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center dark:border-slate-700 dark:bg-slate-900">
            <span class="material-icons-round text-5xl text-green-500" aria-hidden="true">task_alt</span>
            <p class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-100">Tidak ada permintaan yang menunggu persetujuan.</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Permintaan baru akan muncul di sini.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($loans as $loan)
                @php
                    $borrowerName = trim((string) ($loan->borrower?->name ?? 'Peminjam'));
                    $borrowerInitials = collect(preg_split('/\s+/', $borrowerName))
                        ->filter()
                        ->take(2)
                        ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
                        ->implode('');
                @endphp
                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex flex-col gap-4 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">
                                {{ $borrowerInitials ?: 'P' }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ $borrowerName }}</h3>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                                </div>
                                @if ($loan->needs_high_value_approval)
                                    <div class="mt-1.5">
                                        <span class="inline-flex rounded-full bg-purple-100 px-2.5 py-1 text-[9px] font-bold uppercase tracking-wide text-purple-800 dark:bg-purple-950/50 dark:text-purple-300">Nilai tinggi — persetujuan Wakasek</span>
                                    </div>
                                @endif

                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $loan->borrower?->major?->name ?: $loan->borrower?->role }}</p>
                                <div class="mt-2 flex flex-col gap-1.5 text-xs text-slate-500 sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-4 dark:text-slate-400">
                                    @if ($loan->purpose)
                                        <span class="inline-flex min-w-0 items-start gap-1.5">
                                            <span class="material-icons-round mt-px text-sm" aria-hidden="true">description</span>
                                            <span class="break-words">{{ $loan->purpose }}</span>
                                        </span>
                                    @endif
                                    <span class="inline-flex items-start gap-1.5">
                                        <span class="material-icons-round mt-px text-sm" aria-hidden="true">calendar_today</span>
                                        <span>{{ $loan->start_date->format('d M Y, H:i') }} → {{ $loan->due_date->format('d M Y, H:i') }}</span>
                                    </span>
                                    @if ($loan->isOverdue())
                                        <span class="inline-flex items-center gap-1 font-bold text-red-600 dark:text-red-400">
                                            <span class="material-icons-round text-sm" aria-hidden="true">warning</span> Terlambat
                                        </span>
                                    @endif
                                </div>
                                @if ($loan->event)
                                    <p class="mt-1.5 break-words text-xs text-slate-500 dark:text-slate-400">
                                        <span class="font-semibold text-slate-600 dark:text-slate-300">Kegiatan:</span> {{ $loan->event->name }}
                                        @if ($loan->event->pic_name)
                                            <span class="text-slate-400 dark:text-slate-500"> · PJ {{ $loan->event->pic_name }}</span>
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="shrink-0 sm:pl-[3.25rem] lg:pl-0">
                            <a href="{{ route('loans.show', $loan) }}"
                               class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:bg-brand-800 lg:w-auto dark:focus:ring-offset-slate-900">
                                <span class="material-icons-round text-base" aria-hidden="true">check</span> Proses Persetujuan
                            </a>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 border-t border-slate-100 bg-slate-50/80 px-4 py-3 sm:flex-row sm:items-start sm:gap-3 sm:px-5 dark:border-slate-800 dark:bg-slate-800/30">
                        <span class="shrink-0 pt-0.5 text-xs font-medium text-slate-500 dark:text-slate-400">Barang</span>
                        <div class="flex min-w-0 flex-wrap gap-1.5">
                            @foreach ($loan->loanItems as $li)
                                <span class="inline-flex max-w-full items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                    <span class="break-words">{{ $li->item?->name }}</span>
                                    <strong class="shrink-0">×{{ $li->qty_borrowed }}</strong>
                                    @if ($li->item?->is_high_value)
                                        <span class="material-icons-round shrink-0 text-xs text-purple-600 dark:text-purple-400" aria-label="Barang bernilai tinggi">workspace_premium</span>
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</x-app-layout>
