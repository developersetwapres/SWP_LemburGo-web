<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/** @return array<string, string> */
function settingsTokenHeaders(User $user): array
{
    Auth::forgetGuards();

    return ['Authorization' => 'Bearer '.$user->createToken('settings')->plainTextToken];
}

test('frontend context provides guest configuration and cookie preferences', function () {
    $this->withCredentials()->withUnencryptedCookies(['appearance' => 'dark', 'sidebar_state' => 'false'])
        ->getJson('/api/frontend-context')->assertOk()
        ->assertJsonPath('data.type', 'frontend-contexts')
        ->assertJsonPath('data.attributes.auth.user', null)
        ->assertJsonPath('data.attributes.name', config('app.name'))
        ->assertJsonPath('data.attributes.appearance', 'dark')
        ->assertJsonPath('data.attributes.sidebarOpen', false)
        ->assertJsonPath('data.attributes.canResetPassword', true)
        ->assertJsonPath('meta.canAccessAdminPanel', false);
});

test('frontend context and profile preserve shared user data without secrets', function () {
    $user = User::factory()->administrator()->withTwoFactor()->create();
    $web = $this->actingAs($user)->get('/settings/profile')->assertOk()->inertiaProps();
    $headers = settingsTokenHeaders($user);
    $context = $this->getJson('/api/frontend-context', $headers)->assertOk();
    $attributes = $context->json('data.attributes.auth.user.attributes');
    expect($attributes)->toEqual(collect($web['auth']['user'])->except('id')->all());
    $context->assertJsonPath('meta.canAccessAdminPanel', true)
        ->assertJsonMissingPath('data.attributes.auth.user.attributes.password')
        ->assertJsonMissingPath('data.attributes.auth.user.attributes.two_factor_secret');

    $this->getJson('/api/settings/profile', $headers)->assertOk()
        ->assertJsonPath('data.attributes', $attributes)
        ->assertJsonPath('meta.mustVerifyEmail', $web['mustVerifyEmail'])
        ->assertJsonPath('meta.status', $web['status']);
});

test('profile update reuses validation and clears verification only for changed email', function (bool $changeEmail) {
    $user = User::factory()->create();
    $email = $changeEmail ? 'changed@example.com' : $user->email;
    $this->patchJson('/api/settings/profile', ['name' => 'Updated', 'email' => $email, 'role' => ['administrator']], settingsTokenHeaders($user))
        ->assertOk()->assertJsonPath('data.attributes.name', 'Updated');

    expect($user->refresh()->email)->toBe($email);
    expect($user->email_verified_at === null)->toBe($changeEmail);
    expect($user->role)->toBe(['pegawai']);
})->with([true, false]);

test('profile update rejects duplicate email and missing name with 422', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->patchJson('/api/settings/profile', ['email' => $other->email], settingsTokenHeaders($user))
        ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email']);
    expect($user->fresh()->email)->toBe($user->email);
});

