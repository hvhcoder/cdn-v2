@extends('layouts.app')

@section('title', 'Bảng giá')

@section('content')
<h1 class="text-2xl font-semibold tracking-tight">Bảng giá</h1>
<p class="mt-1 text-sm text-neutral-600">Chọn gói phù hợp với nhu cầu của bạn.</p>

<div class="mt-6 grid gap-4 sm:grid-cols-3">
    @foreach ($plans as $plan)
        <div class="flex flex-col rounded-2xl bg-white p-6 ring-1 {{ $plan->is_popular ? 'ring-2 ring-neutral-950' : 'ring-neutral-950/10' }}">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">{{ $plan->name }}</h2>
                @if ($plan->is_popular)
                    <span class="rounded-full bg-neutral-950 px-2 py-0.5 text-xs text-white">Phổ biến</span>
                @endif
            </div>
            <p class="mt-3 text-3xl font-semibold">{{ $plan->price ? number_format($plan->price, 0, ',', '.').'đ' : 'Miễn phí' }}</p>
            <p class="text-sm text-neutral-600">{{ $plan->duration_days ? "/ {$plan->duration_days} ngày" : 'không giới hạn thời gian' }}</p>

            <ul class="mt-5 flex-1 space-y-2 text-sm text-neutral-700">
                <li>{{ $plan->storage_limit_gb ? $plan->storage_limit_gb.' GB lưu trữ' : 'Không giới hạn lưu trữ' }}</li>
                <li>{{ $plan->max_videos ? $plan->max_videos.' video' : 'Không giới hạn video' }}</li>
                <li>{{ $plan->max_upload_size_mb ? 'File tối đa '.$plan->max_upload_size_mb.' MB' : 'Không giới hạn dung lượng file' }}</li>
                <li>{{ $plan->monthly_encode_minutes ? $plan->monthly_encode_minutes.' phút encode / tháng' : 'Không giới hạn encode' }}</li>
                <li>{{ $plan->embed_domain_limit ? $plan->embed_domain_limit.' domain embed' : 'Không giới hạn domain embed' }}</li>
                <li>{{ $plan->can_use_folders ? 'Có folder' : 'Không có folder' }}</li>
            </ul>

            @auth
                @if ($plan->isFree())
                    <span class="mt-6 block rounded-lg border border-neutral-200 py-2 text-center text-sm text-neutral-600">Gói mặc định</span>
                @else
                    <span class="mt-6 block rounded-lg bg-neutral-950 py-2 text-center text-sm text-white">Liên hệ admin để nâng cấp</span>
                @endif
            @else
                <a href="{{ route('register') }}" class="mt-6 block rounded-lg bg-neutral-950 py-2 text-center text-sm text-white">Bắt đầu</a>
            @endauth
        </div>
    @endforeach
</div>
@endsection
