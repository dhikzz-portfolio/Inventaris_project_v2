<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Laporkan Kerusakan Barang</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pilih unit inventaris pada ruang lingkup Anda dan jelaskan kerusakannya.</p>
            </div>
            <a href="{{ route('damage-reports.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">← Kembali</a>
        </div>
    </x-slot>

    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('damage-reports.store') }}" enctype="multipart/form-data"
          x-data="{ submitting: false, classification: '{{ old('classification', 'RUSAK_RINGAN') }}' }" @submit="submitting = true"
          class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        @csrf

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <x-input-label for="asset_unit_id" value="Unit inventaris *" />
                <select id="asset_unit_id" name="asset_unit_id" required
                        class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">— pilih unit —</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}" @selected((string) old('asset_unit_id', $selectedUnitId) === (string) $unit->id)>
                            {{ $unit->inventory_code }} — {{ $unit->catalog?->name }} ({{ $unit->room?->code }}, kondisi: {{ $unit->condition->label() }})
                        </option>
                    @endforeach
                </select>
                @if ($units->isEmpty())
                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">Tidak ada unit inventaris pada ruang lingkup Anda.</p>
                @endif
            </div>

            <div class="md:col-span-2">
                <x-input-label value="Klasifikasi kerusakan *" />
                <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach ($classifications as $classification)
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-blue-400 dark:border-slate-700"
                               :class="classification === '{{ $classification->value }}' ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/30' : ''">
                            <input type="radio" name="classification" value="{{ $classification->value }}" class="mt-1"
                                   x-model="classification" @checked(old('classification', 'RUSAK_RINGAN') === $classification->value)>
                            <span>
                                <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $classification->label() }}</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $classification->description() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="md:col-span-2">
                <x-input-label for="description" value="Deskripsi kerusakan *" />
                <textarea id="description" name="description" rows="4" required maxlength="2000"
                          placeholder="Jelaskan bagian yang rusak, penyebab, dan dampaknya terhadap penggunaan."
                          class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">{{ old('description') }}</textarea>
            </div>

            <div>
                <x-input-label for="photos" value="Dokumentasi foto (opsional, maks. 5)" />
                <input id="photos" name="photos[]" type="file" accept="image/*" multiple
                       class="mt-1 block w-full rounded-xl border border-slate-300 bg-white p-2 text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-blue-700 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Pilih beberapa foto sekaligus. Maksimal 2 MB per foto; foto disimpan privat dan diakses sesuai peran.</p>
            </div>
        </div>

        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
            Laporan ini hanya mencatat dan memantau kondisi. Penghapusan barang tetap mengikuti alur Inventaris Keluar
            yang memerlukan persetujuan Wakasek Sarpras.
        </div>

        <div class="mt-5 flex justify-end">
            <button :disabled="submitting" class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-wait disabled:opacity-60">
                <span class="material-icons-round text-lg" aria-hidden="true">report</span>
                <span x-text="submitting ? 'Menyimpan…' : 'Simpan laporan'"></span>
            </button>
        </div>
    </form>
</x-app-layout>
