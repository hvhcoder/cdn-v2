<?php

use App\Models\Folder;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Video;
use App\Services\Storage\R2Service;

function proUser(bool $vip = true): User
{
    $user = $vip ? User::factory()->vip()->create() : User::factory()->create();
    Subscription::factory()->for($user)->for(Plan::factory()->create(['can_use_folders' => true]))->create();

    return $user;
}

beforeEach(function () {
    Plan::factory()->free()->create();
    $this->user = proUser();
});

test('guests are redirected and the manager page renders for owners', function () {
    $this->get('/folders')->assertRedirect('/login');

    Folder::factory()->for($this->user)->create(['name' => 'Phim bộ']);
    $this->actingAs($this->user)->get('/folders')->assertOk()->assertSee('Phim bộ')->assertSee('Chưa phân loại');
});

test('a plan without folders can view but not create, rename or move into folders', function () {
    $free = User::factory()->vip()->create();
    $own = Folder::factory()->for($free)->create(['name' => 'Cũ']);

    $this->actingAs($free)->get('/folders')->assertOk()->assertSee('không bao gồm thư mục');
    $this->actingAs($free)->post('/folders', ['name' => 'Mới'])->assertSessionHasErrors('folder');
    $this->actingAs($free)->put("/folders/{$own->id}", ['name' => 'Đổi'])->assertSessionHasErrors('folder');
    expect(Folder::count())->toBe(1)->and($own->fresh()->name)->toBe('Cũ');

    $video = Video::factory()->for($free)->create();
    $this->actingAs($free)->postJson('/videos/move', ['video_ids' => [$video->uuid], 'folder_id' => $own->id])->assertStatus(422);
});

test('admins can always use folders', function () {
    $this->actingAs(User::factory()->admin()->create())->post('/folders', ['name' => 'Admin folder'])->assertSessionHasNoErrors();
    expect(Folder::where('name', 'Admin folder')->exists())->toBeTrue();
});

test('folders can be created nested, renamed, recolored and moved', function () {
    $this->actingAs($this->user)->post('/folders', ['name' => 'Phim', 'color' => 'blue'])->assertRedirect();
    $root = Folder::where('name', 'Phim')->firstOrFail();
    expect($root->color)->toBe('blue')->and($root->parent_id)->toBeNull();

    $this->actingAs($this->user)->post('/folders', ['name' => 'Hàn Quốc', 'parent_id' => $root->id])->assertSessionHasNoErrors();
    $child = Folder::where('name', 'Hàn Quốc')->firstOrFail();
    expect($child->parent_id)->toBe($root->id);

    $this->actingAs($this->user)->put("/folders/{$child->id}", ['name' => 'Phim Hàn', 'parent_id' => $root->id, 'color' => 'rose'])->assertSessionHasNoErrors();
    expect($child->fresh()->name)->toBe('Phim Hàn')->and($child->fresh()->color)->toBe('rose');

    $other = Folder::factory()->for($this->user)->create(['name' => 'Nhạc']);
    $this->actingAs($this->user)->put("/folders/{$child->id}", ['name' => 'Phim Hàn', 'parent_id' => $other->id])->assertSessionHasNoErrors();
    expect($child->fresh()->parent_id)->toBe($other->id);

    $this->actingAs($this->user)->put("/folders/{$child->id}", ['name' => 'Phim Hàn', 'parent_id' => null])->assertSessionHasNoErrors();
    expect($child->fresh()->parent_id)->toBeNull();
});

test('names are validated and unique only among siblings', function () {
    $this->actingAs($this->user)->post('/folders', ['name' => ''])->assertSessionHasErrors('name');
    $this->actingAs($this->user)->post('/folders', ['name' => str_repeat('a', 101)])->assertSessionHasErrors('name');
    $this->actingAs($this->user)->post('/folders', ['name' => 'X', 'color' => 'pink'])->assertSessionHasErrors('color');

    $a = Folder::factory()->for($this->user)->create(['name' => 'Trùng']);
    $b = Folder::factory()->for($this->user)->create(['name' => 'Cha']);

    $this->actingAs($this->user)->post('/folders', ['name' => 'Trùng'])->assertSessionHasErrors('name');            // cùng thư mục gốc
    $this->actingAs($this->user)->post('/folders', ['name' => 'Trùng', 'parent_id' => $b->id])->assertSessionHasNoErrors(); // khác cha thì được
    $this->actingAs($this->user)->post('/folders', ['name' => 'Trùng', 'parent_id' => $b->id])->assertSessionHasErrors('name');

    $this->actingAs($this->user)->put("/folders/{$a->id}", ['name' => 'Trùng'])->assertSessionHasNoErrors(); // giữ nguyên tên của chính nó
    expect(Folder::where('name', 'Trùng')->count())->toBe(2);

    // Hai user khác nhau được dùng cùng một tên.
    $this->actingAs(proUser())->post('/folders', ['name' => 'Trùng'])->assertSessionHasNoErrors();
});

