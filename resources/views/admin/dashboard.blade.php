@extends('layouts.admin')

@section('title', 'Tổng quan')

@section('content')
<h1 class="text-2xl font-semibold tracking-tight">Chào mừng trở lại, {{ auth()->user()->email }}!</h1>
<p class="mt-1 text-sm text-neutral-600">Đây là trang quản trị dành cho Admin.</p>

<div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($stats as $stat)
        <div class="rounded-xl border border-neutral-200 p-4">
            <p class="text-sm text-neutral-600">{{ $stat['label'] }}</p>
            <p class="mt-2 text-2xl font-semibold">{{ $stat['value'] }}</p>
        </div>
    @endforeach
</div>

<h2 class="mt-8 text-base font-semibold">Người dùng mới</h2>
<div class="mt-3 overflow-x-auto rounded-xl border border-neutral-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-neutral-200 text-neutral-600">
            <tr>
                <th class="px-4 py-3">Tên</th>
                <th class="px-4 py-3">Email</th>
                <th class="px-4 py-3">Role</th>
                <th class="px-4 py-3">Ngày tạo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($latestUsers as $user)
                <tr class="border-b border-neutral-100 last:border-0">
                    <td class="px-4 py-3">{{ $user->name }}</td>
                    <td class="px-4 py-3">{{ $user->email }}</td>
                    <td class="px-4 py-3">{{ $user->role->label }}</td>
                    <td class="px-4 py-3">{{ $user->created_at->format('d/m/Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
