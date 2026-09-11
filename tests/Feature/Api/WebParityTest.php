<?php

use App\Models\Lembur;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/** @return array<string, string> */
function parityTokenHeaders(User $user): array
{
    Auth::forgetGuards();

    return ['Authorization' => 'Bearer '.$user->createToken('parity')->plainTextToken];
}

/** @return array<string, mixed> */
function parityLemburSummary(array $row, array $included): array
{
    $attributes = $row['attributes'];
    $user = collect($included)->firstWhere('id', $row['relationships']['user']['data']['id'])['attributes'];

    return [
        'id' => (int) $row['id'],
        ...collect($attributes)->only(['uuid', 'tanggal', 'nama_kegiatan', 'lokasi_kegiatan', 'waktu_pulang', 'status', 'upah', 'can_lock', 'can_delete'])->all(),
        'jenis_hari' => ['hari_libur' => 'libur', 'hari_kerja' => 'kerja'][$attributes['jenis_hari']],
        'pegawai' => collect($user)->only(['uuid', 'name', 'nip', 'jabatan'])->all(),
    ];
}

test('lembur API preserves all list props and query results from web', function (array $query) {
    $this->travelTo('2026-09-15');
    $admin = User::factory()->administrator()->create();
    $pegawai = User::factory()->outsourcing()->create(['name' => 'Ayu']);
    User::factory()->outsourcing()->create(['name' => 'Inactive', 'is_active' => false]);
    User::factory()->outsourcing()->count(17)->create()->each(function (User $employee): void {
        Lembur::factory()->for($employee)->create(['tanggal_kegiatan' => '2026-09-04', 'nama_kegiatan' => 'Rapat']);
    });
    Lembur::factory()->for($pegawai)->create(['tanggal_kegiatan' => '2026-09-05', 'lokasi_kegiatan' => 'Merdeka']);
    Lembur::factory()->for($pegawai)->draft()->create(['tanggal_kegiatan' => '2026-09-06']);
    Lembur::factory()->for($pegawai)->create(['tanggal_kegiatan' => '2025-09-05']);
    $suffix = '?'.http_build_query($query);

    $web = $this->actingAs($admin)->get('/dashboard/lemburs'.$suffix)->assertOk()->inertiaProps();
    $api = $this->getJson('/api/admin/lemburs'.$suffix, parityTokenHeaders($admin))->assertOk();

    $api->assertJsonPath('meta.filters', $web['filters'])
        ->assertJsonPath('meta.pegawaiOptions', $web['pegawaiOptions'])
        ->assertJsonPath('meta.total', $web['lemburs']['total'])
        ->assertJsonPath('meta.per_page', $web['lemburs']['per_page'])
        ->assertJsonPath('meta.current_page', $web['lemburs']['current_page']);
    $rows = array_map(fn (array $row): array => parityLemburSummary($row, $api->json('included', [])), $api->json('data'));
    expect($rows)->toEqual($web['lemburs']['data']);
})->with([
    'defaults' => [[]],
    'second page and stable id sorting' => [['page' => 2]],
    'all day types' => [['jenis_hari' => 'semua']],
    'weekend location search' => [['bulan' => '9', 'jenis_hari' => 'libur', 'search' => 'Merdeka']],
    'draft' => [['status' => 'draft']],
    'empty results' => [['search' => 'absent']],
    'web fallback normalization' => [['bulan' => 'invalid', 'status' => 'invalid', 'jenis_hari' => 'invalid']],
]);

test('dashboard API preserves the complete summary and annual chart', function () {
    $this->travelTo('2026-09-15');
    $admin = User::factory()->administrator()->create();
    Lembur::factory()->create(['tanggal_kegiatan' => '2026-09-05']);
    Lembur::factory()->create(['tanggal_kegiatan' => '2026-08-04']);
    Lembur::factory()->draft()->create(['tanggal_kegiatan' => '2026-09-06']);
    Lembur::factory()->create(['tanggal_kegiatan' => '2026-09-07', 'status' => 'locked']);
    $web = $this->actingAs($admin)->get('/dashboard?bulan=invalid')->assertOk()->inertiaProps('summary');

    $this->getJson('/api/admin/dashboard?bulan=invalid', parityTokenHeaders($admin))
        ->assertOk()->assertJsonPath('data.attributes', $web);
});

test('pegawai list preserves every web field filters count and pagination', function (string $query) {
    $admin = User::factory()->administrator()->create();
    $pegawai = User::factory()->outsourcing()->create(['name' => 'Ayu', 'kode_biro' => 'TARGET', 'is_active' => false]);
    Lembur::factory()->for($pegawai)->count(2)->sequence(['tanggal_kegiatan' => '2026-09-01'], ['tanggal_kegiatan' => '2026-09-02'])->create();
    User::factory()->outsourcing()->count(16)->create();
    $web = $this->actingAs($admin)->get('/dashboard/pegawai'.$query)->assertOk()->inertiaProps();
    $api = $this->getJson('/api/admin/pegawai'.$query, parityTokenHeaders($admin))->assertOk();

    $api->assertJsonPath('meta.filters', $web['filters'])
        ->assertJsonPath('meta.total', $web['pegawai']['total'])
        ->assertJsonPath('meta.per_page', $web['pegawai']['per_page']);
    $rows = array_map(fn (array $row): array => collect($row['attributes'])->except('image')->all(), $api->json('data'));
    expect($rows)->toEqual($web['pegawai']['data']);
})->with(['', '?page=2', '?search=TARGET&status=inactive', '?status=unknown']);