test('a folder cannot be moved into itself or into its own descendants', function () {
    $a = Folder::factory()->for($this->user)->create(['name' => 'A']);
    $b = Folder::factory()->for($this->user)->create(['name' => 'B', 'parent_id' => $a->id]);
    $c = Folder::factory()->for($this->user)->create(['name' => 'C', 'parent_id' => $b->id]);

    $this->actingAs($this->user)->put("/folders/{$a->id}", ['name' => 'A', 'parent_id' => $a->id])->assertSessionHasErrors('parent_id');
    $this->actingAs($this->user)->put("/folders/{$a->id}", ['name' => 'A', 'parent_id' => $c->id])->assertSessionHasErrors('parent_id');
    $this->actingAs($this->user)->put("/folders/{$b->id}", ['name' => 'B', 'parent_id' => $c->id])->assertSessionHasErrors('parent_id');

    expect($a->fresh()->parent_id)->toBeNull()->and($b->fresh()->parent_id)->toBe($a->id);

    // Chuyển C lên cùng cấp với A là hợp lệ.
    $this->actingAs($this->user)->put("/folders/{$c->id}", ['name' => 'C', 'parent_id' => null])->assertSessionHasNoErrors();
    expect($c->fresh()->parent_id)->toBeNull();
});

test('the nesting depth is limited, including when moving a whole subtree', function () {
    config(['cdnpro.folders.max_depth' => 3]);
    $l1 = Folder::factory()->for($this->user)->create(['name' => 'L1']);
    $l2 = Folder::factory()->for($this->user)->create(['name' => 'L2', 'parent_id' => $l1->id]);
    $l3 = Folder::factory()->for($this->user)->create(['name' => 'L3', 'parent_id' => $l2->id]);

    $this->actingAs($this->user)->post('/folders', ['name' => 'L4', 'parent_id' => $l3->id])->assertSessionHasErrors('parent_id');
    $this->actingAs($this->user)->post('/folders', ['name' => 'L3b', 'parent_id' => $l2->id])->assertSessionHasNoErrors();

    // Nhánh L1->L2->L3 (cao 3 cấp) không thể đặt dưới một thư mục cấp 1 khác.
    $other = Folder::factory()->for($this->user)->create(['name' => 'Other']);
    $this->actingAs($this->user)->put("/folders/{$l1->id}", ['name' => 'L1', 'parent_id' => $other->id])->assertSessionHasErrors('parent_id');
    expect($l1->fresh()->parent_id)->toBeNull();
});

test('model helpers compute ancestry, breadcrumb, depth, height and descendants', function () {
    $a = Folder::factory()->for($this->user)->create(['name' => 'A']);
    $b = Folder::factory()->for($this->user)->create(['name' => 'B', 'parent_id' => $a->id]);
    $c = Folder::factory()->for($this->user)->create(['name' => 'C', 'parent_id' => $b->id]);
    $x = Folder::factory()->for($this->user)->create(['name' => 'X']);

    expect($a->isAncestorOfOrSelf($c))->toBeTrue()->and($a->isAncestorOfOrSelf($a))->toBeTrue()
        ->and($c->isAncestorOfOrSelf($a))->toBeFalse()->and($x->isAncestorOfOrSelf($c))->toBeFalse();
    expect($c->breadcrumb()->pluck('name')->all())->toBe(['A', 'B', 'C']);
    expect($c->depth())->toBe(3)->and($a->depth())->toBe(1);
    expect($a->height())->toBe(3)->and($c->height())->toBe(1)->and($x->height())->toBe(1);
    expect($a->selfAndDescendantIds())->toEqualCanonicalizing([$a->id, $b->id, $c->id]);
});

