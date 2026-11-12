@props([
    'name' => null,
    'id' => null,
    'options' => [],
    'selected' => '',
    'placeholder' => 'Cari atau pilih…',
    'emptyText' => 'Tidak ada pilihan yang cocok.',
    'searchHint' => 'Ketik untuk mencari',
    'submit' => false,
    'required' => false,
    'disabled' => false,
    'wireModel' => null,
    'ariaLabel' => null,
])

@php
    $fieldId = $id ?? 'searchable-'.Illuminate\Support\Str::lower(Illuminate\Support\Str::random(8));
    $normalizedOptions = collect($options)
        ->map(function ($option) {
            if (is_array($option)) {
                return [
                    'value' => (string) ($option['value'] ?? ''),
                    'label' => (string) ($option['label'] ?? ''),
                    'hint' => (string) ($option['hint'] ?? ''),
                ];
            }

            return ['value' => (string) $option, 'label' => (string) $option, 'hint' => ''];
        })
        ->values()
        ->all();
    $currentValue = old($name ?? $fieldId, $selected);
@endphp

<div
    {{ $attributes->merge(['class' => 'relative']) }}
    @if ($wireModel)
        wire:key="{{ $wireModel }}-{{ (string) $currentValue }}"
    @endif
    x-data="searchableSelect({
        options: {{ Illuminate\Support\Js::from($normalizedOptions) }},
        value: {{ Illuminate\Support\Js::from((string) $currentValue) }},
        submit: {{ $submit ? 'true' : 'false' }}
    })"
    x-modelable="value"
    @keydown.escape="open = false; restoreQuery()"
>
    @if ($name || $wireModel)
        <input type="hidden"
               @if ($name) name="{{ $name }}" @endif
               @if ($wireModel) wire:model.live="{{ $wireModel }}" @endif
               :value="value">
    @endif

    <div class="relative">
        <input
            id="{{ $fieldId }}"
            type="text"
            x-model="query"
            x-ref="input"
            @focus="open = true; query = ''"
            @click="open = true"
            @input="onInput()"
            @blur="restoreQuery(); open = false"
            @keydown.arrow-down.prevent="highlightNext()"
            @keydown.arrow-up.prevent="highlightPrev()"
            @keydown.enter.prevent="chooseHighlighted()"
            :placeholder="selectedOption ? selectedOption.label : {{ Illuminate\Support\Js::from($placeholder) }}"
            @if ($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
            autocomplete="off"
            role="combobox"
            aria-autocomplete="list"
            aria-controls="{{ $fieldId }}-options"
            :aria-expanded="open.toString()"
            @if ($required) required @endif
            @disabled($disabled)
            class="min-h-10 w-full rounded-xl border-slate-300 bg-white pr-9 text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:disabled:bg-slate-800"
        >

        <button
            type="button"
            x-show="value !== ''"
            x-cloak
            @mousedown.prevent="clear()"
            aria-label="Hapus pilihan"
            class="absolute right-2 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
        >
            <span class="material-icons-round text-base" aria-hidden="true">close</span>
        </button>
        <span x-show="value === ''" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 material-icons-round text-base text-slate-400" aria-hidden="true">search</span>
    </div>

    <ul
        x-show="open"
        x-cloak
        id="{{ $fieldId }}-options"
        role="listbox"
        class="absolute z-40 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900"
    >
        <template x-for="(option, index) in filteredOptions" :key="option.value">
            <li
                role="option"
                @mousedown.prevent="choose(option)"
                @mouseenter="highlighted = index"
                :aria-selected="String(value) === option.value"
                :class="highlighted === index ? 'bg-brand-50 text-brand-800 dark:bg-brand-950/40 dark:text-brand-200' : 'text-slate-700 dark:text-slate-200'"
                class="cursor-pointer px-3 py-2 text-sm hover:bg-brand-50 dark:hover:bg-brand-950/40"
            >
                <span class="block font-medium" x-text="option.label"></span>
                <span x-show="option.hint" class="block text-[11px] text-slate-500 dark:text-slate-400" x-text="option.hint"></span>
            </li>
        </template>
        <li x-show="filteredOptions.length === 0" class="px-3 py-2 text-xs text-slate-500 dark:text-slate-400">
            <span class="block font-semibold text-slate-700 dark:text-slate-200">{{ $emptyText }}</span>
            <span class="mt-1 block">{{ $searchHint }}</span>
        </li>
    </ul>
</div>
