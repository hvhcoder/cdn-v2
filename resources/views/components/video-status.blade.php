@props(['video'])

@php
    $status = is_array($video) ? $video['status'] : $video->status;
    $styles = [
        'completed' => ['Hoàn tất', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', 'bg-emerald-500'],
        'processing' => ['Đang xử lý', 'bg-blue-50 text-blue-700 ring-blue-600/20', 'bg-blue-500 animate-pulse'],
        'failed' => ['Lỗi', 'bg-red-50 text-red-700 ring-red-600/20', 'bg-red-500'],
        'pending' => ['Chờ upload', 'bg-slate-100 text-slate-600 ring-slate-500/20', 'bg-slate-400'],
    ];
    [$label, $classes, $dot] = $styles[$status] ?? $styles['pending'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {$classes}"]) }}>
    <span class="size-1.5 rounded-full {{ $dot }}"></span>{{ $label }}
</span>
