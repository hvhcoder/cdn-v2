<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Cho phép admin chỉnh các giá trị trong config/cdnpro.php bằng database. Giá trị .env là mặc định;
 * giá trị trong bảng settings ưu tiên hơn và được áp lại mỗi request / mỗi job nên không cần restart worker.
 */
class RuntimeSettings
{
    /** @var array<string, mixed> giá trị gốc (từ .env) của các khóa đang bị ghi đè */
    private array $originals = [];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function fields(): array
    {
        return [
            'tiktok.concurrency' => ['group' => 'Upload lên TikTok', 'label' => 'Số ảnh đẩy lên TikTok cùng lúc', 'type' => 'int', 'min' => 1, 'max' => 30, 'unit' => 'ảnh', 'help' => 'Mỗi video đẩy tối đa bấy nhiêu ảnh song song (tương ứng TIKTOK_CONCURRENCY).'],
            'tiktok.rotation' => ['group' => 'Upload lên TikTok', 'label' => 'Cách xoay vòng tài khoản TikTok', 'type' => 'select', 'options' => ['random' => 'Ngẫu nhiên (vd A: 1,4,8 · B: 2,3,5)', 'round_robin' => 'Lần lượt (A, B, A, B…)'], 'help' => 'Cách chia từng đoạn cho các tài khoản đang bật (TIKTOK_ROTATION).'],
            'upload.max_retries' => ['group' => 'Thử lại & nghỉ', 'label' => 'Số lần thử lại tối đa cho mỗi ảnh', 'type' => 'int', 'min' => 0, 'max' => 20, 'unit' => 'lần', 'help' => 'Sau lần thất bại đầu tiên, thử lại tối đa bấy nhiêu lần, ưu tiên tài khoản khác (UPLOAD_MAX_RETRIES).'],
            'upload.retry_sleep_ms' => ['group' => 'Thử lại & nghỉ', 'label' => 'Nghỉ giữa các lần thử lại', 'type' => 'int', 'min' => 0, 'max' => 10000, 'unit' => 'ms', 'help' => 'Nhân theo số lần đã thử: 500 → 0,5s, 1s, 1,5s… (UPLOAD_RETRY_SLEEP_MS).'],
            'upload.rest.after_min' => ['group' => 'Thử lại & nghỉ', 'label' => 'Nghỉ sau ít nhất', 'type' => 'int', 'min' => 1, 'max' => 1000, 'unit' => 'file', 'help' => 'Cứ upload được khoảng N file (ngẫu nhiên từ mức này đến mức tối đa) thì nghỉ một lúc (UPLOAD_REST_AFTER_MIN).'],
            'upload.rest.after_max' => ['group' => 'Thử lại & nghỉ', 'label' => 'Nghỉ sau nhiều nhất', 'type' => 'int', 'min' => 1, 'max' => 1000, 'unit' => 'file', 'help' => 'UPLOAD_REST_AFTER_MAX.'],
            'upload.rest.seconds_min' => ['group' => 'Thử lại & nghỉ', 'label' => 'Thời gian nghỉ ít nhất', 'type' => 'int', 'min' => 0, 'max' => 120, 'unit' => 'giây', 'help' => 'UPLOAD_REST_SECONDS_MIN.'],
            'upload.rest.seconds_max' => ['group' => 'Thử lại & nghỉ', 'label' => 'Thời gian nghỉ nhiều nhất', 'type' => 'int', 'min' => 0, 'max' => 120, 'unit' => 'giây', 'help' => 'UPLOAD_REST_SECONDS_MAX.'],
            'hls.segment_time' => ['group' => 'Cắt video (HLS)', 'label' => 'Mỗi đoạn video dài', 'type' => 'int', 'min' => 1, 'max' => 60, 'unit' => 'giây', 'help' => 'Chỉ áp dụng cho video upload sau khi đổi. Đoạn càng ngắn thì càng nhiều ảnh phải upload (HLS_SEGMENT_TIME).'],
            'limits.max_video_gb' => ['group' => 'Giới hạn hệ thống', 'label' => 'Dung lượng tối đa mỗi video', 'type' => 'float', 'min' => 0.1, 'max' => 4.99, 'unit' => 'GB', 'help' => 'R2 chỉ nhận PUT đơn tới ~4.99 GB nên không đặt cao hơn (UPLOAD_MAX_VIDEO_GB).'],
            'limits.max_queue_gb' => ['group' => 'Giới hạn hệ thống', 'label' => 'Tổng dung lượng hàng đợi', 'type' => 'float', 'min' => 0.1, 'max' => 10000, 'unit' => 'GB', 'help' => 'Tổng dung lượng các video đang chờ/đang xử lý; ổ đĩa server cần trống khoảng gấp đôi (UPLOAD_MAX_QUEUE_GB).'],
            'limits.max_queue_videos' => ['group' => 'Giới hạn hệ thống', 'label' => 'Số video tối đa trong hàng đợi', 'type' => 'int', 'min' => 1, 'max' => 10000, 'unit' => 'video', 'help' => 'Toàn hệ thống (UPLOAD_MAX_QUEUE_VIDEOS).'],
            'limits.max_queue_videos_per_user' => ['group' => 'Giới hạn hệ thống', 'label' => 'Số video tối đa trong hàng đợi của mỗi user', 'type' => 'int', 'min' => 1, 'max' => 1000, 'unit' => 'video', 'help' => 'UPLOAD_MAX_QUEUE_VIDEOS_PER_USER.'],
        ];
    }

    public static function configPath(string $key): string
    {
        return "cdnpro.{$key}";
    }

    /**
     * Áp các giá trị admin đã lưu lên config; khóa nào không còn bị ghi đè thì trả về giá trị gốc.
     * Chỉ chạm vào các khóa có trong bảng settings (hoặc từng bị ghi đè) nên không ảnh hưởng phần còn lại.
     */
    public function apply(): void
    {
        $rows = Setting::pluck('value', 'key');

        foreach (self::fields() as $key => $field) {
            $path = self::configPath($key);

            if ($rows->has($key)) {
                $this->originals[$key] ??= config($path);
                config([$path => $this->cast($field, $rows[$key])]);
            } elseif (array_key_exists($key, $this->originals)) {
                config([$path => $this->originals[$key]]);
                unset($this->originals[$key]);
            }
        }
    }

    /**
     * Lưu các giá trị admin nhập. Để trống = bỏ ghi đè, quay về mặc định trong .env.
     *
     * @param  array<string, mixed>  $values  key => giá trị (null/'' để xóa)
     */
    public function save(array $values): void
    {
        foreach (self::fields() as $key => $field) {
            $value = $values[$key] ?? null;

            if ($value === null || $value === '') {
                Setting::where('key', $key)->delete();
            } else {
                Setting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
            }
        }

        $this->apply();
    }

    public function reset(): void
    {
        Setting::query()->delete();
        $this->apply();
    }

    /**
     * Giá trị mặc định (từ .env) của một khóa, kể cả khi đang bị ghi đè.
     */
    public function defaultFor(string $key): mixed
    {
        return array_key_exists($key, $this->originals) ? $this->originals[$key] : config(self::configPath($key));
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function cast(array $field, string $value): mixed
    {
        return match ($field['type']) {
            'int' => (int) $value,
            'float' => (float) $value,
            default => $value,
        };
    }
}
