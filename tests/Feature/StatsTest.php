<?php

use App\Models\Click;
use App\Models\Link;
use App\Models\User;

it('returns totals and clicks_by_day matching recorded clicks, with no ips in recent', function () {
    $user = User::factory()->create();
    $link = Link::factory()->for($user)->create();

    Click::factory()->for($link)->count(3)->create([
        'created_at' => now(),
        'referrer' => 'https://news.example',
    ]);
    Click::factory()->for($link)->count(2)->create([
        'created_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson("/api/links/{$link->id}/stats");

    $response->assertOk()
        ->assertJsonPath('data.totals.clicks', 5)
        ->assertJsonPath('data.link.code', $link->code);

    $byDay = collect($response->json('data.clicks_by_day'))->sum('clicks');
    expect($byDay)->toBe(5);

    foreach ($response->json('data.recent') as $click) {
        expect($click)->not->toHaveKey('ip');
    }
});

it('rejects stats for a non-owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $link = Link::factory()->for($owner)->create();

    $this->actingAs($other, 'sanctum')->getJson("/api/links/{$link->id}/stats")
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'forbidden');
});

it('returns 404 for stats on a missing link', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->getJson('/api/links/999999/stats')
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');
});
