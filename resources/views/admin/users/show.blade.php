@extends('layouts.admin')

@section('title', 'Chi tiết người dùng')

@section('content')
<div class="mb-6">
    <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline">
        <x-heroicon-o-arrow-left class="h-4 w-4" />
        Về danh sách
    </a>
</div>

@if (session('status'))
    <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 p-4 text-sm text-green-800">
        <x-heroicon-s-check-circle class="h-5 w-5 flex-shrink-0" />
        {{ session('status') }}
    </div>
@endif

<div class="grid gap-6 lg:grid-cols-3">
    <!-- Left: User Info & Edit -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Basic Info -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6">
            <h2 class="flex items-center gap-2 text-xl font-semibold">
                <x-heroicon-o-user-circle class="h-6 w-6" />
                Thông tin cơ bản
            </h2>

            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-sm font-medium">Tên <span class="text-red-600">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium">Email <span class="text-red-600">*</span></label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium">Mật khẩu mới <span class="text-neutral-500">(để trống nếu không đổi)</span></label>
                    <input type="password" name="password" placeholder="Tối thiểu 8 ký tự"
                           class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm">
                    @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium">Role</label>
                    <select name="role_id" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm">
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected($role->id === (int) $user->role_id)>{{ $role->label }}</option>
                        @endforeach
                    </select>
                </div>

                <button class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">
                    <x-heroicon-o-check class="h-3 w-3" />
                    Lưu thay đổi
                </button>
            </form>
        </div>

        <!-- Login & Location History -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6">
            <h2 class="flex items-center gap-2 text-xl font-semibold">
                <x-heroicon-o-map-pin class="h-6 w-6" />
                Lịch sử đăng nhập & Vị trí
            </h2>

            <div class="mt-4 space-y-3">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-neutral-600">Đăng nhập gần nhất</p>
                        <p class="font-medium">{{ $user->last_login_at?->format('d/m/Y H:i') ?? 'Chưa có' }}</p>
                    </div>
                    <div>
                        <p class="text-neutral-600">Địa chỉ IP</p>
                        <p class="font-mono font-medium">{{ $user->last_login_ip ?? 'N/A' }}</p>
                    </div>
                </div>

                @if ($user->last_login_lat && $user->last_login_lon)
                    <div class="pt-3 border-t">
                        <p class="text-sm text-neutral-600 mb-2">Tọa độ GPS: {{ $user->last_login_lat }}, {{ $user->last_login_lon }}</p>
                        <a href="https://www.google.com/maps?q={{ $user->last_login_lat }},{{ $user->last_login_lon }}"
                           target="_blank"
                           class="inline-flex items-center gap-2 rounded-lg bg-neutral-100 px-3 py-2 text-sm hover:bg-neutral-200">
                            <x-heroicon-o-map class="h-4 w-4" />
                            Xem trên Google Maps
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <!-- Action Logs -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6">
            <h2 class="flex items-center gap-2 text-xl font-semibold mb-4">
                <x-heroicon-o-clipboard-document-list class="h-6 w-6" />
                Nhật ký hành động
            </h2>

            <div class="space-y-3">
                @forelse ($logs as $log)
                    <div class="border-l-4 border-neutral-300 pl-4 py-2 text-sm">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <p class="font-medium">{{ $log->action }}</p>
                                <p class="flex items-center gap-1 text-xs text-neutral-600 mt-1">
                                    <x-heroicon-o-clock class="h-3 w-3" />
                                    {{ $log->created_at->format('d/m/Y H:i:s') }}
                                </p>
                                @if ($log->ip)
                                    <p class="flex items-center gap-1 text-xs text-neutral-500 mt-1">
                                        <x-heroicon-o-globe-alt class="h-3 w-3" />
                                        IP: {{ $log->ip }}
                                        @if ($log->lat && $log->lon)
                                            - {{ number_format($log->lat, 4) }}, {{ number_format($log->lon, 4) }}
                                            <a href="https://www.google.com/maps?q={{ $log->lat }},{{ $log->lon }}"
                                               target="_blank" class="inline-flex items-center text-blue-600 hover:underline ml-1">
                                                <x-heroicon-o-map-pin class="h-3 w-3" />
                                            </a>
                                        @endif
                                    </p>
                                @endif
                                @if ($log->meta)
                                    <details class="mt-1 text-xs">
                                        <summary class="cursor-pointer text-blue-600">Chi tiết</summary>
                                        <pre class="mt-1 bg-neutral-50 p-2 rounded overflow-x-auto">{{ json_encode($log->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </details>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-neutral-500 text-sm">Chưa có nhật ký nào.</p>
                @endforelse
            </div>

            <div class="mt-4">{{ $logs->links() }}</div>
        </div>
    </div>

    <!-- Right: Status & Quick Actions -->
    <div class="space-y-6">
        <!-- Ban Status -->
        @if ($user->isBanned())
            <div class="rounded-xl border border-red-200 bg-red-50 p-6">
                <h3 class="flex items-center gap-2 text-lg font-semibold text-red-900">
                    <x-heroicon-s-no-symbol class="h-5 w-5 flex-shrink-0" />
                    Tài khoản bị khóa
                </h3>
                <p class="mt-2 text-sm text-red-800"><strong>Lý do:</strong> {{ $user->banned_reason }}</p>
                <p class="mt-1 text-xs text-red-700">
                    Khóa bởi: {{ $user->banner?->email ?? 'N/A' }}<br>
                    Lúc: {{ $user->banned_at->format('d/m/Y H:i') }}
                </p>
                <form method="POST" action="{{ route('admin.users.unban', $user) }}"
                      onsubmit="return confirm('Mở khóa tài khoản này?')" class="mt-4">
                    @csrf
                    <button class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm text-white hover:bg-green-700">
                        <x-heroicon-o-lock-open class="h-4 w-4" />
                        Mở khóa tài khoản
                    </button>
                </form>
            </div>
        @else
            <div class="rounded-xl border border-neutral-200 bg-white p-6">
                <h3 class="text-lg font-semibold">Trạng thái</h3>
                <p class="mt-2 flex items-center gap-1.5 text-sm text-green-600">
                    <x-heroicon-s-check-circle class="h-5 w-5 flex-shrink-0" />
                    Hoạt động bình thường
                </p>

                @if (!$user->isAdmin() && !$user->is(auth()->user()))
                    <button onclick="showBanModal()"
                            class="mt-4 inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700">
                        <x-heroicon-o-no-symbol class="h-3 w-3" />
                        Khóa tài khoản
                    </button>
                @endif
            </div>
        @endif

        <!-- Subscription Info -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6">
            <h3 class="flex items-center gap-2 text-lg font-semibold">
                <x-heroicon-o-credit-card class="h-5 w-5" />
                Gói hiện tại
            </h3>
            <p class="mt-2 text-2xl font-bold text-blue-600">{{ $quota->planFor($user)->name }}</p>

            @if ($user->subscriptions->first())
                <div class="mt-3 text-sm text-neutral-600">
                    <p>Hết hạn: {{ $user->subscriptions->first()->ends_at->format('d/m/Y') }}</p>
                    <p>Trạng thái: <span class="font-medium">{{ $user->subscriptions->first()->status }}</span></p>
                </div>
            @endif
        </div>

        <!-- Quick Stats -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6">
            <h3 class="flex items-center gap-2 text-lg font-semibold">
                <x-heroicon-o-chart-bar class="h-5 w-5" />
                Thống kê
            </h3>
            <div class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-neutral-600">Videos</span>
                    <span class="font-medium">{{ $user->videos()->count() }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-neutral-600">Folders</span>
                    <span class="font-medium">{{ $user->folders()->count() }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-neutral-600">Tham gia</span>
                    <span class="font-medium">{{ $user->created_at->format('d/m/Y') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ban Modal -->
@if (!$user->isBanned() && !$user->isAdmin() && !$user->is(auth()->user()))
<div id="banModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
        <h2 class="text-xl font-semibold">Khóa tài khoản</h2>
        <p class="mt-2 text-sm text-neutral-600">Email: <span class="font-medium">{{ $user->email }}</span></p>
        <form method="POST" action="{{ route('admin.users.ban', $user) }}" class="mt-4">
            @csrf
            <label class="block text-sm font-medium">Lý do khóa <span class="text-red-600">*</span></label>
            <textarea name="reason" required maxlength="500" rows="4"
                      class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"
                      placeholder="VD: Đăng nội dung vi phạm pháp luật, khiêu dâm, bạo lực..."></textarea>
            <div class="mt-4 flex gap-2">
                <button type="button" onclick="closeBanModal()"
                        class="flex-1 rounded-lg border border-neutral-300 px-4 py-2 text-sm hover:bg-neutral-50">
                    Hủy
                </button>
                <button type="submit"
                        class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700">
                    Khóa tài khoản
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showBanModal() {
    document.getElementById('banModal').classList.remove('hidden');
    document.getElementById('banModal').classList.add('flex');
}

function closeBanModal() {
    document.getElementById('banModal').classList.add('hidden');
    document.getElementById('banModal').classList.remove('flex');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeBanModal();
});
</script>
@endif

@endsection
