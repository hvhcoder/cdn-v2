@php
    $icons = [
        'home' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
        'film' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 3v18M17 3v18M3 8h4M3 12h18M3 16h4M17 8h4M17 16h4"/>',
        'folder' => '<path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.7-.9l-.8-1.2A2 2 0 0 0 7.9 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z"/>',
        'upload' => '<path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M20 16v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3"/>',
        'crown' => '<path d="m2 8 4.5 4L12 4l5.5 8L22 8l-2 11H4z"/>',
        'tag' => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.2"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
    ];
    $user = auth()->user();
    $nav = [
        ['Tổng quan', route('dashboard'), request()->routeIs('dashboard'), 'home'],
        ['Video của tôi', route('videos.index'), request()->routeIs('videos.*') && ! request()->routeIs('videos.create'), 'film'],
        ['Thư mục', route('folders.index'), request()->routeIs('folders.*'), 'folder'],
        ['Tải lên video', route('videos.create'), request()->routeIs('videos.create'), 'upload'],
        ['Gói của tôi', route('subscription'), request()->routeIs('subscription'), 'crown'],
        ['Bảng giá', route('pricing'), request()->routeIs('pricing'), 'tag'],
    ];
    $svg = fn (string $name, string $class = 'size-[18px]') => '<svg class="'.$class.' shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$icons[$name].'</svg>';
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Trang chủ') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
@auth
    <div class="flex min-h-screen">
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-30 flex w-64 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform lg:translate-x-0">
            <a href="{{ route('dashboard') }}" class="flex h-16 items-center gap-3 px-5">
                <span class="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-sm">
                    <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10-6.5a1 1 0 0 0 0-1.72l-10-6.5A1 1 0 0 0 8 5.5z"/></svg>
                </span>
                <span class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</span>
            </a>

            <nav class="flex-1 space-y-1 px-3 py-3 text-sm font-medium">
                @foreach ($nav as [$label, $url, $active, $icon])
                    <a href="{{ $url }}" class="group relative flex items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ $active ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                        @if ($active)<span class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-blue-600"></span>@endif
                        {!! $svg($icon) !!}
                        {{ $label }}
                    </a>
                @endforeach
                @if ($user->isAdmin())
                    <div class="my-3 border-t border-slate-100"></div>
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900">
                        {!! $svg('shield') !!} Trang quản trị
                    </a>
                @endif
            </nav>

            <div class="border-t border-slate-100 p-3">
                <div class="flex items-center gap-3 rounded-xl p-2">
                    @if ($user->avatar)
                        <img src="{{ $user->avatar }}" alt="" referrerpolicy="no-referrer" class="size-9 rounded-full object-cover">
                    @else
                        <span class="flex size-9 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold uppercase text-white">{{ mb_substr($user->name, 0, 1) }}</span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $user->name }}</p>
                        <p class="truncate text-xs text-slate-500">
                            @if ($user->isAdmin()) Admin @elseif ($user->isVip()) <span class="font-semibold text-amber-600">VIP</span> @else Thành viên @endif
                        </p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button title="Đăng xuất" class="flex size-8 cursor-pointer items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900">
                            {!! $svg('logout') !!}
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div id="backdrop" class="fixed inset-0 z-20 hidden bg-slate-900/30 backdrop-blur-[1px] lg:hidden"></div>

        <div class="flex min-w-0 flex-1 flex-col lg:pl-64">
            <header class="sticky top-0 z-10 flex h-14 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur lg:hidden">
                <button id="sidebar-toggle" type="button" aria-label="Mở menu" class="flex size-9 cursor-pointer items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100">
                    {!! $svg('menu', 'size-5') !!}
                </button>
                <span class="font-semibold">{{ config('app.name') }}</span>
            </header>

            <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                @include('layouts.partials.flash')
                @yield('content')
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
@else
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 text-sm sm:px-6 lg:px-8">
            <a href="{{ route('pricing') }}" class="flex items-center gap-2 text-base font-semibold">
                <span class="flex size-8 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 text-white">
                    <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10-6.5a1 1 0 0 0 0-1.72l-10-6.5A1 1 0 0 0 8 5.5z"/></svg>
                </span>
                {{ config('app.name') }}
            </a>
            <div class="flex items-center gap-4">
                <a href="{{ route('pricing') }}" class="text-slate-600 hover:text-slate-900">Bảng giá</a>
                <a href="{{ route('login') }}" class="rounded-lg bg-slate-900 px-3.5 py-2 font-medium text-white hover:bg-slate-800">Đăng nhập</a>
            </div>
        </div>
    </header>
    <main class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        @include('layouts.partials.flash')
        @yield('content')
    </main>
@endauth
</body>
</html>
