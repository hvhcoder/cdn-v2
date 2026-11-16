@extends('layouts.app')

@section('title', 'Thư mục')

@php
    $iconAttrs = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
    $swatch = ['slate' => 'bg-slate-400', 'blue' => 'bg-blue-500', 'emerald' => 'bg-emerald-500', 'amber' => 'bg-amber-500', 'rose' => 'bg-rose-500', 'violet' => 'bg-violet-500'];
    $field = 'block h-11 w-full rounded-xl bg-slate-50 px-3.5 text-sm outline-1 -outline-offset-1 outline-slate-200 focus:bg-white focus:outline-2 focus:-outline-offset-2 focus:outline-blue-500';

    // Với mỗi thư mục: id các thư mục con cháu và tổng số video trong cả nhánh (tính từ danh sách phẳng theo thứ tự cây).
    $rows = $tree->values()->map(function ($row, $i) use ($tree) {
        $descendants = [];
        $videos = $row['videos_count'];
        for ($j = $i + 1; $j < $tree->count() && $tree[$j]['depth'] > $row['depth']; $j++) {
            $descendants[] = $tree[$j]['folder']->id;
            $videos += $tree[$j]['videos_count'];
        }

        return $row + ['descendants' => $descendants, 'subtree_videos' => $videos];
    });
@endphp

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Thư mục</h1>
        <p class="mt-1 text-sm text-slate-500">Nhóm video thành các thư mục lồng nhau (tối đa {{ $maxDepth }} cấp) và sao chép link cả thư mục chỉ bằng một cú bấm.</p>
    </div>
    @if ($canManage)
        <button type="button" data-open-create class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm shadow-blue-600/20 transition hover:bg-blue-700">
            <svg class="size-4" {!! $iconAttrs !!}><path d="M12 5v14M5 12h14"/></svg> Tạo thư mục
        </button>
    @endif
</div>

@unless ($canManage)
    <div class="mt-6 rounded-2xl bg-amber-50 p-5 text-sm text-amber-900 ring-1 ring-amber-600/20">
        <p class="font-medium">Gói hiện tại của bạn không bao gồm thư mục.</p>
        <p class="mt-1">Xem <a href="{{ route('pricing') }}" class="font-medium underline">bảng giá</a> để nâng cấp. Các thư mục đã có vẫn xem được.</p>
    </div>
@endunless

<div class="mt-6 overflow-hidden rounded-2xl bg-white shadow-xs ring-1 ring-slate-200/70">
    @forelse ($rows as $row)
        @php $folder = $row['folder']; @endphp
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 py-3 pr-4 transition-colors hover:bg-slate-50/70" style="padding-left: {{ 1.25 + $row['depth'] * 1.75 }}rem">
            <a href="{{ route('videos.index', ['folder' => $folder->id]) }}" class="flex min-w-0 flex-1 items-center gap-3">
                <x-folder-icon :color="$folder->color" size="size-6" />
                <span class="truncate font-medium text-slate-900 hover:text-blue-700">{{ $folder->name }}</span>
                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $row['videos_count'] }} video</span>
                @if ($row['subtree_videos'] > $row['videos_count'])
                    <span class="hidden shrink-0 text-xs text-slate-400 sm:inline">({{ $row['subtree_videos'] }} gồm thư mục con)</span>
                @endif
            </a>

            <div class="flex items-center gap-1">
                <details class="relative">
                    <summary class="flex h-8 cursor-pointer list-none items-center gap-1.5 rounded-lg px-2.5 text-xs font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                        <svg class="size-3.5" {!! $iconAttrs !!}><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg>
                        Sao chép link
                    </summary>
                    <div class="absolute right-0 z-10 mt-1 w-56 rounded-xl bg-white p-1.5 text-sm shadow-lg ring-1 ring-slate-200">
                        @foreach (['m3u8' => 'Link m3u8', 'embed' => 'Link trang phát', 'iframe' => 'Mã nhúng iframe'] as $type => $label)
                            <button type="button" data-links="{{ route('folders.links', ['folder' => $folder, 'type' => $type, 'recursive' => 1]) }}" class="block w-full cursor-pointer rounded-lg px-3 py-2 text-left transition hover:bg-slate-50">{{ $label }} <span class="text-xs text-slate-400">· cả thư mục con</span></button>
                        @endforeach
                    </div>
                </details>

                @if ($canManage)
                    <button type="button" title="Sửa / chuyển thư mục" class="flex size-8 cursor-pointer items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                            data-edit data-action="{{ route('folders.update', $folder) }}" data-name="{{ $folder->name }}" data-parent="{{ $folder->parent_id }}" data-color="{{ $folder->color }}" data-exclude="{{ implode(',', array_merge([$folder->id], $row['descendants'])) }}">
                        <svg class="size-4" {!! $iconAttrs !!}><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                    </button>
                @endif
                <button type="button" title="Xóa thư mục" class="flex size-8 cursor-pointer items-center justify-center rounded-lg text-slate-500 transition hover:bg-red-50 hover:text-red-600"
                        data-delete data-action="{{ route('folders.destroy', $folder) }}" data-name="{{ $folder->name }}" data-direct="{{ $row['videos_count'] }}" data-subtree="{{ $row['subtree_videos'] }}" data-children="{{ count($row['descendants']) }}">
                    <svg class="size-4" {!! $iconAttrs !!}><path d="M3 6h18"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="m19 6-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                </button>
            </div>
        </div>
    @empty
        <div class="px-5 py-16 text-center">
            <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><x-folder-icon size="size-7" /></span>
            <p class="mt-4 font-medium">Chưa có thư mục nào</p>
            <p class="mt-1 text-sm text-slate-500">Tạo thư mục đầu tiên để sắp xếp video của bạn.</p>
        </div>
    @endforelse

    <a href="{{ route('videos.index', ['folder' => 'none']) }}" class="flex items-center gap-3 px-5 py-3 text-sm text-slate-600 transition-colors hover:bg-slate-50">
        <svg class="size-6 shrink-0 text-slate-300" {!! $iconAttrs !!}><rect width="18" height="18" x="3" y="3" rx="2"/><path d="m10 8 6 4-6 4z"/></svg>
        <span class="flex-1">Chưa phân loại</span>
        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $unfiledCount }} video</span>
    </a>
