@extends('layouts.app')

@section('title', 'Tổng quan')

@php
    $gb = fn (int $bytes) => rtrim(rtrim(number_format($bytes / 1024 ** 3, 2, '.', ''), '0'), '.');
    $percent = fn ($used, $limit) => $limit ? min(100, (int) round($used / $limit * 100)) : 0;
    $stats = [
        ['Tổng số video', $usage['video_count'], 'trên '.($plan->max_videos ?? '∞').' video', $percent($usage['video_count'], $plan->max_videos), 'bg-blue-500', 'bg-blue-50 text-blue-600', '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="m10 8 6 4-6 4z"/>'],
        ['Dung lượng', $gb($usage['storage_bytes']).' GB', 'trên '.($plan->storage_limit_gb ? $plan->storage_limit_gb.' GB' : '∞'), $percent($usage['storage_bytes'], $plan->storageLimitBytes()), 'bg-violet-500', 'bg-violet-50 text-violet-600', '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/>'],
        ['Phút encode tháng này', $usage['encode_minutes'], 'trên '.($plan->monthly_encode_minutes ?? '∞').' phút', $percent($usage['encode_minutes'], $plan->monthly_encode_minutes), 'bg-amber-500', 'bg-amber-50 text-amber-600', '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'],
        ['Đang xử lý', $processing, $completed.' video đã hoàn tất', null, '', 'bg-emerald-50 text-emerald-600', '<path d="M21 12a9 9 0 1 1-6.2-8.55"/><path d="M21 3v6h-6"/>'],
    ];
@endphp

@section('content')
{{-- Lời chào --}}
<section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-600 via-blue-600 to-indigo-700 p-6 text-white shadow-lg shadow-blue-600/20 sm:p-8">
    <div class="pointer-events-none absolute -right-16 -top-16 size-64 rounded-full bg-white/10 blur-2xl"></div>
    <div class="pointer-events-none absolute -bottom-20 right-24 size-56 rounded-full bg-indigo-400/30 blur-3xl"></div>
    <div class="relative flex flex-wrap items-center justify-between gap-6">
        <div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium backdrop-blur">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m2 8 4.5 4L12 4l5.5 8L22 8l-2 11H4z"/></svg>
                Gói {{ $plan->name }}
                @if ($subscription) · còn {{ (int) ceil(now()->diffInDays($subscription->ends_at, false)) }} ngày @endif
            </span>
            <h1 class="mt-4 text-2xl font-semibold tracking-tight sm:text-3xl">Xin chào, {{ \Illuminate\Support\Str::of(auth()->user()->name)->words(3, '') }}!</h1>
            <p class="mt-1.5 max-w-xl text-sm text-blue-100">
                @if ($canUpload)
                    Tải video lên, hệ thống sẽ tự cắt, mã hóa và đẩy lên CDN. Bạn chỉ cần sao chép link để chia sẻ.
                @else
                    Xem lại các video của bạn và theo dõi mức sử dụng. Nâng cấp tài khoản lên VIP để tự tải video lên.
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-3">
            @if ($canUpload)
                <a href="{{ route('videos.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-2.5 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M20 16v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3"/></svg>
                    Tải video lên
                </a>
            @else
                <a href="{{ route('pricing') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-2.5 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50">Xem bảng giá</a>
            @endif
            <a href="{{ route('videos.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-5 py-2.5 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/25">Quản lý video</a>
        </div>
    </div>
</section>

{{-- Thống kê --}}
<section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($stats as [$label, $value, $hint, $bar, $barColor, $iconStyle, $iconPath])
        <div class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-slate-200/70">
            <div class="flex items-start justify-between">
                <p class="text-sm text-slate-500">{{ $label }}</p>
                <span class="flex size-9 items-center justify-center rounded-xl {{ $iconStyle }}">
                    <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $iconPath !!}</svg>
                </span>
            </div>
            <p class="mt-3 text-2xl font-semibold tracking-tight">{{ $value }}</p>
            <p class="mt-0.5 text-xs text-slate-500">{{ $hint }}</p>
            @if ($bar !== null)
                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full {{ $bar >= 90 ? 'bg-red-500' : $barColor }}" style="width: {{ $bar }}%"></div>
                </div>
            @endif
        </div>
    @endforeach
