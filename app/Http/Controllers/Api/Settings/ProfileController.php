<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Http\Resources\Api\UserResource;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function show(Request $request): UserResource
    {
        return (new UserResource($request->user()))->additional(['meta' => [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->hasSession() ? $request->session()->get('status') : null,
        ]]);
    }

    public function update(ProfileUpdateRequest $request): UserResource
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return (new UserResource($user))->additional(['meta' => ['message' => __('Profile updated.')]]);
    }

    public function destroy(ProfileDeleteRequest $request): Response
    {
        $user = $request->user();

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
        }

        $user->tokens()->delete();
        $user->delete();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }
}
