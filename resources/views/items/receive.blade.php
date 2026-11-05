<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Catat Penerimaan Barang</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Isi jumlah aktual, kondisi, dan catatan penerimaan. Barang belum menjadi inventaris sampai dicatat.</p>
            </div>
            <a href="{{ route('items.acquisitions') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">← Kembali</a>
        </div>
    </x-slot>

    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->first() }}</div>
    @endif

    <div class="mb-4 rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="text-base font-bold text-slate-900 dark:text-slate-100">{{ $item->name }}</div>
        <div class="mt-1 text-xs text-slate-600 dark:text-slate-300">
            {{ $item->quantity }} {{ $item->unit }} • {{ $item->category?->name }} • {{ $item->acquisitionStatusEnum()?->label() }}
        </div>
        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Spesifikasi: {{ $item->brand ?: 'Tanpa Merek' }} / {{ $item->model_type ?: 'Tidak Ditentukan' }}
            @if ($item->specification) — {{ $item->specification }} @endif
        </div>
    </div>

    <form method="POST" action="{{ route('items.acquisitions.receive', $item) }}" enctype="multipart/form-data"
          x-data="{ submitting: false }" @submit="submitting = true"
          class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        @csrf

        <h3 class="mb-3 text-sm font-bold text-slate-800 dark:text-slate-100">Jumlah aktual per lokasi</h3>

        @php
            $allocations = $item->allocations->isNotEmpty()
                ? $item->allocations
                : collect([new \App\Models\AcquisitionAllocation(['room_id' => $item->room_id, 'quantity' => (int) $item->quantity])]);
        @endphp

        <div class="space-y-3">
            @foreach ($allocations as $allocation)
                <div class="flex flex-col gap-2 rounded-xl border border-slate-200 p-3 dark:border-slate-700 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm text-slate-700 dark:text-slate-200">
                        <div class="font-semibold">{{ $allocation->room?->code ?? 'Ruangan' }} — {{ $allocation->room?->name }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            Dialokasikan {{ $allocation->quantity }} {{ $item->unit }}
                            @if ($allocation->specification)
                                • {{ $allocation->specification->displayName() }}
                            @endif
                        </div>
                        <input type="hidden" name="room_ids[]" value="{{ $allocation->room_id }}">
                    </div>
                    <div class="w-full sm:w-40">
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300" for="received-{{ $allocation->room_id }}">Diterima aktual</label>
                        <input id="received-{{ $allocation->room_id }}" type="number" min="0" max="{{ $allocation->quantity }}"
                               name="received[{{ $allocation->room_id }}]"
                               value="{{ old('received.'.$allocation->room_id, $allocation->received_quantity ?? $allocation->quantity) }}"
                               class="block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <x-input-label for="condition" value="Kondisi barang diterima *" />
                <select id="condition" name="condition" required
                        class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    @foreach ($conditions as $condition)
                        <option value="{{ $condition->value }}" @selected(old('condition') === $condition->value)>{{ $condition->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="note" value="Keterangan penerimaan" />
                <input id="note" name="note" type="text" maxlength="500" value="{{ old('note') }}"
                       placeholder="mis. 1 unit belum dikirim"
                       class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
            </div>
        </div>

        <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
            Jumlah aktual boleh berbeda dari jumlah pengajuan. Sistem menyimpan jumlah aktual dan riwayat selisihnya.
            Penerimaan hanya dapat dicatat satu kali untuk setiap pengajuan.
        </p>

        <div class="mt-5 flex justify-end">
            <button :disabled="submitting" class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-wait disabled:opacity-60">
                <span class="material-icons-round text-lg" aria-hidden="true">save</span>
                <span x-text="submitting ? 'Menyimpan…' : 'Simpan penerimaan'"></span>
            </button>
        </div>
    </form>
</x-app-layout>
