@extends('layouts.app')

@section('title', 'Gói của tôi')

@php
    $meter = function (string $label, int|float $used, ?int $limit, string $text) {
        $percent = $limit ? min(100, round($used / $limit * 100)) : 0;
        return compact('label', 'percent', 'text', 'limit');
    };
    $gb = fn (int $bytes) => round($bytes / 1024 ** 3, 2);
    $meters = [
        $meter('Dung lượng', $usage['storage_bytes'], $plan->storageLimitBytes(), $gb($usage['storage_bytes']).' GB / '.($plan->storage_limit_gb ? $plan->storage_limit_gb.' GB' : 'không giới hạn')),
        $meter('Số video', $usage['video_count'], $plan->max_videos, $usage['video_count'].' / '.($plan->max_videos ?? 'không giới hạn')),
        $meter('Encode tháng này', $usage['encode_minutes'], $plan->monthly_encode_minutes, $usage['encode_minutes'].' / '.($plan->monthly_encode_minutes ?? 'không giới hạn').' phút'),
    ];
@endphp

@section('content')
<h1 class="text-2xl font-semibold tracking-tight">Gói của tôi</h1>

<div class="mt-6 rounded-2xl bg-white p-6 ring-1 ring-neutral-950/10">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <p class="text-sm text-neutral-600">Gói hiện tại</p>
            <p class="text-xl font-semibold">{{ $plan->name }}</p>
        </div>
        <div class="text-right text-sm text-neutral-600">
            @if ($subscription)
                Hết hạn: <span class="font-medium text-neutral-950">{{ $subscription->ends_at->format('d/m/Y H:i') }}</span>
            @else
                Gói miễn phí, không giới hạn thời gian
            @endif
        </div>
    </div>

    <div class="mt-6 space-y-4">
        @foreach ($meters as $m)
            <div>
                <div class="mb-1 flex justify-between text-sm">
                    <span>{{ $m['label'] }}</span>
                    <span class="text-neutral-600">{{ $m['text'] }}</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-neutral-100">
                    <div class="h-full rounded-full {{ $m['percent'] >= 90 ? 'bg-red-600' : 'bg-neutral-950' }}" style="width: {{ $m['percent'] }}%"></div>
                </div>
            </div>
        @endforeach
    </div>

    <a href="{{ route('pricing') }}" class="mt-6 inline-block rounded-lg bg-neutral-950 px-4 py-2 text-sm text-white">Nâng cấp gói</a>
</div>
@endsection
