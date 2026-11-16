@extends('layouts.admin')

@section('title', 'Quản lý gói')

@section('content')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold tracking-tight">Quản lý gói</h1>
    <a href="{{ route('admin.plans.create') }}" class="rounded-lg bg-neutral-950 px-4 py-2 text-sm text-white">Thêm gói</a>
</div>

<div class="mt-6 overflow-x-auto rounded-xl border border-neutral-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-neutral-200 text-neutral-600">
            <tr>
                <th class="px-4 py-3">Tên</th>
                <th class="px-4 py-3">Giá</th>
                <th class="px-4 py-3">Ngày</th>
                <th class="px-4 py-3">GB</th>
                <th class="px-4 py-3">Video</th>
                <th class="px-4 py-3">Trạng thái</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($plans as $plan)
                <tr class="border-b border-neutral-100 last:border-0">
                    <td class="px-4 py-3 font-medium">{{ $plan->name }}</td>
                    <td class="px-4 py-3">{{ number_format($plan->price, 0, ',', '.') }}đ</td>
                    <td class="px-4 py-3">{{ $plan->duration_days ?? '∞' }}</td>
                    <td class="px-4 py-3">{{ $plan->storage_limit_gb ?? '∞' }}</td>
                    <td class="px-4 py-3">{{ $plan->max_videos ?? '∞' }}</td>
                    <td class="px-4 py-3">{{ $plan->is_active ? 'Đang bán' : 'Ẩn' }}</td>
                    <td class="px-4 py-3 text-right"><a href="{{ route('admin.plans.edit', $plan) }}" class="underline">Sửa</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
