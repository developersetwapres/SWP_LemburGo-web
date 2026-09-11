<?php

use App\Models\User;
use App\Services\Api\LemburService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

test('lembur becomes complete when all photo and time attributes are filled', function () {
    Storage::fake('public');
    Auth::login(User::factory()->create());

    $lembur = app(LemburService::class)->store([
        'tanggal_kegiatan' => '2026-09-03',
        'nama_kegiatan' => 'Kegiatan lembur',
        'lokasi_kegiatan' => 'Kantor',
        'foto_kegiatan' => UploadedFile::fake()->image('kegiatan.jpg'),
        'foto_kegiatan_at' => '2026-09-03 17:00:00',
        'foto_pulang' => UploadedFile::fake()->image('pulang.jpg'),
        'foto_pulang_at' => '2026-09-03 20:00:00',
    ]);

    expect($lembur->fresh()->status)->toBe('complete');
});

test('lembur remains draft when a required photo attribute is missing', function () {
    Storage::fake('public');
    Auth::login(User::factory()->create());

    $lembur = app(LemburService::class)->store([
        'tanggal_kegiatan' => '2026-09-03',
        'nama_kegiatan' => 'Kegiatan lembur',
        'lokasi_kegiatan' => 'Kantor',
    ]);

    expect($lembur->fresh()->status)->toBe('draft');
});

test('lembur becomes complete when the second photo is added during update', function () {
    Storage::fake('public');
    Auth::login(User::factory()->create());
    $service = app(LemburService::class);

    $lembur = $service->store([
        'tanggal_kegiatan' => '2026-09-03',
        'nama_kegiatan' => 'Kegiatan lembur',
        'lokasi_kegiatan' => 'Kantor',
        'foto_kegiatan' => UploadedFile::fake()->image('kegiatan.jpg'),
        'foto_kegiatan_at' => '2026-09-03 17:00:00',
    ]);

    $service->update($lembur, [
        'tanggal_kegiatan' => '2026-09-03',
        'nama_kegiatan' => 'Kegiatan lembur',
        'lokasi_kegiatan' => 'Kantor',
        'foto_pulang' => UploadedFile::fake()->image('pulang.jpg'),
        'foto_pulang_at' => '2026-09-03 20:00:00',
    ]);

    expect($lembur->fresh()->status)->toBe('complete');
});

test('replacing a photo during update deletes the old photo', function () {
    Storage::fake('public');
    Auth::login(User::factory()->create());
    $service = app(LemburService::class);

    $lembur = $service->store([
        'tanggal_kegiatan' => '2026-09-03',
        'nama_kegiatan' => 'Kegiatan lembur',
        'lokasi_kegiatan' => 'Kantor',
        'foto_kegiatan' => UploadedFile::fake()->image('kegiatan-lama.jpg'),
        'foto_kegiatan_at' => '2026-09-03 17:00:00',
    ]);
    $oldPath = $lembur->foto_kegiatan;

    $service->update($lembur, [
        'tanggal_kegiatan' => '2026-09-03',
        'nama_kegiatan' => 'Kegiatan lembur',
        'lokasi_kegiatan' => 'Kantor',
        'foto_kegiatan' => UploadedFile::fake()->image('kegiatan-baru.jpg'),
        'foto_kegiatan_at' => '2026-09-03 18:00:00',
    ]);

    $newPath = $lembur->fresh()->foto_kegiatan;

    expect($newPath)->not->toBe($oldPath);
    expect(Storage::disk('public')->exists($oldPath))->toBeFalse();
    expect(Storage::disk('public')->exists($newPath))->toBeTrue();
});
