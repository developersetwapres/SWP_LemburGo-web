<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class AdminDashboardResource extends JsonApiResource
{
    public function toId(Request $request): ?string
    {
        return 'periode-'.$this->resource['bulan'];
    }

    public function toType(Request $request): ?string
    {
        return 'dashboard-summaries';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return $this->resource;
    }
}
