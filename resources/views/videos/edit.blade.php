@extends('layouts.app')

@section('title', 'Sửa video')

@section('content')
<nav class="mb-4 text-sm text-slate-500">
    <a href="{{ route('videos.index') }}" class="hover:text-slate-900">Video của tôi</a>
    <span class="mx-1.5">/</span>
    <a href="{{ route('videos.show', $video) }}" class="hover:text-slate-900">{{ \Illuminate\Support\Str::limit($video->title, 40) }}</a>
    <span class="mx-1.5">/</span>
    <span class="text-slate-700">Sửa</span>
</nav>

<h1 class="text-2xl font-semibold tracking-tight">Sửa video</h1>
<p class="mt-1 text-sm text-slate-500">Đổi tiêu đề hiển thị. Link m3u8 và embed không thay đổi.</p>

<form method="POST" action="{{ route('videos.update', $video) }}" class="mt-6 max-w-xl rounded-2xl bg-white p-6 shadow-xs ring-1 ring-slate-200/70">
    @csrf @method('PUT')

    <label for="title" class="mb-1.5 block text-sm font-medium">Tiêu đề</label>
    <input id="title" name="title" value="{{ old('title', $video->title) }}" required maxlength="255" autofocus
           class="block h-11 w-full rounded-xl bg-slate-50 px-3.5 text-sm outline-1 -outline-offset-1 outline-slate-200 focus:bg-white focus:outline-2 focus:-outline-offset-2 focus:outline-blue-500">
    @error('title') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror

    <div class="mt-6 flex gap-3">
        <button class="cursor-pointer rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">Lưu thay đổi</button>
        <a href="{{ route('videos.show', $video) }}" class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-medium transition hover:bg-slate-50">Hủy</a>
    </div>
</form>
@endsection
