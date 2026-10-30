<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Barang Dihapus</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Ajukan barang yang tidak lagi dapat digunakan untuk dihapus dari inventaris.</p>
            </div>
            @if (auth()->user()->isApproverLevel2())
                <a href="{{ route('write-offs.pending') }}" class="inline-flex min-h-10 items-center gap-1 rounded-lg px-3 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-slate-800">
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

    <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-3">
        <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm lg:sticky lg:top-20 dark:border-slate-700 dark:bg-slate-900">
            <h3 class="mb-4 flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                <span class="material-icons-round text-red-500">delete_forever</span> Ajukan Penghapusan
            </h3>
            <form method="POST" action="{{ route('write-offs.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <x-input-label for="item_id" value="Barang *" />
                    <select id="item_id" name="item_id" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100" required>
                        <option value="">— pilih barang —</option>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}" @selected(old('item_id') == $item->id)>
                                {{ $item->name }} — {{ $item->internal_id }} (stok {{ $item->quantity }} {{ $item->unit }})
                            </option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('item_id')" />
                </div>
                <div>
                    <x-input-label for="quantity" value="Jumlah yang Dihapus *" />
                    <input id="quantity" type="number" name="quantity" min="1" value="{{ old('quantity', 1) }}"
                           class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100" required>
                    <x-input-error class="mt-1" :messages="$errors->get('quantity')" />
                </div>
                <div>
                    <x-input-label for="reason" value="Alasan Pemusnahan *" />
                    <textarea id="reason" name="reason" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                              placeholder="Rusak berat, tidak layak pakai, hilang, habis masa guna..." required>{{ old('reason') }}</textarea>
                    <x-input-error class="mt-1" :messages="$errors->get('reason')" />
                </div>
                <div x-data="{ fileName: '' }">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Berita Acara (PDF/Gambar) *</span>
                        <span class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 transition hover:border-red-300 hover:bg-red-50 peer-focus-visible:ring-2 peer-focus-visible:ring-red-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-red-800 dark:hover:bg-red-950/30">
                            <input id="berita-acara" type="file" name="berita_acara" accept=".pdf,.jpg,.jpeg,.png"
                                   aria-describedby="berita-acara-help"
                                   x-on:change="fileName = $event.target.files.length ? $event.target.files[0].name : ''"
                                   class="peer sr-only" required>
                            <span class="material-icons-round text-lg text-red-600 dark:text-red-400" aria-hidden="true">upload_file</span>
                            <span class="min-w-0 flex-1 truncate" x-text="fileName || 'Pilih berkas berita acara'"></span>
                            <span class="shrink-0 text-xs font-semibold text-red-700 dark:text-red-300">Pilih</span>
                        </span>
                    </label>
                    <p id="berita-acara-help" class="mt-1 text-xs text-slate-500 dark:text-slate-400">Format yang diterima: PDF, JPG, JPEG, atau PNG.</p>
                    <x-input-error class="mt-1" :messages="$errors->get('berita_acara')" />
                </div>
                <x-danger-button class="w-full justify-center">Ajukan Inventaris Keluar</x-danger-button>
            </form>
        </div>

        <div class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm lg:col-span-2 dark:border-slate-700 dark:bg-slate-900">
            <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                <h3 class="flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="material-icons-round text-brand-600">inventory</span> Riwayat Inventaris Keluar ({{ $history->total() }})
                </h3>
            </div>
            <div class="max-h-[60vh] overflow-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <tr>
                            <th class="px-3 py-2.5 text-left">Barang</th>
                            <th class="px-3 py-2.5 text-center">Qty</th>
                            <th class="px-3 py-2.5 text-left">Alasan</th>
                            <th class="px-3 py-2.5 text-center">Status</th>
                            <th class="px-3 py-2.5 text-left">Pengajuan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($history as $wo)
                            <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                <td class="px-3 py-2.5">
                                    <a href="{{ route('items.show', $wo->item) }}" class="font-medium text-slate-800 hover:text-brand-700 dark:text-slate-100 dark:hover:text-brand-300">{{ $wo->item?->name }}</a>
                                    <div class="font-mono text-[11px] text-slate-400 dark:text-slate-500">{{ $wo->item?->internal_id }}</div>
                                </td>
                                <td class="px-3 py-2.5 text-center text-xs text-slate-600 dark:text-slate-300">{{ $wo->quantity }}</td>
                                <td class="max-w-[220px] px-3 py-2.5 text-xs text-slate-600 dark:text-slate-300">
                                    {{ \Illuminate\Support\Str::limit($wo->reason, 80) }}
                                    @if ($wo->decision_note)
                                        <div class="mt-0.5 text-red-600 dark:text-red-300">Penolakan: {{ $wo->decision_note }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    @if ($wo->status === 'PENDING')
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">MENUNGGU</span>
                                    @elseif ($wo->status === 'APPROVED')
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-300">DIHAPUS</span>
                                    @else
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">DITOLAK</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs text-slate-600 dark:text-slate-300">
                                    {{ $wo->requester?->name }}
                                    <div class="text-slate-400 dark:text-slate-500">{{ $wo->created_at->format('d M Y') }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-10 text-center text-sm text-slate-400">Belum ada penghapusan tercatat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $history->links() }}</div>
        </div>
    </div>
</x-app-layout>
