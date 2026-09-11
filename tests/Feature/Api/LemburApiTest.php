<?php

use App\Models\Lembur;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function lemburApiHeaders(User $user): array
{
    return [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $user->createToken('lembur-api-test')->plainTextToken,
    ];
}

test('deleting a lembur also deletes its photos', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $lembur = Lembur::factory()->for($user)->create([
        'foto_kegiatan' => 'lembur/kegiatan/kegiatan.jpg',
        'foto_pulang' => 'lembur/pulang/pulang.jpg',
    ]);

    Storage::disk('public')->put($lembur->foto_kegiatan, 'kegiatan');
    Storage::disk('public')->put($lembur->foto_pulang, 'pulang');

    $this->deleteJson('/api/lemburs/delete/' . $lembur->uuid, [], lemburApiHeaders($user))
        ->assertOk()
        ->assertJsonPath('message', 'Lembur berhasil dihapus.');

    $this->assertDatabaseMissing('lemburs', ['id' => $lembur->id]);
    Storage::disk('public')->assertMissing('lembur/kegiatan/kegiatan.jpg');
    Storage::disk('public')->assertMissing('lembur/pulang/pulang.jpg');
});

test('a user cannot submit more than one lembur on the same date', function () {
    $user = User::factory()->create();
    $headers = lemburApiHeaders($user);
    Lembur::factory()->for($user)->create([
        'tanggal_kegiatan' => '2026-09-08',
    ]);

    $payload = [
        'tanggal_kegiatan' => '2026-09-08',
        'nama_kegiatan' => 'Kegiatan kedua',
        'lokasi_kegiatan' => 'Kantor',
    ];

    $this->postJson('/api/lemburs', $payload, $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tanggal_kegiatan']);
});

test('different users can submit lembur on the same date', function () {
    $date = '2026-09-08';
    $payload = [
        'tanggal_kegiatan' => $date,
        'nama_kegiatan' => 'Kegiatan bersama',
        'lokasi_kegiatan' => 'Kantor',
    ];
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    Lembur::factory()->for($firstUser)->create([
        'tanggal_kegiatan' => $date,
    ]);

    $this->postJson('/api/lemburs', $payload, lemburApiHeaders($secondUser))
        ->assertOk();

    expect(Lembur::query()
        ->whereIn('user_id', [$firstUser->id, $secondUser->id])
        ->whereDate('tanggal_kegiatan', $date)
        ->count())->toBe(2);
});

test('user can export only their lembur as a pdf', function () {
    Storage::fake('public');

    $user = User::factory()->create(['name' => 'Pegawai Terpilih']);
    $otherUser = User::factory()->create(['name' => 'Pegawai Lain']);
    Lembur::factory()->for($user)->create([
        'tanggal_kegiatan' => '2026-09-08',
        'nama_kegiatan' => 'Kegiatan user login',
    ]);
    Lembur::factory()->for($otherUser)->create([
        'tanggal_kegiatan' => '2026-09-08',
        'nama_kegiatan' => 'Kegiatan user lain',
    ]);

    $response = $this->get('/api/lemburs/export?bulan=2026-09', lemburApiHeaders($user))
        ->assertDownload('lembur-2026-09.pdf');

    $pdf = $response->streamedContent();

    expect($pdf)
        ->toStartWith('%PDF')
        ->toContain(mb_convert_encoding('Kegiatan user login', 'UTF-16BE', 'UTF-8'))
        ->not->toContain(mb_convert_encoding('Kegiatan user lain', 'UTF-16BE', 'UTF-8'));
});
