<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class UserResource extends JsonApiResource
{
    /**
     * The resource's attributes.
     *
     * @var list<string>
     */
    public $attributes = [
        'name',
        'email',
        'role',
        'uuid',
        'image',
        'jabatan',
        'nip',
        'kode_biro',
        'is_active',
        'email_verified_at',
        'two_factor_confirmed_at',
        'created_at',
        'updated_at',
    ];

    /**
     * The resource's relationships.
     *
     * @var array<string, class-string<JsonApiResource>>
     */
    public $relationships = [
        // ...
    ];
}
