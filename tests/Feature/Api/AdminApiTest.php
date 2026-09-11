<?php

use App\Models\Lembur;
use App\Models\User;

function adminApiToken(User $admin): string
{
    return $admin->createToken('admin-api-test')->plainTextToken;
}

function adminApiHeaders(User $admin): array
{
    return [
        'Accept' => 'application/vnd.api+json',
        'Authorization' => 'Bearer '.adminApiToken($admin),
    ];
}

test('admin api rejects unauthenticated and non-admin users', function () {
    $this->getJson('/api/admin/dashboard')->assertUnauthorized();

    $pegawai = User::factory()->outsourcing()->create();

    $this->getJson('/api/admin/dashboard', adminApiHeaders($pegawai))
        ->assertForbidden();
});

test('dashboard counts only complete lembur for the current month', function () {
    $this->travelTo('2026-09-15');

    $admin = User::factory()->administrator()->create();
    $pegawai = User::factory()->outsourcing()->create();
    Lembur::factory()->for($pegawai)->create(['tanggal_kegiatan' => '2026-09-04']);
    Lembur::factory()->for(User::factory()->outsourcing())->create(['tanggal_kegiatan' => '2026-09-05']);
    Lembur::factory()->for(User::factory()->outsourcing())->draft()->create(['tanggal_kegiatan' => '2026-09-06']);
    Lembur::factory()->for(User::factory()->outsourcing())->create(['tanggal_kegiatan' => '2026-08-31']);

    $this->getJson('/api/admin/dashboard', adminApiHeaders($admin))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'dashboard-summaries')
        ->assertJsonPath('data.attributes.bulan', '2026-09')
        ->assertJsonPath('data.attributes.total_lembur', 2)
        ->assertJsonPath('data.attributes.hari_kerja', 1)
        ->assertJsonPath('data.attributes.hari_libur', 1)
        ->assertJsonPath('data.attributes.total_upah', 150000)
        ->assertJsonCount(12, 'data.attributes.chart');
});

test('lembur index defaults to the current month and complete status with JSON API user relationship data', function () {
    $this->travelTo('2026-09-15');

    $admin = User::factory()->administrator()->create();
    $pegawai = User::factory()->outsourcing()->create(['name' => 'Dewi Sari']);
    $complete = Lembur::factory()->for($pegawai)->create([
        'tanggal_kegiatan' => '2026-09-04',
        'nama_kegiatan' => 'Rapat target',
    ]);
    Lembur::factory()->for(User::factory()->outsourcing())->draft()->create(['tanggal_kegiatan' => '2026-09-05']);
    Lembur::factory()->for(User::factory()->outsourcing())->create(['tanggal_kegiatan' => '2026-08-31']);

    $this->getJson('/api/admin/lemburs', adminApiHeaders($admin))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', (string) $complete->id)
        ->assertJsonPath('data.0.type', 'lemburs')
        ->assertJsonPath('data.0.attributes.jenis_hari', 'hari_kerja')
        ->assertJsonPath('data.0.relationships.user.data.id', (string) $pegawai->id)
        ->assertJsonPath('included.0.attributes.name', 'Dewi Sari')
        ->assertJsonPath('meta.filters.status', 'complete')
        ->assertJsonPath('meta.filters.bulan', '2026-09');
});