test('pegawai detail and existing history API together preserve all detail props', function () {
    $this->travelTo('2026-09-15');
    $admin = User::factory()->administrator()->create();
    $pegawai = User::factory()->outsourcing()->create(['image' => 'profile.jpg']);
    Lembur::factory()->for($pegawai)->count(11)->sequence(fn ($sequence): array => ['tanggal_kegiatan' => sprintf('2026-09-%02d', $sequence->index + 1)])->create();
    Lembur::factory()->create(['tanggal_kegiatan' => '2026-09-05']);
    $query = '?bulan=9&jenis_hari=semua&pegawai='.$admin->uuid;
    $web = $this->actingAs($admin)->get('/dashboard/pegawai/'.$pegawai->uuid.$query)->assertOk()->inertiaProps();
    $headers = parityTokenHeaders($admin);
    $api = $this->getJson('/api/admin/pegawai/'.$pegawai->uuid, $headers)->assertOk();
    expect(collect($api->json('data.attributes'))->only(array_keys($web['pegawai']))->all())->toEqual($web['pegawai']);

    $history = $this->getJson('/api/admin/pegawai/'.$pegawai->uuid.'/lemburs'.$query, $headers)->assertOk();
    $history->assertJsonPath('meta.filters', $web['filters'])->assertJsonPath('meta.per_page', 10)->assertJsonPath('meta.total', 11);
    expect(array_map(fn (array $row): array => parityLemburSummary($row, $history->json('included')), $history->json('data')))->toEqual($web['history']['data']);
});

test('lembur detail API preserves photos lock actor and all summary data', function () {
    $admin = User::factory()->administrator()->create();
    $lembur = Lembur::factory()->create(['status' => 'locked', 'locked_at' => now(), 'locked_by' => $admin->id]);
    $web = $this->actingAs($admin)->get('/dashboard/lemburs/'.$lembur->uuid)->assertOk()->inertiaProps('lembur');
    $api = $this->getJson('/api/admin/lemburs/'.$lembur->uuid, parityTokenHeaders($admin))->assertOk();
    $attributes = $api->json('data.attributes');
    $summary = parityLemburSummary($api->json('data'), $api->json('included'));
    expect($summary)->toEqual(collect($web)->only(array_keys($summary))->all());
    expect($attributes['foto_kegiatan_url'])->toBe($web['foto_kegiatan']);
    expect($attributes['foto_pulang_url'])->toBe($web['foto_pulang']);
    foreach (['foto_kegiatan_at', 'foto_pulang_at', 'locked_at'] as $field) {
        expect(substr($attributes[$field], 0, 16))->toBe($web[$field]);
    }
    expect(collect($api->json('included'))->firstWhere('id', (string) $admin->id)['attributes']['name'])->toBe($web['locked_by']);
});

test('admin delete removes unlocked records and their photos', function (string $status) {
    Storage::fake('public');
    $admin = User::factory()->administrator()->create();
    $lembur = Lembur::factory()->create(['status' => $status]);
    Storage::disk('public')->put($lembur->foto_kegiatan, 'photo');
    Storage::disk('public')->put($lembur->foto_pulang, 'photo');

    $this->deleteJson('/api/admin/lemburs/'.$lembur->uuid, [], parityTokenHeaders($admin))->assertNoContent();

    $this->assertModelMissing($lembur);
    Storage::disk('public')->assertMissing([$lembur->foto_kegiatan, $lembur->foto_pulang]);
})->with(['draft', 'complete']);

test('admin delete refuses locked records and preserves their files', function () {
    Storage::fake('public');
    $admin = User::factory()->administrator()->create();
    $lembur = Lembur::factory()->create(['status' => 'locked']);
    Storage::disk('public')->put($lembur->foto_kegiatan, 'photo');

    $this->deleteJson('/api/admin/lemburs/'.$lembur->uuid, [], parityTokenHeaders($admin))->assertForbidden();

    $this->assertModelExists($lembur);
    Storage::disk('public')->assertExists($lembur->foto_kegiatan);
});

test('admin delete enforces authentication gate and uuid binding', function () {
    $lembur = Lembur::factory()->create();
    $this->deleteJson('/api/admin/lemburs/'.$lembur->uuid)->assertUnauthorized();
    $this->deleteJson('/api/admin/lemburs/'.$lembur->uuid, [], parityTokenHeaders(User::factory()->outsourcing()->create()))->assertForbidden();
    $this->deleteJson('/api/admin/lemburs/'.$lembur->uuid, [], parityTokenHeaders(User::factory()->administrator()->create(['is_active' => false])))->assertForbidden();
    $this->deleteJson('/api/admin/lemburs/'.$lembur->id, [], parityTokenHeaders(User::factory()->administrator()->create()))->assertNotFound();
    $this->assertModelExists($lembur);
});

test('draft API cannot return another employees missing activity photo', function () {
    $user = User::factory()->outsourcing()->create();
    $own = Lembur::factory()->for($user)->draft()->create();
    Lembur::factory()->create(['foto_kegiatan' => null]);
    Lembur::factory()->for($user)->create();

    $this->getJson('/api/lemburs/draft', parityTokenHeaders($user))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.attributes.uuid', $own->uuid);
});

test('existing wage route calculates wages and enforces ownership', function (string $date, int $wage) {
    $user = User::factory()->outsourcing()->create();
    $lembur = Lembur::factory()->for($user)->create(['tanggal_kegiatan' => $date]);
    $this->getJson('/api/lemburs/'.$lembur->uuid.'/upah', parityTokenHeaders($user))->assertOk()->assertJsonPath('data.upah', $wage);
    $this->getJson('/api/lemburs/'.$lembur->uuid.'/upah', parityTokenHeaders(User::factory()->create()))->assertNotFound();
})->with([['2026-09-04', 50000], ['2026-09-05', 100000]]);
