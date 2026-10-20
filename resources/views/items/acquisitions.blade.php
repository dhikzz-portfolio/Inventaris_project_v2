<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Approval Pengadaan Barang</h2>
                <p class="text-sm text-gray-500">Wakasek Sarpras menyetujui/menolak pengadaan dari Admin Sarpras</p>
            </div>
            <a href="{{ route('items.create') }}" class="text-sm text-amber-700 font-semibold hover:underline">+ Pengadaan baru</a>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-6">
        <div class="px-5 py-3 border-b border-gray-100 font-semibold text-gray-700 text-sm">
            Menunggu Persetujuan ({{ $pending->count() }})
        </div>
        @if ($pending->isEmpty())
            <div class="p-8 text-center text-sm text-gray-400">Tidak ada pengadaan yang menunggu persetujuan.</div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach ($pending as $item)
                    <div class="p-5 flex flex-col md:flex-row md:items-center gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-gray-800">{{ $item->name }}</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-800">MENUNGGU</span>
                                @if ($item->is_high_value)
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-purple-100 text-purple-800">NILAI TINGGI</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ $item->quantity }} {{ $item->unit }} • {{ $item->category?->name }} • Ruang {{ $item->room?->code }} ({{ $item->room?->name }})
                                • Tahun {{ $item->acquisition_year }} • Dana {{ $item->funding_source }}
                            </div>
                            <div class="text-xs text-gray-400 mt-0.5 font-mono">
                                {{ $item->internal_id }} • Reg. {{ $item->register_no }} • Diajukan {{ $item->created_at->diffForHumans() }} oleh {{ $item->requester?->name }}
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <form method="POST" action="{{ route('items.approve', $item) }}">
                                @csrf
                                <button class="inline-flex items-center gap-1 px-3 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-xs font-semibold">
                                    <span class="material-icons-round text-sm">check_circle</span> Setujui
                                </button>
                            </form>
                            <form method="POST" action="{{ route('items.reject', $item) }}" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <input type="text" name="note" placeholder="Alasan penolakan"
                                       class="grow sm:grow-0 rounded-lg border-gray-300 text-xs focus:ring-red-500 focus:border-red-500 w-full sm:w-40">
                                <button class="inline-flex items-center gap-1 px-3 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-semibold">
                                    <span class="material-icons-round text-sm">cancel</span> Tolak
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 font-semibold text-gray-700 text-sm">Riwayat Keputusan Terakhir</div>
        @if ($decided->isEmpty())
            <div class="p-8 text-center text-sm text-gray-400">Belum ada riwayat.</div>
        @else
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="text-left px-5 py-2.5">Barang</th>
                        <th class="text-left px-5 py-2.5">Status</th>
                        <th class="text-left px-5 py-2.5">Catatan</th>
                        <th class="text-left px-5 py-2.5">Keputusan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($decided as $item)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-2.5">
                                <a href="{{ route('items.show', $item) }}" class="font-medium text-gray-800 hover:text-amber-700">{{ $item->name }}</a>
                                <div class="text-xs text-gray-400 font-mono">{{ $item->internal_id }}</div>
                            </td>
                            <td class="px-5 py-2.5">
                                @if ($item->acquisition_status === 'APPROVED')
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-800">DISETUJUI</span>
                                @else
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-800">DITOLAK</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-xs text-gray-500">{{ $item->acquisition_note ?: '—' }}</td>
                            <td class="px-5 py-2.5 text-xs text-gray-500">
                                {{ $item->approver?->name }} • {{ $item->acquisition_decided_at?->format('d M Y H:i') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </div>
</x-app-layout>
