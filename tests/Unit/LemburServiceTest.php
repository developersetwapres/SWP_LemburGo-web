<?php

use App\Models\Lembur;
use App\Services\Api\LemburService;

it('menghitung jumlah upah lembur untuk hari kerja dan hari libur', function () {
    $service = app(LemburService::class);

    $hariKerja = new Lembur;
    $hariKerja->tanggal_kegiatan = '2026-09-02';

    $hariLibur = new Lembur;
    $hariLibur->tanggal_kegiatan = '2026-09-05';

    expect($service->hitungUpah($hariKerja))->toBe(50000)
        ->and($service->hitungUpah($hariLibur))->toBe(100000);
});
