@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-slate-300 focus:border-brand-500 focus:ring-brand-500 focus:ring-2 focus:ring-offset-0 rounded-lg shadow-sm text-sm placeholder:text-slate-400 disabled:opacity-60 disabled:cursor-not-allowed transition-colors duration-150']) }}>
