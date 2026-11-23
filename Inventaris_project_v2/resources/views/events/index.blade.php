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

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <a href="{{ route('events.index') }}"
           class="inline-flex min-h-10 items-center rounded-xl border px-3 py-2 text-xs font-semibold transition {{ $activeStatus === '' ? 'border-brand-600 bg-brand-600 text-white shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:border-brand-200 hover:bg-brand-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:bg-slate-800' }}">Semua</a>
        @foreach ($statuses as $status)
            <a href="{{ route('events.index', ['status' => $status->value]) }}"
               class="inline-flex min-h-10 items-center rounded-xl border px-3 py-2 text-xs font-semibold transition {{ $activeStatus === $status->value ? 'border-brand-600 bg-brand-600 text-white shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:border-brand-200 hover:bg-brand-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:bg-slate-800' }}">{{ $status->label() }}</a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-3">
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
                    <div class="mt-1">
                        @include('events._pic_fields', ['picUsers' => $picUsers, 'event' => null])
                    </div>
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

        <div class="{{ auth()->user()->canRecordActivities() || auth()->user()->managesInventory() ? 'lg:col-span-2' : 'lg:col-span-3' }} min-w-0 self-start overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div role="region" aria-label="Daftar kegiatan" tabindex="0"
                 class="max-h-[65vh] divide-y divide-slate-100 overflow-y-auto overscroll-contain focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:divide-slate-800 lg:max-h-[calc(100vh-12rem)]">
                @forelse ($events as $event)
                <article class="min-w-0 space-y-3 p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('events.show', $event) }}" class="break-words text-sm font-semibold text-slate-800 hover:text-blue-700 dark:text-slate-100 dark:hover:text-blue-300">{{ $event->name }}</a>
                            @if ($event->description)
                                <p class="mt-1 break-words text-xs text-slate-500 dark:text-slate-400">{{ \Illuminate\Support\Str::limit($event->description, 90) }}</p>
                            @endif
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-bold {{ $event->status->badgeClass() }}">{{ $event->status->label() }}</span>
                    </div>

                    <dl class="grid grid-cols-1 gap-2 rounded-xl bg-slate-50 p-3 sm:grid-cols-2 dark:bg-slate-800/70">
                        <div class="min-w-0">
                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Penanggung jawab</dt>
                            <dd class="mt-0.5 break-words text-xs font-medium text-slate-700 dark:text-slate-200">
                                {{ $event->picDisplayName() ?: '—' }}
                                @if ($event->picIsUserAccount())
                                    <span class="block text-[10px] text-blue-600 dark:text-blue-300">akun terdaftar</span>
                                @endif
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Tanggal</dt>
                            <dd class="mt-0.5 text-xs font-medium text-slate-700 dark:text-slate-200">{{ $event->start_date->format('d/m/Y') }} — {{ $event->end_date->format('d/m/Y') }}</dd>
                        </div>
                        <div class="min-w-0 sm:col-span-2">
                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Peminjaman</dt>
                            <dd class="mt-0.5 text-xs font-medium text-slate-700 dark:text-slate-200">{{ $event->loans_count }} pinjam • {{ $event->loans->sum('loan_items_count') }} item</dd>
                        </div>
                    </dl>

                    <a href="{{ route('events.show', $event) }}"
                       class="inline-flex min-h-9 items-center justify-center gap-2 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                        Lihat detail
                    </a>
                </article>
                @empty
                <p class="px-5 py-10 text-center text-sm text-slate-400">Belum ada kegiatan.</p>
                @endforelse
            </div>
            @if ($events->hasPages())
                <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $events->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
