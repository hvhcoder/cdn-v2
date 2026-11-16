<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('plans', 'slug')->ignore($this->route('plan'))],
            'price' => ['required', 'integer', 'min:0'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'storage_limit_gb' => ['nullable', 'integer', 'min:1'],
            'max_videos' => ['nullable', 'integer', 'min:1'],
            'max_upload_size_mb' => ['nullable', 'integer', 'min:1'],
            'monthly_encode_minutes' => ['nullable', 'integer', 'min:1'],
            'embed_domain_limit' => ['nullable', 'integer', 'min:0'],
            'sort' => ['required', 'integer', 'min:0'],
            'can_use_folders' => ['boolean'],
            'is_popular' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'can_use_folders' => $this->boolean('can_use_folders'),
            'is_popular' => $this->boolean('is_popular'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
