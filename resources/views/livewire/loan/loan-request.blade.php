<div class="grid grid-cols-1 lg:grid-cols-5 gap-5">
    {{-- Kiri: cari barang + keranjang --}}
    <div class="space-y-5 lg:col-span-3">
        <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 class="mb-3 flex items-center gap-1.5 text-sm font-bold text-slate-900 dark:text-slate-100">
                <span class="material-icons-round text-blue-600">add_shopping_cart</span> 1. Pilih Barang dari Katalog
            </h3>
            <div class="relative">
                <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">search</span>
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Cari nama, spesifikasi, kode inventaris..."
                       class="w-full rounded-xl border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
            </div>

            <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                <select wire:model.live="category" class="w-full min-w-0 rounded-xl border-slate-300 bg-white text-xs text-slate-700 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="building" class="w-full min-w-0 rounded-xl border-slate-300 bg-white text-xs text-slate-700 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                    <option value="">Semua gedung</option>
                    @foreach ($buildings as $b)
                        <option value="{{ $b['code'] }}">{{ $b['name'] }}</option>
                    @endforeach
                </select>
                <select wire:model.live="room" class="w-full min-w-0 rounded-xl border-slate-300 bg-white text-xs text-slate-700 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                    <option value="">Semua ruangan</option>
                    @foreach ($rooms as $r)
                        <option value="{{ $r->id }}">{{ $r->code }} — {{ $r->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="availability" class="w-full min-w-0 rounded-xl border-slate-300 bg-white text-xs text-slate-700 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                    <option value="">Semua ketersediaan</option>
                    <option value="available">Ada unit tersedia</option>
                    <option value="unavailable">Tidak ada unit tersedia</option>
                </select>
            </div>
            @if ($activeFilters > 0)
                <div class="mt-2 text-right">
                    <button wire:click="resetFilters" class="inline-flex min-h-9 items-center gap-1 rounded-lg px-2 text-xs font-semibold text-slate-500 transition hover:bg-blue-50 hover:text-blue-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-blue-300">
                        <span class="material-icons-round text-sm">filter_alt_off</span> Reset filter
                    </button>
                </div>
            @endif

            @if ($items->isNotEmpty())
                <ul class="mt-3 max-h-80 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-800">
                    @foreach ($items as $item)
                        @php($inCart = collect($cart)->contains(fn ($line) => (int) $line['item_id'] === $item->id))
                        <li class="flex items-start gap-3 py-3">
                            <div class="flex-1 min-w-0">
                                <div class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $item->name }}</div>
                                <div class="font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $item->internal_id }} • {{ $item->room?->code }}</div>
                                @if ($item->specification)
                                    <div class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">{{ $item->specification }}</div>
                                @endif
                                <div class="mt-0.5 text-[11px] text-slate-600 dark:text-slate-300">
                                    Tersedia <strong>{{ $item->available_units_count }}</strong> dari {{ $item->quantity }} {{ $item->unit }}
                                </div>
                                @if ($item->units->isNotEmpty())
                                    <div class="mt-0.5 font-mono text-[10px] text-slate-500 dark:text-slate-400">
                                        Unit: {{ $item->units->pluck('inventory_code')->implode(', ') }}{{ $item->available_units_count > $item->units->count() ? ', …' : '' }}
                                    </div>
                                @endif
                            </div>
                            @if ($item->is_high_value)
                                <span class="rounded-full bg-purple-50 px-2 py-1 text-[10px] font-bold text-purple-700 dark:bg-purple-950/50 dark:text-purple-300">NILAI TINGGI</span>
                            @endif
                            @if ($inCart)
                                <span class="shrink-0 text-[11px] font-semibold text-green-700 dark:text-green-300">✓ di keranjang</span>
                            @elseif ($item->available_units_count < 1)
                                <span class="shrink-0 text-[10px] text-slate-400 dark:text-slate-500">tidak tersedia</span>
                            @else
                                <button wire:click="add({{ $item->id }})"
                                        class="inline-flex min-h-9 shrink-0 items-center gap-1 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                                    <span class="material-icons-round text-sm">add</span> Tambah
                                </button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-3 rounded-xl bg-slate-50 py-6 text-center text-sm text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">Barang tidak ditemukan dengan filter ini.</p>
            @endif
        </div>

        <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 class="mb-3 flex items-center gap-1.5 text-sm font-bold text-slate-900 dark:text-slate-100">
                <span class="material-icons-round text-blue-600">shopping_basket</span> 2. Keranjang ({{ count($cart) }} barang)
            </h3>

            @if ($cart === [])
                <p class="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">Keranjang kosong. Tambahkan barang dari pencarian di atas.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-100 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:text-slate-400">
                            <tr>
                                <th class="text-left py-2">Barang</th>
                                <th class="text-left py-2">Ruangan</th>
                                <th class="text-center py-2 w-28">Jumlah</th>
                                <th class="text-left py-2">Ketersediaan</th>
                                <th class="w-8"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($cart as $index => $line)
                                <tr>
                                    <td class="py-2.5">
                                        <div class="text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $line['name'] }}</div>
                                        <div class="font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $line['internal_id'] }}</div>
                                    </td>
                                    <td class="py-2.5 text-xs text-slate-600 dark:text-slate-300">{{ $line['room'] }}</td>
                                    <td class="py-2.5 text-center">
                                        <input type="number" min="1" max="{{ $line['max'] }}" wire:model.live="cart.{{ $index }}.qty"
                                               class="w-20 rounded-lg border-slate-300 bg-white text-center text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                        <div class="text-[10px] text-slate-500 dark:text-slate-400">maks {{ $line['max'] }}</div>
                                    </td>
                                    <td class="py-2.5">
                                        @if (isset($lineStatus[$line['item_id']]))
                                            @if ($lineStatus[$line['item_id']]['ok'])
                                                <span class="inline-flex items-center gap-1 text-[11px] text-green-700 dark:text-green-300">
                                                    <span class="material-icons-round text-sm">check_circle</span> Tersedia
                                                </span>
                                            @else
                                                <span class="inline-flex max-w-[220px] items-start gap-1 text-[11px] text-red-600 dark:text-red-300">
                                                    <span class="material-icons-round text-sm">error</span> {{ $lineStatus[$line['item_id']]['msg'] }}
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-[11px] text-slate-500 dark:text-slate-400">isi tanggal dulu</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 text-center">
                                        <button wire:click="remove({{ $index }})" title="Hapus dari keranjang"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-red-500 transition hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-red-300 dark:hover:bg-red-950/40 dark:hover:text-red-200">
                                            <span class="material-icons-round text-lg" aria-hidden="true">close</span>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex items-start gap-2 rounded-xl bg-blue-50 px-3 py-2.5 text-xs leading-relaxed text-blue-800 dark:bg-blue-950/40 dark:text-blue-200">
                    <span class="material-icons-round text-sm">workspace_premium</span>
                    Barang bernilai tinggi memerlukan persetujuan <strong>Wakasek Sarpras</strong>. Laboran/K3 yang meminjam dari laboratorium sendiri disetujui otomatis untuk barang biasa. Tanda tangan digital bersifat opsional.
                </div>
            @endif
        </div>

        {{-- Kalender ketersediaan --}}
        <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h3 class="flex items-center gap-1.5 text-sm font-bold text-slate-900 dark:text-slate-100">
                    <span class="material-icons-round text-blue-600">calendar_month</span> Kalender Ketersediaan
                </h3>
                <div class="flex items-center gap-1">
                    <button wire:click="prevMonth" aria-label="Bulan sebelumnya" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"><span class="material-icons-round text-lg" aria-hidden="true">chevron_left</span></button>
                    <span class="w-32 text-center text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $calendar['label'] }}</span>
                    <button wire:click="nextMonth" aria-label="Bulan berikutnya" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"><span class="material-icons-round text-lg" aria-hidden="true">chevron_right</span></button>
                </div>
            </div>

            @if (count($cart) === 0)
                <p class="rounded-xl bg-slate-50 px-3 py-6 text-center text-xs text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">Tambahkan barang ke keranjang untuk melihat kalender pemesanannya.</p>
            @else
                <div class="mb-1 grid grid-cols-7 gap-1 text-center text-[10px] font-semibold text-slate-500 dark:text-slate-400">
                    @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $d)
                        <div>{{ $d }}</div>
                    @endforeach
                </div>
                <div class="grid grid-cols-7 gap-1">
                    @for ($i = 0; $i < $calendar['leading']; $i++)
                        <div></div>
                    @endfor
                    @foreach ($calendar['days'] as $d)
                        <div @class([
                            'rounded-lg border py-2 text-xs font-semibold',
                            'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300' => $d['busy'],
                            'border-green-200 bg-green-50 text-green-700 dark:border-green-900 dark:bg-green-950/40 dark:text-green-300' => ! $d['busy'],
                        ]) title="{{ $d['busy'] ? 'Sudah dibooking/dipinjam' : 'Tersedia' }}">
                            {{ $d['day'] }}
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-[11px] text-slate-600 dark:text-slate-300">
                    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-green-100 border border-green-300"></span> Tersedia</span>
                    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-red-100 border border-red-300"></span> Terpakai (anti-bentrok)</span>
                </div>
                @if ($calendar['busyRanges'])
                    <ul class="mt-3 space-y-1 border-t border-slate-100 pt-3 text-xs text-slate-600 dark:border-slate-800 dark:text-slate-300">
                        @foreach ($calendar['busyRanges'] as $range)
                            <li class="flex items-start gap-1"><span class="material-icons-round text-sm text-red-400">event_busy</span> {{ $range }}</li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>
    </div>

    {{-- Kanan: form pengajuan --}}
    <div class="lg:col-span-2">
        <div class="sticky top-20 rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 class="mb-4 flex items-center gap-1.5 text-sm font-bold text-slate-900 dark:text-slate-100">
                <span class="material-icons-round text-blue-600">edit_note</span> 3. Detail Pengajuan
            </h3>

            @if ($error)
                <div class="mb-3 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                    <span class="material-icons-round text-lg shrink-0">error</span>
                    <span>{{ $error }}</span>
                </div>
            @endif

            <div class="space-y-4">
                <div>
                    <x-input-label value="Tujuan Peminjaman *" />
                    <textarea wire:model.live="purpose" rows="2"
                              class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                              placeholder="Ujian praktik, kegiatan sekolah, praktikum..."></textarea>
                </div>

                <div>
                    <x-input-label value="Tanggal & Waktu Pinjam *" />
                    <input type="datetime-local" wire:model.live="start_date"
                           class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                </div>

                <div>
                    <x-input-label value="Tanggal & Waktu Kembali *" />
                    <input type="datetime-local" wire:model.live="due_date"
                           class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                </div>

                <div>
                    <x-input-label value="Kegiatan / Event (opsional)" />
                    <select wire:model.live="event_id" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                        <option value="">— tanpa kegiatan (pinjam biasa) —</option>
                        @foreach ($events as $event)
                            <option value="{{ $event->id }}">{{ $event->name }} ({{ $event->start_date->format('d/m') }}–{{ $event->end_date->format('d/m/Y') }})</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Peminjaman massal terikat satu kegiatan → satu paket BAST.</p>
                </div>

                <button wire:click="submit" wire:loading.attr="disabled"
                        class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-slate-900">
                    <span wire:loading.remove wire:target="submit" class="material-icons-round text-lg">send</span>
                    <span wire:loading wire:target="submit" class="material-icons-round text-lg animate-spin">progress_activity</span>
                    Kirim Request Peminjaman
                </button>

                <p class="text-center text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                    @if (auth()->user()->isPeminjam())
                        Permintaan akan ditinjau oleh petugas. Barang bernilai tinggi memerlukan persetujuan berjenjang dari Laboran lalu Wakasek. Dokumen dapat dicetak untuk ditandatangani secara fisik.
                    @else
                        Barang bernilai tinggi melewati persetujuan Laboran lalu Wakasek. Tanda tangan digital opsional — dokumen dapat ditandatangani secara fisik.
                    @endif
                </p>
            </div>
        </div>
    </div>
</div>
