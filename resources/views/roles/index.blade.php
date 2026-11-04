<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Role &amp; Permission</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Kelola modul, fitur, dan tindakan yang dapat digunakan setiap role.
            </p>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            {{ session('success') }}
        </div>
    @endif

    <div class="mb-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-xs leading-relaxed text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200">
        Permission mengatur tindakan di dalam ruang kewenangan role. Pemisahan tanggung jawab
        (Super Admin tanpa inventaris, Admin tanpa administrasi akun, Editor/Validator tanpa
        persetujuan akhir) tetap ditegakkan backend dan tidak dapat dilanggar dari halaman ini.
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($roles as $item)
            <div class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">{{ $item['role']->label() }}</h3>
                        <p class="mt-1 font-mono text-[11px] text-slate-400">{{ $item['role']->value }}</p>
                    </div>
                    @if ($item['locked'])
                        <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">TERKUNCI</span>
                    @elseif ($item['customized'])
                        <span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-bold text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">CUSTOM</span>
                    @else
                        <span class="shrink-0 rounded-full bg-green-100 px-2.5 py-1 text-[10px] font-bold text-green-800 dark:bg-green-950/50 dark:text-green-300">DEFAULT</span>
                    @endif
                </div>

                <p class="mt-3 flex-1 text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ $item['description'] }}</p>

                <dl class="mt-4 grid grid-cols-2 gap-2 text-xs">
                    <div class="rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-800/60">
                        <dt class="text-[10px] uppercase tracking-wide text-slate-400">Pengguna</dt>
                        <dd class="mt-0.5 text-base font-bold text-slate-800 dark:text-slate-100">{{ $item['user_count'] }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-800/60">
                        <dt class="text-[10px] uppercase tracking-wide text-slate-400">Fitur aktif</dt>
                        <dd class="mt-0.5 text-base font-bold text-slate-800 dark:text-slate-100">{{ $item['feature_count'] }}</dd>
                    </div>
                </dl>

                <a href="{{ route('roles.show', $item['role']->value) }}"
                   class="mt-4 inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <span class="material-icons-round text-base" aria-hidden="true">{{ $item['locked'] ? 'visibility' : 'tune' }}</span>
                    {{ $item['locked'] ? 'Lihat konfigurasi' : 'Atur permission' }}
                </a>
            </div>
        @endforeach
    </div>
</x-app-layout>
