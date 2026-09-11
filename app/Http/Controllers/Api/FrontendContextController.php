<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\FrontendContextResource;
use App\Http\Resources\Api\UserResource;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;

class FrontendContextController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): FrontendContextResource
    {
        $user = $request->user('sanctum');

        return (new FrontendContextResource([
            'name' => config('app.name'),
            'auth' => ['user' => $user ? (new UserResource($user))->resolve($request)['data'] : null],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'appearance' => $request->cookie('appearance') ?? 'system',
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => Features::enabled(Features::registration()),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->hasSession() ? $request->session()->get('status') : null,
        ]))->additional(['meta' => ['canAccessAdminPanel' => $user?->can('access-admin-panel') ?? false]]);
    }
}