test('lembur index supports month, employee, status, day type, search, and pagination filters', function () {
    $this->travelTo('2026-09-15');

    $admin = User::factory()->administrator()->create();
    $pegawai = User::factory()->outsourcing()->create(['name' => 'Nadia']);
    $otherPegawai = User::factory()->outsourcing()->create();
    $target = Lembur::factory()->for($pegawai)->create([
        'tanggal_kegiatan' => '2026-09-05',
        'nama_kegiatan' => 'Rapat hari libur',
        'lokasi_kegiatan' => 'Gedung Merdeka',
    ]);
    Lembur::factory()->for($pegawai)->create([
        'tanggal_kegiatan' => '2026-09-04',
        'nama_kegiatan' => 'Rapat hari kerja',
    ]);
    $draft = Lembur::factory()->for($pegawai)->draft()->create([
        'tanggal_kegiatan' => '2026-09-06',
        'nama_kegiatan' => 'Kegiatan draft',
    ]);
    Lembur::factory()->for($otherPegawai)->create([
        'tanggal_kegiatan' => '2026-09-12',
        'nama_kegiatan' => 'Rapat pegawai lain',
    ]);

    $this->getJson('/api/admin/lemburs?bulan=9&pegawai='.$pegawai->id.'&jenis_hari=libur&search=Merdeka', adminApiHeaders($admin))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', (string) $target->id);

    $this->getJson('/api/admin/lemburs?bulan=2026-09&pegawai='.$pegawai->uuid.'&status=draft', adminApiHeaders($admin))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', (string) $draft->id);

    $this->getJson('/api/admin/lemburs?bulan=2026-09&per_page=1', adminApiHeaders($admin))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 3);
});

test('admin can view a detailed lembur with inspectable photo URLs', function () {
    $admin = User::factory()->administrator()->create();
    $pegawai = User::factory()->outsourcing()->create(['name' => 'Rama']);
    $lembur = Lembur::factory()->for($pegawai)->create([
        'foto_kegiatan' => 'lembur/kegiatan/cek.jpg',
        'foto_pulang' => 'lembur/pulang/cek.jpg',
    ]);

    $this->getJson('/api/admin/lemburs/'.$lembur->uuid, adminApiHeaders($admin))
        ->assertOk()
        ->assertJsonPath('data.id', (string) $lembur->id)
        ->assertJsonPath('data.attributes.foto_kegiatan_url', config('app.url').'/storage/lembur/kegiatan/cek.jpg')
        ->assertJsonPath('data.attributes.foto_pulang_url', config('app.url').'/storage/lembur/pulang/cek.jpg')
        ->assertJsonPath('included.0.attributes.name', 'Rama');
});

test('admin can lock one complete lembur', function () {
    $admin = User::factory()->administrator()->create();
    $lembur = Lembur::factory()->create();

    $this->postJson('/api/admin/lemburs/'.$lembur->uuid.'/lock', [], adminApiHeaders($admin))
        ->assertOk()
        ->assertJsonPath('data.attributes.status', 'locked')
        ->assertJsonPath('data.attributes.can_lock', false);

    $this->assertDatabaseHas('lemburs', [
        'id' => $lembur->id,
        'status' => 'locked',
        'locked_by' => $admin->id,
    ]);
});

test('bulk lock locks all selected complete lembur and returns its count', function () {
    $admin = User::factory()->administrator()->create();
    $first = Lembur::factory()->create();
    $second = Lembur::factory()->create();

    $this->postJson('/api/admin/lemburs/bulk-lock', ['ids' => [$first->id, $second->id]], adminApiHeaders($admin))
        ->assertOk()
        ->assertJsonPath('data.type', 'bulk-lock-results')
        ->assertJsonPath('data.attributes.locked_count', 2);

    $this->assertDatabaseCount('lemburs', 2);
    $this->assertDatabaseHas('lemburs', ['id' => $first->id, 'status' => 'locked', 'locked_by' => $admin->id]);
    $this->assertDatabaseHas('lemburs', ['id' => $second->id, 'status' => 'locked', 'locked_by' => $admin->id]);
});

test('bulk lock validates invalid rows and leaves every selected record unchanged', function () {
    $admin = User::factory()->administrator()->create();
    $complete = Lembur::factory()->create();
    $draft = Lembur::factory()->draft()->create();

    $this->postJson('/api/admin/lemburs/bulk-lock', ['ids' => [$complete->id, $draft->id]], adminApiHeaders($admin))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ids');

    $this->assertDatabaseHas('lemburs', ['id' => $complete->id, 'status' => 'complete', 'locked_at' => null]);
    $this->assertDatabaseHas('lemburs', ['id' => $draft->id, 'status' => 'draft', 'locked_at' => null]);
});

