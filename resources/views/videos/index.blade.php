@extends('layouts.app')

@section('title', 'Video của tôi')

@php
    $tabs = [null => 'Tất cả', 'completed' => 'Hoàn tất', 'processing' => 'Đang xử lý', 'failed' => 'Lỗi', 'pending' => 'Chờ upload'];
    $total = $counts->sum();
    $queryWithout = fn (string ...$drop) => collect(request()->query())->except(array_merge(['page'], $drop))->all();
    $iconAttrs = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
    $currentId = $filters['folder'];
    $heading = $currentFolder?->name ?? ($currentId === 'none' ? 'Chưa phân loại' : 'Video của tôi');
@endphp

@section('content')
@if ($currentFolder)
    <nav class="mb-3 flex flex-wrap items-center gap-1.5 text-sm text-slate-500">
        <a href="{{ route('videos.index') }}" class="hover:text-slate-900">Video của tôi</a>
        @foreach ($breadcrumb as $crumb)
            <span>/</span>
            @if ($loop->last)
                <span class="font-medium text-slate-800">{{ $crumb->name }}</span>
            @else
                <a href="{{ route('videos.index', ['folder' => $crumb->id]) }}" class="hover:text-slate-900">{{ $crumb->name }}</a>
            @endif
        @endforeach
    </nav>
@endif

