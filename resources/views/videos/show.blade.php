@extends('layouts.app')

@section('title', $video->title)

@php
    $iconAttrs = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
    $embedCode = $payload['embed_url'] ? '<iframe src="'.$payload['embed_url'].'" width="640" height="360" frameborder="0" allow="autoplay; fullscreen" allowfullscreen></iframe>' : null;
@endphp

@section('content')
<nav class="mb-4 text-sm text-slate-500">
    <a href="{{ route('videos.index') }}" class="hover:text-slate-900">Video của tôi</a>
    @foreach ($breadcrumb as $crumb)
        <span class="mx-1.5">/</span>
        <a href="{{ route('videos.index', ['folder' => $crumb->id]) }}" class="hover:text-slate-900">{{ $crumb->name }}</a>
    @endforeach
    <span class="mx-1.5">/</span>
    <span class="text-slate-700">{{ \Illuminate\Support\Str::limit($video->title, 50) }}</span>
</nav>

<div class="flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
        <h1 class="truncate text-2xl font-semibold tracking-tight">{{ $video->title }}</h1>
        <div class="mt-2 flex items-center gap-3 text-sm text-slate-500">
            <x-video-status :video="$video" id="status-badge" />
            <span>Tạo {{ $video->created_at->diffForHumans() }}</span>
        </div>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('videos.edit', $video) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium transition hover:bg-slate-50">
            <svg class="size-4" {!! $iconAttrs !!}><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg> Sửa
        </a>
        @if ($video->status !== 'processing')
            <form method="POST" action="{{ route('videos.destroy', $video) }}" onsubmit="return confirm('Xóa video này? Hành động không thể hoàn tác.')">
                @csrf @method('DELETE')
                <button class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50">
                    <svg class="size-4" {!! $iconAttrs !!}><path d="M3 6h18"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="m19 6-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg> Xóa
                </button>
            </form>
        @endif
    </div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    {{-- Player / trạng thái --}}
    <section class="lg:col-span-2">
        @if ($payload['embed_url'])
            <div class="overflow-hidden rounded-2xl bg-black shadow-lg ring-1 ring-slate-900/10">
                <div class="aspect-video">
                    <iframe src="{{ $payload['embed_url'] }}" class="size-full" allow="autoplay; fullscreen" allowfullscreen loading="lazy"></iframe>
                </div>
            </div>
        @else
            <div id="progress-card" class="rounded-2xl bg-white p-8 text-center shadow-xs ring-1 ring-slate-200/70">
                @if ($video->status === 'processing')
                    <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                        <svg class="size-7 animate-spin" {!! $iconAttrs !!}><path d="M21 12a9 9 0 1 1-6.2-8.55"/></svg>
                    </span>
                    <p class="mt-4 font-medium">Video đang được xử lý</p>
                    <p id="progress-text" class="mt-1 text-sm text-slate-500">{{ $payload['segments_total'] ? "{$payload['segments_uploaded']}/{$payload['segments_total']} phân đoạn" : 'Đang chuẩn bị…' }}</p>
                    <div class="mx-auto mt-4 h-2 max-w-sm overflow-hidden rounded-full bg-slate-100"><div id="progress-bar" class="h-full rounded-full bg-blue-500 transition-all" style="width: {{ $payload['progress'] }}%"></div></div>
                    <p class="mt-4 text-xs text-slate-400">Trang tự cập nhật, bạn có thể rời đi và quay lại sau.</p>
                @elseif ($video->status === 'failed')
                    <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-red-50 text-red-600">
                        <svg class="size-7" {!! $iconAttrs !!}><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
                    </span>
                    <p class="mt-4 font-medium">Xử lý video thất bại</p>
                    <p class="mx-auto mt-1 max-w-md text-sm text-red-600">{{ $video->error_message }}</p>
                    @if ($canUpload)
                        <button id="retry" type="button" class="mt-5 cursor-pointer rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">Xử lý lại</button>
                    @endif
                @else
                    <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                        <svg class="size-7" {!! $iconAttrs !!}><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M20 16v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3"/></svg>
                    </span>
                    <p class="mt-4 font-medium">Video chưa được tải lên xong</p>
                    <p class="mt-1 text-sm text-slate-500">Lần tải lên này chưa hoàn tất. Bạn có thể xử lý lại nếu file đã nằm trên hệ thống, hoặc xóa và tải lên lại.</p>
                    @if ($canUpload)
                        <button id="retry" type="button" class="mt-5 cursor-pointer rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">Xử lý lại</button>
                    @endif
                @endif
                <p id="action-error" class="mt-3 hidden text-sm text-red-600"></p>
            </div>
        @endif
    </section>

    {{-- Thông tin & link --}}
    <aside class="space-y-6">
        <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-slate-200/70">
            <h2 class="font-semibold">Thông tin</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Dung lượng</dt><dd class="font-medium">{{ $video->formattedSize() }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Thời lượng</dt><dd class="font-medium">{{ $video->formattedDuration() }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Số phân đoạn</dt><dd class="font-medium">{{ $video->segment_count ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Độ dài mỗi đoạn</dt><dd class="font-medium">{{ $video->segment_time }} giây</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Ngày tạo</dt><dd class="font-medium">{{ $video->created_at->format('d/m/Y H:i') }}</dd></div>
            </dl>
        </section>

        @if ($tree->isNotEmpty() || $video->folder)
            <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-slate-200/70">
                <h2 class="font-semibold">Thư mục</h2>
                @if ($canManageFolders && $video->user_id === auth()->id())
                    <form method="POST" action="{{ route('videos.move') }}" class="mt-4 flex gap-2">
                        @csrf
                        <input type="hidden" name="video_ids[]" value="{{ $video->uuid }}">
                        <select name="folder_id" class="h-10 min-w-0 flex-1 rounded-lg bg-slate-50 px-3 text-sm outline-1 -outline-offset-1 outline-slate-200 focus:outline-2 focus:outline-blue-500">
                            <option value="">— Chưa phân loại —</option>
                            @foreach ($tree as $row)
                                <option value="{{ $row['folder']->id }}" @selected($video->folder_id === $row['folder']->id)>{!! str_repeat('&nbsp;&nbsp;', $row['depth']) !!}{{ $row['folder']->name }}</option>
                            @endforeach
                        </select>
                        <button class="h-10 shrink-0 cursor-pointer rounded-lg bg-slate-900 px-3 text-xs font-medium text-white transition hover:bg-slate-800">Chuyển</button>
                    </form>
                @else
                    <p class="mt-3 text-sm text-slate-600">{{ $video->folder?->name ?? 'Chưa phân loại' }}</p>
                @endif
            </section>
        @endif

        @if ($payload['m3u8_url'])
            <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-slate-200/70">
                <h2 class="font-semibold">Chia sẻ</h2>

                <label class="mt-4 block text-xs font-medium text-slate-500">Link m3u8</label>
                <div class="mt-1.5 flex gap-2">
                    <input readonly value="{{ $payload['m3u8_url'] }}" class="h-10 min-w-0 flex-1 rounded-lg bg-slate-50 px-3 text-xs outline-1 -outline-offset-1 outline-slate-200" onfocus="this.select()">
                    <button type="button" data-copy="{{ $payload['m3u8_url'] }}" class="h-10 shrink-0 cursor-pointer rounded-lg bg-slate-900 px-3 text-xs font-medium text-white transition hover:bg-slate-800">Sao chép</button>
                </div>

                <label class="mt-4 block text-xs font-medium text-slate-500">Mã nhúng (iframe)</label>
                <textarea readonly rows="3" class="mt-1.5 w-full rounded-lg bg-slate-50 p-3 text-xs outline-1 -outline-offset-1 outline-slate-200" onfocus="this.select()">{{ $embedCode }}</textarea>
                <button type="button" data-copy="{{ $embedCode }}" class="mt-2 w-full cursor-pointer rounded-lg bg-slate-900 py-2 text-xs font-medium text-white transition hover:bg-slate-800">Sao chép mã nhúng</button>

                <div class="mt-3 grid grid-cols-2 gap-2 text-center text-xs font-medium">
                    <a href="{{ $payload['embed_url'] }}" target="_blank" rel="noopener" class="rounded-lg border border-slate-200 py-2 transition hover:bg-slate-50">Mở trang phát</a>
                    <a href="{{ $payload['m3u8_url'] }}?download=1" class="rounded-lg border border-slate-200 py-2 transition hover:bg-slate-50">Tải file .m3u8</a>
                </div>
            </section>
        @endif
    </aside>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const api = (method, url) => fetch(url, { method, headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } });

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-copy]');
        if (!button) return;
        const original = button.textContent;
        try {
            await navigator.clipboard.writeText(button.dataset.copy);
            button.textContent = 'Đã sao chép';
            setTimeout(() => { button.textContent = original; }, 1200);
        } catch (error) { prompt('Sao chép:', button.dataset.copy); }
    });

    document.getElementById('retry')?.addEventListener('click', async (event) => {
        event.target.disabled = true;
        const response = await api('POST', @json(route('videos.process', $video)));
        if (response.ok) { location.reload(); return; }
        const data = await response.json().catch(() => ({}));
        const box = document.getElementById('action-error');
        box.textContent = data.message || 'Không thể xử lý lại video.';
        box.classList.remove('hidden');
        event.target.disabled = false;
    });

    @if ($video->status === 'processing')
    const poll = async () => {
        try {
            const response = await api('GET', @json(route('videos.status', $video)));
            if (!response.ok) return;
            const video = await response.json();
            if (video.status !== 'processing') { location.reload(); return; }
            document.getElementById('progress-text').textContent = video.segments_total ? `${video.segments_uploaded}/${video.segments_total} phân đoạn` : 'Đang chuẩn bị…';
            document.getElementById('progress-bar').style.width = video.progress + '%';
        } catch (error) { /* bỏ qua lỗi mạng tạm thời */ }
    };
    setInterval(poll, 3000);
    @endif
})();
</script>
@endsection
