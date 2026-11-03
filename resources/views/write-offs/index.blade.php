@php
    $initialMode = old('mode', 'UNIT');
    $initialCatalog = old('catalog_id', '');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Inventaris Keluar</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Ajukan penghapusan unit tertentu atau seluruh unit dalam satu katalog.</p>
            </div>
            @if (auth()->user()->isApproverLevel2())
                <a href="{{ route('write-offs.pending') }}" class="inline-flex min-h-10 items-center gap-1 self-start rounded-lg px-3 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 sm:self-auto dark:text-brand-300 dark:hover:bg-slate-800">
                    Permintaan untuk disetujui <span aria-hidden="true">→</span>
                </a>
            @endif
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

    <div x-data="writeOffForm({
        mode: @js($initialMode),
        catalogId: @js($initialCatalog),
        summaries: @js($catalogSummaries),
        filteredTotal: {{ $units->total() }},
    })" x-cloak>

        {{-- Filter: parameter sama dengan mutasi & cetak label --}}
        <form method="GET" action="{{ route('write-offs.index') }}" class="mb-4 rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="flex items-center gap-1.5 text-sm font-bold text-slate-900 dark:text-slate-100">
                    <span class="material-icons-round text-base text-brand-600">filter_alt</span> Filter Unit
                </h3>
                <a href="{{ route('write-offs.index') }}" class="text-xs font-semibold text-brand-700 hover:underline dark:text-brand-300">Reset filter</a>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Kode / nama barang</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="G1-02-12 atau Kursi"
                           class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Kategori</label>
                    <select name="category" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($filters['category'] == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Katalog barang</label>
                    <select name="catalog" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @foreach ($catalogs as $catalog)
                            <option value="{{ $catalog->id }}" @selected($filters['catalog'] == $catalog->id)>{{ $catalog->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Merek</label>
                    <select name="brand" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand }}" @selected($filters['brand'] === $brand)>{{ $brand }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Tipe / model</label>
                    <select name="model" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @foreach ($models as $model)
                            <option value="{{ $model }}" @selected($filters['model'] === $model)>{{ $model }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Gedung</label>
                    <select name="building" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @foreach ($buildings as $building)
                            <option value="{{ $building->code }}" @selected(strtoupper($filters['building']) === $building->code)>{{ $building->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Lantai</label>
                    <select name="floor" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @for ($floor = 1; $floor <= 9; $floor++)
                            <option value="{{ $floor }}" @selected($filters['floor'] === (string) $floor)>Lantai {{ $floor }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Ruangan</label>
                    <select name="room" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->id }}" @selected($filters['room'] == $room->id)>{{ $room->code }} — {{ $room->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Kondisi</label>
                    <select name="condition" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @foreach ($conditions as $condition)
                            <option value="{{ $condition->value }}" @selected($filters['condition'] === $condition->value)>{{ $condition->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Status</label>
                    <select name="status" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button type="submit" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <span class="material-icons-round text-base">search</span> Terapkan filter
                </button>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $units->total() }} unit sesuai filter</span>
            </div>
        </form>

        <form method="POST" action="{{ route('write-offs.store') }}" enctype="multipart/form-data" x-ref="deleteForm">
            @csrf
            <input type="hidden" name="mode" :value="mode">
            {{-- Filter ikut dikirim agar "pilih semua" berlaku untuk seluruh hasil filter --}}
            @foreach ($filters as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <input type="hidden" name="select_all" :value="selectAll ? 1 : 0">
            <input type="hidden" name="confirm_delete" :value="confirmDelete ? 1 : ''">
            <input type="hidden" name="confirm_catalog" :value="confirmCatalog ? 1 : ''">

            <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
                    <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" @click="mode = 'UNIT'"
                                    :class="mode === 'UNIT' ? 'bg-brand-600 text-white' : 'border border-slate-200 bg-white text-slate-600 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-300'"
                                    class="inline-flex min-h-10 items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold transition">
                                <span class="material-icons-round text-lg">checklist</span> Pilih unit
                            </button>
                            <button type="button" @click="mode = 'CATALOG'"
                                    :class="mode === 'CATALOG' ? 'bg-red-600 text-white' : 'border border-slate-200 bg-white text-slate-600 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-300'"
                                    class="inline-flex min-h-10 items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold transition">
                                <span class="material-icons-round text-lg">delete_sweep</span> Seluruh katalog
                            </button>
                            <span class="ms-auto text-xs text-slate-500 dark:text-slate-400" x-show="mode === 'UNIT'">
                                <strong x-text="selectAll ? {{ $units->total() }} : selected.length"></strong> unit dipilih
                            </span>
                        </div>

                        <div x-show="mode === 'CATALOG'" class="mt-4 space-y-2">
                            <x-input-label for="catalog_id" value="Katalog yang dihapus seluruh unitnya *" />
                            <select id="catalog_id" name="catalog_id" x-model.number="catalogId"
                                    class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-red-500 focus:ring-red-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                <option value="">— pilih katalog —</option>
                                @foreach ($catalogs as $catalog)
                                    <option value="{{ $catalog->id }}" @selected(old('catalog_id') == $catalog->id)>{{ $catalog->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-red-700 dark:text-red-300">Tindakan ini mencakup seluruh unit yang memenuhi syarat dalam katalog, bukan hanya unit di halaman ini.</p>
                        </div>

                        <div x-show="mode === 'CATALOG' && catalogSummary" class="mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                            <div class="font-bold" x-text="catalogSummary?.name"></div>
                            <div class="mt-1 grid grid-cols-2 gap-1 sm:grid-cols-4">
                                <div>Spesifikasi: <strong x-text="catalogSummary?.spec_count"></strong></div>
                                <div>Total unit: <strong x-text="catalogSummary?.unit_count"></strong></div>
                                <div>Dapat dihapus: <strong x-text="catalogSummary?.eligible_count"></strong></div>
                                <div>Lokasi: <strong x-text="catalogSummary?.locations?.length || 0"></strong></div>
                            </div>
                            <div class="mt-1 text-[11px]" x-show="(catalogSummary?.eligible_count || 0) === 0">Tidak ada unit yang memenuhi syarat pada katalog ini.</div>
                        </div>
                    </div>

                    <div x-show="mode === 'UNIT'" class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Daftar Unit ({{ $units->total() }})</h3>
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
                                <input type="checkbox" x-model="selectAll" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                Pilih semua sesuai filter
                            </label>
                            <p class="w-full text-[11px] font-normal text-slate-400 md:hidden dark:text-slate-500">Geser tabel ke samping untuk melihat semua kolom.</p>
                        </div>

                        <div class="max-h-[60vh] overflow-auto">
                            <table class="w-full min-w-[900px] text-sm">
                                <thead class="sticky top-0 z-10 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                    <tr>
                                        <th class="w-10 px-3 py-2.5"></th>
                                        <th class="px-3 py-2.5 text-left">Kode</th>
                                        <th class="px-3 py-2.5 text-left">Barang</th>
                                        <th class="px-3 py-2.5 text-left">Merek / Tipe</th>
                                        <th class="px-3 py-2.5 text-left">Lokasi</th>
                                        <th class="px-3 py-2.5 text-left">Kondisi</th>
                                        <th class="px-3 py-2.5 text-left">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @forelse ($units as $unit)
                                        @php($blocked = $unit->is_on_loan || $unit->has_pending_write_off || $unit->status === \App\Enums\UnitStatus::DIPINJAM)
                                        <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                            <td class="px-3 py-2.5">
                                                <input type="checkbox" name="unit_ids[]" value="{{ $unit->id }}" x-model="selected"
                                                       @disabled($blocked)
                                                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-40">
                                            </td>
                                            <td class="px-3 py-2.5 font-mono font-semibold text-slate-800 dark:text-slate-100">{{ $unit->inventory_code }}</td>
                                            <td class="px-3 py-2.5">
                                                <div class="font-medium text-slate-700 dark:text-slate-200">{{ $unit->catalog?->name }}</div>
                                                <div class="text-[10px] text-slate-400">{{ $unit->catalog?->category?->name }}</div>
                                            </td>
                                            <td class="px-3 py-2.5 text-xs text-slate-600 dark:text-slate-300">
                                                {{ $unit->specification?->displayName() ?: '—' }}
                                            </td>
                                            <td class="px-3 py-2.5 text-xs text-slate-500 dark:text-slate-400">{{ $unit->locationLabel() }}</td>
                                            <td class="px-3 py-2.5">
                                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $unit->condition === \App\Enums\Condition::BAIK ? 'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300' }}">{{ $unit->condition->label() }}</span>
                                            </td>
                                            <td class="px-3 py-2.5">
                                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                                                @if ($unit->is_on_loan || $unit->status === \App\Enums\UnitStatus::DIPINJAM)
                                                    <div class="mt-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300">sedang dipinjam</div>
                                                @elseif ($unit->has_pending_write_off)
                                                    <div class="mt-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300">menunggu persetujuan</div>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="px-3 py-10 text-center text-sm text-slate-400">Tidak ada unit yang cocok dengan filter.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $units->links() }}</div>
                    </div>
                </div>

                <div class="space-y-4 rounded-2xl border border-sky-100 bg-white p-5 shadow-sm lg:sticky lg:top-20 dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                        <span class="material-icons-round text-red-500">delete_forever</span> Pengajuan Penghapusan
                    </h3>

                    <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                        <span x-show="mode === 'UNIT'"><strong x-text="selectAll ? {{ $units->total() }} : selected.length"></strong> unit dipilih.</span>
                        <span x-show="mode === 'CATALOG' && !catalogSummary">Pilih katalog terlebih dahulu.</span>
                        <span x-show="mode === 'CATALOG' && catalogSummary"><strong x-text="catalogSummary?.name"></strong> — <strong x-text="catalogSummary?.eligible_count"></strong> unit dapat dihapus.</span>
                    </div>

                    <div>
                        <x-input-label for="reason" value="Alasan Pemusnahan *" />
                        <textarea id="reason" name="reason" rows="3" required
                                  class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                                  placeholder="Rusak berat, tidak layak pakai, hilang...">{{ old('reason') }}</textarea>
                        <x-input-error class="mt-1" :messages="$errors->get('reason')" />
                    </div>

                    <div x-data="{ fileName: '' }">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Berita Acara (PDF/Gambar) *</span>
                            <span class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 transition hover:border-red-300 hover:bg-red-50 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                                <input type="file" name="berita_acara" accept=".pdf,.jpg,.jpeg,.png" required
                                       x-on:change="fileName = $event.target.files.length ? $event.target.files[0].name : ''"
                                       class="peer sr-only">
                                <span class="material-icons-round text-lg text-red-600 dark:text-red-400" aria-hidden="true">upload_file</span>
                                <span class="min-w-0 flex-1 truncate" x-text="fileName || 'Pilih berkas berita acara'"></span>
                                <span class="shrink-0 text-xs font-semibold text-red-700 dark:text-red-300">Pilih</span>
                            </span>
                        </label>
                        <x-input-error class="mt-1" :messages="$errors->get('berita_acara')" />
                    </div>

                    <button type="button" @click="openConfirm()"
                            class="inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                        <span class="material-icons-round text-lg">delete_forever</span> Tinjau &amp; Ajukan
                    </button>
                </div>
            </div>

            {{-- Modal ringkasan + konfirmasi eksplisit --}}
            <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/60 p-4" role="dialog" aria-modal="true">
                <div class="w-full max-w-lg rounded-2xl border border-sky-100 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">Konfirmasi penghapusan</h3>

                    <div x-show="mode === 'UNIT'" class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                        <strong x-text="selectAll ? {{ $units->total() }} : selected.length"></strong> unit akan diajukan untuk dihapus.
                        <p class="mt-2 text-xs">Unit yang sedang dipinjam, sudah dihapus, atau sudah menunggu persetujuan lain akan ditolak backend.</p>
                    </div>

                    <div x-show="mode === 'CATALOG'" class="mt-3 space-y-2 text-sm text-slate-700 dark:text-slate-200">
                        <p class="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-800 dark:bg-red-950/40 dark:text-red-200">
                            Seluruh unit dalam katalog ini akan masuk proses penghapusan. Katalog, spesifikasi, dan histori transaksi tetap tersimpan.
                        </p>
                        <div>Katalog: <strong x-text="catalogSummary?.name"></strong></div>
                        <div>Jumlah spesifikasi: <strong x-text="catalogSummary?.spec_count"></strong></div>
                        <div>Total unit: <strong x-text="catalogSummary?.unit_count"></strong> (dapat dihapus: <strong x-text="catalogSummary?.eligible_count"></strong>)</div>
                        <div class="text-xs">
                            <div class="font-semibold">Rincian merek / tipe:</div>
                            <ul class="ms-4 list-disc">
                                <template x-for="spec in (catalogSummary?.specs || [])" :key="spec.label">
                                    <li><span x-text="spec.label"></span> — <span x-text="spec.units"></span> unit</li>
                                </template>
                            </ul>
                        </div>
                        <div class="text-xs">
                            <div class="font-semibold">Lokasi terdampak:</div>
                            <div x-text="(catalogSummary?.locations || []).join(', ') || '—'"></div>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button type="button" @click="modalOpen = false"
                                class="min-h-10 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">
                            Batal
                        </button>
                        <button type="button" @click="submitConfirmed()"
                                class="min-h-10 rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700">
                            Ya, ajukan penghapusan
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <div class="mt-5 overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                <p class="text-base font-bold text-slate-900 dark:text-slate-100">Riwayat Inventaris Keluar ({{ $history->total() }})</p>
                <p class="mt-1 text-[11px] font-normal text-slate-400 md:hidden dark:text-slate-500">Geser tabel ke samping untuk melihat semua kolom.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <tr>
                            <th class="px-3 py-2.5 text-left">Barang</th>
                            <th class="px-3 py-2.5 text-center">Mode</th>
                            <th class="px-3 py-2.5 text-center">Unit</th>
                            <th class="px-3 py-2.5 text-left">Alasan</th>
                            <th class="px-3 py-2.5 text-center">Status</th>
                            <th class="px-3 py-2.5 text-left">Pengajuan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($history as $wo)
                            <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                <td class="px-3 py-2.5">
                                    @php($catalogNames = $wo->units->pluck('catalog.name')->filter()->unique())
                                    @if ($catalogNames->isNotEmpty())
                                        <span class="font-medium text-slate-800 dark:text-slate-100">{{ $catalogNames->take(2)->implode(', ') }}</span>
                                        @if ($catalogNames->count() > 2)
                                            <span class="text-[11px] text-slate-400">+{{ $catalogNames->count() - 2 }} katalog lain</span>
                                        @endif
                                    @else
                                        <a href="{{ route('items.show', $wo->item) }}" class="font-medium text-slate-800 hover:text-brand-700 dark:text-slate-100 dark:hover:text-brand-300">{{ $wo->item?->name }}</a>
                                        <div class="font-mono text-[11px] text-slate-400">{{ $wo->item?->internal_id }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400">{{ $wo->mode ?: 'CATALOG' }}</td>
                                <td class="px-3 py-2.5 text-center text-xs text-slate-600 dark:text-slate-300">{{ $wo->units_count ?: $wo->quantity }}</td>
                                <td class="max-w-[220px] px-3 py-2.5 text-xs text-slate-600 dark:text-slate-300">
                                    {{ \Illuminate\Support\Str::limit($wo->reason, 80) }}
                                    @if ($wo->decision_note)
                                        <div class="mt-0.5 text-red-600 dark:text-red-300">Penolakan: {{ $wo->decision_note }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    @if ($wo->status === 'PENDING')
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">MENUNGGU</span>
                                    @elseif ($wo->status === 'APPROVED')
                                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-800 dark:bg-red-950/50 dark:text-red-300">DIHAPUS</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">DITOLAK</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs text-slate-600 dark:text-slate-300">
                                    {{ $wo->requester?->name }}
                                    <div class="text-slate-400">{{ $wo->created_at->format('d M Y') }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-3 py-10 text-center text-sm text-slate-400">Belum ada penghapusan tercatat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $history->links() }}</div>
        </div>
    </div>

    @push('scripts')
        <script>
            function writeOffForm(config) {
                return {
                    mode: config.mode || 'UNIT',
                    catalogId: Number(config.catalogId) || '',
                    summaries: config.summaries || {},
                    selected: [],
                    selectAll: false,
                    confirmDelete: false,
                    confirmCatalog: false,
                    modalOpen: false,

                    get catalogSummary() {
                        return this.summaries[this.catalogId] || null;
                    },

                    openConfirm() {
                        if (this.mode === 'CATALOG' && !this.catalogId) {
                            alert('Pilih katalog yang akan dihapus seluruh unitnya.');

                            return;
                        }

                        if (this.mode === 'UNIT' && !this.selectAll && this.selected.length === 0) {
                            alert('Pilih minimal satu unit atau centang "Pilih semua sesuai filter".');

                            return;
                        }

                        this.modalOpen = true;
                    },

                    submitConfirmed() {
                        this.confirmDelete = true;
                        this.confirmCatalog = this.mode === 'CATALOG';
                        this.modalOpen = false;
                        this.$nextTick(() => this.$refs.deleteForm.requestSubmit());
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
