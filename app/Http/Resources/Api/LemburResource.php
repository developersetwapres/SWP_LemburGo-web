<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Support\Facades\Storage;

class LemburResource extends JsonApiResource
{
    /**
     * The resource's attributes.
     */
    public $attributes = [
        'uuid',
        'tanggal_kegiatan',
        'nama_kegiatan',
        'lokasi_kegiatan',
        'foto_kegiatan',
        'foto_kegiatan_at',
        'foto_pulang',
        'foto_pulang_at',
        'waktu_pulang',
        'status',
    ];

    /**
     * The resource's relationships.
     */
    public $relationships = [
        // ...
    ];

    /**
     * The resource's attributes.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        $attributes = collect($this->attributes)
            ->mapWithKeys(fn(string $attribute): array => [$attribute => $this->resource->{$attribute}])
            ->all();

        $attributes['foto_kegiatan'] = $this->url($this->resource->foto_kegiatan);
        $attributes['foto_pulang'] = $this->url($this->resource->foto_pulang);

        return $attributes;
    }

    private function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