<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ $heading }}</h1>
        <p class="mt-1 text-sm text-slate-500">
            @if ($currentFolder || $currentId === 'none') {{ $videos->total() }} video trong mục này · @else {{ $total }} video · @endif
            chọn nhiều video để chuyển thư mục hoặc sao chép link hàng loạt.
        </p>
    </div>
    @if ($canUpload)
        <a href="{{ route('videos.create', $currentFolder ? ['folder' => $currentFolder->id] : []) }}" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm shadow-blue-600/20 transition hover:bg-blue-700">
            <svg class="size-4" {!! $iconAttrs !!}><path d="M12 5v14M5 12h14"/></svg>
            Tải video lên
        </a>
    @endif
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)]">
    {{-- Cây thư mục --}}
    <aside class="lg:sticky lg:top-6 lg:self-start">
        <div class="rounded-2xl bg-white p-2.5 shadow-xs ring-1 ring-slate-200/70">
            <div class="flex items-center justify-between px-2.5 pb-1.5 pt-1.5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Thư mục</p>
                <a href="{{ route('folders.index') }}" class="text-xs font-medium text-blue-600 hover:text-blue-700">Quản lý</a>
            </div>
            <nav class="max-h-80 space-y-0.5 overflow-y-auto text-sm">
                <a href="{{ route('videos.index') }}" class="flex items-center justify-between rounded-lg px-2.5 py-2 transition {{ $currentId === null ? 'bg-blue-50 font-medium text-blue-700' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span>Tất cả video</span><span class="text-xs text-slate-400">{{ $total }}</span>
                </a>
                <a href="{{ route('videos.index', ['folder' => 'none']) }}" class="flex items-center justify-between rounded-lg px-2.5 py-2 transition {{ $currentId === 'none' ? 'bg-blue-50 font-medium text-blue-700' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span>Chưa phân loại</span><span class="text-xs text-slate-400">{{ $unfiledCount }}</span>
                </a>
                @foreach ($tree as $row)
                    @php $active = $currentId === $row['folder']->id; @endphp
                    <a href="{{ route('videos.index', ['folder' => $row['folder']->id]) }}" class="flex items-center gap-2 rounded-lg py-2 pr-2.5 transition {{ $active ? 'bg-blue-50 font-medium text-blue-700' : 'text-slate-600 hover:bg-slate-50' }}" style="padding-left: {{ 0.625 + $row['depth'] * 0.9 }}rem">
                        <x-folder-icon :color="$row['folder']->color" size="size-4" />
                        <span class="min-w-0 flex-1 truncate">{{ $row['folder']->name }}</span>
                        <span class="text-xs text-slate-400">{{ $row['videos_count'] }}</span>
                    </a>
                @endforeach
            </nav>
            @if ($tree->isEmpty())
                <p class="px-2.5 pb-2 pt-1 text-xs text-slate-400">Chưa có thư mục nào.</p>
            @endif
        </div>
    </aside>

    <div class="min-w-0">
        {{-- Thư mục con --}}
        @if ($subfolders->isNotEmpty() && ! $filters['q'] && ! $filters['status'])
            <div class="mb-4 flex flex-wrap gap-2">
                @foreach ($subfolders as $row)
                    <a href="{{ route('videos.index', ['folder' => $row['folder']->id]) }}" class="flex items-center gap-2 rounded-xl bg-white px-3.5 py-2 text-sm shadow-xs ring-1 ring-slate-200/70 transition hover:ring-blue-300">
                        <x-folder-icon :color="$row['folder']->color" />
                        <span class="font-medium">{{ $row['folder']->name }}</span>
                        <span class="text-xs text-slate-400">{{ $row['videos_count'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="rounded-2xl bg-white shadow-xs ring-1 ring-slate-200/70">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4">
                <div class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1 text-sm">
                    @foreach ($tabs as $key => $label)
                        @php $active = ($filters['status'] ?? null) === $key; @endphp
                        <a href="{{ route('videos.index', $queryWithout('status') + ($key ? ['status' => $key] : [])) }}"
                           class="rounded-lg px-3 py-1.5 font-medium transition {{ $active ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                            {{ $label }}
                            <span class="ml-1 text-xs {{ $active ? 'text-blue-600' : 'text-slate-400' }}">{{ $key ? ($counts[$key] ?? 0) : $total }}</span>
                        </a>
                    @endforeach
                </div>

                <form method="GET" action="{{ route('videos.index') }}" class="flex flex-wrap items-center gap-2">
                    @if ($filters['status']) <input type="hidden" name="status" value="{{ $filters['status'] }}"> @endif
                    @if ($currentId) <input type="hidden" name="folder" value="{{ $currentId }}"> @endif
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" {!! $iconAttrs !!}><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                        <input name="q" value="{{ $filters['q'] }}" placeholder="Tìm theo tiêu đề…" class="h-10 w-48 rounded-xl bg-slate-50 pl-9 pr-3 text-sm outline-1 -outline-offset-1 outline-slate-200 placeholder:text-slate-400 focus:bg-white focus:outline-2 focus:-outline-offset-2 focus:outline-blue-500">
                    </div>
                    <select name="sort" onchange="this.form.submit()" class="h-10 rounded-xl bg-slate-50 px-3 text-sm outline-1 -outline-offset-1 outline-slate-200 focus:outline-2 focus:outline-blue-500">
                        <option value="newest" @selected($filters['sort'] === 'newest')>Mới nhất</option>
                        <option value="oldest" @selected($filters['sort'] === 'oldest')>Cũ nhất</option>
                        <option value="title" @selected($filters['sort'] === 'title')>Theo tên A–Z</option>
                        <option value="size" @selected($filters['sort'] === 'size')>Dung lượng lớn nhất</option>
                    </select>
                    <button class="h-10 rounded-xl bg-slate-900 px-4 text-sm font-medium text-white transition hover:bg-slate-800">Tìm</button>
                </form>
            </div>

            @if ($videos->count())
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-500">
                            <tr class="border-b border-slate-100">
                                <th class="w-10 py-3 pl-5"><input id="select-all" type="checkbox" class="size-4 cursor-pointer rounded border-slate-300" aria-label="Chọn tất cả"></th>
                                <th class="px-3 py-3 font-medium">Video</th>
                                <th class="hidden px-4 py-3 font-medium md:table-cell">Dung lượng</th>
                                <th class="hidden px-4 py-3 font-medium md:table-cell">Thời lượng</th>
                                <th class="px-4 py-3 font-medium">Trạng thái</th>
                                <th class="hidden px-4 py-3 font-medium xl:table-cell">Ngày tạo</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody id="rows">
                            @foreach ($videos as $video)
                                @php $progress = $video->segment_count ? (int) floor($video->uploaded_segments_count / $video->segment_count * 100) : 0; @endphp
                                <tr class="border-b border-slate-100 align-middle transition-colors last:border-0 hover:bg-slate-50/70"
                                    data-uuid="{{ $video->uuid }}" data-status="{{ $video->status }}" data-url="{{ route('videos.status', $video) }}"
                                    @if ($video->status === 'completed') data-m3u8="{{ route('m3u8', $video) }}" data-embed="{{ route('embed', $video) }}" @endif>
                                    <td class="py-3.5 pl-5"><input type="checkbox" value="{{ $video->uuid }}" data-select class="size-4 cursor-pointer rounded border-slate-300" aria-label="Chọn video"></td>
                                    <td class="px-3 py-3.5">
                                        <a href="{{ route('videos.show', $video) }}" class="flex items-center gap-3">
                                            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-slate-100 to-slate-200 text-slate-500">
                                                <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10-6.5a1 1 0 0 0 0-1.72l-10-6.5A1 1 0 0 0 8 5.5z"/></svg>
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block max-w-[15rem] truncate font-medium text-slate-900 hover:text-blue-700">{{ $video->title }}</span>
                                                <span class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500">
                                                    @if ($video->folder && ! $currentFolder)
                                                        <x-folder-icon :color="$video->folder->color" size="size-3.5" />{{ $video->folder->name }}
                                                    @endif
                                                    <span class="md:hidden">{{ $video->formattedSize() }} · {{ $video->formattedDuration() }}</span>
                                                </span>
                                            </span>
                                        </a>
                                    </td>
                                    <td class="hidden px-4 py-3.5 text-slate-600 md:table-cell">{{ $video->formattedSize() }}</td>
                                    <td class="hidden px-4 py-3.5 text-slate-600 md:table-cell">{{ $video->formattedDuration() }}</td>
                                    <td class="px-4 py-3.5" data-cell="status">
                                        <x-video-status :video="$video" />
                                        @if ($video->status === 'processing')
                                            <p class="mt-1.5 text-xs text-slate-500" data-cell="detail">{{ $video->segment_count ? "{$video->uploaded_segments_count}/{$video->segment_count} phân đoạn" : 'Đang chuẩn bị…' }}</p>
                                            <div class="mt-1 h-1 w-32 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-blue-500 transition-all" style="width: {{ $progress }}%" data-cell="bar"></div></div>
                                        @elseif ($video->status === 'failed' && $video->error_message)
                                            <p class="mt-1.5 max-w-[14rem] truncate text-xs text-red-600" title="{{ $video->error_message }}">{{ $video->error_message }}</p>
                                        @endif
                                    </td>
                                    <td class="hidden px-4 py-3.5 text-slate-500 xl:table-cell" title="{{ $video->created_at->format('d/m/Y H:i') }}">{{ $video->created_at->diffForHumans() }}</td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center justify-end gap-1">
                                            @if ($video->status === 'completed')
                                                <button type="button" data-copy="{{ route('m3u8', $video) }}" title="Sao chép link m3u8" class="flex size-8 cursor-pointer items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900">
                                                    <svg class="size-4" {!! $iconAttrs !!}><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg>
                                                </button>
                                            @endif
                                            <a href="{{ route('videos.show', $video) }}" title="Xem chi tiết" class="flex size-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900">
                                                <svg class="size-4" {!! $iconAttrs !!}><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                            </a>
                                            <a href="{{ route('videos.edit', $video) }}" title="Sửa tiêu đề" class="flex size-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900">
                                                <svg class="size-4" {!! $iconAttrs !!}><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                            </a>
                                            @if ($video->status !== 'processing')
                                                <form method="POST" action="{{ route('videos.destroy', $video) }}" onsubmit="return confirm('Xóa video này? Hành động không thể hoàn tác.')">
                                                    @csrf @method('DELETE')
                                                    <button title="Xóa" class="flex size-8 cursor-pointer items-center justify-center rounded-lg text-slate-500 transition hover:bg-red-50 hover:text-red-600">
                                                        <svg class="size-4" {!! $iconAttrs !!}><path d="M3 6h18"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="m19 6-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4 text-sm text-slate-500">
                    <p>Hiển thị {{ $videos->firstItem() }}–{{ $videos->lastItem() }} trong {{ $videos->total() }} video</p>
                    @if ($videos->hasPages())
                        <div>{{ $videos->links() }}</div>
                    @endif
                </div>
            @else
                <div class="px-5 py-16 text-center">
                    <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <svg class="size-7" {!! $iconAttrs !!}><rect width="18" height="18" x="3" y="3" rx="2"/><path d="m10 8 6 4-6 4z"/></svg>
                    </span>
                    @if ($filters['q'] || $filters['status'])
                        <p class="mt-4 font-medium">Không tìm thấy video phù hợp</p>
                        <a href="{{ route('videos.index', $currentId ? ['folder' => $currentId] : []) }}" class="mt-2 inline-block text-sm font-medium text-blue-600 hover:text-blue-700">Xóa bộ lọc</a>
                    @elseif ($currentFolder || $currentId === 'none')
                        <p class="mt-4 font-medium">Mục này chưa có video</p>
                        <p class="mt-1 text-sm text-slate-500">Chọn video ở mục "Tất cả video" rồi chuyển vào đây.</p>
                    @else
                        <p class="mt-4 font-medium">Chưa có video nào</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $canUpload ? 'Tải video đầu tiên của bạn lên để bắt đầu.' : 'Các video của bạn sẽ xuất hiện ở đây.' }}</p>
                        @if ($canUpload)
                            <a href="{{ route('videos.create') }}" class="mt-4 inline-block rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Tải video lên</a>
                        @endif
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Thanh thao tác hàng loạt --}}
<div id="bulk-bar" class="fixed inset-x-0 bottom-5 z-40 hidden justify-center px-4">
    <form id="bulk-form" method="POST" action="{{ route('videos.move') }}" class="flex max-w-full flex-wrap items-center gap-3 rounded-2xl bg-slate-900 px-4 py-3 text-sm text-white shadow-2xl">
        @csrf
        <span><span id="bulk-count" class="font-semibold">0</span> video đã chọn</span>
        <span class="hidden h-5 w-px bg-white/20 sm:block"></span>

        @if ($canManageFolders)
            <select name="folder_id" class="h-9 max-w-48 rounded-lg bg-white/10 px-2.5 text-sm text-white outline-none">
                <option value="" class="text-slate-900">Chuyển vào…</option>
                <option value="" class="text-slate-900" data-none>— Ra ngoài thư mục —</option>
                @foreach ($tree as $row)
                    <option value="{{ $row['folder']->id }}" class="text-slate-900">{!! str_repeat('&nbsp;&nbsp;', $row['depth']) !!}{{ $row['folder']->name }}</option>
                @endforeach
            </select>
            <button id="bulk-move" type="submit" class="h-9 cursor-pointer rounded-lg bg-blue-600 px-3.5 font-medium transition hover:bg-blue-500">Chuyển</button>
        @endif

        <button type="button" data-bulk-copy="m3u8" class="h-9 cursor-pointer rounded-lg bg-white/10 px-3.5 font-medium transition hover:bg-white/20">Sao chép m3u8</button>
        <button type="button" data-bulk-copy="embed" class="h-9 cursor-pointer rounded-lg bg-white/10 px-3.5 font-medium transition hover:bg-white/20">Sao chép link phát</button>
        <button type="button" id="bulk-clear" class="cursor-pointer text-white/70 underline hover:text-white">Bỏ chọn</button>
    </form>
