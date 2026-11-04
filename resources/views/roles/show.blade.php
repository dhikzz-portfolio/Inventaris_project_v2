<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <a href="{{ route('roles.index') }}" class="text-xs font-semibold text-brand-700 hover:underline dark:text-brand-300">← Role &amp; Permission</a>
                <h2 class="mt-1 text-xl font-bold leading-tight text-slate-900 dark:text-white">{{ $role->label() }}</h2>
                <p class="mt-1 max-w-3xl text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2 text-[11px] font-bold">
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $userCount }} pengguna</span>
                @if ($locked)
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600 dark:bg-slate-800 dark:text-slate-300">TERKUNCI</span>
                @elseif ($customized)
                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">CUSTOM</span>
                @else
                    <span class="rounded-full bg-green-100 px-2.5 py-1 text-green-800 dark:bg-green-950/50 dark:text-green-300">DEFAULT</span>
                @endif
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->first() }}</div>
    @endif

    @if ($locked)
        <div class="mb-5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-relaxed text-slate-700 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200">
            <strong>Role Super Admin bersifat terkunci.</strong> Permission administrasi sistem tidak dapat
            dikurangi atau ditambah dari halaman ini untuk mencegah sistem kehilangan kontrol administrasi
            dan mencegah eskalasi hak akses. Perubahan tetap dicatat pada audit log sistem.
        </div>
    @endif

    <form method="POST" action="{{ route('roles.update', $role->value) }}" id="role-permission-form">
        @csrf
        @method('PUT')

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="text-xs text-slate-600 dark:text-slate-300">
                <span class="font-semibold">Pratinjau:</span>
                <span id="preview-visible">0</span> fitur terlihat,
                <span id="preview-actions">0</span> tindakan diizinkan.
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="applyAll('hidden')" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Sembunyikan semua</button>
                <button type="button" onclick="applyAll('view')" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Semua hanya lihat</button>
                <button type="button" onclick="applyAll('full')" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Semua kelola</button>
            </div>
        </div>

        <div class="space-y-5">
            @foreach ($catalog as $moduleKey => $module)
                <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900" aria-labelledby="module-{{ $moduleKey }}">
                    <div class="border-b border-slate-100 px-4 py-3.5 dark:border-slate-800 sm:px-5">
                        <h3 id="module-{{ $moduleKey }}" class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ $module['label'] }}</h3>
                        <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">{{ $module['description'] }}</p>
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($module['features'] as $feature => $meta)
                            @php($state = $states[$feature] ?? \App\Support\PermissionCatalog::STATE_HIDDEN)
                            <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between sm:px-5">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $meta['label'] }}</p>
                                        <span data-state-badge="{{ $feature }}"
                                              @class([
                                                  'rounded-full px-2 py-0.5 text-[10px] font-bold',
                                                  'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-300' => $state === 'FULL',
                                                  'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-300' => $state === 'VIEW_ONLY',
                                                  'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $state === 'HIDDEN',
                                                  'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300' => $state === 'CUSTOM',
                                              ])>{{ $state === 'FULL' ? 'Kelola' : ($state === 'VIEW_ONLY' ? 'Hanya lihat' : ($state === 'HIDDEN' ? 'Disembunyikan' : 'Custom')) }}</span>
                                    </div>
                                    <p class="mt-0.5 font-mono text-[10px] text-slate-400">{{ $feature }}.*</p>

                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        <button type="button" onclick="setFeature('{{ $feature }}', 'hidden')" class="rounded-md border border-slate-200 px-2 py-1 text-[10px] font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Sembunyikan</button>
                                        <button type="button" onclick="setFeature('{{ $feature }}', 'view')" class="rounded-md border border-slate-200 px-2 py-1 text-[10px] font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Hanya lihat</button>
                                        <button type="button" onclick="setFeature('{{ $feature }}', 'full')" class="rounded-md border border-slate-200 px-2 py-1 text-[10px] font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Kelola</button>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-x-4 gap-y-1.5 sm:max-w-md sm:justify-end">
                                    @foreach ($meta['actions'] as $action)
                                        @php($checked = in_array($action, $selected[$feature] ?? [], true))
                                        <label class="inline-flex items-center gap-1.5 text-xs text-slate-700 dark:text-slate-200">
                                            <input type="checkbox"
                                                   name="permissions[{{ $feature }}][]"
                                                   value="{{ $action }}"
                                                   data-feature="{{ $feature }}"
                                                   data-action="{{ $action }}"
                                                   data-permission="{{ $feature }}.{{ $action }}"
                                                   onchange="updatePreview()"
                                                   @checked($checked)
                                                   @disabled($locked)
                                                   class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 disabled:opacity-50">
                                            <span>{{ ucfirst($action) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        @unless ($locked)
            <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
                <label for="reason" class="block text-xs font-semibold text-slate-700 dark:text-slate-200">Alasan perubahan (opsional, tercatat pada audit)</label>
                <textarea id="reason" name="reason" rows="2" maxlength="500"
                          class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">{{ old('reason') }}</textarea>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="submit"
                            class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-brand-600 px-4 text-sm font-bold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <span class="material-icons-round text-base" aria-hidden="true">save</span> Simpan perubahan
                    </button>
                    <span class="text-[11px] text-slate-400">Perubahan langsung berlaku untuk semua sesi pengguna role ini.</span>
                </div>
            </div>
        @endunless
    </form>

    @unless ($locked)
        <form method="POST" action="{{ route('roles.reset', $role->value) }}" class="mt-3"
              onsubmit="return confirm('Kembalikan seluruh permission role ini ke default?')">
            @csrf
            <input type="hidden" name="reason" value="Reset ke default dari halaman role.">
            <button class="inline-flex min-h-9 items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 text-xs font-semibold text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                <span class="material-icons-round text-base" aria-hidden="true">restart_alt</span> Reset ke default
            </button>
        </form>
    @endunless

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-100 px-4 py-3.5 dark:border-slate-800 sm:px-5">
            <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Riwayat perubahan permission</h3>
            <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">Siapa mengubah apa, kapan, dan alasannya.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-2.5 sm:px-5">Waktu</th>
                        <th class="px-4 py-2.5">Oleh</th>
                        <th class="px-4 py-2.5">Alasan</th>
                        <th class="px-4 py-2.5">Sebelum → Sesudah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($history as $log)
                        <tr class="align-top">
                            <td class="whitespace-nowrap px-4 py-2.5 text-slate-600 dark:text-slate-300 sm:px-5">{{ $log->created_at?->format('d M Y H:i') }}</td>
                            <td class="px-4 py-2.5 text-slate-700 dark:text-slate-200">{{ $log->actor?->displayName() ?? 'Sistem' }}</td>
                            <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ $log->reason ?: '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">
                                {{ count($log->before ?? []) }} → {{ count($log->after ?? []) }} permission
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-slate-400">Belum ada perubahan konfigurasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <script>
        function boxes(feature) {
            return Array.from(document.querySelectorAll('input[data-feature="' + feature + '"]'));
        }

        function setFeature(feature, mode) {
            boxes(feature).forEach(function (box) {
                box.checked = mode === 'full' ? true : (mode === 'view' ? box.dataset.action === 'view' : false);
            });
            updatePreview();
        }

        function applyAll(mode) {
            document.querySelectorAll('input[data-feature]').forEach(function (box) {
                box.checked = mode === 'full' ? true : (mode === 'view' ? box.dataset.action === 'view' : false);
            });
            updatePreview();
        }

        function updatePreview() {
            var checked = Array.from(document.querySelectorAll('input[data-feature]:checked'));
            var features = new Set(checked.map(function (box) { return box.dataset.feature; }));

            document.getElementById('preview-visible').textContent = features.size;
            document.getElementById('preview-actions').textContent = checked.length;

            document.querySelectorAll('[data-state-badge]').forEach(function (badge) {
                var feature = badge.dataset.stateBadge;
                var all = boxes(feature);
                var on = all.filter(function (box) { return box.checked; });
                var total = all.length;
                var text = 'Disembunyikan';
                var cls = 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';

                if (on.length === total) {
                    text = 'Kelola';
                    cls = 'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-300';
                } else if (on.length === 1 && on[0].dataset.action === 'view') {
                    text = 'Hanya lihat';
                    cls = 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-300';
                } else if (on.length > 0) {
                    text = 'Custom';
                    cls = 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300';
                }

                badge.textContent = text;
                badge.className = 'rounded-full px-2 py-0.5 text-[10px] font-bold ' + cls;
            });
        }

        updatePreview();
    </script>
</x-app-layout>
