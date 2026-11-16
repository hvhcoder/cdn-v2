<?php

namespace App\Services\Video;

use App\Models\Video;

class PlaylistBuilder
{
    /**
     * Sinh m3u8 động từ bảng video_segments (không ghi file vào public/).
     */
    public function build(Video $video): string
    {
        $segments = $video->segments()->get();
        $target = (int) ceil($segments->max('duration') ?: $video->segment_time);

        $lines = ['#EXTM3U', '#EXT-X-VERSION:3', "#EXT-X-TARGETDURATION:{$target}", '#EXT-X-MEDIA-SEQUENCE:0', '#EXT-X-PLAYLIST-TYPE:VOD', '#EXT-X-INDEPENDENT-SEGMENTS'];

        foreach ($segments as $segment) {
            $lines[] = sprintf('#EXTINF:%.3f,', $segment->duration);
            $lines[] = $segment->cdn_url;
        }

        $lines[] = '#EXT-X-ENDLIST';

        return implode("\n", $lines)."\n";
    }
}
