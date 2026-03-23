@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg text-indigo-700 bg-indigo-50 transition duration-150 ease-in-out'
            : 'inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
