<?php

use App\Models\Click;
use App\Models\Link;

it('redirects an active link and records exactly one click with a hashed ip', function () {
    $link = Link::factory()->create(['target_url' => 'https://example.com/target']);

    $response = $this->withHeaders(['Referer' => 'https://referrer.example'])
        ->get('/'.$link->code);

    $response->assertStatus(302)->assertRedirect('https://example.com/target');

    $this->assertDatabaseCount('clicks', 1);

    $click = Click::first();
    expect($click->link_id)->toBe($link->id)
        ->and($click->referrer)->toBe('https://referrer.example')
        ->and($click->ip)->not->toBeNull()
        ->and($click->ip)->not->toBe('127.0.0.1');
});

it('returns 404 for an unknown code', function () {
    $this->get('/does-not-exist')->assertStatus(404);
});
