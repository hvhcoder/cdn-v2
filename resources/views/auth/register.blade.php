@extends('layouts.guest')

@section('title', 'Đăng ký')

@section('content')
    <h1 class="mt-6 text-2xl font-semibold tracking-tight text-neutral-950 dark:text-neutral-50">Tạo tài khoản {{ config('app.name') }}</h1>
    <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">Đăng ký để bắt đầu lưu trữ và phát video.</p>

    <x-auth.google-button>Đăng ký với Google</x-auth.google-button>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <x-auth.errors />

        <div>
            <label for="name" class="mb-1.5 block text-sm font-medium text-neutral-950 dark:text-neutral-50">Tên</label>
            <x-auth.input id="name" name="name" autocomplete="name" placeholder="Nguyễn Văn A" />
        </div>

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-neutral-950 dark:text-neutral-50">Email</label>
            <x-auth.input id="email" name="email" type="email" autocomplete="email" placeholder="ban@example.com" />
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-neutral-950 dark:text-neutral-50">Mật khẩu</label>
            <x-auth.password-input id="password" name="password" autocomplete="new-password" />
        </div>

        <div>
            <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-neutral-950 dark:text-neutral-50">Nhập lại mật khẩu</label>
            <x-auth.password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" :toggle="false" />
        </div>

        <button type="submit" class="mt-2 inline-flex h-10 w-full cursor-pointer items-center justify-center rounded-lg bg-zinc-950 px-4 text-sm font-medium text-white shadow-xs transition-colors duration-150 hover:bg-zinc-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zinc-950 active:translate-y-px dark:bg-zinc-50 dark:text-zinc-950 dark:hover:bg-zinc-200 dark:focus-visible:outline-zinc-50">
            Đăng ký
        </button>
    </form>
@endsection

@section('footer')
    Đã có tài khoản? <a href="{{ route('login') }}" class="rounded-sm font-medium text-zinc-950 underline decoration-zinc-950/30 underline-offset-4 transition-colors duration-150 hover:decoration-current dark:text-zinc-50 dark:decoration-zinc-50/30">Đăng nhập</a>
@endsection
