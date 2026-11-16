<?php

namespace App\Services\Video;

use RuntimeException;
use Symfony\Component\Process\Process;

class FfmpegService
{
    public function __construct(
        private string $ffmpeg = 'ffmpeg',
        private string $ffprobe = 'ffprobe',
        private int $timeout = 3600,
        private string $audioBitrate = '128k',
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('cdnpro.ffmpeg.binary'), config('cdnpro.ffmpeg.probe'), config('cdnpro.ffmpeg.timeout'), config('cdnpro.ffmpeg.audio_bitrate'));
    }

    /**
     * Cắt video thành HLS (.ts + playlist.m3u8). Tự transcode khi cần.
     *
     * @return array{playlist: string, segments: array<int, array{path: string, duration: float}>}
     */
    public function convertToHls(string $inputPath, string $outputDir, int $segmentTime = 5): array
    {
        if (! is_file($inputPath)) {
            throw new RuntimeException("Không tìm thấy file video: {$inputPath}");
        }
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $playlist = $outputDir.DIRECTORY_SEPARATOR.'playlist.m3u8';
        $pattern = $outputDir.DIRECTORY_SEPARATOR.'segment_%05d.ts';

        $transcode = $this->needsTranscode($inputPath);
        $process = new Process($this->buildCommand($inputPath, $playlist, $pattern, $segmentTime, $transcode));
        $process->setTimeout($this->timeout)->run();

        if (! $process->isSuccessful() && ! $transcode) {
            $process = new Process($this->buildCommand($inputPath, $playlist, $pattern, $segmentTime, true));
            $process->setTimeout($this->timeout)->run();
        }

        if (! $process->isSuccessful() || ! is_file($playlist)) {
            throw new RuntimeException('FFmpeg cắt HLS thất bại: '.mb_substr($process->getErrorOutput(), -500));
        }

        return ['playlist' => $playlist, 'segments' => $this->parsePlaylist($playlist, $outputDir)];
    }

    /**
     * Đọc playlist của ffmpeg ra danh sách (file, thời lượng).
     *
     * @return array<int, array{path: string, duration: float}>
     */
    public function parsePlaylist(string $playlist, string $dir): array
    {
        $segments = [];
        $duration = null;

        foreach (preg_split('/\R/', (string) file_get_contents($playlist)) as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#EXTINF:')) {
                $duration = (float) substr($line, 8);
            } elseif ($line !== '' && ! str_starts_with($line, '#') && $duration !== null) {
                $segments[] = ['path' => $dir.DIRECTORY_SEPARATOR.$line, 'duration' => $duration];
                $duration = null;
            }
        }

        return $segments;
    }

    /**
     * @return array{duration: float, width: int, height: int}
     */
    public function probeVideo(string $inputPath): array
    {
        $process = new Process([
            $this->ffprobe, '-v', 'error', '-select_streams', 'v:0',
            '-show_entries', 'format=duration:stream=width,height', '-of', 'json', $inputPath,
        ]);
        $process->setTimeout(60)->run();

        $data = $process->isSuccessful() ? (json_decode($process->getOutput(), true) ?: []) : [];

        return [
            'duration' => (float) ($data['format']['duration'] ?? 0),
            'width' => (int) ($data['streams'][0]['width'] ?? 0),
            'height' => (int) ($data['streams'][0]['height'] ?? 0),
        ];
    }

    public function needsTranscode(string $inputPath): bool
    {
        $process = new Process([
            $this->ffprobe, '-v', 'error', '-show_entries', 'stream=codec_type,codec_name,pix_fmt', '-of', 'json', $inputPath,
        ]);
        $process->setTimeout(60)->run();

        if (! $process->isSuccessful()) {
            return true;
        }

        $streams = collect(json_decode($process->getOutput(), true)['streams'] ?? []);
        $video = $streams->firstWhere('codec_type', 'video') ?? [];

        $videoOk = in_array(strtolower($video['codec_name'] ?? ''), ['h264', 'avc1'], true)
            && ! str_contains(strtolower($video['pix_fmt'] ?? ''), '10');

        // Âm thanh luôn được mã hóa lại (xem audioArguments) nên không ảnh hưởng quyết định này.
        return ! $videoOk;
    }

    /**
     * @return array<int, string>
     */
    private function buildCommand(string $input, string $playlist, string $pattern, int $segmentTime, bool $transcode): array
    {
        $cmd = [$this->ffmpeg, '-y', '-i', $input, '-map', '0:v:0', '-map', '0:a:0?', '-sn', '-dn'];

        $cmd = array_merge($cmd, $transcode ? [
            '-c:v', 'libx264', '-preset', 'veryfast', '-profile:v', 'high', '-level', '4.1', '-pix_fmt', 'yuv420p',
            '-g', (string) max(1, $segmentTime * 25), '-keyint_min', '25', '-sc_threshold', '0',
            '-force_key_frames', "expr:gte(t,n_forced*{$segmentTime})",
        ] : ['-c:v', 'copy', '-bsf:v', 'h264_mp4toannexb']);

        $cmd = array_merge($cmd, $this->audioArguments());

        return array_merge($cmd, [
            '-f', 'hls', '-hls_time', (string) $segmentTime, '-hls_playlist_type', 'vod',
            '-hls_flags', 'independent_segments', '-hls_segment_type', 'mpegts',
            '-hls_segment_filename', $pattern, $playlist,
        ]);
    }

    /**
     * Âm thanh luôn được mã hóa lại thành AAC-LC stereo 48 kHz, kể cả khi giữ nguyên hình (-c:v copy).
     * Copy thẳng âm thanh nguồn vào MPEG-TS hay gây tiếng rè/méo trên trình duyệt với HE-AAC, AAC 5.1,
     * sample rate lạ (22/24/32 kHz), MP3 mono...; aresample=async sửa trôi/đứt timestamp giữa các đoạn.
     *
     * @return array<int, string>
     */
    private function audioArguments(): array
    {
        return [
            '-c:a', 'aac', '-profile:a', 'aac_low', '-b:a', $this->audioBitrate, '-ar', '48000', '-ac', '2',
            '-af', 'aresample=async=1:first_pts=0',
        ];
    }
}
