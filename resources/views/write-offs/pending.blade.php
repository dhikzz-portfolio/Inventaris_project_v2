<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Approval Penghapusan Aset</h2>
                <p class="text-sm text-gray-500">Wakasek Sarpras — persetujuan akhir pemusnahan aset</p>
            </div>
            <a href="{{ route('write-offs.index') }}" class="text-sm text-gray-500 hover:text-gray-800">← Inventaris Keluar</a>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-6">
        <div class="px-5 py-3 border-b border-gray-100 font-semibold text-gray-700 text-sm">Menunggu Persetujuan ({{ $pending->count() }})</div>
        @if ($pending->isEmpty())
            <div class="p-8 text-center text-sm text-gray-400">Tidak ada permohonan penghapusan.</div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach ($pending as $wo)
                    <div class="p-5 grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <div class="lg:col-span-2">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-gray-800">{{ $wo->item?->name }}</span>
                                <span class="text-xs bg-red-50 text-red-700 rounded px-2 py-0.5 font-bold">{{ $wo->quantity }} {{ $wo->item?->unit }}</span>
                            </div>
                            <p class="text-sm text-gray-600 mt-1">{{ $wo->reason }}</p>
                            <div class="text-xs text-gray-400 mt-1">
                                {{ $wo->item?->internal_id }} • Ruang {{ $wo->item?->room?->code }} •
                                Diajukan {{ $wo->created_at->diffForHumans() }} oleh {{ $wo->requester?->name }}
                            </div>
                            <a href="{{ route('files.show', ['path' => $wo->berita_acara_path]) }}" target="_blank"
                               class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700 hover:underline">
                                <span class="material-icons-round text-sm">description</span> Lihat Berita Acara
                            </a>
                        </div>
                        <div class="flex flex-col gap-2">
                            <form method="POST" action="{{ route('write-offs.approve', $wo) }}">
                                @csrf
                                <button class="w-full inline-flex items-center justify-center gap-1 px-3 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-semibold">
                                    <span class="material-icons-round text-lg">check_circle</span> Setujui & Hapus Barang
                                </button>
                            </form>
                            <form method="POST" action="{{ route('write-offs.reject', $wo) }}" class="space-y-2">
                                @csrf
                                <input type="text" name="decision_note" placeholder="Alasan penolakan (opsional)"
                                       class="w-full rounded-lg border-gray-300 text-xs focus:ring-red-500 focus:border-red-500">
                                <button class="w-full inline-flex items-center justify-center gap-1 px-3 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold">
                                    <span class="material-icons-round text-lg">cancel</span> Tolak
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 font-semibold text-gray-700 text-sm">Riwayat Keputusan</div>
        @if ($decided->isEmpty())
            <div class="p-8 text-center text-sm text-gray-400">Belum ada riwayat.</div>
        @else
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="text-left px-5 py-2.5">Barang</th>
                        <th class="text-center px-5 py-2.5">Qty</th>
                        <th class="text-center px-5 py-2.5">Status</th>
                        <th class="text-left px-5 py-2.5">Keputusan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($decided as $wo)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-2.5 font-medium text-gray-800">{{ $wo->item?->name }}</td>
                            <td class="px-5 py-2.5 text-center text-xs">{{ $wo->quantity }}</td>
                            <td class="px-5 py-2.5 text-center">
                                @if ($wo->status === 'APPROVED')
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700">DIHAPUS</span>
                                @else
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">DITOLAK</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-xs text-gray-500">{{ $wo->approver?->name }} • {{ $wo->updated_at->format('d M Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </div>
</x-app-layout>
