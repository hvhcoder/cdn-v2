@extends('layouts.app')

@section('title', 'Tải video lên')

@php
    $fmtGb = fn (int $bytes) => rtrim(rtrim(number_format($bytes / 1024 ** 3, 2, '.', ''), '0'), '.');
    $queuePercent = min(100, (int) round($limits['queue_bytes'] / max(1, $limits['max_queue_bytes']) * 100));
@endphp

@section('content')
<nav class="mb-4 text-sm text-slate-500">
    <a href="{{ route('videos.index') }}" class="hover:text-slate-900">Video của tôi</a>
    <span class="mx-1.5">/</span>
    <span class="text-slate-700">Tải lên</span>
</nav>

<h1 class="text-2xl font-semibold tracking-tight">Tải video lên</h1>
<p class="mt-1 text-sm text-slate-500">
    Gói {{ $plan->name }} · {{ $usage['video_count'] }}/{{ $plan->max_videos ?? '∞' }} video ·
    {{ round($usage['storage_bytes'] / 1024 ** 3, 2) }}/{{ $plan->storage_limit_gb ?? '∞' }} GB
</p>

@if ($canUpload)
    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-slate-200/70">
            <p class="text-xs text-slate-500">Tối đa mỗi video</p>
            <p class="mt-1 text-lg font-semibold">{{ $fmtGb($limits['max_video_bytes']) }} GB</p>
            @if ($planMaxUploadBytes)<p class="text-xs text-slate-500">Gói của bạn: {{ $fmtGb($planMaxUploadBytes) }} GB</p>@endif
        </div>
        <div class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-slate-200/70">
            <p class="text-xs text-slate-500">Dung lượng hàng đợi hệ thống</p>
            <p class="mt-1 text-lg font-semibold">{{ $fmtGb($limits['queue_bytes']) }} / {{ $fmtGb($limits['max_queue_bytes']) }} GB</p>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-blue-500" style="width: {{ $queuePercent }}%"></div></div>
        </div>
        <div class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-slate-200/70">
            <p class="text-xs text-slate-500">Video đang trong hàng đợi</p>
            <p class="mt-1 text-lg font-semibold">{{ $limits['queue_videos'] }} / {{ $limits['max_queue_videos'] }}</p>
        </div>
    </div>

    @if ($canManageFolders && $tree->isNotEmpty())
        <div class="mt-4 flex flex-wrap items-center gap-3 rounded-2xl bg-white p-4 shadow-xs ring-1 ring-slate-200/70">
            <label for="upload-folder" class="text-sm font-medium">Lưu vào thư mục</label>
            <select id="upload-folder" class="h-10 min-w-56 rounded-xl bg-slate-50 px-3 text-sm outline-1 -outline-offset-1 outline-slate-200 focus:outline-2 focus:outline-blue-500">
                <option value="">— Chưa phân loại —</option>
                @foreach ($tree as $row)
                    <option value="{{ $row['folder']->id }}" @selected($defaultFolder?->id === $row['folder']->id)>{!! str_repeat('&nbsp;&nbsp;', $row['depth']) !!}{{ $row['folder']->name }}</option>
                @endforeach
            </select>
            <span class="text-xs text-slate-500">Áp dụng cho các file bạn thêm vào hàng đợi tiếp theo.</span>
        </div>
    @endif

    {{-- Header Graft method temporarily disabled --}}
    @if(false)
    <div class="mt-4 rounded-2xl bg-white p-4 shadow-xs ring-1 ring-slate-200/70">
        <label class="block text-sm font-medium mb-3">Phương thức mã hóa</label>
        <div class="space-y-2">
            <label class="flex items-start gap-3 cursor-pointer p-3 rounded-xl hover:bg-slate-50">
                <input type="radio" name="encoding_method" value="pixel" checked class="mt-0.5 h-4 w-4 text-blue-600">
                <div class="flex-1">
                    <div class="text-sm font-medium">Pixel Encoding (Mặc định)</div>
                    <div class="text-xs text-slate-500 mt-0.5">Nhúng TS vào pixel PNG với CRC checksum - An toàn, có checksum</div>
                </div>
            </label>
            <label class="flex items-start gap-3 cursor-pointer p-3 rounded-xl hover:bg-slate-50">
                <input type="radio" name="encoding_method" value="header-graft" class="mt-0.5 h-4 w-4 text-blue-600">
                <div class="flex-1">
                    <div class="text-sm font-medium">Header Graft (Thử nghiệm)</div>
                    <div class="text-xs text-slate-500 mt-0.5">PNG 1x1 minimal + raw TS - Nhanh hơn 40x, file nhỏ hơn</div>
                </div>
            </label>
        </div>
    </div>
    @endif

    <div id="dropzone" class="mt-4 cursor-pointer rounded-3xl border-2 border-dashed border-slate-300 bg-white px-6 py-14 text-center transition-colors hover:border-blue-400 hover:bg-blue-50/30">
        <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
            <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M20 16v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3"/></svg>
        </span>
        <p class="mt-4 font-medium">Kéo thả video vào đây hoặc bấm để chọn</p>
        <p class="mx-auto mt-1 max-w-xl text-sm text-slate-500">Chọn được nhiều file. Tải lên lần lượt, server xử lý song song nên không cần đợi từng video. Video được cắt thành các đoạn {{ $limits['segment_time'] }} giây.</p>
        <input id="file-input" type="file" accept="video/*,.mkv" multiple class="hidden">
    </div>

    <div id="queue-panel" class="mt-4 hidden rounded-2xl bg-white p-4 shadow-xs ring-1 ring-slate-200/70">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold">Hàng đợi tải lên</h2>
            <div class="flex items-center gap-4 text-xs">
                <a href="{{ route('videos.index') }}" class="font-medium text-blue-600 hover:text-blue-700">Xem danh sách video →</a>
                <button id="clear-finished" type="button" class="cursor-pointer text-slate-500 underline">Dọn mục đã xong / lỗi</button>
            </div>
        </div>
        <div id="overall" class="mb-4 hidden">
            <div class="mb-1 flex justify-between text-xs text-slate-500"><span id="overall-text"></span><span id="overall-percent"></span></div>
            <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div id="overall-bar" class="h-full rounded-full bg-blue-600 transition-all" style="width:0%"></div></div>
        </div>
        <ul id="queue" class="space-y-2"></ul>
    </div>