test('password update validates current password and confirmation', function (array $payload, string $error) {
    $user = User::factory()->create();
    $this->putJson('/api/settings/password', $payload, settingsTokenHeaders($user))->assertUnprocessable()->assertJsonValidationErrors($error);
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
})->with([
    [['current_password' => 'wrong', 'password' => 'new-password', 'password_confirmation' => 'new-password'], 'current_password'],
    [['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'different'], 'password'],
]);

test('password update works with existing bearer authentication and is throttled', function () {
    $user = User::factory()->create();
    $headers = settingsTokenHeaders($user);
    $this->putJson('/api/settings/password', ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password'], $headers)->assertNoContent();
    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->putJson('/api/settings/password', [], $headers)->assertUnprocessable();
    }
    $this->putJson('/api/settings/password', [], $headers)->assertTooManyRequests();
});

test('account deletion validates password and revokes all tokens', function () {
    $user = User::factory()->create();
    $user->createToken('other-device');
    $headers = settingsTokenHeaders($user);
    $this->deleteJson('/api/settings/profile', ['password' => 'wrong'], $headers)->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->assertModelExists($user);
    $this->deleteJson('/api/settings/profile', ['password' => 'password'], $headers)->assertNoContent();
    $this->assertModelMissing($user);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('settings endpoints reject guests', function (string $method, string $path) {
    $this->json($method, '/api/settings/'.$path)->assertUnauthorized();
})->with([['GET', 'profile'], ['PATCH', 'profile'], ['DELETE', 'profile'], ['GET', 'security'], ['PUT', 'password']]);

test('security settings require a confirmed session and preserve all web props', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $this->actingAs($user)->withHeader('Origin', 'http://localhost:3000')
        ->getJson('/api/settings/security')->assertStatus(423);
    $web = $this->withSession(['auth.password_confirmed_at' => time()])
        ->get('/settings/security')->assertOk()->inertiaProps();

    $this->getJson('/api/settings/security')->assertOk()->assertJsonPath('data.attributes', collect($web)->only([
        'canManageTwoFactor', 'canManagePasskeys', 'passkeys', 'passwordRules', 'twoFactorEnabled', 'requiresConfirmation',
    ])->all());
});

test('security settings reject bearer only access rather than bypassing password confirmation', function () {
    $user = User::factory()->create();
    $this->getJson('/api/settings/security', settingsTokenHeaders($user))->assertUnauthorized();
});

test('security settings honor disabled Fortify features', function () {
    config(['fortify.features' => []]);
    $user = User::factory()->create();
    $this->actingAs($user)->withHeader('Origin', 'http://localhost:3000')
        ->withSession(['auth.password_confirmed_at' => time()])->getJson('/api/settings/security')->assertOk()
        ->assertJsonPath('data.attributes.canManageTwoFactor', false)
        ->assertJsonPath('data.attributes.canManagePasskeys', false)
        ->assertJsonPath('data.attributes.passkeys', [])
        ->assertJsonMissingPath('data.attributes.twoFactorEnabled');
});

test('security API preserves passkey order display fields and confirmed two factor state', function () {
    $this->freezeTime();
    $user = User::factory()->withTwoFactor()->create();
    $user->passkeys()->create(['name' => 'Older', 'credential_id' => 'older', 'credential' => []])
        ->forceFill(['created_at' => now()->subDays(2), 'last_used_at' => now()->subDay()])->save();
    $user->passkeys()->create(['name' => 'Newer', 'credential_id' => 'newer', 'credential' => []]);
    User::factory()->create()->passkeys()->create(['name' => 'Other user', 'credential_id' => 'other', 'credential' => []]);
    $web = $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
        ->get('/settings/security')->assertOk()->inertiaProps();

    $this->withHeader('Origin', 'http://localhost:3000')->getJson('/api/settings/security')->assertOk()
        ->assertJsonPath('data.attributes.passkeys', $web['passkeys'])
        ->assertJsonPath('data.attributes.passkeys.0.name', 'Newer')
        ->assertJsonPath('data.attributes.twoFactorEnabled', true)
        ->assertJsonMissingPath('data.attributes.passkeys.0.credential');
});

test('Fortify JSON login requires two factor and accepts a recovery code before API access', function () {
    $user = User::factory()->withTwoFactor()->create();
    $this->withHeader('Origin', 'http://localhost:3000')->postJson('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk()->assertJsonPath('two_factor', true);
    $this->getJson('/api/settings/profile')->assertUnauthorized();
    $this->postJson('/two-factor-challenge', ['recovery_code' => 'recovery-code-1'])->assertNoContent();
    $this->getJson('/api/settings/profile')->assertOk()->assertJsonPath('data.attributes.email', $user->email);
    expect($user->fresh()->recoveryCodes())->not->toContain('recovery-code-1');
});

test('Next frontend can reuse Fortify JSON login password confirmation and session API', function () {
    $user = User::factory()->administrator()->create();
    $this->withHeader('Origin', 'http://localhost:3000')->getJson('/sanctum/csrf-cookie')->assertNoContent();
    $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertOk()->assertJsonPath('two_factor', false);
    Auth::forgetGuards();
    $this->getJson('/api/settings/profile')->assertOk()->assertJsonPath('data.attributes.email', $user->email);
    $this->postJson('/user/confirm-password', ['password' => 'password'])->assertCreated();
    $this->getJson('/api/settings/security')->assertOk();
    $this->deleteJson('/api/auth/logout')->assertOk();
    $this->assertGuest('web');
});

test('existing token login still returns full user data and logout revokes only current token', function () {
    $user = User::factory()->create();
    $other = $user->createToken('other')->accessToken;
    $login = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk()
        ->assertJsonPath('data.attributes.uuid', $user->uuid)->assertJsonMissingPath('data.attributes.password');
    $this->deleteJson('/api/auth/logout', [], ['Authorization' => 'Bearer '.$login->json('meta.token')])->assertOk();
    $this->assertDatabaseCount('personal_access_tokens', 1);
    $this->assertModelExists($other);
});

test('credentialed CORS supports Fortify and API requests including CSRF and PDF headers', function (string $path) {
    $this->options($path, [], ['Origin' => 'http://localhost:3000', 'Access-Control-Request-Method' => 'POST', 'Access-Control-Request-Headers' => 'X-XSRF-TOKEN,Content-Type'])
        ->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
        ->assertHeader('Access-Control-Allow-Credentials', 'true');
})->with(['/login', '/user/confirm-password', '/api/settings/profile', '/passkeys/login']);

test('allowed cross-site SPA origin receives a readable CSRF token and private-network permission', function () {
    config(['cors.allowed_origins' => ['https://developersetwapres.github.io']]);

    $csrf = $this->withHeader('Origin', 'https://developersetwapres.github.io')
        ->getJson('/sanctum/csrf-cookie')->assertNoContent();
    $preflight = $this->options('/login', [], [
        'Origin' => 'https://developersetwapres.github.io',
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'X-CSRF-TOKEN,Content-Type',
        'Access-Control-Request-Private-Network' => 'true',
    ])->assertNoContent();

    expect($csrf->headers->get('X-CSRF-TOKEN'))->toBeString()->not->toBeEmpty();
    $csrf->assertHeader('Access-Control-Expose-Headers', 'Content-Disposition, X-CSRF-TOKEN');
    $preflight->assertHeader('Access-Control-Allow-Origin', 'https://developersetwapres.github.io')
        ->assertHeader('Access-Control-Allow-Credentials', 'true')
        ->assertHeader('Access-Control-Allow-Private-Network', 'true');
});

test('disallowed cross-site origin cannot read CSRF or private-network headers', function () {
    config(['cors.allowed_origins' => ['https://developersetwapres.github.io']]);

    $csrf = $this->withHeader('Origin', 'https://malicious.example')
        ->getJson('/sanctum/csrf-cookie')->assertNoContent();
    $preflight = $this->options('/login', [], [
        'Origin' => 'https://malicious.example',
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Private-Network' => 'true',
    ])->assertNoContent();

    expect($csrf->headers->has('X-CSRF-TOKEN'))->toBeFalse();
    expect($preflight->headers->has('Access-Control-Allow-Private-Network'))->toBeFalse();
});

test('stateful API writes require CSRF outside the test middleware exemption', function () {
    $user = User::factory()->create();
    $this->app->instance('env', 'local');
    $this->actingAs($user)->withHeader('Origin', 'http://localhost:3000')
        ->patchJson('/api/settings/profile', ['name' => 'Changed', 'email' => $user->email])->assertStatus(419);
    expect($user->fresh()->name)->toBe($user->name);
});
