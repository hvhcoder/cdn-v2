<?php

use App\Models\UploadConfig;
use App\Services\Cdn\AccountPool;
use App\Services\Cdn\TikTokUploader;
use App\Services\Cdn\UploadFailedException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->uploader = new TikTokUploader;
    $this->file = tempnam(sys_get_temp_dir(), 'seg');
    file_put_contents($this->file, 'png-bytes');
});

afterEach(fn () => @unlink($this->file));

test('parses cookie formats and extracts csrf token', function () {
    expect($this->uploader->parseCookie('a=1; csrftoken=tok; b=2'))->toBe(['a=1; csrftoken=tok; b=2', 'tok']);

    [$cookie, $csrf] = $this->uploader->parseCookie('[{"name":"csrftoken","value":"x1"},{"name":"sid","value":"s"}]');
    expect($cookie)->toBe('csrftoken=x1; sid=s')->and($csrf)->toBe('x1');

    [$cookie, $csrf] = $this->uploader->parseCookie('{"cookies":[{"name":"csrftoken","value":"y2"}]}');
    expect($cookie)->toBe('csrftoken=y2')->and($csrf)->toBe('y2');
});

test('uploads a streamed file with the right headers and returns the cdn url', function () {
    Http::fake(['*' => Http::response(['code' => 0, 'data' => ['src' => 'https://x/y/abc123~tplv-origin.png']])]);
    $account = UploadConfig::factory()->make(['config_data' => ['cookie' => 'csrftoken=tok; sid=1']]);

    $url = $this->uploader->upload($this->file, 'seg.png', $account);

    expect($url)->toBe('https://p16-lp-sg.ibyteimg.com/origin/tos-alisg-i-375lmtcpo0-sg/abc123');

    Http::assertSent(fn (Request $request) => $request->url() === config('cdnpro.tiktok.endpoint')
        && $request->hasHeader('x-csrftoken', 'tok')
        && $request->hasHeader('Cookie', 'csrftoken=tok; sid=1')
        && $request->isMultipart());
});

test('supports the uri and original_img_uri response shapes', function () {
    Http::fake(['*' => Http::sequence()
        ->push(['code' => 0, 'data' => ['uri' => 'ad-site-i18n-sg/img1']])
        ->push(['code' => 0, 'data' => ['original_img_uri' => 'tos/img2']])]);
    $account = UploadConfig::factory()->make();

    expect($this->uploader->upload($this->file, 'a.png', $account))->toBe('https://p16-tiktokcdn-com.akamaized.net/origin/ad-site-i18n/img1');
    expect($this->uploader->upload($this->file, 'b.png', $account))->toBe('https://p16-tiktok-ads-sg.tiktokcdn.com/obj/tos/img2');
});

test('a non zero code is a failure, never a guessed url', function () {
    Http::fake(['*' => Http::response(['code' => 5, 'msg' => 'too large'])]);

    try {
        $this->uploader->upload($this->file, 'a.png', UploadConfig::factory()->make());
    } catch (UploadFailedException $e) {
        expect($e->getMessage())->toContain('too large')->and($e->accountIsDead)->toBeFalse();

        return;
    }

    $this->fail('Expected UploadFailedException');
});

test('auth style errors flag the account as dead', function () {
    Http::fake(['*' => Http::response(['code' => 40001, 'msg' => 'please login'], 200)]);

    try {
        $this->uploader->upload($this->file, 'a.png', UploadConfig::factory()->make());
    } catch (UploadFailedException $e) {
        expect($e->accountIsDead)->toBeTrue();

        return;
    }

    $this->fail('Expected UploadFailedException');
});

test('account pool skips dead and excluded accounts and prefers least recently used', function () {
    $dead = UploadConfig::factory()->create(['status' => UploadConfig::DEAD]);
    $recent = UploadConfig::factory()->create(['last_used_at' => now()]);
    $fresh = UploadConfig::factory()->create();
    $pool = new AccountPool;

    expect($pool->next()->id)->toBe($fresh->id);
    expect($pool->next([$fresh->id])->id)->toBe($recent->id);
    expect($pool->next([$fresh->id, $recent->id]))->toBeNull();
    expect($dead->status)->toBe('dead');
});

test('cookie is encrypted at rest', function () {
    $account = UploadConfig::factory()->create(['config_data' => ['cookie' => 'csrftoken=secret-value']]);

    $raw = DB::table('upload_configs')->where('id', $account->id)->value('config_data');

    expect($raw)->not->toContain('secret-value');
    expect($account->fresh()->data('cookie'))->toBe('csrftoken=secret-value');
});
