<?php

use App\Http\Resources\Api\LemburResource;
use App\Models\Lembur;
use Illuminate\Http\Request;

test('lembur resource returns absolute URLs for photos', function () {
    config(['filesystems.disks.public.url' => 'https://api.example.test/storage']);

    $lembur = Lembur::factory()->create([
        'foto_kegiatan' => 'lembur/kegiatan/kegiatan.jpg',
        'foto_pulang' => 'lembur/pulang/pulang.jpg',
    ]);

    $attributes = (new LemburResource($lembur))->toAttributes(Request::create('/'));

    expect($attributes)
        ->foto_kegiatan->toBe('https://api.example.test/storage/lembur/kegiatan/kegiatan.jpg')
        ->foto_pulang->toBe('https://api.example.test/storage/lembur/pulang/pulang.jpg');
});

test('lembur resource returns null when a photo is missing', function () {
    config(['filesystems.disks.public.url' => 'https://api.example.test/storage']);

    $lembur = Lembur::factory()->draft()->create();

    $attributes = (new LemburResource($lembur))->toAttributes(Request::create('/'));

    expect($attributes)
        ->foto_kegiatan->toBeString()
        ->foto_pulang->toBeNull();
});
