<?php

namespace App\Services\Video;

use App\Models\Folder;
use App\Models\User;
use App\Models\Video;
use App\Services\Billing\QuotaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FolderService
{
    public const KEEP_VIDEOS = 'keep';

    public const DELETE_VIDEOS = 'delete_videos';

    public function __construct(private QuotaService $quota, private VideoDeleter $videoDeleter) {}

    /**
     * Chỉ gói có tính năng thư mục (hoặc admin) mới được tạo/sửa/chuyển thư mục.
     *
     * @throws ValidationException
     */
    public function assertCanManage(User $user): void
    {
        if (! $this->quota->canUseFolders($user)) {
            throw ValidationException::withMessages(['folder' => 'Gói hiện tại của bạn không bao gồm thư mục. Hãy nâng cấp gói để sử dụng.']);
        }
    }

    /**
     * Danh sách thư mục phẳng theo thứ tự cây (cha trước con, sắp theo tên) kèm cấp và số video trực tiếp.
     *
     * @return Collection<int, array{folder: Folder, depth: int, videos_count: int}>
     */
    public function flatTree(User $user): Collection
    {
        $byParent = $user->folders()->withCount('videos')->orderBy('name')->get()->groupBy('parent_id');
        $rows = collect();

        $walk = function (?int $parentId, int $depth) use (&$walk, $byParent, $rows): void {
            foreach ($byParent->get($parentId, collect()) as $folder) {
                $rows->push(['folder' => $folder, 'depth' => $depth, 'videos_count' => (int) $folder->videos_count]);
                $walk($folder->id, $depth + 1);
            }
        };
        $walk(null, 0);

        return $rows;
    }

    /**
     * Kiểm tra thư mục cha hợp lệ: thuộc về user, không tạo vòng lặp, không vượt quá số cấp tối đa.
     *
     * @throws ValidationException
     */
    public function resolveParent(User $user, ?int $parentId, ?Folder $moving = null): ?Folder
    {
        if ($parentId === null) {
            return null;
        }

        $parent = $user->folders()->find($parentId);
        if ($parent === null) {
            throw ValidationException::withMessages(['parent_id' => 'Thư mục cha không hợp lệ.']);
        }

        if ($moving !== null && $moving->isAncestorOfOrSelf($parent)) {
            throw ValidationException::withMessages(['parent_id' => 'Không thể chuyển thư mục vào chính nó hoặc vào thư mục con của nó.']);
        }

        $max = (int) config('cdnpro.folders.max_depth');
        $subtreeHeight = $moving?->height() ?? 1;
        if ($parent->depth() + $subtreeHeight > $max) {
            throw ValidationException::withMessages(['parent_id' => "Thư mục chỉ được lồng tối đa {$max} cấp."]);
        }

        return $parent;
    }

    /**
     * Xóa thư mục.
     *
     * KEEP_VIDEOS: video và thư mục con được chuyển lên thư mục cha (hoặc ra ngoài cùng nếu không có cha).
     * DELETE_VIDEOS: xóa luôn mọi video trong thư mục và các thư mục con (không xóa nếu có video đang xử lý).
     *
     * @return int số video bị xóa
     *
     * @throws ValidationException
     */
    public function delete(Folder $folder, string $mode): int
    {
        if ($mode === self::DELETE_VIDEOS) {
            $ids = $folder->selfAndDescendantIds();
            $videos = Video::whereIn('folder_id', $ids)->get();

            if ($videos->contains(fn (Video $v) => $v->status === Video::PROCESSING)) {
                throw ValidationException::withMessages(['folder' => 'Có video đang được xử lý trong thư mục này, hãy đợi xử lý xong rồi xóa.']);
            }

            $videos->each(fn (Video $video) => $this->videoDeleter->delete($video));
            $folder->delete();

            return $videos->count();
        }

        DB::transaction(function () use ($folder) {
            $folder->videos()->update(['folder_id' => $folder->parent_id]);
            $folder->children()->update(['parent_id' => $folder->parent_id]);
            $folder->delete();
        });

        return 0;
    }

    /**
     * Chuyển nhiều video của user vào một thư mục (null = ra ngoài thư mục).
     *
     * @param  array<int, string>  $videoUuids
     * @return int số video đã chuyển
     *
     * @throws ValidationException
     */
    public function moveVideos(User $user, array $videoUuids, ?int $folderId): int
    {
        $folder = null;
        if ($folderId !== null) {
            $this->assertCanManage($user);
            $folder = $user->folders()->find($folderId)
                ?? throw ValidationException::withMessages(['folder_id' => 'Thư mục đích không hợp lệ.']);
        }

        return $user->videos()->whereIn('uuid', $videoUuids)->update(['folder_id' => $folder?->id]);
    }

    /**
     * Gom link của các video đã hoàn tất trong thư mục để dán nhanh.
     *
     * @param  'm3u8'|'embed'|'iframe'  $type
     * @return array{count: int, text: string}
     */
    public function links(Folder $folder, string $type, bool $recursive): array
    {
        $ids = $recursive ? $folder->selfAndDescendantIds() : [$folder->id];

        $videos = Video::whereIn('folder_id', $ids)->where('status', Video::COMPLETED)->orderBy('title')->get();

        $lines = $videos->map(function (Video $video) use ($type) {
            $embed = route('embed', $video);

            return match ($type) {
                'embed' => $embed,
                'iframe' => '<iframe src="'.$embed.'" width="640" height="360" frameborder="0" allow="autoplay; fullscreen" allowfullscreen></iframe>',
                default => route('m3u8', $video),
            };
        });

        return ['count' => $lines->count(), 'text' => $lines->implode("\n")];
    }
}
