<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <nav aria-label="Breadcrumb" class="mb-1 flex flex-wrap items-center gap-2 text-xs">
                    <a href="{{ route('catalogs.index') }}" class="font-medium text-slate-500 transition hover:text-brand-700 dark:text-slate-400 dark:hover:text-brand-300">Katalog</a>
                    <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">/</span>
                    <a href="{{ route('units.show', $unit) }}" class="font-medium text-slate-500 transition hover:text-brand-700 dark:text-slate-400 dark:hover:text-brand-300">{{ $unit->inventory_code }}</a>
                    <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">/</span>
                    <span class="text-slate-500 dark:text-slate-400">Edit</span>
                </nav>
                <h2 class="truncate text-xl font-bold leading-tight text-slate-900 dark:text-white">Edit Data Unit</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-mono font-semibold text-slate-700 dark:text-slate-200">{{ $unit->inventory_code }}</span>
                    <span aria-hidden="true">·</span> {{ $unit->catalog?->name ?: 'Tanpa katalog' }}
                </p>
            </div>
            <a href="{{ route('units.show', $unit) }}"
               class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                <span class="material-icons-round text-base" aria-hidden="true">arrow_back</span> Kembali
            </a>
        </div>
    </x-slot>

    @if ($errors->any())
        <div role="alert" class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <span class="material-icons-round text-lg" aria-hidden="true">error_outline</span>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('units.update-details', $unit) }}" class="space-y-4">
        @csrf
        @method('PATCH')

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 class="mb-1 text-base font-bold text-slate-900 dark:text-slate-100">Identitas Unit</h3>
            <p class="mb-4 text-xs leading-5 text-slate-500 dark:text-slate-400">Kode inventaris dan lokasi tidak diubah di sini. Gunakan mutasi untuk memindahkan unit. Nama/kategori katalog diubah dari halaman katalog.</p>

            <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-3">
                <div class="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-800/60">
                    <dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Kode inventaris</dt>
                    <dd class="mt-0.5 break-all font-mono text-sm font-bold text-slate-900 dark:text-slate-100">{{ $unit->inventory_code }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-800/60">
                    <dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Katalog</dt>
                    <dd class="mt-0.5 break-words font-semibold text-slate-800 dark:text-slate-100">{{ $unit->catalog?->name ?: '—' }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-800/60">
                    <dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Lokasi</dt>
                    <dd class="mt-0.5 break-words font-medium text-slate-800 dark:text-slate-100">{{ $unit->locationLabel() }}</dd>
                </div>
            </dl>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="unit-brand" class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-200">Merek</label>
                    <input id="unit-brand" name="brand" type="text" maxlength="100"
                           value="{{ old('brand', $unit->specification?->brand ?? $unit->catalog?->brand) }}"
                           placeholder="Contoh: Epson"
                           class="h-10 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    @error('brand') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="unit-model" class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-200">Tipe / model</label>
                    <input id="unit-model" name="model_type" type="text" maxlength="100"
                           value="{{ old('model_type', $unit->specification?->model_type ?? $unit->catalog?->model_type) }}"
                           placeholder="Contoh: EB-X51"
                           class="h-10 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    @error('model_type') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-[11px] leading-5 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                Merek dan tipe disimpan per unit. Mengubahnya akan memindahkan unit ini ke spesifikasi yang sesuai tanpa mengubah unit lain.
            </p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 class="mb-4 text-base font-bold text-slate-900 dark:text-slate-100">Kondisi &amp; Status</h3>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="unit-condition" class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-200">Kondisi</label>
                    <select id="unit-condition" name="condition" required class="h-10 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        @foreach ($conditions as $conditionOption)
                            <option value="{{ $conditionOption->value }}" @selected(old('condition', $unit->condition->value) === $conditionOption->value)>{{ $conditionOption->label() }}</option>
                        @endforeach
                    </select>
                    @error('condition') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="unit-status" class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-200">Status</label>
                    @if ($statusLocked)
                        <div class="flex h-10 items-center rounded-xl border border-slate-200 bg-slate-100 px-3 text-sm font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                            {{ $unit->status->label() }}
                        </div>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Status unit yang sedang dipinjam hanya berubah melalui proses pengembalian.</p>
                    @else
                        <select id="unit-status" name="status" required class="h-10 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            @foreach ($statuses as $statusOption)
                                <option value="{{ $statusOption->value }}" @selected(old('status', $unit->status->value) === $statusOption->value)>{{ $statusOption->label() }}</option>
                            @endforeach
                        </select>
                        @error('status') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    @endif
                </div>

                @unless ($statusLocked)
                    <div class="sm:col-span-2">
                        <label for="unit-status-reason" class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-200">Alasan perubahan status <span class="font-normal text-slate-500">(wajib bila status diubah)</span></label>
                        <input id="unit-status-reason" name="status_reason" type="text" maxlength="500"
                               value="{{ old('status_reason') }}"
                               placeholder="Contoh: tidak ditemukan saat pemeriksaan fisik"
                               class="h-10 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        @error('status_reason') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                @endunless
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 class="mb-4 text-base font-bold text-slate-900 dark:text-slate-100">Data Perolehan</h3>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="unit-acquisition-date" class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-200">Tanggal perolehan</label>
                    <input id="unit-acquisition-date" name="acquisition_date" type="date"
                           value="{{ old('acquisition_date', $unit->acquisition_date?->toDateString()) }}"
                           class="h-10 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    @error('acquisition_date') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="unit-acquisition-year" class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-200">Tahun perolehan</label>
                    <input id="unit-acquisition-year" name="acquisition_year" type="number" inputmode="numeric" min="1900" max="2100"
                           value="{{ old('acquisition_year', $unit->acquisition_year) }}"
                           placeholder="Contoh: 2024"
                           class="h-10 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    @error('acquisition_year') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="unit-acquisition-value" class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-200">Nilai perolehan (Rp)</label>
                    <input id="unit-acquisition-value" name="acquisition_value" type="number" inputmode="decimal" min="0" step="0.01"
                           value="{{ old('acquisition_value', $unit->acquisition_value) }}"
                           placeholder="Contoh: 4500000"
                           class="h-10 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    @error('acquisition_value') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="unit-register-no" class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-200">Nomor register</label>
                    <input id="unit-register-no" name="register_no" type="text" maxlength="100"
                           value="{{ old('register_no', $unit->register_no) }}"
                           class="h-10 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    @error('register_no') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="unit-funding-source" class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-200">Sumber dana</label>
                    <input id="unit-funding-source" name="funding_source" type="text" maxlength="100"
                           value="{{ old('funding_source', $unit->funding_source) }}"
                           placeholder="Contoh: BOS"
                           class="h-10 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    @error('funding_source') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="unit-acquisition-source" class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-200">Sumber perolehan</label>
                    <input id="unit-acquisition-source" name="acquisition_source" type="text" maxlength="150"
                           value="{{ old('acquisition_source', $unit->acquisition_source) }}"
                           placeholder="Contoh: Pembelian"
                           class="h-10 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    @error('acquisition_source') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="unit-notes" class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-200">Catatan</label>
                    <textarea id="unit-notes" name="notes" rows="3" maxlength="1000"
                              class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                              placeholder="Catatan tambahan tentang unit ini">{{ old('notes', $unit->notes) }}</textarea>
                    @error('notes') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <a href="{{ route('units.show', $unit) }}"
               class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                Batal
            </a>
            <button class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                <span class="material-icons-round text-base" aria-hidden="true">save</span> Simpan Perubahan
            </button>
        </div>
    </form>
</x-app-layout>
