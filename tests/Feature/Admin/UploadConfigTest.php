<?php

use App\Models\UploadConfig;
use App\Models\User;
use App\Services\Storage\R2Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->r2Payload = [
        'platform' => 'r2', 'name' => 'Main',
        'config_data' => ['endpoint' => 'https://acc.r2.cloudflarestorage.com/', 'bucket' => 'vids', 'region' => '', 'access_key' => 'AK123', 'secret_key' => 'SK456'],
    ];
});

test('only admins can reach upload config pages', function () {
    $this->get('/admin/upload-configs')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/admin/upload-configs')->assertForbidden();
    $this->actingAs(User::factory()->create())->post('/admin/upload-configs', $this->r2Payload)->assertForbidden();

    $this->actingAs($this->admin)->get('/admin/upload-configs')->assertOk();
    $this->actingAs($this->admin)->get('/admin/upload-configs/create?platform=tiktok')->assertOk()->assertSee('Cookie');
    $this->actingAs($this->admin)->get('/admin/upload-configs/create?platform=r2')->assertOk()->assertSee('Bucket');
});

test('first r2 config becomes default and secrets are encrypted at rest', function () {
    $this->actingAs($this->admin)->post('/admin/upload-configs', $this->r2Payload)->assertRedirect();

    $config = UploadConfig::firstOrFail();
    expect($config->is_default)->toBeTrue()
        ->and($config->platform)->toBe('r2')
        ->and($config->data('endpoint'))->toBe('https://acc.r2.cloudflarestorage.com')
        ->and($config->data('region'))->toBe('auto')
        ->and($config->data('secret_key'))->toBe('SK456');

    $raw = DB::table('upload_configs')->value('config_data');
    expect($raw)->not->toContain('SK456')->not->toContain('AK123')->not->toContain('vids');
});

test('tiktok configs form a pool without a default', function () {
    $this->actingAs($this->admin)->post('/admin/upload-configs', ['platform' => 'tiktok', 'name' => 'tk1', 'config_data' => ['cookie' => 'csrftoken=a']])->assertRedirect();
    $this->actingAs($this->admin)->post('/admin/upload-configs', ['platform' => 'tiktok', 'name' => 'tk2', 'config_data' => ['cookie' => 'csrftoken=b']])->assertRedirect();

    expect(UploadConfig::forPlatform('tiktok')->count())->toBe(2);
    expect(UploadConfig::forPlatform('tiktok')->where('is_default', true)->count())->toBe(0);

    $tk = UploadConfig::forPlatform('tiktok')->first();
    $this->actingAs($this->admin)->put("/admin/upload-configs/{$tk->id}/default")->assertStatus(422);
});

test('required fields are validated per platform', function () {
    $this->actingAs($this->admin)->post('/admin/upload-configs', [...$this->r2Payload, 'config_data' => ['bucket' => 'x']])
        ->assertSessionHasErrors(['config_data.endpoint', 'config_data.access_key', 'config_data.secret_key']);
    $this->actingAs($this->admin)->post('/admin/upload-configs', ['platform' => 'tiktok', 'name' => 'x', 'config_data' => ['cookie' => '']])
        ->assertSessionHasErrors('config_data.cookie');
    $this->actingAs($this->admin)->post('/admin/upload-configs', ['platform' => 'bogus', 'name' => 'x', 'config_data' => []])
        ->assertSessionHasErrors('platform');
    expect(UploadConfig::count())->toBe(0);
});

test('editing with blank secrets keeps them and never shows them in the form', function () {
    $config = UploadConfig::factory()->r2()->default()->create(['config_data' => [
        'endpoint' => 'https://a.example.com', 'bucket' => 'b', 'region' => 'auto', 'access_key' => 'OLD-AK', 'secret_key' => 'OLD-SK',
    ]]);

    $this->actingAs($this->admin)->get("/admin/upload-configs/{$config->id}/edit")->assertOk()->assertDontSee('OLD-SK')->assertDontSee('OLD-AK');
    $this->actingAs($this->admin)->put("/admin/upload-configs/{$config->id}", [
        'name' => 'Renamed', 'config_data' => ['endpoint' => 'https://new.example.com', 'bucket' => 'b2', 'region' => 'auto', 'access_key' => '', 'secret_key' => ''],
    ])->assertRedirect();

    $config->refresh();
    expect($config->name)->toBe('Renamed')->and($config->data('bucket'))->toBe('b2')
        ->and($config->data('access_key'))->toBe('OLD-AK')->and($config->data('secret_key'))->toBe('OLD-SK');
});

