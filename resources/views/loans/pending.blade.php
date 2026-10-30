<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Persetujuan Peminjaman</h2>
                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">{{ $loans->count() }} MENUNGGU</span>
                </div>
                <p class="text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    @if (auth()->user()->isKetuaLaboran())
                        Tinjau permintaan pinjaman dari seluruh laboratorium. Untuk barang bernilai tinggi, persetujuan Ketua Laboran menjadi tahap awal sebelum ditinjau Wakasek.
                    @elseif (auth()->user()->isSuperAdmin())
                        Tinjau permintaan pinjaman yang menunggu persetujuan. Barang bernilai tinggi memerlukan persetujuan Wakasek pada tahap akhir.
                    @else
                        Tinjau permintaan pinjaman sesuai cakupan laboratorium Anda. Barang bernilai tinggi memerlukan persetujuan Wakasek pada tahap berikutnya.
                    @endif
                </p>
            </div>
            <a href="{{ route('loans.returns') }}" class="inline-flex min-h-10 items-center gap-1 rounded-lg px-3 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-slate-800">Pengembalian <span aria-hidden="true">→</span></a>
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
                <article class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $loan->borrower?->name }}</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $loan->borrower?->major?->name ?: $loan->borrower?->role }}</span>
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                                @if ($loan->needs_high_value_approval)
                                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-purple-100 text-purple-800 dark:bg-purple-950/50 dark:text-purple-300">NILAI TINGGI — PERSETUJUAN WAKASEK</span>
                                @endif
                            </div>
                            <div class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $loan->purpose }}</div>
                            <div class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                {{ $loan->start_date->format('d M Y H:i') }} → {{ $loan->due_date->format('d M Y H:i') }}
                                @if ($loan->event) • Kegiatan: <strong>{{ $loan->event->name }}</strong> (PJ: {{ $loan->event->pic_name }}) @endif
                                @if ($loan->isOverdue()) <span class="text-red-600 font-bold">⚠ TERLAMBAT</span> @endif
                            </div>

                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($loan->loanItems as $li)
                                    <span class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-[11px] text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                        {{ $li->item?->name }}
                                        <strong>×{{ $li->qty_borrowed }}</strong>
                                        @if ($li->item?->is_high_value)
                                            <span class="material-icons-round text-xs text-purple-500">workspace_premium</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        </div>

                        <div class="shrink-0">
                            <a href="{{ route('loans.show', $loan) }}"
                               class="inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:bg-brand-800 lg:w-auto lg:py-2 dark:focus:ring-offset-slate-900">
                                <span class="material-icons-round text-lg">gavel</span> Proses Persetujuan
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</x-app-layout>
