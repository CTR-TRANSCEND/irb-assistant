@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-slate-300 focus:border-brand-500 focus:ring-brand-500 focus:ring-2 focus:ring-offset-0 rounded-lg shadow-sm text-sm placeholder:text-slate-400 disabled:opacity-60 disabled:cursor-not-allowed transition-colors duration-150 dark:bg-slate-700 dark:border-slate-600 dark:text-white dark:placeholder-slate-400 dark:focus:border-indigo-400 dark:focus:ring-indigo-400']) }}>
