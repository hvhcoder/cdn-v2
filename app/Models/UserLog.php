<?php

namespace App\Models;

use App\Services\Geo\GeoLocator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property string action @property array|null meta @property string|null ip @property float|null lat @property float|null lon */
#[Fillable(['user_id', 'action', 'meta', 'ip', 'lat', 'lon'])]
class UserLog extends Model
{
    // Actions
    public const VIDEO_UPLOADED = 'video.uploaded';

    public const VIDEO_DELETED = 'video.deleted';

    public const EMBED_BLOCKED = 'embed.blocked';

    public const PLAN_GRANTED = 'plan.granted';

    public const USER_BANNED = 'user.banned';

    public const USER_UNBANNED = 'user.unbanned';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function record(int $userId, string $action, ?array $meta = null, ?string $ip = null): void
    {
        $geo = $ip ? app(GeoLocator::class)->locate($ip) : null;

        static::create([
            'user_id' => $userId,
            'action' => $action,
            'meta' => $meta,
            'ip' => $ip,
            'lat' => $geo['lat'] ?? null,
            'lon' => $geo['lon'] ?? null,
        ]);
    }
}