</div>

<div id="toast" class="pointer-events-none fixed bottom-24 left-1/2 z-50 -translate-x-1/2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm text-white opacity-0 shadow-lg transition-opacity"></div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const $ = (id) => document.getElementById(id);
    const checks = () => [...document.querySelectorAll('[data-select]')];
    const selected = () => checks().filter((c) => c.checked);
    const toast = $('toast');

    const showToast = (message) => {
        toast.textContent = message;
        toast.classList.remove('opacity-0');
        setTimeout(() => toast.classList.add('opacity-0'), 2200);
    };

    function refreshBulk() {
        const chosen = selected();
        $('bulk-count').textContent = chosen.length;
        $('bulk-bar').classList.toggle('hidden', chosen.length === 0);
        $('bulk-bar').classList.toggle('flex', chosen.length > 0);
        if ($('select-all')) $('select-all').checked = chosen.length > 0 && chosen.length === checks().length;
    }

    document.addEventListener('change', (event) => {
        if (event.target.matches('[data-select]')) refreshBulk();
        if (event.target.id === 'select-all') { checks().forEach((c) => { c.checked = event.target.checked; }); refreshBulk(); }
    });

    $('bulk-clear')?.addEventListener('click', () => { checks().forEach((c) => { c.checked = false; }); refreshBulk(); });

    // Chuyển thư mục: gửi các video đã chọn dưới dạng video_ids[].
    $('bulk-form')?.addEventListener('submit', (event) => {
        const select = event.target.querySelector('select[name="folder_id"]');
        if (select && select.selectedIndex === 0) { event.preventDefault(); showToast('Hãy chọn thư mục đích.'); return; }
        event.target.querySelectorAll('input[name="video_ids[]"]').forEach((el) => el.remove());
        selected().forEach((c) => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'video_ids[]'; input.value = c.value;
            event.target.appendChild(input);
        });
    });

    // Sao chép link của các video đã chọn (chỉ video đã hoàn tất mới có link).
    document.querySelectorAll('[data-bulk-copy]').forEach((button) => button.addEventListener('click', async () => {
        const key = button.dataset.bulkCopy;
        const links = selected().map((c) => c.closest('tr').dataset[key]).filter(Boolean);
        if (!links.length) { showToast('Các video đã chọn chưa hoàn tất nên chưa có link.'); return; }
        try {
            await navigator.clipboard.writeText(links.join('\n'));
            showToast(`Đã sao chép ${links.length} link`);
        } catch (error) { prompt('Sao chép link:', links.join('\n')); }
    }));

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-copy]');
        if (!button) return;
        try {
            await navigator.clipboard.writeText(button.dataset.copy);
            button.classList.add('text-emerald-600');
            setTimeout(() => button.classList.remove('text-emerald-600'), 1200);
        } catch (error) { prompt('Sao chép link:', button.dataset.copy); }
    });

    // Tự cập nhật tiến độ các video đang xử lý; khi xong thì tải lại trang để hiện đúng nút/hành động.
    const poll = async () => {
        const rows = [...document.querySelectorAll('tr[data-status="processing"]')];
        if (!rows.length) return;

        await Promise.all(rows.map(async (row) => {
            try {
                const response = await fetch(row.dataset.url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } });
                if (!response.ok) return;
                const video = await response.json();

                if (video.status !== 'processing') { location.reload(); return; }

                const detail = row.querySelector('[data-cell="detail"]');
                const bar = row.querySelector('[data-cell="bar"]');
                if (detail) detail.textContent = video.segments_total ? `${video.segments_uploaded}/${video.segments_total} phân đoạn` : 'Đang chuẩn bị…';
                if (bar) bar.style.width = video.progress + '%';
            } catch (error) { /* bỏ qua lỗi mạng tạm thời */ }
        }));
    };
    setInterval(poll, 3000);
})();
</script>
@endsection