@else
    <div class="mt-6 rounded-2xl bg-amber-50 p-6 text-sm text-amber-900 ring-1 ring-amber-600/20">
        <p class="font-medium">Chỉ tài khoản VIP hoặc Admin mới được tải video lên.</p>
        <p class="mt-1">Liên hệ admin hoặc xem <a href="{{ route('pricing') }}" class="font-medium underline">bảng giá</a> để nâng cấp.</p>
    </div>
@endif

@if ($canUpload)
<script>
(() => {
    const MAX_VIDEO_BYTES = @json($limits['max_video_bytes']);
    const PLAN_MAX_BYTES = @json($planMaxUploadBytes);
    const PRESIGN_URL = @json(route('videos.presign'));
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const queue = [];
    let uploading = false;
    let nextId = 1;

    const $ = (id) => document.getElementById(id);
    const queueEl = $('queue');
    const panel = $('queue-panel');

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const formatSize = (bytes) => bytes >= 1024 ** 3 ? (bytes / 1024 ** 3).toFixed(2) + ' GB' : (bytes / 1024 ** 2).toFixed(1) + ' MB';
    const formatEta = (s) => !isFinite(s) ? '' : s >= 3600 ? `${Math.floor(s / 3600)} giờ ${Math.round(s % 3600 / 60)} phút` : s >= 60 ? `${Math.floor(s / 60)} phút ${Math.round(s % 60)} giây` : `${Math.round(s)} giây`;

    async function api(method, url, body) {
        const response = await fetch(url, {
            method,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const firstError = data.errors ? Object.values(data.errors)[0][0] : null;
            throw Object.assign(new Error(data.message || firstError || `Lỗi HTTP ${response.status}`), { status: response.status });
        }
        return data;
    }

    function putFile(url, file, item) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            item.xhr = xhr;
            item.startedAt = Date.now();
            xhr.open('PUT', url);
            xhr.upload.onprogress = (e) => {
                if (!e.lengthComputable) return;
                item.loaded = e.loaded;
                item.percent = Math.round(e.loaded / e.total * 100);
                item.speed = e.loaded / Math.max(0.5, (Date.now() - item.startedAt) / 1000);
                render();
            };
            xhr.onload = () => xhr.status >= 200 && xhr.status < 300 ? resolve() : reject(new Error(`Tải lên R2 thất bại (HTTP ${xhr.status}).`));
            xhr.onerror = () => reject(new Error('Không tải lên được R2 (kiểm tra mạng hoặc CORS của bucket).'));
            xhr.onabort = () => reject(Object.assign(new Error('Đã hủy tải lên.'), { cancelled: true }));
            xhr.send(file);
        });
    }

    const labels = { queued: 'Đang chờ', uploading: 'Đang tải lên', processing: 'Server đang xử lý', completed: 'Hoàn tất', failed: 'Lỗi' };
    const colors = { queued: 'text-slate-500', uploading: 'text-blue-700', processing: 'text-blue-700', completed: 'text-emerald-600', failed: 'text-red-600' };

    function render() {
        panel.classList.toggle('hidden', queue.length === 0);

        const open = queue.filter((i) => ['queued', 'uploading'].includes(i.status));
        const total = open.reduce((sum, i) => sum + i.file.size, 0);
        const done = open.reduce((sum, i) => sum + (i.status === 'uploading' ? i.loaded : 0), 0);
        $('overall').classList.toggle('hidden', open.length === 0);
        if (open.length) {
            const percent = total ? Math.round(done / total * 100) : 0;
            $('overall-text').textContent = `Còn ${open.length} file · ${formatSize(done)} / ${formatSize(total)}`;
            $('overall-percent').textContent = percent + '%';
            $('overall-bar').style.width = percent + '%';
        }

        queueEl.innerHTML = queue.map((item) => {
            const eta = item.status === 'uploading' && item.speed ? formatEta((item.file.size - item.loaded) / item.speed) : '';
            const server = item.server || {};
            const bar = item.status === 'uploading' ? item.percent : (item.status === 'processing' ? (server.progress || 0) : null);
            const buttons = [];
            if (item.status === 'queued') buttons.push(`<button type="button" data-remove="${item.id}" class="cursor-pointer underline">Bỏ khỏi hàng đợi</button>`);
            if (item.status === 'uploading') buttons.push(`<button type="button" data-cancel="${item.id}" class="cursor-pointer text-red-600 underline">Hủy</button>`);
            if (item.status === 'failed' && item.retryable) buttons.push(`<button type="button" data-requeue="${item.id}" class="cursor-pointer underline">Thử lại</button>`);
            if (item.status === 'failed') buttons.push(`<button type="button" data-remove="${item.id}" class="cursor-pointer underline">Xóa</button>`);
            if (item.uuid && ['processing', 'completed'].includes(item.status)) buttons.push(`<a href="/videos/${item.uuid}" class="font-medium text-blue-600">Xem video →</a>`);

            let state = labels[item.status];
            if (item.status === 'uploading') state += ` ${item.percent}%`;
            if (item.status === 'processing') state += server.segments_total ? ` · ${server.segments_uploaded}/${server.segments_total} phân đoạn` : ' · chuẩn bị…';

            return `
            <li class="rounded-xl border border-slate-200 p-3.5 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="truncate font-medium" title="${esc(item.file.name)}">${esc(item.file.name)}</span>
                    <span class="shrink-0 text-xs text-slate-500">${formatSize(item.file.size)}</span>
                </div>
                <div class="mt-1 flex flex-wrap items-center justify-between gap-2 text-xs">
                    <span class="${colors[item.status]}">${state}</span>
                    <span class="text-slate-500">${item.status === 'uploading' && item.speed ? `${(item.speed / 1048576).toFixed(1)} MB/s${eta ? ' · còn ' + eta : ''}` : ''}</span>
                </div>
                ${bar !== null ? `<div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-blue-600 transition-all" style="width:${bar}%"></div></div>` : ''}
                ${item.message ? `<p class="mt-1.5 text-xs text-red-600">${esc(item.message)}</p>` : ''}
                ${buttons.length ? `<div class="mt-2 flex gap-4 text-xs text-slate-600">${buttons.join('')}</div>` : ''}
            </li>`;
        }).join('');
    }

    queueEl.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        const find = (key) => queue.find((i) => i.id === Number(button.dataset[key]));

        if (button.dataset.remove) {
            const index = queue.findIndex((i) => i.id === Number(button.dataset.remove) && i.status !== 'uploading');
            if (index >= 0) queue.splice(index, 1);
        } else if (button.dataset.cancel) {
            find('cancel')?.xhr?.abort();
        } else if (button.dataset.requeue) {
            const item = find('requeue');
            if (item) { Object.assign(item, { status: 'queued', message: '', percent: 0, loaded: 0, speed: 0 }); pump(); }
        }
        render();
    });

    $('clear-finished').addEventListener('click', () => {
        for (let i = queue.length - 1; i >= 0; i--) if (['completed', 'failed'].includes(queue[i].status)) queue.splice(i, 1);
        render();
    });

    async function upload(item) {
        item.status = 'uploading';
        item.loaded = 0;
        item.percent = 0;
        render();

        const presign = await api('POST', PRESIGN_URL, {
            filename: item.file.name,
            size: item.file.size,
            folder_id: item.folderId || null,
            encoding_method: document.querySelector('input[name="encoding_method"]:checked')?.value || 'pixel'
        });
        item.uuid = presign.video;

        try {
            await putFile(presign.upload_url, item.file, item);
            await api('POST', `/videos/${presign.video}/process`);
        } catch (error) {
            // Hàng đợi hệ thống đầy: giữ file đã lên R2 để bấm "Xử lý lại" ở trang chi tiết; các lỗi khác thì dọn video nháp.
            if (error.status === 429) {
                error.keepDraft = true;
                error.message += ' File đã được giữ lại, hãy vào trang chi tiết video và bấm "Xử lý lại" sau.';
            } else {
                await api('DELETE', `/videos/${presign.video}`).catch(() => {});
                item.uuid = null;
            }
            throw error;
        }

        item.status = 'processing';
    }

    async function pump() {
        if (uploading) return;
        uploading = true;
        let item;
        while ((item = queue.find((entry) => entry.status === 'queued'))) {
            try {
                await upload(item);
            } catch (error) {
                item.status = 'failed';
                item.message = error.message;
                item.retryable = !error.keepDraft && error.status !== 422 && error.status !== 403;
            }
            render();
        }
        uploading = false;
    }

    function tooLarge(file) {
        if (file.size > MAX_VIDEO_BYTES) return `File quá lớn: tối đa ${formatSize(MAX_VIDEO_BYTES)} mỗi video.`;
        if (PLAN_MAX_BYTES && file.size > PLAN_MAX_BYTES) return `Vượt gói: file tối đa ${formatSize(PLAN_MAX_BYTES)}.`;
        return null;
    }

    function enqueue(files) {
        for (const file of files) {
            const base = { id: nextId++, file, status: 'failed', percent: 0, loaded: 0, speed: 0, retryable: false };
            if (!file.type.startsWith('video/') && !/\.(mkv|mp4|mov|webm|avi|m4v)$/i.test(file.name)) {
                queue.push({ ...base, message: 'Không phải file video.' });
            } else if (tooLarge(file)) {
                queue.push({ ...base, message: tooLarge(file) });
            } else {
                queue.push({ ...base, status: 'queued', message: '', retryable: true, folderId: $('upload-folder')?.value || null });
            }
        }
        render();
        pump();
    }

    const dropzone = $('dropzone');
    const input = $('file-input');
    dropzone.addEventListener('click', () => input.click());
    input.addEventListener('change', () => { enqueue([...input.files]); input.value = ''; });
    ['dragenter', 'dragover'].forEach((name) => dropzone.addEventListener(name, (e) => { e.preventDefault(); dropzone.classList.add('border-blue-400', 'bg-blue-50/40'); }));
    ['dragleave', 'drop'].forEach((name) => dropzone.addEventListener(name, (e) => { e.preventDefault(); dropzone.classList.remove('border-blue-400', 'bg-blue-50/40'); }));
    dropzone.addEventListener('drop', (e) => enqueue([...e.dataTransfer.files]));

    window.addEventListener('beforeunload', (e) => {
        if (queue.some((i) => ['queued', 'uploading'].includes(i.status))) { e.preventDefault(); e.returnValue = ''; }
    });

    // Theo dõi tiến độ xử lý trên server của các file đã tải lên xong.
    setInterval(async () => {
        const active = queue.filter((i) => i.status === 'processing' && i.uuid);
        await Promise.all(active.map(async (item) => {
            try {
                item.server = await api('GET', `/videos/${item.uuid}/status`);
                if (item.server.status === 'completed') item.status = 'completed';
                if (item.server.status === 'failed') { item.status = 'failed'; item.message = item.server.error || 'Xử lý thất bại.'; item.retryable = false; }
            } catch (error) { /* bỏ qua lỗi mạng tạm thời */ }
        }));
        if (active.length) render();
    }, 3000);
})();
</script>
@endif
@endsection
