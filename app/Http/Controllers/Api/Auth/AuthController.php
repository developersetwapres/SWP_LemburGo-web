<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use JsonResponseTrait;

    public function login(LoginRequest $request): UserResource|JsonResponse
    {
        $user = User::query()
            ->where('email', $request->email)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->error('Email atau password salah', null, 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return (new UserResource($user))
            ->includePreviouslyLoadedRelationships()
            ->additional([
                'meta' => [
                    'message' => 'Login berhasil',
                    'token' => $token,
                ],
            ]);
    }

    public function logout(Request $request): JsonResponse
    {
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        } else {
            $request->user()->currentAccessToken()->delete();
        }

        return $this->success('Logout berhasil');
    }
}