test('platform of an existing config cannot be changed', function () {
    $config = UploadConfig::factory()->create();

    $this->actingAs($this->admin)->put("/admin/upload-configs/{$config->id}", [
        'platform' => 'r2', 'name' => 'x', 'config_data' => ['cookie' => 'csrftoken=new'],
    ])->assertRedirect();

    expect($config->fresh()->platform)->toBe('tiktok')->and($config->fresh()->data('cookie'))->toBe('csrftoken=new');
});

test('only one r2 config is the default and defaults move on delete or disable', function () {
    $a = UploadConfig::factory()->r2()->default()->create(['name' => 'a']);
    $b = UploadConfig::factory()->r2()->create(['name' => 'b']);

    $this->actingAs($this->admin)->put("/admin/upload-configs/{$b->id}/default")->assertRedirect();
    expect($a->fresh()->is_default)->toBeFalse()->and($b->fresh()->is_default)->toBeTrue();

    $this->actingAs($this->admin)->put("/admin/upload-configs/{$b->id}/toggle")->assertRedirect();
    expect($b->fresh()->status)->toBe('disabled')->and($b->fresh()->is_default)->toBeFalse();
    expect(UploadConfig::defaultFor('r2')->id)->toBe($a->id);

    $this->actingAs($this->admin)->delete("/admin/upload-configs/{$a->id}")->assertRedirect();
    expect(UploadConfig::defaultFor('r2'))->toBeNull();

    $this->actingAs($this->admin)->put("/admin/upload-configs/{$b->id}/toggle")->assertRedirect();
    expect(UploadConfig::defaultFor('r2')->id)->toBe($b->id);
});

test('index filters by platform and search', function () {
    UploadConfig::factory()->r2()->create(['name' => 'r2-prod']);
    UploadConfig::factory()->create(['name' => 'tiktok-one']);

    $this->actingAs($this->admin)->get('/admin/upload-configs?platform=r2')->assertSee('r2-prod')->assertDontSee('tiktok-one');
    $this->actingAs($this->admin)->get('/admin/upload-configs?q=tiktok')->assertSee('tiktok-one')->assertDontSee('r2-prod');
});

test('the r2 service uses the default database config and falls back to env config', function () {
    config(['cdnpro.r2.endpoint' => 'https://env.example.com', 'cdnpro.r2.bucket' => 'envbucket', 'cdnpro.r2.access_key' => 'e', 'cdnpro.r2.secret_key' => 'e']);
    expect(app(R2Service::class)->presignUpload('k.mp4'))->toStartWith('https://env.example.com/envbucket/k.mp4');

    UploadConfig::factory()->r2()->default()->create(['config_data' => [
        'endpoint' => 'https://db.example.com', 'bucket' => 'dbbucket', 'access_key' => 'a', 'secret_key' => 's',
    ]]);
    expect(app(R2Service::class)->presignUpload('k.mp4'))->toStartWith('https://db.example.com/dbbucket/k.mp4');
});

test('r2 connection test reports success, bad keys and server errors', function () {
    $config = UploadConfig::factory()->r2()->create();
    Http::fake(['*' => Http::sequence()->push('', 404)->push('', 403)->push('', 500)]);

    $this->actingAs($this->admin)->post("/admin/upload-configs/{$config->id}/test")->assertSessionHas('status');
    $this->actingAs($this->admin)->post("/admin/upload-configs/{$config->id}/test")->assertSessionHasErrors('config');
    $this->actingAs($this->admin)->post("/admin/upload-configs/{$config->id}/test")->assertSessionHasErrors('config');
});

test('tiktok test uploads a tiny png, revives a dead account and flags failures', function () {
    $config = UploadConfig::factory()->create(['status' => UploadConfig::DEAD, 'last_error' => 'old']);

    Http::fake(['*' => Http::sequence()
        ->push(['code' => 0, 'data' => ['src' => 'https://x/abc~o.png']])
        ->push(['code' => 40001, 'msg' => 'please login'], 200)]);

    $this->actingAs($this->admin)->post("/admin/upload-configs/{$config->id}/test")->assertSessionHas('status');
    expect($config->fresh()->status)->toBe('active')->and($config->fresh()->last_error)->toBeNull();

    $this->actingAs($this->admin)->post("/admin/upload-configs/{$config->id}/test")->assertSessionHasErrors('config');
    expect($config->fresh()->status)->toBe('dead')->and($config->fresh()->last_error)->toContain('login');
});

test('custom referer is sent to tiktok', function () {
    $config = UploadConfig::factory()->create(['config_data' => ['cookie' => 'csrftoken=a', 'referer' => 'https://ads.example.org/']]);
    Http::fake(['*' => Http::response(['code' => 0, 'data' => ['src' => 'https://x/a~o.png']])]);

    $this->actingAs($this->admin)->post("/admin/upload-configs/{$config->id}/test");

    Http::assertSent(fn ($request) => $request->hasHeader('Referer', 'https://ads.example.org/'));
});
