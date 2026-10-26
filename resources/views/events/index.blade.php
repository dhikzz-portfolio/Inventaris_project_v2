<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Rekap Kegiatan</h2>
            <p class="text-sm text-gray-500">Peminjaman massal terikat satu kegiatan — satu paket pengembalian & BAST</p>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        @if (auth()->user()->managesInventory())
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm mb-4 flex items-center gap-1.5">
                    <span class="material-icons-round text-blue-600">event_available</span> Buat Kegiatan
                </h3>
                <form method="POST" action="{{ route('events.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <x-input-label value="Nama Kegiatan *" />
                        <input type="text" name="name" value="{{ old('name') }}" required
                               class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Ujian Akhir Semester 2026...">
                    </div>
                    <div>
                        <x-input-label value="Penanggung Jawab *" />
                        <input type="text" name="pic_name" value="{{ old('pic_name') }}" required
                               class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <x-input-label value="Mulai *" />
                            <input type="date" name="start_date" value="{{ old('start_date') }}" required
                                   class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <x-input-label value="Selesai *" />
                            <input type="date" name="end_date" value="{{ old('end_date') }}" required
                                   class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    <div>
                        <x-input-label value="Deskripsi" />
                        <textarea name="description" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">{{ old('description') }}</textarea>
                    </div>
                    <x-primary-button class="w-full justify-center bg-blue-600 hover:bg-blue-700 focus:ring-blue-500">Simpan Kegiatan</x-primary-button>
                    @if ($errors->any())
                        <p class="text-xs text-red-600">{{ $errors->first() }}</p>
                    @endif
                </form>
            </div>
        @endif

        <div class="{{ auth()->user()->managesInventory() ? 'lg:col-span-2' : 'lg:col-span-3' }} bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="text-left px-5 py-3">Kegiatan</th>
                            <th class="text-left px-5 py-3">Penanggung Jawab</th>
                            <th class="text-left px-5 py-3">Tanggal</th>
                            <th class="text-center px-5 py-3">Peminjaman</th>
                            <th class="text-center px-5 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($events as $event)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <a href="{{ route('events.show', $event) }}" class="font-medium text-gray-800 hover:text-blue-700">{{ $event->name }}</a>
                                    @if ($event->description)
                                        <div class="text-[11px] text-gray-400">{{ \Illuminate\Support\Str::limit($event->description, 70) }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-xs text-gray-600">{{ $event->pic_name }}</td>
                                <td class="px-5 py-3 text-xs text-gray-500">
                                    {{ $event->start_date->format('d/m/Y') }} — {{ $event->end_date->format('d/m/Y') }}
                                </td>
                                <td class="px-5 py-3 text-center text-xs">
                                    <strong>{{ $event->loans_count }}</strong> pinjam
                                    <span class="block text-[10px] text-gray-400">{{ $event->loans->sum('loan_items_count') }} item</span>
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <a href="{{ route('events.show', $event) }}" class="text-xs font-semibold text-blue-700 hover:underline">Detail →</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400 text-sm">Belum ada kegiatan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3">{{ $events->links() }}</div>
        </div>
    </div>
</x-app-layout>
