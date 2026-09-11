<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLemburIndexRequest;
use App\Http\Requests\Admin\AdminPegawaiIndexRequest;
use App\Http\Requests\Admin\AdminPegawaiUpdateRequest;
use App\Http\Resources\Admin\AdminLemburResource;
use App\Http\Resources\Admin\AdminPegawaiResource;
use App\Models\User;
use App\Services\Admin\LemburQuery;
use App\Services\Admin\PegawaiQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

class PegawaiController extends Controller
{
    public function index(
        AdminPegawaiIndexRequest $request,
        PegawaiQuery $pegawaiQuery,
    ): AnonymousResourceCollection {
        $filters = $pegawaiQuery->filters($request->validated());
        $pegawai = $pegawaiQuery->forAdmin($filters)
            ->paginate($pegawaiQuery->perPage($request->validated()))
            ->withQueryString();

        return AdminPegawaiResource::collection($pegawai)
            ->additional(['meta' => ['filters' => $filters]]);
    }

    public function show(User $pegawai): AdminPegawaiResource
    {
        abort_unless($pegawai->hasRole('outsourcing'), 404);

        return new AdminPegawaiResource($pegawai);
    }

    public function update(
        AdminPegawaiUpdateRequest $request,
        User $pegawai,
    ): AdminPegawaiResource {
        abort_unless($pegawai->hasRole('outsourcing'), 404);

        $payload = $request->validated();

        if (array_key_exists('is_active', $payload)) {
            $payload['is_active'] = filter_var($payload['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        if (! empty($payload['password'])) {
            $payload['password'] = Hash::make($payload['password']);
        }

        if (array_key_exists('status', $payload)) {
            $payload['is_active'] = $payload['status'] === 'active';
            unset($payload['status']);
        }

        $pegawai->fill(array_intersect_key($payload, array_flip([
            'name',
            'jabatan',
            'nip',
            'is_active',
            'password',
        ])));

        $pegawai->save();

        return new AdminPegawaiResource($pegawai->fresh());
    }

    public function lemburs(
        AdminLemburIndexRequest $request,
        User $pegawai,
        LemburQuery $lemburQuery,
    ): AnonymousResourceCollection {
        abort_unless($pegawai->hasRole('outsourcing'), 404);

        $filters = $lemburQuery->filters([
            ...$request->validated(),
            'pegawai' => $pegawai->uuid,
        ]);
        $lemburs = $lemburQuery->forAdmin($filters)
            ->paginate($lemburQuery->perPage($request->validated(), 10))
            ->withQueryString();

        return AdminLemburResource::collection($lemburs)
            ->additional(['meta' => ['filters' => $filters]]);
    }
}
