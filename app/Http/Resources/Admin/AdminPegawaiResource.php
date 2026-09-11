<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class AdminPegawaiResource extends JsonApiResource
{
    public function toId(Request $request): ?string
    {
        return (string) $this->resource->getKey();
    }

    public function toType(Request $request): ?string
    {
        return 'users';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'uuid' => $this->resource->uuid,
            'name' => $this->resource->name,
            'email' => $this->whenHas('email'),
            'image' => $this->whenHas('image'),
            'jabatan' => $this->resource->jabatan,
            'nip' => $this->resource->nip,
            'kode_biro' => $this->resource->kode_biro,
            'is_active' => (bool) $this->resource->is_active,
            'lemburs_count' => $this->when(
                array_key_exists('lemburs_count', $this->resource->getAttributes()),
                $this->resource->lemburs_count,
            ),
        ];
    }
}
