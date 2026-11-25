<div>
    @if (session('success'))
        <div role="status" class="mb-4 flex items-start gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            <span class="material-icons-round text-lg" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <form wire:submit="submit" class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <label for="stock-catalog" class="block text-sm font-medium text-slate-700 dark:text-slate-300">1. Katalog dengan stok diterima *</label>
                <select id="stock-catalog" wire:model.live="catalogId"
                        class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">— pilih katalog —</option>
                    @foreach ($catalogs as $option)
                        <option value="{{ $option['id'] }}">
                            {{ $option['name'] }}{{ $option['category'] ? ' — '.$option['category'] : '' }} (sisa {{ $option['remaining'] }})
                        </option>
                    @endforeach
                </select>
                @error('catalogId') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                    Hanya katalog dengan penerimaan tercatat yang tampil. Sisa stok = jumlah diterima − jumlah yang sudah didistribusikan.
                </p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-700 dark:bg-slate-800/60">
                <p class="font-semibold text-slate-800 dark:text-slate-100">Sisa stok</p>
                @if ($selectedCatalog)
                    <p class="mt-1 text-2xl font-bold tabular-nums {{ $overStock ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">
                        {{ $stockRemaining }} <span class="text-sm font-medium text-slate-500 dark:text-slate-400">unit</span>
                    </p>
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                        Diterima {{ $selectedCatalog['received'] }} • sudah didistribusikan {{ $selectedCatalog['distributed'] }}
                    </p>
                    <p class="mt-2 text-[11px] {{ $overStock ? 'font-semibold text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400' }}">
                        Diminta pada formulir: {{ $requestedTotal }} unit{{ $overStock ? ' — melebihi sisa stok' : '' }}
                    </p>
                @else
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Pilih katalog untuk melihat sisa stok.</p>
                @endif
            </div>
        </div>

        @error('rows') <p class="mt-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $message }}</p> @enderror

        <div class="mt-6 space-y-4">
            @foreach ($rows as $index => $row)
                <section wire:key="stock-row-{{ $row['key'] ?? $index }}" class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-800/40">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Alokasi {{ $index + 1 }}</h3>
                        <button type="button" wire:click="removeRow({{ $index }})"
                                @disabled(count($rows) <= 1)
                                class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-900">
                            <span class="material-icons-round text-sm">delete</span> Hapus
                        </button>
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Ruangan tujuan *</label>
                            <select wire:model="rows.{{ $index }}.room_id"
                                    class="block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                <option value="">— pilih ruangan —</option>
                                @foreach ($rooms as $room)
                                    <option value="{{ $room->id }}">Gedung {{ $room->building_letter }}{{ $room->floor }}-{{ str_pad((string) $room->room_no, 2, '0', STR_PAD_LEFT) }} — {{ $room->name }}</option>
                                @endforeach
                            </select>
                            @error('rows.'.$index.'.room_id') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Jumlah *</label>
                            <input type="number" min="1" wire:model.live="rows.{{ $index }}.quantity"
                                   class="block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            @error('rows.'.$index.'.quantity') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Kondisi fisik</label>
                            <select wire:model="rows.{{ $index }}.condition"
                                    class="block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                @foreach ($conditions as $condition)
                                    <option value="{{ $condition->value }}">{{ $condition->label() }}</option>
                                @endforeach
                            </select>
                            @error('rows.'.$index.'.condition') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Merek</label>
                            <input type="text" maxlength="100" wire:model="rows.{{ $index }}.brand" placeholder="mis. Futura"
                                   class="block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            @error('rows.'.$index.'.brand') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Model / tipe</label>
                            <input type="text" maxlength="100" wire:model="rows.{{ $index }}.model_type" placeholder="mis. Kursi Susun"
                                   class="block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            @error('rows.'.$index.'.model_type') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Tahun perolehan</label>
                            <input type="number" min="1990" max="{{ now()->year + 1 }}" wire:model="rows.{{ $index }}.acquisition_year"
                                   placeholder="{{ now()->year }}"
                                   class="block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            @error('rows.'.$index.'.acquisition_year') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Sumber dana</label>
                            <input type="text" maxlength="100" wire:model="rows.{{ $index }}.funding_source" placeholder="BOS"
                                   class="block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            @error('rows.'.$index.'.funding_source') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Status operasional</label>
                            <select wire:model.live="rows.{{ $index }}.status"
                                    class="block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            @error('rows.'.$index.'.status') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        @if (($row['status'] ?? null) !== null && ($row['status'] ?? null) !== \App\Enums\UnitStatus::AKTIF->value)
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Alasan status *</label>
                                <input type="text" maxlength="255" wire:model="rows.{{ $index }}.reason" placeholder="Wajib diisi"
                                       class="block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                @error('rows.'.$index.'.reason') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <div class="sm:col-span-2 lg:col-span-3">
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Keterangan</label>
                            <input type="text" maxlength="500" wire:model="rows.{{ $index }}.note" placeholder="mis. kebutuhan praktik kelas XI"
                                   class="block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            @error('rows.'.$index.'.note') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">
                        Merek dan model pada baris ini membentuk kelompok spesifikasi tersendiri. Ruangan yang sama boleh menerima beberapa kelompok spesifikasi berbeda.
                    </p>
                </section>
            @endforeach
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button type="button" wire:click="addRow"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                <span class="material-icons-round text-base">add</span> Tambah alokasi ruangan
            </button>

            <button type="submit" wire:loading.attr="disabled"
                    class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60">
                <span class="material-icons-round text-lg">local_shipping</span> Distribusikan stok
            </button>
            <span wire:loading class="text-xs text-slate-500 dark:text-slate-400">Memproses…</span>
        </div>
    </form>
</div>
