<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class FrontendContextResource extends JsonApiResource
{
    public function toId(Request $request): ?string
    {
        return 'current';
    }

    public function toType(Request $request): ?string
    {
        return 'frontend-contexts';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return $this->resource;
    }
}
