@php
    $picMode = old('pic_mode', $event?->pic_user_id ? 'user' : 'custom');
    $selectedPicId = old('pic_user_id', $event?->pic_user_id);
@endphp

<div x-data="{ mode: '{{ $picMode }}' }" class="space-y-2">
    <input type="hidden" name="pic_mode" :value="mode">

    <div class="grid grid-cols-2 gap-2" role="group" aria-label="Jenis penanggung jawab">
        <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 dark:border-slate-600 dark:has-[:checked]:bg-blue-950/40">
            <input type="radio" x-model="mode" value="user" class="text-blue-600 focus:ring-blue-500">
            <span class="font-semibold text-slate-700 dark:text-slate-200">Akun pengguna</span>
        </label>
        <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 dark:border-slate-600 dark:has-[:checked]:bg-blue-950/40">
            <input type="radio" x-model="mode" value="custom" class="text-blue-600 focus:ring-blue-500">
            <span class="font-semibold text-slate-700 dark:text-slate-200">Nama khusus</span>
        </label>
    </div>

    <div x-show="mode === 'user'" x-cloak>
        <select name="pic_user_id" :disabled="mode !== 'user'"
                class="block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
            <option value="">— cari/pilih nama pengguna —</option>
            @foreach ($picUsers as $picUser)
                <option value="{{ $picUser->id }}" @selected((string) $selectedPicId === (string) $picUser->id)>
                    {{ $picUser->displayName() }} — {{ \App\Enums\Role::tryFrom($picUser->role)?->label() ?? $picUser->role }}
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Ketik nama untuk mencari pada daftar akun terdaftar.</p>
    </div>

    <div x-show="mode === 'custom'" x-cloak>
        <input type="text" name="pic_name" :disabled="mode !== 'custom'"
               value="{{ old('pic_name', $event?->pic_name) }}" maxlength="255"
               placeholder="Panitia MPLS 2026, OSIS, MPK, atau organisasi sekolah"
               class="block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Nama khusus tidak membuat akun pengguna baru.</p>
    </div>

    <x-input-error :messages="$errors->get('pic_user_id')" class="mt-1" />
    <x-input-error :messages="$errors->get('pic_name')" class="mt-1" />
</div>