</div>

{{-- Hộp thoại tạo / sửa --}}
<dialog id="folder-dialog" class="m-auto w-full max-w-md rounded-2xl p-0 shadow-2xl backdrop:bg-slate-900/40 backdrop:backdrop-blur-[1px]">
    <form id="folder-form" method="POST" action="{{ route('folders.store') }}" class="p-6">
        @csrf
        <input type="hidden" name="_method" id="folder-method" value="POST" disabled>
        <h2 id="folder-title" class="text-lg font-semibold">Tạo thư mục</h2>

        <label for="folder-name" class="mb-1.5 mt-5 block text-sm font-medium">Tên thư mục</label>
        <input id="folder-name" name="name" required maxlength="100" class="{{ $field }}">

        <label for="folder-parent" class="mb-1.5 mt-4 block text-sm font-medium">Nằm trong</label>
        <select id="folder-parent" name="parent_id" class="{{ $field }}">
            <option value="">— Thư mục gốc —</option>
            @foreach ($rows as $row)
                <option value="{{ $row['folder']->id }}">{!! str_repeat('&nbsp;&nbsp;&nbsp;', $row['depth']) !!}{{ $row['folder']->name }}</option>
            @endforeach
        </select>

        <p class="mb-1.5 mt-4 text-sm font-medium">Màu</p>
        <div class="flex gap-2">
            <label class="cursor-pointer"><input type="radio" name="color" value="" class="peer sr-only" checked><span class="flex size-8 items-center justify-center rounded-full border-2 border-slate-200 text-xs text-slate-400 peer-checked:border-slate-900">∅</span></label>
            @foreach ($colors as $color)
                <label class="cursor-pointer"><input type="radio" name="color" value="{{ $color }}" class="peer sr-only"><span class="block size-8 rounded-full {{ $swatch[$color] }} ring-2 ring-transparent ring-offset-2 peer-checked:ring-slate-900"></span></label>
            @endforeach
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <button type="button" data-close class="cursor-pointer rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-medium transition hover:bg-slate-50">Hủy</button>
            <button class="cursor-pointer rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">Lưu</button>
        </div>
    </form>
</dialog>

