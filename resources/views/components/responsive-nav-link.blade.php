@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-brand-600 text-start text-base font-semibold text-brand-800 bg-brand-50 focus:outline-none focus:text-brand-800 focus:bg-brand-100 focus:border-brand-700 transition duration-150 ease-in-out dark:bg-brand-900/40 dark:text-brand-300'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-slate-600 hover:text-brand-700 hover:bg-brand-50 hover:border-brand-200 focus:outline-none focus:text-brand-700 focus:bg-brand-50 focus:border-brand-300 transition duration-150 ease-in-out dark:text-slate-300 dark:hover:bg-slate-800';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
