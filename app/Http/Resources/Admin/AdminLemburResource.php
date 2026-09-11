<?php

namespace App\Http\Resources\Admin;

use App\Models\Lembur;
use App\Services\Api\LemburService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Lembur */
class AdminLemburResource extends JsonApiResource
{
    public function __construct(mixed $resource)
    {
        parent::__construct($resource);

        $this->includePreviouslyLoadedRelationships();
    }

    public function toId(Request $request): ?string
    {
        return (string) $this->resource->getKey();
    }

    public function toType(Request $request): ?string
    {
        return 'lemburs';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'uuid' => $this->resource->uuid,
            'tanggal' => $this->resource->tanggal_kegiatan->format('Y-m-d'),
            'nama_kegiatan' => $this->resource->nama_kegiatan,
            'lokasi_kegiatan' => $this->resource->lokasi_kegiatan,
            'foto_kegiatan_url' => $this->url($this->resource->foto_kegiatan),
            'foto_kegiatan_at' => $this->resource->foto_kegiatan_at?->format('Y-m-d H:i:s'),
            'foto_pulang_url' => $this->url($this->resource->foto_pulang),
            'foto_pulang_at' => $this->resource->foto_pulang_at?->format('Y-m-d H:i:s'),
            'waktu_pulang' => $this->resource->waktu_pulang?->format('H:i'),
            'jenis_hari' => $this->resource->isHariLibur() ? 'hari_libur' : 'hari_kerja',
            'upah' => app(LemburService::class)->hitungUpah($this->resource),
            'status' => $this->resource->status,
            'can_lock' => $this->resource->canBeLocked(),
            'can_delete' => $this->resource->status !== 'locked',
            'locked_at' => $this->resource->locked_at?->format('Y-m-d H:i:s'),
        ];
    }

    /** @return array<string, class-string<JsonApiResource>> */
    public function toRelationships(Request $request): array
    {
        return [
            'user' => AdminPegawaiResource::class,
            'lockedBy' => AdminPegawaiResource::class,
        ];
    }

    private function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
