<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadConfigRequest;
use App\Models\UploadConfig;
use App\Services\Cdn\CdnUploader;
use App\Services\Cdn\UploadFailedException;
use App\Services\Storage\R2Service;
use App\Services\UploadPlatforms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UploadConfigController extends Controller
{
    public function index(Request $request): View
    {
        $platform = $request->query('platform');

        return view('admin.upload-configs.index', [
            'platforms' => UploadPlatforms::all(),
            'currentPlatform' => UploadPlatforms::get((string) $platform) ? $platform : null,
            'configs' => UploadConfig::query()
                ->when(UploadPlatforms::get((string) $platform), fn ($q) => $q->forPlatform($platform))
                ->when($request->query('q'), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
                ->orderBy('platform')->orderByDesc('is_default')->latest()
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $platform = UploadPlatforms::get((string) $request->query('platform')) ? $request->query('platform') : UploadConfig::R2;

        return view('admin.upload-configs.form', [
            'config' => new UploadConfig(['platform' => $platform, 'status' => UploadConfig::ACTIVE, 'config_data' => []]),
            'platforms' => UploadPlatforms::all(),
        ]);
    }

    public function store(UploadConfigRequest $request): RedirectResponse
    {
        $platform = $request->validated('platform');

        $config = UploadConfig::create([
            'platform' => $platform,
            'name' => $request->validated('name'),
            'status' => UploadConfig::ACTIVE,
            'config_data' => $request->configData($platform),
        ]);

        UploadConfig::ensureDefault($platform);

        return redirect()->route('admin.upload-configs.index', ['platform' => $platform])
            ->with('status', "Đã thêm cấu hình \"{$config->name}\".");
    }

    public function edit(UploadConfig $uploadConfig): View
    {
        return view('admin.upload-configs.form', ['config' => $uploadConfig, 'platforms' => UploadPlatforms::all()]);
    }

    public function update(UploadConfigRequest $request, UploadConfig $uploadConfig): RedirectResponse
    {
        $uploadConfig->update([
            'name' => $request->validated('name'),
            'config_data' => $request->configData($uploadConfig->platform, $uploadConfig->config_data ?? []),
        ]);

        return redirect()->route('admin.upload-configs.index', ['platform' => $uploadConfig->platform])
            ->with('status', 'Đã cập nhật cấu hình.');
    }

    public function destroy(UploadConfig $uploadConfig): RedirectResponse
    {
        $uploadConfig->delete();
        UploadConfig::ensureDefault($uploadConfig->platform);

        return back()->with('status', 'Đã xóa cấu hình.');
    }

    public function toggle(UploadConfig $uploadConfig): RedirectResponse
    {
        $enable = $uploadConfig->status !== UploadConfig::ACTIVE;

        $uploadConfig->update(['status' => $enable ? UploadConfig::ACTIVE : UploadConfig::DISABLED, 'last_error' => null]);

        if (! $enable && $uploadConfig->is_default) {
            $uploadConfig->forceFill(['is_default' => false])->save();
        }
        UploadConfig::ensureDefault($uploadConfig->platform);

        return back()->with('status', $enable ? 'Đã bật cấu hình.' : 'Đã tắt cấu hình.');
    }

    public function setDefault(UploadConfig $uploadConfig): RedirectResponse
    {
        abort_unless(UploadPlatforms::isSingle($uploadConfig->platform), 422, 'Nền tảng này dùng pool, không có cấu hình mặc định.');

        $uploadConfig->makeDefault();

        return back()->with('status', "Đang dùng \"{$uploadConfig->name}\" làm mặc định.");
    }

    /**
     * R2: HEAD thử bucket. TikTok: upload thử một ảnh PNG 1x1; thành công sẽ bật lại tài khoản đã bị đánh dấu chết.
     */
    public function test(UploadConfig $uploadConfig, CdnUploader $uploader): RedirectResponse
    {
        [$ok, $message] = match ($uploadConfig->platform) {
            UploadConfig::R2 => array_values((new R2Service($uploadConfig->toR2Config()))->testConnection()),
            UploadConfig::TIKTOK => $this->testTikTok($uploadConfig, $uploader),
            default => [false, 'Nền tảng chưa hỗ trợ kiểm tra.'],
        };

        return $ok
            ? back()->with('status', "{$uploadConfig->name}: {$message}")
            : back()->withErrors(['config' => "{$uploadConfig->name}: {$message}"]);
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function testTikTok(UploadConfig $config, CdnUploader $uploader): array
    {
        $file = tempnam(sys_get_temp_dir(), 'tt');
        ob_start();
        imagepng(imagecreatetruecolor(1, 1));
        file_put_contents($file, ob_get_clean());

        try {
            $url = $uploader->upload($file, 'healthcheck.png', $config);
            $config->update(['status' => UploadConfig::ACTIVE, 'last_error' => null]);

            return [true, "Upload thử thành công: {$url}"];
        } catch (UploadFailedException $e) {
            $e->accountIsDead ? $config->markDead($e->getMessage()) : $config->update(['last_error' => mb_substr($e->getMessage(), 0, 500)]);

            return [false, $e->getMessage()];
        } finally {
            @unlink($file);
        }
    }
}