test('folders belong to their owner: other users cannot touch them', function () {
    $stranger = proUser();
    $mine = Folder::factory()->for($this->user)->create(['name' => 'Mine']);

    $this->actingAs($stranger)->put("/folders/{$mine->id}", ['name' => 'Hacked'])->assertForbidden();
    $this->actingAs($stranger)->delete("/folders/{$mine->id}", ['mode' => 'keep'])->assertForbidden();
    $this->actingAs($stranger)->getJson("/folders/{$mine->id}/links")->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->put("/folders/{$mine->id}", ['name' => 'Hacked'])->assertForbidden();

    expect($mine->fresh()->name)->toBe('Mine');

    // Không thể gắn thư mục của mình vào thư mục cha của người khác.
    $this->actingAs($stranger)->post('/folders', ['name' => 'Sneaky', 'parent_id' => $mine->id])->assertSessionHasErrors('parent_id');
});

test('deleting a folder in keep mode moves its videos and subfolders up to the parent', function () {
    $parent = Folder::factory()->for($this->user)->create(['name' => 'Parent']);
    $folder = Folder::factory()->for($this->user)->create(['name' => 'Mid', 'parent_id' => $parent->id]);
    $child = Folder::factory()->for($this->user)->create(['name' => 'Leaf', 'parent_id' => $folder->id]);
    $inMid = Video::factory()->for($this->user)->create(['folder_id' => $folder->id]);
    $inLeaf = Video::factory()->for($this->user)->create(['folder_id' => $child->id]);

    $this->actingAs($this->user)->delete("/folders/{$folder->id}", ['mode' => 'keep'])->assertRedirect(route('folders.index'))->assertSessionHas('status');

    expect(Folder::find($folder->id))->toBeNull();
    expect($inMid->fresh()->folder_id)->toBe($parent->id);
    expect($child->fresh()->parent_id)->toBe($parent->id)->and($inLeaf->fresh()->folder_id)->toBe($child->id);
});

test('deleting a root folder in keep mode leaves the videos unfiled', function () {
    $folder = Folder::factory()->for($this->user)->create();
    $video = Video::factory()->for($this->user)->create(['folder_id' => $folder->id]);

    $this->actingAs($this->user)->delete("/folders/{$folder->id}", ['mode' => 'keep']);

    expect($video->fresh())->not->toBeNull()->and($video->fresh()->folder_id)->toBeNull();
});

test('deleting a folder in delete_videos mode removes the videos of the whole branch', function () {
    $r2 = Mockery::mock(R2Service::class)->makePartial();
    $r2->shouldReceive('delete')->once()->with('raw/orphan.mp4');
    app()->instance(R2Service::class, $r2);

    $folder = Folder::factory()->for($this->user)->create();
    $child = Folder::factory()->for($this->user)->create(['parent_id' => $folder->id]);
    $a = Video::factory()->for($this->user)->create(['folder_id' => $folder->id, 'status' => Video::FAILED, 'r2_path' => 'raw/orphan.mp4']);
    $b = Video::factory()->for($this->user)->create(['folder_id' => $child->id]);
    $outside = Video::factory()->for($this->user)->create();

    $this->actingAs($this->user)->delete("/folders/{$folder->id}", ['mode' => 'delete_videos'])
        ->assertRedirect(route('folders.index'))->assertSessionHas('status');

    expect(Folder::count())->toBe(0)->and(Video::find($a->id))->toBeNull()->and(Video::find($b->id))->toBeNull();
    expect(Video::find($outside->id))->not->toBeNull();
});

test('delete_videos refuses when a video in the branch is still processing', function () {
    $folder = Folder::factory()->for($this->user)->create();
    $done = Video::factory()->for($this->user)->create(['folder_id' => $folder->id, 'status' => Video::COMPLETED]);
    Video::factory()->for($this->user)->create(['folder_id' => $folder->id, 'status' => Video::PROCESSING]);

    $this->actingAs($this->user)->delete("/folders/{$folder->id}", ['mode' => 'delete_videos'])->assertSessionHasErrors('folder');

    expect(Folder::find($folder->id))->not->toBeNull()->and(Video::find($done->id))->not->toBeNull();
});

test('deleting requires a valid mode', function () {
    $folder = Folder::factory()->for($this->user)->create();

    $this->actingAs($this->user)->delete("/folders/{$folder->id}")->assertSessionHasErrors('mode');
    $this->actingAs($this->user)->delete("/folders/{$folder->id}", ['mode' => 'nuke'])->assertSessionHasErrors('mode');
    expect(Folder::find($folder->id))->not->toBeNull();
});

