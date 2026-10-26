@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-blue-600 text-start text-base font-semibold text-blue-800 bg-blue-50 focus:outline-none focus:text-blue-800 focus:bg-blue-100 focus:border-blue-700 transition duration-150 ease-in-out dark:bg-blue-950/40 dark:text-blue-300'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-slate-600 hover:text-blue-700 hover:bg-blue-50 hover:border-blue-200 focus:outline-none focus:text-blue-700 focus:bg-blue-50 focus:border-blue-300 transition duration-150 ease-in-out dark:text-slate-300 dark:hover:bg-slate-800';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
