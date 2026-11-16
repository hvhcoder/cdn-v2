<?php

namespace App\Models;

use Database\Factories\VideoSegmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['video_id', 'index', 'duration', 'cdn_url', 'upload_config_id', 'status', 'attempts'])]
class VideoSegment extends Model
{
    /** @use HasFactory<VideoSegmentFactory> */
    use HasFactory;

    public const PENDING = 'pending';

    public const UPLOADED = 'uploaded';

    /**
     * @return BelongsTo<Video, $this>
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
