@php
    $icons = [
        'home' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
        'users' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'plans' => '<path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>',
        'accounts' => '<path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/>',
        'r2' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/>',
        'settings' => '<path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>',
        'back' =>'<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
    ];
    $nav = [
        ['Tổng quan', 'admin.dashboard', 'home'],
        ['Người dùng', 'admin.users.*', 'users'],
        ['Quản lý gói', 'admin.plans.*', 'plans'],
        ['Cấu hình upload', 'admin.upload-configs.*', 'accounts'],
        ['Cài đặt hệ thống', 'admin.settings.*', 'settings'],
    ];
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Quản trị') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-neutral-100 text-neutral-900">
<div class="flex min-h-screen">
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-30 flex w-56 -translate-x-full flex-col border-r border-neutral-200 bg-white transition-transform lg:translate-x-0">
        <a href="{{ route('admin.dashboard') }}" class="flex h-14 items-center px-6 text-lg font-semibold text-blue-600">{{ config('app.name') }}</a>
        <nav class="flex-1 space-y-1 px-2 py-2 text-sm">
            @foreach ($nav as [$label, $pattern, $icon])
                @php $active = request()->routeIs($pattern); @endphp
                <a href="{{ route(str_replace('.*', '.index', $pattern)) }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 transition-colors {{ $active ? 'bg-blue-50 text-blue-600' : 'text-neutral-700 hover:bg-neutral-50' }}">
                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$icon] !!}</svg>
                    {{ $label }}
                </a>
            @endforeach
        </nav>
        <div class="border-t border-neutral-200 p-2 text-sm">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-neutral-700 hover:bg-neutral-50">
                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons['back'] !!}</svg>
                Về trang người dùng
            </a>
        </div>
    </aside>

    <div id="backdrop" class="fixed inset-0 z-20 hidden bg-black/30 lg:hidden"></div>

    <div class="flex min-w-0 flex-1 flex-col lg:pl-56">
        <header class="flex h-14 items-center justify-between border-b border-neutral-200 bg-white px-4">
            <button id="sidebar-toggle" type="button" aria-label="Mở menu" class="flex size-9 cursor-pointer items-center justify-center rounded-lg text-neutral-600 hover:bg-neutral-100 lg:hidden">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h18"/></svg>
            </button>
            <span class="hidden lg:block"></span>
            <form method="POST" action="{{ route('logout') }}" class="flex items-center gap-3 text-sm">
                @csrf
                <span class="flex size-8 items-center justify-center rounded-full bg-blue-500 text-sm text-white">{{ mb_strtolower(mb_substr(auth()->user()->email, 0, 1)) }}</span>
                <span class="hidden sm:inline">{{ auth()->user()->email }}</span>
                <button class="text-neutral-500 hover:text-neutral-950 hover:underline">Đăng xuất</button>
            </form>
        </header>

        <main class="flex-1 p-4">
            <div class="min-h-full rounded-xl bg-white p-6 sm:p-8">
                @if (session('status'))
                    <div class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-800 ring-1 ring-green-600/20 ring-inset">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 ring-1 ring-red-600/20 ring-inset">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>
</div>

<script>
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('backdrop');
    const toggle = (open) => {
        sidebar.classList.toggle('-translate-x-full', !open);
        backdrop.classList.toggle('hidden', !open);
    };
    document.getElementById('sidebar-toggle').addEventListener('click', () => toggle(sidebar.classList.contains('-translate-x-full')));
    backdrop.addEventListener('click', () => toggle(false));
</script>
</body>
</html>