{{-- Hộp thoại xóa --}}
<dialog id="delete-dialog" class="m-auto w-full max-w-md rounded-2xl p-0 shadow-2xl backdrop:bg-slate-900/40 backdrop:backdrop-blur-[1px]">
    <form id="delete-form" method="POST" class="p-6">
        @csrf @method('DELETE')
        <h2 class="text-lg font-semibold">Xóa thư mục “<span id="delete-name"></span>”</h2>
        <p id="delete-summary" class="mt-1 text-sm text-slate-500"></p>

        <label class="mt-5 flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-4 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/50">
            <input type="radio" name="mode" value="keep" checked class="mt-1">
            <span><span class="block text-sm font-medium">Chỉ xóa thư mục, giữ lại video</span><span class="mt-0.5 block text-xs text-slate-500">Video và thư mục con được chuyển lên thư mục cha.</span></span>
        </label>
        <label class="mt-3 flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-4 has-[:checked]:border-red-500 has-[:checked]:bg-red-50/50">
            <input type="radio" name="mode" value="delete_videos" class="mt-1">
            <span><span class="block text-sm font-medium text-red-700">Xóa luôn video bên trong</span><span id="delete-warning" class="mt-0.5 block text-xs text-red-600/80"></span></span>
        </label>

        <div class="mt-6 flex justify-end gap-3">
            <button type="button" data-close class="cursor-pointer rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-medium transition hover:bg-slate-50">Hủy</button>
            <button class="cursor-pointer rounded-xl bg-red-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-red-700">Xóa thư mục</button>
        </div>
    </form>
</dialog>

<div id="toast" class="pointer-events-none fixed bottom-6 left-1/2 z-50 -translate-x-1/2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm text-white opacity-0 shadow-lg transition-opacity"></div>

<script>
(() => {
    const $ = (id) => document.getElementById(id);
    const folderDialog = $('folder-dialog');
    const deleteDialog = $('delete-dialog');
    const toast = $('toast');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const createAction = @json(route('folders.store'));

    const showToast = (message) => {
        toast.textContent = message;
        toast.classList.remove('opacity-0');
        setTimeout(() => toast.classList.add('opacity-0'), 2200);
    };

    function openFolderDialog({ action, method, title, name = '', parent = '', color = '', exclude = [] }) {
        $('folder-form').action = action;
        $('folder-method').value = method;
        $('folder-method').disabled = method === 'POST';
        $('folder-title').textContent = title;
        $('folder-name').value = name;
        $('folder-parent').value = parent || '';
        [...$('folder-parent').options].forEach((option) => { option.disabled = exclude.includes(option.value); });
        document.querySelectorAll('#folder-form input[name="color"]').forEach((radio) => { radio.checked = radio.value === (color || ''); });
        folderDialog.showModal();
        $('folder-name').focus();
    }

    document.querySelector('[data-open-create]')?.addEventListener('click', () => openFolderDialog({ action: createAction, method: 'POST', title: 'Tạo thư mục' }));

    document.querySelectorAll('[data-edit]').forEach((button) => button.addEventListener('click', () => openFolderDialog({
        action: button.dataset.action, method: 'PUT', title: 'Sửa thư mục', name: button.dataset.name,
        parent: button.dataset.parent, color: button.dataset.color, exclude: button.dataset.exclude.split(','),
    })));

    document.querySelectorAll('[data-delete]').forEach((button) => button.addEventListener('click', () => {
        const { name, direct, subtree, children } = button.dataset;
        $('delete-form').action = button.dataset.action;
        $('delete-name').textContent = name;
        $('delete-summary').textContent = `${direct} video trong thư mục${children > 0 ? `, ${subtree} video và ${children} thư mục con tính cả nhánh` : ''}.`;
        $('delete-warning').textContent = subtree > 0 ? `Sẽ xóa vĩnh viễn ${subtree} video${children > 0 ? ' và các thư mục con' : ''}, không thể hoàn tác.` : 'Thư mục không có video.';
        deleteDialog.querySelector('input[value="keep"]').checked = true;
        deleteDialog.showModal();
    }));

    document.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', () => button.closest('dialog').close()));
    [folderDialog, deleteDialog].forEach((dialog) => dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); }));

    document.querySelectorAll('[data-links]').forEach((button) => button.addEventListener('click', async () => {
        button.closest('details').open = false;
        try {
            const response = await fetch(button.dataset.links, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } });
            const data = await response.json();
            if (!data.count) { showToast('Chưa có video hoàn tất trong thư mục này.'); return; }
            await navigator.clipboard.writeText(data.text);
            showToast(`Đã sao chép ${data.count} link`);
        } catch (error) { showToast('Không sao chép được, hãy thử lại.'); }
    }));
})();
</script>
@endsection
