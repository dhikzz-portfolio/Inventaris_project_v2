@php
    $effective = $item->effectiveStatus();
    $available = $item->availableQuantity();
    $isDeleted = $effective === \App\Enums\ItemStatus::DIHAPUS;
    $canBorrow = ! $isDeleted && $item->isRecordedInInventory() && $available > 0;
    $locationLines = app(\App\Services\LabelService::class)->splitLocationCode((string) $item->location_code);
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <nav aria-label="Breadcrumb" class="mb-1 flex flex-wrap items-center gap-2 text-xs">
                    <a href="{{ route('catalogs.index') }}" class="font-medium text-slate-500 transition hover:text-brand-700 dark:text-slate-400 dark:hover:text-brand-300">Katalog Barang</a>
                    <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">/</span>
                    <a href="{{ route('catalogs.show', $item) }}" class="max-w-[14rem] truncate font-medium text-slate-500 transition hover:text-brand-700 dark:text-slate-400 dark:hover:text-brand-300">{{ $item->name }}</a>
                </nav>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">{{ $item->name }}</h2>
                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $effective->badgeClass() }}">{{ $effective->label() }}</span>
                    @if ($item->is_high_value)
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-purple-100 text-purple-800">NILAI TINGGI</span>
                    @endif
                    @if ($item->isAcquisitionPending() && $item->acquisitionStatusEnum())
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $item->acquisitionStatusEnum()->badgeClass() }}">{{ $item->acquisitionStatusEnum()->label() }}</span>
                    @endif
                </div>
                <p class="mt-1 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $item->internal_id }}</p>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <a href="{{ route('items.label', $item) }}" target="_blank"
                   class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                   title="Re-print label stiker">
                    <span class="material-icons-round text-lg" aria-hidden="true">print</span> Cetak Label
                </a>
                @if (auth()->user()->managesInventory())
                    <a href="{{ route('items.edit', $item) }}"
                       class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-brand-700 dark:hover:bg-slate-800 dark:hover:text-brand-300">
                        <span class="material-icons-round text-base" aria-hidden="true">edit</span> Ubah Data
                    </a>
                @endif
                <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                    <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-controls="item-more-menu"
                            class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        Lainnya
                        <span class="material-icons-round text-base transition-transform" :class="open ? 'rotate-180' : ''" aria-hidden="true">expand_more</span>
                    </button>
                    <div id="item-more-menu" x-show="open" x-cloak x-transition
                         class="absolute right-0 top-full z-30 mt-2 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl shadow-slate-900/10 dark:border-slate-700 dark:bg-slate-900">
                        <a href="{{ route('items.acquisitions') }}"
                           class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800">
                            <span class="material-icons-round text-base text-slate-400" aria-hidden="true">list_alt</span> Lihat daftar pengadaan
                        </a>
                        <a href="{{ route('catalogs.show', $item) }}"
                           class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800">
                            <span class="material-icons-round text-base text-slate-400" aria-hidden="true">inventory_2</span> Kembali ke katalog
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 flex items-start gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            <span class="material-icons-round text-lg" aria-hidden="true">check_circle</span><span>{{ session('success') }}</span>
        </div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <span class="material-icons-round text-lg" aria-hidden="true">error_outline</span><span>{{ $errors->first() }}</span>
        </div>
    @endif

    @if ($isDeleted)
        <div role="status" class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <span class="material-icons-round text-lg shrink-0" aria-hidden="true">warning_amber</span>
            <span>Barang ini sudah dihapus. Peminjaman dinonaktifkan sampai status barang diubah kembali.</span>
        </div>
    @endif

    {{-- Kartu ringkasan --}}
    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Jumlah</div>
            <div class="mt-1 text-2xl font-bold leading-none {{ $item->quantity === 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">
                {{ $item->quantity }} <span class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $item->unit }}</span>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Tersedia</div>
            <div class="mt-1 text-2xl font-bold leading-none {{ $available === 0 ? 'text-red-600 dark:text-red-400' : 'text-green-700 dark:text-green-400' }}">
                {{ $available }} <span class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $item->unit }}</span>
            </div>
            @if ($item->qty_damaged > 0 || $item->qty_lost > 0 || $item->pendingLostQuantity() > 0)
                <div class="mt-1.5 space-y-0.5 text-[10px] font-semibold">
                    @if ($item->qty_damaged > 0)<div class="text-orange-700 dark:text-orange-300">Rusak: {{ $item->qty_damaged }} {{ $item->unit }}</div>@endif
                    @if ($item->qty_lost > 0)<div class="text-red-600 dark:text-red-400">Hilang: {{ $item->qty_lost }} {{ $item->unit }}</div>@endif
                    @if ($item->pendingLostQuantity() > 0)<div class="text-amber-700 dark:text-amber-300">Selisih belum ditutup: {{ $item->pendingLostQuantity() }}</div>@endif
                </div>
            @endif
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Tahun</div>
            <div class="mt-1 text-2xl font-bold leading-none text-slate-900 dark:text-white">{{ $item->acquisition_year ?: '—' }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Sumber dana</div>
            <div class="mt-1 truncate text-lg font-bold leading-tight text-slate-900 dark:text-white" title="{{ $item->funding_source }}">{{ $item->funding_source ?: '—' }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">
        {{-- Kolom utama --}}
        <div class="space-y-4 lg:col-span-2">
            @if ($item->acquisitionStatusEnum() && ! $item->isRecordedInInventory())
                @php($acquisitionStatus = $item->acquisitionStatusEnum())
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-slate-100">
                            <span class="material-icons-round text-lg text-brand-600 dark:text-brand-300" aria-hidden="true">fact_check</span>
                            Status Pengadaan
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $acquisitionStatus->badgeClass() }}">{{ $acquisitionStatus->label() }}</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $acquisitionStatus->hint() }}</p>
                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-slate-500 dark:text-slate-400">
                        <span>Pengaju: {{ $item->requester?->name ?? '—' }}</span>
                        @if ($item->acquisition_submitted_at)
                            <span>Diajukan: {{ $item->acquisition_submitted_at->format('d M Y H:i') }}</span>
                        @endif
                        @if ($item->acquisition_confirmed_at)
                            <span>Divalidasi: {{ $item->confirmer?->name }} ({{ $item->acquisition_confirmed_at->format('d M Y H:i') }})</span>
                        @endif
                        @if ($item->acquisition_decided_at)
                            <span>Keputusan: {{ $item->approver?->name }} ({{ $item->acquisition_decided_at->format('d M Y H:i') }})</span>
                        @endif
                    </div>
                    @if ($item->acquisition_status === \App\Enums\AcquisitionStatus::DITOLAK->value && $item->acquisition_note)
                        <p class="mt-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                            <strong>Alasan penolakan:</strong> {{ $item->acquisition_note }}
                        </p>
                    @endif
                    <a href="{{ route('items.acquisitions') }}" class="mt-3 inline-flex min-h-9 items-center gap-1 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                        <span class="material-icons-round text-sm" aria-hidden="true">list_alt</span> Lihat daftar pengadaan
                    </a>
                </section>
            @endif

            {{-- Informasi barang --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="mb-3 flex items-center gap-2.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                        <span class="material-icons-round" aria-hidden="true">inventory_2</span>
                    </span>
                    Informasi barang
                </h3>
                <div class="flex flex-col gap-4 sm:flex-row">
                    <div class="h-32 w-full shrink-0 overflow-hidden rounded-xl bg-gradient-to-br from-slate-100 to-slate-200 sm:h-36 sm:w-40 dark:from-slate-800 dark:to-slate-700">
                        @if ($item->photo_path)
                            <img src="{{ asset('storage/'.$item->photo_path) }}" alt="{{ $item->name }}" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full w-full items-center justify-center text-[11px] font-semibold text-slate-400 dark:text-slate-500">Tanpa foto</div>
                        @endif
                    </div>
                    <dl class="grid flex-1 grid-cols-1 gap-x-4 gap-y-2.5 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Kategori</dt>
                            <dd class="mt-0.5 break-words font-semibold text-slate-800 dark:text-slate-100">{{ $item->category?->name ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Ruangan</dt>
                            <dd class="mt-0.5 break-words font-semibold text-slate-800 dark:text-slate-100">
                                {{ $item->room?->name ?: '—' }}
                                @if ($item->room)
                                    <span class="font-normal text-slate-500 dark:text-slate-400">({{ $item->room->code }}, Lt.{{ $item->room->floor }})</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Jurusan</dt>
                            <dd class="mt-0.5 break-words font-semibold text-slate-800 dark:text-slate-100">{{ $item->room?->major?->name ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Spesifikasi</dt>
                            <dd class="mt-0.5 break-words font-medium text-slate-700 dark:text-slate-200">{{ $item->specification ?: 'Tidak ada spesifikasi detail.' }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            {{-- Identitas inventaris + tombol copy --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900"
                     x-data="{
                        copied: null,
                        copy(text, key) {
                            const done = () => { this.copied = key; setTimeout(() => { this.copied = null; }, 1500); };
                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText(text).then(done).catch(() => this.fallback(text, done));
                            } else {
                                this.fallback(text, done);
                            }
                        },
                        fallback(text, done) {
                            const ta = document.createElement('textarea');
                            ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
                            document.body.appendChild(ta); ta.select();
                            try { document.execCommand('copy'); } catch (e) {}
                            document.body.removeChild(ta); done();
                        }
                     }">
                <h3 class="mb-3 flex items-center gap-2.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                        <span class="material-icons-round" aria-hidden="true">tag</span>
                    </span>
                    Identitas inventaris
                </h3>
                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-2.5 dark:bg-slate-800/60">
                        <div class="min-w-0">
                            <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">ID internal / spasial</p>
                            <p class="mt-0.5 break-all font-mono text-sm font-bold text-slate-900 dark:text-slate-100">{{ $item->internal_id ?: '—' }}</p>
                            <p class="mt-0.5 text-[10px] text-slate-400 dark:text-slate-500">Untuk pencarian ruangan</p>
                        </div>
                        <button type="button" data-copy="{{ $item->internal_id }}" @click="copy($el.dataset.copy, 'internal')"
                                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                                aria-label="Salin ID internal" title="Salin ID internal">
                            <span class="material-icons-round text-base" x-show="copied !== 'internal'">content_copy</span>
                            <span class="material-icons-round text-base text-green-600 dark:text-green-400" x-show="copied === 'internal'" x-cloak>check</span>
                        </button>
                    </div>
                    <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-2.5 dark:bg-slate-800/60">
                        <div class="min-w-0">
                            <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Standar aset, baris 1 (kode lokasi/instansi)</p>
                            <p class="mt-0.5 break-all font-mono text-sm font-bold text-slate-900 dark:text-slate-100">{{ $item->location_code ?: '—' }}</p>
                            <p class="mt-0.5 text-[10px] text-slate-400 dark:text-slate-500">Untuk pelaporan dinas/yayasan</p>
                        </div>
                        <button type="button" data-copy="{{ $item->location_code }}" @click="copy($el.dataset.copy, 'location')"
                                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                                aria-label="Salin kode lokasi" title="Salin kode lokasi">
                            <span class="material-icons-round text-base" x-show="copied !== 'location'">content_copy</span>
                            <span class="material-icons-round text-base text-green-600 dark:text-green-400" x-show="copied === 'location'" x-cloak>check</span>
                        </button>
                    </div>
                    <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-2.5 dark:bg-slate-800/60">
                        <div class="min-w-0">
                            <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Nomor register (opsional)</p>
                            <p class="mt-0.5 break-all font-mono text-sm font-bold text-slate-900 dark:text-slate-100">{{ $item->register_no ?: '—' }}</p>
                            <p class="mt-0.5 text-[10px] text-slate-400 dark:text-slate-500">Administratif, bukan kode inventaris unit</p>
                        </div>
                        <button type="button" data-copy="{{ $item->register_no }}" @click="copy($el.dataset.copy, 'register')"
                                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                                aria-label="Salin nomor register" title="Salin nomor register">
                            <span class="material-icons-round text-base" x-show="copied !== 'register'">content_copy</span>
                            <span class="material-icons-round text-base text-green-600 dark:text-green-400" x-show="copied === 'register'" x-cloak>check</span>
                        </button>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-slate-400 dark:text-slate-500">Label terakhir dicetak: {{ $item->last_label_printed_at?->format('d M Y H:i') ?: 'belum pernah' }}</p>
            </section>

            {{-- Kelompok spesifikasi & alokasi pengadaan --}}
            @if ($item->allocations->isNotEmpty())
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="mb-3 flex items-center gap-2.5 text-base font-bold text-slate-900 dark:text-slate-100">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                            <span class="material-icons-round" aria-hidden="true">account_tree</span>
                        </span>
                        Kelompok Spesifikasi &amp; Alokasi Pengadaan
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-left text-xs">
                            <thead class="bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                                <tr>
                                    <th class="px-2 py-2">Spesifikasi</th>
                                    <th class="px-2 py-2">Ruangan</th>
                                    <th class="px-2 py-2 text-center">Diterima / Dialokasikan</th>
                                    <th class="px-2 py-2 text-center">Kondisi</th>
                                    <th class="px-2 py-2">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($item->allocations as $allocation)
                                    <tr>
                                        <td class="px-2 py-2">{{ $allocation->specification?->displayName() ?? '—' }}</td>
                                        <td class="px-2 py-2">{{ $allocation->room?->code }} — {{ $allocation->room?->name }}</td>
                                        <td class="px-2 py-2 text-center">{{ $allocation->received_quantity ?? $allocation->quantity }} / {{ $allocation->quantity }}</td>
                                        <td class="px-2 py-2 text-center">{{ $allocation->conditionEnum()->label() }}</td>
                                        <td class="px-2 py-2">{{ $allocation->note ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            {{-- Riwayat (tab) --}}
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900" x-data="{ tab: 'peminjaman' }">
                <div class="flex flex-wrap items-center gap-1 border-b border-slate-100 px-2 pt-2 dark:border-slate-800">
                    <h3 class="mb-1 me-2 flex items-center gap-2.5 px-2 text-base font-bold text-slate-900 dark:text-slate-100">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                            <span class="material-icons-round" aria-hidden="true">history</span>
                        </span>
                        Riwayat
                    </h3>
                    <button type="button" @click="tab = 'peminjaman'"
                            :class="tab === 'peminjaman' ? 'border-brand-600 text-brand-700 dark:text-brand-300' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                            class="inline-flex min-h-9 items-center border-b-2 px-3 text-xs font-semibold transition">Peminjaman</button>
                    <button type="button" @click="tab = 'mutasi'"
                            :class="tab === 'mutasi' ? 'border-brand-600 text-brand-700 dark:text-brand-300' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                            class="inline-flex min-h-9 items-center border-b-2 px-3 text-xs font-semibold transition">Mutasi lokasi</button>
                    <button type="button" @click="tab = 'jadwal'"
                            :class="tab === 'jadwal' ? 'border-brand-600 text-brand-700 dark:text-brand-300' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                            class="inline-flex min-h-9 items-center border-b-2 px-3 text-xs font-semibold transition">Jadwal pemesanan</button>
                </div>

                {{-- Tab: Peminjaman --}}
                <div x-show="tab === 'peminjaman'" x-cloak class="p-4">
                    @if ($lastBorrower)
                        <p class="mb-2 text-[11px] text-slate-500 dark:text-slate-400">Peminjam terakhir: <strong class="text-slate-700 dark:text-slate-200">{{ $lastBorrower->name }}</strong></p>
                    @endif
                    @if ($loanHistory->isEmpty())
                        <p class="text-sm text-slate-400 dark:text-slate-500">Belum ada riwayat peminjaman.</p>
                    @else
                        <p class="mb-2 text-[11px] text-slate-400 md:hidden dark:text-slate-500">Geser tabel ke samping untuk melihat semua kolom.</p>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[700px] table-fixed text-sm">
                                <colgroup>
                                    <col class="w-[18%]">
                                    <col class="w-[20%]">
                                    <col class="w-[19%]">
                                    <col class="w-[12%]">
                                    <col class="w-[16%]">
                                    <col class="w-[15%]">
                                </colgroup>
                                <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                                    <tr>
                                        <th scope="col" class="px-3 py-3 text-left">Peminjam</th>
                                        <th scope="col" class="px-3 py-3 text-left">Tujuan</th>
                                        <th scope="col" class="px-3 py-3 text-left">Periode</th>
                                        <th scope="col" class="px-2 py-3 text-center">Qty</th>
                                        <th scope="col" class="px-2 py-3 text-center">Kondisi Kembali</th>
                                        <th scope="col" class="px-2 py-3 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach ($loanHistory as $li)
                                        <tr class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                                            <td class="truncate px-3 py-4">
                                                <a href="{{ route('loans.show', $li->loan) }}" class="font-semibold text-slate-800 hover:text-brand-700 dark:text-slate-100 dark:hover:text-brand-300">{{ $li->loan?->borrower?->name }}</a>
                                                <div class="mt-0.5 truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $li->loan?->event?->name ?: '—' }}</div>
                                            </td>
                                            <td class="truncate px-3 py-4 text-xs text-slate-600 dark:text-slate-300" title="{{ $li->loan?->purpose }}">{{ \Illuminate\Support\Str::limit($li->loan?->purpose, 40) }}</td>
                                            <td class="whitespace-nowrap px-3 py-4 text-xs text-slate-600 dark:text-slate-300">{{ $li->loan?->start_date?->format('d/m/Y') }} – {{ $li->loan?->due_date?->format('d/m/Y') }}</td>
                                            <td class="px-2 py-4 text-center">
                                                <span class="inline-flex min-w-12 justify-center rounded-lg bg-slate-100 px-2 py-1 text-xs font-bold tabular-nums text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $li->qty_borrowed }} / {{ $li->qty_returned ?? '—' }}</span>
                                            </td>
                                            <td class="px-2 py-4 text-center text-xs">
                                                @if ($li->condition_in)
                                                    <span class="inline-flex justify-center rounded-lg px-2 py-1 font-semibold {{ $li->condition_in->isDamaged() ? 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300' : 'bg-green-50 text-green-700 dark:bg-green-950/50 dark:text-green-300' }}">{{ $li->condition_in->label() }}</span>
                                                @else
                                                    <span class="text-slate-400 dark:text-slate-500">—</span>
                                                @endif
                                            </td>
                                            <td class="px-2 py-4 text-center">
                                                <span class="inline-flex justify-center rounded-lg px-2 py-1 text-[10px] font-bold {{ $li->loan?->status?->badgeClass() }}">{{ $li->loan?->status?->label() }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Tab: Mutasi lokasi --}}
                <div x-show="tab === 'mutasi'" x-cloak class="p-4">
                    @if ($item->movements->isEmpty())
                        <p class="text-sm text-slate-400 dark:text-slate-500">Belum pernah dipindahkan.</p>
                    @else
                        <ul class="space-y-2.5 text-xs">
                            @foreach ($item->movements as $m)
                                <li class="border-b border-dashed border-slate-100 pb-2 dark:border-slate-800">
                                    <div class="flex items-center gap-1 text-slate-700 dark:text-slate-200">
                                        <span class="font-semibold">{{ $m->fromRoom?->code }}</span>
                                        <span class="material-icons-round text-sm text-slate-400" aria-hidden="true">arrow_forward</span>
                                        <span class="font-semibold">{{ $m->toRoom?->code }}</span>
                                        <span class="ms-auto text-slate-400 dark:text-slate-500">{{ $m->moved_at?->format('d/m/Y') }}</span>
                                    </div>
                                    <div class="mt-0.5 text-slate-400 dark:text-slate-500">oleh {{ $m->mover?->name }}{{ $m->note ? ' — '.$m->note : '' }}</div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Tab: Jadwal pemesanan --}}
                <div x-show="tab === 'jadwal'" x-cloak class="p-4">
                    @if ($upcoming->isEmpty())
                        <p class="text-sm text-slate-400 dark:text-slate-500">Belum ada pemesanan mendatang — barang bebas dibooking.</p>
                    @else
                        <ul class="space-y-2 text-sm">
                            @foreach ($upcoming as $loan)
                                <li class="flex items-center justify-between gap-2 border-b border-dashed border-slate-100 pb-2 dark:border-slate-800">
                                    <div>
                                        <div class="text-xs font-medium text-slate-700 dark:text-slate-200">{{ $loan->purpose }}</div>
                                        <div class="text-[11px] text-slate-400 dark:text-slate-500">{{ $loan->start_date->format('d/m/Y H:i') }} → {{ $loan->due_date->format('d/m/Y H:i') }}</div>
                                    </div>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        </div>

        {{-- Kolom kanan --}}
        <div class="space-y-4">
            {{-- Pratinjau label stiker (QR) --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-4 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
                <h3 class="mb-3 text-sm font-bold text-slate-900 dark:text-slate-100">Label stiker</h3>
                <div class="inline-block w-full max-w-[320px] border-2 border-slate-800 rounded-lg p-2.5 bg-white text-left">
                    <div class="flex items-center gap-2.5">
                        <div class="shrink-0 w-11 sm:w-14 flex items-center justify-center">
                            @if ($institutionLogo = \App\Models\InstitutionSetting::current()->logoUrl())
                                <img src="{{ $institutionLogo }}" class="max-h-12 sm:max-h-14 w-auto object-contain" alt="logo">
                            @else
                                <span class="text-[9px] font-bold text-slate-400">LOGO<br>SEKOLAH</span>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-mono font-bold text-slate-900 leading-tight text-[16px] sm:text-[19px] break-words">
                                @foreach ($locationLines as $locationLine)
                                    {{ $locationLine }}@unless($loop->last)<br>@endunless
                                @endforeach
                            </div>
                            <div class="font-bold text-slate-900 leading-tight text-xs sm:text-sm mt-0.5 break-words uppercase">
                                {{ $item->name }}
                            </div>
                        </div>
                        <img src="{{ app(\App\Services\LabelService::class)->qrDataUrl($item) }}" alt="QR" class="shrink-0 h-12 w-12 sm:h-14 sm:w-14">
                    </div>
                    <div class="mt-2 border-t border-slate-800 pt-1 text-center text-[9px] sm:text-[10px] font-bold uppercase text-slate-900">
                        {{ \App\Models\InstitutionSetting::current()->institution_name }}
                    </div>
                </div>
                <a href="{{ route('items.label', $item) }}" target="_blank"
                   class="mt-3 inline-flex min-h-9 w-full items-center justify-center gap-1.5 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                    <span class="material-icons-round text-lg" aria-hidden="true">print</span> Cetak / Re-print Stiker
                </a>
                <p class="mt-2 text-[11px] text-slate-400 dark:text-slate-500">Terakhir dicetak {{ $item->last_label_printed_at?->format('d M Y, H:i') ?: 'belum pernah' }}</p>
            </section>

            {{-- Ketersediaan --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
                <h3 class="mb-2 text-sm font-bold text-slate-900 dark:text-slate-100">Ketersediaan</h3>
                @if ($canBorrow)
                    <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">Tersedia <strong class="text-green-700 dark:text-green-400">{{ $available }} {{ $item->unit }}</strong>. Barang dapat diajukan untuk dipinjam.</p>
                    <a href="{{ route('loans.create') }}"
                       class="mt-3 inline-flex min-h-10 w-full items-center justify-center gap-1.5 rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                        <span class="material-icons-round text-lg" aria-hidden="true">add_shopping_cart</span> Ajukan Peminjaman
                    </a>
                @else
                    <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">
                        Tidak bisa dipinjam:
                        @if ($isDeleted)
                            barang berstatus dihapus.
                        @elseif (! $item->isRecordedInInventory())
                            barang belum tercatat di inventaris.
                        @else
                            stok tersedia 0 {{ $item->unit }}.
                        @endif
                    </p>
                    <button type="button" disabled
                            class="mt-3 inline-flex min-h-10 w-full cursor-not-allowed items-center justify-center gap-1.5 rounded-xl bg-slate-200 px-4 text-sm font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <span class="material-icons-round text-lg" aria-hidden="true">block</span> Ajukan Peminjaman
                    </button>
                @endif
            </section>

            {{-- Peminjam terakhir --}}
            @if ($lastBorrower)
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
                    <h3 class="mb-2 flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-slate-100">
                        <span class="material-icons-round text-base text-brand-600 dark:text-brand-300" aria-hidden="true">person</span>
                        Peminjam terakhir
                    </h3>
                    <div class="text-sm">
                        <div class="font-semibold text-slate-800 dark:text-slate-100">{{ $lastBorrower->name }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $lastBorrower->email }} • {{ $lastBorrower->role }}</div>
                        <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Dipinjam {{ $item->latestLoanItem()?->created_at?->diffForHumans() }}</div>
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
