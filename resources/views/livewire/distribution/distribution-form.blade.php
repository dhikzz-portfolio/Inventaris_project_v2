<div>
    @if (session('success'))
        <div role="status" class="mb-4 flex items-start gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            <span class="material-icons-round text-lg" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <form wire:submit="submit" class="lg:col-span-2 rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 class="mb-4 text-base font-bold text-slate-900 dark:text-slate-100">Pilih barang yang didistribusikan</h3>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="distribution-catalog" class="block text-sm font-medium text-slate-700 dark:text-slate-300">1. Katalog barang *</label>
                    <select id="distribution-catalog" wire:model.live="catalogId"
                            class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">— pilih katalog —</option>
                        @foreach ($catalogs as $catalogOption)
                            <option value="{{ $catalogOption->id }}">{{ $catalogOption->name }} ({{ $catalogOption->category?->name }})</option>
                        @endforeach
                    </select>
                    @error('catalogId') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="distribution-specification" class="block text-sm font-medium text-slate-700 dark:text-slate-300">2. Merek &amp; tipe/model *</label>
                    <select id="distribution-specification" wire:model.live="specificationId"
                            @disabled($specifications->isEmpty())
                            class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 disabled:bg-slate-50 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">— pilih spesifikasi —</option>
                        @foreach ($specifications as $option)
                            <option value="{{ $option['id'] }}">{{ $option['label'] }} — tersedia {{ $option['available'] }} unit</option>
                        @endforeach
                    </select>
                    @if ($catalogId !== '' && $specifications->isEmpty())
                        <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">Katalog ini belum memiliki spesifikasi/unit yang dapat didistribusikan.</p>
                    @endif
                    @error('specificationId') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="distribution-quantity" class="block text-sm font-medium text-slate-700 dark:text-slate-300">3. Jumlah unit *</label>
                    <input id="distribution-quantity" type="number" min="1" wire:model.live="quantity"
                           class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    @error('quantity') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="distribution-room" class="block text-sm font-medium text-slate-700 dark:text-slate-300">4. Lokasi tujuan *</label>
                    <select id="distribution-room" wire:model.live="toRoomId"
                            class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">— pilih ruangan —</option>
                        @foreach ($rooms as $roomOption)
                            <option value="{{ $roomOption->id }}">Gedung {{ $roomOption->building_letter }}{{ $roomOption->floor }}-{{ str_pad((string) $roomOption->room_no, 2, '0', STR_PAD_LEFT) }} — {{ $roomOption->name }}</option>
                        @endforeach
                    </select>
                    @error('toRoomId') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="distribution-note" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Catatan</label>
                    <textarea id="distribution-note" wire:model="note" rows="2"
                              class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                              placeholder="Contoh: kebutuhan praktik kelas XI"></textarea>
                    @error('note') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-700 dark:bg-slate-800/60">
                <p class="font-semibold text-slate-800 dark:text-slate-100">Ringkasan sebelum disimpan</p>
                <dl class="mt-2 grid grid-cols-1 gap-1 sm:grid-cols-2">
                    <div class="flex justify-between gap-2"><dt class="text-slate-500 dark:text-slate-400">Katalog</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ $catalogs->firstWhere('id', (int) $catalogId)?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500 dark:text-slate-400">Merek / tipe</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ $specifications->firstWhere('id', (int) $specificationId)['label'] ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500 dark:text-slate-400">Stok spesifikasi</dt><dd class="font-bold text-slate-900 dark:text-white">{{ $stockAvailable }} unit</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500 dark:text-slate-400">Jumlah distribusi</dt><dd class="font-bold text-slate-900 dark:text-white">{{ $quantity }} unit</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500 dark:text-slate-400">Tujuan</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ $selectedRoom?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500 dark:text-slate-400">Sisa setelah distribusi</dt><dd class="font-medium {{ $stockAvailable - $quantity < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-800 dark:text-slate-100' }}">{{ $stockAvailable - $quantity }} unit</dd></div>
                </dl>
                <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">
                    Stok dihitung hanya dari unit spesifikasi ini yang aktif, tidak dipinjam, dan belum pernah didistribusikan. Merek lain tidak ikut dihitung.
                </p>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <button type="submit" wire:loading.attr="disabled"
                        class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60">
                    <span class="material-icons-round text-lg">local_shipping</span> Distribusikan
                </button>
                <span wire:loading class="text-xs text-slate-500 dark:text-slate-400">Memproses…</span>
            </div>
        </form>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Stok per spesifikasi</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Stok dihitung terpisah untuk setiap merek dan tipe/model.</p>

            <div class="mt-3 space-y-2">
                @forelse ($specifications as $option)
                    <div class="rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700">
                        <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $option['label'] }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">Siap didistribusikan: {{ $option['available'] }} unit</div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400">Pilih katalog untuk melihat stok tiap spesifikasi.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Riwayat distribusi terakhir</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Setiap baris terhubung ke unit inventaris yang dipindahkan.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Waktu</th>
                        <th class="px-4 py-3 text-left">Katalog</th>
                        <th class="px-4 py-3 text-left">Merek / tipe</th>
                        <th class="px-4 py-3 text-right">Jumlah</th>
                        <th class="px-4 py-3 text-left">Tujuan</th>
                        <th class="px-4 py-3 text-left">Petugas</th>
                        <th class="px-4 py-3 text-right">Unit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($recent as $row)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-600 dark:text-slate-300">{{ $row->distributed_at?->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 text-xs text-slate-700 dark:text-slate-200">{{ $row->catalog?->name }}</td>
                            <td class="px-4 py-3 text-xs text-slate-700 dark:text-slate-200">{{ $row->specification?->displayName() }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums text-slate-800 dark:text-slate-100">{{ $row->quantity }}</td>
                            <td class="px-4 py-3 text-xs text-slate-700 dark:text-slate-200">{{ $row->toRoom?->name }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">{{ $row->actor?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-xs text-slate-500 dark:text-slate-400">
                                <a href="{{ route('specifications.show', $row->specification_id) }}" class="font-semibold text-brand-700 hover:underline dark:text-brand-300">Lihat</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada distribusi tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
