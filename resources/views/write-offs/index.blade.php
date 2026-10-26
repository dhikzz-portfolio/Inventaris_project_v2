<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Inventaris Keluar (Penghapusan)</h2>
                <p class="text-sm text-gray-500">Pemusnahan/write-off wajib mengunggah Berita Acara & disetujui Wakasek Sarpras</p>
            </div>
            @if (auth()->user()->isApproverLevel2())
                <a href="{{ route('write-offs.pending') }}" class="text-sm text-blue-700 font-semibold hover:underline">
                    Approval Penghapusan →
                </a>
            @endif
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 text-sm mb-4 flex items-center gap-1.5">
                <span class="material-icons-round text-red-500">delete_forever</span> Ajukan Penghapusan
            </h3>
            <form method="POST" action="{{ route('write-offs.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <x-input-label value="Barang *" />
                    <select name="item_id" class="mt-1 block w-full rounded-lg border-gray-300 focus:ring-red-500 focus:border-red-500 text-sm" required>
                        <option value="">— pilih barang —</option>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}" @selected(old('item_id') == $item->id)>
                                {{ $item->name }} — {{ $item->internal_id }} (stok {{ $item->quantity }} {{ $item->unit }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label value="Jumlah yang Dihapus *" />
                    <input type="number" name="quantity" min="1" value="{{ old('quantity', 1) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300 focus:ring-red-500 focus:border-red-500 text-sm" required>
                </div>
                <div>
                    <x-input-label value="Alasan Pemusnahan *" />
                    <textarea name="reason" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 focus:ring-red-500 focus:border-red-500 text-sm"
                              placeholder="Rusak berat, tidak layak pakai, hilang, habis masa guna..." required>{{ old('reason') }}</textarea>
                </div>
                <div>
                    <x-input-label value="Berita Acara (PDF/Gambar) *" />
                    <input type="file" name="berita_acara" accept=".pdf,.jpg,.jpeg,.png"
                           class="mt-1 block w-full text-sm text-gray-600 file:me-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-red-50 file:text-red-700 hover:file:bg-red-100" required>
                </div>
                <x-danger-button class="w-full justify-center">Ajukan Inventaris Keluar</x-danger-button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
                <span class="material-icons-round text-blue-600">inventory</span> Riwayat Inventaris Keluar
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-gray-400 border-b border-gray-100">
                        <tr>
                            <th class="text-left py-2">Barang</th>
                            <th class="text-center py-2">Qty</th>
                            <th class="text-left py-2">Alasan</th>
                            <th class="text-center py-2">Status</th>
                            <th class="text-left py-2">Pengajuan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($history as $wo)
                            <tr class="hover:bg-gray-50">
                                <td class="py-2.5">
                                    <a href="{{ route('items.show', $wo->item) }}" class="font-medium text-gray-800 hover:text-blue-700">{{ $wo->item?->name }}</a>
                                    <div class="text-[11px] font-mono text-gray-400">{{ $wo->item?->internal_id }}</div>
                                </td>
                                <td class="py-2.5 text-center text-xs">{{ $wo->quantity }}</td>
                                <td class="py-2.5 text-xs text-gray-500 max-w-[220px]">
                                    {{ \Illuminate\Support\Str::limit($wo->reason, 80) }}
                                    @if ($wo->decision_note)
                                        <div class="text-red-500 mt-0.5">Penolakan: {{ $wo->decision_note }}</div>
                                    @endif
                                </td>
                                <td class="py-2.5 text-center">
                                    @if ($wo->status === 'PENDING')
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-800">MENUNGGU</span>
                                    @elseif ($wo->status === 'APPROVED')
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-800">DIHAPUS</span>
                                    @else
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">DITOLAK</span>
                                    @endif
                                </td>
                                <td class="py-2.5 text-xs text-gray-500">
                                    {{ $wo->requester?->name }}<br>
                                    <span class="text-gray-400">{{ $wo->created_at->format('d M Y') }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-gray-400 text-sm">Belum ada penghapusan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $history->links() }}</div>
        </div>
    </div>
</x-app-layout>
