<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

it('registers a user and returns a token', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.user.email', 'ada@example.com')
        ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email'], 'token']]);

    expect(User::where('email', 'ada@example.com')->exists())->toBeTrue();
});

it('rejects registration with invalid data', function () {
    $response = $this->postJson('/api/register', [
        'name' => '',
        'email' => 'not-an-email',
        'password' => 'short',
        'password_confirmation' => 'mismatch',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['code', 'message', 'details']]);
});

it('logs in with valid credentials', function () {
    User::factory()->create([
        'email' => 'ada@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'ada@example.com',
        'password' => 'secret123',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email'], 'token']]);
});

it('rejects login with the wrong password using a safe message', function () {
    User::factory()->create([
        'email' => 'ada@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'ada@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');
});

it('revokes the token on logout so it cannot be reused', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/logout')
        ->assertNoContent();

    expect($user->fresh()->tokens()->count())->toBe(0);

    // The sanctum guard caches its resolved user per test process; force it
    // to re-resolve so the second request re-checks the (now deleted) token.
    Auth::forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/logout')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('rejects a protected route without a token', function () {
    $this->postJson('/api/links', ['target_url' => 'https://example.com'])
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated');
});
