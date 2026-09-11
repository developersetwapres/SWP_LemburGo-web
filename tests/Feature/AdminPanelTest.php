<?php

use App\Models\Lembur;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('only evaluators can access the admin panel', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('admin lembur index applies server-side filters', function () {
    $admin = User::factory()->administrator()->create();
    $pegawai = User::factory()->outsourcing()->create(['name' => 'Dewi Pemeriksa']);
    $bulan = now()->format('Y-m');

    Lembur::factory()->for($pegawai)->create([
        'tanggal_kegiatan' => now()->startOfMonth()->addDay()->format('Y-m-d'),
        'nama_kegiatan' => 'Rapat target',
    ]);
    Lembur::factory()->for($pegawai)->draft()->create([
        'tanggal_kegiatan' => now()->startOfMonth()->addDays(2)->format('Y-m-d'),
        'nama_kegiatan' => 'Rapat draft',
    ]);

    config(['app.asset_url' => 'http://assets.test']);
    $version = hash('xxh128', 'http://assets.test');

    $response = $this->actingAs($admin)
        ->get(route('admin.lemburs.index', [
            'bulan' => $bulan,
            'pegawai' => $pegawai->uuid,
            'status' => 'complete',
            'search' => 'target',
        ]), ['X-Inertia' => 'true', 'X-Inertia-Version' => $version]);

    $response
        ->assertOk()
        ->assertJsonPath('component', 'admin/lemburs/index')
        ->assertJsonCount(1, 'props.lemburs.data')
        ->assertJsonPath('props.lemburs.data.0.nama_kegiatan', 'Rapat target')
        ->assertJsonPath('props.filters.status', 'complete');
});

test('admin can bulk lock complete lembur and records the locker', function () {
    $admin = User::factory()->administrator()->create();
    $lembur = Lembur::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.lemburs.bulk-lock'), ['ids' => [$lembur->id]])
        ->assertRedirect();

    $this->assertDatabaseHas('lemburs', [
        'id' => $lembur->id,
        'status' => 'locked',
        'locked_by' => $admin->id,
    ]);
});

test('admin cannot lock draft lembur', function () {
    $admin = User::factory()->administrator()->create();
    $lembur = Lembur::factory()->draft()->create();

    $this->actingAs($admin)
        ->post(route('admin.lemburs.bulk-lock'), ['ids' => [$lembur->id]])
        ->assertInvalid('ids');

    $this->assertDatabaseHas('lemburs', [
        'id' => $lembur->id,
        'status' => 'draft',
        'locked_at' => null,
    ]);
});

test('admin can delete unlocked lembur and its evidence files', function () {
    Storage::fake('public');
    $admin = User::factory()->administrator()->create();
    $lembur = Lembur::factory()->create([
        'foto_kegiatan' => 'lembur/kegiatan/admin-evidence.jpg',
        'foto_pulang' => 'lembur/pulang/admin-attendance.jpg',
    ]);
    Storage::disk('public')->put($lembur->foto_kegiatan, 'evidence');
    Storage::disk('public')->put($lembur->foto_pulang, 'attendance');

    $this->actingAs($admin)
        ->delete(route('admin.lemburs.destroy', $lembur->uuid))
        ->assertRedirect();

    $this->assertDatabaseMissing('lemburs', ['id' => $lembur->id]);
    Storage::disk('public')->assertMissing('lembur/kegiatan/admin-evidence.jpg');
    Storage::disk('public')->assertMissing('lembur/pulang/admin-attendance.jpg');
});

test('admin cannot delete locked lembur', function () {
    $admin = User::factory()->administrator()->create();
    $lembur = Lembur::factory()->locked()->create();

    $this->actingAs($admin)
        ->delete(route('admin.lemburs.destroy', $lembur->uuid))
        ->assertRedirect()
        ->assertSessionHas('flash.toast.message', 'Data lembur yang terkunci tidak dapat dihapus.');

    $this->assertDatabaseHas('lemburs', ['id' => $lembur->id]);
});

test('pdf export renders the official recap table for active filters', function () {
    Storage::fake('public');
    $admin = User::factory()->administrator()->create();
    $pegawai = User::factory()->outsourcing()->create();
    $bulan = '2026-09';

    $evidence = UploadedFile::fake()->image('evidence.jpg', 320, 240);
    $attendance = UploadedFile::fake()->image('attendance.jpg', 320, 240);
    Storage::disk('public')->put('lembur/kegiatan/evidence.jpg', file_get_contents($evidence->getPathname()));
    Storage::disk('public')->put('lembur/pulang/attendance.jpg', file_get_contents($attendance->getPathname()));

    Lembur::factory()->for($pegawai)->create([
        'tanggal_kegiatan' => '2026-09-02',
        'nama_kegiatan' => 'Kegiatan terpilih',
        'foto_kegiatan' => 'lembur/kegiatan/evidence.jpg',
        'foto_pulang' => 'lembur/pulang/attendance.jpg',
    ]);
    Lembur::factory()->for($pegawai)->create([
        'tanggal_kegiatan' => '2026-09-04',
        'nama_kegiatan' => 'Kegiatan lain',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.lemburs.export', ['bulan' => $bulan, 'search' => 'terpilih']))
        ->assertDownload('lembur-'.$bulan.'.pdf');

    $pdf = $response->streamedContent();

    expect($pdf)
        ->toStartWith('%PDF')
        ->toContain('/Subtype /Image')
        ->toContain(mb_convert_encoding('Kegiatan terpilih', 'UTF-16BE', 'UTF-8'))
        ->not->toContain(mb_convert_encoding('Kegiatan lain', 'UTF-16BE', 'UTF-8'))
        ->not->toContain(mb_convert_encoding('Foto eviden', 'UTF-16BE', 'UTF-8'))
        ->not->toContain(mb_convert_encoding('Foto presensi pulang', 'UTF-16BE', 'UTF-8'));
});

test('lembur recap blade renders the official header and table columns', function () {
    $html = view('pdf.lembur-rekap', [
        'bulan' => 'SEPTEMBER',
        'tahun' => 2026,
        'lemburs' => [[
            'tanggal' => '02-09-2026',
            'nama_lengkap' => 'Dewi Pemeriksa',
            'kegiatan' => 'Kegiatan lembur',
            'lokasi' => 'Kantor',
            'foto_eviden' => null,
            'foto_presensi_pulang' => null,
            'waktu_kepulangan' => '20:00',
        ]],
    ])->render();

    expect($html)
        ->toContain('REKAP PENGAJUAN UANG LEMBUR (DI LUAR JAM KERJA)')
        ->toContain('TEKNISI KOMPUTER, JARINGAN, DAN PROGRAMMER')
        ->toContain('BIRO TATA USAHA DAN SUMBER DAYA MANUSIA')
        ->toContain('BULAN SEPTEMBER TAHUN 2026')
        ->toContain('Tanggal')
        ->toContain('Nama Lengkap')
        ->toContain('Kegiatan/Acara')
        ->toContain('Lokasi')
        ->toContain('Lampiran Foto Eviden')
        ->toContain('Lampiran Foto Presensi Pulang')
        ->toContain('Waktu Kepulangan')
        ->toContain('Dewi Pemeriksa');
});
