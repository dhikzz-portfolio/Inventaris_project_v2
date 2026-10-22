<div class="grid grid-cols-1 lg:grid-cols-5 gap-5">
    {{-- Kiri: cari barang + keranjang --}}
    <div class="lg:col-span-3 space-y-5">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
                <span class="material-icons-round text-amber-600">add_shopping_cart</span> 1. Cari & Tambah Barang
            </h3>
            <div class="relative">
                <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Ketik minimal 2 huruf: nama barang / ID / kategori..."
                       class="w-full pl-10 pr-3 py-2.5 rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
            </div>

            @if ($items->isNotEmpty())
                <ul class="mt-3 divide-y divide-gray-100 max-h-64 overflow-y-auto">
                    @foreach ($items as $item)
                        <li class="py-2.5 flex items-center gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-800 truncate">{{ $item->name }}</div>
                                <div class="text-[11px] text-gray-400 font-mono">{{ $item->internal_id }} • {{ $item->room?->code }} • stok {{ $item->quantity }} {{ $item->unit }}</div>
                            </div>
                            @if ($item->is_high_value)
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-purple-100 text-purple-700">NILAI TINGGI</span>
                            @endif
                            @if (array_key_exists($item->id, array_column($cart, 'item_id')))
                                <span class="text-[11px] text-green-700 font-semibold">✓ di keranjang</span>
                            @else
                                <button wire:click="add({{ $item->id }})"
                                        class="shrink-0 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold">
                                    <span class="material-icons-round text-sm">add</span> Tambah
                                </button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @elseif (strlen($search) >= 2)
                <p class="mt-3 text-sm text-gray-400 text-center py-3">Barang tidak ditemukan.</p>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
                <span class="material-icons-round text-amber-600">shopping_basket</span> 2. Keranjang ({{ count($cart) }} barang)
            </h3>

            @if ($cart === [])
                <p class="text-sm text-gray-400 py-3 text-center">Keranjang kosong. Tambahkan barang dari pencarian di atas.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs uppercase text-gray-400">
                            <tr>
                                <th class="text-left py-2">Barang</th>
                                <th class="text-left py-2">Ruangan</th>
                                <th class="text-center py-2 w-28">Jumlah</th>
                                <th class="text-left py-2">Ketersediaan</th>
                                <th class="w-8"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($cart as $index => $line)
                                <tr>
                                    <td class="py-2.5">
                                        <div class="font-medium text-gray-800 text-xs">{{ $line['name'] }}</div>
                                        <div class="text-[11px] font-mono text-gray-400">{{ $line['internal_id'] }}</div>
                                    </td>
                                    <td class="py-2.5 text-xs text-gray-500">{{ $line['room'] }}</td>
                                    <td class="py-2.5 text-center">
                                        <input type="number" min="1" max="{{ $line['max'] }}" wire:model.live="cart.{{ $index }}.qty"
                                               class="w-20 text-center rounded-lg border-gray-300 text-xs focus:ring-amber-500 focus:border-amber-500">
                                        <div class="text-[10px] text-gray-400">maks {{ $line['max'] }}</div>
                                    </td>
                                    <td class="py-2.5">
                                        @if (isset($lineStatus[$line['item_id']]))
                                            @if ($lineStatus[$line['item_id']]['ok'])
                                                <span class="inline-flex items-center gap-1 text-[11px] text-green-700">
                                                    <span class="material-icons-round text-sm">check_circle</span> Tersedia
                                                </span>
                                            @else
                                                <span class="inline-flex items-start gap-1 text-[11px] text-red-600 max-w-[220px]">
                                                    <span class="material-icons-round text-sm">error</span> {{ $lineStatus[$line['item_id']]['msg'] }}
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-[11px] text-gray-400">isi tanggal dulu</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 text-center">
                                        <button wire:click="remove({{ $index }})" title="Hapus dari keranjang"
                                                class="text-red-400 hover:text-red-600">
                                            <span class="material-icons-round text-lg">close</span>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-2 text-[11px] text-gray-400 flex items-center gap-1">
                    <span class="material-icons-round text-sm">workspace_premium</span>
                    Barang bernilai tinggi pada keranjang memerlukan persetujuan Laboran → <strong>Wakasek Sarpras</strong>. Tanda tangan digital bersifat opsional — dokumen resmi dapat dicetak dan ditandatangani fisik.
                </div>
            @endif
        </div>

        {{-- Kalender ketersediaan --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-800 text-sm flex items-center gap-1.5">
                    <span class="material-icons-round text-amber-600">calendar_month</span> Kalender Ketersediaan
                </h3>
                <div class="flex items-center gap-1">
                    <button wire:click="prevMonth" class="p-1 rounded hover:bg-gray-100 text-gray-500"><span class="material-icons-round text-lg">chevron_left</span></button>
                    <span class="text-xs font-semibold text-gray-700 w-32 text-center">{{ $calendar['label'] }}</span>
                    <button wire:click="nextMonth" class="p-1 rounded hover:bg-gray-100 text-gray-500"><span class="material-icons-round text-lg">chevron_right</span></button>
                </div>
            </div>

            @if (count($cart) === 0)
                <p class="text-xs text-gray-400 py-4 text-center">Tambahkan barang ke keranjang untuk melihat kalender pemesanannya.</p>
            @else
                <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-semibold text-gray-400 mb-1">
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
                            'rounded-lg py-2 text-xs font-medium border',
                            'bg-red-50 border-red-200 text-red-700' => $d['busy'],
                            'bg-green-50 border-green-200 text-green-700' => ! $d['busy'],
                        ]) title="{{ $d['busy'] ? 'Sudah dibooking/dipinjam' : 'Tersedia' }}">
                            {{ $d['day'] }}
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex items-center gap-4 text-[10px] text-gray-500">
                    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-green-100 border border-green-300"></span> Tersedia</span>
                    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-red-100 border border-red-300"></span> Terpakai (anti-bentrok)</span>
                </div>
                @if ($calendar['busyRanges'])
                    <ul class="mt-2 space-y-1 text-[11px] text-gray-500">
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
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 sticky top-20">
            <h3 class="font-semibold text-gray-800 text-sm mb-4 flex items-center gap-1.5">
                <span class="material-icons-round text-amber-600">edit_note</span> 3. Detail Pengajuan
            </h3>

            @if ($error)
                <div class="mb-3 rounded-lg bg-red-50 border border-red-200 text-red-700 px-3 py-2.5 text-sm flex items-start gap-2">
                    <span class="material-icons-round text-lg shrink-0">error</span>
                    <span>{{ $error }}</span>
                </div>
            @endif

            <div class="space-y-4">
                <div>
                    <x-input-label value="Tujuan Peminjaman *" />
                    <textarea wire:model.live="purpose" rows="2"
                              class="mt-1 block w-full rounded-lg border-gray-300 focus:ring-amber-500 focus:border-amber-500 text-sm"
                              placeholder="Ujian praktik, kegiatan sekolah, praktikum..."></textarea>
                </div>

                <div>
                    <x-input-label value="Tanggal & Waktu Pinjam *" />
                    <input type="datetime-local" wire:model.live="start_date"
                           class="mt-1 block w-full rounded-lg border-gray-300 focus:ring-amber-500 focus:border-amber-500 text-sm">
                </div>

                <div>
                    <x-input-label value="Tanggal & Waktu Kembali *" />
                    <input type="datetime-local" wire:model.live="due_date"
                           class="mt-1 block w-full rounded-lg border-gray-300 focus:ring-amber-500 focus:border-amber-500 text-sm">
                </div>

                <div>
                    <x-input-label value="Kegiatan / Event (opsional)" />
                    <select wire:model.live="event_id" class="mt-1 block w-full rounded-lg border-gray-300 focus:ring-amber-500 focus:border-amber-500 text-sm">
                        <option value="">— tanpa kegiatan (pinjam biasa) —</option>
                        @foreach ($events as $event)
                            <option value="{{ $event->id }}">{{ $event->name }} ({{ $event->start_date->format('d/m') }}–{{ $event->end_date->format('d/m/Y') }})</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-gray-400 mt-1">Peminjaman massal terikat satu event → satu paket BAST.</p>
                </div>

                <button wire:click="submit" wire:loading.attr="disabled"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white text-sm font-bold">
                    <span wire:loading.remove wire:target="submit" class="material-icons-round text-lg">send</span>
                    <span wire:loading wire:target="submit" class="material-icons-round text-lg animate-spin">progress_activity</span>
                    Kirim Request Peminjaman
                </button>

                <p class="text-[11px] text-gray-400 text-center">
                    Barang bernilai tinggi melewati persetujuan Laboran lalu Wakasek. Tanda tangan digital opsional — dokumen dapat ditandatangani secara fisik.
                </p>
            </div>
        </div>
    </div>
</div>
