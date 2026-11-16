<?php

namespace App\Models;

use Database\Factories\VideoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'folder_id', 'title', 'r2_path', 'status', 'encoding_method', 'segment_time', 'size_bytes', 'duration_seconds',
    'segment_count', 'error_message', 'embed_locked_at', 'dead_checked_at', 'dead_segments', 'allowed_domains',
])]
class Video extends Model
{
    /** @use HasFactory<VideoFactory> */
    use HasFactory, HasUuids;

    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    // Encoding methods
    public const ENCODING_PIXEL = 'pixel';

    public const ENCODING_HEADER_GRAFT = 'header-graft';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'embed_locked_at' => 'datetime',
            'dead_checked_at' => 'datetime',
            'allowed_domains' => 'array',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

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
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * @return HasMany<VideoSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(VideoSegment::class)->orderBy('index');
    }

    /**
     * @return HasMany<AbuseReport, $this>
     */
    public function abuseReports(): HasMany
    {
        return $this->hasMany(AbuseReport::class);
    }

    public function isEmbedLocked(): bool
    {
        return $this->embed_locked_at !== null;
    }

    /**
     * Trạng thái gọn cho UI/polling. Dùng uploaded_segments_count nếu đã withCount để tránh N+1.
     *
     * @return array<string, mixed>
     */
    public function statusPayload(): array
    {
        $uploaded = $this->uploaded_segments_count
            ?? $this->segments()->where('status', VideoSegment::UPLOADED)->count();

        return [
            'video' => $this->uuid,
            'title' => $this->title,
            'status' => $this->status,
            'error' => $this->error_message,
            'segments_uploaded' => (int) $uploaded,
            'segments_total' => $this->segment_count,
            'progress' => $this->segment_count ? (int) floor($uploaded / $this->segment_count * 100) : 0,
            'size_bytes' => $this->size_bytes,
            'm3u8_url' => $this->status === self::COMPLETED ? route('m3u8', $this) : null,
            'embed_url' => $this->status === self::COMPLETED ? route('embed', $this) : null,
        ];
    }

    public function formattedSize(): string
    {
        $bytes = (int) $this->size_bytes;

        return $bytes >= 1024 ** 3
            ? number_format($bytes / 1024 ** 3, 2).' GB'
            : number_format($bytes / 1024 ** 2, 1).' MB';
    }

    /**
     * Thời lượng dạng m:ss hoặc h:mm:ss; chưa biết thì trả về dấu gạch.
     */
    public function formattedDuration(): string
    {
        $seconds = (int) $this->duration_seconds;
        if ($seconds <= 0) {
            return '—';
        }

        return $seconds >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60)
            : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    public function tempDirectory(): string
    {
        return storage_path('app/temp/'.$this->uuid);
    }
}
