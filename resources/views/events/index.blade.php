<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Rekap Kegiatan</h2>
            <p class="text-sm text-gray-500">Guru dan staf dapat mencatat kegiatan sekolah; pengelola dapat memantau seluruh kegiatan</p>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="mb-4 flex items-center gap-2 flex-wrap">
        <a href="{{ route('events.index') }}"
           class="px-3 py-1.5 rounded-full text-xs font-semibold {{ $activeStatus === '' ? 'bg-slate-800 text-white' : 'bg-white border border-gray-200 text-gray-600' }}">Semua</a>
        @foreach ($statuses as $status)
            <a href="{{ route('events.index', ['status' => $status->value]) }}"
               class="px-3 py-1.5 rounded-full text-xs font-semibold {{ $activeStatus === $status->value ? 'bg-slate-800 text-white' : 'bg-white border border-gray-200 text-gray-600' }}">{{ $status->label() }}</a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        @if (auth()->user()->canRecordActivities() || auth()->user()->managesInventory())
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 self-start">
            <h3 class="font-semibold text-gray-800 text-sm mb-4 flex items-center gap-1.5">
                <span class="material-icons-round text-amber-600">event_available</span> Catat Kegiatan
            </h3>
            <form method="POST" action="{{ route('events.store') }}" class="space-y-3">
                @csrf
                <div>
                    <x-input-label value="Nama Kegiatan *" />
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500"
                           placeholder="Ujian Akhir Semester 2026...">
                </div>
                <div>
                    <x-input-label value="Penanggung Jawab *" />
                    <input type="text" name="pic_name" value="{{ old('pic_name', auth()->user()->displayName()) }}" required
                           class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <x-input-label value="Mulai *" />
                        <input type="date" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" required
                               class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <x-input-label value="Selesai *" />
                        <input type="date" name="end_date" value="{{ old('end_date', now()->toDateString()) }}" required
                               class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                    </div>
                </div>
                <div>
                    <x-input-label value="Status Kegiatan *" />
                    <select name="status" required class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', \App\Enums\EventStatus::BELUM_BERJALAN->value) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label value="Deskripsi" />
                    <textarea name="description" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">{{ old('description') }}</textarea>
                </div>
                <x-primary-button class="w-full justify-center bg-amber-600 hover:bg-amber-700 focus:ring-amber-500">Simpan Kegiatan</x-primary-button>
                <p class="text-[11px] text-gray-400">Mencatat kegiatan tidak memberi akses pengelolaan inventaris.</p>
            </form>
        </div>
        @endif

        <div class="{{ auth()->user()->canRecordActivities() || auth()->user()->managesInventory() ? 'lg:col-span-2' : 'lg:col-span-3' }} bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="text-left px-5 py-3">Kegiatan</th>
                            <th class="text-left px-5 py-3">Penanggung Jawab</th>
                            <th class="text-left px-5 py-3">Tanggal</th>
                            <th class="text-left px-5 py-3">Status</th>
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
                                <td class="px-5 py-3">
                                    <span class="text-[10px] font-bold px-2 py-1 rounded-full {{ $event->status->badgeClass() }}">{{ $event->status->label() }}</span>
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
                            <tr><td colspan="6" class="px-5 py-10 text-center text-gray-400 text-sm">Belum ada kegiatan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3">{{ $events->links() }}</div>
        </div>
    </div>
</x-app-layout>
