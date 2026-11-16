@extends('layouts.admin')

@section('title', $plan->exists ? 'Sửa gói' : 'Thêm gói')

@php
    $fields = [
        'name' => ['Tên gói', 'text'],
        'slug' => ['Slug', 'text'],
        'price' => ['Giá (VND)', 'number'],
        'duration_days' => ['Số ngày (để trống = vô hạn)', 'number'],
        'storage_limit_gb' => ['Dung lượng GB (trống = không giới hạn)', 'number'],
        'max_videos' => ['Số video tối đa', 'number'],
        'max_upload_size_mb' => ['File upload tối đa (MB)', 'number'],
        'monthly_encode_minutes' => ['Phút encode / tháng', 'number'],
        'embed_domain_limit' => ['Số domain embed', 'number'],
        'sort' => ['Thứ tự', 'number'],
    ];
    $checks = ['can_use_folders' => 'Cho dùng folder', 'is_popular' => 'Đánh dấu phổ biến', 'is_active' => 'Đang bán'];
@endphp

@section('content')
<h1 class="text-2xl font-semibold tracking-tight">{{ $plan->exists ? 'Sửa gói '.$plan->name : 'Thêm gói' }}</h1>

<form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}"
      class="mt-6 grid max-w-2xl gap-4 rounded-xl border border-neutral-200 p-6 sm:grid-cols-2">
    @csrf
    @if ($plan->exists) @method('PUT') @endif

    @foreach ($fields as $name => [$label, $type])
        <div>
            <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium">{{ $label }}</label>
            <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $plan->$name) }}"
                   class="block h-10 w-full rounded-lg bg-neutral-100 px-3 text-sm outline-1 -outline-offset-1 outline-neutral-200 focus:outline-2 focus:-outline-offset-2 focus:outline-zinc-950">
        </div>
    @endforeach

    <div class="space-y-2 sm:col-span-2">
        @foreach ($checks as $name => $label)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $plan->$name)) class="size-4 rounded">
                {{ $label }}
            </label>
        @endforeach
    </div>

    <div class="flex gap-3 sm:col-span-2">
        <button class="rounded-lg bg-neutral-950 px-4 py-2 text-sm text-white">Lưu</button>
        <a href="{{ route('admin.plans.index') }}" class="rounded-lg border border-neutral-200 px-4 py-2 text-sm">Hủy</a>
    </div>
</form>
@endsection
