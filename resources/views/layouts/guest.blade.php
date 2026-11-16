<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<section class="flex min-h-screen flex-col items-center justify-center bg-neutral-100 px-4 py-16 dark:bg-neutral-950">
    <div class="w-full max-w-sm">
        <div class="rounded-2xl bg-white p-6 shadow-xs ring-1 ring-neutral-950/10 sm:p-8 dark:bg-neutral-900 dark:ring-neutral-800">
            <span class="flex size-10 items-center justify-center rounded-xl bg-neutral-950 text-white dark:bg-neutral-50 dark:text-neutral-950">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83z" /><path d="M2 12a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 12" /><path d="M2 17a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 17" /></svg>
            </span>

            @yield('content')
        </div>

        <p class="mt-6 text-center text-sm text-neutral-600 dark:text-neutral-400">@yield('footer')</p>
    </div>
</section>

<script>
    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.getAttribute('aria-controls'));
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', show);
            button.setAttribute('aria-label', show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
        });
    });
</script>
</body>
</html>
