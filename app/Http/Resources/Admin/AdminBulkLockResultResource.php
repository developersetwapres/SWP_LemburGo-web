<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class AdminBulkLockResultResource extends JsonApiResource
{
    public function toId(Request $request): ?string
    {
        return (string) $this->resource['request_id'];
    }

    public function toType(Request $request): ?string
    {
        return 'bulk-lock-results';
    }

    /** @return array{locked_count: int} */
    public function toAttributes(Request $request): array
    {
        return [
            'locked_count' => $this->resource['locked_count'],
        ];
    }
}