test('videos can be bulk moved into a folder and back out', function () {
    $folder = Folder::factory()->for($this->user)->create();
    $videos = Video::factory()->count(3)->for($this->user)->create();
    $ids = $videos->pluck('uuid')->all();

    $this->actingAs($this->user)->postJson('/videos/move', ['video_ids' => $ids, 'folder_id' => $folder->id])->assertOk()->assertJson(['moved' => 3]);
    expect(Video::where('folder_id', $folder->id)->count())->toBe(3);

    $this->actingAs($this->user)->post('/videos/move', ['video_ids' => [$ids[0]], 'folder_id' => ''])->assertRedirect()->assertSessionHas('status');
    expect($videos[0]->fresh()->folder_id)->toBeNull()->and($videos[1]->fresh()->folder_id)->toBe($folder->id);
});

test('bulk move is tenant safe and validated', function () {
    $stranger = proUser();
    $mine = Folder::factory()->for($this->user)->create();
    $theirs = Folder::factory()->for($stranger)->create();
    $myVideo = Video::factory()->for($this->user)->create();
    $theirVideo = Video::factory()->for($stranger)->create();

    // Không thể chuyển video của người khác, kể cả khi biết uuid.
    $this->actingAs($this->user)->postJson('/videos/move', ['video_ids' => [$theirVideo->uuid], 'folder_id' => $mine->id])->assertOk()->assertJson(['moved' => 0]);
    expect($theirVideo->fresh()->folder_id)->toBeNull();

    // Không thể chuyển vào thư mục của người khác.
    $this->actingAs($this->user)->postJson('/videos/move', ['video_ids' => [$myVideo->uuid], 'folder_id' => $theirs->id])->assertStatus(422);
    expect($myVideo->fresh()->folder_id)->toBeNull();

    $this->actingAs($this->user)->postJson('/videos/move', ['video_ids' => [], 'folder_id' => $mine->id])->assertStatus(422);
    $this->actingAs($this->user)->postJson('/videos/move', ['folder_id' => $mine->id])->assertStatus(422);
});

test('folder links collect only completed videos and can include subfolders', function () {
    $folder = Folder::factory()->for($this->user)->create();
    $child = Folder::factory()->for($this->user)->create(['parent_id' => $folder->id]);
    $a = Video::factory()->for($this->user)->create(['folder_id' => $folder->id, 'status' => Video::COMPLETED, 'title' => 'A']);
    $b = Video::factory()->for($this->user)->create(['folder_id' => $child->id, 'status' => Video::COMPLETED, 'title' => 'B']);
    Video::factory()->for($this->user)->create(['folder_id' => $folder->id, 'status' => Video::PROCESSING]);
    Video::factory()->for($this->user)->create(['status' => Video::COMPLETED]); // ngoài thư mục

    $direct = $this->actingAs($this->user)->getJson("/folders/{$folder->id}/links?type=m3u8")->assertOk()->json();
    expect($direct['count'])->toBe(1)->and($direct['text'])->toBe(route('m3u8', $a));

    $deep = $this->actingAs($this->user)->getJson("/folders/{$folder->id}/links?type=m3u8&recursive=1")->json();
    expect($deep['count'])->toBe(2)->and($deep['text'])->toBe(route('m3u8', $a)."\n".route('m3u8', $b));

    $embed = $this->actingAs($this->user)->getJson("/folders/{$folder->id}/links?type=embed")->json();
    expect($embed['text'])->toBe(route('embed', $a));

    $iframe = $this->actingAs($this->user)->getJson("/folders/{$folder->id}/links?type=iframe")->json();
    expect($iframe['text'])->toStartWith('<iframe src="'.route('embed', $a).'"');

    $this->actingAs($this->user)->getJson("/folders/{$folder->id}/links?type=bogus")->assertStatus(422);
});

test('an empty folder has no links', function () {
    $folder = Folder::factory()->for($this->user)->create();

    $this->actingAs($this->user)->getJson("/folders/{$folder->id}/links")->assertOk()->assertJson(['count' => 0, 'text' => '']);
});

test('the video list can be filtered by folder or by unfiled and shows breadcrumb and subfolders', function () {
    $movies = Folder::factory()->for($this->user)->create(['name' => 'Phim']);
    $korean = Folder::factory()->for($this->user)->create(['name' => 'Hàn Quốc', 'parent_id' => $movies->id]);
    Video::factory()->for($this->user)->create(['folder_id' => $movies->id, 'title' => 'Trong Phim']);
    Video::factory()->for($this->user)->create(['folder_id' => $korean->id, 'title' => 'Trong Hàn']);
    Video::factory()->for($this->user)->create(['title' => 'Lẻ loi']);

    $this->actingAs($this->user)->get("/videos?folder={$movies->id}")->assertOk()
        ->assertSee('Trong Phim')->assertDontSee('Trong Hàn')->assertDontSee('Lẻ loi')
        ->assertSee('Hàn Quốc'); // thẻ thư mục con

    $this->actingAs($this->user)->get("/videos?folder={$korean->id}")->assertOk()
        ->assertSee('Trong Hàn')->assertDontSee('Trong Phim')->assertSeeInOrder(['Video của tôi', 'Phim', 'Hàn Quốc']);

    $this->actingAs($this->user)->get('/videos?folder=none')->assertOk()->assertSee('Lẻ loi')->assertDontSee('Trong Phim')->assertSee('Chưa phân loại');
    $this->actingAs($this->user)->get('/videos')->assertSee('Lẻ loi')->assertSee('Trong Phim')->assertSee('Trong Hàn');
});

