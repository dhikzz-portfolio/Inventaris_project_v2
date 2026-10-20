<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $item->name }}</h2>
                    @php($effective = $item->effectiveStatus())
                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $effective->badgeClass() }}">{{ $effective->label() }}</span>
                    @if ($item->is_high_value)
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-purple-100 text-purple-800">NILAI TINGGI</span>
                    @endif
                    @if ($item->isAcquisitionPending())
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-800">PENGADAAN MENUNGGU APPROVAL</span>
                    @endif
                </div>
                <p class="text-sm text-gray-500 font-mono">{{ $item->internal_id }}</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('items.label', $item) }}" target="_blank"
                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium"
                   title="Re-print label stiker">
                    <span class="material-icons-round text-lg">print</span> Cetak Label
                </a>
                @if (auth()->user()->managesInventory())
                    <a href="{{ route('items.edit', $item) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium">
                        <span class="material-icons-round text-lg">edit</span> Ubah Data
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Kolom utama --}}
        <div class="lg:col-span-2 space-y-5">
            {{-- Foto & spesifikasi --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex flex-col sm:flex-row gap-5">
                    <div class="w-full sm:w-48 h-40 rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center overflow-hidden shrink-0">
                        @if ($item->photo_path)
                            <img src="{{ asset('storage/'.$item->photo_path) }}" alt="{{ $item->name }}" class="h-full w-full object-cover">
                        @else
                            <span class="material-icons-round text-6xl text-slate-400">inventory_2</span>
                        @endif
                    </div>
                    <div class="flex-1 text-sm space-y-1.5 text-gray-600">
                        <p class="text-gray-800 font-medium">{{ $item->specification ?: 'Tidak ada spesifikasi detail.' }}</p>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                            <div><span class="text-gray-400">Kategori:</span> <strong>{{ $item->category?->name }}</strong></div>
                            <div><span class="text-gray-400">Jumlah:</span> <strong>{{ $item->quantity }} {{ $item->unit }}</strong></div>
                            <div><span class="text-gray-400">Tersedia:</span> <strong class="{{ $item->availableQuantity() === 0 ? 'text-red-600' : 'text-green-700' }}">{{ $item->availableQuantity() }} {{ $item->unit }}</strong>
                                @if ($item->activeLoanRemainingQuantity() > 0)
                                    <span class="text-[11px] text-blue-600">({{ $item->activeLoanRemainingQuantity() }} sedang di luar/dipinjam)</span>
                                @endif
                            </div>
                            @if ($item->qty_damaged > 0 || $item->qty_lost > 0)
                                <div class="text-[11px] text-red-600">
                                    @if ($item->qty_damaged > 0) Rusak permanen: {{ $item->qty_damaged }} {{ $item->unit }}. @endif
                                    @if ($item->qty_lost > 0) Hilang permanen: {{ $item->qty_lost }} {{ $item->unit }}. @endif
                                </div>
                            @endif
                            @if ($item->pendingLostQuantity() > 0)
                                <div class="text-[11px] text-amber-700">Selisih belum ditutup: {{ $item->pendingLostQuantity() }} {{ $item->unit }} (menunggu tindak lanjut).</div>
                            @endif
                            <div><span class="text-gray-400">Tahun:</span> <strong>{{ $item->acquisition_year }}</strong></div>
                            <div><span class="text-gray-400">Sumber Dana:</span> <strong>{{ $item->funding_source }}</strong></div>
                            <div><span class="text-gray-400">Ruangan:</span> <strong>{{ $item->room?->name }} ({{ $item->room?->code }}, Lt.{{ $item->room?->floor }})</strong></div>
                            <div><span class="text-gray-400">Jurusan:</span> <strong>{{ $item->room?->major?->name ?: '-' }}</strong></div>
                            <div><span class="text-gray-400">Tersedia:</span> <strong>{{ $item->availableQuantity() }} {{ $item->unit }}</strong></div>
                            @if ($item->min_stock !== null)
                                <div><span class="text-gray-400">Min. Stok:</span> <strong class="{{ $item->isLowStock() ? 'text-red-600' : '' }}">{{ $item->min_stock }} {{ $item->unit }} {{ $item->isLowStock() ? '⚠' : '' }}</strong></div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Dua format ID --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
                    <span class="material-icons-round text-amber-600">tag</span> Sistem ID Inventaris (Dua Format)
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-gray-100">
                            <tr>
                                <td class="py-2.5 pe-4 text-xs text-gray-500 w-52">ID Internal / Spasial</td>
                                <td class="py-2.5 font-mono font-semibold text-gray-800">{{ $item->internal_id }}</td>
                                <td class="py-2.5 text-xs text-gray-400">Pencarian ruangan</td>
                            </tr>
                            <tr>
                                <td class="py-2.5 pe-4 text-xs text-gray-500">Standar Aset — Baris 1<br><span class="text-[10px]">Kode Lokasi/Instansi</span></td>
                                <td class="py-2.5 font-mono font-semibold text-gray-800">{{ $item->location_code }}</td>
                                <td class="py-2.5 text-xs text-gray-400">Pelaporan dinas/yayasan</td>
                            </tr>
                            <tr>
                                <td class="py-2.5 pe-4 text-xs text-gray-500">Standar Aset — Baris 2<br><span class="text-[10px]">Kode Barang + No. Register</span></td>
                                <td class="py-2.5 font-mono font-semibold text-gray-800">{{ $item->asset_code }}.{{ $item->register_no }}</td>
                                <td class="py-2.5 text-xs text-gray-400">Klasifikasi + registrasi</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 text-xs text-gray-400">
                    Label terakhir dicetak: {{ $item->last_label_printed_at?->format('d M Y H:i') ?: 'belum pernah' }}
                    — <a href="{{ route('items.label', $item) }}" target="_blank" class="text-amber-700 hover:underline">re-print stiker</a>
                </div>
            </div>

            {{-- Riwayat peminjaman --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
                    <span class="material-icons-round text-amber-600">history</span> Riwayat Peminjaman
                    @if ($lastBorrower)
                        <span class="ms-auto text-xs font-normal text-gray-500">
                            Peminjam terakhir: <strong>{{ $lastBorrower->name }}</strong>
                        </span>
                    @endif
                </h3>
                @if ($loanHistory->isEmpty())
                    <p class="text-sm text-gray-400">Belum ada riwayat peminjaman.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-xs uppercase text-gray-400">
                                <tr>
                                    <th class="text-left py-2">Peminjam</th>
                                    <th class="text-left py-2">Tujuan</th>
                                    <th class="text-left py-2">Periode</th>
                                    <th class="text-center py-2">Qty</th>
                                    <th class="text-center py-2">Kondisi Kembali</th>
                                    <th class="text-center py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($loanHistory as $li)
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2">
                                            <a href="{{ route('loans.show', $li->loan) }}" class="text-gray-700 hover:text-amber-700 font-medium">{{ $li->loan?->borrower?->name }}</a>
                                            <div class="text-[11px] text-gray-400">{{ $li->loan?->event?->name ?: '—' }}</div>
                                        </td>
                                        <td class="py-2 text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($li->loan?->purpose, 40) }}</td>
                                        <td class="py-2 text-xs text-gray-500">{{ $li->loan?->start_date?->format('d/m/Y') }} → {{ $li->loan?->due_date?->format('d/m/Y') }}</td>
                                        <td class="py-2 text-center text-xs">{{ $li->qty_borrowed }} / {{ $li->qty_returned ?? '—' }}</td>
                                        <td class="py-2 text-center text-xs">
                                            @if ($li->condition_in)
                                                <span class="{{ $li->condition_in->isDamaged() ? 'text-red-600 font-semibold' : 'text-green-700' }}">{{ $li->condition_in->label() }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="py-2 text-center">
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $li->loan?->status?->badgeClass() }}">{{ $li->loan?->status?->label() }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Kolom kanan --}}
        <div class="space-y-5">
            {{-- QR & label preview --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 text-center">
                <h3 class="font-semibold text-gray-800 text-sm mb-3">QR Code Label</h3>
                <div class="inline-block border-2 border-slate-800 rounded-lg p-3 bg-white">
                    <div class="flex items-center gap-3">
                        @if (\App\Models\InstitutionSetting::current()->logo_path)
                            <img src="{{ asset('storage/'.\App\Models\InstitutionSetting::current()->logo_path) }}" class="h-10 w-10 object-contain" alt="logo">
                        @endif
                        <div class="text-left leading-tight">
                            <div class="text-[9px] font-bold">{{ \App\Models\InstitutionSetting::current()->institution_name }}</div>
                            <div class="font-mono text-[9px]">{{ $item->location_code }}</div>
                            <div class="font-mono text-[9px]">{{ $item->asset_code }}.{{ $item->register_no }}</div>
                        </div>
                        <img src="{{ app(\App\Services\LabelService::class)->qrDataUrl($item) }}" alt="QR" class="h-16 w-16">
                    </div>
                </div>
                <a href="{{ route('items.label', $item) }}" target="_blank"
                   class="mt-3 inline-flex items-center gap-1.5 text-sm text-amber-700 font-semibold hover:underline">
                    <span class="material-icons-round text-lg">print</span> Cetak / Re-print Stiker
                </a>
            </div>

            {{-- Jadwal (anti-bentrok) --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
                    <span class="material-icons-round text-amber-600">event_available</span> Jadwal Pemesanan
                </h3>
                @if ($upcoming->isEmpty())
                    <p class="text-sm text-gray-400">Belum ada pemesanan mendatang — barang bebas dibooking.</p>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach ($upcoming as $loan)
                            <li class="flex items-center justify-between gap-2 border-b border-dashed border-gray-100 pb-2">
                                <div>
                                    <div class="text-gray-700 text-xs font-medium">{{ $loan->purpose }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $loan->start_date->format('d/m/Y H:i') }} → {{ $loan->due_date->format('d/m/Y H:i') }}</div>
                                </div>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <a href="{{ route('loans.create') }}" class="mt-3 inline-flex items-center gap-1.5 text-sm text-amber-700 font-semibold hover:underline">
                    <span class="material-icons-round text-lg">add_shopping_cart</span> Ajukan Peminjaman
                </a>
            </div>

            {{-- Riwayat mutasi --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
                    <span class="material-icons-round text-amber-600">swap_horiz</span> Riwayat Mutasi Lokasi
                </h3>
                @if ($item->movements->isEmpty())
                    <p class="text-sm text-gray-400">Belum pernah dipindahkan.</p>
                @else
                    <ul class="space-y-2.5 text-xs">
                        @foreach ($item->movements as $m)
                            <li class="border-b border-dashed border-gray-100 pb-2">
                                <div class="flex items-center gap-1 text-gray-700">
                                    <span class="font-semibold">{{ $m->fromRoom?->code }}</span>
                                    <span class="material-icons-round text-sm text-gray-400">arrow_forward</span>
                                    <span class="font-semibold">{{ $m->toRoom?->code }}</span>
                                    <span class="text-gray-400 ms-auto">{{ $m->moved_at?->format('d/m/Y') }}</span>
                                </div>
                                <div class="text-gray-400 mt-0.5">oleh {{ $m->mover?->name }}{{ $m->note ? ' — '.$m->note : '' }}</div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Peminjam terakhir --}}
            @if ($lastBorrower)
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
                        <span class="material-icons-round text-amber-600">person</span> Peminjam Terakhir
                    </h3>
                    <div class="text-sm">
                        <div class="font-medium text-gray-800">{{ $lastBorrower->name }}</div>
                        <div class="text-xs text-gray-500">{{ $lastBorrower->username }} • {{ $lastBorrower->role }}</div>
                        <div class="text-xs text-gray-400 mt-1">Dipinjam {{ $item->latestLoanItem()?->created_at?->diffForHumans() }}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
