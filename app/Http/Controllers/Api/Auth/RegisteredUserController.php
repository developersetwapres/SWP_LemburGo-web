<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\Api\Pmb\PmbApplicationResource;
use App\Http\Resources\Api\UserResource;
use App\Services\Auth\RegisterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RegisteredUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        RegisterRequest $request,
        RegisterService $service,
    ) {
        $user = $service->register($request->validated());
        $token = $user->createToken('auth_token')->plainTextToken;

        return (new UserResource($user))
            ->includePreviouslyLoadedRelationships()
            ->additional([
                'meta' => [
                    'message' => 'Akun pendaftaran berhasil dibuat.',
                    'token' => $token,
                ],
            ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
