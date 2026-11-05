<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Catat Penerimaan Barang</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Isi jumlah aktual, kondisi fisik, dan keterangan untuk setiap alokasi ruangan. Barang belum menjadi inventaris sampai dicatat.</p>
            </div>
            <a href="{{ route('items.acquisitions') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">← Kembali</a>
        </div>
    </x-slot>

    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <ul class="list-disc ms-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
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

        <h3 class="mb-1 text-sm font-bold text-slate-800 dark:text-slate-100">Jumlah, kondisi, dan keterangan per alokasi</h3>
        <p class="mb-3 text-[11px] text-slate-500 dark:text-slate-400">Kondisi fisik dapat berbeda antar ruangan. Barang rusak tetap diterima penuh dan diterbitkan sebagai inventaris resmi.</p>

        @php
            $allocations = $item->allocations->isNotEmpty()
                ? $item->allocations
                : collect([new \App\Models\AcquisitionAllocation([
                    'room_id' => $item->room_id,
                    'quantity' => (int) $item->quantity,
                    'condition' => \App\Enums\Condition::BAIK->value,
                ])]);
        @endphp

        <div class="space-y-3">
            @foreach ($allocations as $allocation)
                @php
                    $key = $allocation->exists ? $allocation->id : 0;
                    $conditionValue = old('received.'.$key.'.condition', $allocation->conditionEnum()->value);
                    $noteValue = old('received.'.$key.'.note', $allocation->note);
                @endphp
                <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                    <div class="flex flex-col gap-1 text-sm text-slate-700 dark:text-slate-200">
                        <div class="font-semibold">{{ $allocation->room?->code ?? 'Ruangan' }} — {{ $allocation->room?->name }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            Dialokasikan {{ $allocation->quantity }} {{ $item->unit }}
                            @if ($allocation->specification)
                                • {{ $allocation->specification->displayName() }}
                            @endif
                            @if ($allocation->acquisition_year)
                                • Tahun {{ $allocation->acquisition_year }}
                            @endif
                            @if ($allocation->funding_source)
                                • Dana {{ $allocation->funding_source }}
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-12">
                        <div class="sm:col-span-3">
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300" for="received-{{ $key }}">Diterima aktual</label>
                            <input id="received-{{ $key }}" type="number" min="0" max="{{ $allocation->quantity }}"
                                   name="received[{{ $key }}][quantity]"
                                   value="{{ old('received.'.$key.'.quantity', $allocation->received_quantity ?? $allocation->quantity) }}"
                                   class="block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        </div>

                        <div class="sm:col-span-4">
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300" for="condition-{{ $key }}">Kondisi fisik</label>
                            <select id="condition-{{ $key }}" name="received[{{ $key }}][condition]"
                                    class="block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                @foreach ($conditions as $condition)
                                    <option value="{{ $condition->value }}" @selected($conditionValue === $condition->value)>{{ $condition->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="sm:col-span-5">
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300" for="note-{{ $key }}">Keterangan</label>
                            <input id="note-{{ $key }}" type="text" maxlength="500" name="received[{{ $key }}][note]"
                                   value="{{ $noteValue }}"
                                   placeholder="mis. kemasan penyok / 1 unit belum dikirim"
                                   class="block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
            Jumlah aktual boleh berbeda dari jumlah pengajuan. Sistem menyimpan jumlah aktual dan riwayat selisihnya.
            Unit berkondisi rusak otomatis dibuatkan laporan kerusakan saat inventaris dicatat.
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
