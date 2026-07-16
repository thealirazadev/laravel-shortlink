<?php

use App\Models\User;

it('creates a link with a unique code and short url', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/links', [
        'target_url' => 'https://example.com/very/long/path',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.target_url', 'https://example.com/very/long/path')
        ->assertJsonStructure(['data' => ['id', 'code', 'short_url', 'target_url', 'expires_at', 'created_at']]);

    $code = $response->json('data.code');
    expect($response->json('data.short_url'))->toBe(config('app.url').'/'.$code);

    $this->assertDatabaseHas('links', ['user_id' => $user->id, 'code' => $code]);
});

it('rejects a non-http target url', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/links', ['target_url' => 'not-a-url'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');
});

it('rejects an expires_at in the past', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/links', [
            'target_url' => 'https://example.com',
            'expires_at' => now()->subDay()->toIso8601String(),
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');
});

it('generates distinct codes for duplicate create requests', function () {
    $user = User::factory()->create();

    $first = $this->actingAs($user, 'sanctum')->postJson('/api/links', ['target_url' => 'https://example.com']);
    $second = $this->actingAs($user, 'sanctum')->postJson('/api/links', ['target_url' => 'https://example.com']);

    $first->assertCreated();
    $second->assertCreated();
    expect($first->json('data.code'))->not->toBe($second->json('data.code'));
});
