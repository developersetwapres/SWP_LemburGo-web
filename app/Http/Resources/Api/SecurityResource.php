<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class SecurityResource extends JsonApiResource
{
    public function toId(Request $request): ?string
    {
        return (string) $request->user()->getAuthIdentifier();
    }

    public function toType(Request $request): ?string
    {
        return 'security-settings';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return $this->resource;
    }
}