test('filtering by another users folder shows nothing special and leaks nothing', function () {
    $theirs = Folder::factory()->create(['name' => 'Bí mật']);
    Video::factory()->for($theirs->user)->create(['folder_id' => $theirs->id, 'title' => 'Video bí mật']);
    Video::factory()->for($this->user)->create(['title' => 'Của tôi']);

    $this->actingAs($this->user)->get("/videos?folder={$theirs->id}")->assertOk()->assertSee('Của tôi')->assertDontSee('Video bí mật')->assertDontSee('Bí mật');
});

test('the sidebar tree on the video page shows folder counts', function () {
    $folder = Folder::factory()->for($this->user)->create(['name' => 'Sidebar']);
    Video::factory()->count(2)->for($this->user)->create(['folder_id' => $folder->id]);

    $this->actingAs($this->user)->get('/videos')->assertOk()->assertSee('Sidebar')->assertSeeInOrder(['Sidebar', '2']);
});

test('uploads can target a folder and are validated against ownership and plan', function () {
    $r2 = Mockery::mock(R2Service::class)->makePartial();
    $r2->shouldReceive('presignUpload')->andReturn('https://r2.test/put');
    app()->instance(R2Service::class, $r2);

    $mine = Folder::factory()->for($this->user)->create();
    $theirs = Folder::factory()->create();

    $uuid = $this->actingAs($this->user)->postJson('/videos/presign', ['filename' => 'a.mp4', 'size' => 1000, 'folder_id' => $mine->id])
        ->assertCreated()->json('video');
    expect(Video::where('uuid', $uuid)->value('folder_id'))->toBe($mine->id);

    $this->actingAs($this->user)->postJson('/videos/presign', ['filename' => 'b.mp4', 'size' => 1000, 'folder_id' => $theirs->id])->assertStatus(422);
    expect(Video::count())->toBe(1);

    $free = User::factory()->vip()->create();
    $freeFolder = Folder::factory()->for($free)->create();
    $this->actingAs($free)->postJson('/videos/presign', ['filename' => 'c.mp4', 'size' => 1000, 'folder_id' => $freeFolder->id])->assertStatus(422);

    // Không chọn thư mục vẫn upload bình thường.
    $this->actingAs($free)->postJson('/videos/presign', ['filename' => 'd.mp4', 'size' => 1000])->assertCreated();
});

test('the upload page offers the folder picker and preselects the folder from the link', function () {
    $folder = Folder::factory()->for($this->user)->create(['name' => 'Chọn sẵn']);

    $this->actingAs($this->user)->get("/videos/create?folder={$folder->id}")->assertOk()
        ->assertSee('Lưu vào thư mục')->assertSee('value="'.$folder->id.'" selected', false);
});

test('the video page lets the owner move the video and shows the folder breadcrumb', function () {
    $parent = Folder::factory()->for($this->user)->create(['name' => 'Cha']);
    $folder = Folder::factory()->for($this->user)->create(['name' => 'Con', 'parent_id' => $parent->id]);
    $video = Video::factory()->for($this->user)->create(['folder_id' => $folder->id]);

    $this->actingAs($this->user)->get("/videos/{$video->uuid}")->assertOk()
        ->assertSeeInOrder(['Video của tôi', 'Cha', 'Con'])->assertSee(route('videos.move'));
});

test('the sidebar has a folders link', function () {
    $this->actingAs($this->user)->get('/dashboard')->assertSee('Thư mục')->assertSee(route('folders.index'));
});

test('deleting a user removes their folders but not other users folders', function () {
    $mine = Folder::factory()->for($this->user)->create();
    $other = Folder::factory()->create();

    $this->user->delete();

    expect(Folder::find($mine->id))->toBeNull()->and(Folder::find($other->id))->not->toBeNull();
});
