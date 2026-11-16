<?php

namespace App\Http\Requests\Admin;

use App\Services\RuntimeSettings;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * Tên input dùng "__" thay cho "." vì dấu chấm xung đột với cú pháp lồng của validator.
     */
    public static function inputName(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = ['settings' => ['required', 'array']];

        foreach (RuntimeSettings::fields() as $key => $field) {
            $rules['settings.'.self::inputName($key)] = match ($field['type']) {
                'select' => ['nullable', Rule::in(array_keys($field['options']))],
                'int' => ['nullable', 'integer', "min:{$field['min']}", "max:{$field['max']}"],
                default => ['nullable', 'numeric', "min:{$field['min']}", "max:{$field['max']}"],
            };
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $names = [];
        foreach (RuntimeSettings::fields() as $key => $field) {
            $names['settings.'.self::inputName($key)] = mb_strtolower($field['label']);
        }

        return $names;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ([['upload.rest.after_min', 'upload.rest.after_max'], ['upload.rest.seconds_min', 'upload.rest.seconds_max']] as [$min, $max]) {
                if ($this->effective($min) > $this->effective($max)) {
                    $validator->errors()->add('settings.'.self::inputName($max), 'Giá trị tối đa phải lớn hơn hoặc bằng giá trị tối thiểu.');
                }
            }
        });
    }

    /**
     * Giá trị sẽ có hiệu lực: giá trị vừa nhập, hoặc mặc định .env nếu để trống.
     */
    private function effective(string $key): float
    {
        $input = $this->input('settings.'.self::inputName($key));

        return (float) (($input === null || $input === '') ? app(RuntimeSettings::class)->defaultFor($key) : $input);
    }

    /**
     * @return array<string, mixed> key (dạng "upload.rest.after_min") => giá trị
     */
    public function values(): array
    {
        $values = [];
        foreach (array_keys(RuntimeSettings::fields()) as $key) {
            $values[$key] = $this->validated('settings')[self::inputName($key)] ?? null;
        }

        return $values;
    }
}
