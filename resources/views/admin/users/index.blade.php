@extends('layouts.admin')

@section('title', 'Người dùng')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-semibold tracking-tight">Người dùng</h1>
    <form method="GET" class="flex gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Tìm tên / email"
               class="h-9 rounded-lg bg-white px-3 text-sm ring-1 ring-neutral-950/10">
        <button class="rounded-lg bg-neutral-950 px-3 text-sm text-white">Tìm</button>
    </form>
</div>

<div class="mt-6 overflow-x-auto rounded-xl border border-neutral-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-neutral-200 text-neutral-600">
            <tr>
                <th class="px-4 py-3">Người dùng</th>
                <th class="px-4 py-3">Role</th>
                <th class="px-4 py-3">Gói</th>
                <th class="px-4 py-3">Cấp gói</th>
                <th class="px-4 py-3">Hành động</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $user)
                <tr class="border-b border-neutral-100 align-top last:border-0 {{ $user->isBanned() ? 'bg-red-50' : '' }}">
                    <td class="px-4 py-3">
                        <p class="font-medium">{{ $user->name }}</p>
                        <p class="text-neutral-600">{{ $user->email }}</p>
                        @if ($user->isBanned())
                            <p class="mt-1 flex items-center gap-1 text-xs text-red-600">
                                <x-heroicon-s-no-symbol class="h-3.5 w-3.5" />
                                <strong>Bị khóa:</strong> {{ $user->banned_reason }}
                            </p>
                            <p class="text-xs text-neutral-500">
                                Khóa bởi: {{ $user->banner?->email ?? 'N/A' }} lúc {{ $user->banned_at->format('d/m/Y H:i') }}
                            </p>
                        @endif
                        @if ($user->last_login_at)
                            <p class="mt-1 text-xs text-neutral-500">
                                Đăng nhập: {{ $user->last_login_at->diffForHumans() }}
                                @if ($user->last_login_lat && $user->last_login_lon)
                                    -
                                    <a href="https://www.google.com/maps?q={{ $user->last_login_lat }},{{ $user->last_login_lon }}"
                                       target="_blank" class="inline-flex items-center gap-0.5 text-blue-600 hover:underline">
                                        <x-heroicon-o-map-pin class="h-3 w-3" /> {{ number_format($user->last_login_lat, 4) }}, {{ number_format($user->last_login_lon, 4) }}
                                    </a>
                                @endif
                            </p>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <form method="POST" action="{{ route('admin.users.role', $user) }}" class="flex gap-1">
                            @csrf @method('PUT')
                            <select name="role_id" class="h-8 rounded-lg bg-neutral-100 px-2 text-sm">
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}" @selected($role->id === (int) $user->role_id)>{{ $role->label }}</option>
                                @endforeach
                            </select>
                            <button class="rounded-lg border border-neutral-200 px-2 text-xs">Lưu</button>
                        </form>
                    </td>
                    <td class="px-4 py-3">{{ $quota->planFor($user)->name }}</td>
                    <td class="px-4 py-3">
                        <form method="POST" action="{{ route('admin.users.plan', $user) }}" class="flex flex-wrap gap-1">
                            @csrf
                            <select name="plan_id" class="h-8 rounded-lg bg-neutral-100 px-2 text-sm">
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                                @endforeach
                            </select>
                            <input name="days" type="number" min="1" placeholder="ngày" class="h-8 w-20 rounded-lg bg-neutral-100 px-2 text-sm">
                            <button class="rounded-lg bg-neutral-950 px-2 text-xs text-white">Cấp</button>
                        </form>
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.users.show', $user) }}"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-blue-600 px-3 py-1.5 text-xs text-blue-600 hover:bg-blue-50">
                            <x-heroicon-o-pencil-square class="h-3.5 w-3.5" />
                            Xem / Sửa
                        </a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $users->links() }}</div>
@endsection
