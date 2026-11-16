@props(['color' => null, 'size' => 'size-5'])

@php
    $palette = [
        'slate' => 'text-slate-500',
        'blue' => 'text-blue-500',
        'emerald' => 'text-emerald-500',
        'amber' => 'text-amber-500',
        'rose' => 'text-rose-500',
        'violet' => 'text-violet-500',
    ];
@endphp

<svg {{ $attributes->merge(['class' => "{$size} shrink-0 ".($palette[$color] ?? 'text-slate-400')]) }} viewBox="0 0 24 24" fill="currentColor" fill-opacity=".18" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.7-.9l-.8-1.2A2 2 0 0 0 7.9 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z"/></svg>
