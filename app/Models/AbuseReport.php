<?php

namespace App\Models;

use Database\Factories\AbuseReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['video_id', 'reason', 'details', 'reporter_ip', 'status', 'resolved_by', 'resolved_at'])]
class AbuseReport extends Model
{
    /** @use HasFactory<AbuseReportFactory> */
    use HasFactory;

    public const PENDING = 'pending';

    public const RESOLVED = 'resolved';

    public const DISMISSED = 'dismissed';

    public const REASONS = [
        'copyright' => 'Vi phạm bản quyền',
        'explicit' => 'Nội dung khiêu dâm / bạo lực',
        'spam' => 'Spam / quảng cáo',
        'other' => 'Lý do khác',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Video, $this>
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @param  Builder<AbuseReport>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', self::PENDING);
    }
}
