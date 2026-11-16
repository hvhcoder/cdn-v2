<?php

namespace App\Http\Requests\Admin;

use App\Models\UploadConfig;
use App\Services\UploadPlatforms;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $editing = $this->route('uploadConfig') instanceof UploadConfig;
        $platform = $editing ? $this->route('uploadConfig')->platform : (string) $this->input('platform');

        return [
            'platform' => [$editing ? 'nullable' : 'required', Rule::in(UploadPlatforms::keys())],
            'name' => ['required', 'string', 'max:100'],
            'config_data' => ['required', 'array'],
            ...(UploadPlatforms::get($platform) ? UploadPlatforms::rules($platform, $editing) : []),
        ];
    }

    /**
     * Làm sạch dữ liệu cấu hình; khi sửa, trường bí mật để trống thì giữ giá trị cũ.
     *
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    public function configData(string $platform, array $existing = []): array
    {
        $input = $this->validated('config_data') ?? [];
        $clean = [];

        foreach (UploadPlatforms::get($platform)['fields'] as $key => $field) {
            $value = trim((string) ($input[$key] ?? ''));

            if ($value === '' && ($field['secret'] ?? false)) {
                $value = (string) ($existing[$key] ?? '');
            }

            if ($value !== '') {
                $clean[$key] = in_array($key, ['endpoint', 'referer'], true) ? rtrim($value, '/') : $value;
            }
        }

        if ($platform === UploadConfig::R2) {
            $clean['region'] ??= 'auto';
        }

        return $clean;
    }
}
