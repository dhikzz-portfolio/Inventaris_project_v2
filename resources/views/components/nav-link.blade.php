@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-blue-600 text-sm font-semibold leading-5 text-blue-800 focus:outline-none focus:border-blue-700 transition duration-150 ease-in-out dark:text-blue-300'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-slate-500 hover:text-blue-700 hover:border-blue-200 focus:outline-none focus:text-blue-700 focus:border-blue-300 transition duration-150 ease-in-out dark:text-slate-400 dark:hover:text-blue-300';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
