@props([
    'id',
    'name' => null,
    'label',
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'autocomplete' => null,
    'autofocus' => false,
    'readonly' => false,
    'toggleable' => false,
    'icon' => null,
    'error' => null,
])

@php
    $name = $name ?? $id;
    $errorMessage = $error ?? $errors->first($name);
    $hasError = filled($errorMessage);
    $canToggle = $toggleable && $type === 'password';
@endphp

<div>
    <label for="{{ $id }}" class="mb-2 block text-sm font-semibold text-slate-700">{{ $label }}</label>

    <div class="relative">
        @if ($icon)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex w-12 items-center justify-center text-slate-400" aria-hidden="true">
                <span class="material-icons-round text-xl">{{ $icon }}</span>
            </span>
        @endif

        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if (! is_null($value)) value="{{ $value }}" @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($autofocus) autofocus @endif
            @readonly($readonly)
            required
            @class([
                'block w-full rounded-xl border bg-white px-4 py-3.5 text-base text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 sm:py-4',
                'pl-12' => $icon,
                'pr-14' => $canToggle,
                'border-red-400 focus:border-red-500 focus:ring-4 focus:ring-red-500/10' => $hasError,
                'border-slate-300 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10' => ! $hasError,
            ])
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        >

        @if ($canToggle)
            <button
                type="button"
                data-password-toggle="{{ $id }}"
                aria-pressed="false"
                aria-label="Tampilkan kata sandi"
                class="absolute inset-y-0 right-0 flex w-14 items-center justify-center rounded-r-xl text-slate-400 transition-colors hover:text-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
            >
                <span class="material-icons-round text-2xl" data-password-icon aria-hidden="true">visibility</span>
            </button>
        @endif
    </div>

    @if ($hasError)
        <p id="{{ $id }}-error" class="mt-2 flex items-start gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
            <span class="material-icons-round text-base leading-5" aria-hidden="true">error_outline</span>
            <span>{{ $errorMessage }}</span>
        </p>
    @endif
</div>
