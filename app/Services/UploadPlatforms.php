<?php

namespace App\Services;

/**
 * Danh sách nền tảng upload và các trường cấu hình của từng nền tảng (form + validate dùng chung).
 *
 * mode "single": chỉ một cấu hình mặc định được dùng (R2). Mode "pool": xoay vòng mọi cấu hình đang bật (TikTok).
 */
class UploadPlatforms
{
    /**
     * @return array<string, array{label: string, desc: string, mode: string, fields: array<string, array<string, mixed>>}>
     */
    public static function all(): array
    {
        return [
            'r2' => [
                'label' => 'Cloudflare R2',
                'desc' => 'Nơi browser upload video gốc lên. Dùng cấu hình mặc định đang bật.',
                'mode' => 'single',
                'fields' => [
                    'endpoint' => ['label' => 'Endpoint', 'type' => 'url', 'required' => true, 'placeholder' => 'https://<account-id>.r2.cloudflarestorage.com'],
                    'bucket' => ['label' => 'Bucket', 'type' => 'text', 'required' => true],
                    'region' => ['label' => 'Region', 'type' => 'text', 'required' => false, 'placeholder' => 'auto'],
                    'access_key' => ['label' => 'Access Key ID', 'type' => 'text', 'required' => true, 'secret' => true],
                    'secret_key' => ['label' => 'Secret Access Key', 'type' => 'password', 'required' => true, 'secret' => true],
                ],
            ],
            'tiktok' => [
                'label' => 'TikTok',
                'desc' => 'Pool tài khoản để đẩy segment lên CDN. Hệ thống xoay vòng các tài khoản đang bật và tự loại tài khoản hết hạn.',
                'mode' => 'pool',
                'fields' => [
                    'cookie' => ['label' => 'Cookie', 'type' => 'textarea', 'required' => true, 'secret' => true, 'placeholder' => 'JSON ([{"name":..,"value":..}]) hoặc chuỗi a=b; c=d'],
                    'referer' => ['label' => 'Referer (tùy chọn)', 'type' => 'url', 'required' => false, 'placeholder' => 'https://ads.tiktok.com/'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $platform): ?array
    {
        return self::all()[$platform] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function isSingle(string $platform): bool
    {
        return (self::get($platform)['mode'] ?? null) === 'single';
    }

    /**
     * Quy tắc validate theo trường. Khi sửa, trường bí mật được để trống để giữ nguyên.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(string $platform, bool $editing): array
    {
        $rules = [];

        foreach (self::get($platform)['fields'] as $key => $field) {
            $required = $field['required'] && ! ($editing && ($field['secret'] ?? false));
            $rules["config_data.{$key}"] = [
                $required ? 'required' : 'nullable',
                'string',
                ...($field['type'] === 'url' ? ['url', 'max:255'] : []),
            ];
        }

        return $rules;
    }
}
