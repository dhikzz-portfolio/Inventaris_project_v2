<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">{{ $event->name }}</h2>
                    <span class="text-[11px] font-bold px-2 py-1 rounded-full {{ $event->status->badgeClass() }}">{{ $event->status->label() }}</span>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    PJ: {{ $event->pic_name }} • {{ $event->start_date->format('d M Y') }} — {{ $event->end_date->format('d M Y') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if (auth()->user()->managesInventory())
                    <a href="{{ route('events.bast', $event) }}" target="_blank"
                       class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <span class="material-icons-round text-lg">description</span> BAST Gabungan (PDF)
                    </a>
                @endif
                <a href="{{ route('events.index') }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-800">← Semua kegiatan</a>
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ session('error') }}</div>
    @endif

    @if ($event->description)
        <div class="mb-5 rounded-2xl border border-sky-100 bg-white p-4 text-sm leading-relaxed text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">{{ $event->description }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 class="mb-4 flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                <span class="material-icons-round text-blue-600">receipt_long</span> Peminjaman Terikat Kegiatan ({{ $event->loans->count() }})
            </h3>

            @if ($event->loans->isEmpty())
                <p class="text-sm text-gray-400">Belum ada peminjaman terikat. Pilih kegiatan ini saat mengajukan peminjaman.</p>
            @else
                <div class="space-y-4">
                    @foreach ($event->loans as $loan)
                        <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <div>
                                    <a href="{{ route('loans.show', $loan) }}" class="font-semibold text-slate-800 hover:text-blue-700 dark:text-slate-100 dark:hover:text-blue-300">#{{ $loan->id }} — {{ $loan->borrower?->name }}</a>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $loan->start_date->format('d/m/Y H:i') }} → {{ $loan->due_date->format('d/m/Y H:i') }}</div>
                                </div>
                                <span class="text-[10px] font-bold px-2 py-1 rounded-full {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                            </div>
                            <ul class="mt-2 space-y-1">
                                @foreach ($loan->loanItems as $li)
                                    <li class="flex items-center justify-between border-b border-dashed border-slate-100 pb-2 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300">
                                        <span>{{ $li->item?->name }} <span class="font-mono text-gray-400">({{ $li->item?->internal_id }})</span></span>
                                        <span>×{{ $li->qty_borrowed }}
                                            @if ($li->qty_returned !== null)
                                                • kembali {{ $li->qty_returned }}
                                                @if ($li->hasShortage())<span class="text-red-600 font-bold"> (HILANG {{ $li->missingQuantity() }})</span>@endif
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                            @if ($loan->bast_path)
                                <a href="{{ route('loans.bast', $loan) }}" target="_blank" class="mt-2 inline-flex items-center gap-1 text-xs text-blue-600 font-semibold hover:underline">
                                    <span class="material-icons-round text-sm">description</span> BAST/Surat pinjaman ini
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="space-y-5">
            <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="mb-3 flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="material-icons-round text-blue-600">inventory_2</span> ID Barang Dibooking ({{ $bookedItems->count() }})
                </h3>
                @if ($bookedItems->isEmpty())
                    <p class="text-sm text-gray-400">Belum ada.</p>
                @else
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($bookedItems as $id)
                            @php($linked = \App\Models\Item::where('internal_id', $id)->first())
                            @if ($linked)
                                <a href="{{ route('items.show', $linked) }}"
                                   class="text-[11px] font-mono bg-slate-100 hover:bg-slate-200 text-slate-700 rounded px-2 py-1">{{ $id }}</a>
                            @else
                                <span class="text-[11px] font-mono bg-slate-100 text-slate-500 rounded px-2 py-1">{{ $id }}</span>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($event->isManageableBy(auth()->user()))
                <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="mb-3 flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                        <span class="material-icons-round text-blue-600">edit_calendar</span> Kelola Kegiatan
                    </h3>
                    <form method="POST" action="{{ route('events.update', $event) }}" class="space-y-2.5">
                        @csrf
                        @method('PATCH')
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-500 mb-1">Status *</label>
                            <select name="status" class="w-full rounded-lg border-gray-300 text-sm" required>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}" @selected($event->status === $status)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-500 mb-1">Nama kegiatan *</label>
                            <input type="text" name="name" value="{{ $event->name }}" required class="w-full rounded-lg border-gray-300 text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-500 mb-1">Penanggung jawab *</label>
                            <input type="text" name="pic_name" value="{{ $event->pic_name }}" required class="w-full rounded-lg border-gray-300 text-xs">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-500 mb-1">Mulai *</label>
                                <input type="date" name="start_date" value="{{ $event->start_date->toDateString() }}" required class="w-full rounded-lg border-gray-300 text-xs">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-500 mb-1">Selesai *</label>
                                <input type="date" name="end_date" value="{{ $event->end_date->toDateString() }}" required class="w-full rounded-lg border-gray-300 text-xs">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-500 mb-1">Deskripsi</label>
                            <textarea name="description" rows="2" class="w-full rounded-lg border-gray-300 text-xs">{{ $event->description }}</textarea>
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold py-2">Simpan Perubahan</button>
                        <p class="text-[10px] text-gray-400">Status kegiatan tidak mengubah status persetujuan peminjaman.</p>
                    </form>
                </div>
            @endif

            <div class="rounded-2xl border border-sky-100 bg-white p-5 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
                    <span class="material-icons-round text-blue-600">how_to_reg</span> Alur Kegiatan
                </h3>
                <ol class="list-decimal ms-4 space-y-1.5 text-gray-600 text-xs">
                    <li>Peminjam mengajukan peminjaman <strong>dengan memilih kegiatan ini</strong>.</li>
                    <li>Laboran menyetujui (Wakasek untuk barang bernilai tinggi).</li>
                    <li>BAST/Surat Peminjaman Resmi digenerate otomatis (PDF siap tanda tangan).</li>
                    <li>Seluruh barang dikembalikan & dilaporkan sebagai <strong>satu paket</strong>.</li>
                </ol>
            </div>
        </div>
    </div>
</x-app-layout>
