<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Pengadaan Barang</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Alur tiga tahap: Operator → Editor/Validator → Admin. Barang baru menjadi inventaris setelah diterima dan dicatat.</p>
            </div>
            @if (auth()->user()->can('procurement.create'))
                <a href="{{ route('items.create') }}" class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-950">
                    <span class="material-icons-round text-base" aria-hidden="true">add</span> Pengajuan baru
                </a>
            @endif
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 flex items-start gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            <span class="material-icons-round text-lg" aria-hidden="true">check_circle</span><span>{{ session('success') }}</span>
        </div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->first() }}</div>
    @endif

    <div class="mb-4 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-xs text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 sm:grid-cols-3">
        <div class="flex items-start gap-2"><span class="material-icons-round text-base text-blue-600">edit_note</span><span>Operator menyusun draft lalu mengirimkannya.</span></div>
        <div class="flex items-start gap-2"><span class="material-icons-round text-base text-amber-600">how_to_reg</span><span>Editor/Validator memvalidasi — bukan persetujuan akhir.</span></div>
        <div class="flex items-start gap-2"><span class="material-icons-round text-base text-green-700">verified</span><span>Admin memutuskan menyetujui atau menolak.</span></div>
    </div>

    <form method="GET" action="{{ route('items.acquisitions') }}" role="search"
          class="mb-5 flex flex-col gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:flex-row">
        <label for="acquisition-search" class="sr-only">Cari pengadaan</label>
        <div class="relative min-w-0 flex-1">
            <span class="material-icons-round pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400" aria-hidden="true">search</span>
            <input id="acquisition-search" type="search" name="search" value="{{ $search }}"
                   placeholder="Cari nama barang, kode internal, kategori, ruangan, pengaju, atau sumber dana"
                   class="min-h-10 w-full rounded-xl border-slate-200 bg-slate-50 py-2 pl-10 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:bg-slate-950">
        </div>
        <div class="flex shrink-0 gap-2">
            <button type="submit" class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 sm:flex-none">
                <span class="material-icons-round text-base" aria-hidden="true">search</span> Cari
            </button>
            @if ($search !== '')
                <a href="{{ route('items.acquisitions') }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 px-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    Hapus
                </a>
            @endif
        </div>
    </form>

    @foreach ($statuses as $status)
        @php
            $items = $groups->get($status->value, collect());
        @endphp
        @continue($items->isEmpty())
        <div class="mb-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3.5 dark:border-slate-800 sm:px-5">
                <div class="min-w-0 text-sm font-bold text-slate-800 dark:text-slate-100">{{ $status->label() }}</div>
                <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $status->badgeClass() }}">{{ $items->count() }}</span>
            </div>

            <div class="max-h-[34rem] divide-y divide-slate-100 overflow-y-auto overscroll-contain dark:divide-slate-800">
                @foreach ($items as $item)
                    @php
                        $isRequester = (int) $item->acquisition_requested_by === (int) $user->id;
                        $canOwn = ($isRequester && $user->can('procurement.create')) || $user->can('procurement.edit');
                        $canReceive = $canOwn && $user->can('procurement.receive');
                    @endphp
                    <div class="flex flex-col gap-4 p-4 transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/40 sm:p-5 lg:flex-row lg:items-start">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('catalogs.show', $item) }}" class="font-semibold text-slate-900 hover:text-blue-700 dark:text-slate-100 dark:hover:text-blue-300">{{ $item->name }}</a>
                                @if ($item->is_high_value)
                                    <span class="rounded-full bg-purple-100 px-2 py-0.5 text-[10px] font-bold text-purple-800">NILAI TINGGI</span>
                                @endif
                            </div>
                            <div class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                                {{ $item->quantity }} {{ $item->unit }} • {{ $item->category?->name }} • Ruang {{ $item->room?->code }}
                                • Tahun {{ $item->acquisition_year }} • Dana {{ $item->funding_source }}
                                @if ($item->acquisition_received_quantity !== null)
                                    • Diterima {{ $item->acquisition_received_quantity }} {{ $item->unit }}
                                @endif
                            </div>
                            <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-slate-500 dark:text-slate-400">
                                <span class="font-mono">{{ $item->internal_id }}</span>
                                <span>Pengaju: {{ $item->requester?->name ?? '—' }}</span>
                                @if ($item->acquisition_submitted_at)
                                    <span>Dikirim: {{ $item->acquisition_submitted_at->format('d M Y H:i') }}</span>
                                @endif
                                @if ($item->acquisition_confirmed_at)
                                    <span>Dikonfirmasi: {{ $item->confirmer?->name }} ({{ $item->acquisition_confirmed_at->format('d M Y H:i') }})</span>
                                @endif
                                @if ($item->acquisition_decided_at)
                                    <span>Keputusan: {{ $item->approver?->name }} ({{ $item->acquisition_decided_at->format('d M Y H:i') }})</span>
                                @endif
                                @if ($item->acquisition_received_at)
                                    <span>Diterima: {{ $item->receiver?->name }} ({{ $item->acquisition_received_at->format('d M Y H:i') }})</span>
                                @endif
                            </div>

                            @if ($item->acquisition_status === \App\Enums\AcquisitionStatus::DITOLAK->value)
                                <div class="mt-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                                    <strong>Alasan penolakan:</strong> {{ $item->acquisition_note ?: '—' }}
                                </div>
                            @endif

                            @if ($item->acquisition_received_quantity !== null && (int) $item->acquisition_received_quantity !== (int) $item->quantity)
                                <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                                    Jumlah aktual diterima ({{ $item->acquisition_received_quantity }}) berbeda dari jumlah pengajuan ({{ $item->quantity }}). Selisih disimpan sebagai riwayat.
                                </div>
                            @endif
                        </div>

                        <div class="flex shrink-0 flex-wrap items-start gap-2 lg:max-w-xs lg:justify-end">
                            @if ($item->acquisition_status === \App\Enums\AcquisitionStatus::DRAFT->value && $canOwn && $user->can('procurement.create'))
                                <form method="POST" action="{{ route('items.acquisitions.submit', $item) }}" x-data="{ submitting: false }" @submit="submitting = true">
                                    @csrf
                                    <button :disabled="submitting" class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-blue-700 disabled:opacity-60">
                                        <span class="material-icons-round text-sm">send</span> <span x-text="submitting ? 'Mengirim…' : 'Kirim ke Editor/Validator'"></span>
                                    </button>
                                </form>
                            @endif

                            @if ($item->acquisition_status === \App\Enums\AcquisitionStatus::MENUNGGU_KONFIRMASI->value && $user->can('procurement.validate'))
                                <form method="POST" action="{{ route('items.acquisitions.confirm', $item) }}" x-data="{ submitting: false }" @submit="submitting = true">
                                    @csrf
                                    <button :disabled="submitting" class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-amber-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-amber-700 disabled:opacity-60">
                                        <span class="material-icons-round text-sm">how_to_reg</span> <span x-text="submitting ? 'Mengonfirmasi…' : 'Konfirmasi diketahui'"></span>
                                    </button>
                                </form>
                            @endif

                            @if ($item->acquisition_status === \App\Enums\AcquisitionStatus::MENUNGGU_PERSETUJUAN->value && $user->can('procurement.approve'))
                                <form method="POST" action="{{ route('items.approve', $item) }}" x-data="{ submitting: false }" @submit="submitting = true">
                                    @csrf
                                    <button :disabled="submitting" class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-green-700 disabled:opacity-60">
                                        <span class="material-icons-round text-sm">check_circle</span> <span x-text="submitting ? 'Menyetujui…' : 'Setujui'"></span>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('items.reject', $item) }}" class="flex flex-wrap items-center gap-2" x-data="{ submitting: false }" @submit="submitting = true">
                                    @csrf
                                    <input type="text" name="note" required placeholder="Alasan penolakan (wajib)"
                                           class="w-full grow rounded-xl border-slate-300 bg-white text-xs text-slate-800 focus:border-red-500 focus:ring-red-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 sm:w-40 sm:grow-0">
                                    <button :disabled="submitting" class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-red-700 disabled:opacity-60">
                                        <span class="material-icons-round text-sm">cancel</span> <span x-text="submitting ? 'Menolak…' : 'Tolak'"></span>
                                    </button>
                                </form>
                            @endif

                            @if ($item->acquisition_status === \App\Enums\AcquisitionStatus::DISETUJUI->value)
                                @if ($canOwn && $user->can('procurement.edit'))
                                    <form method="POST" action="{{ route('items.acquisitions.start', $item) }}" x-data="{ submitting: false }" @submit="submitting = true">
                                        @csrf
                                        <button :disabled="submitting" class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-sky-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-sky-700 disabled:opacity-60">
                                            <span class="material-icons-round text-sm">play_arrow</span> <span x-text="submitting ? 'Memproses…' : 'Mulai proses'"></span>
                                        </button>
                                    </form>
                                @endif
                                @if ($canReceive)
                                    <a href="{{ route('items.acquisitions.receive-form', $item) }}" class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-700">
                                        <span class="material-icons-round text-sm">inventory</span> Catat penerimaan
                                    </a>
                                @endif
                            @endif

                            @if ($item->acquisition_status === \App\Enums\AcquisitionStatus::DALAM_PROSES->value && $canReceive)
                                <a href="{{ route('items.acquisitions.receive-form', $item) }}" class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-700">
                                    <span class="material-icons-round text-sm">inventory</span> Catat penerimaan
                                </a>
                            @endif

                            @if ($item->acquisition_status === \App\Enums\AcquisitionStatus::BARANG_DITERIMA->value && $canReceive)
                                <form method="POST" action="{{ route('items.acquisitions.record', $item) }}" x-data="{ submitting: false }" @submit="submitting = true"
                                      onsubmit="return confirm('Terbitkan unit inventaris dari barang yang sudah diterima?')">
                                    @csrf
                                    <button :disabled="submitting" class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60">
                                        <span class="material-icons-round text-sm">qr_code_2</span> <span x-text="submitting ? 'Mencatat…' : 'Catat inventaris'"></span>
                                    </button>
                                </form>
                            @endif

                            <a href="{{ route('items.show', $item) }}" class="inline-flex min-h-9 items-center rounded-lg px-2.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Detail</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    @if ($groups->isEmpty())
        <div class="rounded-2xl border border-slate-200 bg-white px-5 py-10 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <span class="material-icons-round text-3xl text-slate-300 dark:text-slate-600" aria-hidden="true">{{ $search !== '' ? 'search_off' : 'inventory_2' }}</span>
            <p class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                {{ $search !== '' ? 'Pengadaan tidak ditemukan' : 'Belum ada pengajuan pengadaan' }}
            </p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                {{ $search !== '' ? 'Coba kata kunci lain atau hapus pencarian.' : 'Pengajuan pada ruang lingkup Anda akan tampil di sini.' }}
            </p>
        </div>
    @endif
</x-app-layout>
