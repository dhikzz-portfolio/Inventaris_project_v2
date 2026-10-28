<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Rekap Kegiatan</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Guru dan staf dapat mencatat kegiatan sekolah; pengelola dapat memantau seluruh kegiatan</p>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->first() }}</div>
    @endif

    <div class="mb-4 flex items-center gap-2 flex-wrap">
        <a href="{{ route('events.index') }}"
           class="rounded-full px-3 py-1.5 text-xs font-semibold transition {{ $activeStatus === '' ? 'bg-slate-800 text-white' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800' }}">Semua</a>
        @foreach ($statuses as $status)
            <a href="{{ route('events.index', ['status' => $status->value]) }}"
               class="rounded-full px-3 py-1.5 text-xs font-semibold transition {{ $activeStatus === $status->value ? 'bg-slate-800 text-white' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800' }}">{{ $status->label() }}</a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        @if (auth()->user()->canRecordActivities() || auth()->user()->managesInventory())
        <div class="self-start rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 class="mb-4 flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                <span class="material-icons-round text-blue-600">event_available</span> Catat Kegiatan
            </h3>
            <form method="POST" action="{{ route('events.store') }}" class="space-y-3">
                @csrf
                <div>
                    <x-input-label value="Nama Kegiatan *" />
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                           placeholder="Ujian Akhir Semester 2026...">
                </div>
                <div>
                    <x-input-label value="Penanggung Jawab *" />
                    <input type="text" name="pic_name" value="{{ old('pic_name', auth()->user()->displayName()) }}" required
                           class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <x-input-label value="Mulai *" />
                        <input type="date" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" required
                               class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    </div>
                    <div>
                        <x-input-label value="Selesai *" />
                        <input type="date" name="end_date" value="{{ old('end_date', now()->toDateString()) }}" required
                               class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    </div>
                </div>
                <div>
                    <x-input-label value="Status Kegiatan *" />
                    <select name="status" required class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', \App\Enums\EventStatus::BELUM_BERJALAN->value) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label value="Deskripsi" />
                    <textarea name="description" rows="2" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">{{ old('description') }}</textarea>
                </div>
                <x-primary-button class="w-full justify-center bg-blue-600 hover:bg-blue-700 focus:ring-blue-500">Simpan Kegiatan</x-primary-button>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Mencatat kegiatan tidak memberi akses pengelolaan inventaris.</p>
            </form>
        </div>
        @endif

        <div class="{{ auth()->user()->canRecordActivities() || auth()->user()->managesInventory() ? 'lg:col-span-2' : 'lg:col-span-3' }} overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <tr>
                            <th class="text-left px-5 py-3">Kegiatan</th>
                            <th class="text-left px-5 py-3">Penanggung Jawab</th>
                            <th class="text-left px-5 py-3">Tanggal</th>
                            <th class="text-left px-5 py-3">Status</th>
                            <th class="text-center px-5 py-3">Peminjaman</th>
                            <th class="text-center px-5 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($events as $event)
                            <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                <td class="px-5 py-3">
                                    <a href="{{ route('events.show', $event) }}" class="font-medium text-slate-800 hover:text-blue-700 dark:text-slate-100 dark:hover:text-blue-300">{{ $event->name }}</a>
                                    @if ($event->description)
                                        <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ \Illuminate\Support\Str::limit($event->description, 70) }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-xs text-slate-600 dark:text-slate-300">{{ $event->pic_name }}</td>
                                <td class="px-5 py-3 text-xs text-slate-500 dark:text-slate-400">
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
                            <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400 text-sm">Belum ada kegiatan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">{{ $events->links() }}</div>
        </div>
    </div>
</x-app-layout>
