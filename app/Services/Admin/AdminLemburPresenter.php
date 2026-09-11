<?php

namespace App\Services\Admin;

use App\Models\Lembur;
use App\Services\Api\LemburService;
use Illuminate\Support\Facades\Storage;

class AdminLemburPresenter
{
    public function __construct(private LemburService $lemburService) {}

    /** @return array<string, mixed> */
    public function summary(Lembur $lembur): array
    {
        return [
            'id' => $lembur->id,
            'uuid' => $lembur->uuid,
            'tanggal' => $lembur->tanggal_kegiatan->format('Y-m-d'),
            'nama_kegiatan' => $lembur->nama_kegiatan,
            'lokasi_kegiatan' => $lembur->lokasi_kegiatan,
            'waktu_pulang' => $lembur->waktu_pulang?->format('H:i'),
            'status' => $lembur->status,
            'jenis_hari' => $lembur->isHariLibur() ? 'libur' : 'kerja',
            'upah' => $this->lemburService->hitungUpah($lembur),
            'can_lock' => $lembur->canBeLocked(),
            'can_delete' => $lembur->status !== 'locked',
            'pegawai' => [
                'uuid' => $lembur->user->uuid,
                'name' => $lembur->user->name,
                'nip' => $lembur->user->nip,
                'jabatan' => $lembur->user->jabatan,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function detail(Lembur $lembur): array
    {
        return [
            ...$this->summary($lembur),
            'foto_kegiatan' => $this->url($lembur->foto_kegiatan),
            'foto_kegiatan_at' => $lembur->foto_kegiatan_at?->format('Y-m-d H:i'),
            'foto_pulang' => $this->url($lembur->foto_pulang),
            'foto_pulang_at' => $lembur->foto_pulang_at?->format('Y-m-d H:i'),
            'locked_at' => $lembur->locked_at?->format('Y-m-d H:i'),
            'locked_by' => $lembur->lockedBy?->name,
        ];
    }

    private function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
