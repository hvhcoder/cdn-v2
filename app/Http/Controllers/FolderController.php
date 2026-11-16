<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Services\Billing\QuotaService;
use App\Services\Video\FolderService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FolderController extends Controller
{
    /**
     * Trang quản lý thư mục: cây thư mục, tạo / đổi tên / chuyển / xóa, sao chép link cả thư mục.
     */
    public function index(Request $request, FolderService $folders, QuotaService $quota): View
    {
        $user = $request->user();

        return view('folders.index', [
            'tree' => $folders->flatTree($user),
            'canManage' => $quota->canUseFolders($user),
            'unfiledCount' => $user->videos()->whereNull('folder_id')->count(),
            'colors' => Folder::COLORS,
            'maxDepth' => (int) config('cdnpro.folders.max_depth'),
        ]);
    }

    public function store(Request $request, FolderService $folders): RedirectResponse
    {
        $user = $request->user();
        $folders->assertCanManage($user);

        $data = $request->validate($this->rules($request));
        $parent = $folders->resolveParent($user, $data['parent_id'] ?? null);

        $user->folders()->create([
            'parent_id' => $parent?->id,
            'name' => trim($data['name']),
            'color' => $data['color'] ?? null,
        ]);

        return back()->with('status', 'Đã tạo thư mục.');
    }

    public function update(Request $request, Folder $folder, FolderService $folders): RedirectResponse
    {
        Gate::authorize('update', $folder);
        $user = $request->user();
        $folders->assertCanManage($user);

        $data = $request->validate($this->rules($request, $folder));
        $parent = $folders->resolveParent($user, $data['parent_id'] ?? null, $folder);

        $folder->update([
            'parent_id' => $parent?->id,
            'name' => trim($data['name']),
            'color' => $data['color'] ?? null,
        ]);

        return back()->with('status', 'Đã cập nhật thư mục.');
    }

    /**
     * mode=keep: giữ video (chuyển lên thư mục cha). mode=delete_videos: xóa luôn video trong thư mục.
     */
    public function destroy(Request $request, Folder $folder, FolderService $folders): RedirectResponse
    {
        Gate::authorize('delete', $folder);

        $mode = $request->validate(['mode' => ['required', Rule::in([FolderService::KEEP_VIDEOS, FolderService::DELETE_VIDEOS])]])['mode'];
        $deleted = $folders->delete($folder, $mode);

        return redirect()->route('folders.index')->with('status', $mode === FolderService::DELETE_VIDEOS
            ? "Đã xóa thư mục và {$deleted} video bên trong."
            : 'Đã xóa thư mục. Video được chuyển lên thư mục cha.');
    }

    /**
     * Link của các video đã hoàn tất trong thư mục để sao chép hàng loạt.
     */
    public function links(Request $request, Folder $folder, FolderService $folders): JsonResponse
    {
        Gate::authorize('view', $folder);

        $data = $request->validate([
            'type' => ['nullable', Rule::in(['m3u8', 'embed', 'iframe'])],
            'recursive' => ['nullable', 'boolean'],
        ]);

        return response()->json($folders->links($folder, $data['type'] ?? 'm3u8', (bool) ($data['recursive'] ?? false)));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request, ?Folder $folder = null): array
    {
        $parentId = $request->input('parent_id') ?: null;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('folders', 'name')
                ->where(fn (Builder $q) => $q->where('user_id', $request->user()->id)
                    ->when($parentId, fn ($q) => $q->where('parent_id', $parentId), fn ($q) => $q->whereNull('parent_id')))
                ->ignore($folder?->id)],
            'parent_id' => ['nullable', 'integer'],
            'color' => ['nullable', Rule::in(Folder::COLORS)],
        ];
    }
}
