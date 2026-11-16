@extends('layouts.admin')

@section('title', 'Cấu hình upload')

@php
    $statusStyles = ['active' => 'bg-green-50 text-green-700', 'dead' => 'bg-red-50 text-red-700', 'disabled' => 'bg-neutral-100 text-neutral-600'];
    $statusLabels = ['active' => 'Đang bật', 'dead' => 'Hết hạn / lỗi', 'disabled' => 'Đã tắt'];
@endphp

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-semibold tracking-tight">Cấu hình upload</h1>
    <a href="{{ route('admin.upload-configs.create', ['platform' => $currentPlatform ?? 'r2']) }}" class="rounded-lg bg-neutral-950 px-4 py-2 text-sm text-white">
        Thêm cấu hình {{ $currentPlatform ? $platforms[$currentPlatform]['label'] : '' }}
    </a>
</div>

<div class="mt-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex gap-1 rounded-lg bg-neutral-100 p-1 text-sm">
        <a href="{{ route('admin.upload-configs.index') }}" class="rounded-md px-3 py-1.5 {{ $currentPlatform ? 'text-neutral-600' : 'bg-white shadow-xs' }}">Tất cả</a>
        @foreach ($platforms as $key => $platform)
            <a href="{{ route('admin.upload-configs.index', ['platform' => $key]) }}" class="rounded-md px-3 py-1.5 {{ $currentPlatform === $key ? 'bg-white shadow-xs' : 'text-neutral-600' }}">{{ $platform['label'] }}</a>
        @endforeach
    </div>
    <form method="GET" class="flex gap-2">
        @if ($currentPlatform) <input type="hidden" name="platform" value="{{ $currentPlatform }}"> @endif
        <input name="q" value="{{ request('q') }}" placeholder="Tìm theo tên" class="h-9 rounded-lg bg-white px-3 text-sm ring-1 ring-neutral-950/10">
        <button class="rounded-lg bg-neutral-950 px-3 text-sm text-white">Tìm</button>
    </form>
</div>

@if ($currentPlatform)
    <p class="mt-3 text-sm text-neutral-600">{{ $platforms[$currentPlatform]['desc'] }}</p>
@endif

<div class="mt-4 overflow-x-auto rounded-xl border border-neutral-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-neutral-200 text-neutral-600">
            <tr>
                <th class="px-4 py-3">Tên</th>
                <th class="px-4 py-3">Nền tảng</th>
                <th class="px-4 py-3">Thông tin</th>
                <th class="px-4 py-3">Trạng thái</th>
                <th class="px-4 py-3">Đã dùng</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($configs as $config)
                @php $single = $platforms[$config->platform]['mode'] === 'single'; @endphp
                <tr class="border-b border-neutral-100 align-top last:border-0">
                    <td class="px-4 py-3 font-medium">
                        {{ $config->name }}
                        @if ($config->is_default)
                            <span class="ml-1 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-normal text-blue-700">Mặc định</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">{{ $config->platformLabel() }}</td>
                    <td class="max-w-xs truncate px-4 py-3 text-neutral-600">
                        @if ($config->platform === 'r2')
                            {{ $config->data('bucket') }} · {{ $config->data('endpoint') }}
                        @else
                            Cookie đã lưu (mã hóa)
                        @endif
                        @if ($config->last_error)
                            <p class="truncate text-xs text-red-600" title="{{ $config->last_error }}">{{ $config->last_error }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $statusStyles[$config->status] ?? '' }}">{{ $statusLabels[$config->status] ?? $config->status }}</span></td>
                    <td class="px-4 py-3 text-neutral-600">
                        @unless ($single){{ number_format($config->uploads_count) }} lần · {{ round($config->uploaded_bytes / 1024 ** 2, 1) }} MB @endunless
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap justify-end gap-3">
                            <form method="POST" action="{{ route('admin.upload-configs.test', $config) }}">@csrf<button class="underline">Kiểm tra</button></form>
                            @if ($single && ! $config->is_default)
                                <form method="POST" action="{{ route('admin.upload-configs.default', $config) }}">@csrf @method('PUT')<button class="underline">Đặt mặc định</button></form>
                            @endif
                            <form method="POST" action="{{ route('admin.upload-configs.toggle', $config) }}">@csrf @method('PUT')<button class="underline">{{ $config->status === 'active' ? 'Tắt' : 'Bật' }}</button></form>
                            <a href="{{ route('admin.upload-configs.edit', $config) }}" class="underline">Sửa</a>
                            <form method="POST" action="{{ route('admin.upload-configs.destroy', $config) }}" onsubmit="return confirm('Xóa cấu hình này?')">@csrf @method('DELETE')<button class="text-red-600 underline">Xóa</button></form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-neutral-600">Chưa có cấu hình nào.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
