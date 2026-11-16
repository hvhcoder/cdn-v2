<?php

return [

    'r2' => [
        'endpoint' => env('R2_ENDPOINT'),
        'bucket' => env('R2_BUCKET'),
        'access_key' => env('R2_ACCESS_KEY'),
        'secret_key' => env('R2_SECRET_KEY'),
        'region' => env('R2_REGION', 'auto'),
        'presign_minutes' => 60,
    ],

    'ffmpeg' => [
        'binary' => env('FFMPEG_PATH', 'ffmpeg'),
        'probe' => env('FFPROBE_PATH', 'ffprobe'),
        'timeout' => 3600,
        // Bitrate AAC khi mã hóa lại âm thanh.
        'audio_bitrate' => env('HLS_AUDIO_BITRATE', '128k'),
    ],

    'hls' => [
        // Mỗi đoạn video dài bao nhiêu giây (1-60). Đoạn càng ngắn thì càng nhiều ảnh phải upload.
        'segment_time' => (int) env('HLS_SEGMENT_TIME', 5),
    ],

    'png' => [
        'width' => 1024,
    ],

    'tiktok' => [
        'endpoint' => 'https://ads.tiktok.com/instant_page/api/v1/file/upload/',
        'referer' => 'https://ads.tiktok.com/',
        'timeout' => 45,
        // Số ảnh (segment) đẩy lên TikTok cùng lúc cho mỗi video.
        'concurrency' => (int) env('TIKTOK_CONCURRENCY', 6),
        // Cách chia segment cho các tài khoản: "random" (mỗi segment một tài khoản ngẫu nhiên, vd A: 1,4,8 – B: 2,3,5)
        // hoặc "round_robin" (lần lượt A, B, A, B...).
        'rotation' => env('TIKTOK_ROTATION', 'random'),
    ],

    'limits' => [
        // Dung lượng tối đa của 1 video. R2 chỉ cho PUT đơn tới ~4.995 GiB nên giá trị hiệu lực bị chặn ở 4.99.
        'max_video_gb' => (float) env('UPLOAD_MAX_VIDEO_GB', 5),
        // Tổng dung lượng các video đang chờ/đang xử lý trong hàng đợi (bảo vệ ổ đĩa server).
        'max_queue_gb' => (float) env('UPLOAD_MAX_QUEUE_GB', 15),
        // Số video tối đa đang nằm trong hàng đợi cùng lúc (toàn hệ thống / mỗi user).
        'max_queue_videos' => (int) env('UPLOAD_MAX_QUEUE_VIDEOS', 20),
        'max_queue_videos_per_user' => (int) env('UPLOAD_MAX_QUEUE_VIDEOS_PER_USER', 5),
    ],

    'folders' => [
        // Số cấp lồng nhau tối đa của thư mục (thư mục gốc = cấp 1).
        'max_depth' => (int) env('FOLDER_MAX_DEPTH', 5),
    ],

    'upload' => [
        // Số lần thử lại tối đa cho mỗi segment sau lần upload đầu thất bại (mỗi lần thử lại ưu tiên tài khoản khác).
        'max_retries' => (int) env('UPLOAD_MAX_RETRIES', 5),
        // Nghỉ giữa các lần thử lại: retry_sleep_ms x số lần đã thử.
        'retry_sleep_ms' => (int) env('UPLOAD_RETRY_SLEEP_MS', 500),
        'job_time_budget_seconds' => 480,

        // Cứ upload được khoảng N file (ngẫu nhiên trong [after_min, after_max]) thì nghỉ ngẫu nhiên [seconds_min, seconds_max] giây.
        'rest' => [
            'after_min' => (int) env('UPLOAD_REST_AFTER_MIN', 30),
            'after_max' => (int) env('UPLOAD_REST_AFTER_MAX', 45),
            'seconds_min' => (int) env('UPLOAD_REST_SECONDS_MIN', 3),
            'seconds_max' => (int) env('UPLOAD_REST_SECONDS_MAX', 5),
        ],
    ],

];
