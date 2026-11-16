<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Models\Setting;
use App\Services\RuntimeSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(RuntimeSettings $settings): View
    {
        $overrides = Setting::pluck('value', 'key');

        $groups = [];
        foreach (RuntimeSettings::fields() as $key => $field) {
            $groups[$field['group']][] = $field + [
                'key' => $key,
                'name' => SettingsRequest::inputName($key),
                'override' => $overrides[$key] ?? null,
                'default' => $settings->defaultFor($key),
            ];
        }

        return view('admin.settings', ['groups' => $groups]);
    }

    public function update(SettingsRequest $request, RuntimeSettings $settings): RedirectResponse
    {
        $settings->save($request->values());

        return back()->with('status', 'Đã lưu cài đặt. Có hiệu lực ngay cho các job và video tiếp theo, không cần restart worker.');
    }

    public function reset(RuntimeSettings $settings): RedirectResponse
    {
        $settings->reset();

        return back()->with('status', 'Đã khôi phục toàn bộ về giá trị mặc định trong .env.');
    }
}
