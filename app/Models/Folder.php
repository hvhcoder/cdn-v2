<?php

namespace App\Models;

use Database\Factories\FolderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['parent_id', 'name', 'color'])]
class Folder extends Model
{
    /** @use HasFactory<FolderFactory> */
    use HasFactory;

    /** Màu gợi ý cho thư mục (khóa => mã màu Tailwind). */
    public const COLORS = ['slate', 'blue', 'emerald', 'amber', 'rose', 'violet'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Folder, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    /**
     * @return HasMany<Video, $this>
     */
    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    /**
     * Folder này là chính $other hoặc tổ tiên của $other (dùng để chặn vòng lặp khi chuyển thư mục cha).
     */
    public function isAncestorOfOrSelf(self $other): bool
    {
        for ($node = $other, $guard = 0; $node !== null && $guard < 100; $node = $node->parent, $guard++) {
            if ($node->is($this)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Chuỗi thư mục từ gốc tới thư mục này (cho breadcrumb).
     *
     * @return Collection<int, Folder>
     */
    public function breadcrumb(): Collection
    {
        $chain = collect();
        for ($node = $this, $guard = 0; $node !== null && $guard < 100; $node = $node->parent, $guard++) {
            $chain->prepend($node);
        }

        return $chain;
    }

    /**
     * Số cấp của thư mục này (gốc = 1).
     */
    public function depth(): int
    {
        return $this->breadcrumb()->count();
    }

    /**
     * Số cấp của nhánh con sâu nhất dưới thư mục này, tính cả chính nó (không có con = 1).
     *
     * @param  Collection<int, Collection<int, Folder>>|null  $childrenByParent  bản đồ parent_id => các thư mục con
     */
    public function height(?Collection $childrenByParent = null): int
    {
        $childrenByParent ??= self::where('user_id', $this->user_id)->get()->groupBy('parent_id');

        $max = 0;
        foreach ($childrenByParent->get($this->id, collect()) as $child) {
            $max = max($max, $child->height($childrenByParent));
        }

        return 1 + $max;
    }

    /**
     * Id của thư mục này và mọi thư mục con cháu.
     *
     * @return array<int, int>
     */
    public function selfAndDescendantIds(): array
    {
        $byParent = self::where('user_id', $this->user_id)->get(['id', 'parent_id'])->groupBy('parent_id');
        $ids = [$this->id];
        $queue = [$this->id];

        while ($queue) {
            $current = array_shift($queue);
            foreach ($byParent->get($current, collect()) as $child) {
                $ids[] = $child->id;
                $queue[] = $child->id;
            }
        }

        return $ids;
    }
}