test('export uses the same active lembur filters', function () {
    $this->travelTo('2026-09-15');

    $admin = User::factory()->administrator()->create();
    $pegawai = User::factory()->outsourcing()->create();
    Lembur::factory()->for($pegawai)->create([
        'tanggal_kegiatan' => '2026-09-04',
        'nama_kegiatan' => 'Kegiatan terpilih',
    ]);
    Lembur::factory()->for(User::factory()->outsourcing())->create([
        'tanggal_kegiatan' => '2026-09-05',
        'nama_kegiatan' => 'Kegiatan lain',
    ]);

    $response = $this->get('/api/admin/lemburs/export?bulan=2026-09&search=terpilih', adminApiHeaders($admin));

    $response->assertDownload('lembur-2026-09.pdf');
    $content = $response->streamedContent();
    expect(str_contains($content, mb_convert_encoding('Kegiatan terpilih', 'UTF-16BE', 'UTF-8')))->toBeTrue();
    expect(str_contains($content, mb_convert_encoding('Kegiatan lain', 'UTF-16BE', 'UTF-8')))->toBeFalse();
});

test('pegawai list and detail expose only outsourcing staff with server-side search', function () {
    $admin = User::factory()->administrator()->create();
    $pegawai = User::factory()->outsourcing()->create(['name' => 'Citra Outsourcing']);
    User::factory()->administrator()->create(['name' => 'Admin Internal']);

    $this->getJson('/api/admin/pegawai?search=Citra', adminApiHeaders($admin))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.attributes.name', 'Citra Outsourcing')
        ->assertJsonPath('meta.filters.search', 'Citra');

    $this->getJson('/api/admin/pegawai/'.$pegawai->uuid, adminApiHeaders($admin))
        ->assertOk()
        ->assertJsonPath('data.id', (string) $pegawai->id)
        ->assertJsonPath('data.attributes.nip', $pegawai->nip);
});

test('admin can update an outsourcing staff profile and filter by active state through the shared query service', function () {
    $admin = User::factory()->administrator()->create();
    $pegawai = User::factory()->outsourcing()->create([
        'name' => 'Citra Lama',
        'jabatan' => 'Admin Gaji',
        'nip' => '1234567890',
        'is_active' => true,
    ]);

    $this->putJson('/api/admin/pegawai/'.$pegawai->uuid, [
        'name' => 'Citra Baru',
        'jabatan' => 'Supervisor Lembur',
        'nip' => '9876543210',
        'is_active' => false,
        'password' => 'secret123',
    ], adminApiHeaders($admin))
        ->assertOk()
        ->assertJsonPath('data.attributes.name', 'Citra Baru')
        ->assertJsonPath('data.attributes.jabatan', 'Supervisor Lembur')
        ->assertJsonPath('data.attributes.nip', '9876543210')
        ->assertJsonPath('data.attributes.is_active', false);

    $this->assertDatabaseHas('users', [
        'id' => $pegawai->id,
        'name' => 'Citra Baru',
        'jabatan' => 'Supervisor Lembur',
        'nip' => '9876543210',
        'is_active' => false,
    ]);

    $pegawai->refresh();
    expect(Hash::check('secret123', $pegawai->password))->toBeTrue();

    $this->getJson('/api/admin/pegawai?status=inactive', adminApiHeaders($admin))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.attributes.name', 'Citra Baru')
        ->assertJsonPath('meta.filters.status', 'inactive');
});

test('pegawai lembur history is paginated and uses the shared lembur filters', function () {
    $this->travelTo('2026-09-15');

    $admin = User::factory()->administrator()->create();
    $pegawai = User::factory()->outsourcing()->create();
    $currentMonth = Lembur::factory()->for($pegawai)->create(['tanggal_kegiatan' => '2026-09-04']);
    Lembur::factory()->for($pegawai)->create(['tanggal_kegiatan' => '2026-08-31']);

    $this->getJson('/api/admin/pegawai/'.$pegawai->uuid.'/lemburs?bulan=9&per_page=1', adminApiHeaders($admin))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', (string) $currentMonth->id)
        ->assertJsonPath('meta.filters.pegawai', $pegawai->uuid)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 1);
});