</section>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    {{-- Video gần đây --}}
    <section class="rounded-2xl bg-white shadow-xs ring-1 ring-slate-200/70 lg:col-span-2">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 class="font-semibold">Video gần đây</h2>
            <a href="{{ route('videos.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-700">Xem tất cả →</a>
        </div>

        @forelse ($recent as $video)
            <a href="{{ route('videos.show', $video) }}" class="flex items-center gap-4 border-b border-slate-100 px-5 py-3.5 transition-colors last:border-0 hover:bg-slate-50">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-slate-100 to-slate-200 text-slate-500">
                    <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10-6.5a1 1 0 0 0 0-1.72l-10-6.5A1 1 0 0 0 8 5.5z"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ $video->title }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $video->formattedSize() }} · {{ $video->formattedDuration() }} · {{ $video->created_at->diffForHumans() }}</p>
                    @if ($video->status === 'processing' && $video->segment_count)
                        <div class="mt-1.5 h-1 w-40 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-blue-500" style="width: {{ (int) floor($video->uploaded_segments_count / $video->segment_count * 100) }}%"></div></div>
                    @endif
                </div>
                <x-video-status :video="$video" />
            </a>
        @empty
            <div class="px-5 py-12 text-center">
                <span class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="m10 8 6 4-6 4z"/></svg>
                </span>
                <p class="mt-3 text-sm font-medium">Chưa có video nào</p>
                <p class="mt-1 text-sm text-slate-500">{{ $canUpload ? 'Tải video đầu tiên của bạn lên để bắt đầu.' : 'Các video của bạn sẽ xuất hiện ở đây.' }}</p>
                @if ($canUpload)
                    <a href="{{ route('videos.create') }}" class="mt-4 inline-block rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Tải video lên</a>
                @endif
            </div>
        @endforelse
    </section>

    {{-- Gói của tôi --}}
    <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-slate-200/70">
        <h2 class="font-semibold">Gói của bạn</h2>
        <div class="mt-4 rounded-xl bg-slate-50 p-4">
            <p class="text-xs text-slate-500">Gói hiện tại</p>
            <p class="mt-0.5 text-lg font-semibold">{{ $plan->name }}</p>
            <p class="mt-1 text-xs text-slate-500">
                @if ($subscription) Hết hạn {{ $subscription->ends_at->format('d/m/Y') }} @else Không giới hạn thời gian @endif
            </p>
        </div>
        <ul class="mt-4 space-y-2.5 text-sm text-slate-600">
            <li class="flex justify-between"><span>File tối đa</span><span class="font-medium text-slate-900">{{ $plan->max_upload_size_mb ? $plan->max_upload_size_mb.' MB' : 'Không giới hạn' }}</span></li>
            <li class="flex justify-between"><span>Domain embed</span><span class="font-medium text-slate-900">{{ $plan->embed_domain_limit ?? '∞' }}</span></li>
            <li class="flex justify-between"><span>Folder</span><span class="font-medium text-slate-900">{{ $plan->can_use_folders ? 'Có' : 'Không' }}</span></li>
        </ul>
        <a href="{{ route('subscription') }}" class="mt-5 block rounded-xl border border-slate-200 py-2.5 text-center text-sm font-medium transition hover:bg-slate-50">Chi tiết gói</a>
        <a href="{{ route('pricing') }}" class="mt-2 block rounded-xl bg-slate-900 py-2.5 text-center text-sm font-medium text-white transition hover:bg-slate-800">Nâng cấp gói</a>
    </section>
</div>
@endsection
